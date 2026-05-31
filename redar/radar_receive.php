<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$jsonFile = "radar_data.json";

// ---- RECEIVE FROM ESP32 ----
if (isset($_GET['angle']) && isset($_GET['distance'])) {

    $angle    = intval($_GET['angle']);
    $distance = intval($_GET['distance']);
    $time     = date("H:i:s");

    // Load existing data
    $data = [];
    if (file_exists($jsonFile)) {
        $data = json_decode(file_get_contents($jsonFile), true);
        if (!is_array($data)) $data = [];
    }

    // Add new entry
    $newEntry = [
        "angle"    => $angle,
        "distance" => $distance,
        "time"     => $time
    ];

    // Keep only last 20 readings
    array_push($data, $newEntry);
    if (count($data) > 20) {
        $data = array_slice($data, -20);
    }

    // Save to JSON file
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

    echo json_encode(["status" => "OK", "saved" => $newEntry]);
    exit;
}

// ---- SEND DATA TO DASHBOARD ----
if (isset($_GET['fetch'])) {
    if (file_exists($jsonFile)) {
        echo file_get_contents($jsonFile);
    } else {
        echo json_encode([]);
    }
    exit;
}

echo json_encode(["status" => "No action"]);
?>