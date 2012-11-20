<?php

if (!function_exists('array_map_recursive')) {
    function array_map_recursive($func, $arr){
      $a = array();
      if(is_array($arr))
        foreach($arr as $k => $v)
          $a[$k] = is_array($v) ? array_map_recursive($func, $v) : $func($v);
      return $a;
    }
}

/**
 * Indents a flat JSON string to make it more human-readable.
 *
 * @param string $json The original JSON string to process.
 *
 * @return string Indented version of the original JSON string.
 * @see http://recursive-design.com/blog/2008/03/11/format-json-with-php/
 */
function indent($json) {

    $result      = '';
    $pos         = 0;
    $strLen      = strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;

    for ($i=0; $i<=$strLen; $i++) {

        // Grab the next character in the string.
        $char = substr($json, $i, 1);

        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;

        // If this character is the end of an element,
        // output a new line and indent the next line.
        } else if(($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos --;
            for ($j=0; $j<$pos; $j++) {
                $result .= $indentStr;
            }
        }

        // Add the character to the result string.
        $result .= $char;

        // If the last character was the beginning of an element,
        // output a new line and indent the next line.
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos ++;
            }

            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }

        $prevChar = $char;
    }

    return $result;
}

// Global includes
require_once 'vendor/trails/trails.php';
require_once 'app/controllers/studip_controller.php';

// Local includes

$error_reporting = error_reporting();
require 'vendor/Slim/Slim/Slim.php';
error_reporting($error_reporting);

Slim\Slim::registerAutoloader();

require_once 'classes/Router.php';
require_once 'classes/Helper.php';
require_once 'classes/OAuth.php';
require_once 'classes/HTTPAuth.php';

require_once 'classes/buttons/Interactable.class.php';
require_once 'classes/buttons/Button.class.php';
require_once 'classes/buttons/LinkButton.class.php';

require_once 'app/models/OAuthUser.php';
require_once 'app/models/OAuthConsumer.php';
require_once 'app/models/Permissions.php';

// Populate $_DELETE, $_HEAD, $_OPTIONS and $_PUT
foreach (words('DELETE HEAD OPTIONS PUT') as $method) {
    $var = '_' . $method;
    $GLOBALS[$var] = array();
    if ($_SERVER['REQUEST_METHOD'] == $method) {
        parse_str(file_get_contents('php://input'), $GLOBALS[$var]);
        foreach ($GLOBALS[$var] as $key => $value) {
            Request::set($key, $value);
        }
        $_REQUEST = array_merge($_REQUEST, $GLOBALS[$var]);
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
