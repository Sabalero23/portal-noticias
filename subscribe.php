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
    redirect('index.php');
}

// Verificar si se envió el email
if (!isset($_POST['email']) || empty($_POST['email'])) {
    setFlashMessage('error', 'Por favor, ingresa tu dirección de email');
    redirect('index.php');
}

// Sanitizar y validar el email
$email = sanitize($_POST['email']);

if (!isValidEmail($email)) {
    setFlashMessage('error', 'Por favor, ingresa una dirección de email válida');
    redirect('index.php');
}

// Verificar aceptación de términos
if (!isset($_POST['accept_terms']) || $_POST['accept_terms'] != '1') {
    setFlashMessage('error', 'Debes aceptar recibir noticias para suscribirte');
    redirect('index.php');
}

// Obtener nombre (opcional)
$name = isset($_POST['name']) ? sanitize($_POST['name']) : '';

// Obtener categorías seleccionadas (opcional)
$categories = isset($_POST['categories']) && is_array($_POST['categories']) ? $_POST['categories'] : [];
$categoriesStr = !empty($categories) ? implode(',', array_map('intval', $categories)) : '';

// Verificar si ya está suscrito
$db = Database::getInstance();
$existingSubscriber = $db->fetch(
    "SELECT id, status, confirmed FROM subscribers WHERE email = ?",
    [$email]
);

// Si ya existe, actualizar en lugar de insertar
if ($existingSubscriber) {
    // Si está activo y confirmado, mostrar mensaje
    if ($existingSubscriber['status'] === 'active' && $existingSubscriber['confirmed']) {
        setFlashMessage('info', 'Ya estás suscrito a nuestro newsletter');
        redirect('index.php');
    }
    
    // Si está inactivo o no confirmado, reactivar
    $confirmationToken = generateToken();
    
    $db->query(
        "UPDATE subscribers SET 
         name = ?, 
         status = 'active', 
         confirmation_token = ?, 
         categories = ?,
         updated_at = NOW() 
         WHERE id = ?",
        [$name, $confirmationToken, $categoriesStr, $existingSubscriber['id']]
    );
    
    // Enviar email de confirmación
    sendConfirmationEmail($email, $name, $confirmationToken);
    
    setFlashMessage('success', 'Hemos enviado un email de confirmación a tu dirección de correo');
    redirect('index.php');
    exit;
}

// Generar token de confirmación
$confirmationToken = generateToken();

// Insertar nuevo suscriptor
$db->query(
    "INSERT INTO subscribers (email, name, status, confirmation_token, categories, created_at) 
     VALUES (?, ?, 'active', ?, ?, NOW())",
    [$email, $name, $confirmationToken, $categoriesStr]
);

// Verificar si se insertó correctamente
if ($db->lastInsertId()) {
    // Enviar email de confirmación
    sendConfirmationEmail($email, $name, $confirmationToken);
    
    setFlashMessage('success', 'Gracias por suscribirte. Hemos enviado un email de confirmación a tu dirección de correo');
} else {
    setFlashMessage('error', 'Error al procesar tu suscripción. Por favor, intenta nuevamente');
}

// Redireccionar a la página principal
redirect('index.php');

/**
 * Envía un email de confirmación al suscriptor
 * 
 * @param string $email Email del suscriptor
 * @param string $name Nombre del suscriptor
 * @param string $token Token de confirmación
 * @return bool True si se envió, false si no
 */
function sendConfirmationEmail($email, $name, $token) {
    // Construir URL de confirmación
    $confirmUrl = SITE_URL . '/confirm_subscription.php?token=' . urlencode($token) . '&email=' . urlencode($email);
    
    // Nombre para mostrar
    $displayName = !empty($name) ? $name : 'Suscriptor';
    
    // Asunto del correo
    $subject = 'Confirma tu suscripción a ' . getSetting('site_name', 'Portal de Noticias');
    
    // Cuerpo del correo
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="' . SITE_URL . '/' . getSetting('logo', 'assets/img/logo.png') . '" alt="Logo" style="max-width: 200px;">
        </div>
        
        <h2 style="color: #333;">¡Hola ' . htmlspecialchars($displayName) . '!</h2>
        
        <p>Gracias por suscribirte al newsletter de ' . getSetting('site_name', 'Portal de Noticias') . '.</p>
        
        <p>Para confirmar tu suscripción, por favor haz clic en el siguiente botón:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $confirmUrl . '" style="background-color: #2196F3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Confirmar Suscripción</a>
        </div>
        
        <p>O copia y pega el siguiente enlace en tu navegador:</p>
        <p style="word-break: break-all; color: #666;">' . $confirmUrl . '</p>
        
        <p>Si no has solicitado esta suscripción, puedes ignorar este mensaje.</p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        
        <p style="color: #777; font-size: 12px; text-align: center;">
            &copy; ' . date('Y') . ' ' . getSetting('site_name', 'Portal de Noticias') . '. Todos los derechos reservados.
        </p>
    </div>
    ';
    
    // Enviar el correo
    return sendEmail($email, $subject, $body);
}