<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}

// Incluir funciones y conexión si no están incluidas
if (!function_exists('sanitize')) {
    require_once 'functions.php';
}
if (!class_exists('Database')) {
    require_once 'db_connection.php';
}

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Intenta autenticar a un usuario
     * @param string $username Nombre de usuario o email
     * @param string $password Contraseña
     * @return array|bool Datos del usuario o false si falla
     */
    public function login($username, $password) {
        // Sanitizar entradas
        $username = sanitize($username);
        
        // Determinar si es email o username
        $field = isValidEmail($username) ? 'email' : 'username';
        
        // Buscar usuario
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE $field = ? AND status = 'active'",
            [$username]
        );
        
        // Verificar si existe y la contraseña coincide
        if ($user && verifyPassword($password, $user['password'])) {
            // Eliminar datos sensibles
            unset($user['password']);
            
            // Registrar login
            $this->db->query(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            // Guardar en sesión
            $_SESSION['user'] = $user;
            
            // Registrar acción
            logAction('login', 'Login exitoso', $user['id']);
            
            return $user;
        }
        
        // Login fallido
        logAction('login_failed', "Intento fallido para $field: $username", 0);
        
        return false;
    }
    
    /**
     * Cierra la sesión del usuario actual
     */
    public function logout() {
        // Registrar acción si hay usuario
        if (isset($_SESSION['user'])) {
            logAction('logout', 'Logout exitoso', $_SESSION['user']['id']);
        }
        
        // Destruir sesión
        session_unset();
        session_destroy();
        
        // Iniciar nueva sesión para mensajes flash
        session_start();
        
        // Mensaje de éxito
        setFlashMessage('success', 'Has cerrado sesión correctamente');
    }
    
    /**
     * Registra un nuevo usuario
     * @param array $data Datos del usuario
     * @return array|bool Datos del usuario o false si falla
     */
    public function register($data) {
        // Validar datos
        if (empty($data['username']) || empty($data['email']) || empty($data['password']) || empty($data['name'])) {
            return false;
        }
        
        // Sanitizar datos
        $username = sanitize($data['username']);
        $email = sanitize($data['email']);
        $name = sanitize($data['name']);
        
        // Validar email
        if (!isValidEmail($email)) {
            return false;
        }
        
        // Verificar si ya existe
        $existing = $this->db->fetch(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
        
        if ($existing) {
            return false;
        }
        
        // Crear usuario
        $role = $data['role'] ?? 'subscriber';
        $bio = $data['bio'] ?? '';
        $passwordHash = hashPassword($data['password']);
        
        $this->db->query(
            "INSERT INTO users (username, password, email, name, bio, role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$username, $passwordHash, $email, $name, $bio, $role]
        );
        
        $userId = $this->db->lastInsertId();
        
        if ($userId) {
            // Obtener datos del usuario creado
            $user = $this->db->fetch(
                "SELECT * FROM users WHERE id = ?",
                [$userId]
            );
            
            // Eliminar datos sensibles
            unset($user['password']);
            
            // Registrar acción
            logAction('register', 'Registro exitoso', $userId);
            
            return $user;
        }
        
        return false;
    }
    
    /**
     * Verifica si el usuario actual tiene permisos
     * @param string|array $roles Roles permitidos
     * @return bool True si tiene permiso, false si no
     */
    public function checkPermission($roles) {
        return hasRole($roles);
    }
    
    /**
     * Redirecciona si no tiene permisos
     * @param string|array $roles Roles permitidos
     * @param string $redirect URL a redireccionar
     */
    public function requirePermission($roles, $redirect = 'login.php') {
        if (!$this->checkPermission($roles)) {
            setFlashMessage('error', 'No tienes permisos para acceder a esta página');
            redirect($redirect);
        }
    }
    
    /**
     * Envía un correo para resetear contraseña
     * @param string $email Email del usuario
     * @return bool True si se envió, false si no
     */
    public function forgotPassword($email) {
        // Validar email
        if (!isValidEmail($email)) {
            return false;
        }
        
        // Buscar usuario
        $user = $this->db->fetch(
            "SELECT id, name FROM users WHERE email = ? AND status = 'active'",
            [$email]
        );
        
        if (!$user) {
            return false;
        }
        
        // Generar token
        $token = generateToken();
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Guardar token
        $this->db->query(
            "INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())",
            [$user['id'], $token, $expires]
        );
        
        // Construir URL de reset
        $resetUrl = SITE_URL . '/reset-password.php?token=' . $token;
        
        // Construir mensaje
        $subject = 'Recuperar contraseña - ' . getSetting('site_name', 'Portal de Noticias');
        $body = '
            <p>Hola ' . $user['name'] . ',</p>
            <p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace para cambiarla:</p>
            <p><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>
            <p>Este enlace expirará en 24 horas.</p>
            <p>Si no solicitaste este cambio, puedes ignorar este correo.</p>
            <p>Saludos,<br>' . getSetting('site_name', 'Portal de Noticias') . '</p>
        ';
        
        // Enviar correo
        $sent = sendEmail($email, $subject, $body);
        
        if ($sent) {
            logAction('forgot_password', 'Solicitud de recuperación de contraseña', $user['id']);
        }
        
        return $sent;
    }
    
    /**
     * Verifica si un token de reset es válido
     * @param string $token Token a verificar
     * @return array|bool Datos del usuario o false si no es válido
     */
    public function verifyResetToken($token) {
        $result = $this->db->fetch(
            "SELECT pr.*, u.id as user_id, u.name, u.email 
             FROM password_resets pr
             JOIN users u ON pr.user_id = u.id
             WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()",
            [$token]
        );
        
        return $result ? $result : false;
    }
    
    /**
     * Cambia la contraseña de un usuario con token
     * @param string $token Token de reset
     * @param string $password Nueva contraseña
     * @return bool True si se cambió, false si no
     */
    public function resetPassword($token, $password) {
        // Verificar token
        $reset = $this->verifyResetToken($token);
        
        if (!$reset) {
            return false;
        }
        
        // Hashear nueva contraseña
        $passwordHash = hashPassword($password);
        
        // Actualizar contraseña
        $updated = $this->db->query(
            "UPDATE users SET password = ? WHERE id = ?",
            [$passwordHash, $reset['user_id']]
        );
        
        if ($updated) {
            // Marcar token como usado
            $this->db->query(
                "UPDATE password_resets SET used = 1, used_at = NOW() WHERE token = ?",
                [$token]
            );
            
            // Registrar acción
            logAction('reset_password', 'Contraseña restablecida con token', $reset['user_id']);
            
            return true;
        }
        
        return false;
    }
}
?>