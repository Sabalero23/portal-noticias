<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Obtener el tema solicitado
$theme = isset($_GET['theme']) ? sanitize($_GET['theme']) : 'default';

// Validar que el tema existe
$themePath = BASE_PATH . '/assets/themes/' . $theme;
if (!is_dir($themePath) || !file_exists($themePath . '/styles.css') || !file_exists($themePath . '/responsive.css')) {
    // Si el tema no existe, usar el predeterminado
    $theme = 'default';
}

// Guardar el tema seleccionado en una cookie (dura 30 días)
setcookie('user_theme', $theme, time() + (30 * 24 * 60 * 60), '/');

// Si el usuario está autenticado, guardar la preferencia en la base de datos
if (isLoggedIn()) {
    $db = Database::getInstance();
    $userId = $_SESSION['user']['id'];
    
    // Verificar si el usuario ya tiene una preferencia guardada
    $preference = $db->fetch(
        "SELECT id FROM user_preferences WHERE user_id = ? AND preference_key = 'theme'",
        [$userId]
    );
    
    if ($preference) {
        // Actualizar preferencia existente
        $db->query(
            "UPDATE user_preferences SET preference_value = ?, updated_at = NOW() WHERE user_id = ? AND preference_key = 'theme'",
            [$theme, $userId]
        );
    } else {
        // Crear nueva preferencia
        $db->query(
            "INSERT INTO user_preferences (user_id, preference_key, preference_value, created_at, updated_at) 
             VALUES (?, 'theme', ?, NOW(), NOW())",
            [$userId, $theme]
        );
    }
}

// Opcional: Actualizar también la configuración global si es un administrador
if (hasRole(['admin'])) {
    // Pregunta al administrador si quiere establecer este tema como predeterminado
    if (isset($_GET['set_default']) && $_GET['set_default'] == '1') {
        $db = Database::getInstance();
        
        // Verificar si la configuración existe
        $setting = $db->fetch(
            "SELECT id FROM settings WHERE setting_key = 'active_theme'",
            []
        );
        
        if ($setting) {
            // Actualizar configuración existente
            $db->query(
                "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = 'active_theme'",
                [$theme]
            );
        } else {
            // Insertar nueva configuración
            $db->query(
                "INSERT INTO settings (setting_key, setting_value, setting_group, created_at, updated_at) 
                 VALUES ('active_theme', ?, 'site', NOW(), NOW())",
                [$theme]
            );
        }
        
        // Registrar la acción en el log
        logAction('Actualizar tema', 'Tema global actualizado a ' . $theme, $userId);
        
        // Mostrar mensaje de éxito
        setFlashMessage('success', 'Se ha establecido "' . ucfirst($theme) . '" como el tema predeterminado del sitio.');
    }
}

// Redirigir a la página anterior o a la página de inicio
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header('Location: ' . $referer);
exit;