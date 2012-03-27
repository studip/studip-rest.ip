<?php
require_once 'bootstrap.php';

/**
 * RestipPlugin.class.php
 *
 * @author  Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @version 0 alpha
 */
class RestipPlugin extends StudIPPlugin implements SystemPlugin, HomepagePlugin, APIPlugin
{

    function __construct() {
        parent::__construct();

        $config = Config::getInstance();
        if (!$config['OAUTH_ENABLED']) {
            return;
        }

        if (!$this->checkEnvironment()) {
            $message   = _('Das OAuth-Plugin ist aktiviert, aber nicht für die Rolle "Nobody" freigegeben.');
            $details   = array();
            $details[] = _('Dies behindert die Kommunikation externer Applikationen mit dem System.');
            $details[] = sprintf(_('Klicken Sie <a href="%s">hier</a>, um die Rollenzuweisung zu bearbeiten.'),
                               URLHelper::getLink('dispatch.php/admin/role/assign_plugin_role/' . $this->getPluginId()));
            PageLayout::postMessage(Messagebox::info($message, $details));
        }

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

    function initialize()
    {
        PageLayout::addStylesheet($this->getPluginURL() . '/assets/form-settings.css');
        PageLayout::addScript($this->getPluginURL() . '/assets/oauth.js');
    }

    function perform ($unconsumed_path)
    {
        $dispatcher = new Trails_Dispatcher(
            $this->getPluginPath() . DIRECTORY_SEPARATOR . 'app',
            rtrim(PluginEngine::getLink($this, array(), null), '/'),
            'admin'
        );
        $dispatcher->plugin = $this;
        $dispatcher->dispatch($unconsumed_path);
    }

    function checkEnvironment() {
        # TODO performance - use cache on success ?
        $role_persistence = new RolePersistence;
        $plugin_roles     = $role_persistence->getAssignedPluginRoles($this->getPluginId());
        $role_names       = array_map(function ($role) { return $role->getRolename(); }, $plugin_roles);

        return in_array('Nobody', $role_names);
    }
    
    public function getHomepageTemplate($user_id) {
        return null;
    }
    
    public function routes(&$router) {

        Routes::setRoutes($router);

    }
}
