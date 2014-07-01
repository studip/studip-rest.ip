<?
$router->contentType('application/json;charset=utf-8');

$json = json_encode($data);

echo Studip\ENV === 'development' ? indent($json) : $json;
