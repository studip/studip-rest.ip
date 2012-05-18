<?php

namespace RestIP;
use \Avatar, \DBManager, \PDO, \User;

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
            '/user(/:user_id)'         => _('Nutzerdaten'),
        );
    }

    /**
     *
     **/
    public function routes(&$router)
    {
        //
        $router->get('/user(/:user_id)', function ($user_id) use ($router)
        {
            $user_id = $user_id ?: $GLOBALS['user']->id;

            $user = User::find($user_id);
            if (!$user) {
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
                    return false;
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
                'privadr'       => $get_field('privadr', 'privadr'),
            );

            $router->render(compact('user'));
        });

        // Deletes a user
        $router->delete('/user/:user_id', function ($user_id) use ($router) {
            require 'lib/classes/UserManagement.class.php';
            $user = new \UserManagement($user_id.'.');
            if (empty($user->user_data['auth_user_md5.user_id'])) {
                $router->halt(404, sprintf('User id "%s" not found', $user_id));
                die;
            }
            $router->halt($user->deleteUser() ? 200 : 500);
        });
    }
}
