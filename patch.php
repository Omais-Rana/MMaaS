<?php
$f = 'app/Http/Controllers/Api/RouteSearchController.php';
$c = file_get_contents($f);
$s1 = "'t.trip_headsign',\n                'u.name as operator_name'";
$s2 = "'t.trip_headsign',\r\n                'u.name as operator_name'";
$r = "'t.trip_headsign',\n                't.id as trip_id',\n                't.shape_id',\n                'st_origin.stop_sequence as origin_sequence',\n                'st_dest.stop_sequence as dest_sequence',\n                'u.name as operator_name'";

$c = str_replace($s1, $r, $c);
$c = str_replace($s2, $r, $c);

file_put_contents($f, $c);
echo "Patched\n";
