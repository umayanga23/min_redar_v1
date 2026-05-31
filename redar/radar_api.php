<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$jsonFile = "radar_data.json";

// Read saved data
if (!file_exists($jsonFile)) {
    echo json_encode(["error" => "No data file found"]);
    exit;
}

$raw  = file_get_contents($jsonFile);
$data = json_decode($raw, true);

if (!is_array($data) || count($data) === 0) {
    echo json_encode(["error" => "Empty data"]);
    exit;
}

// Build points array [0..180] => distance
$points = array_fill(0, 181, 0);
foreach ($data as $entry) {
    $angle = intval($entry['angle']);
    $dist  = intval($entry['distance']);
    if ($angle >= 0 && $angle <= 180) {
        $points[$angle] = $dist;
    }
}

// Detect sweep direction from last 2 entries
$last = array_slice($data, -2);
$direction = 1;
if (count($last) === 2) {
    $direction = $last[1]['angle'] >= $last[0]['angle'] ? 1 : -1;
}

echo json_encode([
    "points"    => $points,
    "direction" => $direction,
    "ts"        => filemtime($jsonFile),   // changes when ESP32 writes new data
    "count"     => count($data)
]);
?>