#include <Wire.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>

// Definimos nuestras credenciales de la red WiFi
const char* ssid = "Webzignet";
const char* pass = "webzignet";

// URL de la API
const char* api_url = "http://esp32.webzignet.com/api.php/?question=";

// Configuración del sensor DHT11
#define DHTPIN 23
#define DHTTYPE DHT11
DHT dht(DHTPIN, DHTTYPE);

// Configuración del KY-038
const int micPin = 34; // Salida analógica del KY-038

void setup() {
  // Iniciamos el terminal Serial para depuración
  Serial.begin(115200);

  // Iniciamos el sensor DHT11
  dht.begin();

  // Configuramos el KY-038
  pinMode(micPin, INPUT);

  // Iniciamos la conexión a la red WiFi
  WiFi.begin(ssid, pass);
  delay(2000);
  Serial.print("Conectando a ");
  Serial.println(ssid);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("");
  Serial.println("WiFi conectado");
  Serial.println("Dirección IP: ");
  Serial.println(WiFi.localIP());

  Serial.println("Listo para preguntas. Escribe tu pregunta y presiona Enter.");
}

void loop() {
  // Verificar si hay datos disponibles en el puerto serie
  if (Serial.available()) {
    String question = Serial.readStringUntil('\n'); // Leer la pregunta hasta el salto de línea
    question.trim(); // Eliminar espacios en blanco al principio y al final

    if (question.length() > 0) {
      Serial.print("Pregunta recibida: ");
      Serial.println(question);

      // Realizamos una solicitud a la API
      HTTPClient http;
      String encodedQuestion = String(urlEncode(question)); // Codifica la pregunta
      String url = String(api_url) + encodedQuestion;
      http.begin(url);
      int httpCode = http.GET();

      if (httpCode > 0) {
        String payload = http.getString();
        Serial.println(); // Línea en blanco para separar la pregunta de la respuesta
        Serial.println("Respuesta de la API:");
        Serial.println(payload); // Mostrar respuesta en la consola
      } else {
        Serial.println(); // Línea en blanco para separar la pregunta del error
        Serial.println("Error en la solicitud");
      }

      http.end();
    }
  }

  delay(5000); // Espera antes de la siguiente lectura
}

String urlEncode(String str) {
  String encoded = "";
  char c;
  for (int i = 0; i < str.length(); i++) {
    c = str.charAt(i);
    if (c == ' ') {
      encoded += '+';
    } else if ((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9')) {
      encoded += c;
    } else {
      encoded += '%';
      encoded += String(c >> 4, HEX);
      encoded += String(c & 0x0F, HEX);
    }
  }
  return encoded;
}
