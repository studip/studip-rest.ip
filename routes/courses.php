<?php
// TODO Alle Besonderheiten einer Veranstaltungen abbilden (Anmeldeverfahren?)
// TODO Veranstaltungen, die mehr als ein Semester laufen?
// TODO /courses POST, je nach Status: In Veranstaltung eintragen bzw. bearbeiten
// TODO Studiengruppen
// TODO Nutzer anzeigen?

namespace RestIP;
use \DBManager, \PDO, \Modules, \StudipCacheFactory, \AutoInsert, \Semester;

class CoursesRoute implements \APIPlugin
{
    function describeRoutes()
    {
        return array(
            '/courses'                       => _('Veranstaltungen'),
            '/courses/:course_id'            => _('Veranstaltungsinformationen'),
            '/courses/semester'              => _('Belegte Semester'),
            '/courses/semester/:semester_id' => _('Veranstaltungen eines Semesters'),
            '/courses/overview'              => _('Übersicht Veranstaltungsinhalte'),
        );
    }

    public static function before()
    {
        require_once 'lib/classes/Modules.class.php';
    }

    function routes(&$router)
    {
        //
        $router->get('/courses', function () use ($router)
        {
            $courses = Course::load(null, @$_REQUEST['order'] == 'name');
            $courses = array_values($courses);

            $router->render(compact('courses'));
        });

        //
        $router->get('/courses/semester', function () use ($router) {
            $courses = Course::load(null, @$_REQUEST['order'] == 'name');

            $semesters = CoursesRoute::extractSemesters($courses, $router);

            $router->render(compact('semesters'));
        });

        //
        $router->get('/courses/semester/:semester_id', function ($semester_id) use ($router) {
            $temp        = $router->dispatch('get', '/semesters/:semester_id', $semester_id);
            $semester    = $temp['semester'];
            $semester_id = $semester['semester_id'];

            $courses  = Course::load(null, @$_REQUEST['order'] == 'name');
            $courses = array_filter($courses, function ($x) use ($semester_id) {
                return $x['semester_id'] === $semester_id;
            });
            $courses = array_values($courses);

            $router->render(compact('courses'));
        });

        //
        $router->get('/courses/:course_id', function ($course_id) use ($router) {
            $course = Course::load($course_id, @$_REQUEST['order'] == 'name');
            if (!$course) {
                $router->halt(404, sprintf('Course %s not found', $course_id));
            }

            $router->render(compact('course'));
        });

        $router->get('/courses/:course_id/members(/:status)', function ($course_id, $status = null) use ($router) {
            $course = Course::load($course_id, @$_REQUEST['order'] == 'name');
            if (!$course) {
                $router->halt(404, sprintf('Course %s not found', $course_id));
            }

            if ($status !== null && !in_array($status, words('students tutors teachers'))) {
                $router->halt(406, sprintf('Status "%s" is not acceptable', $status));
            }

            $members = array();
            foreach (words($status ?: 'students tutors teachers') as $status) {
                $members[$status] = $course[$status];
            }

            $router->render(compact('members'));
        });

        $router->get('/courses/overview', function () use ($router)
        {
            require_once 'lib/meine_seminare_func.inc.php';
            $courses = Course::load(null, @$_REQUEST['order'] == 'name');
            $my_sem = array();
            foreach($courses as $course) {
                unset($course['teachers']);
                unset($course['tutors']);
                unset($course['students']);
                $my_sem[$course['course_id']] = array(
                    'name'       => $course['title'],
                    'chdate'     => $course['chdate'],
                    'start_time' => $course['start_time'],
                    'modules'    => $course['modules'],
                    'visitdate'  => $course['visitdate'],
                    'status'     => $course['status'],
                    'obj_type'   => 'sem');
            }
            \get_my_obj_values($my_sem, $GLOBALS['user']->id);
            $slot_mapper = array(
                    'files' => "documents",
                    'elearning' => "elearning_interface"
                );
            foreach($courses as $course_key => $course) {
                unset($courses[$course_key]['teachers']);
                unset($courses[$course_key]['tutors']);
                unset($courses[$course_key]['students']);
                unset($courses[$course_key]['modules']);
                $navigation = array();
                $active_modules = array();
                if (function_exists('getPluginNavigationForSeminar')) { //since 2.4
                    $sem_class = $GLOBALS['SEM_CLASS'][$GLOBALS['SEM_TYPE'][$course['status']]['class']];
                    $plugin_navigation = getPluginNavigationForSeminar($course['course_id'], $course['visitdate']);
                    foreach (words('forum participants files news scm schedule wiki vote literature elearning') as $key) {
                        if ($sem_class) {
                            $slot = isset($slot_mapper[$key]) ? $slot_mapper[$key] : $key;
                            $module = $sem_class->getModule($slot);
                            if (is_a($module, "StandardPlugin")) {
                                $navigation[$key] = $plugin_navigation[get_class($module)];
                                unset($plugin_navigation[get_class($module)]);
                            } else {
                                $navigation[$key] = $my_obj_values[$key];
                            }
                        } else {
                            $navigation[$key] = $my_obj_values[$key];
                        }
                    }
                    $navigation = array_merge($navigation, $plugin_navigation);

                } else {
                    foreach (words('forum participants files news scm schedule wiki vote literature elearning') as $key) {
                        $navigation[$key] = $my_sem[$course['course_id']][$key];
                    }
                    foreach (\PluginEngine::getPlugins('StandardPlugin', $course['course_id']) as $plugin) {
                        $navigation[] = $plugin->getIconNavigation($course['course_id'], $my_sem[$course['course_id']]['visitdate']);
                    }
                }

                foreach ($navigation as $key => $nav) {
                    if (isset($nav) && $nav->isVisible(true)) {
                        $url = \UrlHelper::getUrl($course['url'] . '&redirect_to=' . strtr($nav->getURL(), '?', '&'));
                        $image = $nav->getImage();
                        $icon = $image['src'];
                        $text = $image['title'];
                        $badge_number = method_exists($nav, 'getBadgeNumber') ? $nav->getBadgeNumber() : false;
                        $new = strpos($icon,'/red/') !== false || $badge_number > 0;
                        $active_modules[$key] = compact('url','icon','text','new','badge_number');
                    }
                }

                $courses[$course_key]['active_modules'] = $active_modules;
            }
            $router->render(compact('courses'));
        });

    }

    public static function extractSemesters($collection, $router)
    {
        if (isset($collection['students']) or isset($collection['tutors']) or isset($collection['teachers'])) {
            $collection = array($collection);
        }

        $semesters = array();
        foreach ($collection as $item) {
            if ($item['semester_id'] && !isset($semesters[$item['semester_id']])) {
                $semester = $router->dispatch('get', '/semesters/:semester_id', $item['semester_id']);
                $semesters[$item['semester_id']] = $semester['semester'];
            }
        }
        return array_values($semesters);
    }
}

class Course
{
    static function load($ids = null, $order_by_name = false)
    {
        if (is_array($ids) && empty($ids)) {
            return array();
        }

        $query = "SELECT sem.Seminar_id AS course_id, start_time, sem.chdate,
                         duration_time, VeranstaltungsNummer AS `number`,
                         Name AS title, Untertitel AS subtitle, sem.status AS type, modules,
                         Beschreibung AS description, Ort AS location, gruppe,
                         IFNULL(visitdate, 0) AS visitdate, su.status
                  FROM seminare AS sem
                  LEFT JOIN seminar_user AS su ON (sem.Seminar_id = su.seminar_id AND su.user_id = ?)
                  LEFT JOIN object_user_visits ouv ON (ouv.object_id = su.seminar_id AND ouv.user_id = su.user_id AND ouv.type = 'sem')";
        $parameters = array($GLOBALS['user']->id);

        if ($ids !== null) {
            $query .= " WHERE sem.Seminar_id IN (?)";
            $parameters[] = $ids;
            if (is_array($ids) && count($ids) > 1) {
                $query .= $order_by_name
                        ? " ORDER BY title ASC"
                        : " ORDER BY start_time DESC";
            }
        } else {
            $semester_cur = Semester::findCurrent();
            if (time() >= $semester_cur->vorles_ende) {
                $semester_cur = Semester::findNext();
            }
            $semester_old = Semester::findByTimestamp(time() - 365 * 24 * 60 * 60);

            $query .= " WHERE su.user_id IS NOT NULL AND start_time <= ? AND (? <= start_time + duration_time OR duration_time = -1) ORDER BY title ASC";
            $parameters[] = $semester_cur->beginn;
            $parameters[] = $semester_old->beginn;
        }

        $statement = DBManager::get()->prepare($query);
        $statement->execute($parameters);
        $courses = $statement->fetchAll(PDO::FETCH_ASSOC);

        $query = "SELECT user_id
                  FROM seminar_user
                  JOIN auth_user_md5 USING (user_id)
                  WHERE Seminar_id = ? AND status = ? AND seminar_user.visible = 'yes'";
        if ($order_by_name) {
            $query .= " ORDER BY Nachname ASC, Vorname ASC";
        } else {
            $query .= " ORDER BY position ASC";
        }
        // limit list to 500 users max
        $query .= ' LIMIT 500';
        $statement = DBManager::get()->prepare($query);

        $modules = new Modules;
        $colors  = self::loadColors();

        foreach ($courses as &$course) {
            $course['modules'] = $modules->getLocalModules($course['course_id'], 'sem', $course['modules'], $course['type']);
            foreach ($course['modules'] as &$module) {
                $module = (bool)$module;
            }

            $course['semester_id'] = Helper::getSemester($course['start_time'], $course['duration_time']) ?: Helper::getSemester();

            $statement->execute(array($course['course_id'], 'dozent'));
            $course['teachers'] = $statement->fetchAll(PDO::FETCH_COLUMN) ?: array();
            $statement->closeCursor();

            $statement->execute(array($course['course_id'], 'tutor'));
            $course['tutors'] = $statement->fetchAll(PDO::FETCH_COLUMN) ?: array();
            $statement->closeCursor();

            if (AutoInsert::checkSeminar($course['course_id'])) {
                $course['students'] = array();
            } else {
                $statement->execute(array($course['course_id'], 'autor'));
                $course['students'] = $statement->fetchAll(PDO::FETCH_COLUMN) ?: array();
                $statement->closeCursor();
            }

            $course['color'] = $colors[$course['gruppe'] ?: 0];

            $course['url'] = $GLOBALS['ABSOLUTE_URI_STUDIP']."seminar_main.php?auswahl=".$course['course_id'];

            unset($course['gruppe']);

            $course['location'] = strip_tags($course['location']);
        }

        return (func_num_args() === 0 || is_array($ids) || $ids === null)
            ? $courses
            : reset($courses);
    }

    public static function loadColors()
    {
        $cache  = StudipCacheFactory::getCache();
        $colors = unserialize($cache->read('/rest.ip/group_colors'));

        if (!$colors) {
            $colors = array();
            if (file_exists('assets/stylesheets/less/tables.less')) {
                $less = file_get_contents('assets/stylesheets/less/tables.less');

                $matched = preg_match_all('/\.gruppe(\d) \{ background: (\#[a-f0-9]{3,6}); \}/', $less, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    if (strlen($match[2]) === 4) {
                        $match[2] = '#' . $match[2][1] . $match[2][1] . $match[2][2] . $match[2][2] . $match[2][3] . $match[2][3];
                    }
                    $colors[$match[1]] = $match[2];
                }
            } else {
                $colors = array('#ffffff', '#ff0000', '#ff9933', '#ffcc66', '#99ff99',
                                '#66cc66', '#6699cc', '#666699', '#000000');
            }

            $cache->write('/rest.ip/group_colors', serialize($colors), 7 * 24 * 60 * 60);
        }
        return $colors;
    }
}
