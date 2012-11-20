<?php
namespace RestIP;
use \APIPlugin;

/**
 *
 **/
class StudipRoute implements APIPlugin
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
            $manifest = parse_ini_file(dirname(__FILE__) . '/../plugin.manifest');
            $API_VERSION = $manifest['version'];

            $router->render(array(
                'API_VERSION'           => $API_VERSION,
                'ALLOW_CHANGE_USERNAME' => $GLOBALS['ALLOW_CHANGE_USERNAME'],
                'ALLOW_CHANGE_EMAIL'    => $GLOBALS['ALLOW_CHANGE_EMAIL'],
                'ALLOW_CHANGE_NAME'     => $GLOBALS['ALLOW_CHANGE_NAME'],
                'ALLOW_CHANGE_TITLE'    => $GLOBALS['ALLOW_CHANGE_TITLE'],
                'INST_TYPE'             => $GLOBALS['INST_TYPE'],
                'SEM_TYPE'              => $GLOBALS['SEM_TYPE'],
                'SEM_CLASS'             => $GLOBALS['SEM_CLASS'],
                'TERMIN_TYP'            => $GLOBALS['TERMIN_TYP'],
                'PERS_TERMIN_KAT'       => $GLOBALS['PERS_TERMIN_KAT'],
                'SUPPORT_EMAIL'         => $GLOBALS['UNI_CONTACT'],
                'TITLES'                => $GLOBALS['DEFAULT_TITLE_FOR_STATUS'],
                'UNI_NAME_CLEAN'        => $GLOBALS['UNI_NAME_CLEAN'],
            ));
        });

        $router->get('/studip/colors', function () use ($router) {
            $colors = array();
            foreach ($GLOBALS['THEME']['COLORS'] as $key => $color) {
                $colors[strtolower($key)] = $color;
            }
            $router->render(compact('colors'));
        });
    }
}
