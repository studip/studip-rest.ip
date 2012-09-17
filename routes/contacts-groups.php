<?php
namespace RestIP;
use \APIPlugin, \DBManager, \PDO, \Request, \User;

class ContactsGroupsRoute implements APIPlugin
{
    public function describeRoutes()
    {
        return array(
            '/contacts' => _('Kontakte'),
        );
    }

    public static function before()
    {
        require_once 'lib/statusgruppe.inc.php';
    }

    public function routes(&$router)
    {
        // Get all contact groups
        $router->get('/contacts/groups', function () use ($router) {
            $groups = ContactsGroups::load($GLOBALS['user']->id);

            $users = array();
            foreach ($groups as $index => $group) {
                $members = ContactsGroups::loadMembers($GLOBALS['user']->id, $group['group_id']);

                if (!$router->compact()) {
                    foreach ($members as $user_id) {
                        if (!isset($users[$user_id])) {
                            $user = $router->dispatch('get', '/user(/:user_id)', $user_id);
                            $users[$user_id] = $user['user'];
                        }
                    }
                }

                $groups[$index]['members'] = $members;
            }

            $router->render($router->compact() ? compact('groups') : compact('groups', 'users'));
        });

        // Create new contact group
        $router->post('/contacts/groups', function () use ($router) {
            $name = trim(Request::get('name'));
            if (!$name) {
                $router->halt(406, 'No name provided');
            }

            AddNewStatusgruppe($name, $GLOBALS['user']->id, 0);
            $router->render($router->dispatch('get', '/contacts/groups'));
        });

        // Get members of contact group
        $router->get('/contacts/groups/:group_id', function ($group_id) use ($router) {
            $group = ContactsGroups::loadGroup($group_id);
            if (!$group) {
                $router->halt(404, 'Contact group "%s" not found', $group_id);
            }

            $group['members'] = ContactsGroups::loadMembers($GLOBALS['user']->id, $group_id);

            if ($router->compact()) {
                $router->render(compact('group'));
            }

            $users = array();
            foreach ($group['members'] as $user_id) {
                $user = $router->dispatch('get', '/user(/:user_id)', $user_id);
                $users[] = $user['user'];
            }
            $router->render(compact('group', 'users'));
        });

        // Remove contact group
        $router->delete('/contacts/groups/:group_id', function ($group_id) use ($router) {
            if ($group_id === 'unassigned') {
                $router->halt(403, 'You cannot delete the group "unassigned"');
            }
            if (!ContactsGroups::exists($group_id)) {
                $router->halt(404, 'Contact group "%s" not found', $group_id);
            }
            DeleteStatusgruppe($group_id);
            $router->halt(200, 'Contact group "%s" has been deleted', $group_id);
        });

        // Put a user into contact group
        $router->put('/contacts/groups/:group_id/:user_id', function ($group_id, $user_id) use ($router) {
            if ($group_id === 'unassigned') {
                $router->halt(403, 'You cannot put a user into the group "unassigned". Remove him from all assigned contact groups instead.');
            }
            if (!ContactsGroups::exists($group_id)) {
                $router->halt(404, 'Contact group "%s" not found', $group_id);
            }
            $user = User::find($user_id);
            if (!$user) {
                $router->halt(404, 'User "%s" not found', $user_id);
            }
            if (!InsertPersonStatusgruppe($user_id, $group_id)) {
//                $router->halt(500);
            }
            $router->render($router->dispatch('get', '/contacts/groups/:group_id', $group_id));
        });

        // Remove user from contact group
        $router->delete('/contacts/groups/:group_id/:user_id', function ($group_id, $user_id) use ($router) {
            if ($group_id === 'unassigned') {
                $router->halt(403, 'You cannot remove a user from the group "unassigned". Remove him from your contacts instead.');
            }
            if (!ContactsGroups::exists($group_id)) {
                $router->halt(404, 'Contact group "%s" not found', $group_id);
            }

            $user = User::find($user_id);
            if (!$user) {
                $router->halt(404, 'User "%s" not found', $user_id);
            }
            $username = $user->username;

            RemovePersonStatusgruppe($username, $group_id);

            $router->halt(200);
        });
    }
}

class ContactsGroups
{
    static function exists($group_id)
    {
        $query = "SELECT 1 FROM statusgruppen WHERE statusgruppe_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($group_id));
        return $statement->fetchColumn();
    }

    static function load($user_id)
    {
        $query = "SELECT statusgruppe_id AS group_id, name FROM statusgruppen WHERE range_id = ? ORDER BY position ASC";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($user_id));
        $groups = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        $groups['unassigned'] = self::loadGroup('unassigned');
        return $groups;
    }

    static function loadGroup($group_id)
    {
        if ($group_id === 'unassigned') {
            return array(
                'group_id' => 'unassigned',
                'name'     => _('Nicht zugeordnet'),
            );
        }
        $query = "SELECT statusgruppe_id AS group_id, name FROM statusgruppen WHERE statusgruppe_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($group_id));
        return $statement->fetch(PDO::FETCH_ASSOC);
    }
    
    static function loadUnassigned($user_id)
    {
        $query = "SELECT user_id
                  FROM contact
                  WHERE owner_id = :user_id AND user_id NOT IN(
                      SELECT user_id
                      FROM statusgruppen
                      JOIN statusgruppe_user USING (statusgruppe_id)
                      WHERE range_id = :user_id
                  )";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    static function loadMembers($user_id, $group_id)
    {
        if ($group_id === 'unassigned') {
            return self::loadUnassigned($user_id);
        }
        $query = "SELECT user_id
                  FROM statusgruppen
                  JOIN statusgruppe_user USING (statusgruppe_id)
                  WHERE range_id = ? AND statusgruppe_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($user_id, $group_id));
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }
}
