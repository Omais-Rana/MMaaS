<?php
$f = 'app/Http/Controllers/Api/RouteSearchController.php';
$c = file_get_contents($f);
$method = <<<EOD

    public function getAllRoutesWithShapes()
    {
        \$routeShapes = \Illuminate\Support\Facades\DB::select("
            SELECT r.route_short_name, MAX(r.route_long_name) as route_long_name, MAX(r.route_color) as route_color, t.shape_id 
            FROM routes r 
            JOIN trips t ON r.id = t.route_id 
            WHERE t.shape_id IS NOT NULL 
            GROUP BY r.route_short_name, t.shape_id
        ");
        
        \$points = \Illuminate\Support\Facades\DB::table('shapes')
            ->orderBy('shape_id')
            ->orderBy('shape_pt_sequence')
            ->get(['shape_id', 'shape_pt_lat', 'shape_pt_lon']);
            
        \$shapePoints = [];
        foreach(\$points as \$pt) {
            \$shapePoints[\$pt->shape_id][] = [\$pt->shape_pt_lat, \$pt->shape_pt_lon];
        }
        
        \$result = [];
        foreach(\$routeShapes as \$rs) {
            if (isset(\$shapePoints[\$rs->shape_id])) {
                \$result[] = [
                    'route_short_name' => \$rs->route_short_name,
                    'route_long_name' => \$rs->route_long_name,
                    'route_color' => \$rs->route_color,
                    'shape_pts' => \$shapePoints[\$rs->shape_id]
                ];
            }
        }
        
        return response()->json(['routes' => \$result]);
    }
}
EOD;

$c = preg_replace('/}[^}]*$/', $method, $c);
file_put_contents($f, $c);
echo "patched\n";
