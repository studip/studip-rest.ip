<?php

function array_map_recursive($func, $arr){
  $a = array(); 
  if(is_array($arr))
    foreach($arr as $k => $v)
      $a[$k] = is_array($v) ? array_map_recursive($func, $v) : $func($v);
  return $a;
}

// Global includes
require_once 'vendor/trails/trails.php';
require_once 'app/controllers/studip_controller.php';

// Local includes
$error_reporting = error_reporting();
require 'vendor/Slim/Slim/Slim.php';
error_reporting($error_reporting);

require_once 'classes/APIPlugin.php';
require_once 'classes/Router.php';
require_once 'classes/Helper.php';
require_once 'classes/OAuth.php';
require_once 'app/models/OAuthUser.php';
require_once 'app/models/OAuthConsumer.php';
require_once 'app/models/Permissions.php';

// Populate $_DELETE, $_HEAD, $_OPTIONS and $_PUT
foreach (words('DELETE HEAD OPTIONS PUT') as $method) {
    $var = '_' . $method;
    $$var = array();  
    if ($_SERVER['REQUEST_METHOD'] == $method) {  
        parse_str(file_get_contents('php://input'), $$var);  
    }
}

// Autoload
spl_autoload_register(function ($name) {
    @include 'vendor/oauth-php/library/' . $name . '.php';
}, false, true);

// Set up OAuth database conncetion
$options = array(
    'dsn'      => 'mysql:host=' . $GLOBALS['DB_STUDIP_HOST']
               .       ';dbname=' . $GLOBALS['DB_STUDIP_DATABASE'],
    'username' => $GLOBALS['DB_STUDIP_USER'],
    'password' => $GLOBALS['DB_STUDIP_PASSWORD']
);
OAuthStore::instance('PDO', $options);
