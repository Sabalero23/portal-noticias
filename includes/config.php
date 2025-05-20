<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}
// Modo de depuración
define('DEBUG_MODE', false);

// Configuración general
define('SITE_URL', 'https://noti.ordenes.com.ar');
define('ADMIN_URL', SITE_URL . '/admin');
define('TIMEZONE', 'America/Argentina/Buenos_Aires');

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'noticias');
define('DB_PASS', 'clave');
define('DB_NAME', 'noticias');
define('DB_CHARSET', 'utf8mb4');

// Configuración de correo
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'tu_email@gmail.com');
define('MAIL_PASSWORD', 'tu_contraseña');
define('MAIL_FROM', 'noreply@portalnoticias.com');
define('MAIL_FROM_NAME', 'Portal de Noticias');

// Configuración de archivos
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Configuración de seguridad
define('TOKEN_SALT', 'un_salt_aleatorio_para_tokens');
define('COOKIE_EXPIRE', time() + (86400 * 30)); // 30 días
define('SESSION_EXPIRE', 1800); // 30 minutos

// Versión del sistema
define('SYSTEM_VERSION', '1.0.0');

// Configuración PWA
define('PWA_ENABLED', true);
define('PWA_NAME', 'Portal de Noticias');
define('PWA_SHORT_NAME', 'Noticias');
define('PWA_THEME_COLOR', '#2196F3');
define('PWA_BACKGROUND_COLOR', '#ffffff');

// Zona horaria
date_default_timezone_set(TIMEZONE);

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
