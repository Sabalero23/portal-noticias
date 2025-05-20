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

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    setFlashMessage('error', 'Token de seguridad inválido');
    
    // Redirigir a la página anterior
    if (isset($_SERVER['HTTP_REFERER'])) {
        redirect($_SERVER['HTTP_REFERER']);
    } else {
        redirect('index.php');
    }
}

// Verificar que tenemos los datos necesarios
if (!isset($_POST['name']) || empty($_POST['name']) || 
    !isset($_POST['email']) || empty($_POST['email']) || 
    !isset($_POST['comment']) || empty($_POST['comment']) || 
    !isset($_POST['news_id']) || empty($_POST['news_id'])) {
    
    setFlashMessage('error', 'Por favor, completa todos los campos obligatorios');
    
    // Redirigir a la página anterior
    if (isset($_SERVER['HTTP_REFERER'])) {
        redirect($_SERVER['HTTP_REFERER']);
    } else {
        redirect('index.php');
    }
}

// Sanitizar entradas
$name = sanitize($_POST['name']);
$email = sanitize($_POST['email']);
$comment = sanitize($_POST['comment']);
$newsId = (int)$_POST['news_id'];
$website = isset($_POST['website']) ? sanitize($_POST['website']) : '';
$parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

// Validación adicional
if (strlen($name) < 2 || strlen($name) > 100) {
    setFlashMessage('error', 'El nombre debe tener entre 2 y 100 caracteres');
    redirectBack();
}

if (!isValidEmail($email)) {
    setFlashMessage('error', 'Por favor, ingresa una dirección de email válida');
    redirectBack();
}

if (strlen($comment) < 5 || strlen($comment) > 2000) {
    setFlashMessage('error', 'El comentario debe tener entre 5 y 2000 caracteres');
    redirectBack();
}

// Validar website si se proporciona
if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
    setFlashMessage('error', 'Por favor, ingresa una URL válida para tu sitio web');
    redirectBack();
}

// Verificar que la noticia existe
$db = Database::getInstance();
$news = $db->fetch(
    "SELECT id, title, slug, allow_comments FROM news WHERE id = ? AND status = 'published'",
    [$newsId]
);

if (!$news) {
    setFlashMessage('error', 'La noticia no existe o no está disponible');
    redirect('index.php');
}

// Verificar si se permiten comentarios
if (!$news['allow_comments']) {
    setFlashMessage('error', 'Los comentarios están deshabilitados para esta noticia');
    redirect('news.php?slug=' . $news['slug']);
}

// Verificar parent_id si existe
if ($parentId) {
    $parentComment = $db->fetch(
        "SELECT id FROM comments WHERE id = ? AND news_id = ? AND status = 'approved'",
        [$parentId, $newsId]
    );
    
    if (!$parentComment) {
        $parentId = null; // Si no existe el comentario padre, lo ignoramos
    }
}

// Obtener información adicional
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Comprobar estado de moderación de comentarios
$commentStatus = getSetting('moderate_comments', '1') === '1' ? 'pending' : 'approved';

// Si el usuario está logueado y tiene permisos especiales, aprobamos automáticamente
if (isLoggedIn() && hasRole(['admin', 'editor', 'author'])) {
    $commentStatus = 'approved';
}

// Guardar el comentario
$db->query(
    "INSERT INTO comments (news_id, parent_id, name, email, website, comment, status, ip_address, user_agent, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
    [$newsId, $parentId, $name, $email, $website, $comment, $commentStatus, $ipAddress, $userAgent]
);

// Verificar si se insertó correctamente
if ($db->lastInsertId()) {
    // Mensaje según el estado
    if ($commentStatus === 'approved') {
        setFlashMessage('success', 'Tu comentario ha sido publicado exitosamente');
        
        // Notificar al administrador sobre el nuevo comentario
        notifyAdminNewComment($news, $name, $comment);
    } else {
        setFlashMessage('info', 'Tu comentario ha sido recibido y está pendiente de moderación');
    }
    
    // Si está logueado, guardar una cookie con su información para futuros comentarios
    if (!isLoggedIn()) {
        setcookie('comment_name', $name, time() + (86400 * 30), '/'); // 30 días
        setcookie('comment_email', $email, time() + (86400 * 30), '/');
        if (!empty($website)) {
            setcookie('comment_website', $website, time() + (86400 * 30), '/');
        }
    }
} else {
    setFlashMessage('error', 'Error al publicar tu comentario. Por favor, intenta nuevamente');
}

// Redirigir a la página de la noticia
redirect('news.php?slug=' . $news['slug'] . '#comments');

/**
 * Redirige a la página anterior
 */
function redirectBack() {
    if (isset($_SERVER['HTTP_REFERER'])) {
        redirect($_SERVER['HTTP_REFERER']);
    } else {
        redirect('index.php');
    }
    exit;
}

/**
 * Notifica al administrador sobre un nuevo comentario
 * 
 * @param array $news Datos de la noticia
 * @param string $commenterName Nombre del comentarista
 * @param string $commentText Texto del comentario
 */
function notifyAdminNewComment($news, $commenterName, $commentText) {
    // Email del administrador
    $adminEmail = getSetting('email_contact', 'admin@portalnoticias.com');
    
    // Asunto del correo
    $subject = 'Nuevo comentario en "' . $news['title'] . '"';
    
    // URL de la noticia
    $newsUrl = SITE_URL . '/news.php?slug=' . $news['slug'] . '#comments';
    
    // URL para administrar comentarios
    $commentsAdminUrl = SITE_URL . '/admin/comments/index.php';
    
    // Cuerpo del correo
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
        <h2 style="color: #333;">Nuevo comentario</h2>
        
        <p>Se ha recibido un nuevo comentario en la noticia "<strong>' . htmlspecialchars($news['title']) . '</strong>".</p>
        
        <div style="background-color: #f9f9f9; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;">
            <p><strong>Nombre:</strong> ' . htmlspecialchars($commenterName) . '</p>
            <p><strong>Comentario:</strong> ' . htmlspecialchars($commentText) . '</p>
        </div>
        
        <div style="margin: 25px 0; text-align: center;">
            <a href="' . $newsUrl . '" style="background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px; display: inline-block;">Ver comentario</a>
            <a href="' . $commentsAdminUrl . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">Administrar comentarios</a>
        </div>
        
        <p style="color: #777; font-size: 12px; text-align: center; margin-top: 30px;">
            Este es un mensaje automático, por favor no respondas a este correo.
        </p>
    </div>
    ';
    
    // Enviar el correo
    sendEmail($adminEmail, $subject, $body);
}