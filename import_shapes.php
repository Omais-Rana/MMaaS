<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$file = 'd:\Project\Project\testFile\Evolity\shapes.txt';
if (!file_exists($file)) {
    echo "File not found\n";
    exit;
}

$handle = fopen($file, 'r');
$header = fgetcsv($handle);

$batch = [];
$chunkSize = 1000;
DB::disableQueryLog();

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 4) continue;
    $batch[] = [
        'shape_id' => $row[0],
        'shape_pt_lat' => (float)$row[1],
        'shape_pt_lon' => (float)$row[2],
        'shape_pt_sequence' => (int)$row[3],
        'shape_dist_traveled' => isset($row[4]) && $row[4] !== '' ? (float)$row[4] : null,
    ];

    if (count($batch) >= $chunkSize) {
        DB::table('shapes')->insert($batch);
        $batch = [];
    }
}

if (count($batch) > 0) {
    DB::table('shapes')->insert($batch);
}

fclose($handle);
echo "Import completed.\n";
