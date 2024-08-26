<?php
// Configuración de la base de datos
$servername = "hosname";
$username = "usuario";
$password = "contraseña";
$dbname = "nombre base de datos";

// Crear conexión con la base de datos
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Establecer la codificación de caracteres a UTF-8
$conn->set_charset("utf8mb4");

// Función para obtener la IP del usuario
function obtener_ip_usuario() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// Función para registrar errores en la base de datos
function registrar_error($conn, $mensaje, $tipo = 'error') {
    $sql = "INSERT INTO registro (mensaje, tipo) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $mensaje, $tipo);
    $stmt->execute();
    $stmt->close();
}

// Función para obtener la ubicación utilizando la IP
function obtener_ubicacion($conn, $ip) {
    $url = "https://ipinfo.io/{$ip}/geo";
    $respuesta = @file_get_contents($url);
    
    if ($respuesta === FALSE) {
        registrar_error($conn, "Error al obtener ubicación para IP: $ip");
        return null; // Manejar error de la solicitud
    }
    
    $data = json_decode($respuesta, true);
    
    // Verificar el formato de los datos recibidos
    if (isset($data['loc'])) {
        list($lat, $lon) = explode(',', $data['loc']);
        return ['lat' => $lat, 'lon' => $lon];
    } else {
        registrar_error($conn, "Datos de ubicación inválidos: " . print_r($data, true));
        return null; // Datos de ubicación no válidos
    }
}

// Función para obtener coordenadas de un lugar usando la API de OpenStreetMap (Nominatim)
function obtener_coordenadas($lugar) {
    $url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($lugar) . "&format=json&addressdetails=1";
    $respuesta = @file_get_contents($url);

    if ($respuesta === FALSE) {
        return null; // Manejar error de la solicitud
    }

    $data = json_decode($respuesta, true);

    if (isset($data[0]['lat']) && isset($data[0]['lon'])) {
        return ['lat' => $data[0]['lat'], 'lon' => $data[0]['lon']];
    } else {
        return null; // Datos de ubicación no válidos
    }
}

// Función para obtener el clima usando la API de Open-Meteo
function obtener_clima($lat, $lon) {
    $url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current_weather=true";

    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $respuesta = curl_exec($ch);

    // Verificar si ocurrió un error en la solicitud cURL
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Error al obtener datos del clima: " . $error_msg;
    }

    curl_close($ch);

    // Verificar si la respuesta está vacía
    if (empty($respuesta)) {
        return "No se pudo obtener la información del clima. Respuesta vacía.";
    }

    // Decodificar la respuesta JSON
    $data = json_decode($respuesta, true);

    // Verificar si la respuesta contiene datos relevantes
    if (isset($data['current_weather']['temperature'])) {
        $temp_celsius = $data['current_weather']['temperature'];
        $temp_fahrenheit = ($temp_celsius * 9/5) + 32;
        return "Clima: " . round($temp_celsius, 1) . "°C, " . round($temp_fahrenheit, 2) . "°F";
    } else {
        return "No se pudo obtener la información del clima. Respuesta: " . print_r($data, true);
    }
}

// Función para realizar una consulta a Wikipedia
function searchWikipedia($conn, $query) {
    $url = 'https://es.wikipedia.org/w/api.php?' .
           'action=query' .
           '&format=json' .
           '&list=search' .
           '&srsearch=' . urlencode($query) .
           '&utf8=1';
    
    $response = @file_get_contents($url);
    
    if ($response === FALSE) {
        registrar_error($conn, "Error al obtener datos de Wikipedia para la consulta: $query");
        return "Error al obtener datos de Wikipedia.";
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['query']['search'][0])) {
        $title = htmlspecialchars($data['query']['search'][0]['title']); // Sanitizar
        $snippet = strip_tags($data['query']['search'][0]['snippet']); // Quitar etiquetas HTML
        return "Título: $title\nDescripción: $snippet";
    } else {
        registrar_error($conn, "No se encontró información para la consulta: $query");
        return "No se encontró información.";
    }
}

// Obtén la pregunta desde el parámetro GET
$query = isset($_GET['question']) ? trim($_GET['question']) : '';

// Verifica si la pregunta ya existe en la base de datos
$sql = "SELECT respuesta FROM respuestas WHERE pregunta = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $query);
$stmt->execute();
$stmt->bind_result($respuesta);
$stmt->fetch();
$stmt->close();

if ($respuesta) {
    // Si la pregunta existe en la base de datos, devuélvela
    $response = $respuesta;
} else {
    // Verificar si la pregunta es sobre el clima
    if (preg_match('/clima|tiempo|temperatura/i', $query)) {
        // Verificar si la pregunta incluye un lugar específico
        if (preg_match('/clima\s+de\s+(.+)/i', $query, $matches)) {
            $lugar = $matches[1];
            $coordenadas = obtener_coordenadas($lugar);
            
            if ($coordenadas && isset($coordenadas['lat'], $coordenadas['lon'])) {
                // Obtener el clima basado en la ubicación especificada
                $response = obtener_clima($coordenadas['lat'], $coordenadas['lon']);
            } else {
                $response = "No se pudo obtener la ubicación para el lugar especificado.";
            }
        } else {
            // Obtener la IP del usuario
            $ip = obtener_ip_usuario();
            // Obtener la ubicación a partir de la IP
            $ubicacion = obtener_ubicacion($conn, $ip);
            
            if ($ubicacion && isset($ubicacion['lat'], $ubicacion['lon'])) {
                // Obtener el clima basado en la ubicación del usuario
                $response = obtener_clima($ubicacion['lat'], $ubicacion['lon']);
            } else {
                $response = "No se pudo determinar la ubicación del usuario.";
            }
        }
    } else {
        // Si no es una pregunta sobre el clima, busca en Wikipedia y guarda la respuesta en la base de datos
        $response = searchWikipedia($conn, $query);

        // Inserta la nueva pregunta y respuesta en la base de datos
        $sql = "INSERT INTO respuestas (pregunta, respuesta) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $query, $response);
        $stmt->execute();
        $stmt->close();
    }
}

// Devuelve la respuesta en formato de texto plano
header('Content-Type: text/plain');
echo $response;

// Cierra la conexión con la base de datos
$conn->close();
?>
