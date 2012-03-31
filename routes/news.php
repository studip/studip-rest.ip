<?php

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
        $router->get('/news(/:range_id)', function ($range_id = false) use ($router)
        {
            $range_id = $range_id ?: $GLOBALS['user']->id;

            require_once 'lib/classes/StudipNews.class.php';

            $data = StudipNews::GetNewsByRange($range_id);

            // Adjust data
            array_walk($data, function (&$item) {
                $item['body']       = formatReady($item['body']);
                $item['chdate_uid'] = trim($item['chdate_uid']);
            });

            $router->value(array('news' => array_values($data)));
        });
    }
}