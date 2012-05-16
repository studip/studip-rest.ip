<?php

/**
 *
 **/
class ApiController extends StudipController
{
    /**
     *
     **/
    public function perform($unconsumed)
    {
        if ($_SERVER['Content-Type'] === 'application/json') {
            $format = 'json';
        }
        if (preg_match('/\.(json|xml)$/', $unconsumed, $match)) {
            $format = $match[1];
            $unconsumed = substr($unconsumed, 0, - strlen($match[0]));
        }

        // Yes, this is indeed a pretty nasty hack
        // Kids, don't try this at home!
        $_SERVER['PATH_INFO'] = '/' . $unconsumed;

        $GLOBALS['user'] = new Seminar_User;
        $GLOBALS['user']->start(OAuth::verify());

        \Slim_Route::setDefaultConditions(array(
            'course_id'   => '[0-9a-f]{32}',
            'message_id'  => '[0-9a-f]{32}',
            'range_id'    => '[0-9a-f]{32}',
            'semester_id' => '[0-9a-f]{32}',
            'user_id'     => '[0-9a-f]{32}',
        ));

        $template_factory = new Flexi_TemplateFactory($this->dispatcher->plugin->getPluginPath());
        $template =  $template_factory->open('app/views/api/' . $format . '.php');

        $router = RestIP\Router::getInstance(OAuth::$consumer_key, $template);
        $router->handleErrors();
        error_reporting(0);

        $mode = Request::option('mode', 'compact');

        if ($mode === 'complete') {
            $router->setMode(RestIP\Router::MODE_COMPLETE);
        } else {
            $router->setMode(RestIP\Router::MODE_COMPACT);
        }

        $router->run();
    }
}
