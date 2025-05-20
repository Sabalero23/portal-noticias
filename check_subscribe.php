<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Habilitar la visualización de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para realizar pruebas de diagnóstico
function runDiagnostics() {
    $results = [
        'status' => 'success',
        'messages' => []
    ];
    
    // Verificar conexión a la base de datos
    try {
        $db = Database::getInstance();
        $results['messages'][] = "✅ Conexión a base de datos: OK";
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['messages'][] = "❌ Error en la conexión a la base de datos: " . $e->getMessage();
    }
    
    // Verificar tabla de suscriptores
    try {
        $db = Database::getInstance();
        $tableExists = $db->fetch("SHOW TABLES LIKE 'subscribers'");
        
        if ($tableExists) {
            $results['messages'][] = "✅ Tabla 'subscribers': Existe";
            
            // Verificar estructura de la tabla
            $columns = $db->fetchAll("SHOW COLUMNS FROM subscribers");
            $columnNames = array_column($columns, 'Field');
            
            $requiredColumns = ['id', 'email', 'name', 'status', 'confirmation_token', 'confirmed', 'categories', 'created_at', 'updated_at'];
            $missingColumns = array_diff($requiredColumns, $columnNames);
            
            if (empty($missingColumns)) {
                $results['messages'][] = "✅ Estructura de tabla 'subscribers': OK";
            } else {
                $results['status'] = 'warning';
                $results['messages'][] = "⚠️ Columnas faltantes en 'subscribers': " . implode(', ', $missingColumns);
            }
        } else {
            $results['status'] = 'error';
            $results['messages'][] = "❌ Tabla 'subscribers' no existe";
        }
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['messages'][] = "❌ Error al verificar tabla 'subscribers': " . $e->getMessage();
    }
    
    // Verificar funciones necesarias
    $requiredFunctions = ['verifyCsrfToken', 'generateToken', 'sanitize', 'isValidEmail', 'setFlashMessage', 'getFlashMessage', 'redirect', 'sendEmail'];
    
    foreach ($requiredFunctions as $function) {
        if (function_exists($function)) {
            $results['messages'][] = "✅ Función '$function': Disponible";
        } else {
            $results['status'] = 'error';
            $results['messages'][] = "❌ Función '$function': No disponible";
        }
    }
    
    // Verificar funcionamiento de verifyCsrfToken
    try {
        $token = generateCsrfToken();
        if ($token && verifyCsrfToken($token)) {
            $results['messages'][] = "✅ Generación y verificación de CSRF token: OK";
        } else {
            $results['status'] = 'error';
            $results['messages'][] = "❌ Error en la verificación de CSRF token";
        }
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['messages'][] = "❌ Error en la generación/verificación de CSRF token: " . $e->getMessage();
    }
    
    // Verificar configuración de email
    if (function_exists('sendEmail')) {
        // Verificar constantes necesarias para correo
        $emailConfigOk = true;
        $missingEmailConfig = [];
        
        if (!defined('MAIL_HOST') || empty(MAIL_HOST)) {
            $emailConfigOk = false;
            $missingEmailConfig[] = 'MAIL_HOST';
        }
        
        if (!defined('MAIL_PORT')) {
            $emailConfigOk = false;
            $missingEmailConfig[] = 'MAIL_PORT';
        }
        
        if (!defined('MAIL_USERNAME') || empty(MAIL_USERNAME)) {
            $emailConfigOk = false;
            $missingEmailConfig[] = 'MAIL_USERNAME';
        }
        
        if (!defined('MAIL_PASSWORD') || empty(MAIL_PASSWORD)) {
            $emailConfigOk = false;
            $missingEmailConfig[] = 'MAIL_PASSWORD';
        }
        
        if (!defined('MAIL_FROM') || empty(MAIL_FROM)) {
            $emailConfigOk = false;
            $missingEmailConfig[] = 'MAIL_FROM';
        }
        
        if (!defined('MAIL_FROM_NAME') || empty(MAIL_FROM_NAME)) {
            $emailConfigOk = false;
            $missingEmailConfig[] = 'MAIL_FROM_NAME';
        }
        
        if ($emailConfigOk) {
            $results['messages'][] = "✅ Configuración de correo: OK";
        } else {
            $results['status'] = 'warning';
            $results['messages'][] = "⚠️ Configuración de correo incompleta. Falta: " . implode(', ', $missingEmailConfig);
        }
    }
    
    // Verificar parámetros globales
    if (!defined('SITE_URL') || empty(SITE_URL)) {
        $results['status'] = 'warning';
        $results['messages'][] = "⚠️ Constante SITE_URL no definida o vacía";
    } else {
        $results['messages'][] = "✅ Constante SITE_URL: " . SITE_URL;
    }
    
    return $results;
}

// Verificar si estamos en un modo de prueba
if (isset($_GET['test']) && $_GET['test'] === 'email') {
    // Prueba de envío de email
    if (function_exists('sendEmail')) {
        $testEmail = isset($_GET['email']) ? $_GET['email'] : 'test@example.com';
        $subject = 'Prueba de correo desde Portal de Noticias';
        $body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
            <h2 style="color: #333;">Prueba de correo</h2>
            <p>Este es un correo de prueba del sistema de newsletter.</p>
            <p>Si recibes este correo, la configuración de correo está funcionando correctamente.</p>
            <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
            <p style="color: #777; font-size: 12px; text-align: center;">
                ' . date('Y-m-d H:i:s') . ' - Portal de Noticias
            </p>
        </div>
        ';
        
        $result = sendEmail($testEmail, $subject, $body);
        echo '<h1>Prueba de envío de correo</h1>';
        echo '<p>Destinatario: ' . htmlspecialchars($testEmail) . '</p>';
        echo '<p>Resultado: ' . ($result ? 'Correo enviado con éxito' : 'Error al enviar el correo') . '</p>';
        if (!$result) {
            echo '<p>Revisa la configuración de correo en el archivo config.php y asegúrate de que los datos SMTP sean correctos.</p>';
        }
        exit;
    } else {
        echo '<h1>Error</h1>';
        echo '<p>La función sendEmail() no existe</p>';
        exit;
    }
}

// Ejecutar diagnóstico
$diagnosticResults = runDiagnostics();

// Mostrar resultados
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Suscripción al Newsletter</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #2196F3;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
        }
        .message {
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 4px;
        }
        .message.success {
            background-color: #E8F5E9;
            color: #2E7D32;
        }
        .message.warning {
            background-color: #FFF8E1;
            color: #F57F17;
        }
        .message.error {
            background-color: #FFEBEE;
            color: #C62828;
        }
        .section {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        code {
            font-family: Consolas, Monaco, 'Andale Mono', monospace;
            background-color: #f1f1f1;
            padding: 2px 5px;
            border-radius: 3px;
        }
        .btn {
            display: inline-block;
            background-color: #2196F3;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn:hover {
            background-color: #0b7dda;
        }
        form {
            margin-top: 20px;
        }
        input, button {
            padding: 8px 12px;
            margin-right: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 14px;
            color: #777;
        }
    </style>
</head>
<body>
    <h1>Diagnóstico de Suscripción al Newsletter</h1>
    
    <div class="section">
        <h2>Resultados del diagnóstico</h2>
        <?php foreach ($diagnosticResults['messages'] as $message): ?>
            <?php 
                $class = 'success';
                if (strpos($message, '❌') !== false) {
                    $class = 'error';
                } else if (strpos($message, '⚠️') !== false) {
                    $class = 'warning';
                }
            ?>
            <div class="message <?php echo $class; ?>"><?php echo $message; ?></div>
        <?php endforeach; ?>
    </div>
    
    <div class="section">
        <h2>Pruebas adicionales</h2>
        <p>Puedes realizar pruebas adicionales para verificar el correcto funcionamiento del sistema de suscripción:</p>
        
        <h3>Prueba de envío de correo</h3>
        <form action="check_subscribe.php" method="get">
            <input type="hidden" name="test" value="email">
            <input type="email" name="email" placeholder="Email de prueba" required>
            <button type="submit">Enviar correo de prueba</button>
        </form>
        
        <h3>Verificar suscripciones existentes</h3>
        <?php
        try {
            $db = Database::getInstance();
            $subscriptions = $db->fetchAll("SELECT id, email, name, status, confirmed, created_at FROM subscribers ORDER BY created_at DESC LIMIT 5");
            
            if (!empty($subscriptions)) {
                echo '<p>Últimas 5 suscripciones:</p>';
                echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
                echo '<tr><th>ID</th><th>Email</th><th>Nombre</th><th>Estado</th><th>Confirmado</th><th>Fecha</th></tr>';
                
                foreach ($subscriptions as $sub) {
                    echo '<tr>';
                    echo '<td>' . $sub['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($sub['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($sub['name'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($sub['status']) . '</td>';
                    echo '<td>' . ($sub['confirmed'] ? 'Sí' : 'No') . '</td>';
                    echo '<td>' . date('d/m/Y H:i', strtotime($sub['created_at'])) . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            } else {
                echo '<p>No hay suscripciones en la base de datos.</p>';
            }
        } catch (Exception $e) {
            echo '<p class="message error">Error al consultar suscripciones: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>Soluciones comunes</h2>
        
        <h3>Problema de CSRF Token</h3>
        <p>Si el token CSRF no funciona correctamente, verifica que la sesión esté iniciada con <code>session_start()</code> al principio de tus archivos o en <code>functions.php</code>.</p>
        
        <h3>Problemas con envío de correo</h3>
        <p>Si los correos no se envían, asegúrate de que la configuración SMTP en <code>config.php</code> es correcta. Puedes necesitar configurar un servicio como Gmail, SendGrid o Mailgun.</p>
        
        <h3>Problemas con redirecciones</h3>
        <p>Asegúrate de que la constante <code>SITE_URL</code> tiene el valor correcto y que la función <code>redirect()</code> está bien implementada.</p>
        
        <h3>Problemas con mensajes flash</h3>
        <p>Verifica que las funciones <code>setFlashMessage()</code> y <code>getFlashMessage()</code> están funcionando correctamente y que la sesión está iniciada.</p>
    </div>
    
    <div class="section">
        <h2>Formulario de prueba</h2>
        <p>Puedes probar el formulario de suscripción directamente desde aquí:</p>
        
        <form action="subscribe.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div style="margin-bottom: 10px;">
                <input type="text" name="name" placeholder="Nombre (opcional)" style="width: 300px;">
            </div>
            <div style="margin-bottom: 10px;">
                <input type="email" name="email" placeholder="Email" required style="width: 300px;">
            </div>
            <div style="margin-bottom: 10px;">
                <label><input type="checkbox" name="accept_terms" value="1" required> Acepto recibir noticias</label>
            </div>
            <button type="submit">Suscribirse</button>
        </form>
    </div>
    
    <div class="footer">
        <p>Esta página de diagnóstico es solo para uso administrativo. <a href="index.php">Volver al sitio</a></p>
    </div>
</body>
</html>