<?
require '../../../../lib/bootstrap.php';
require_once 'lib/functions.php';

require_once 'bootstrap.php';

page_open(array(
    'sess' => 'Seminar_Session',
    'auth' => 'Seminar_Auth',
    'perm' => 'Seminar_Perm',
    'user' => 'Seminar_User'
));

$root = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'app';
$uri = rtrim($ABSOLUTE_URI_STUDIP, '/') . '/api.php';

$dispatcher = new Trails_Dispatcher($root, $uri, 'default');
$dispatcher->dispatch($_SERVER['PATH_INFO'] ?: '/');
