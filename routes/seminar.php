<?php

namespace RestIP;

/**
 * 
 **/
class SeminarRoute implements \APIPlugin
{
    /**
     * 
     **/
    public function describeRoutes()
    {
        return array(
            '/seminar/:seminar_id' => _('Veranstaltungsinformationen'),
        );
    }

    /**
     * 
     **/
    public function routes(&$router)
    {
        $router->get('/seminar/:seminar_id', function ($seminar_id) use (&$router) {
            $query = "SELECT Seminar_id AS seminar_id, Name AS title, Untertitel AS subtitle, status AS type 
                      FROM seminare
                      WHERE Seminar_id = ?";
            $statement = \DBManager::get()->prepare($query);
            $statement->execute(array($seminar_id));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);

            $router->value($result);
        });
    }
}
