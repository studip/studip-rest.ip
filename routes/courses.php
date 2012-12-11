<?php
// TODO Alle Besonderheiten einer Veranstaltungen abbilden (Anmeldeverfahren?)
// TODO Veranstaltungen, die mehr als ein Semester laufen?
// TODO /courses POST, je nach Status: In Veranstaltung eintragen bzw. bearbeiten
// TODO Studiengruppen
// TODO Nutzer anzeigen?

namespace RestIP;
use \DBManager, \PDO, \Modules, \StudipCacheFactory;

class CoursesRoute implements \APIPlugin
{
    function describeRoutes()
    {
        return array(
            '/courses'                       => _('Veranstaltungen'),
            '/courses/:course_id'            => _('Veranstaltungsinformationen'),
            '/courses/semester'              => _('Belegte Semester'),
            '/courses/semester/:semester_id' => _('Veranstaltungen eines Semesters'),
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
            $courses = Course::load();
            $courses   = array_values($courses);

            if ($router->compact()) {
                $router->render(compact('courses'));
                return;
            }

            $semesters = CoursesRoute::extractSemesters($courses, $router);
            $users     = CoursesRoute::extractUsers($courses, $router);

            $router->render(compact('courses', 'semesters', 'users'));
        });

        //
        $router->get('/courses/semester', function () use ($router) {
            $courses = Course::load();

            $semesters = CoursesRoute::extractSemesters($courses, $router);

            $router->render(compact('semesters'));
        });

        //
        $router->get('/courses/semester/:semester_id', function ($semester_id) use ($router) {
            $temp = $router->dispatch('get', '/semesters/:semester_id', $semester_id);
            $semester = $temp['semester'];

            $courses  = Course::load();
            $courses = array_values($courses);

            if ($router->compact()) {
                $router->render(compact('courses'));
                return;
            }

            foreach ($courses as &$course) {
                if ($course['semester_id'] != $semester_id) {
                    unset($course);
                }
            }

            $users = CoursesRoute::extractUsers($courses, $router);

            $router->render(compact('courses', 'semester', 'users'));
        });

        //
        $router->get('/courses/:course_id', function ($course_id) use ($router) {
            $course = Course::load($course_id);
            if (!$course) {
                $router->halt(404, sprintf('Course %s not found', $course_id));
            }

            if ($router->compact()) {
                $router->render(compact('course'));
            }

            $semesters = CoursesRoute::extractSemesters($course, $router);
            $users     = CoursesRoute::extractUsers($course, $router);

            $router->render(compact('course', 'semesters', 'users'));
        });

        $router->get('/courses/:course_id/members(/:status)', function ($course_id, $status = null) use ($router) {
            $course = Course::load($course_id);
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

            if (!$router->compact()) {
                $users = CoursesRoute::extractUsers($members, $router);
            }

            if (count($members) == 1) {
                $members = reset($members);
            }

            $router->render($router->compact() ? compact('members') : compact('members', 'users'));
        });
    }

    public static function extractUsers($collection, $router)
    {
        if (isset($collection['students']) or isset($collection['tutors']) or isset($collection['teachers'])) {
            $collection = array($collection);
        }

        $users = array();
        foreach ($collection as $item) {
            foreach (words('students tutors teachers') as $status) {
                foreach ($item[$status] as $user_id) {
                    if (!isset($users[$user_id])) {
                        $user = $router->dispatch('get', '/user(/:user_id)', $user_id);
                        $users[$user_id] = $user['user'];
                    }
                }
            }
        }
        return array_values($users);
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
    static function load($ids = null)
    {
        if (is_array($ids) && empty($ids)) {
            return array();
        }

        $query = "SELECT sem.Seminar_id AS course_id, start_time,
                         duration_time, VeranstaltungsNummer AS `number`,
                         Name AS title, Untertitel AS subtitle, sem.status AS type, modules,
                         Beschreibung AS description, Ort AS location, gruppe
                  FROM seminare AS sem
                  LEFT JOIN seminar_user AS su ON (sem.Seminar_id = su.seminar_id AND su.user_id = ?)";
        $parameters = array($GLOBALS['user']->id);

        if (func_num_args() > 0) {
            $query .= " WHERE sem.Seminar_id IN (?)";
            $parameters[] = $ids;
            if (is_array($ids) && count($ids) > 1) {
                $query .= " ORDER BY start_time DESC";
            }
        } else {
            $query .= " WHERE su.user_id IS NOT NULL";
        }

        $statement = DBManager::get()->prepare($query);
        $statement->execute($parameters);
        $courses = $statement->fetchAll(PDO::FETCH_ASSOC);

        $query = "SELECT user_id
                  FROM seminar_user
                  WHERE Seminar_id = ? AND status = ? AND visible != 'no'
                  ORDER BY position ASC";
        $statement = DBManager::get()->prepare($query);

        $modules = new Modules;
        $colors  = self::loadColors();

        foreach ($courses as &$course) {
            $course['modules'] = $modules->getLocalModules($course['course_id'], 'sem');
            foreach ($course['modules'] as &$module) {
                $module = (bool)$module;
            }

            $course['semester_id'] = Helper::getSemester($course['start_time']) ?: Helper::getSemester();

            $statement->execute(array($course['course_id'], 'dozent'));
            $course['teachers'] = $statement->fetchAll(PDO::FETCH_COLUMN) ?: array();
            $statement->closeCursor();

            $statement->execute(array($course['course_id'], 'tutor'));
            $course['tutors'] = $statement->fetchAll(PDO::FETCH_COLUMN) ?: array();
            $statement->closeCursor();

            $statement->execute(array($course['course_id'], 'autor'));
            $course['students'] = $statement->fetchAll(PDO::FETCH_COLUMN) ?: array();
            $statement->closeCursor();

            $course['color'] = $colors[$course['gruppe'] ?: 0];
            unset($course['gruppe']);
        }

        return (func_num_args() === 0 || is_array($ids))
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