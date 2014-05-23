<?php
namespace RestIP;

/**
 *
 **/
class Helper
{
    // TODO
    public static function UserHasAccessToRange($range_id/*, $user_id*/)
    {
        $user_id = $user_id ?: $GLOBALS['user']->id;

        return true;
    }

    /**
     *
     **/
    public static function getSemester($timestamp = null, $duration = 0)
    {
        static $semesters;
        if (!isset($semesters)) {
            $semesters = \SemesterData::GetSemesterArray();
        }

        if (!$timestamp || $duration == -1) {
            $timestamp = time();
        }

        foreach (array_reverse($semesters) as $semester) {
            if ($timestamp >= $semester['beginn'] && ($duration == -1 || ($timestamp + $duration <= $semester['ende']))) {
                return $semester['semester_id'];
            }
        }

        return false;
    }

    public static function isNumericIndexed($array) {
        return (is_array($array) && count(array_filter(array_keys($array), 'is_string')) == 0);
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

        // Pretty print, "inspired" by http://gdatatips.blogspot.com/2008/11/xml-php-pretty-printer.html
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
            if (is_numeric($key)) {
                throw new \Exception('Cannot compile numeric indexes');
            } elseif (preg_match('/\W/', $key)) {
                throw new \Exception(sprintf('Cannot compile index: "%s"', $key));
            }

            if (self::isNumericIndexed($value)) {
                foreach ($value as $k => $v) {
                    if (is_array($v)) {
                        $subnode = $node->addChild($key);
                        self::array_to_xml($v, $subnode, $parameters);
                    } else {
                        $subnode = $node->addChild($key, $v);
                    }
                }
            } elseif (is_array($value)) {
                $subnode = $node->addChild($key);
                self::array_to_xml($value, $subnode, $parameters);
            } else {
                $subnode = $node->addChild($key, $value);
            }

            if (!empty($parameters['add_id_index']) and is_numeric($key)) {
                $subnode->addAttribute('id', $key);
            }
        }
    }

    public static function Sanitize($value)
    {
        if (mb_detect_encoding($value, 'UTF-8', true)) {
            $value = utf8_decode($value);
        }
        return $value;
    }

    public static function getSSOPlugins()
    {
        return array_filter($GLOBALS['STUDIP_AUTH_PLUGIN'], function ($provider) {
                return \StudipAuthAbstract::GetInstance($provider) instanceof \StudipAuthSSO;
        });
    }
}
