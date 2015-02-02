<?php
class AppController extends StudipController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
        
        if (Request::isXhr()) {
            $this->response->add_header('Content-Type', 'text/html;charset=windows-1252');
        } else {
            $this->set_layout($GLOBALS['template_factory']->open('layouts/base.php'));
        }

        $this->store = new OAuthConsumer;
        $this->types = array(
            'website' => _('Website'),
            'program' => _('Herkömmliches Desktopprogramm'),
            'app'     => _('Mobile App')
        );
    }
    
    public function addToInfobox($category, $text, $icon = 'blank.gif')
    {
        static $widgets = array();
        
        if (!class_exists('Sidebar')) {
            return parent::addToInfobox($category, $text, $icon);
        }
        
        $mapping = array(
            _('Aktionen') => array(
                'key'   => 'actions',
                'class' => 'ActionsWidget',
                'add'   => function (&$widget, $text, $icon) {
                    $element = LinkElement::fromHTML($text);
                    $element->icon = str_replace('black', 'blue', Assets::image_path($icon));
                    $widget->addElement($element);
                }
            )
        );
        
        $info = $mapping[$category] ?: array(
            'key'   => 'info',
            'class' => 'SidebarWidget',
            'add'   => function (&$widget, $text, $icon) {
                $element = new WidgetElement($text);
                $element->icon = $icon;
                $widget->addElement($element);
            }
        );

        if (!isset($widgets[$info['key']])) {
            $widget = new $info['class'];
            $widget->setTitle($category);
            Sidebar::get()->addWidget($widget);
            
            $widgets[$info['key']] = $widget;
        } else {
            $widget = $widgets[$info['key']];
        }
        
        $info['add']($widget, $text, $icon);
    }
    
}