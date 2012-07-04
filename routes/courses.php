<?php
// TODO Alle Besonderheiten einer Veranstaltungen abbilden (Anmeldeverfahren?)
// TODO Veranstaltungen, die mehr als ein Semester laufen?
// TODO /courses POST, je nach Status: In Veranstaltung eintragen bzw. bearbeiten
// TODO Studiengruppen
// TODO Nutzer anzeigen?

namespace RestIP;
use \DBManager, \PDO;

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
                         duration_time,
                         Name AS title, Untertitel AS subtitle, sem.status AS type, modules,
                         Beschreibung AS description, Ort AS location
                  FROM seminare AS sem";
        if (func_num_args() > 0) {
            $query .= " WHERE sem.Seminar_id IN (?)";
            $parameter = $ids;
            if (is_array($ids) && count($ids) > 1) {
                $query .= " ORDER BY start_time DESC";
            }
        } else {
            $query .= " LEFT JOIN seminar_user AS su ON sem.Seminar_id = su.seminar_id
                        WHERE user_id = ?";
            $parameter = $GLOBALS['user']->id;
        }

        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($parameter));
        $courses = $statement->fetchAll(PDO::FETCH_ASSOC);

        $query = "SELECT user_id
                  FROM seminar_user
                  WHERE Seminar_id = ? AND status = ? AND visible != 'no'
                  ORDER BY position ASC";
        $statement = DBManager::get()->prepare($query);

        $modules = new \Modules;

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
        }

        return (func_num_args() === 0 || is_array($ids))
            ? $courses
            : reset($courses);
    }
}