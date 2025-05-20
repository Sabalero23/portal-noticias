<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Establecer cabeceras para JSON
header('Content-Type: application/json');

// Obtener configuración de la API del clima
$apiKey = getSetting('weather_api_key', '');
$defaultCity = getSetting('weather_api_city', 'Reconquista');
$units = getSetting('weather_api_units', 'metric');

// Verificar si hay API key configurada
if (empty($apiKey) || strpos($apiKey, '_here') !== false) {
    echo json_encode(['error' => 'API del clima no configurada correctamente']);
    exit;
}

// Verificar si se proporcionaron coordenadas
if (isset($_GET['lat']) && isset($_GET['lon'])) {
    $lat = filter_var($_GET['lat'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $lon = filter_var($_GET['lon'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    $apiUrl = "https://api.openweathermap.org/data/2.5/weather?lat=$lat&lon=$lon&units=$units&lang=es&appid=$apiKey";
} else {
    // Si no hay coordenadas, usar la ciudad predeterminada
    $city = urlencode($defaultCity);
    $apiUrl = "https://api.openweathermap.org/data/2.5/weather?q=$city&units=$units&lang=es&appid=$apiKey";
}

// Realizar la petición a la API de OpenWeatherMap
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Verificar si la petición fue exitosa
if ($statusCode !== 200 || !$response) {
    echo json_encode(['error' => 'Error al obtener datos del clima']);
    exit;
}

// Devolver los datos del clima
echo $response;
?>