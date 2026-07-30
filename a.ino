#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>

#include <SPI.h>
#include <MFRC522.h>
#include <Adafruit_NeoPixel.h>
#include <Servo.h>

// -------------------- WiFi --------------------
const char* SSID     = "HONOR Magic6 Lite 5G";
const char* PASSWORD = "9faf5daa57b9";

// Endpoint que recibe el UID y registra intento en BD
const char* SERVER_URL      = "http://3.214.181.94/insertar_intento_directo.php";
// Endpoint que devuelve el último intento (10 segundos) id|resultado|dispositivo
const char* SERVER_LAST_URL = "http://3.214.181.94/ultimo_intento_app.php";

// -------------------- Pines NodeMCU --------------------
// RC522
#define RST_PIN   D1
#define SS_PIN    D2

// NeoPixel
#define PIXEL_PIN D4
#define NUM_PIXELS 12

// Servo
#define SERVO_PIN D0

// -------------------- Objetos globales --------------------
MFRC522 rfid(SS_PIN, RST_PIN);
Adafruit_NeoPixel pixels(NUM_PIXELS, PIXEL_PIN, NEO_GRB + NEO_KHZ800);
Servo gate;

// Servo
const int OPEN_ANGLE  = 90;
const int CLOSE_ANGLE = 0;
const unsigned long HOLD_MS = 10000;

// Polling para último intento
unsigned long lastPollMs       = 0;
const unsigned long POLL_EVERY = 1000;  // cada 1 segundo
long lastSeenIntentId          = 0;     // último id procesado

// -------------------- Helpers visuales --------------------
void ringSolid(uint8_t r, uint8_t g, uint8_t b, uint8_t brightness = 40) {
  pixels.setBrightness(brightness);
  for (int i = 0; i < NUM_PIXELS; i++) {
    pixels.setPixelColor(i, pixels.Color(r, g, b));
  }
  pixels.show();
}

String uidToHex(const MFRC522::Uid &uid) {
  String s = "";
  for (byte i = 0; i < uid.size; i++) {
    if (uid.uidByte[i] < 0x10) s += "0";
    s += String(uid.uidByte[i], HEX);
  }
  s.toUpperCase();
  return s;
}

void openGateThenClose() {
  gate.write(OPEN_ANGLE);
  delay(HOLD_MS);
  gate.write(CLOSE_ANGLE);
}

// -------------------- Enviar intento RFID al backend --------------------
// NO mueve el servo, solo envía el UID para que el PHP registre PERMITIDO/DENEGADO
void enviarIntentoRFID(const String& uidHex) {
  Serial.println();
  Serial.println("📡 Enviando intento RFID al backend...");
  Serial.println("---------------------------");

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("❌ WiFi NO conectado, no se envía intento.");
    return;
  }

  WiFiClient client;
  HTTPClient http;

  if (!http.begin(client, SERVER_URL)) {
    Serial.println("❌ Error http.begin() en SERVER_URL");
    return;
  }

  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  // Puedes cambiar lector_id si quieres identificar la puerta
  String postData = "uid_tag=" + uidHex + "&lector_id=ESP8266_RFID";

  Serial.print("➡️ POST: ");
  Serial.println(postData);

  int httpCode = http.POST(postData);

  if (httpCode <= 0) {
    Serial.print("❌ Error HTTP POST: ");
    Serial.println(httpCode);
    http.end();
    return;
  }

  Serial.print("📥 Código HTTP insertar_intento_directo.php: ");
  Serial.println(httpCode);

  String payload = http.getString();
  Serial.println("📨 Respuesta insertar_intento_directo.php:");
  Serial.println(payload);

  http.end();

  Serial.println("✅ Intento RFID enviado, el resultado se tomará desde ultimo_intento_app.php");
}

// -------------------- Consultar último intento (10 segundos) --------------------
void checkUltimoIntento() {
  // Polling cada 1 segundo
  unsigned long now = millis();
  if (now - lastPollMs < POLL_EVERY) {
    return;
  }
  lastPollMs = now;

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠️ Sin WiFi, no se puede consultar último intento.");
    return;
  }

  WiFiClient client;
  HTTPClient http;

  Serial.println();
  Serial.println("📡 Consultando último intento (<=10s)...");

  if (!http.begin(client, SERVER_LAST_URL)) {
    Serial.println("❌ Error http.begin() en SERVER_LAST_URL");
    return;
  }

  int httpCode = http.GET();
  Serial.print("📥 Código HTTP ultimo_intento_app.php: ");
  Serial.println(httpCode);

  if (httpCode <= 0) {
    Serial.print("❌ Error HTTP GET: ");
    Serial.println(httpCode);
    http.end();
    return;
  }

  String payload = http.getString();
  http.end();

  payload.trim();

  Serial.print("📨 Respuesta ultimo_intento_app.php: ");
  Serial.println(payload);

  if (payload.length() == 0) {
    Serial.println("⚠️ Respuesta vacía desde ultimo_intento_app.php");
    return;
  }

  // Formato esperado: id|resultado|dispositivo
  int p1 = payload.indexOf('|');
  int p2 = payload.indexOf('|', p1 + 1);

  if (p1 == -1 || p2 == -1) {
    Serial.println("⚠️ Formato inesperado en respuesta.");
    return;
  }

  long id = payload.substring(0, p1).toInt();
  String resultado   = payload.substring(p1 + 1, p2);
  String dispositivo = payload.substring(p2 + 1);

  resultado.trim();
  dispositivo.trim();

  Serial.print("➡️ Último id: ");
  Serial.print(id);
  Serial.print(" | resultado: ");
  Serial.print(resultado);
  Serial.print(" | dispositivo: ");
  Serial.println(dispositivo);

  // Si no hay nuevos intentos, no hacemos nada
  if (id <= lastSeenIntentId) {
    Serial.println("ℹ️ Sin nuevo intento a procesar.");
    return;
  }

  // Marcamos como procesado
  lastSeenIntentId = id;

  // Solo nos interesan PERMITIDO o DENEGADO
  if (resultado == "PERMITIDO") {
    Serial.println("✅ Nuevo intento PERMITIDO (tarjeta/llavero/app).");
    ringSolid(0, 255, 0); // verde
    openGateThenClose();
    ringSolid(0, 0, 0);
  } else if (resultado == "DENEGADO") {
    Serial.println("⛔ Nuevo intento DENEGADO (tarjeta/llavero/app).");
    ringSolid(255, 0, 0); // rojo
    delay(1500);
    ringSolid(0, 0, 0);
  } else {
    Serial.println("ℹ️ Resultado distinto de PERMITIDO/DENEGADO, sin acción.");
  }
}

// -------------------- SETUP --------------------
void setup() {
  Serial.begin(115200);
  delay(100);

  Serial.println();
  Serial.println("===== SISTEMA DE ACCESO ESP8266 =====");
  Serial.println("Inicializando...");

  // NeoPixel
  pixels.begin();
  ringSolid(0, 0, 0);

  // Servo
  gate.attach(SERVO_PIN);
  gate.write(CLOSE_ANGLE);

  // RFID
  SPI.begin();
  pinMode(SS_PIN, OUTPUT);
  digitalWrite(SS_PIN, HIGH);
  rfid.PCD_Init();

  // WiFi
  Serial.print("Conectando a WiFi: ");
  Serial.println(SSID);

  ringSolid(0, 0, 255); // azul mientras conecta

  WiFi.begin(SSID, PASSWORD);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("✔️ WiFi conectado");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());

  ringSolid(0, 0, 0);

  Serial.println();
  Serial.println("👉 Esperando tarjeta/llavero y comandos desde APP...");
}

// -------------------- LOOP --------------------
void loop() {
  // 1) Siempre revisar el último intento en la BD (tarjeta / llavero / APP)
  checkUltimoIntento();

  // 2) Si se presenta una tarjeta/llavero, enviamos el intento al backend
  if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {

    String uidHex = uidToHex(rfid.uid);

    Serial.println();
    Serial.println("====================================");
    Serial.print("🎫 UID detectado: ");
    Serial.println(uidHex);
    Serial.println("====================================");

    ringSolid(0, 0, 255); // azul mientras enviamos

    enviarIntentoRFID(uidHex);

    ringSolid(0, 0, 0);

    Serial.println();
    Serial.println("👉 Intento registrado, esperando resultado en BD...");
    Serial.println("------------------------------------");

    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();

    delay(500); // evitar múltiples lecturas rápidas
  }

  delay(10); // pequeño respiro para no saturar el loop
}
