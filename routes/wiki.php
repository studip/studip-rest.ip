<?php
namespace RestIP;
use \DBManager, \PDO;

class WikiRoute implements \APIPlugin
{
    public function describeRoutes()
    {
        return array(
            '/courses/:course_id/wiki'       => _('Wikiseitenindex'),
            '/courses/:course_id/wiki/:page' => _('Wikiseite'),
        );
    }
    
    public function before()
    {
        require_once 'lib/wiki.inc.php';
    }

    public function routes(&$router)
    {
        $router->get('/courses/:course_id/wiki', function ($course_id) use ($router) {
            $query = "SELECT DISTINCT keyword
                      FROM wiki
                      WHERE range_id = ?
                      ORDER BY keyword ASC";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array($course_id));
            $keywords = $statement->fetchAll(PDO::FETCH_COLUMN);

            $router->render(compact('keywords'));
        })->conditions(array('course_id' => '[0-9a-f]{32}'));

        $router->get('/courses/:course_id/wiki/:page(/:version)', function ($course_id, $page, $version = null) use ($router) {
            $query = "SELECT *
                      FROM wiki
                      WHERE range_id = ? AND keyword = ? AND version = IFNULL(?, version)
                      ORDER BY version DESC
                      LIMIT 1";
            $statement = DBManager::get()->prepare($query);
            $statement->execute(array($course_id, $page, $version));
            $page = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$page) {
                $router->halt(404, sprintf('Course "%s" has no wiki page "%s"', $course_id, $page));
            }

            $page['body_original'] = $page['body'];
            $page['body']          = wikiReady($page['body']);
            
            // Rename range_id to course_id
            $page['course_id'] = $page['range_id'];
            unset($page['range_id']);

            if ($router->compact()) {
                $router->render(compact('page'));
                return;
            }

            $users = array();
            $users[$page['user_id']] = reset($router->dispatch('get', '/user(/:user_id)', $page['user_id']));

            $router->render(compact('page', 'users'));
        });
    }
}
