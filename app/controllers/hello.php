<?
class HelloController extends Trails_Controller {
    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);
        
        if (!is_callable(array($this, $action . '_action'))) {
            array_unshift($args, $action);
            $action = 'hello';
        }
    }
    
    function hello_action($name) {
        $name = ucwords($name);
        $this->render_text("Hello $name!");
    }
}