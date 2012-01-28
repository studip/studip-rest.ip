<?
require_once 'vendor/trails/trails.php';
require_once 'app/controllers/studip_controller.php';

require_once 'app/models/OAuthUser.php';
require_once 'app/models/OAuthConsumer.php';
require_once 'app/controllers/oauthed_controller.php';
require_once 'app/controllers/api_controller.php';

// Populate $_DELETE, $_HEAD, $_OPTIONS and $_PUT
foreach (words('DELETE HEAD OPTIONS PUT') as $method) {
    $var = '_' . $method;
    $$var = array();  
    if ($_SERVER['REQUEST_METHOD'] == $method) {  
        parse_str(file_get_contents('php://input'), $$var);  
    }
}


spl_autoload_register(function ($name) {
    @include 'vendor/oauth-php/library/' . $name . '.php';
}, false, true);
