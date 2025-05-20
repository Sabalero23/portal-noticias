<?php
// Definir ruta base
define('BASE_PATH', dirname(dirname(__DIR__)));
define('ADMIN_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor'], 'index.php');

// Verificar token CSRF
if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
    setFlashMessage('error', 'Token de seguridad inválido');
    redirect('index.php');
}

// Verificar que tenemos un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('error', 'ID de comentario no proporcionado');
    redirect('index.php');
}

$commentId = (int)$_GET['id'];
$permanent = isset($_GET['permanent']) && $_GET['permanent'] == '1';

// Verificar si existe el comentario
$db = Database::getInstance();
$comment = $db->fetch(
    "SELECT id, status FROM comments WHERE id = ?",
    [$commentId]
);

if (!$comment) {
    setFlashMessage('error', 'Comentario no encontrado');
    redirect('index.php');
}

// Determinar si es eliminación permanente o mover a la papelera
if ($permanent) {
    // Eliminar permanentemente
    $deleted = $db->query(
        "DELETE FROM comments WHERE id = ?",
        [$commentId]
    );
    
    $message = 'Comentario eliminado permanentemente';
    $actionType = 'delete_comment_permanent';
} else {
    // Si ya está en la papelera, no hacer nada
    if ($comment['status'] === 'trash') {
        setFlashMessage('info', 'El comentario ya estaba en la papelera');
        redirect('index.php');
    }
    
    // Mover a la papelera
    $deleted = $db->query(
        "UPDATE comments SET status = 'trash' WHERE id = ?",
        [$commentId]
    );
    
    $message = 'Comentario movido a la papelera';
    $actionType = 'delete_comment_trash';
}

if ($deleted) {
    // Registrar la acción
    logAdminAction(
        $actionType, 
        "Comentario ID: $commentId", 
        'comments', 
        $commentId
    );
    
    setFlashMessage('success', $message);
} else {
    setFlashMessage('error', 'Error al eliminar el comentario');
}

// Redirigir a la página anterior o a la lista de comentarios
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'admin/comments') !== false) {
    redirect($_SERVER['HTTP_REFERER']);
} else {
    redirect('index.php');
}