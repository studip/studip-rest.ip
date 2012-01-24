<?
/**
 * Inspired by Stud.IP's public/dispatch.php
 *
 *
 */
require '../../../../lib/bootstrap.php';
require_once 'lib/functions.php';

# define root
$trails_root = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'app';

$trails_uri = rtrim($ABSOLUTE_URI_STUDIP, '/') . '/api.php';
# load trails
require_once 'vendor/trails/trails.php';

# dispatch
$request_uri = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

$dispatcher = new Trails_Dispatcher($trails_root, $trails_uri, 'default');
$dispatcher->dispatch($request_uri);
