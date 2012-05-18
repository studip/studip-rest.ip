<?
$router->contentType('text/xml;charset=windows-1252');

$content = RestIP\Helper::arrayToXML($data, array(
    'root_node' => 'response',
), Studip\ENV === 'development');

echo $content;
