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
        if (preg_match('/\.(json|xml)$/', $unconsumed, $match)) {
            $format = $match[1];
            $unconsumed = substr($unconsumed, 0, - strlen($match[0]));
        }

        // Yes, this is indeed a pretty nasty hack
        // Kids, don't try this at home!
        $_SERVER['PATH_INFO'] = '/' . $unconsumed;

        $GLOBALS['user'] = new Seminar_User(OAuth::verify());

        $router = RestIP\Router::getInstance(OAuth::$consumer_key);
        $router->handleErrors();
        error_reporting(0);

        // Hook into slim to convert raw data into requested data format
        $router->hook('slim.after.router', function () use ($router, $format) {
            $data = $router->value();
            if (isset($data)) {
                switch ($format) {
                    case 'json':
                        $data = array_map_recursive('studip_utf8encode', $data);

                        header('Content-Type: application/json');
                        echo indent(json_encode($data));
                        break;
                    case 'xml':
                        header('Content-Type: text/xml;charset=windows-1252');
                        echo RestIP\Helper::arrayToXML(reset($data), array(
                            'root_node' => key($data),
                        ));
                        break;
                    default:
                        $router->halt(501, 'Not implemented');
                }
            }
            header('X-Server-Timestamp: ' . time());
            die;
        }, 1);

        $router->run();
    }
}
