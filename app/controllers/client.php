<?php

class ClientController extends StudipController
{
    const CONSUMER_KEY    = '1d918110489350d4ff682c48f247a34804f2268ef';
    const CONSUMER_SECRET = '07d9acf83e15069f54476fb2f6e13583';
    
    const API_URL = 'http://127.0.0.1/~tleilax/studip/trunk/public/plugins.php/restipplugin';
    
    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);
        
        $this->set_layout($GLOBALS['template_factory']->open('layouts/base_without_infobox'));
        Navigation::activateItem('/oauth');
        PageLayout::setTitle(_('OAuth Client'));
        
        $options = array(
            'consumer_key'    => self::CONSUMER_KEY,
            'consumer_secret' => self::CONSUMER_SECRET
        );
        $this->store = OAuthStore::instance('2Leg', $options);
    }
    
    function index_action() {
        $resource = Request::get('resource');
        if ($resource) {
            $this->result = $this->request($resource, Request::option('mode'), Request::option('method'));
        }
    }
    
    private function request($resource, $mode = 'php', $method = 'GET') {
        try {
            // Obtain a request object for the request we want to make
            $request = new OAuthRequester(self::API_URL . '/oauth/request_token', 'POST');
            $result = $request->doRequest(0);
            parse_str($result['body'], $params);

            // now make the request. 
            $request_url = self::API_URL . sprintf('/api/%s.%s', $resource, $mode);
            $request = new OAuthRequester($request_url, $method, $params);
            $result = $request->doRequest();
        } catch (Exception $e) {
            PageLayout::postMessage(MessageBox::error('#' . $e->getCode() . ' ' . $e->getMessage()));
            return false;
        }

        if ($result['code'] != 200) {
            var_dump($result);
            die;
        }
        
        return $this->consumeResult($result['body'], $mode);
    }
    
    private function consumeResult($result, $mode) {
        if ($mode === 'csv') {
            $temp = explode("\n", $result);
            $temp = array_filter($temp);
            $rows = array_map(function ($row) { return str_getcsv($row, ';'); }, $temp);
            
            $header = array_shift($rows);
            $result = array();
            foreach ($rows as $row) {
                $index = reset($row);
                $result[$index] = array_combine($header, $row);
            }
        } elseif ($mode === 'json') {
            $result = json_decode($result, true);
        } elseif ($mode === 'php') {
            $result = unserialize($result);
        } elseif ($mode === 'xml') {
            $result = json_decode(json_encode(simplexml_load_string($result)), true);
        }
        
        return $result;
    }
}