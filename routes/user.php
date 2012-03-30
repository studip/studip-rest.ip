<?
class UserRoute implements APIPlugin
{
    public function describeRoutes()
    {
        return array(
            '/user/courses(/:user_id)' => _('Veranstaltungen eines Nutzers'),
            '/user(/:user_id)'         => _('Nutzerdaten'),
        );
    }
    
    public function routes(&$router)
    {
        $router->get('/user/courses(/:user_id)', function ($user_id = null) use ($router)
        {
            $user_id = $user_id ?: $GLOBALS['user']->id;
            
            $semesters = SemesterData::GetSemesterArray();

            $getSemester = function ($timestamp) use ($semesters) {
                foreach ($semesters as $semester) {
                    if ($timestamp >= $semester['beginn'] and $timestamp <= $semester['ende']) {
                        return $semester;
                    }
                }

                return false;
            };

            $query = "SELECT sem.Seminar_id, start_time, Name, Untertitel "
                   . "FROM seminar_user AS su "
                   . "JOIN seminare AS sem ON su.seminar_id = sem.Seminar_id "
                   . "WHERE user_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array($user_id));
            $seminars = $statement->fetchAll(PDO::FETCH_ASSOC);
            
            $temp = array();
            foreach ($seminars as $seminar) {
                $semester = $getSemester($seminar['start_time']);
                if (!isset($temp[$semester['semester_id']])) {
                    $temp[$semester['semester_id']] = array(
                        'name'     => $semester['name'],
                        'begin'    => $semester['beginn'],
                        'end'      => $semester['ende'],
                        'seminars' => array(),
                        'seminar_start' => $semester['vorles_beginn'],
                        'seminar_ende'  => $semester['vorles_ende'],
                    );
                }
                $temp[$semester['semester_id']]['seminars'][$seminar['Seminar_id']] = array(
                    'seminar_id' => $seminar['Seminar_id'],
                    'title'      => $seminar['Name'],
                    'subtitle'   => $seminar['Untertitel'],
                );
            }
            $semesters = $temp;
            
            $router->value(compact('semesters'));
        });
        
        $router->get('/user(/:user_id)', function ($user_id) use ($router)
        {
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

            $router->value(compact('user'));
        });
    }
}
