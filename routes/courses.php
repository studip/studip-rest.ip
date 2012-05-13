<?php
// TODO Alle Besonderheiten einer Veranstaltungen abbilden (Anmeldeverfahren?)
// TODO Veranstaltungen, die mehr als ein Semester laufen?
// TODO /courses POST, je nach Status: In Veranstaltung eintragen bzw. bearbeiten
// TODO Studiengruppen
// TODO Nutzer anzeigen?

namespace RestIP;
use \DBManager, \PDO;

require_once 'lib/classes/Modules.class.php';

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

    function routes(&$router)
    {
        //
        $router->get('/courses', function () use ($router)
        {
            $courses = Course::load();

            $semesters = array();
            $users     = array();
            foreach ($courses as $course) {
                if (!isset($semesters[$course['semester_id']])) {
                    $semester = $router->dispatch('get', '/semesters/:semester_id', $course['semester_id']);
                    $semesters[$course['semester_id']] = $semester['semester'];
                }

                foreach ($course['teachers'] + $course['tutors'] + $course['students'] as $user_id) {
                    if (!isset($users[$user_id])) {
                        $user = $router->dispatch('get', '/user(/:user_id)', $user_id);
                        $users[$user_id] = $user['user'];
                    }
                }
            }

            $courses   = array_values($courses);
            $semesters = array_values($semesters);
            $users     = array_values($users);

            $router->render(compact('courses', 'semesters', 'users'));
        });

        //
        $router->get('/courses/semester', function () use ($router) {
            $courses = Course::load();
            
            $semesters = array();
            foreach ($courses as $course) {
                if (!isset($semesters[$course['semester_id']])) {
                    $semester = $router->dispatch('get', '/semesters/:semester_id', $course['semester_id']);
                    $semesters[$course['semester_id']] = $semester['semester'];
                }
            }

            $semesters = array_values($semesters);

            $router->render(compact('semesters'));
        });

        //
        $router->get('/courses/semester/:semester_id', function ($semester_id) use ($router) {
            $temp = $router->dispatch('get', '/semesters/:semester_id', $semester_id);
            $semester = $temp['semester'];

            $courses  = Course::load();

            $users = array();
            foreach ($courses as &$course) {
                if ($course['semester_id'] != $semester_id) {
                    unset($course);
                }

                foreach ($course['teachers'] + $course['tutors'] as $user_id) {
                    if (!isset($users[$user_id])) {
                        $user = $router->dispatch('get', '/user(/:user_id)', $user_id);
                        $users[$user_id] = $user['user'];
                    }
                }
            }

            $courses = array_values($courses);
            $users   = array_values($users);

            $router->render(compact('courses', 'semester', 'users'));
        });

        //
        $router->get('/courses/:course_id', function ($course_id) use ($router) {
            $course = Course::load($course_id);
            if (!$course) {
                $router->halt(404, sprintf('Course %s not found', $course_id));
            }
            $router->render(compact('course'));
        });
    }
}

class Course
{
    static function load($ids = null, $additional_fields = array())
    {
        if (is_array($ids) && empty($ids)) {
            return array();
        }

        $additional_fields = implode(',', $additional_fields);

        $query = "SELECT sem.Seminar_id AS course_id, IF(sem.status=99, su.mkdate, start_time) AS start_time,
                         duration_time, 
                         Name AS title, Untertitel AS subtitle, sem.status AS type, modules,
                         Beschreibung AS description, Ort AS location
                  FROM seminar_user AS su
                  JOIN seminare AS sem ON su.seminar_id = sem.Seminar_id
                  WHERE user_id = ? OR 1";
        if (func_num_args() > 0) {
            $query .= " AND sem.Seminar_id IN (?)";
            if (is_array($ids) && count($ids) > 1) {
                $query .= " ORDER BY start_time DESC";
            }
        }

        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($GLOBALS['user']->id, $ids));
        $courses = $statement->fetchAll(PDO::FETCH_ASSOC);

        $query = "SELECT user_id
                  FROM seminar_user
                  WHERE Seminar_id = ? AND status = ? AND visible = 'yes'
                  ORDER BY position ASC";
        $statement = DBManager::get()->prepare($query);

        $modules = new \Modules;

        foreach ($courses as &$course) {
            $course['modules'] = $modules->getLocalModules($course['course_id'], 'sem');
            foreach ($course['modules'] as &$module) {
                $module = (bool)$module;
            }

            $course['semester_id'] = Helper::getSemester($course['start_time']);

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