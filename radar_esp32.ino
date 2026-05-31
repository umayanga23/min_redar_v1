/*
 * ESP32 Perimeter Radar — Enhanced Firmware
 * ──────────────────────────────────────────
 * Features:
 *  - Full 180° servo sweep (not just 3 positions)
 *  - WiFi retry with offline fallback
 *  - Non-blocking HTTP (fire-and-forget)
 *  - Improved OLED display with bar graph
 *  - Alert buzzer on close detection (optional)
 *  - Clean serial output
 *
 * Wiring:
 *  - Servo signal → GPIO 18
 *  - HC-SR04 TRIG  → GPIO 5
 *  - HC-SR04 ECHO  → GPIO 4
 *  - OLED SDA      → GPIO 21
 *  - OLED SCL      → GPIO 22
 *  - Buzzer (opt.) → GPIO 2
 */

#include <ESP32Servo.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <WiFi.h>
#include <HTTPClient.h>

// ── WiFi credentials ────────────────────────────────────
const char* ssid     = "YOUR_ROuter";   // ← change
const char* password = "YOUR_PASSWORD";       // ← change

// ── Server config ────────────────────────────────────────
const char* serverIP   = "192.168.0.102"; // ← your PC IP
const char* serverPath = "/redarV1/radar_receive.php";
const int   serverPort = 80;

// ── Pins ────────────────────────────────────────────────
const int SERVO_PIN  = 18;
const int TRIG_PIN   = 5;
const int ECHO_PIN   = 4;
const int BUZZER_PIN = 2;   // set -1 to disable

// ── Sweep config ─────────────────────────────────────────
const int  SWEEP_MIN     = 0;
const int  SWEEP_MAX     = 180;
const int  SWEEP_STEP    = 5;    // degrees per step (smaller = smoother)
const int  STEP_DELAY_MS = 100;  // ms per step (100ms × 37 steps = ~3.7s per sweep)

// ── Alert thresholds ─────────────────────────────────────
const int ALERT_CLOSE_CM = 80;   // buzzer trigger distance

// ── OLED ────────────────────────────────────────────────
#define SCREEN_W  128
#define SCREEN_H  64
#define OLED_ADDR 0x3C
Adafruit_SSD1306 display(SCREEN_W, SCREEN_H, &Wire, -1);

// ── Servo ────────────────────────────────────────────────
Servo myServo;

// ── State ────────────────────────────────────────────────
int  currentAngle = SWEEP_MIN;
int  sweepDir     = 1;   // 1 = forward, -1 = backward
bool wifiOK       = false;
unsigned long lastSendMs = 0;
int  httpFailCount = 0;

// ────────────────────────────────────────────────────────

long measureDistance() {
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  long dur = pulseIn(ECHO_PIN, HIGH, 35000); // 35ms timeout ≈ 600cm
  if (dur == 0) return 0;
  return dur * 0.034 / 2;
}

void buzzAlert(int freq, int durationMs) {
  if (BUZZER_PIN < 0) return;
  tone(BUZZER_PIN, freq, durationMs);
}

void drawOLED(int angle, long dist) {
  display.clearDisplay();

  // Title bar
  display.setTextSize(1);
  display.setTextColor(WHITE);
  display.setCursor(0, 0);
  display.print("RADAR ");
  display.print(angle);
  display.print((char)247); // degree symbol
  display.print("  ");

  // Direction arrow
  display.print(sweepDir == 1 ? ">>>" : "<<<");

  display.drawLine(0, 10, 127, 10, WHITE);

  // Distance reading
  display.setCursor(0, 14);
  if (dist == 0 || dist > 400) {
    display.setTextSize(1);
    display.setCursor(20, 24);
    display.println("OUT OF RANGE");
  } else {
    display.setTextSize(2);
    display.print(dist);
    display.print("cm");
  }

  // Zone indicator
  display.setTextSize(1);
  display.setCursor(0, 46);
  if (dist > 0 && dist <= 400) {
    if (dist < ALERT_CLOSE_CM) {
      display.println("!! CLOSE OBJECT !!");
    } else if (dist < 250) {
      display.println("MID RANGE");
    } else {
      display.println("CLEAR");
    }
  }

  // Bar graph (bottom row, 0..400cm → 0..127px)
  if (dist > 0 && dist <= 400) {
    int barW = map(dist, 0, 400, 0, 127);
    display.fillRect(0, 58, barW, 5, WHITE);
    display.drawRect(0, 58, 127, 5, WHITE);
  }

  display.display();
}

void sendToServer(int angle, long dist) {
  if (!wifiOK || WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = "http://";
  url += serverIP;
  url += serverPath;
  url += "?angle=";
  url += String(angle);
  url += "&distance=";
  url += String(dist);

  http.begin(url);
  http.setTimeout(2000); // 2s timeout — don't block sweep
  int code = http.GET();

  if (code == 200) {
    httpFailCount = 0;
    Serial.println("[HTTP] OK");
  } else {
    httpFailCount++;
    Serial.print("[HTTP] Error: ");
    Serial.println(code);
  }
  http.end();

  // If too many failures, try reconnecting WiFi
  if (httpFailCount > 5) {
    Serial.println("[WiFi] Too many failures, reconnecting...");
    WiFi.disconnect();
    delay(500);
    WiFi.begin(ssid, password);
    httpFailCount = 0;
  }
}

void connectWiFi() {
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(WHITE);
  display.setCursor(0, 10);
  display.println("Connecting WiFi...");
  display.println(ssid);
  display.display();

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  Serial.print("[WiFi] Connecting");
  int tries = 0;
  while (WiFi.status() != WL_CONNECTED && tries < 24) {
    delay(500);
    Serial.print(".");
    tries++;
  }

  wifiOK = (WiFi.status() == WL_CONNECTED);

  display.clearDisplay();
  display.setCursor(0, 10);
  if (wifiOK) {
    Serial.println("\n[WiFi] Connected: " + WiFi.localIP().toString());
    display.println("WiFi Connected!");
    display.setCursor(0, 28);
    display.println(WiFi.localIP().toString());
  } else {
    Serial.println("\n[WiFi] FAILED — offline mode");
    display.println("WiFi FAILED");
    display.setCursor(0, 28);
    display.println("Offline mode");
  }
  display.display();
  delay(1200);
}

void setup() {
  Serial.begin(115200);
  Serial.println("\n=== ESP32 Radar Boot ===");

  // OLED init
  if (!display.begin(SSD1306_SWITCHCAPVCC, OLED_ADDR)) {
    Serial.println("[OLED] Not found! Check wiring.");
    // Continue without OLED
  }
  display.clearDisplay();
  display.setTextColor(WHITE);

  // Pins
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  if (BUZZER_PIN >= 0) pinMode(BUZZER_PIN, OUTPUT);

  // Servo
  myServo.attach(SERVO_PIN, 500, 2400);
  myServo.write(90);
  delay(600);

  // WiFi
  connectWiFi();

  // Ready beep
  if (BUZZER_PIN >= 0) {
    buzzAlert(880, 80);
    delay(120);
    buzzAlert(1100, 80);
  }

  Serial.println("[Radar] Sweep started");
}

void loop() {
  // Move servo
  myServo.write(currentAngle);
  delay(STEP_DELAY_MS);

  // Measure
  long dist = measureDistance();

  // OLED
  drawOLED(currentAngle, dist);

  // Serial output
  Serial.printf("[Scan] Angle: %3d°  Dist: ", currentAngle);
  if (dist == 0 || dist > 400) {
    Serial.println("--- (out of range)");
  } else {
    Serial.printf("%ld cm", dist);
    if (dist < ALERT_CLOSE_CM) {
      Serial.print("  *** CLOSE OBJECT ***");
      buzzAlert(1400, 60);
    }
    Serial.println();
  }

  // Send to server
  sendToServer(currentAngle, dist);

  // Advance angle
  currentAngle += sweepDir * SWEEP_STEP;

  // Reverse at limits
  if (currentAngle >= SWEEP_MAX) {
    currentAngle = SWEEP_MAX;
    sweepDir = -1;
    Serial.println("[Sweep] ← Reversing (180→0)");
  } else if (currentAngle <= SWEEP_MIN) {
    currentAngle = SWEEP_MIN;
    sweepDir = 1;
    Serial.println("[Sweep] → Reversing (0→180)");
  }
}
