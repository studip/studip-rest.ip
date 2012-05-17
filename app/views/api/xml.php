<?
$content = RestIP\Helper::arrayToXML($data, array(
    'root_node' => 'response',
), Studip\ENV === 'development');

header('Content-Type: text/xml;charset=windows-1252');
header('Content-Length: ' . strlen($content));
echo $content;
