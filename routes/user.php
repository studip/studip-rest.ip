<?php

namespace RestIP;
use \Avatar, \DBManager, \PDO, \User, \Request;

/**
 *
 **/
class UserRoute implements \APIPlugin
{
    /**
     *
     **/
    public function describeRoutes()
    {
        return array(
            '/user(/:user_id)'          => _('Nutzerdaten'),
            '/user/:user_id/institutes' => _('Einrichtung eines Nutzers'),
            '/user/:user_id/courses'    => _('Veranstaltungen eines Nutzers'),
            '/users'                    => _('Nutzersuche'),
        );
    }

    public static function before()
    {
        require_once 'lib/classes/UserManagement.class.php';
        require_once 'lib/user_visible.inc.php';
    }

    /**
     *
     **/
    public function routes(&$router)
    {
        //
        $router->get('/user(/:user_id)', function ($user_id = null) use ($router)
        {
            $user_id = $user_id ?: $GLOBALS['user']->id;

            $user = User::find($user_id);
            $is_visible = $GLOBALS['user']->id === $user_id || get_visibility_by_id($user_id);
            if (!$user || !$is_visible) {
                $router->halt(404, sprintf('User %s not found', $user_id));
                return;
            }

            $visibilities = get_local_visibility_by_id($user_id, 'homepage');
            if (is_array(json_decode($visibilities, true))) {
                $visibilities = json_decode($visibilities, true);
            } else {
                $visibilities = array();
            }

            $get_field = function ($field, $visibility) use ($user_id, $user, $visibilities) {
                if (!$user[$field]
                    || !is_element_visible_for_user($GLOBALS['user']->id, $user_id, $visibilities[$visibility]))
                {
                    return '';
                }
                return $user[$field];
            };

            $avatar = function ($size) use ($user_id, $visibilities) {
                static $avatar;
                if (!$avatar) {
                    $avatar_id = is_element_visible_for_user($GLOBALS['user']->id, $user_id, $visibilities['picture'])
                               ? $user_id : 'nobody';
                    $avatar = Avatar::getAvatar($avatar_id);
                }
                return $avatar->getURL($size);
            };

            $user = array(
                'user_id'       => $user_id,
                'username'      => $user['username'],
                'perms'         => $user['perms'],
                'title_pre'     => $user['title_front'],
                'forename'      => $user['Vorname'],
                'lastname'      => $user['Nachname'],
                'title_post'    => $user['title_rear'],
                'email'         => get_visible_email($user_id),
                'avatar_small'  => $avatar(Avatar::SMALL),
                'avatar_medium' => $avatar(Avatar::MEDIUM),
                'avatar_normal' => $avatar(Avatar::NORMAL),
                'phone'         => $get_field('privatnr', 'private_phone'),
                'homepage'      => $get_field('Home', 'homepage'),
                'privadr'       => strip_tags($get_field('privadr', 'privadr')),
            );

            $query = "SELECT value
                      FROM user_config
                      WHERE field = ? AND user_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array('SKYPE_NAME', $user_id));
            $user['skype'] = $statement->fetchColumn() ?: '';
            $statement->closeCursor();

            if ($user['skype']) {
                $statement->execute(array('SKYPE_ONLINE_STATUS', $user_id));
                $user['skype_show'] = (bool)$statement->fetchColumn();
            } else {
                $user['skype_show'] = false;
            }

            header('Cache-Control: private');
            $router->expires('+1 day');
            $router->render(compact('user'));
        });

        // Deletes a user
        $router->delete('/user/:user_id', function ($user_id) use ($router) {
            $user = new \UserManagement($user_id.'.');
            if (empty($user->user_data['auth_user_md5.user_id'])) {
                $router->halt(404, sprintf('User id "%s" not found', $user_id));
                die;
            }
            $router->halt($user->deleteUser() ? 200 : 500);
        });

        $router->get('/user/:user_id/institutes', function ($user_id) use ($router) {
            $query = "SELECT i0.Institut_id AS institute_id, i0.Name AS name,
                             inst_perms AS perms, sprechzeiten AS consultation,
                             raum AS room, ui.telefon AS phone, ui.fax,
                             i0.Strasse AS street, i0.Plz AS city,
                             i1.Name AS faculty_name, i1.Strasse AS faculty_street,
                             i1.Plz AS faculty_city
                      FROM user_inst AS ui
                      JOIN Institute AS i0 USING (Institut_id)
                      LEFT JOIN Institute AS i1 ON (i0.fakultaets_id = i1.Institut_id)
                      WHERE visible = 1 AND user_id = :user_id
                      ORDER BY priority ASC";
            $statement = DBManager::get()->prepare($query);
            $statement->bindValue(':user_id', $user_id);
            $statement->execute();

            $institutes = array(
                'work'  => array(),
                'study' => array(),
            );
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $index = ($row['perms'] !== 'dozent')
                       ? 'study'
                       : 'work';
                $institutes[$index][] = $row;
            }

            $router->render(compact('institutes'));
        });

        $router->get('/user/:user_id/courses', function ($user_id) use ($router) {
            $query = "SELECT Seminar_id AS course_id, su.status AS perms,
                             Veranstaltungsnummer AS event_number,
                             s.Name AS name, Untertitel AS subtitle,
                             sd.semester_id, sd.name AS semester_name
                      FROM seminar_user AS su
                      JOIN seminare AS s USING (Seminar_id)
                      LEFT JOIN semester_data AS sd ON (s.start_time BETWEEN sd.beginn AND sd.ende)
                      WHERE user_id = :user_id AND su.visible != 'no'
                      ORDER BY s.start_time DESC";
            $statement = DBManager::get()->prepare($query);
            $statement->bindValue(':user_id', $user_id);
            $statement->execute();

            $courses = array();
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $index = ($row['perms'] !== 'dozent')
                       ? 'study'
                       : 'work';
                $courses[$index][] = $row;
            }
            $router->render(compact('courses'));
        });

        $router->get('/users', function () use ($router) {
            $needle = trim(Request::get('q') ?: Request::get('needle'));

            if (!$needle) {
                $router->halt(400, 'Missing needle');
            }

            $query = "SELECT user_id
                      FROM auth_user_md5
                      LEFT JOIN user_info USING(user_id)
                      WHERE (TRIM(CONCAT(title_front, Vorname, Nachname, title_rear)) LIKE CONCAT('%', REPLACE(:needle, ' ', ''), '%')
                         OR TRIM(CONCAT(Nachname, Vorname)) LIKE CONCAT('%', REPLACE(:needle, ' ', ''), '%')
                         OR username = :needle)
                         AND visible != 'no'
                      ORDER BY Nachname, Vorname";
            $statement = DBManager::get()->prepare($query);
            $statement->bindValue(':needle', $needle);
            $statement->execute();
            $ids = $statement->fetchAll(PDO::FETCH_COLUMN);
            
            $users = array();
            foreach ($ids as $id) {
                $user = reset($router->dispatch('get', '/user(/:user_id)', $id));
                $user['name'] = trim($user['title_pre'] . ' ' . $user['forename'] . ' ' . $user['lastname'] . ' ' . $user['title_post']);
                $users[] = $user;
            }
            $router->render(compact('users'));
        });
    }
}
