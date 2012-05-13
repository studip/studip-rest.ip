<?
$data = array_map_recursive('studip_utf8encode', $data);
$data = array_map('array_values', $data);

$json = json_encode($data);
if (Studip\ENV === 'development') {
    $json = indent($json);
}

header('Content-Type: application/json');
echo $json;
die;
