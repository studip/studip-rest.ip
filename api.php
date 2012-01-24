<?
/**
 * Inspired by Stud.IP's public/dispatch.php
 */
require '../../../../lib/bootstrap.php';
require_once 'lib/functions.php';
require_once 'vendor/trails/trails.php';
require_once 'app/controllers/oauth_controller.php';

spl_autoload_register(function ($name) { 
    include 'oauth-php/library/' . $name . '.php';
}, false, true);

$root = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'app';
$uri = rtrim($ABSOLUTE_URI_STUDIP, '/') . '/api.php';

$dispatcher = new Trails_Dispatcher($root, $uri, 'default');
$dispatcher->dispatch($_SERVER['PATH_INFO'] ?: '/');
