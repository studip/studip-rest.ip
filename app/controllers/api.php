<?php

/**
 *
 **/
class ApiController extends StudipController
{
    protected static $format_guesses = array(
        'application/json' => 'json',
        'text/php'         => 'php',
        'text/xml'         => 'xml',
    );

    /**
     *
     **/
    public function perform($unconsumed)
    {
        $format = reset(self::$format_guesses);

        if (isset($_SERVER['CONTENT_TYPE'])) {
            foreach (self::$format_guesses as $mime_type => $guessed_format) {
                if ($_SERVER['CONTENT_TYPE'] === $mime_type) {
                    $format = $guessed_format;
                }
            }
        }
        if (preg_match('/\.(' . implode('|', self::$format_guesses) . ')$/', $unconsumed, $match)) {
            $format = $match[1];
            $unconsumed = substr($unconsumed, 0, - strlen($match[0]));
        }

        // Yes, this is indeed a pretty nasty hack
        // Kids, don't try this at home!
        $_SERVER['PATH_INFO'] = '/' . $unconsumed;

        // Fake user identity ()
        $user = User::find(OAuth::verify());

        $GLOBALS['auth'] = new Seminar_Auth();
        $GLOBALS['auth']->auth = array(
            'uid'   => $user->user_id,
            'uname' => $user->username,
            'perm'  => $user->perms,
        );

        $GLOBALS['user'] = new Seminar_User();
        $GLOBALS['user']->fake_user = true;
        $GLOBALS['user']->register_globals = false;
        $GLOBALS['user']->start($user->user_id);
    
        $GLOBALS['perm'] = new Seminar_Perm();
        $GLOBALS['MAIL_VALIDATE_BOX'] = false;

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

        if (Request::option('mode', 'compact') === 'complete') {
            $router->setMode(RestIP\Router::MODE_COMPLETE);
        } else {
            $router->setMode(RestIP\Router::MODE_COMPACT);
        }

        $router->run();
    }
}
