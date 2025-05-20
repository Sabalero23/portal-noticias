<?php
// Definir ruta base
define('BASE_PATH', dirname(__DIR__, 2));
define('ADMIN_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor', 'author'], '../index.php');

// Verificar token CSRF
if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
    setFlashMessage('error', 'Token de seguridad inválido. La acción ha sido cancelada.');
    redirect('index.php');
    exit;
}

// Verificar que se proporcionó un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de archivo inválido.');
    redirect('index.php');
    exit;
}

$fileId = (int)$_GET['id'];
$db = Database::getInstance();

// Obtener información del archivo
$file = $db->fetch(
    "SELECT id, file_name, file_path, user_id FROM media WHERE id = ?",
    [$fileId]
);

if (!$file) {
    setFlashMessage('error', 'El archivo seleccionado no existe.');
    redirect('index.php');
    exit;
}

// Verificar permisos (solo admin puede eliminar cualquier archivo, usuarios solo pueden eliminar sus propios archivos)
$currentUserId = $_SESSION['user']['id'];
$userRole = $_SESSION['user']['role'];

if ($file['user_id'] != $currentUserId && $userRole !== 'admin') {
    setFlashMessage('error', 'No tienes permisos para eliminar este archivo.');
    redirect('index.php');
    exit;
}

// Verificar si el archivo está en uso
$isInUse = false;

// Verificar si el archivo se usa en noticias (imagen principal)
$newsCount = $db->fetch(
    "SELECT COUNT(*) as count FROM news WHERE image = ? OR thumbnail = ?",
    [$file['file_path'], $file['file_path']]
)['count'];

if ($newsCount > 0) {
    $isInUse = true;
}

// Verificar si el archivo se usa en contenido de noticias
if (!$isInUse) {
    $newsContentCount = $db->fetch(
        "SELECT COUNT(*) as count FROM news WHERE content LIKE ?",
        ['%' . $file['file_path'] . '%']
    )['count'];
    
    if ($newsContentCount > 0) {
        $isInUse = true;
    }
}

// Verificar si el archivo se usa en categorías
if (!$isInUse) {
    $categoryCount = $db->fetch(
        "SELECT COUNT(*) as count FROM categories WHERE image = ?",
        [$file['file_path']]
    )['count'];
    
    if ($categoryCount > 0) {
        $isInUse = true;
    }
}

// Verificar si el archivo se usa en anuncios
if (!$isInUse) {
    $adCount = $db->fetch(
        "SELECT COUNT(*) as count FROM ads WHERE image = ?",
        [$file['file_path']]
    )['count'];
    
    if ($adCount > 0) {
        $isInUse = true;
    }
}

// Verificar si el archivo se usa como imagen de usuario
if (!$isInUse) {
    $userCount = $db->fetch(
        "SELECT COUNT(*) as count FROM users WHERE avatar = ?",
        [$file['file_path']]
    )['count'];
    
    if ($userCount > 0) {
        $isInUse = true;
    }
}

// Si el archivo está en uso, mostrar error
if ($isInUse) {
    setFlashMessage('error', 'No se puede eliminar el archivo porque está siendo utilizado en el sitio.');
    redirect('index.php');
    exit;
}

// Eliminar archivo físico
$filePath = BASE_PATH . '/' . $file['file_path'];
if (file_exists($filePath)) {
    if (!unlink($filePath)) {
        setFlashMessage('error', 'No se pudo eliminar el archivo físico. Verifique los permisos.');
        redirect('index.php');
        exit;
    }
}

// Eliminar registro de la base de datos
$deleted = $db->query(
    "DELETE FROM media WHERE id = ?",
    [$fileId]
);

if ($deleted) {
    // Registrar en log de actividad
    logAction('delete_file', 'Archivo eliminado: ' . $file['file_name'], $_SESSION['user']['id']);
    
    setFlashMessage('success', 'El archivo "' . $file['file_name'] . '" ha sido eliminado correctamente.');
} else {
    setFlashMessage('error', 'Error al eliminar el registro del archivo de la base de datos.');
}

// Redirigir a la biblioteca de medios
redirect('index.php');