<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}

// Incluir configuración si no está incluida
if (!defined('SITE_URL')) {
    require_once 'config.php';
}

/**
 * Sanitiza una entrada
 * @param string $input Entrada para sanitizar
 * @return string Entrada sanitizada
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Valida una dirección de correo electrónico
 * @param string $email Correo para validar
 * @return bool True si es válido, false si no
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Genera una contraseña segura hashed
 * @param string $password Contraseña a hashear
 * @return string Contraseña hasheada
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifica una contraseña
 * @param string $password Contraseña a verificar
 * @param string $hash Hash almacenado
 * @return bool True si coincide, false si no
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Genera un slug para URLs
 * @param string $string Cadena a convertir
 * @return string Slug generado
 */
function generateSlug($string) {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $string), '-'));
    // Eliminar caracteres especiales
    $slug = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'], ['a', 'e', 'i', 'o', 'u', 'n', 'u'], $slug);
    // Eliminar guiones dobles
    $slug = preg_replace('/-+/', '-', $slug);
    return $slug;
}

/**
 * Genera un token seguro
 * @param int $length Longitud del token
 * @return string Token generado
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Redirecciona a una URL
 * @param string $url URL a redireccionar
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Obtiene la URL actual
 * @return string URL actual
 */
function getCurrentUrl() {
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
}

/**
 * Formatea una fecha
 * @param string $date Fecha a formatear
 * @param string $format Formato deseado
 * @return string Fecha formateada
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    $dateTime = new DateTime($date);
    return $dateTime->format($format);
}

/**
 * Calcula tiempo transcurrido de forma legible
 * @param string $date Fecha a calcular
 * @return string Tiempo transcurrido
 */
function timeAgo($date) {
    $timestamp = strtotime($date);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return "hace unos segundos";
    } elseif ($difference < 3600) {
        return "hace " . round($difference / 60) . " minutos";
    } elseif ($difference < 86400) {
        return "hace " . round($difference / 3600) . " horas";
    } elseif ($difference < 604800) {
        return "hace " . round($difference / 86400) . " días";
    } elseif ($difference < 2592000) {
        return "hace " . round($difference / 604800) . " semanas";
    } elseif ($difference < 31536000) {
        return "hace " . round($difference / 2592000) . " meses";
    } else {
        return "hace " . round($difference / 31536000) . " años";
    }
}

/**
 * Limita una cadena a cierto número de caracteres
 * @param string $string Cadena a limitar
 * @param int $length Longitud máxima
 * @param string $append Texto a añadir al final
 * @return string Cadena limitada
 */
function truncateString($string, $length = 100, $append = '...') {
    if (strlen($string) > $length) {
        $string = substr($string, 0, $length);
        $string = substr($string, 0, strrpos($string, ' '));
        $string .= $append;
    }
    return $string;
}

/**
 * Limpia una cadena para uso seguro en JavaScript
 * @param string $string Cadena a limpiar
 * @return string Cadena limpia
 */
function escapeJS($string) {
    return json_encode($string);
}

/**
 * Verifica si el usuario actual tiene cierto rol
 * @param array|string $roles Roles a verificar
 * @return bool True si tiene el rol, false si no
 */
function hasRole($roles) {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['user']['role'], $roles);
    } else {
        return $_SESSION['user']['role'] === $roles;
    }
}

/**
 * Verifica si el usuario está logueado
 * @return bool True si está logueado, false si no
 */
function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

/**
 * Obtiene una configuración de la base de datos
 * @param string $key Clave de configuración
 * @param mixed $default Valor por defecto
 * @return mixed Valor de configuración
 */
function getSetting($key, $default = null) {
    $db = Database::getInstance();
    $result = $db->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    
    if ($result && isset($result['setting_value'])) {
        return $result['setting_value'];
    }
    
    return $default;
}

/**
 * Verifica si un valor de configuración es válido (no es el valor por defecto)
 * @param string $key Clave de configuración
 * @param string $defaultPattern Patrón para identificar valores por defecto
 * @return bool True si es válido, false si no
 */
function isValidSetting($key, $defaultPattern = '_here') {
    $value = getSetting($key);
    
    // Verificar si está vacío
    if (empty($value)) {
        return false;
    }
    
    // Verificar si contiene el patrón por defecto (ej: your_api_key_here)
    if (strpos($value, $defaultPattern) !== false) {
        return false;
    }
    
    return true;
}

/**
 * Valida tipo de archivo
 * @param string $filename Nombre del archivo
 * @return bool True si es válido, false si no
 */
function isValidFileType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_EXTENSIONS);
}

/**
 * Sube un archivo al servidor
 * @param array $file Archivo ($_FILES)
 * @param string $destination Directorio destino
 * @return string|false Ruta al archivo o false si falla
 */
function uploadFile($file, $destination) {
    // Validar archivo
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    
    // Validar tamaño
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return false;
    }
    
    // Validar tipo
    if (!isValidFileType($file['name'])) {
        return false;
    }
    
    // Crear directorio si no existe
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Generar nombre único
    $filename = generateToken(16) . '_' . sanitize($file['name']);
    $filepath = $destination . '/' . $filename;
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filepath;
    }
    
    return false;
}

/**
 * Muestra un mensaje flash
 * @param string $type Tipo de mensaje (success, error, warning, info)
 * @param string $message Mensaje a mostrar
 */
function setFlashMessage($type, $message) {
    if (!session_id()) {
        session_start();
    }
    
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Obtiene y elimina un mensaje flash
 * @return array|null Mensaje flash o null si no hay
 */
function getFlashMessage() {
    if (!session_id()) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Registra una acción en el log
 * @param string $action Acción realizada
 * @param string $details Detalles de la acción
 * @param int $userId ID del usuario (0 si es sistema)
 */
function logAction($action, $details = '', $userId = 0) {
    // Implementar según necesidades
    // Se podría guardar en base de datos o archivo
}

/**
 * Verifica el token CSRF
 * @param string $token Token enviado
 * @return bool True si es válido, false si no
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;
}

/**
 * Genera un token CSRF
 * @return string Token generado
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Envía un correo electrónico usando PHPMailer o mail()
 * @param string $to Destinatario
 * @param string $subject Asunto
 * @param string $body Cuerpo del mensaje
 * @param array $headers Cabeceras adicionales (no usado con PHPMailer)
 * @return bool True si se envió, false si no
 */
function sendEmail($to, $subject, $body, $headers = []) {
    try {
        // Incluir mailer si no está cargado
        if (!class_exists('Mailer')) {
            require_once BASE_PATH . '/includes/mailer.php';
        }
        
        // Verificar si SMTP está habilitado
        $smtpEnabled = getSetting('enable_smtp', '0') === '1';
        
        if ($smtpEnabled) {
            // Usar PHPMailer con SMTP
            return sendEmailSMTP($to, $subject, $body);
        } else {
            // Usar función mail() original como fallback
            $defaultHeaders = [
                'From' => MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
                'Content-Type' => 'text/html; charset=UTF-8',
                'MIME-Version' => '1.0'
            ];
            
            $headers = array_merge($defaultHeaders, $headers);
            $headerString = '';
            
            foreach ($headers as $key => $value) {
                $headerString .= "$key: $value\r\n";
            }
            
            // Registrar intento (para debug)
            if (DEBUG_MODE) {
                error_log("sendEmail: Usando mail() - To: $to, Subject: $subject");
            }
            
            return mail($to, $subject, $body, $headerString);
        }
    } catch (Exception $e) {
        // Log del error
        error_log("Error en sendEmail: " . $e->getMessage());
        
        // Intentar fallback con mail() si PHPMailer falla
        if ($smtpEnabled) {
            try {
                $headers = [
                    'From' => MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'MIME-Version' => '1.0'
                ];
                
                $headerString = '';
                foreach ($headers as $key => $value) {
                    $headerString .= "$key: $value\r\n";
                }
                
                return mail($to, $subject, $body, $headerString);
            } catch (Exception $e2) {
                error_log("Error en fallback mail(): " . $e2->getMessage());
                return false;
            }
        }
        
        return false;
    }
}

/**
 * Genera breadcrumbs
 * @param array $items Items de breadcrumb [label => url]
 * @return string HTML de breadcrumbs
 */
function generateBreadcrumbs($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    $i = 0;
    $count = count($items);
    
    foreach ($items as $label => $url) {
        $i++;
        
        if ($i === $count) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . $url . '">' . $label . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    
    return $html;
}

/**
 * Decodifica entidades HTML
 * 
 * @param string $text Texto a decodificar
 * @return string Texto decodificado
 */
function decodeHtmlEntities($text) {
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Función wrapper para autenticar usuario (compatibilidad con login.php)
 * @param string $username Nombre de usuario o email
 * @param string $password Contraseña
 * @param bool $rememberMe Si recordar la sesión
 * @return bool|string True si éxito, mensaje de error si falla
 */
function loginUser($username, $password, $rememberMe = false) {
    try {
        // Crear instancia de Auth
        $auth = new Auth();
        
        // Intentar login
        $user = $auth->login($username, $password);
        
        if ($user) {
            // Login exitoso
            
            // Manejar "recordar sesión" si se solicita
            if ($rememberMe) {
                // Crear cookie segura para recordar sesión
                $token = generateToken();
                $expire = time() + COOKIE_EXPIRE; // 30 días por defecto
                
                // Guardar token en base de datos (tabla users o nueva tabla remember_tokens)
                $db = Database::getInstance();
                $db->query(
                    "UPDATE users SET remember_token = ?, remember_expires = FROM_UNIXTIME(?) WHERE id = ?",
                    [$token, $expire, $user['id']]
                );
                
                // Establecer cookie
                setcookie('remember_token', $token, $expire, '/', '', true, true);
            }
            
            return true;
        } else {
            // Login fallido
            return 'Usuario o contraseña incorrectos';
        }
        
    } catch (Exception $e) {
        // Error en el proceso
        error_log('Error en loginUser: ' . $e->getMessage());
        return 'Error interno. Por favor, intenta nuevamente.';
    }
}

/**
 * Función wrapper para logout (compatibilidad)
 * @return void
 */
function logoutUser() {
    try {
        $auth = new Auth();
        $auth->logout();
        
        // Limpiar cookie de "recordar"
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            
            // Limpiar de base de datos
            if (isset($_SESSION['user']['id'])) {
                $db = Database::getInstance();
                $db->query(
                    "UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = ?",
                    [$_SESSION['user']['id']]
                );
            }
        }
        
    } catch (Exception $e) {
        error_log('Error en logoutUser: ' . $e->getMessage());
    }
}

/**
 * Función para verificar cookie de "recordar sesión"
 * Se debe llamar al inicio de cada página
 * @return void
 */
function checkRememberLogin() {
    // Solo verificar si no hay sesión activa
    if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
        try {
            $token = $_COOKIE['remember_token'];
            $db = Database::getInstance();
            
            // Buscar token válido
            $user = $db->fetch(
                "SELECT * FROM users 
                 WHERE remember_token = ? 
                 AND remember_expires > NOW() 
                 AND status = 'active'",
                [$token]
            );
            
            if ($user) {
                // Restaurar sesión
                unset($user['password']);
                $_SESSION['user'] = $user;
                
                // Actualizar last_login
                $db->query(
                    "UPDATE users SET last_login = NOW() WHERE id = ?",
                    [$user['id']]
                );
                
                // Extender cookie
                $newExpire = time() + COOKIE_EXPIRE;
                setcookie('remember_token', $token, $newExpire, '/', '', true, true);
                
                // Actualizar expiración en BD
                $db->query(
                    "UPDATE users SET remember_expires = FROM_UNIXTIME(?) WHERE id = ?",
                    [$newExpire, $user['id']]
                );
            } else {
                // Token inválido, limpiar cookie
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }
            
        } catch (Exception $e) {
            error_log('Error en checkRememberLogin: ' . $e->getMessage());
            // Limpiar cookie en caso de error
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }
}

/**
 * htmlspecialchars que maneja valores NULL
 * @param mixed $value Valor a escapar
 * @param string $default Valor por defecto si es NULL
 * @return string Valor escapado
 */
function escapeHtml($value, $default = '') {
    if ($value === null) {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>