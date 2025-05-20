<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Verificar si se proporcionó el token y el email
if (!isset($_GET['token']) || empty($_GET['token']) || !isset($_GET['email']) || empty($_GET['email'])) {
    setFlashMessage('error', 'Enlace de confirmación inválido');
    redirect('index.php');
}

// Sanitizar entradas
$token = sanitize($_GET['token']);
$email = sanitize($_GET['email']);

// Validar email
if (!isValidEmail($email)) {
    setFlashMessage('error', 'Dirección de email inválida');
    redirect('index.php');
}

// Buscar suscriptor
$db = Database::getInstance();
$subscriber = $db->fetch(
    "SELECT id, name, status, confirmed FROM subscribers 
     WHERE email = ? AND confirmation_token = ?",
    [$email, $token]
);

// Verificar si existe
if (!$subscriber) {
    setFlashMessage('error', 'Enlace de confirmación inválido o expirado');
    redirect('index.php');
}

// Verificar si ya está confirmado
if ($subscriber['confirmed']) {
    setFlashMessage('info', 'Tu suscripción ya ha sido confirmada anteriormente');
    redirect('index.php');
}

// Confirmar suscripción
$updated = $db->query(
    "UPDATE subscribers SET confirmed = 1, status = 'active', updated_at = NOW() WHERE id = ?",
    [$subscriber['id']]
);

// Verificar si se actualizó correctamente
if ($updated) {
    // Notificar confirmación
    sendWelcomeEmail($email, $subscriber['name']);
    
    setFlashMessage('success', '¡Tu suscripción ha sido confirmada exitosamente! Gracias por suscribirte a nuestro newsletter.');
} else {
    setFlashMessage('error', 'Error al confirmar tu suscripción. Por favor, intenta nuevamente.');
}

// Redireccionar a la página principal
redirect('index.php');

/**
 * Envía un email de bienvenida al suscriptor confirmado
 * 
 * @param string $email Email del suscriptor
 * @param string $name Nombre del suscriptor
 * @return bool True si se envió, false si no
 */
function sendWelcomeEmail($email, $name) {
    // Nombre para mostrar
    $displayName = !empty($name) ? $name : 'Suscriptor';
    
    // Asunto del correo
    $subject = '¡Bienvenido al newsletter de ' . getSetting('site_name', 'Portal de Noticias') . '!';
    
    // Obtener categorías populares
    $db = Database::getInstance();
    $popularCategories = $db->fetchAll(
        "SELECT c.id, c.name, c.slug, COUNT(n.id) as news_count
         FROM categories c
         JOIN news n ON c.id = n.category_id
         WHERE n.status = 'published'
         GROUP BY c.id
         ORDER BY news_count DESC
         LIMIT 3"
    );
    
    // Crear enlaces a categorías populares
    $categoriesHtml = '';
    if (!empty($popularCategories)) {
        $categoriesHtml .= '<h3 style="color: #333; margin-top: 30px;">Categorías populares</h3>';
        $categoriesHtml .= '<ul style="padding-left: 20px;">';
        
        foreach ($popularCategories as $category) {
            $categoryUrl = SITE_URL . '/category.php?slug=' . $category['slug'];
            $categoriesHtml .= '<li style="margin-bottom: 10px;"><a href="' . $categoryUrl . '" style="color: #2196F3; text-decoration: none;">' . htmlspecialchars($category['name']) . '</a></li>';
        }
        
        $categoriesHtml .= '</ul>';
    }
    
    // Obtener noticias recientes
    $recentNews = $db->fetchAll(
        "SELECT id, title, slug, excerpt, image
         FROM news
         WHERE status = 'published'
         ORDER BY published_at DESC
         LIMIT 3"
    );
    
    // Crear sección de noticias recientes
    $newsHtml = '';
    if (!empty($recentNews)) {
        $newsHtml .= '<h3 style="color: #333; margin-top: 30px;">Noticias destacadas</h3>';
        
        foreach ($recentNews as $news) {
            $newsUrl = SITE_URL . '/news.php?slug=' . $news['slug'];
            $newsImage = SITE_URL . '/' . $news['image'];
            
            $newsHtml .= '
            <div style="margin-bottom: 20px; border: 1px solid #eee; border-radius: 5px; overflow: hidden;">
                <a href="' . $newsUrl . '" style="text-decoration: none; color: inherit;">
                    <img src="' . $newsImage . '" alt="' . htmlspecialchars($news['title']) . '" style="width: 100%; max-height: 200px; object-fit: cover;">
                    <div style="padding: 15px;">
                        <h4 style="color: #333; margin-top: 0;">' . htmlspecialchars($news['title']) . '</h4>
                        <p style="color: #666; font-size: 14px;">' . htmlspecialchars(truncateString($news['excerpt'], 100)) . '</p>
                        <p style="margin-bottom: 0;"><span style="color: #2196F3; font-weight: bold;">Leer más →</span></p>
                    </div>
                </a>
            </div>
            ';
        }
    }
    
    // Cuerpo del correo
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="' . SITE_URL . '/' . getSetting('logo', 'assets/img/logo.png') . '" alt="Logo" style="max-width: 200px;">
        </div>
        
        <h2 style="color: #333;">¡Bienvenido ' . htmlspecialchars($displayName) . '!</h2>
        
        <p>Gracias por confirmar tu suscripción al newsletter de ' . getSetting('site_name', 'Portal de Noticias') . '.</p>
        
        <p>A partir de ahora, recibirás periódicamente nuestras actualizaciones con las noticias más relevantes.</p>
        
        ' . $categoriesHtml . '
        
        ' . $newsHtml . '
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . SITE_URL . '" style="background-color: #2196F3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Visitar Portal de Noticias</a>
        </div>
        
        <p style="margin-top: 30px;">¡Esperamos que disfrutes de nuestro contenido!</p>
        
        <p>Saludos,<br>El equipo de ' . getSetting('site_name', 'Portal de Noticias') . '</p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        
        <p style="color: #777; font-size: 12px;">
            Si deseas cancelar tu suscripción, puedes hacerlo en cualquier momento haciendo clic <a href="' . SITE_URL . '/unsubscribe.php?email=' . urlencode($email) . '&token=' . urlencode(generateToken()) . '" style="color: #777;">aquí</a>.
        </p>
        
        <p style="color: #777; font-size: 12px; text-align: center;">
            &copy; ' . date('Y') . ' ' . getSetting('site_name', 'Portal de Noticias') . '. Todos los derechos reservados.
        </p>
    </div>
    ';
    
    // Enviar el correo
    return sendEmail($email, $subject, $body);
}