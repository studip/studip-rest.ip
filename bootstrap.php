<?
require_once 'vendor/trails/trails.php';
require_once 'app/models/OAuthUser.php';
require_once 'app/models/OAuthConsumer.php';
require_once 'app/controllers/oauth_controller.php';

spl_autoload_register(function ($name) {
    include 'oauth-php/library/' . $name . '.php';
}, false, true);
