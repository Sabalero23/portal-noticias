<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Método no permitido');
    redirect('index.php');
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    setFlashMessage('error', 'Token de seguridad inválido. Por favor, intenta nuevamente.');
    redirect('index.php');
}

// Obtener datos del formulario
$newsId = isset($_POST['news_id']) ? intval($_POST['news_id']) : 0;
$parentId = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
$name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
$email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
$website = isset($_POST['website']) ? sanitize($_POST['website']) : '';
$comment = isset($_POST['comment']) ? sanitize($_POST['comment']) : '';

// Validar datos
$errors = [];

if (empty($newsId)) {
    $errors[] = 'ID de noticia inválido';
}

if (empty($name)) {
    $errors[] = 'El nombre es obligatorio';
}

if (empty($email)) {
    $errors[] = 'El email es obligatorio';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El email no es válido';
}

if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
    $errors[] = 'La URL del sitio web no es válida';
}

if (empty($comment)) {
    $errors[] = 'El comentario es obligatorio';
}

// Si hay errores, redirigir de vuelta
if (!empty($errors)) {
    setFlashMessage('error', implode('<br>', $errors));
    // Intentar redirigir a la página de la noticia
    $db = Database::getInstance();
    $news = $db->fetch("SELECT slug FROM news WHERE id = ?", [$newsId]);
    
    if ($news) {
        redirect('news.php?slug=' . $news['slug'] . '#comments');
    } else {
        redirect('index.php');
    }
    exit;
}

// Verificar si la noticia existe y permite comentarios
$db = Database::getInstance();
$news = $db->fetch(
    "SELECT id, title, slug, allow_comments FROM news WHERE id = ? AND status = 'published'",
    [$newsId]
);

if (!$news) {
    setFlashMessage('error', 'La noticia no existe o no está disponible');
    redirect('index.php');
    exit;
}

if (!$news['allow_comments']) {
    setFlashMessage('error', 'Los comentarios están desactivados para esta noticia');
    redirect('news.php?slug=' . $news['slug']);
    exit;
}

// Verificar si el comentario padre existe
if ($parentId !== null) {
    $parentComment = $db->fetch(
        "SELECT id FROM comments WHERE id = ? AND news_id = ?",
        [$parentId, $newsId]
    );
    
    if (!$parentComment) {
        $parentId = null; // Ignorar el parentId si no existe
    }
}

// Determinar el estado del comentario (automáticamente aprobado o pendiente)
$commentStatus = getSetting('moderate_comments', '1') === '1' ? 'pending' : 'approved';

// Guardar el comentario
try {
    $db->query(
        "INSERT INTO comments (news_id, parent_id, name, email, website, comment, ip_address, user_agent, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            $newsId,
            $parentId,
            $name,
            $email,
            $website,
            $comment,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $commentStatus
        ]
    );
    
    // Registrar la actividad
    logAction('Nuevo comentario', 'Comentario en: ' . $news['title'], 0);
    
    // Mostrar mensaje según el estado
    if ($commentStatus === 'pending') {
        setFlashMessage('success', 'Tu comentario ha sido enviado y está pendiente de moderación. ¡Gracias por participar!');
    } else {
        setFlashMessage('success', '¡Comentario publicado correctamente!');
    }
    
    // Enviar notificación por email a los administradores (opcional)
    $adminEmail = getSetting('email_contact', '');
    if (!empty($adminEmail)) {
        $siteName = getSetting('site_name', 'Portal de Noticias');
        $subject = '[' . $siteName . '] Nuevo comentario en: ' . $news['title'];
        
        $message = "Se ha recibido un nuevo comentario en la noticia: " . $news['title'] . "\n\n";
        $message .= "Autor: " . $name . "\n";
        $message .= "Email: " . $email . "\n";
        if (!empty($website)) {
            $message .= "Sitio web: " . $website . "\n";
        }
        $message .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Desconocida') . "\n\n";
        $message .= "Comentario:\n" . $comment . "\n\n";
        
        if ($commentStatus === 'pending') {
            $message .= "El comentario está pendiente de moderación. Puedes aprobarlo desde el panel de administración.\n";
            $message .= SITE_URL . "/admin/comments/index.php\n";
        } else {
            $message .= "El comentario ha sido aprobado automáticamente. Puedes verlo en:\n";
            $message .= SITE_URL . "/news.php?slug=" . $news['slug'] . "#comments\n";
        }
        
        // Enviar email
        $headers = "From: " . $siteName . " <" . $adminEmail . ">\r\n";
        $headers .= "Reply-To: " . $name . " <" . $email . ">\r\n";
        mail($adminEmail, $subject, $message, $headers);
    }
    
} catch (Exception $e) {
    error_log('Error al guardar comentario: ' . $e->getMessage());
    setFlashMessage('error', 'Ha ocurrido un error al procesar tu comentario. Por favor, intenta nuevamente.');
}

// Redirigir a la página de la noticia
redirect('news.php?slug=' . $news['slug'] . '#comments');