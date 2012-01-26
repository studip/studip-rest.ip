<?php
require_once 'bootstrap.php';
/**
 * RestipPlugin.class.php
 *
 * @author  Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @version 0 alpha
 */
class RestipPlugin extends StudIPPlugin implements SystemPlugin {

    function __construct() {
        parent::__construct();
        
        global $perm;
        
        if ($perm->have_perm('autor')) {
            $navigation = new AutoNavigation(_('OAuth Client'));
            $navigation->setURL(PluginEngine::getLink($this, array(), 'client'));
            $navigation->setImage('blank.gif');
            Navigation::addItem('/oauth', $navigation);
        }
        
        if ($perm->have_perm('root')) {
            $navigation = new AutoNavigation(_('OAuth'));
            $navigation->setURL(PluginEngine::getLink($this, array(), 'admin'));
            $navigation->setImage('blank.gif');
            Navigation::addItem('/admin/config/oauth', $navigation);
        }
    }
    
    public function initialize() {
        PageLayout::addStylesheet($this->getPluginURL() . '/assets/form-settings.css');
        PageLayout::addScript($this->getPluginURL() . '/assets/oauth.js');
    }

    function perform ($unconsumed_path) {
        global $auth;
        
        $auth->login_if($auth->auth['uid'] == 'nobody'); 

        if ($unconsumed_path == 'auth/login') {
            $url  = $GLOBALS['ABSOLUTE_URI_STUDIP'];
            $url .= '/plugins_packages/UOL/restipplugin/api.php/auth/register';
            header('Location: ' . $url);
            die;
        }

        $dispatcher = new Trails_Dispatcher(
            $this->getPluginPath() . DIRECTORY_SEPARATOR . 'app',
            rtrim(PluginEngine::getLink($this, array(), null), '/'),
            'admin'
        );
        $dispatcher->plugin = $this;
        $dispatcher->dispatch($unconsumed_path);
    }

}
