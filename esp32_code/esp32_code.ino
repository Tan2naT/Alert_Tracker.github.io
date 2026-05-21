#include <TinyGPS++.h>

// ---------------- GPS ----------------
TinyGPSPlus gps;
HardwareSerial gpsSerial(2);

#define GPS_RX 4
#define GPS_TX 5

// ---------------- A7670G ----------------
HardwareSerial simSerial(1);

#define SIM_RX 16
#define SIM_TX 17

// ---------------- Controls ----------------
#define BUTTON_PIN 27
#define READY_LED_PIN 18

const int BUTTON_PRESSED = LOW;

// ---------------- Upload Settings ----------------
const char* APN = "internet.globe.com.ph";
const char* BASE_URL =
  "http://pnkalrt.page.gd/TRACKER_SYSTEM/api/random_test_coordinate.php";

const char* API_KEY = "TAN";
const char* SOURCE = "ESP32";

// ---------------- States ----------------
bool simReady = false;
bool uploadInProgress = false;

bool lastButtonState = HIGH;

unsigned long lastButtonChange = 0;
unsigned long lastSimRetry = 0;

const unsigned long debounceDelay = 60;
const unsigned long simRetryInterval = 30000;

// =====================================================

String readSimResponse(unsigned long timeoutMs) {

  String response = "";

  unsigned long startTime = millis();

  while (millis() - startTime < timeoutMs) {

    while (simSerial.available()) {

      char c = simSerial.read();

      response += c;

      Serial.write(c);
    }

    delay(5);
  }

  return response;
}

// =====================================================

bool sendATCommand(
  const String& command,
  const char* expected,
  unsigned long timeoutMs) {

  while (simSerial.available()) {
    simSerial.read();
  }

  Serial.print(">> ");
  Serial.println(command);

  simSerial.println(command);

  String response = readSimResponse(timeoutMs);

  return response.indexOf(expected) >= 0;
}

// =====================================================

bool waitForNetwork() {

  for (int i = 0; i < 20; i++) {

    simSerial.println("AT+CREG?");

    String response = readSimResponse(2000);

    if (response.indexOf("+CREG: 0,1") >= 0 ||
        response.indexOf("+CREG: 0,5") >= 0) {

      return true;
    }

    delay(1000);
  }

  return false;
}

// =====================================================

bool setupSimData() {

  Serial.println("Initializing SIM...");

  if (!sendATCommand("AT", "OK", 2000)) {
    return false;
  }

  sendATCommand("ATE0", "OK", 1000);

  if (!sendATCommand("AT+CPIN?", "READY", 3000)) {
    return false;
  }

  sendATCommand("AT+CSQ", "OK", 2000);

  if (!waitForNetwork()) {

    Serial.println("Network registration failed.");

    return false;
  }

  sendATCommand("AT+CGATT=1", "OK", 5000);

  String apnCmd =
    "AT+CGDCONT=1,\"IP\",\"" + String(APN) + "\"";

  if (!sendATCommand(apnCmd, "OK", 3000)) {
    return false;
  }

  sendATCommand("AT+CGACT=1,1", "OK", 10000);

  // NETOPEN
  simSerial.println("AT+NETOPEN");

  String netResponse = readSimResponse(10000);

  if (!(netResponse.indexOf("OK") >= 0 ||
        netResponse.indexOf("already opened") >= 0)) {

    Serial.println("NETOPEN failed.");

    return false;
  }

  sendATCommand("AT+IPADDR", ".", 3000);

  Serial.println("SIM READY");

  return true;
}

// =====================================================

String buildUploadUrl(double lat, double lng) {

  String url = String(BASE_URL);

  url += "?key=" + String(API_KEY);

  url += "&source=" + String(SOURCE);

  url += "&latitude=" + String(lat, 6);

  url += "&longitude=" + String(lng, 6);

  return url;
}

// =====================================================

bool uploadCoordinates(double lat, double lng) {

  String url = buildUploadUrl(lat, lng);

  Serial.println();
  Serial.println("Uploading...");
  Serial.println(url);

  sendATCommand("AT+HTTPTERM", "OK", 1000);

  if (!sendATCommand("AT+HTTPINIT", "OK", 3000)) {
    return false;
  }

  String urlCmd =
    "AT+HTTPPARA=\"URL\",\"" + url + "\"";

  if (!sendATCommand(urlCmd, "OK", 5000)) {

    sendATCommand("AT+HTTPTERM", "OK", 1000);

    return false;
  }

  simSerial.println("AT+HTTPACTION=0");

  String response = readSimResponse(20000);

  Serial.println(response);

  bool success =
    response.indexOf(",200,") >= 0 ||
    response.indexOf(",201,") >= 0;

  sendATCommand("AT+HTTPTERM", "OK", 1000);

  return success;
}

// =====================================================

bool deviceReady() {

  return simReady &&
         gps.location.isValid() &&
         gps.satellites.value() >= 3;
}

// =====================================================

void setup() {

  Serial.begin(115200);

  pinMode(BUTTON_PIN, INPUT_PULLUP);

  pinMode(READY_LED_PIN, OUTPUT);

  digitalWrite(READY_LED_PIN, LOW);

  // GPS
  gpsSerial.begin(
    9600,
    SERIAL_8N1,
    GPS_RX,
    GPS_TX);

  // SIM
  simSerial.begin(
    115200,
    SERIAL_8N1,
    SIM_RX,
    SIM_TX);

  Serial.println("SYSTEM STARTING...");

  delay(5000);

  simReady = setupSimData();

  if (simReady) {
    Serial.println("SIM OK");
  } else {
    Serial.println("SIM FAILED");
  }

  Serial.println("Waiting for GPS lock...");
}

// =====================================================

void loop() {

  // ---------------- GPS ----------------

  while (gpsSerial.available()) {

    gps.encode(gpsSerial.read());
  }

  static unsigned long lastGpsPrint = 0;

  if (millis() - lastGpsPrint >= 3000) {

    lastGpsPrint = millis();

    if (gps.location.isValid()) {

      Serial.println("===== GPS =====");

      Serial.print("LAT: ");
      Serial.println(gps.location.lat(), 6);

      Serial.print("LNG: ");
      Serial.println(gps.location.lng(), 6);

      Serial.print("SAT: ");
      Serial.println(gps.satellites.value());

      Serial.println("================");
    }
  }

  // ---------------- SIM RETRY ----------------

  if (!simReady &&
      millis() - lastSimRetry >= simRetryInterval) {

    lastSimRetry = millis();

    simReady = setupSimData();
  }

  // ---------------- READY LED ----------------

  bool ready = deviceReady() &&
               !uploadInProgress;

  digitalWrite(READY_LED_PIN, ready);

  // ---------------- BUTTON ----------------

  bool buttonState = digitalRead(BUTTON_PIN);

  if (buttonState != lastButtonState) {

    lastButtonChange = millis();

    lastButtonState = buttonState;
  }

  if ((millis() - lastButtonChange) > debounceDelay &&
      buttonState == BUTTON_PRESSED &&
      ready) {

    uploadInProgress = true;

    bool uploaded =
      uploadCoordinates(
        gps.location.lat(),
        gps.location.lng());

    if (uploaded) {

      Serial.println("UPLOAD SUCCESS");

    } else {

      Serial.println("UPLOAD FAILED");
    }

    while (digitalRead(BUTTON_PIN) == BUTTON_PRESSED) {
      delay(10);
    }

    uploadInProgress = false;
  }
}