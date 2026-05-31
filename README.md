# min_redar_v1
Iot Base mini Radar version 



# 🛰️ ESP32 Perimeter Radar v1

A real-time 180° radar system built with **ESP32**, **HC-SR04 ultrasonic sensor**, **SG90 servo**, and **OLED display** — with live data streaming to a PHP backend dashboard.

![Radar Demo](assets/demo_video_thumbnail.jpg)

---

## ✨ Features

- Full 180° servo sweep with configurable step & speed
- Real-time distance measurement via HC-SR04
- OLED display with distance bar graph and zone alerts
- Buzzer alert on close object detection (< 80 cm)
- WiFi data streaming to PHP server (HTTP GET)
- Offline fallback mode if WiFi fails
- PHP backend stores last 30 readings as JSON
- REST API endpoint for dashboard polling
- CSV log download from server

---

## 🔧 Hardware

| Component       | Pin      |
|----------------|----------|
| Servo Signal    | GPIO 18  |
| HC-SR04 TRIG   | GPIO 5   |
| HC-SR04 ECHO   | GPIO 4   |
| OLED SDA       | GPIO 21  |
| OLED SCL       | GPIO 22  |
| Buzzer (opt.)  | GPIO 2   |

---

## 📦 Libraries Required

Install via Arduino Library Manager:
- `ESP32Servo`
- `Adafruit GFX Library`
- `Adafruit SSD1306`

---

## ⚙️ Setup

1. Open `firmware/esp32_radar.ino` in Arduino IDE
2. Update WiFi credentials:
```cpp
const char* ssid     = "YOUR_SSID";
const char* password = "YOUR_PASSWORD";
```
3. Set your server IP:
```cpp
const char* serverIP = "192.168.x.x";
```
4. Upload `server/radar_receive.php` and `server/radar_api.php` to your web server
5. Flash firmware to ESP32

---

## 🌐 API Endpoints

| Endpoint | Description |
|---|---|
| `?angle=90&distance=120` | Receive data from ESP32 |
| `?fetch` | Get last 30 readings as JSON |
| `?download_log` | Download full CSV log |

---

## 📹 Demo

▶️ [Watch the demo video](#) ← *add your YouTube/LinkedIn video link here*

---

## 🧠 Tech Stack

`ESP32` · `Arduino IDE` · `PHP` · `HC-SR04` · `OLED SSD1306` · `WiFi HTTP`

---

## 📄 License

MIT License — feel free to use and modify.

---

## 👤 Author

**Umayanga** — IoT & Embedded Systems Developer  
[GitHub](https://github.com/umayanga23) · [LinkedIn](#) ← add your link