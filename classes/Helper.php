<?php
namespace RestIP;

/**
 *
 **/
class Helper
{
    /**
     *
     **/
    public static function getUserData()
    {
        static $user_data;
        if (!is_array($user_data)) {
            $query = "SELECT val FROM user_data WHERE sid = ?";
            $statement = \DBManager::get()->prepare($query);
            $statement->execute(array($GLOBALS['user']->id));
            $user_data = unserialize($statement->fetchColumn() ?: 'a:0:{}');
        }
        return $user_data;
    }

    /**
     *
     **/
    public static function setUserData($user_data)
    {
        $query = "INSERT INTO user_data (sid, val, chdate)
                  VALUES (?, ?, UNIX_TIMESTAMP())
                  ON DUPLICATE KEY UPDATE val = VALUES(val), chate = UNIX_TIMESTAMP()";
        $statement = \DBManager::get()->prepare($query);
        $statement->execute(array($GLOBALS['user']->id, serialize($user_data)));
    }

    /**
     *
     **/
    public static function arrayToXML($data, $parameters = array(), $pretty_print = true)
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

    /**
     *
     **/
    public static function array_to_xml($array, &$node, $parameters)
    {
        if ($node === null) {
            $root_node = $parameters['root_node'] ?: 'root';
            $root_attributes = $parameters['root_attributes'] ?: array();

            $attributes = '';
            foreach ($root_attributes as $key=>$value) {
                $attributes .= ' ' . $key . '="' . $value . '"';
            }

            $node = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $root_node . $attributes . ' />');
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
