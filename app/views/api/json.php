<?
$router->contentType('application/json');

$json = json_encode($data);

echo Studip\ENV === 'development' ? indent($json) : $json;
