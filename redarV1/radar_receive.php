<?php
/**
 * radar_receive.php — Receives readings from ESP32 and stores them.
 * Also serves data if ?fetch is passed (for direct polling).
 *
 * ESP32 URL format:
 *   http://<server-ip>/redar/radar_receive.php?angle=90&distance=125
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$jsonFile = __DIR__ . "/radar_data.json";
$logFile  = __DIR__ . "/radar_log.txt";
$MAX_ENTRIES = 30;   // keep last 30 readings in radar_data.json
$MAX_LOG     = 5000; // max lines in log file

// ─── Receive from ESP32 ──────────────────────────────────────────────────
if (isset($_GET['angle']) && isset($_GET['distance'])) {

    $angle    = intval($_GET['angle']);
    $distance = intval($_GET['distance']);

    // Validate
    if ($angle < 0 || $angle > 180) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid angle: must be 0-180"]);
        exit;
    }
    if ($distance < 0 || $distance > 600) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid distance: must be 0-600"]);
        exit;
    }

    $timestamp = date("Y-m-d H:i:s");
    $timeShort = date("H:i:s");

    // Load existing data
    $data = [];
    if (file_exists($jsonFile)) {
        $raw = file_get_contents($jsonFile);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $data = $decoded;
        }
    }

    $entry = [
        "angle"    => $angle,
        "distance" => $distance,
        "time"     => $timeShort,
        "ts"       => $timestamp
    ];

    array_push($data, $entry);

    // Keep last N entries
    if (count($data) > $MAX_ENTRIES) {
        $data = array_slice($data, -$MAX_ENTRIES);
    }

    // Write radar data
    $written = file_put_contents(
        $jsonFile,
        json_encode($data, JSON_PRETTY_PRINT),
        LOCK_EX
    );

    if ($written === false) {
        http_response_code(500);
        echo json_encode(["error" => "Failed to write data file. Check permissions."]);
        exit;
    }

    // Append to persistent log (for CSV export from server side)
    $logLine = "{$timestamp},{$angle},{$distance}\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

    // Trim log if too large
    if (file_exists($logFile)) {
        $lines = file($logFile);
        if (count($lines) > $MAX_LOG) {
            $trimmed = array_slice($lines, -$MAX_LOG);
            file_put_contents($logFile, implode('', $trimmed), LOCK_EX);
        }
    }

    echo json_encode([
        "status"   => "ok",
        "saved"    => $entry,
        "total"    => count($data)
    ]);
    exit;
}

// ─── Serve data to dashboard ─────────────────────────────────────────────
if (isset($_GET['fetch'])) {
    if (file_exists($jsonFile)) {
        echo file_get_contents($jsonFile);
    } else {
        echo json_encode([]);
    }
    exit;
}

// ─── Download full log as CSV ─────────────────────────────────────────────
if (isset($_GET['download_log'])) {
    if (!file_exists($logFile)) {
        echo json_encode(["error" => "No log file found"]);
        exit;
    }
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=radar_full_log_" . date('Y-m-d') . ".csv");
    echo "Timestamp,Angle (degrees),Distance (cm)\n";
    readfile($logFile);
    exit;
}

// ─── Health check ────────────────────────────────────────────────────────
echo json_encode([
    "status"      => "ready",
    "endpoints"   => [
        "receive"   => "?angle=90&distance=120",
        "fetch"     => "?fetch",
        "log"       => "?download_log"
    ],
    "data_file"   => file_exists($jsonFile) ? "present" : "missing",
    "log_file"    => file_exists($logFile)  ? "present" : "missing",
    "server_time" => date("Y-m-d H:i:s")
]);
?>
