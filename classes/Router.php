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
    public static function getInstance($consumer_key = null, $template = null)
    {
        if (!isset(self::$instances[$consumer_key])) {
            self::$instances[$consumer_key] = new self($consumer_key, $template);
        }
        return self::$instances[$consumer_key];
    }

    const MODE_COMPACT = 0;
    const MODE_COMPLETE = 1;

    protected $router;
    protected $routes = array();  // Contains routes, their methods and the source of the definition
    protected $_routes = array(); // Contains routes, their methods and the actual callable handler
    protected $descriptions = array();
    protected $permissions;
    protected $template;
    protected $internal_dispatch = false;
    protected $route_result;
    protected $mode = Router::MODE_COMPACT;

    /**
     *
     **/
    private function __construct($consumer_key, $template)
    {
        $this->template = $template;
        
        $this->router = new \Slim();

        restore_error_handler(); // @see handleErrors()

        $this->permissions = new Permissions($consumer_key);

        // Get routes from plugins, default routes are also defined as fake plugins
        $default_routes = glob(dirname(__FILE__) . '/../routes/*.php');
        foreach ($default_routes as $route) {
            $class_name = 'RestIP\\' . ucfirst(basename($route, '.php')) . 'Route';

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

    public function setMode($mode)
    {
        $this->mode = $mode;
    }
    
    public function getMode()
    {
        return $this->mode;
    }
    
    public function compact()
    {
        return $this->mode === self::MODE_COMPACT;
    }

    public function complete()
    {
        return $this->mode === self::MODE_COMPLETE;
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
     */
    public function dispatch($method, $route)
    {
        if (!isset($this->_routes[$route][$method])) {
            $this->halt(500, sprintf('Tried to dispatch unknown route %s:%s', $method, $route));
        }

        $this->internal_dispatch = true;

        $arguments = array_slice(func_get_args(), 2);
        call_user_func_array($this->_routes[$route][$method], $arguments);

        $this->internal_dispatch = false;

        return $this->route_result;
    }

    /**
     * 
     */
    function render($data = array(), $status = null)
    {
        if ($this->internal_dispatch) {
            $this->route_result = $data;
            return;
        }
        
        header('X-Server-Timestamp: ' . time());
        header_remove('x-powered-by');
        header_remove('set-cookie');

        if ($status !== null) {
            header('Status: ' . $status);
            header('HTTP/1.1 ' . $status);
        }

        $this->template->data = $data;
        echo $this->template->render();
        die;
    }

    /**
     *
     **/
    public function __call($method, $arguments)
    {
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
