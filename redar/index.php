<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>RADAR — Object Detection</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@300;400;600;700&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --green:      #00ffa3;
      --green-dim:  #00cc7a;
      --green-dark: #002918;
      --green-glow: rgba(0,255,163,0.15);
      --red:        #ff2244;
      --red-glow:   rgba(255,34,68,0.25);
      --amber:      #ffbb00;
      --amber-glow: rgba(255,187,0,0.2);
      --bg:         #050e08;
      --panel:      #060f09;
      --border:     rgba(0,255,163,0.14);
      --font-mono:  'Share Tech Mono', monospace;
      --font-hud:   'Orbitron', sans-serif;
      --font-body:  'Rajdhani', sans-serif;
    }

    html, body {
      height: 100%; width: 100%;
      background: var(--bg);
      color: var(--green);
      font-family: var(--font-mono);
      overflow: hidden;
    }

    /* ── Noise grain overlay ── */
    body::before {
      content: '';
      position: fixed; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
      pointer-events: none; z-index: 998; opacity: 0.4;
    }
    /* ── CRT scanlines ── */
    body::after {
      content: '';
      position: fixed; inset: 0;
      background: repeating-linear-gradient(to bottom,
        transparent 0px, transparent 2px,
        rgba(0,0,0,0.06) 2px, rgba(0,0,0,0.06) 4px);
      pointer-events: none; z-index: 999;
    }

    /* ── Layout ── */
    .shell {
      display: grid;
      grid-template-columns: 1fr 300px;
      grid-template-rows: 52px 1fr 44px;
      height: 100vh;
    }

    /* ── Top bar ── */
    .topbar {
      grid-column: 1 / -1;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 20px;
      border-bottom: 1px solid var(--border);
      background: linear-gradient(90deg, #060f09 0%, #040c07 100%);
      position: relative;
      z-index: 10;
    }
    .topbar::after {
      content: '';
      position: absolute; bottom: -2px; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, var(--green), transparent);
      opacity: 0.3;
    }

    .topbar-left { display: flex; align-items: center; gap: 18px; }

    .logo-hex {
      width: 32px; height: 32px;
      display: flex; align-items: center; justify-content: center;
      position: relative;
    }
    .logo-hex svg { width: 100%; height: 100%; }

    .topbar-title {
      font-family: var(--font-hud);
      font-size: 14px; font-weight: 900;
      letter-spacing: 8px;
      text-transform: uppercase;
      color: var(--green);
      text-shadow: 0 0 20px var(--green), 0 0 40px rgba(0,255,163,0.3);
    }
    .topbar-sub {
      font-family: var(--font-body);
      font-size: 11px; letter-spacing: 3px;
      color: rgba(0,255,163,0.35);
      margin-top: 1px;
    }

    .topbar-meta {
      display: flex; gap: 28px; align-items: center;
      font-size: 11px; font-family: var(--font-body);
      font-weight: 600; letter-spacing: 1px;
    }
    .meta-item {
      display: flex; align-items: center; gap: 7px;
      color: rgba(0,255,163,0.6);
    }
    .meta-item strong { color: var(--green); font-weight: 700; }

    .dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: var(--green);
      box-shadow: 0 0 8px var(--green);
      animation: blink 1.4s ease-in-out infinite;
      flex-shrink: 0;
    }
    .dot.red    { background: var(--red);   box-shadow: 0 0 8px var(--red); }
    .dot.amber  { background: var(--amber); box-shadow: 0 0 8px var(--amber); }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.2} }

    .badge {
      padding: 2px 8px;
      border: 1px solid var(--border);
      font-family: var(--font-hud);
      font-size: 9px; letter-spacing: 2px;
      color: rgba(0,255,163,0.5);
      background: rgba(0,255,163,0.04);
    }

    /* ── Radar area ── */
    .radar-area {
      display: flex; align-items: center; justify-content: center;
      padding: 20px;
      position: relative;
      background:
        radial-gradient(ellipse 70% 60% at 50% 55%, #071a0d 0%, var(--bg) 100%);
      overflow: hidden;
    }
    .radar-area::before {
      content: '';
      position: absolute; inset: 0;
      background:
        linear-gradient(135deg, rgba(0,255,163,0.02) 0%, transparent 50%),
        linear-gradient(315deg, rgba(0,255,163,0.02) 0%, transparent 50%);
      pointer-events: none;
    }

    canvas#radar {
      display: block;
      border-radius: 50%;
      box-shadow:
        0 0 0 1px rgba(0,255,163,0.1),
        0 0 0 3px rgba(0,255,163,0.04),
        0 0 60px rgba(0,255,163,0.1),
        0 0 120px rgba(0,255,163,0.05);
      image-rendering: pixelated;
    }

    /* threat ring label */
    .threat-ring {
      position: absolute; bottom: 16px; left: 50%;
      transform: translateX(-50%);
      display: flex; gap: 16px;
    }
    .threat-badge {
      display: flex; align-items: center; gap: 5px;
      font-family: var(--font-body); font-size: 10px;
      font-weight: 700; letter-spacing: 2px;
      opacity: 0.7;
    }
    .threat-swatch {
      width: 10px; height: 10px; border-radius: 2px;
    }

    /* ── Connection warning overlay ── */
    #conn-warn {
      position: absolute; top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(5,14,8,0.95);
      border: 1px solid var(--red);
      padding: 28px 44px;
      text-align: center;
      font-family: var(--font-hud); font-size: 12px;
      color: var(--red); letter-spacing: 3px;
      box-shadow: 0 0 40px var(--red-glow), inset 0 0 30px rgba(255,34,68,0.05);
      display: none; z-index: 100;
      animation: warnPulse 2s ease-in-out infinite;
    }
    @keyframes warnPulse {
      0%,100% { box-shadow: 0 0 40px var(--red-glow), inset 0 0 30px rgba(255,34,68,0.05); }
      50%      { box-shadow: 0 0 60px var(--red-glow), inset 0 0 50px rgba(255,34,68,0.1); }
    }
    #conn-warn.visible { display: block; }

    /* ── Side panel ── */
    .side {
      display: flex; flex-direction: column;
      border-left: 1px solid var(--border);
      background: var(--panel);
      overflow: hidden;
      position: relative;
    }
    .side::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, var(--green), transparent);
      opacity: 0.15;
    }

    .panel-block {
      padding: 14px 16px;
      border-bottom: 1px solid var(--border);
      position: relative;
    }

    .panel-label {
      font-family: var(--font-hud);
      font-size: 8px; letter-spacing: 3px;
      color: rgba(0,255,163,0.35);
      text-transform: uppercase;
      margin-bottom: 12px;
      display: flex; align-items: center; gap: 8px;
    }
    .panel-label::after {
      content: ''; flex: 1; height: 1px;
      background: var(--border);
    }

    /* ── Live stats ── */
    .stat-grid {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 8px;
    }
    .stat-card {
      background: rgba(0,255,163,0.03);
      border: 1px solid rgba(0,255,163,0.08);
      padding: 10px 12px;
      position: relative; overflow: hidden;
    }
    .stat-card::after {
      content: ''; position: absolute;
      top: 0; left: 0; right: 0; height: 1px;
      background: var(--green); opacity: 0.3;
    }
    .stat-card-label {
      font-family: var(--font-body); font-weight: 600;
      font-size: 10px; letter-spacing: 2px;
      color: rgba(0,255,163,0.4); margin-bottom: 4px;
    }
    .stat-card-val {
      font-family: var(--font-hud); font-size: 26px; font-weight: 700;
      color: var(--green);
      text-shadow: 0 0 12px rgba(0,255,163,0.5);
      line-height: 1;
    }
    .stat-card-unit {
      font-size: 9px; opacity: 0.4; margin-left: 2px;
    }

    .sweep-row {
      margin-top: 10px; padding: 6px 10px;
      background: rgba(0,255,163,0.03);
      border: 1px solid rgba(0,255,163,0.08);
      display: flex; justify-content: space-between; align-items: center;
    }
    .sweep-label {
      font-family: var(--font-body); font-size: 10px;
      font-weight: 600; letter-spacing: 2px;
      color: rgba(0,255,163,0.4);
    }
    #stat-dir {
      font-family: var(--font-hud); font-size: 11px;
      color: var(--green);
    }

    /* ── Threat meter ── */
    .threat-meter-wrap { }
    .threat-bar-track {
      height: 6px; background: rgba(0,255,163,0.07);
      border: 1px solid rgba(0,255,163,0.1);
      border-radius: 2px; overflow: hidden; margin-bottom: 6px;
    }
    .threat-bar-fill {
      height: 100%; width: 0%;
      transition: width 0.6s ease, background 0.4s;
      border-radius: 2px;
    }
    .threat-level-labels {
      display: flex; justify-content: space-between;
      font-family: var(--font-body); font-size: 9px;
      font-weight: 700; letter-spacing: 1px;
      color: rgba(0,255,163,0.3);
    }
    #threat-text {
      font-family: var(--font-hud); font-size: 10px;
      letter-spacing: 2px; text-align: center;
      margin-top: 8px;
    }

    /* ── Signal bars ── */
    .bars {
      display: flex; align-items: flex-end; gap: 3px;
      height: 28px; margin-top: 6px;
    }
    .bar {
      flex: 1; background: var(--green);
      opacity: 0.08; border-radius: 1px 1px 0 0;
      transition: opacity 0.25s, height 0.3s;
      min-height: 3px;
    }
    .bar.active { opacity: 0.8; }

    /* ── Targets list ── */
    .targets-list {
      flex: 1; overflow-y: auto;
      scrollbar-width: thin;
      scrollbar-color: rgba(0,255,163,0.15) transparent;
    }

    .target-item {
      display: grid;
      grid-template-columns: 10px 44px 1fr auto;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-bottom: 1px solid rgba(0,255,163,0.05);
      font-size: 12px;
      transition: background 0.15s;
      animation: slideIn 0.25s ease;
    }
    @keyframes slideIn {
      from { opacity: 0; transform: translateX(10px); }
      to   { opacity: 1; transform: none; }
    }
    .target-item:hover { background: rgba(0,255,163,0.04); }

    .target-icon {
      width: 8px; height: 8px; border-radius: 50%;
      background: var(--green); box-shadow: 0 0 6px var(--green);
    }
    .target-icon.close { background: var(--red);   box-shadow: 0 0 6px var(--red); }
    .target-icon.mid   { background: var(--amber); box-shadow: 0 0 6px var(--amber); }

    .target-angle {
      font-family: var(--font-hud); font-size: 10px;
      color: rgba(0,255,163,0.5);
    }
    .target-status {
      font-family: var(--font-body); font-size: 10px;
      font-weight: 700; letter-spacing: 1px;
    }
    .target-dist {
      font-family: var(--font-hud); font-size: 12px;
      color: var(--green); text-align: right;
    }

    /* ── Bottom bar ── */
    .bottombar {
      grid-column: 1 / -1;
      display: flex; align-items: center; gap: 0;
      border-top: 1px solid var(--border);
      background: var(--panel);
      font-family: var(--font-body);
      font-size: 10px; font-weight: 600;
      letter-spacing: 1.5px;
      overflow: hidden;
    }
    .bot-item {
      padding: 0 20px; height: 100%;
      display: flex; align-items: center; gap: 7px;
      border-right: 1px solid var(--border);
      color: rgba(0,255,163,0.45);
      white-space: nowrap;
    }
    .bot-item strong { color: rgba(0,255,163,0.75); font-weight: 700; }
    .bot-spacer { flex: 1; }

    /* ── Scrolling status ticker ── */
    .ticker-wrap {
      flex: 1; overflow: hidden; padding: 0 12px;
      height: 100%; display: flex; align-items: center;
    }
    .ticker {
      display: inline-flex; gap: 40px;
      animation: ticker 18s linear infinite;
      white-space: nowrap; color: rgba(0,255,163,0.3);
    }
    @keyframes ticker {
      from { transform: translateX(100%); }
      to   { transform: translateX(-100%); }
    }
  </style>
</head>
<body>

<div class="shell">

  <!-- Top bar -->
  <header class="topbar">
    <div class="topbar-left">
      <div class="logo-hex">
        <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
          <polygon points="16,2 28,9 28,23 16,30 4,23 4,9" stroke="#00ffa3" stroke-width="1.5" fill="rgba(0,255,163,0.07)"/>
          <polygon points="16,7 24,11.5 24,20.5 16,25 8,20.5 8,11.5" stroke="#00ffa3" stroke-width="0.8" fill="none" opacity="0.4"/>
          <circle cx="16" cy="16" r="3" fill="#00ffa3" opacity="0.8"/>
        </svg>
      </div>
      <div>
        <div class="topbar-title">RADAR SWEEP SYSTEM</div>
        <div class="topbar-sub">TACTICAL OBJECT DETECTION · ESP32</div>
      </div>
    </div>
    <div class="topbar-meta">
      <div class="meta-item">
        <span class="dot" id="conn-dot"></span>
        <span id="conn-label" style="font-family:var(--font-hud);font-size:9px;letter-spacing:2px;">CONNECTING</span>
      </div>
      <div class="meta-item">
        <span style="opacity:0.4">TIME</span>
        <strong id="ts-label" style="font-family:var(--font-hud);font-size:11px;">--:--:--</strong>
      </div>
      <div class="badge">HC-SR04</div>
      <div class="badge">SG90</div>
    </div>
  </header>

  <!-- Radar canvas -->
  <main class="radar-area">
    <canvas id="radar"></canvas>

    <div class="threat-ring">
      <div class="threat-badge">
        <div class="threat-swatch" style="background:var(--red)"></div>
        <span style="color:var(--red)">CLOSE &lt;80cm</span>
      </div>
      <div class="threat-badge">
        <div class="threat-swatch" style="background:var(--amber)"></div>
        <span style="color:var(--amber)">MID &lt;200cm</span>
      </div>
      <div class="threat-badge">
        <div class="threat-swatch" style="background:var(--green)"></div>
        <span style="color:var(--green)">CLEAR &gt;200cm</span>
      </div>
    </div>

    <div id="conn-warn">⚠ NO SERIAL DATA<br><span style="font-size:9px;opacity:.5;letter-spacing:1px;font-family:var(--font-mono)">Check radar_api.php is running</span></div>
  </main>

  <!-- Side panel -->
  <aside class="side">

    <!-- Live readings -->
    <div class="panel-block">
      <div class="panel-label">Live Telemetry</div>
      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-card-label">ANGLE</div>
          <div class="stat-card-val">
            <span id="stat-angle">---</span><span class="stat-card-unit">°</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-label">DISTANCE</div>
          <div class="stat-card-val">
            <span id="stat-dist">---</span><span class="stat-card-unit">cm</span>
          </div>
        </div>
      </div>
      <div class="sweep-row">
        <span class="sweep-label">SWEEP DIR</span>
        <span id="stat-dir">---</span>
      </div>
    </div>

    <!-- Threat level -->
    <div class="panel-block">
      <div class="panel-label">Threat Level</div>
      <div class="threat-meter-wrap">
        <div class="threat-bar-track">
          <div class="threat-bar-fill" id="threat-fill"></div>
        </div>
        <div class="threat-level-labels">
          <span>LOW</span><span>MED</span><span>HIGH</span><span>CRIT</span>
        </div>
        <div id="threat-text" style="color:var(--green);opacity:0.5">SCANNING...</div>
      </div>
    </div>

    <!-- Signal strength -->
    <div class="panel-block">
      <div class="panel-label">Signal Density</div>
      <div class="bars" id="signal-bars">
        <div class="bar" id="bar0"></div><div class="bar" id="bar1"></div>
        <div class="bar" id="bar2"></div><div class="bar" id="bar3"></div>
        <div class="bar" id="bar4"></div><div class="bar" id="bar5"></div>
        <div class="bar" id="bar6"></div><div class="bar" id="bar7"></div>
        <div class="bar" id="bar8"></div><div class="bar" id="bar9"></div>
        <div class="bar" id="bar10"></div><div class="bar" id="bar11"></div>
      </div>
    </div>

    <!-- Targets -->
    <div class="panel-block" style="padding-bottom:8px;">
      <div class="panel-label">Detected Targets</div>
    </div>
    <div class="targets-list" id="targets-list">
      <div style="padding:24px 16px;opacity:.25;font-size:11px;font-family:var(--font-body);letter-spacing:1px;">
        NO TARGETS DETECTED
      </div>
    </div>
  </aside>

  <!-- Bottom bar -->
  <footer class="bottombar">
    <div class="bot-item"><span>MAX RANGE</span><strong>400cm</strong></div>
    <div class="bot-item"><span>SWEEP</span><strong>0°–180°</strong></div>
    <div class="bot-item"><span id="fps-label">FPS</span><strong id="fps-val">--</strong></div>
    <div class="bot-item"><span id="targets-count">TARGETS</span><strong id="targets-val">0</strong></div>
    <div class="ticker-wrap">
      <div class="ticker">
        SYSTEM NOMINAL · ESP32 ACTIVE · HC-SR04 ULTRASONIC · SERVO SWEEP 0°→180° ·
        OBJECT DETECTION ENABLED · REAL-TIME TELEMETRY · SECURE CHANNEL
      </div>
    </div>
  </footer>

</div>

<script>
// ── Constants ──────────────────────────────────────────────────────────────
const MAX_DIST  = 400;
const CLOSE_THR = 80;
const MID_THR   = 200;
const POLL_MS   = 80;
const TRAIL_LEN = 8;

// ── Canvas ─────────────────────────────────────────────────────────────────
const canvas = document.getElementById('radar');
const ctx    = canvas.getContext('2d');

function resize() {
  const area = document.querySelector('.radar-area');
  const size = Math.min(area.clientWidth - 48, area.clientHeight - 80, 560);
  canvas.width = canvas.height = size;
}
resize();
window.addEventListener('resize', () => { resize(); drawStatic(); });

const C  = () => canvas.width / 2;
const R  = () => C() * 0.9;

// ── State ──────────────────────────────────────────────────────────────────
let points      = new Array(181).fill(0);
let sweepAngle  = 0;
let sweepDir    = 1;
let trailAngles = [];
let lastTs      = 0;
let connected   = false;
let frameCount  = 0, fpsT = performance.now();

// ── Colours ────────────────────────────────────────────────────────────────
function distColor(dist, alpha = 1) {
  if (dist <= 0) return null;
  if (dist < CLOSE_THR) return `rgba(255,34,68,${alpha})`;
  if (dist < MID_THR)   return `rgba(255,187,0,${alpha})`;
  return `rgba(0,255,163,${alpha})`;
}

function polar(angle, dist) {
  const r   = (dist / MAX_DIST) * R();
  const rad = (angle - 90) * Math.PI / 180;
  return { x: C() + r * Math.cos(rad), y: C() + r * Math.sin(rad) };
}

// ── Static grid ────────────────────────────────────────────────────────────
function drawStatic() {
  const c = C(), r = R();
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  // Background fill
  const bg = ctx.createRadialGradient(c, c, 0, c, c, r);
  bg.addColorStop(0,   '#071e0f');
  bg.addColorStop(0.6, '#04120a');
  bg.addColorStop(1,   '#020d06');
  ctx.fillStyle = bg;
  ctx.beginPath();
  ctx.arc(c, c, r + 4, 0, Math.PI * 2);
  ctx.fill();

  // Threat zone fills (subtle)
  const zones = [
    { pct: CLOSE_THR / MAX_DIST, color: 'rgba(255,34,68,0.04)' },
    { pct: MID_THR   / MAX_DIST, color: 'rgba(255,187,0,0.03)' },
  ];
  zones.forEach(z => {
    const zr = r * z.pct;
    ctx.beginPath();
    ctx.arc(c, c, zr, Math.PI, 0);
    ctx.closePath();
    ctx.fillStyle = z.color;
    ctx.fill();
  });

  ctx.save();

  // Rings
  [0.2, 0.4, 0.6, 0.8, 1.0].forEach((f, i) => {
    ctx.beginPath();
    ctx.arc(c, c, r * f, Math.PI, 0);
    const alpha = i === 4 ? 0.2 : 0.1;
    ctx.strokeStyle = `rgba(0,255,163,${alpha})`;
    ctx.lineWidth = i === 4 ? 1.5 : 1;
    ctx.setLineDash(i < 4 ? [4, 6] : []);
    ctx.stroke();
    ctx.setLineDash([]);

    // Labels
    if (i < 4) {
      const label = Math.round(MAX_DIST * f) + '';
      ctx.fillStyle = 'rgba(0,255,163,0.25)';
      ctx.font = `${Math.max(8, canvas.width * 0.016)}px Share Tech Mono`;
      ctx.textAlign = 'left';
      ctx.fillText(label, c + 4, c - r * f + 10);
    }
  });

  // Spokes every 15°
  for (let a = 0; a <= 180; a += 15) {
    const rad = (a - 90) * Math.PI / 180;
    const isMajor = a % 30 === 0;
    ctx.beginPath();
    ctx.moveTo(c, c);
    ctx.lineTo(c + r * Math.cos(rad), c + r * Math.sin(rad));
    ctx.strokeStyle = isMajor ? 'rgba(0,255,163,0.14)' : 'rgba(0,255,163,0.05)';
    ctx.lineWidth = isMajor ? 1 : 0.5;
    ctx.stroke();

    // Angle labels for major spokes
    if (isMajor) {
      const lx = c + (r + 16) * Math.cos(rad);
      const ly = c + (r + 16) * Math.sin(rad);
      ctx.fillStyle = 'rgba(0,255,163,0.4)';
      ctx.font = `${Math.max(9, canvas.width * 0.019)}px Share Tech Mono`;
      ctx.textAlign = 'center';
      ctx.fillText(a + '°', lx, ly + 4);
    }
  }

  // Baseline (horizon line)
  ctx.beginPath();
  ctx.moveTo(c - r, c); ctx.lineTo(c + r, c);
  ctx.strokeStyle = 'rgba(0,255,163,0.2)';
  ctx.lineWidth = 1; ctx.stroke();

  // Center cross
  const cs = 6;
  ctx.beginPath();
  ctx.moveTo(c - cs, c); ctx.lineTo(c + cs, c);
  ctx.moveTo(c, c - cs); ctx.lineTo(c, c + cs);
  ctx.strokeStyle = 'rgba(0,255,163,0.5)';
  ctx.lineWidth = 1; ctx.stroke();

  ctx.restore();
}

// ── Sweep trail ────────────────────────────────────────────────────────────
function drawSweep() {
  const c = C(), r = R();

  trailAngles.push(sweepAngle);
  if (trailAngles.length > TRAIL_LEN) trailAngles.shift();

  // Sweep cone (filled arc)
  if (trailAngles.length > 1) {
    const first = trailAngles[0];
    const last  = trailAngles[trailAngles.length - 1];
    const r1    = (first - 90) * Math.PI / 180;
    const r2    = (last  - 90) * Math.PI / 180;

    const cone = ctx.createConicalGradient
      ? null  // fallback below
      : null;

    // Manual cone using radial gradient trick
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(c, c);
    ctx.arc(c, c, r, Math.min(r1, r2), Math.max(r1, r2));
    ctx.closePath();
    const coneGrad = ctx.createRadialGradient(c, c, 0, c, c, r);
    coneGrad.addColorStop(0,   'rgba(0,255,163,0.18)');
    coneGrad.addColorStop(0.7, 'rgba(0,255,163,0.06)');
    coneGrad.addColorStop(1,   'rgba(0,255,163,0)');
    ctx.fillStyle = coneGrad;
    ctx.fill();
    ctx.restore();
  }

  // Trail lines
  trailAngles.forEach((a, i) => {
    const alpha = ((i + 1) / TRAIL_LEN) * 0.7;
    const rad   = (a - 90) * Math.PI / 180;
    const grd   = ctx.createLinearGradient(c, c, c + r * Math.cos(rad), c + r * Math.sin(rad));
    grd.addColorStop(0,   `rgba(0,255,163,${alpha})`);
    grd.addColorStop(0.7, `rgba(0,255,163,${alpha * 0.3})`);
    grd.addColorStop(1,   'rgba(0,255,163,0)');

    ctx.save();
    ctx.beginPath();
    ctx.moveTo(c, c);
    ctx.lineTo(c + r * Math.cos(rad), c + r * Math.sin(rad));
    ctx.strokeStyle = grd;
    ctx.lineWidth = i === trailAngles.length - 1 ? 2 : 1;
    ctx.stroke();
    ctx.restore();
  });

  // Bright tip
  const tipRad = (sweepAngle - 90) * Math.PI / 180;
  ctx.save();
  ctx.beginPath();
  ctx.moveTo(c, c);
  ctx.lineTo(c + r * Math.cos(tipRad), c + r * Math.sin(tipRad));
  ctx.strokeStyle = 'rgba(0,255,163,0.9)';
  ctx.lineWidth = 1.5;
  ctx.shadowColor = 'rgba(0,255,163,0.8)';
  ctx.shadowBlur = 8;
  ctx.stroke();
  ctx.restore();
}

// ── Objects ────────────────────────────────────────────────────────────────
function drawObjects() {
  points.forEach((dist, angle) => {
    if (dist <= 0) return;
    const col = distColor(dist, 0.95);
    if (!col) return;
    const p   = polar(angle, dist);
    const sz  = dist < CLOSE_THR ? 5 : 4;

    ctx.save();

    // Glow
    const grd = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, sz * 4);
    grd.addColorStop(0, col);
    grd.addColorStop(1, col.replace(/[\d.]+\)$/, '0)'));
    ctx.beginPath();
    ctx.arc(p.x, p.y, sz * 4, 0, Math.PI * 2);
    ctx.fillStyle = grd;
    ctx.fill();

    // Core dot
    ctx.beginPath();
    ctx.arc(p.x, p.y, sz, 0, Math.PI * 2);
    ctx.fillStyle = col;
    ctx.shadowColor = col;
    ctx.shadowBlur = 12;
    ctx.fill();

    // Echo ring
    ctx.beginPath();
    ctx.arc(p.x, p.y, sz + 5, 0, Math.PI * 2);
    ctx.strokeStyle = col.replace(/[\d.]+\)$/, '0.25)');
    ctx.lineWidth = 1;
    ctx.stroke();

    // Line from center
    ctx.beginPath();
    ctx.moveTo(C(), C());
    ctx.lineTo(p.x, p.y);
    ctx.strokeStyle = col.replace(/[\d.]+\)$/, '0.1)');
    ctx.lineWidth = 0.5;
    ctx.setLineDash([3, 5]);
    ctx.stroke();
    ctx.setLineDash([]);

    ctx.restore();
  });
}

// ── Full frame ─────────────────────────────────────────────────────────────
function drawFrame() {
  drawStatic();
  drawSweep();
  drawObjects();
}

// ── Panel updates ──────────────────────────────────────────────────────────
function updatePanel(data) {
  const dist = data.points[sweepAngle] || 0;

  document.getElementById('stat-angle').textContent = sweepAngle;
  document.getElementById('stat-dist').textContent  = dist > 0 ? dist : '---';
  document.getElementById('stat-dir').textContent   =
    data.direction === 1 ? '▶  0° → 180°' : '◀  180° → 0°';

  // Signal bars
  const near  = points.filter((d, i) => Math.abs(i - sweepAngle) <= 20 && d > 0).length;
  const level = Math.min(near, 12);
  for (let i = 0; i < 12; i++) {
    const bar = document.getElementById('bar' + i);
    bar.style.height = (10 + i * 6) + 'px';
    bar.classList.toggle('active', i < level);
  }

  // Targets
  const targets = points
    .map((d, a) => ({ angle: a, dist: d }))
    .filter(t => t.dist > 0)
    .sort((a, b) => a.dist - b.dist)
    .slice(0, 14);

  document.getElementById('targets-val').textContent = targets.length;

  // Threat level
  const closest = targets.length ? targets[0].dist : 0;
  let threatPct = 0, threatColor = 'var(--green)', threatText = 'NO THREAT';
  if (closest > 0) {
    if (closest < CLOSE_THR) {
      threatPct = 85 + (CLOSE_THR - closest) / CLOSE_THR * 15;
      threatColor = 'var(--red)';
      threatText  = '⚠ CRITICAL — OBJECT CLOSE';
    } else if (closest < MID_THR) {
      threatPct = 40 + (MID_THR - closest) / MID_THR * 45;
      threatColor = 'var(--amber)';
      threatText  = '△ MEDIUM THREAT DETECTED';
    } else {
      threatPct = 5 + (MAX_DIST - closest) / MAX_DIST * 35;
      threatColor = 'var(--green)';
      threatText  = '✔ LOW — AREA CLEAR';
    }
  }
  const fill = document.getElementById('threat-fill');
  fill.style.width      = Math.min(100, threatPct) + '%';
  fill.style.background = threatColor;
  fill.style.boxShadow  = `0 0 8px ${threatColor}`;
  const tt = document.getElementById('threat-text');
  tt.textContent   = threatText;
  tt.style.color   = threatColor;
  tt.style.opacity = targets.length ? '0.9' : '0.4';

  const list = document.getElementById('targets-list');
  if (targets.length === 0) {
    list.innerHTML = '<div style="padding:24px 16px;opacity:.25;font-size:11px;font-family:var(--font-body);letter-spacing:1px;">NO TARGETS DETECTED</div>';
    return;
  }
  list.innerHTML = targets.map(t => {
    const cls    = t.dist < CLOSE_THR ? 'close' : t.dist < MID_THR ? 'mid' : '';
    const label  = t.dist < CLOSE_THR ? '<span style="color:var(--red)">CLOSE</span>'
                 : t.dist < MID_THR   ? '<span style="color:var(--amber)">MID</span>'
                 :                      '<span style="color:var(--green);opacity:.5">FAR</span>';
    return `<div class="target-item">
      <div class="target-icon ${cls}"></div>
      <span class="target-angle">${t.angle}°</span>
      <span class="target-status">${label}</span>
      <span class="target-dist">${t.dist}<span style="font-size:9px;opacity:.4">cm</span></span>
    </div>`;
  }).join('');
}

// ── Clock ──────────────────────────────────────────────────────────────────
function updateClock() {
  document.getElementById('ts-label').textContent = new Date().toTimeString().slice(0, 8);
}
setInterval(updateClock, 1000);
updateClock();

// ── FPS ────────────────────────────────────────────────────────────────────
function updateFPS() {
  frameCount++;
  const now = performance.now();
  if (now - fpsT > 1000) {
    document.getElementById('fps-val').textContent =
      Math.round(frameCount * 1000 / (now - fpsT));
    frameCount = 0; fpsT = now;
  }
}

// ── Connection ─────────────────────────────────────────────────────────────
function setConnected(ok) {
  connected = ok;
  const dot   = document.getElementById('conn-dot');
  const label = document.getElementById('conn-label');
  const warn  = document.getElementById('conn-warn');
  if (ok) {
    dot.className    = 'dot';
    label.textContent = 'LIVE';
    warn.classList.remove('visible');
  } else {
    dot.className    = 'dot red';
    label.textContent = 'OFFLINE';
    warn.classList.add('visible');
  }
}

// ── Polling ────────────────────────────────────────────────────────────────
async function poll() {
  try {
    const res  = await fetch('radar_api.php?_=' + Date.now());
    const data = await res.json();

    if (data.error) {
      setConnected(false);
    } else {
      setConnected(true);
      if (data.ts !== lastTs) {
        lastTs = data.ts;
        points = data.points;
        sweepDir   = data.direction;
        sweepAngle = sweepDir === 1
          ? Math.max(...Object.keys(data.points).map(Number).filter(a => data.points[a] > 0), sweepAngle)
          : Math.min(...Object.keys(data.points).map(Number).filter(a => data.points[a] > 0), sweepAngle);
        sweepAngle = Math.max(0, Math.min(180, sweepAngle));
      }
      drawFrame();
      updatePanel(data);
      maybePing(data.points);
      updateFPS();
    }
  } catch (e) { setConnected(false); }
  setTimeout(poll, POLL_MS);
}

// ── Audio ping ─────────────────────────────────────────────────────────────
const audioCtx   = new (window.AudioContext || window.webkitAudioContext)();
let   lastPingTs = 0;

function playPing(dist) {
  const osc  = audioCtx.createOscillator();
  const gain = audioCtx.createGain();
  osc.connect(gain); gain.connect(audioCtx.destination);
  osc.frequency.value = dist < 80 ? 880 : dist < 200 ? 440 : 260;
  osc.type = 'sine';
  gain.gain.setValueAtTime(0.35, audioCtx.currentTime);
  gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.45);
  osc.start(audioCtx.currentTime);
  osc.stop(audioCtx.currentTime + 0.45);
}

function maybePing(pts) {
  const now = Date.now();
  if (now - lastPingTs < 600) return;
  const valid = pts.filter(d => d > 0 && d < MAX_DIST);
  if (valid.length) { playPing(Math.min(...valid)); lastPingTs = now; }
}

// ── Boot ───────────────────────────────────────────────────────────────────
drawStatic();
poll();
</script>

</body>
</html>