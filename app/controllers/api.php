<?
class ApiController extends OAuthedController
{
    function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);

        $range = array_shift($args);        
        list($range_id, $output_type) = explode('.', $range);
        array_unshift($args, $range_id);
        
        $this->output_type = $output_type;
        $this->action      = $action;
        $this->args        = $args;
    }
    
    function news_action($range_id) {
        $range_id === 'studip' || $this->isAuthorized();
        
        require_once 'lib/classes/StudipNews.class.php';

        $this->data = StudipNews::GetNewsByRange($range_id);
    }
    
    function rescue($exception) {
        if (get_class($exception) !== 'Flexi_TemplateNotFoundException') {
            return parent::rescue($exception);
        }

        $headers = array();
        $body    = '';
        $status  = 200;
        
        switch ($this->output_type) {
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

        if ($pretty_print) {

            // Pretty print, "inspired" by from http://gdatatips.blogspot.com/2008/11/xml-php-pretty-printer.html
            $level = 1;
            $indent = 0;
            $pretty = array();

            $xml = explode("\n", preg_replace('/>\s*</', ">\n<", $xml));

            if (count($xml) and preg_match('/^<\?\s*xml/', reset($xml))) {
                array_push($pretty, array_shift($xml));
            }

            foreach ($xml as $node) {
                if (preg_match('/^<[\w]+[^>\/]*>$/U', $node)) {
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
