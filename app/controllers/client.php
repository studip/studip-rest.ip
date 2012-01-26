<?php
class ClientController extends StudipController
{
    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);
    }
    
    function index_action() {
        $this->render_text('client yet to come...');
    }
}