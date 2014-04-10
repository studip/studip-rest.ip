<?php
namespace RestIP;
use \Institute, \APIPlugin;

class FacultiesRoute implements APIPlugin
{
    public function describeRoutes()
    {
        return array();
    }

    public function routes(&$router)
    {
        $router->get('/faculties', function () use ($router) {
            $temp = Institute::findBySQL('Institut_id = fakultaets_id ORDER BY Name ASC');
            $faculties = array_map(function ($faculty) {
                $temp = $faculty->toArray();

                return array(
                    'faculty_id' => $temp['id'],
                    'name'       => $temp['name'],
                    'email'      => $temp['email'],
                    'phone'      => $temp['telefon'],
                    'fax'        => $temp['fax'],
                    'street'     => $temp['strasse'],
                    'city'       => $temp['plz'],
                );
            }, $temp);

            $router->render(compact('faculties'));
        });
    }
}
