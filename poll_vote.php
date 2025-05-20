<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirigir a la página principal si no es POST
    redirect('index.php');
}

// Respuesta predeterminada para JSON
$response = [
    'success' => false,
    'message' => 'Error desconocido',
    'poll_id' => 0,
    'options' => [],
    'total_votes' => 0
];

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $response['message'] = 'Token de seguridad inválido';
    echo json_encode($response);
    exit;
}

// Verificar datos de la encuesta
if (!isset($_POST['poll_id']) || !isset($_POST['option_id'])) {
    $response['message'] = 'Datos incompletos';
    echo json_encode($response);
    exit;
}

// Sanitizar entradas
$pollId = (int)$_POST['poll_id'];
$optionId = (int)$_POST['option_id'];

// Verificar si son números válidos
if ($pollId <= 0 || $optionId <= 0) {
    $response['message'] = 'ID de encuesta u opción inválidos';
    echo json_encode($response);
    exit;
}

// Verificar si ya votó (usando cookies)
if (isset($_COOKIE['poll_' . $pollId])) {
    $response['message'] = 'Ya has votado en esta encuesta';
    
    // Obtener resultados actuales para mostrar
    showResults($pollId, $response);
    
    echo json_encode($response);
    exit;
}

// Obtener IP y User Agent
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Verificar si ya votó (usando IP)
$db = Database::getInstance();
$existingVote = $db->fetch(
    "SELECT id FROM poll_votes WHERE poll_id = ? AND ip_address = ?",
    [$pollId, $ipAddress]
);

if ($existingVote) {
    $response['message'] = 'Ya has votado en esta encuesta';
    
    // Obtener resultados actuales para mostrar
    showResults($pollId, $response);
    
    echo json_encode($response);
    exit;
}

// Verificar si la encuesta existe y está activa
$poll = $db->fetch(
    "SELECT id, question, status FROM polls WHERE id = ? AND status = 'active'",
    [$pollId]
);

if (!$poll) {
    $response['message'] = 'Encuesta no encontrada o inactiva';
    echo json_encode($response);
    exit;
}

// Verificar si la opción existe y pertenece a la encuesta
$option = $db->fetch(
    "SELECT id, option_text FROM poll_options WHERE id = ? AND poll_id = ?",
    [$optionId, $pollId]
);

if (!$option) {
    $response['message'] = 'Opción no encontrada o no pertenece a esta encuesta';
    echo json_encode($response);
    exit;
}

// Iniciar transacción
$transaction = new Transaction();
$transaction->begin();

try {
    // Registrar el voto
    $db->query(
        "INSERT INTO poll_votes (poll_id, option_id, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [$pollId, $optionId, $ipAddress, $userAgent]
    );
    
    // Incrementar el contador de votos
    $db->query(
        "UPDATE poll_options SET votes = votes + 1 WHERE id = ?",
        [$optionId]
    );
    
    // Confirmar transacción
    $transaction->commit();
    
    // Establecer cookie para evitar votos múltiples (30 días)
    setcookie('poll_' . $pollId, 'voted', time() + (86400 * 30), '/');
    
    // Éxito
    $response['success'] = true;
    $response['message'] = 'Voto registrado correctamente';
    $response['poll_id'] = $pollId;
    
    // Obtener y devolver resultados actualizados
    showResults($pollId, $response);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $transaction->rollback();
    
    // Registrar error
    error_log('Error al registrar voto: ' . $e->getMessage());
    
    $response['message'] = 'Error al procesar el voto. Intenta nuevamente.';
}

// Devolver respuesta como JSON
header('Content-Type: application/json');
echo json_encode($response);

/**
 * Obtiene y agrega los resultados de la encuesta a la respuesta
 * 
 * @param int $pollId ID de la encuesta
 * @param array &$response Array de respuesta a modificar
 */
function showResults($pollId, &$response) {
    $db = Database::getInstance();
    
    // Obtener todas las opciones con sus votos
    $options = $db->fetchAll(
        "SELECT id, option_text, votes FROM poll_options WHERE poll_id = ? ORDER BY id",
        [$pollId]
    );
    
    // Calcular total de votos
    $totalVotes = 0;
    foreach ($options as $option) {
        $totalVotes += $option['votes'];
    }
    
    // Calcular porcentajes
    $formattedOptions = [];
    foreach ($options as $option) {
        $percentage = $totalVotes > 0 ? round(($option['votes'] / $totalVotes) * 100) : 0;
        
        $formattedOptions[] = [
            'id' => $option['id'],
            'text' => $option['option_text'], // CORREGIDO: usa option_text en lugar de text
            'votes' => $option['votes'],
            'percentage' => $percentage
        ];
    }
    
    // Actualizar respuesta
    $response['options'] = $formattedOptions;
    $response['total_votes'] = $totalVotes;
}