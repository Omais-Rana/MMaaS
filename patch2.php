<?php
$f = 'app/Http/Controllers/Api/RouteSearchController.php';
$c = file_get_contents($f);

$s = "'duration' => round((strtotime(\$busArrivalTime) - strtotime(\$busDepartureTime)) / 60),
                'stops' => \$transitStops,";

$r = "'duration' => round((strtotime(\$busArrivalTime) - strtotime(\$busDepartureTime)) / 60),
                'stops' => \$transitStops,
                'shape_pts' => !empty(\$route->shape_id) 
                    ? \Illuminate\Support\Facades\DB::table('shapes')
                        ->where('shape_id', \$route->shape_id)
                        // Note: To make it perfect, we could clip the shape sequence between the origin_sequence and dest_sequence 
                        // But for now, we pass the full shape or most of it for the trip.
                        ->orderBy('shape_pt_sequence')
                        ->get(['shape_pt_lat', 'shape_pt_lon'])
                        ->map(fn(\$pt) => [\$pt->shape_pt_lat, \$pt->shape_pt_lon])
                        ->toArray() 
                    : [],";

$c = str_replace(str_replace("\n", "\r\n", $s), $r, $c);
$c = str_replace($s, $r, $c);
file_put_contents($f, $c);
echo "Patched legs\n";
