<?php
namespace RestIP;

use \APIPlugin, \Slim\Slim;
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

        $this->router = new Slim(array(
            'debug' => false,
        ));

        restore_error_handler(); // @see handleErrors()

        $this->permissions = new Permissions($consumer_key);

        // Get routes from plugins, default routes are also defined as fake plugins
        $default_routes = glob(dirname(__FILE__) . '/../routes/*.php');
        foreach ($default_routes as $route) {
            $class_name = 'RestIP\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', basename($route, '.php')))) . 'Route';

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
        set_error_handler(array('\Slim\Slim', 'handleErrors'));
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
        return $this->internal_dispatch || $this->mode === self::MODE_COMPACT;
    }

    public function complete()
    {
        return !$this->internal_dispatch && $this->mode === self::MODE_COMPLETE;
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

    /*
     * Sets route result
     *
     * @param mixed $result Route result
     */
    public function setRouteResult($result)
    {
        $this->route_result = $result;
    }

    /**
     * Returns the route result.
     *
     * @return mixed
     */
    public function getRouteResult()
    {
        return $this->route_result;
    }

    public function response()
    {
        return $this->router->response();
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
    function render($data = array(), $status = 200)
    {
        $this->setRouteResult($data);
        if ($this->internal_dispatch) {
            return;
        }

        header_remove('x-powered-by');
        header_remove('set-cookie');
        header_remove('Pragma');
        header_remove('Expires');

        $this->applyHook('restip.before.render');
        $data = $this->getRouteResult();
        $data = array_map_recursive('studip_utf8encode', $data);

        if ($data !== false) {
            $this->template->data   = $data;
            $this->template->router = $this;
            $result = $this->template->render();
        } else {
            $result = '';
        }

        $this->halt($status, $result);
    }

    /**
     *
     */
    public function halt($status = 200, $message = '')
    {
        if (func_num_args() > 2) {
            $arguments = array_slice(func_get_args(), 2);
            $message = vsprintf($message, $arguments);
        }

        $this->router->halt($status, $message);
    }

    /**
     *
     **/
    public function __call($method, $arguments)
    {
        if (in_array($method, words('delete get post put head'))) {
            $backtrace = debug_backtrace();
            while ($trace = array_shift($backtrace) and $trace['class'] == __CLASS__);
            $class = ($trace and is_subclass_of($trace['class'], 'APIPlugin')) ? $trace['class'] : false;

            $route = reset($arguments);

            if (!isset($this->routes[$route])) {
                $this->routes[$route] = array();
            }
            $this->routes[$route][$method] = $class;

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

    public function url_for($path, $parameters = array())
    {
        $arguments = func_get_args();
        if (is_array(end($arguments))) {
            $parameters = array_pop($arguments);
        } else {
            $parameters = array();
        }
        $path = implode('/', $arguments);

        $url = $path;
        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }
        return $url;
    }

    public function paginate($total, $offset, $limit, $path, $parameters = array())
    {
        $url_arguments = array_slice(func_get_args(), 3);
        $parameters = is_array(end($url_arguments))
                    ? array_pop($url_arguments)
                    : array();

        $pagination = compact('total', 'offset', 'limit');
        if ($offset > 0) {
            $args = $url_arguments;
            $args[] = $parameters + array('offset' => $offset - $limit, 'limit' => $limit);
            $pagination['previous'] = call_user_func_array(array($this, 'url_for'), $args);
        }
        if ($offset + $limit < $total) {
            $args = $url_arguments;
            $args[] = $parameters + array('offset' => $offset + $limit, 'limit' => $limit);
            $pagination['next'] = call_user_func_array(array($this, 'url_for'), $args);
        }
        return $pagination;
    }

    public function getCurrentRouteAndMethod()
    {
        $routes  = $this->router()->getMatchedRoutes($this->request()->getMethod(), $this->request()->getResourceUri());
        $route   = reset($routes);
        $pattern = rtrim($route->getPattern(), '?');

        $methods = $route->getHttpMethods();
        $method  = strtolower(reset($methods));

        return array($pattern, $method);
    }

}
