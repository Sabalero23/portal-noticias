<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Si el usuario ya está logueado, redirigir a la página principal
if (isLoggedIn()) {
    redirect('index.php');
}

// Verificar si se proporcionó el token y el email
if (!isset($_GET['token']) || empty($_GET['token']) || !isset($_GET['email']) || empty($_GET['email'])) {
    setFlashMessage('error', 'Enlace de confirmación inválido');
    redirect('login.php');
}

// Sanitizar entradas
$token = sanitize($_GET['token']);
$email = sanitize($_GET['email']);

// Validar email
if (!isValidEmail($email)) {
    setFlashMessage('error', 'Dirección de email inválida');
    redirect('login.php');
}

// Buscar usuario
$db = Database::getInstance();
$user = $db->fetch(
    "SELECT id, name, username, status, confirmation_token 
     FROM users 
     WHERE email = ? AND confirmation_token = ?",
    [$email, $token]
);

// Verificar si existe
if (!$user) {
    setFlashMessage('error', 'Enlace de confirmación inválido o expirado');
    redirect('login.php');
}

// Verificar si ya está confirmado
if ($user['status'] === 'active') {
    setFlashMessage('info', 'Tu cuenta ya ha sido confirmada anteriormente. Puedes iniciar sesión.');
    redirect('login.php');
}

// Confirmar cuenta
$updated = $db->query(
    "UPDATE users SET status = 'active', confirmation_token = NULL, updated_at = NOW() WHERE id = ?",
    [$user['id']]
);

// Verificar si se actualizó correctamente
if ($updated) {
    // Enviar correo de bienvenida
    sendWelcomeEmail($email, $user['name'], $user['username']);
    
    setFlashMessage('success', '¡Tu cuenta ha sido confirmada exitosamente! Ahora puedes iniciar sesión.');
    redirect('login.php?registered=success');
} else {
    setFlashMessage('error', 'Error al confirmar tu cuenta. Por favor, intenta nuevamente o contacta al administrador.');
    redirect('login.php');
}

/**
 * Envía un correo de bienvenida al usuario confirmado
 * 
 * @param string $email Email del usuario
 * @param string $name Nombre del usuario
 * @param string $username Nombre de usuario
 * @return bool True si se envió, false si no
 */
function sendWelcomeEmail($email, $name, $username) {
    // Nombre para mostrar
    $displayName = !empty($name) ? $name : $username;
    
    // Asunto del correo
    $subject = '¡Bienvenido a ' . getSetting('site_name', 'Portal de Noticias') . '!';
    
    // URL de login
    $loginUrl = SITE_URL . '/login.php';
    
    // Cuerpo del correo en HTML
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="' . SITE_URL . '/' . getSetting('logo', 'assets/img/logo.png') . '" alt="Logo" style="max-width: 200px;">
        </div>
        
        <h2 style="color: #333;">¡Bienvenido ' . htmlspecialchars($displayName) . '!</h2>
        
        <p>Gracias por registrarte en ' . getSetting('site_name', 'Portal de Noticias') . '. Tu cuenta ha sido activada exitosamente.</p>
        
        <p>Con tu cuenta podrás:</p>
        <ul>
            <li>Comentar en noticias</li>
            <li>Guardar tus artículos favoritos</li>
            <li>Personalizar tu experiencia de lectura</li>
            <li>Recibir notificaciones de temas de tu interés</li>
        </ul>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $loginUrl . '" style="background-color: #2196F3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Iniciar sesión</a>
        </div>
        
        <p>No dudes en contactarnos si tienes alguna pregunta o sugerencia.</p>
        
        <p>¡Esperamos que disfrutes de nuestro contenido!</p>
        
        <p>Saludos,<br>El equipo de ' . getSetting('site_name', 'Portal de Noticias') . '</p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        
        <p style="color: #777; font-size: 12px; text-align: center;">
            &copy; ' . date('Y') . ' ' . getSetting('site_name', 'Portal de Noticias') . '. Todos los derechos reservados.
        </p>
    </div>
    ';
    
    // Enviar correo
    return sendEmail($email, $subject, $body);
}
?>