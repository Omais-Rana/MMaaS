<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$file = 'd:\Project\Project\testFile\Evolity\trips.txt';
if (!file_exists($file)) {
    echo "File not found\n";
    exit;
}

$handle = fopen($file, 'r');
$header = fgetcsv($handle);
$tripIdIdx = array_search('trip_id', $header);
$shapeIdIdx = array_search('shape_id', $header);

if ($tripIdIdx === false || $shapeIdIdx === false) {
    echo "Columns not found\n";
    exit;
}

DB::disableQueryLog();

$count = 0;
while (($row = fgetcsv($handle)) !== false) {
    if (!isset($row[$tripIdIdx]) || !isset($row[$shapeIdIdx])) continue;
    $tripId = $row[$tripIdIdx];
    $shapeId = $row[$shapeIdIdx];

    if ($shapeId !== '') {
        DB::statement("UPDATE trips SET shape_id = ? WHERE trip_id = ?", [$shapeId, $tripId]);
        $count++;
        if ($count % 1000 === 0) echo "Updated $count trips...\n";
    }
}

fclose($handle);
echo "Update completed. Total: $count\n";
