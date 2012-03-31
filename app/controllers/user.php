<?php

/**
 *
 **/
class UserController extends StudipController
{
    /**
     *
     **/
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $GLOBALS['perm']->check('autor');

        $layout = $GLOBALS['template_factory']->open('layouts/base');
        $this->set_layout($layout);

        Navigation::activateItem('/links/settings/oauth');
        PageLayout::setTabNavigation('/links/settings');
        PageLayout::setTitle(_('Applikationen'));

        $this->store = new OAuthConsumer;
        $this->types = array(
            'website' => _('Website'),
            'program' => _('Herkömmliches Desktopprogramm'),
            'app'     => _('Mobile App')
        );
    }

    /**
     *
     **/
    public function index_action()
    {
        $this->consumers = OAuthUser::getConsumers($GLOBALS['user']->id);
        $this->types = array(
            'website' => _('Website'),
            'program' => _('Herkömmliches Desktopprogramm'),
            'app'     => _('Mobile App')
        );

        $this->setInfoboxImage('infobox/administration.jpg');
        $this->addToInfobox('Informationen', _('Dies sind die Apps, die Zugriff auf Ihren Account haben.'), 'icons/16/black/info-circle.png');
    }

    /**
     *
     **/
    public function revoke_action($consumer_key)
    {
        OAuthUser::revokeToken($GLOBALS['user']->id, $consumer_key);
        PageLayout::postMessage(MessageBox::success(_('Der Applikation wurde der Zugriff auf Ihre Daten untersagt.')));
        $this->redirect('user/index');
    }
}