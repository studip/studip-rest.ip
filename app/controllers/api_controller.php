<?
class ApiController extends OAuthedController
{
# Adapted changes from https://github.com/luniki/trails/commit/6670fdefefd059d7a25a5b973e078f3e17385f04

    /**
     * This method extracts an action string and further arguments from it's
     * parameter. The action string is mapped to a method being called afterwards
     * using the said arguments. That method is called and a response object is
     * generated, populated and sent back to the dispatcher.
     *
     * @param type <description>
     *
     * @return type <description>
     */
    function perform($unconsumed) {

        list($action, $args, $format) = $this->extract_action_and_args($unconsumed);
        # try to detect format
        if (NULL === $format) {
            foreach (words('json php xml csv') as $f) {
                if (FALSE !== strpos($_SERVER['HTTP_ACCEPT'], $f)) {
                    $format = $f;
                    break;
                }
            }
        }

        # set format
        $this->format = $format ?: 'html';

        # call before filter
        $before_filter_result = $this->before_filter($action, $args);

        # send action to controller
        # TODO (mlunzena) shouldn't the after filter be triggered too?
        if (!(FALSE === $before_filter_result || $this->performed)) {

            $mapped_action = $this->map_action($action);

            # is action callable?
            if (method_exists($this, $mapped_action)) {
                call_user_func_array(array(&$this, $mapped_action), $args);
            }
            else {
                $this->does_not_understand($action, $args);
            }

            if (!$this->performed) {
                $this->render_action($action);
            }

            # call after filter
            $this->after_filter($action, $args);
        }

        return $this->response;
    }

    /**
    * Extracts action and args from a string.
    *
    * @param string the processed string
    *
    * @return array an array with two elements - a string containing the
    * action and an array of strings representing the args
    */
    function extract_action_and_args($string) {

        if ('' === $string) {
            return array('index', array(), NULL);
        }

        $matched = preg_match('/^(?P<path>\w+(?:\/\w+)*)(?:\.(?P<format>\w+))?$/',
                          $string, $matches);

        if (!$matched) {
            throw new Trails_Exception(400, "Bad Request");
        }

        $args = explode('/', $matches['path']);
        $action = array_shift($args);
        return array($action, $args, @$matches['format']);
    }

    function respond_to($ext) {
        return $this->format === $ext;
    }

######

    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);

        if (empty($action) and $GLOBALS['user']->id !== 'nobody') {
            $action = $GLOBALS['user']->id;
        }

        $method = $_SERVER['REQUEST_METHOD'];

        if (!is_callable(array($this, $action . '_' . $method . '_action'))) {
            array_unshift($args, $action);
            $action = 'index';
        }

        $action = $action . '_' . $method;
    }

    function rescue($exception) {
        if (get_class($exception) !== 'Flexi_TemplateNotFoundException') {
            return parent::rescue($exception);
        }

        $headers = array();
        $body    = '';
        $status  = 200;

        switch ($this->format) {
            case 'csv':
                $headers['Content-Type'] = 'text/csv;charset=windows-1252';
                $body .= self::csvEncode(array_keys(reset($this->data))) . "\n";
                foreach ($this->data as $row) {
                    $body .= self::csvEncode($row) . "\n";
                }
                break;
            case 'json':
                $headers['Content-Type'] = 'application/json;charset=windows-1252';
                $body = json_encode($this->data);
                break;
            case 'php':
                $headers['Content-Type'] = 'text/plain;charset=windows-1252';
                $body = serialize($this->data);
                break;
            case 'xml':
                $headers['Content-Type'] = 'text/xml';
                $body = self::asXML(array_values($this->data), array('root_node' => $this->action));
                break;
            default:
                $body = 'Missing or unknown output format';
                $status = 400;
                break;
        }
        return new Trails_Response($body, $headers, $status);
    }

    private static function csvEncode($item, $delimiter = ';', $quote = '"') {
        if (is_array($item)) {
            return implode($delimiter, array_map('self::csvEncode', $item));
        }
        return $quote . str_replace($quote, $quote . $quote, $item) . $quote;
    }

    private static function asXML($data, $parameters = array(), $pretty_print = true)
    {
        $dom = null;
        self::array_to_xml($data, $dom, $parameters);
        $xml = $dom->asXML();
        $xml = str_replace(array('&lt;![CDATA[', ']]&gt;'), array('<![CDATA[', ']]>'), $xml);

        // Pretty print, "inspired" by from http://gdatatips.blogspot.com/2008/11/xml-php-pretty-printer.html
        if ($pretty_print) {
            // Fix empty tag bug
            preg_match_all('/<(\w+)(?:\s[^>\/]*)?><\/\\1>/', $xml, $matches);
            $empty_tags = array();
            foreach ($matches as $index => $match) {
                $index = '<EMPTY_TAG_' . md5($index) . '/>';
                $empty_tags[$index] = $match[0];
            }
            $xml = str_replace(array_values($empty_tags), array_keys($empty_tags), $xml);

            $level = 1;
            $indent = 0;
            $pretty = array();

            $xml = explode("\n", preg_replace('/>\s*</', ">\n<", $xml));

            if (count($xml) and preg_match('/^<\?\s*xml/', reset($xml))) {
                array_push($pretty, array_shift($xml));
            }

            foreach ($xml as $node) {
                if (preg_match('/^<[\w]+[^>]*[^\/]>$/U', $node)) {
                    array_push($pretty, str_repeat("\t", $indent) . $node);
                    $indent += $level;
                } else {
                    if (preg_match('/^<\/.+>$/', $node)) {
                        $indent -= $level;
                    }

                    if ($indent < 0) {
                        $indent += $level;
                    }

                    array_push($pretty, str_repeat("\t", $indent) . $node);
                }
            }
            $xml = implode("\n", $pretty);

            $xml = str_replace(array_keys($empty_tags), array_values($empty_tags), $xml);
        }
        return $xml;
    }

    private static function array_to_xml($array, &$node, $parameters)
    {
        if ($node === null) {
            $root_node = $parameters['root_node'] ?: 'root';
            $root_attributes = $parameters['root_attributes'] ?: array();

            $attributes = '';
            foreach ($root_attributes as $key=>$value) {
                $attributes .= ' ' . $key . '="' . $value . '"';
            }

            $node = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $root_node . $attributes . ' />');
        }

        foreach ($array as $key => $value) {
            $index = is_numeric($key) ? 'item' : $key;

            if (is_array($value)) {
                $subnode = $node->addChild($index);
                self::array_to_xml($value, $subnode, $parameters);
            } else {
                $subnode = $node->addChild($index, $value);
            }

            if (!empty($parameters['add_id_index']) and is_numeric($key)) {
                $subnode->addAttribute('id', $key);
            }
        }
    }

}
