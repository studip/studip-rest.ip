<?php
namespace RestIP;
use \DBManager, \PDO, \Request;

class ContactsRoute implements \APIPlugin
{
    public function describeRoutes()
    {
        return array(
            '/contacts' => _('Kontakte'),
        );
    }

    public static function before()
    {
        @include_once 'lib/contact.inc.php';
    }

    public function routes(&$router)
    {

        // Get all contact
        $router->get('/contacts', function () use ($router) {
            $contacts = Contacts::load($GLOBALS['user']->id);

            if ($router->compact()) {
                $router->render(compact('contacts'));
                return;
            }

            $users = array();
            foreach ($contacts as $user_id) {
                $user = $router->dispatch('get', '/user(/:user_id)', $user_id);
                $users[] = $user['user'];
            }
            $router->render(compact('contacts', 'users'));
        });

        // Add contact
        $router->put('/contacts/:user_id', function ($user_id) use ($router) {
            $user = \User::find($user_id);
            if (!$user) {
                $router->halt(404, 'User "%s" not found', $user_id);
            }

            $contact_id = Contacts::locate($GLOBALS['user']->id, $user_id);
            if ($contact_id) {
                $router->halt(406, 'User "%s" is already a contact', $user_id);
            }

            Contacts::add($user_id);

            $router->render($router->dispatch('get', '/contacts'));
        });

        // Remove contact
        $router->delete('/contacts/:user_id', function ($user_id) use ($router) {
            $user = \User::find($user_id);
            if (!$user) {
                $router->halt(404, 'User "%s" not found', $user_id);
            }

            $contact_id = Contacts::locate($GLOBALS['user']->id, $user_id);
            if (!$contact_id) {
                $router->halt(406, 'User "%s" is not a contact', $user_id);
            }
            Contacts::delete($contact_id);

            $router->halt(200, 'Contact "%s" has been removed', $user_id);
        });
    }
}

class Contacts
{
    static function locate($user_id, $contact_id)
    {
        $query = "SELECT *
                  FROM contact
                  WHERE owner_id = ? AND user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($user_id, $contact_id));
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return @$result['contact_id'] ?: $result['user_id'];
    }

    static function load($user_id)
    {
        $query = "SELECT user_id
                  FROM contact
                  JOIN auth_user_md5 USING (user_id)
                  WHERE owner_id = ?
                  ORDER BY Nachname ASC, Vorname ASC";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($user_id));
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    static function add($user_id)
    {
        if (function_exists('AddNewContact')) {
            AddNewContact($user_id);
        } else {
            $user_to_add = \User::find($user_id);
            if ($user_to_add) {
                $new_contact = array(
                    'owner_id' => \User::findCurrent()->id,
                    'user_id'  => $user_to_add->id);
                \Contact::import($new_contact)->store();
            }
        }
    }

    static function delete($contact_id)
    {
        if (function_exists('DeleteContact')) {
            DeleteContact($contact_id);
        } else {
            $contact = \Contact::find(array(\User::findCurrent()->id, $contact_id));
            if ($contact) {
                $contact->delete();
            }
        }
    }
}
