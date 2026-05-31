<?php
/**
 * radar_api.php — Read-only API for the dashboard
 * Returns all stored radar points as a JSON object.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$jsonFile = __DIR__ . "/radar_data.json";

if (!file_exists($jsonFile)) {
    http_response_code(404);
    echo json_encode(["error" => "No data file found. Is the ESP32 sending data?"]);
    exit;
}

$raw  = file_get_contents($jsonFile);
$data = json_decode($raw, true);

if (!is_array($data) || count($data) === 0) {
    echo json_encode(["error" => "Empty or corrupt data file"]);
    exit;
}

// Build points array [0..180] => distance (latest reading per angle)
$points = array_fill(0, 181, 0);
foreach ($data as $entry) {
    $angle = intval($entry['angle'] ?? -1);
    $dist  = intval($entry['distance'] ?? 0);
    if ($angle >= 0 && $angle <= 180 && $dist >= 0 && $dist <= 500) {
        $points[$angle] = $dist;
    }
}

// Detect sweep direction from last 2 entries
$last      = array_slice($data, -2);
$direction = 1;
if (count($last) === 2) {
    $direction = (intval($last[1]['angle']) >= intval($last[0]['angle'])) ? 1 : -1;
}

// Most recent entry for stats
$latest = end($data);
reset($data);

echo json_encode([
    "points"    => $points,
    "direction" => $direction,
    "ts"        => filemtime($jsonFile),
    "count"     => count($data),
    "latest"    => [
        "angle"    => intval($latest['angle'] ?? 0),
        "distance" => intval($latest['distance'] ?? 0),
        "time"     => $latest['time'] ?? "--:--:--"
    ]
], JSON_PRETTY_PRINT);
?>
