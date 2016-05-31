<?php

namespace RestIP;

/**
 * 
 **/
class SemesterRoute implements \APIPlugin
{
    /**
     * 
     **/
    public function describeRoutes()
    {
        return array(
            '/semesters'              => _('Semester'),
            '/semesters/:semester_id' => _('Semesterinformationen'),
        );
    }

    public static function before()
    {
        require_once 'lib/classes/SemesterData.class.php';
    }

    /**
     * 
     **/
    public function routes(&$router)
    {
        $router->get('/semesters', function () use ($router) {
            $temp = \SemesterData::GetSemesterArray();
            
            $semesters = array();
            foreach ($temp as $sem) {
                if ($sem['semester_id']) {
                    $semesters[$sem['semester_id']] = reset($router->dispatch('get', '/semesters/:semester_id', $sem['semester_id']));
                }
            }
            
            $semesters = array_values($semesters);

            $router->render(compact('semesters'));
        });
        
        $router->get('/semesters/:semester_id', function ($semester_id) use ($router) {
            $temp = \SemesterData::getInstance()->getSemesterData($semester_id);
            if (!$temp) {
                $router->halt(404, sprintf('Semester "%s" not found', $semester_id));
            }
            
            $semester = array(
                'semester_id'        => $temp['semester_id'],
                'title'              => $temp['name'],
                'description'        => $temp['description'],
                'begin'              => $temp['beginn'],
                'end'                => $temp['ende'],
                'seminars_begin'     => $temp['vorles_beginn'],
                'seminars_end'       => $temp['vorles_ende'],
                'begin_iso'          => date('c',  $temp['beginn']),
                'end_iso'            => date('c',  $temp['ende']),
                'seminars_begin_iso' => date('c',  $temp['vorles_beginn']),
                'seminars_end_iso'   => date('c',  $temp['vorles_ende']),
            );
            $router->render(compact('semester'));
        });
    }
}
