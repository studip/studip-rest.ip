<?php

# namespace RestIP;

/**
 *
 **/
class DiscoveryRoute implements APIPlugin
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
            $routes      = $router->getRoutes();
            $permissions = $router->getPermissions();

            foreach ($routes as $route => $methods) {
                foreach (array_keys($methods) as $method) {
                    $routes[$route][$method] = $permissions->check($route, $method);
                }
            }

            $router->render(compact('routes'));
        });
    }
}
