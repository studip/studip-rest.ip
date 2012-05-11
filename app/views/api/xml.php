<?
header('Content-Type: text/xml;charset=windows-1252');
echo RestIP\Helper::arrayToXML(reset($data), array(
    'root_node' => key($data),
));
