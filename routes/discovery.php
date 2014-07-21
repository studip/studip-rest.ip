<?php

namespace RestIP;
use \Request;

/**
 *
 **/
class DiscoveryRoute implements \APIPlugin
{
    /**
     *
     **/
    public function describeRoutes()
    {
        return array(
            '/discovery' => _('Schnittstellenbeschreibung'),
        );
    }
    
    /**
     *
     **/
    public function routes(&$router)
    {
        $router->get('/discovery', function () use ($router)
        {
            $baseroutes  = $router->getRoutes();
            $permissions = $router->getPermissions();
            $routes      = array();

            if(Request::get('mode') == 'alternative'){
                foreach($baseroutes as $route => $basemethods) {
                    $methods = array();
                    foreach (array_keys($basemethods) as $method) {
                        $methods[$method] =  $permissions->check($route, $method);
                    }
                    $routes[] = array('route' => $route, 'methods' => $methods);
                } 
            } else {
                foreach ($baseroutes as $route => $methods) {
                    foreach (array_keys($methods) as $method) {
                        $routes[$route][$method] = $permissions->check($route, $method);
                    }
                }
            }

            $router->render(compact('routes'));
        });
    }
}
