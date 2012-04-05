<?php
namespace RestIP;

/**
 *
 **/
class Router
{
    private static $instances = array();

    /**
     *
     **/
    public static function getInstance($consumer_key = null)
    {
        if (!isset(self::$instances[$consumer_key])) {
            self::$instances[$consumer_key] = new self($consumer_key);
        }
        return self::$instances[$consumer_key];
    }

    protected $router;
    protected $routes = array();  // Contains routes, their methods and the source of the definition
    protected $_routes = array(); // Contains routes, their methods and the actual callable handler
    protected $descriptions = array();
    protected $permissions;

    /**
     *
     **/
    private function __construct($consumer_key)
    {
        \Slim_Route::setDefaultConditions(array(
            'message_id' => '[0-9a-f]{32}',
            'range_id'   => '[0-9a-f]{32}',
            'user_id'    => '[0-9a-f]{32}',
        ));

        $this->router = new \Slim();
        
        restore_error_handler(); // @see handleErrors()
        
        $this->permissions = new Permissions($consumer_key);

        // Get routes from plugins, default routes are also defined as fake plugins
        $default_routes = glob(dirname(__FILE__) . '/../routes/*.php');
        foreach ($default_routes as $route) {
            $class_name = ucfirst(basename($route, '.php')) . 'Route';

            require_once $route;

            $router = new $class_name;
            $router->routes($this);
            $this->descriptions = array_merge($this->descriptions, $router->describeRoutes());
        }

        // Unfortunately, PluginEngine::sendMessage() discards the reference
        // to the router somewhere along the way so we need to iterate manually
        foreach (\PluginManager::getInstance()->getPlugins('APIPlugin') as $plugin) {
            $plugin->routes($this);
            $this->descriptions = array_merge($this->descriptions, $plugin->describeRoutes());
        }
    }

    /**
     * Usually, Slim sets this error handler on instanciation. But we don't want that and want to
     * be able to set the error handler on our own and only for the api.
     **/
    public function handleErrors()
    {
        set_error_handler(array('Slim', 'handleErrors'));
    }

    /**
     * Returns a list of all available routes
     *
     * @return array List of all available routes as an associative array (key = route, value = list of methods).
     *               The list is sorted by routes.
     **/
    public function getRoutes()
    {
        $routes = $this->routes;
        ksort($routes);
        return $routes;
    }

    /**
     *
     **/
    public function getPermissions()
    {
        return $this->permissions;
    }

    /**
     *
     **/
    public function getDescriptions()
    {
        return $this->descriptions;
    }

    /**
     *
     **/
    public function value($val = null)
    {
        static $value;
        if (func_num_args() == 0) {
            return $value;
        } else {
            $value = $val;
        }
    }
    
    public function dispatch($method, $route)
    {
        if (!isset($this->_routes[$route][$method])) {
            throw new Exception('Tried to dispatch unknown route');
        }

        $arguments = array_slice(func_get_args(), 2);
        
        call_user_func_array($this->_routes[$route][$method], $arguments);
        
        $result = $this->value();
        $this->value(null);
        
        return $result;
    }

    /**
     *
     **/
    public function __call($method, $arguments) {
        if (in_array($method, words('delete get post put'))) {
            $backtrace = debug_backtrace();
            while ($trace = array_shift($backtrace) and $trace['class'] == __CLASS__);
            $caller = ($trace and is_a($trace['class'], 'StudIPPlugin')) ? $trace['class'] : false;

            $route = reset($arguments);

            if (!isset($this->routes[$route])) {
                $this->routes[$route] = array();
            }
            $this->routes[$route][$method] = $caller;

            if (!$this->permissions->check($route, $method)) {
                $router = $this->router;
                array_splice($arguments, 1, 1, array(function () use ($router) {
                    $router->halt(403, 'Forbidden');
                }));
            }
            $this->_routes[$route][$method] = $arguments[1];
        }

        return call_user_func_array(array($this->router, $method), $arguments);
    }
}
