<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Obtener categorías para el menú
$db = Database::getInstance();
$categories = $db->fetchAll("SELECT id, name, slug FROM categories ORDER BY name");

// Si el usuario ya está logueado, redirigir a la página principal
if (isLoggedIn()) {
    redirect('index.php');
}

// Inicializar variables
$resetRequested = false;
$resetError = '';
$email = '';

// Procesar solicitud de restablecimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $resetError = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        // Obtener email
        $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
        
        // Validar email
        if (empty($email) || !isValidEmail($email)) {
            $resetError = 'Por favor, introduce una dirección de correo electrónico válida.';
        } else {
            // Verificar si existe el usuario
            $db = Database::getInstance();
            $user = $db->fetch(
                "SELECT id, name, username, status FROM users WHERE email = ?",
                [$email]
            );
            
            if (!$user) {
                // Por seguridad, no revelar si el email existe o no
                $resetRequested = true;
            } else {
                // Verificar si la cuenta está activa
                if ($user['status'] !== 'active') {
                    $resetError = 'Esta cuenta no está activa. Por favor, confirma tu cuenta primero o contacta al administrador.';
                } else {
                    // Generar token de restablecimiento
                    $resetToken = generateToken();
                    $expireTime = time() + (60 * 60); // 1 hora
                    
                    // Guardar token en la base de datos
                    $result = $db->query(
                        "UPDATE users SET reset_token = ?, reset_token_expires = FROM_UNIXTIME(?), updated_at = NOW() WHERE id = ?",
                        [$resetToken, $expireTime, $user['id']]
                    );
                    
                    if ($result) {
                        // Enviar email con enlace para restablecer
                        $emailSent = sendPasswordResetEmail($email, $user['name'], $user['username'], $resetToken);
                        
                        if ($emailSent) {
                            $resetRequested = true;
                        } else {
                            $resetError = 'Error al enviar el correo electrónico. Por favor, intenta nuevamente o contacta al administrador.';
                        }
                    } else {
                        $resetError = 'Error al procesar tu solicitud. Por favor, intenta nuevamente.';
                    }
                }
            }
        }
    }
}

/**
 * Envía un correo electrónico con enlace para restablecer contraseña
 * 
 * @param string $email Email del usuario
 * @param string $name Nombre del usuario
 * @param string $username Nombre de usuario
 * @param string $token Token de restablecimiento
 * @return bool True si se envió, false si no
 */
function sendPasswordResetEmail($email, $name, $username, $token) {
    // Nombre para mostrar
    $displayName = !empty($name) ? $name : $username;
    
    // Construir URL de restablecimiento
    $resetUrl = SITE_URL . '/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email);
    
    // Asunto del correo
    $subject = 'Restablecimiento de contraseña - ' . getSetting('site_name', 'Portal de Noticias');
    
    // Cuerpo del correo
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="' . SITE_URL . '/' . getSetting('logo', 'assets/img/logo.png') . '" alt="Logo" style="max-width: 200px;">
        </div>
        
        <h2 style="color: #333;">Restablecimiento de contraseña</h2>
        
        <p>Hola ' . htmlspecialchars($displayName) . ',</p>
        
        <p>Has solicitado restablecer tu contraseña en ' . getSetting('site_name', 'Portal de Noticias') . '.</p>
        
        <p>Para crear una nueva contraseña, haz clic en el siguiente botón:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $resetUrl . '" style="background-color: #2196F3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Restablecer contraseña</a>
        </div>
        
        <p>O copia y pega el siguiente enlace en tu navegador:</p>
        <p style="word-break: break-all; color: #666;">' . $resetUrl . '</p>
        
        <p><strong>Nota:</strong> Este enlace es válido por 1 hora.</p>
        
        <p>Si no has solicitado este restablecimiento, puedes ignorar este mensaje y tu contraseña seguirá siendo la misma.</p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        
        <p style="color: #777; font-size: 12px; text-align: center;">
            &copy; ' . date('Y') . ' ' . getSetting('site_name', 'Portal de Noticias') . '. Todos los derechos reservados.
        </p>
    </div>
    ';
    
    // Enviar el correo
    return sendEmail($email, $subject, $body);
}

// Configuración para la página
$pageTitle = 'Recuperar contraseña - ' . getSetting('site_name', 'Portal de Noticias');
$metaDescription = 'Restablece tu contraseña en ' . getSetting('site_name', 'Portal de Noticias');

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="login.php">Iniciar sesión</a></li>
                <li class="breadcrumb-item active" aria-current="page">Recuperar contraseña</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Contenido Principal -->
<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h1 class="h4 mb-0">Recuperar contraseña</h1>
                </div>
                <div class="card-body p-4">
                    <?php if ($resetRequested): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">¡Solicitud enviada!</h4>
                        <p>Si la dirección de correo electrónico <strong><?php echo htmlspecialchars($email); ?></strong> está asociada a una cuenta, recibirás un enlace para restablecer tu contraseña.</p>
                        <p class="mb-0">Por favor, revisa tu bandeja de entrada (y carpeta de spam) y sigue las instrucciones del correo.</p>
                    </div>
                    <div class="text-center mt-4">
                        <a href="login.php" class="btn btn-primary">Volver a iniciar sesión</a>
                    </div>
                    <?php else: ?>
                    
                    <?php if (!empty($resetError)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $resetError; ?>
                    </div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-4">Ingresa tu dirección de correo electrónico para recibir instrucciones sobre cómo restablecer tu contraseña.</p>
                    
                    <form action="forgot_password.php" method="post" id="resetForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="request_reset" class="btn btn-primary">Enviar instrucciones</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light text-center py-3">
                    <p class="mb-0">
                        <a href="login.php" class="text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Volver a iniciar sesión</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript para el formulario -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación del formulario
    const resetForm = document.getElementById('resetForm');
    
    if (resetForm) {
        resetForm.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Validar correo electrónico
            const email = document.getElementById('email');
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.value.trim() || !emailPattern.test(email.value.trim())) {
                isValid = false;
                email.classList.add('is-invalid');
            } else {
                email.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                event.preventDefault();
                
                // Mostrar mensaje de error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger mt-3';
                errorDiv.textContent = 'Por favor, introduce una dirección de correo electrónico válida.';
                
                const existingError = resetForm.querySelector('.alert');
                if (existingError) {
                    resetForm.removeChild(existingError);
                }
                
                resetForm.prepend(errorDiv);
            }
        });
        
        // Eliminar validación visual al cambiar el valor
        const email = document.getElementById('email');
        if (email) {
            email.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        }
    }
});
</script>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>