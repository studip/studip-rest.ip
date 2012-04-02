<?php

/**
 * 
 **/
class SemesterRoute implements APIPlugin
{
    /**
     * 
     **/
    public function describeRoutes()
    {
        return array(
            '/semester/:semester_id' => _('Semesterinformationen'),
        );
    }

    /**
     * 
     **/
    public function routes(&$router)
    {
        $router->get('/semester/:semester_id', function ($semester_id) use (&$router) {
            $temp = SemesterData::getInstance()->getSemesterData($semester_id);
            
            $semester = array(
                'semester_id'    => $temp['semester_id'],
                'title'          => $temp['name'],
                'description'    => $temp['description'],
                'begin'          => $temp['beginn'],
                'end'            => $temp['ende'],
                'seminars_begin' => $temp['vorles_beginn'],
                'seminars_end'   => $temp['vorles_ende'],
            );

            $router->value($semester);
        });
    }
}
