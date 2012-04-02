<?php

/**
 * 
 **/
class MessageRoute implements APIPlugin
{
    /**
     *
     **/
    public function describeRoutes()
    {
        return array(
            '/user/messages(/:user_id)' => _('Nachrichten eines Nutzers'),
            '/message/:message_id'      => _('Nachrichten'),
        );
    }

    /**
     *
     **/
    public function routes(&$router)
    {
        $router->get('/user/messages(/:user_id)', function ($user_id = null) use (&$router) {
            $router->value(array('implement' => __METHOD__ . '#L' . __LINE__));
        });
        
        $router->get('/message/:message_id', function ($message_id) use (&$router) {
            $router->value(array('implement' => __METHOD__ . '#L' . __LINE__));
        });
    }
}
