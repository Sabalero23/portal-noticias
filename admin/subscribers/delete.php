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

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor'], '../index.php');

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Método no válido');
    redirect('index.php');
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    setFlashMessage('error', 'Token de seguridad inválido');
    redirect('index.php');
    exit;
}

// Verificar que tengamos IDs seleccionados
if (!isset($_POST['selected_ids']) || !is_array($_POST['selected_ids']) || count($_POST['selected_ids']) === 0) {
    setFlashMessage('error', 'No se han seleccionado suscriptores');
    redirect('index.php');
    exit;
}

// Sanitizar IDs
$selectedIds = array_map('intval', $_POST['selected_ids']);

// Verificar la acción a realizar
$actionType = isset($_POST['action_type']) ? sanitize($_POST['action_type']) : '';

// Inicializar DB
$db = Database::getInstance();

// Iniciar transacción
$transaction = new Transaction();
$transaction->begin();

try {
    // Verificar acción
    if ($actionType === 'delete') {
        // Eliminar suscriptores
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $deleted = $db->query(
            "DELETE FROM subscribers WHERE id IN ($placeholders)",
            $selectedIds
        );
        
        // Verificar resultado
        if ($deleted === false) {
            throw new Exception('Error al eliminar los suscriptores');
        }
        
        // Registrar acción en el log
        logAction('Eliminar suscriptores', 'Se eliminaron ' . count($selectedIds) . ' suscriptores', $_SESSION['user']['id'] ?? 0);
        
        // Mensaje de éxito
        $successMessage = count($selectedIds) === 1 
            ? 'Suscriptor eliminado correctamente' 
            : 'Se eliminaron ' . count($selectedIds) . ' suscriptores correctamente';
        
        setFlashMessage('success', $successMessage);
    } 
    elseif ($actionType === 'status') {
        // Cambiar estado de suscriptores
        $newStatus = isset($_POST['new_status']) ? sanitize($_POST['new_status']) : '';
        
        // Validar estado
        $validStatuses = ['active', 'inactive', 'unsubscribed'];
        if (!in_array($newStatus, $validStatuses)) {
            throw new Exception('Estado no válido');
        }
        
        // Actualizar estado
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $params = array_merge([$newStatus], $selectedIds);
        
        $updated = $db->query(
            "UPDATE subscribers SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)",
            $params
        );
        
        // Verificar resultado
        if ($updated === false) {
            throw new Exception('Error al actualizar el estado de los suscriptores');
        }
        
        // Estado en español para el mensaje
        $statusSpanish = '';
        switch ($newStatus) {
            case 'active':
                $statusSpanish = 'activo';
                break;
            case 'inactive':
                $statusSpanish = 'inactivo';
                break;
            case 'unsubscribed':
                $statusSpanish = 'dado de baja';
                break;
        }
        
        // Registrar acción en el log
        logAdminAction(
            'Cambiar estado de suscriptores', 
            'Se cambió el estado de ' . count($selectedIds) . ' suscriptores a "' . $statusSpanish . '"', 
            'subscribers'
        );
        
        // Mensaje de éxito
        $successMessage = count($selectedIds) === 1 
            ? 'Estado del suscriptor cambiado a "' . $statusSpanish . '" correctamente' 
            : 'Se cambió el estado de ' . count($selectedIds) . ' suscriptores a "' . $statusSpanish . '" correctamente';
        
        setFlashMessage('success', $successMessage);
    } 
    else {
        throw new Exception('Acción no válida');
    }
    
    // Confirmar transacción
    $transaction->commit();
} 
catch (Exception $e) {
    // Revertir transacción en caso de error
    $transaction->rollback();
    
    // Mensaje de error
    setFlashMessage('error', 'Error: ' . $e->getMessage());
}

// Redireccionar de vuelta al listado
redirect('index.php');