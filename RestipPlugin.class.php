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

        $config = Config::getInstance();
        if (!$config['OAUTH_ENABLED']) {
            return;
        }

        $navigation = new AutoNavigation(_('OAuth Client'));
        $navigation->setURL(PluginEngine::getLink($this, array(), 'client'));
        $navigation->setImage('blank.gif');
        Navigation::addItem('/oauth', $navigation);

        if ($GLOBALS['perm']->have_perm('autor')) {
            $navigation = new AutoNavigation(_('Apps'));
            $navigation->setURL(PluginEngine::getLink($this, array(), 'user'));
            Navigation::addItem('/links/settings/oauth', $navigation);
        }

        if ($GLOBALS['perm']->have_perm('root')) {
            $navigation = new AutoNavigation(_('OAuth'));
            $navigation->setURL(PluginEngine::getLink($this, array(), 'admin'));
            Navigation::addItem('/admin/config/oauth', $navigation);
        }
    }

    public function initialize() {
        PageLayout::addStylesheet($this->getPluginURL() . '/assets/form-settings.css');
        PageLayout::addScript($this->getPluginURL() . '/assets/oauth.js');
    }

    function perform ($unconsumed_path) {
        $unconsumed_path = preg_replace('~^api/(\w+)(\.(?:csv|json|php|xml))~', 'api/$1/index$2', $unconsumed_path);

        $dispatcher = new Trails_Dispatcher(
            $this->getPluginPath() . DIRECTORY_SEPARATOR . 'app',
            rtrim(PluginEngine::getLink($this, array(), null), '/'),
            'admin'
        );
        $dispatcher->plugin = $this;
        $dispatcher->container = $this->getContainer();
        $dispatcher->dispatch($unconsumed_path);
    }

    function getContainer()
    {
        require_once dirname(__FILE__) . '/vendor/pimple/lib/Pimple.php';
        $container = new Pimple();


        $container['CONSUMER_KEY'] = '1d918110489350d4ff682c48f247a34804f2268ef';
        $container['CONSUMER_SECRET'] = '07d9acf83e15069f54476fb2f6e13583';

        # workaround to get an absolute URL
        URLHelper::setBaseURL($GLOBALS['ABSOLUTE_URI_STUDIP']);

        $container['PROVIDER_URL'] = PluginEngine::getURL($this, array(), 'oauth');
        $container['API_URL'] = PluginEngine::getURL($this, array(), 'api');

        $container['CONSUMER_URL'] = PluginEngine::getURL($this, array(), 'client');



        return $container;
    }
}
