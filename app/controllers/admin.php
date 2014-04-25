<?php

/**
 *
 **/
class AdminController extends StudipController
{
    /**
     *
     **/
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $GLOBALS['perm']->check('root');

        $layout = $GLOBALS['template_factory']->open('layouts/base');
        $this->set_layout($layout);

        Navigation::activateItem('/admin/config/oauth');
        PageLayout::setTitle(_('OAuth Administration'));

        $this->store = new OAuthConsumer;
        $this->types = array(
            'website' => _('Website'),
            'program' => _('Herkömmliches Desktopprogramm'),
            'app'     => _('Mobile App')
        );

        // Infobox
        $this->setInfoboxImage('infobox/administration.jpg');

        if ($action !== 'index') {
            $back = sprintf('<a href="%s">%s</a>',
                           $this->url_for('admin'),
                           _('Zurück zur Übersicht'));
            $this->addToInfobox(_('Aktionen'), $back, 'icons/16/black/arr_1left');
        }

        $new = sprintf('<a href="%s">%s</a>',
                       $this->url_for('admin/edit'),
                       _('Neue Applikation registrieren'));
        $this->addToInfobox(_('Aktionen'), $new, 'icons/16/black/plus');

        $global = sprintf('<a href="%s">%s</a>',
                         $this->url_for('admin/permissions'),
                         _('Globale Zugriffseinstellungen'));
        $this->addToInfobox(_('Aktionen'), $global, 'icons/16/black/admin');

        $config = sprintf('<a href="%s">%s</a>',
                          $this->url_for('admin/config'),
                          _('Konfiguration'));
        $this->addToInfobox(_('Aktionen'), $config, 'icons/16/black/tools');
    }

    /**
     *
     **/
    public function index_action()
    {
        $this->consumers = $this->store->getList();
        $this->routes    = RestIP\Router::getInstance()->getRoutes();
    }

    /**
     *
     **/
    public function render_keys($key, $consumer = null)
    {
        if ($consumer === null) {
            $consumer = $this->store->load($key);
        }

        return array(
            'Consumer Key = ' . $consumer['consumer_key'],
            'Consumer Secret = ' . $consumer['consumer_secret'],
        );
    }

    /**
     *
     **/
    public function keys_action($key)
    {
        $details = $this->render_keys($key);

        if (Request::isXhr()) {
            $this->render_text(implode('<br>', $details));
        } else {
            PageLayout::postMessage(MessageBox::info(_('Die Schlüssel in den Details dieser Meldung sollten vertraulich behandelt werden!'), $details, true));
            $this->redirect('admin/index#' . $key);
        }
    }

    /**
     *
     **/
    public function edit_action($key = null)
    {
        $this->consumer = $this->store->extractConsumerFromRequest($key);

        if (Request::submitted('store')) {
            $errors = $this->store->validate($this->consumer);

            if (!empty($errors)) {
                $message = MessageBox::error(_('Folgende Fehler sind aufgetreten:'), $errors);
                PageLayout::postMessage($message);
                return;
            }

            $consumer = $this->store->store($this->consumer, Request::int('enabled', 0));

            if ($key) {
                $message = MessageBox::success(_('Die Applikation wurde erfolgreich gespeichert.'));
            } else {
                $details  = $this->render_keys($key, $consumer);
                $message = MessageBox::success(_('Die Applikation wurde erfolgreich erstellt, die Schlüssel finden Sie in den Details dieser Meldung.'), $details, true);
            }
            PageLayout::postMessage($message);
            $this->redirect('admin/index#' . $consumer['consumer_key']);
            return;
        }

        $this->set_layout($GLOBALS['template_factory']->open('layouts/base_without_infobox'));

        $this->id = $id;
    }

    /**
     *
     **/
    public function toggle_action($key, $state = null)
    {
        $consumer = $this->store->extractConsumerFromRequest($key);

        $state = $state === null
               ? !$consumer['enabled']
               : $state === 'on';

        $consumer = $this->store->store($consumer, $state);

        $message = $state
                 ? _('Die Applikation wurde erfolgreich aktiviert.')
                 : _('Die Applikation wurde erfolgreich deaktiviert.');

        PageLayout::postMessage(MessageBox::success($message));
        $this->redirect('admin/index#' . $consumer['consumer_key']);
    }

    /**
     *
     **/
    public function delete_action($key)
    {
        $this->store->delete($key);
        PageLayout::postMessage(MessageBox::success(_('Die Applikation wurde erfolgreich gelöscht.')));
        $this->redirect('admin/index');
    }

    /**
     *
     **/
    public function permissions_action($consumer_key = null)
    {
        if (Request::submitted('store')) {
            $perms = $_POST['permission'];

            $permissions = RestIP\Router::getInstance($consumer_key ?: null)->getPermissions();
            foreach ($_POST['permission'] as $route => $methods) {
                foreach ($methods as $method => $granted) {
                    $permissions->set(urldecode($route), urldecode($method), (bool)$granted);
                }
            }

            PageLayout::postMessage(MessageBox::success(_('Die Zugriffsberechtigungen wurden erfolgreich gespeichert')));
            $this->redirect($consumer_key ? 'admin' : 'admin/permissions');
            return;
        }

        $title = $consumer_key ? 'Zugriffsberechtigungen' : 'Globale Zugriffsberechtigungen';
        $title .= ' - ' . PageLayout::getTitle();
        PageLayout::setTitle($title);

        $this->consumer_key = $consumer_key;
        $this->router       = RestIP\Router::getInstance($consumer_key);
        $this->routes       = $this->router->getRoutes();
        $this->descriptions = $this->router->getDescriptions();
        $this->permissions  = $this->router->getPermissions();
        $this->global       = $consumer_key ? RestIP\Router::getInstance()->getPermissions() : false;
    }

    public function config_action()
    {
        $this->config = Config::get();
        $this->auth_plugins = Restip\Helper::getSSOPlugins();

        if (Request::isPost()) {
            $this->config->store('OAUTH_ENABLED', Request::int('active', 0));
            $this->config->store('RESTIP_AUTH_SESSION_ENABLED', Request::int('session-active', 0));
            $this->config->store('RESTIP_AUTH_HTTP_ENABLED', Request::int('http-active', 0));
            $this->config->store('OAUTH_AUTH_PLUGIN', Request::option('auth', 'Standard'));

            PageLayout::postMessage(MessageBox::success(_('Die Einstellungen wurden gespeichert.')));
            $this->redirect('admin/config');
        }
    }
}