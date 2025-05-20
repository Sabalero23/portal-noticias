<?php
// Definir ruta base
define('BASE_PATH', dirname(dirname(__DIR__)));
define('ADMIN_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once ADMIN_PATH . '/includes/functions.php';

// Si la función no existe, crearla localmente
if (!function_exists('logAdminAction')) {
    function logAdminAction($action, $details = '', $module = '', $itemId = 0) {
        // Implementación simple que no hace nada
        // O podrías implementar una versión básica aquí si lo deseas
        return true;
    }
}

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor'], 'index.php');

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    setFlashMessage('error', 'Token de seguridad inválido');
    redirect('index.php');
}

// Verificar que tenemos acción e IDs
if (!isset($_POST['action']) || !isset($_POST['ids']) || !is_array($_POST['ids']) || empty($_POST['ids'])) {
    setFlashMessage('error', 'Parámetros incorrectos');
    redirect('index.php');
}

$action = sanitize($_POST['action']);
$ids = array_map('intval', $_POST['ids']);

// Verificar acción válida
$validActions = ['approve', 'pending', 'spam', 'trash', 'restore', 'delete'];
if (!in_array($action, $validActions)) {
    setFlashMessage('error', 'Acción no válida');
    redirect('index.php');
}

// Iniciar transacción
$db = Database::getInstance();
$transaction = new Transaction();
$transaction->begin();

try {
    $count = 0;
    
    // Determinar acción a realizar
    switch ($action) {
        case 'approve':
            // Aprobar comentarios
            $count = $db->query(
                "UPDATE comments SET status = 'approved' WHERE id IN (" . implode(',', $ids) . ")",
                []
            );
            $message = 'Comentarios aprobados correctamente';
            $actionType = 'bulk_approve_comments';
            break;
            
        case 'pending':
            // Marcar como pendientes
            $count = $db->query(
                "UPDATE comments SET status = 'pending' WHERE id IN (" . implode(',', $ids) . ")",
                []
            );
            $message = 'Comentarios marcados como pendientes';
            $actionType = 'bulk_pending_comments';
            break;
            
        case 'spam':
            // Marcar como spam
            $count = $db->query(
                "UPDATE comments SET status = 'spam' WHERE id IN (" . implode(',', $ids) . ")",
                []
            );
            $message = 'Comentarios marcados como spam';
            $actionType = 'bulk_spam_comments';
            break;
            
        case 'trash':
            // Mover a la papelera
            $count = $db->query(
                "UPDATE comments SET status = 'trash' WHERE id IN (" . implode(',', $ids) . ")",
                []
            );
            $message = 'Comentarios movidos a la papelera';
            $actionType = 'bulk_trash_comments';
            break;
            
        case 'restore':
            // Restaurar de la papelera
            $count = $db->query(
                "UPDATE comments SET status = 'approved' WHERE id IN (" . implode(',', $ids) . ") AND status = 'trash'",
                []
            );
            $message = 'Comentarios restaurados correctamente';
            $actionType = 'bulk_restore_comments';
            break;
            
        case 'delete':
            // Eliminar permanentemente
            $count = $db->query(
                "DELETE FROM comments WHERE id IN (" . implode(',', $ids) . ")",
                []
            );
            $message = 'Comentarios eliminados permanentemente';
            $actionType = 'bulk_delete_comments';
            break;
    }
    
    // Confirmar transacción
    $transaction->commit();
    
    // Registrar la acción (modificado para evitar el error)
    logAdminAction(
        $actionType, 
        "Comentarios afectados: " . count($ids), 
        'comments', 
        0 // No hay un ID específico
    );
    
    setFlashMessage('success', $message);
    
} catch (Exception $e) {
    // Revertir transacción
    $transaction->rollback();
    
    // Registrar error
    error_log('Error en acción masiva de comentarios: ' . $e->getMessage());
    
    setFlashMessage('error', 'Error al procesar la acción: ' . $e->getMessage());
}

// Redirigir a la lista de comentarios
redirect('index.php');