<?php

/**
 *
 **/
class UserController extends RestIP\AppController
{
    /**
     *
     **/
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $GLOBALS['perm']->check('autor');

        Navigation::activateItem('/links/settings/oauth');
        PageLayout::setTabNavigation('/links/settings');
        PageLayout::setTitle(_('Applikationen'));
    }

    /**
     *
     **/
    public function index_action()
    {
        $this->consumers = OAuthUser::getConsumers($GLOBALS['user']->id);

        $this->setInfoboxImage('infobox/administration.jpg');
        $this->addToInfobox(_('Informationen'), _('Dies sind die Apps, die Zugriff auf Ihren Account haben.'), 'icons/16/black/info-circle.png');
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