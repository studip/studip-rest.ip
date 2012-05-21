<?php

namespace RestIP;

/**
 *
 **/
class StudipRoute implements \APIPlugin
{
    /**
     *
     **/
    public function describeRoutes()
    {
        return array(
            '/studip/settings' => _('Grundlegende Systemeinstellungen'),
        );
    }

    /**
     *
     **/
    public function routes(&$router)
    {
        $router->get('/studip/settings', function () use ($router) {
            $router->render(array(
                'ALLOW_CHANGE_USERNAME' => $GLOBALS['ALLOW_CHANGE_USERNAME'],
                'ALLOW_CHANGE_EMAIL'    => $GLOBALS['ALLOW_CHANGE_EMAIL'],
                'ALLOW_CHANGE_NAME'     => $GLOBALS['ALLOW_CHANGE_NAME'],
                'ALLOW_CHANGE_TITLE'    => $GLOBALS['ALLOW_CHANGE_TITLE'],
                'INST_TYPE'             => $GLOBALS['INST_TYPE'],
                'SEM_TYPE'              => $GLOBALS['SEM_TYPE'],
                'SEM_CLASS'             => $GLOBALS['SEM_CLASS'],
                'TERMIN_TYP'            => $GLOBALS['TERMIN_TYP'],
                'PERS_TERMIN_KAT'       => $GLOBALS['PERS_TERMIN_KAT'],
                'TITLES'                => $GLOBALS['DEFAULT_TITLE_FOR_STATUS'],
                'UNI_NAME_CLEAN'        => $GLOBALS['UNI_NAME_CLEAN'],
            ));
        });
        
        $router->get('/studip/colors', function () use ($router) {
            $router->render(array('colors' => array(
                'background' => '#e1e4e9',
                'dark'       => '#34578c',
                'light'      => '#899ab9',
            )));
        });
    }
}
