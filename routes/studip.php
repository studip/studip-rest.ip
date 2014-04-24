<?php
namespace RestIP;
use \APIPlugin, \SemType, \SemClass;

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
            if (class_exists('SemClass')) { //Stud.IP < 2.4
                $sem_types = array_map(function ($item) {
                    return array(
                        'name'  => $item['name'],
                        'class' => $item['class'],
                    );
                    }, SemType::getTypes());
                $sem_class = array_map(function ($item) {
                                               $item = (array)$item;
                                               return reset($item);
                                           }, SemClass::getClasses());
            } else {
                $sem_types = $GLOBALS['SEM_TYPE'];
                $sem_class = $GLOBALS['SEM_CLASS'];
            }
            $manifest = parse_ini_file(dirname(__FILE__) . '/../plugin.manifest');
            $API_VERSION = $manifest['version'];

            $router->render(array(
                'API_VERSION'           => $API_VERSION,
                'ALLOW_CHANGE_USERNAME' => $GLOBALS['ALLOW_CHANGE_USERNAME'],
                'ALLOW_CHANGE_EMAIL'    => $GLOBALS['ALLOW_CHANGE_EMAIL'],
                'ALLOW_CHANGE_NAME'     => $GLOBALS['ALLOW_CHANGE_NAME'],
                'ALLOW_CHANGE_TITLE'    => $GLOBALS['ALLOW_CHANGE_TITLE'],
                'INST_TYPE'             => $GLOBALS['INST_TYPE'],
                'SEM_TYPE'              => $sem_types,
                'SEM_CLASS'             => $sem_class,
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
