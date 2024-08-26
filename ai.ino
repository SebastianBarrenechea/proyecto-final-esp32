#include <WiFi.h>
#include <HTTPClient.h>

// Definimos nuestras credenciales de la red WiFi
const char* ssid = "Webzignet"; // Reemplaza con el nombre de tu red WiFi
const char* pass = "webzignet"; // Reemplaza con tu contraseña de WiFi

// URL de la API
const char* api_url = "http://esp32.webzignet.com/api.php/?question=";

void setup() {
  // Iniciamos el terminal Serial para depuración
  Serial.begin(115200);

  // Iniciamos la conexión a la red WiFi
  WiFi.begin(ssid, pass);
  delay(2000); // Espera para permitir la conexión
  Serial.print("Se está conectando a la red WiFi denominada ");
  Serial.println(ssid);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("");
  Serial.println("WiFi conectado");
  Serial.println("Dirección IP: ");
  Serial.println(WiFi.localIP());

  Serial.println("Ingrese una pregunta para consultar a la API:");
}

void loop() {
  // Verificamos si hay datos disponibles en el Monitor Serial
  if (Serial.available() > 0) {
    String question = Serial.readStringUntil('\n'); // Lee la pregunta ingresada por el usuario
    question.trim(); // Elimina espacios en blanco al principio y final
    if (question.length() > 0) {
      String encodedQuestion = urlEncode(question); // Codifica la pregunta para la URL
      String url = String(api_url) + encodedQuestion;
      
      // Realizar una solicitud HTTP a la API
      HTTPClient http;
      http.begin(url);
      int httpCode = http.GET();

      if (httpCode > 0) {
        String payload = http.getString();
        Serial.println("Respuesta de la API:");
        Serial.println(payload);
      } else {
        Serial.println("Error en la solicitud a la API");
      }

      http.end();
    } else {
      Serial.println("La pregunta ingresada está vacía. Inténtelo de nuevo.");
    }

    Serial.println("\nIngrese otra pregunta para consultar a la API:");
  }

  delay(100); // Pequeña espera antes de la siguiente iteración del loop
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
