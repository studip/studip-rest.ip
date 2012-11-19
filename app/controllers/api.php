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

        // Get id from authorisation (either OAuth or standard)
        try {
            if (OAuth::isSigned()) {
                $user_id = OAuth::verify();
            } elseif (HTTPAuth::isSigned()) {
                $user_id = HTTPAuth::verify();
            } elseif ($GLOBALS['user']->id !== 'nobody') {
                $user_id = $GLOBALS['user']->id;
            }
            if (!$user_id) {
                throw new Exception('Unauthorized', 401);
            }
        } catch (Exception $e) {
            $status = sprintf('HTTP/1.1 %u %s', 401, 'Unauthorized');
            header($status, true, 401);
            die($status);
        }

        if ($GLOBALS['user']->id === 'nobody') {
            // Fake user identity
            $user = User::find($user_id);

            $GLOBALS['auth'] = new Seminar_Auth();
            $GLOBALS['auth']->auth = array(
                'uid'   => $user->user_id,
                'uname' => $user->username,
                'perm'  => $user->perms,
            );

            $GLOBALS['user'] = new Seminar_User($user->user_id);

            $GLOBALS['perm'] = new Seminar_Perm();
            $GLOBALS['MAIL_VALIDATE_BOX'] = false;
        }

        setTempLanguage($GLOBALS['user']->id);

        Slim\Route::setDefaultConditions(array(
            'course_id'   => '[0-9a-f]{32}',
            'message_id'  => '[0-9a-f]{32}',
            'range_id'    => '[0-9a-f]{32}',
            'semester_id' => '[0-9a-f]{32}',
            'user_id'     => '[0-9a-f]{32}',
        ));

        $template_factory = new Flexi_TemplateFactory($this->dispatcher->plugin->getPluginPath());
        $template =  $template_factory->open('app/views/api/' . $format . '.php');

        $router = RestIP\Router::getInstance(null, $template);
        $router->handleErrors();

        if (Studip\ENV === 'development') {
            error_reporting(E_ALL ^ (E_NOTICE | E_WARNING));
        } else {
            error_reporting(0);
        }

        if (Request::option('mode', 'compact') === 'complete') {
            $router->setMode(RestIP\Router::MODE_COMPLETE);
        } else {
            $router->setMode(RestIP\Router::MODE_COMPACT);
        }

        $env = $router->environment();
        $env['PATH_INFO']   = '/' . trim($unconsumed);

        $router->hook('slim.before.dispatch', function () use ($router) {
            $route   = reset($router->router()->getMatchedRoutes());
            $pattern = rtrim($route->getPattern(), '?');
            $method  = strtolower(reset($route->getHttpMethods()));

            $routes  = $router->getRoutes();
            $handler = $routes[$pattern][$method];
            $before  = sprintf('%s::before', $handler);

            if (is_callable($before)) {
                call_user_func($before);
            }
        });

        $router->run();

        $router->hook('slim.after.dispatch', function () use ($router) {
            $route   = reset($router->router()->getMatchedRoutes());
            $pattern = rtrim($route->getPattern(), '?');
            $method  = strtolower(reset($route->getHttpMethods()));

            $routes  = $router->getRoutes();
            $handler = $routes[$pattern][$method];
            $after   = sprintf('%s::after', $handler);

            if (is_callable($after)) {
                call_user_func($after);
            }
        });

        restoreLanguage();

        return new Trails_Response();
    }
}
