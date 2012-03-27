<?
class ApiController
{
    function perform($unconsumed) {
        error_reporting(E_ALL ^ E_NOTICE);
    
        if (preg_match('/\.(csv|json|php|xml)$/', $unconsumed, $match)) {
            $format = $match[1];
            $unconsumed = substr($unconsumed, 0, - strlen($match[0]));
        }
    
        $GLOBALS['user']->id = OAuth::verify();

        // Yes, this is indeed a pretty nasty hack
        // Kids, don't try this at home!
        $_SERVER['PATH_INFO'] = '/' . $unconsumed;

        $router = new Slim();
        
        // Unfortunately, PluginEngine::sendMessage() discards the reference
        // to the router somewhere along the way so we need to iterate manually
        foreach (PluginManager::getInstance()->getPlugins('APIPlugin') as $plugin) {
            $plugin->routes($router);
        }
        
        // Hook into slim to convert raw data into requested data format 
        $router->hook('slim.after.router', function () use ($router, $format) {
            $data   = $router->data['data'];
            switch ($format) {
                case 'csv':
                    header('Content-Type: text/csv;charset=windows-1252');
                    echo RestIP\Helper::csvEncode(array_keys($data)) . "\n";
                    foreach ($data as $row) {
                        echo RestIP\Helper::csvEncode($row) . "\n";
                    }
                    break;
                case 'json':
                    $data = array_map_recursive('studip_utf8encode', $data);
                    
                    header('Content-Type: application/json');
                    echo json_encode($data);
                    break;
                case 'php':
                    header('Content-Type: text/plain;charset=windows-1252');
                    echo serialize($data);
                    break;
                case 'xml':
                    header('Content-Type: text/xml;charset=windows-1252');
                    echo RestIP\Helper::arrayToXML(reset($data), array(
                        'root_node' => key($data),
                    ));
                    break;
                default:
                    echo '<pre>';
                    var_dump($data);
                    break;
            }
            header('X-SERVER-TIMESTAMP: ' . time());
            die;
        }, 1);
    
        $router->run();
    }
}
