<?
$content = RestIP\Helper::arrayToXML(reset($data), array(
    'root_node' => key($data),
), Studip\ENV === 'development');

header('Content-Type: text/xml;charset=windows-1252');
header('Content-Length: ' . strlen($content));
echo $content;
