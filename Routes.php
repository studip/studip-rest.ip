<?php

class Routes
{
    public static function setRoutes(&$router) {
        self::newsRoute($router);
        self::userRoute($router);
    }

    private static function newsRoute(&$router) {
        $router->get('/news(/:range_id)', function ($range_id = false) use ($router) {
            $range_id = $range_id ?: $GLOBALS['user']->id;

            require_once 'lib/classes/StudipNews.class.php';

            $data = StudipNews::GetNewsByRange($range_id);

            // Adjust data
            array_walk($data, function (&$item) {
                $item['body']       = formatReady($item['body']);
                $item['chdate_uid'] = trim($item['chdate_uid']);
            });

            $router->data['data'] = array('news' => array_values($data));
        });
    }

    private static function userRoute(&$router) {
        $router->get('/user/courses', function () use ($router) {
            $query = "SELECT sem.Seminar_id FROM seminar_user AS su JOIN seminare AS sem ON su.seminar_id = sem.Seminar_id WHERE user_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array($GLOBALS['user']->id));
            $seminars = $statement->fetchAll(PDO::FETCH_ASSOC);
            
            $router->data['data'] = compact('seminars');
        });
        
        $router->get('/user(/:user_id)', function ($user_id) use ($router) {
            $user_id = $user_id ?: $GLOBALS['user']->id;

            $user = User::find($user_id);
            if (!$user) {
                $router->data['data'] = array('user' => false);
                return;
            }
            
            $visibilities = get_local_visibility_by_id($user_id, 'homepage');
            if (is_array(json_decode($visibilities, true))) {
                $visibilities = json_decode($visibilities, true);
            } else {
                $visibilities = array();
            }
            
            $get_field = function ($field, $visibility) use ($user_id, $user, $visibilities) {
                if (!$user[$field] || !is_element_visible_for_user($GLOBALS['user']->id, $user_id, $visibilities[$visibility])) {
                    return false;
                }
                return $user[$field]; 
            };

            $avatar = function ($size) use ($user_id, $visibilities) {
                static $avatar;
                if (!$avatar) {
                    $avatar_id = is_element_visible_for_user($GLOBALS['user']->id, $user_id, $visibilities['picture']) ? $user_id : 'nobody';
                    $avatar = Avatar::getAvatar($avatar_id);
                }
                return $avatar->getURL($size);
            };

            $data = array(
                'user_id'       => $user_id,
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

            $router->data['data'] = array('user' => $data);
        });
    }
}
