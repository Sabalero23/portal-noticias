<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Responder con error si no es POST
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar que tenemos el ID del anuncio
if (!isset($_POST['ad_id']) || empty($_POST['ad_id'])) {
    // Responder con error si no hay ID
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de anuncio no proporcionado']);
    exit;
}

// Sanitizar entrada
$adId = (int)$_POST['ad_id'];

// Verificar que es un ID válido
if ($adId <= 0) {
    // Responder con error si el ID no es válido
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de anuncio inválido']);
    exit;
}

// Registrar el clic
$db = Database::getInstance();
$updated = $db->query(
    "UPDATE ads SET clicks = clicks + 1 WHERE id = ?",
    [$adId]
);

// Verificar si se actualizó correctamente
$success = $updated !== false;

// Registrar datos adicionales del clic en un log (opcional)
if ($success) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Opcionalmente, puedes guardar estos datos en una tabla de log
    $db->query(
        "INSERT INTO ad_clicks (ad_id, ip_address, user_agent, referer, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [$adId, $ipAddress, $userAgent, $referer]
    );
}

// Responder como JSON
header('Content-Type: application/json');
echo json_encode(['success' => $success]);