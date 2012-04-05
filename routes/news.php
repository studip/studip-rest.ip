<?php

namespace RestIP;
use APIPlugin, StudipNews;

/**
 *
 **/
class NewsRoute implements APIPlugin
{
    /**
     *
     **/
    public function describeRoutes()
    {
        return array(
            '/news(/:range_id)' => _('Ankündigungen'),
        );
    }
    
    /**
     *
     **/
    public function routes(&$router)
    {
        $router->get('/news(/:range_id)', function ($range_id = false) use (&$router)
        {
            $range_id = $range_id ?: $GLOBALS['user']->id;

            require_once 'lib/classes/StudipNews.class.php';

            $news = StudipNews::GetNewsByRange($range_id);

            // Adjust data and extract users
            $users = array();
            array_walk($news, function (&$item) use (&$router, &$users) {
                $item['body']       = formatReady($item['body']);
                $item['chdate_uid'] = trim($item['chdate_uid']);
                
                if (!isset($users[$item['user_id']])) {
                    $users[$item['user_id']] = $router->dispatch('get', '/user(/:user_id)', $item['user_id']);
                }
                if (!isset($users[$item['chdate_uid']])) {
                    $users[$item['chdate_uid']] = $router->dispatch('get', '/user(/:user_id)', $item['chdate_uid']);
                }
                unset($item['author']);
            });

            $router->value(compact('news', 'users'));
        })->conditions(array('range_id' => '(studip|[a-f0-9]{32})'));
    }
}