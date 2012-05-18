<?
$router->contentType('application/json');

$data = array_map_recursive('studip_utf8encode', $data);
$json = json_encode($data);

echo Studip\ENV === 'development' ? indent($json) : $json;
