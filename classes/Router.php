<?php
namespace RestIP;

class Router
{
    private static $instances = array();
    
    public static function getInstance($consumer_key = null)
    {
        if (!isset(self::$instances[$consumer_key])) {
            self::$instances[$consumer_key] = new self($consumer_key);
        }
        return self::$instances[$consumer_key];
    }

    protected $router;
    protected $routes = array();
    protected $descriptions = array();
    protected $permissions;
    
    private function __construct($consumer_key)
    {
        $this->router = new \Slim();
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
    
    public function getRoutes()
    {
        return $this->routes;
    }
    
    public function getPermissions()
    {
        return $this->permissions;
    }
    
    public function getDescriptions()
    {
        return $this->descriptions;
    }
    
    public function value($val = null)
    {
        static $value;
        if (func_num_args() == 0) {
            return $value;
        } else {
            $value = $val;
        }
    }
    
    public function __call($method, $arguments) {
        if (in_array($method, words('delete get post put'))) {
            $backtrace = debug_backtrace();
            while ($trace = array_shift($backtrace) and $trace['class'] == __CLASS__);
            $caller = ($trace and is_a($trace['class'], StudIPPlugin)) ? $trace['class'] : false;
            
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
        }
        
        call_user_func_array(array($this->router, $method), $arguments);
    }
}
