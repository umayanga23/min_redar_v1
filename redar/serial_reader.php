<?php
// radar_write.php — receives data from ESP32 via GET request

define('DATA_FILE', __DIR__ . '/radar_data.json');
define('MAX_DISTANCE', 400);
define('LOCK_FILE',  __DIR__ . '/radar_data.lock');

header('Access-Control-Allow-Origin: *');
header('Content-Type: text/plain');

// ── Validate inputs ──────────────────────────────────────────────────────
$angle    = isset($_GET['angle'])    ? (int)$_GET['angle']    : -1;
$distance = isset($_GET['distance']) ? (int)$_GET['distance'] : 0;

if ($angle < 0 || $angle > 180) {
    http_response_code(400);
    echo "error: invalid angle ($angle)";
    exit;
}

if ($distance < 0 || $distance > MAX_DISTANCE) {
    $distance = 0;
}

// ── File lock so two requests never corrupt the JSON ─────────────────────
$lock = fopen(LOCK_FILE, 'w');
flock($lock, LOCK_EX);   // wait here until we can write safely

// ── Load existing data ───────────────────────────────────────────────────
$data = [
    'ts'         => 0,
    'direction'  => 1,
    'last_angle' => 0,
    'points'     => array_fill(0, 181, 0),
];

if (file_exists(DATA_FILE)) {
    $raw      = file_get_contents(DATA_FILE);
    $existing = json_decode($raw, true);
    if ($existing && isset($existing['points'])) {
        $data = $existing;
    }
}

// ── Work out sweep direction ─────────────────────────────────────────────
$lastAngle          = (int)($data['last_angle'] ?? $angle);
$data['direction']  = ($angle >= $lastAngle) ? 1 : -1;
$data['last_angle'] = $angle;

// ── Only clear the scan when a full new sweep STARTS ────────────────────
// Old code cleared on every angle==0, which wiped dots mid-display.
// Now we only clear when direction flips from right→left (new sweep begins).
static $prevDir = null;
$prevDir = $data['_prev_dir'] ?? 1;

if ($data['direction'] !== $prevDir && $angle === 0) {
    $data['points'] = array_fill(0, 181, 0);
}
$data['_prev_dir'] = $data['direction'];

// ── Write this angle's distance ──────────────────────────────────────────
$data['points'][$angle] = $distance;
$data['ts']             = round(microtime(true) * 1000);

// ── Save ─────────────────────────────────────────────────────────────────
file_put_contents(DATA_FILE, json_encode($data));

flock($lock, LOCK_UN);
fclose($lock);

http_response_code(200);
echo "ok: angle=$angle dist={$distance}cm dir={$data['direction']}";