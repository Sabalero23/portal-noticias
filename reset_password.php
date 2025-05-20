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
    setFlashMessage('error', 'Enlace de restablecimiento inválido');
    redirect('forgot_password.php');
}

// Sanitizar entradas
$token = sanitize($_GET['token']);
$email = sanitize($_GET['email']);

// Validar email
if (!isValidEmail($email)) {
    setFlashMessage('error', 'Dirección de email inválida');
    redirect('forgot_password.php');
}

// Buscar usuario
$db = Database::getInstance();
$user = $db->fetch(
    "SELECT id, name, username, status, reset_token, reset_token_expires 
     FROM users 
     WHERE email = ? AND reset_token = ? AND status = 'active'",
    [$email, $token]
);

// Verificar si existe
if (!$user) {
    setFlashMessage('error', 'Enlace de restablecimiento inválido o expirado');
    redirect('forgot_password.php');
}

// Verificar si el token ha expirado
$tokenExpires = strtotime($user['reset_token_expires']);
if ($tokenExpires < time()) {
    setFlashMessage('error', 'El enlace de restablecimiento ha expirado. Por favor, solicita uno nuevo.');
    redirect('forgot_password.php');
}

// Inicializar variables
$resetSuccess = false;
$resetError = '';
$newPassword = '';
$confirmPassword = '';

// Procesar formulario de restablecimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $resetError = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        // Obtener datos del formulario
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Validar nueva contraseña
        if (strlen($newPassword) < 8) {
            $resetError = 'La nueva contraseña debe tener al menos 8 caracteres.';
        }
        // Verificar que las contraseñas coincidan
        elseif ($newPassword !== $confirmPassword) {
            $resetError = 'Las contraseñas no coinciden.';
        }
        else {
            // Hash de la nueva contraseña
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Actualizar contraseña y limpiar token
            $result = $db->query(
                "UPDATE users 
                 SET password = ?, reset_token = NULL, reset_token_expires = NULL, updated_at = NOW() 
                 WHERE id = ?",
                [$hashedPassword, $user['id']]
            );
            
            if ($result) {
                $resetSuccess = true;
                
                // Enviar correo de notificación
                sendPasswordChangedEmail($email, $user['name'], $user['username']);
            } else {
                $resetError = 'Error al restablecer la contraseña. Por favor, intenta nuevamente.';
            }
        }
    }
}

/**
 * Envía un correo electrónico notificando el cambio de contraseña
 * 
 * @param string $email Email del usuario
 * @param string $name Nombre del usuario
 * @param string $username Nombre de usuario
 * @return bool True si se envió, false si no
 */
function sendPasswordChangedEmail($email, $name, $username) {
    // Nombre para mostrar
    $displayName = !empty($name) ? $name : $username;
    
    // Asunto del correo
    $subject = 'Tu contraseña ha sido cambiada - ' . getSetting('site_name', 'Portal de Noticias');
    
    // URL de login
    $loginUrl = SITE_URL . '/login.php';
    
    // Cuerpo del correo
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="' . SITE_URL . '/' . getSetting('logo', 'assets/img/logo.png') . '" alt="Logo" style="max-width: 200px;">
        </div>
        
        <h2 style="color: #333;">Tu contraseña ha sido cambiada</h2>
        
        <p>Hola ' . htmlspecialchars($displayName) . ',</p>
        
        <p>Tu contraseña en ' . getSetting('site_name', 'Portal de Noticias') . ' ha sido cambiada exitosamente.</p>
        
        <p>Ya puedes iniciar sesión con tu nueva contraseña.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $loginUrl . '" style="background-color: #2196F3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Iniciar sesión</a>
        </div>
        
        <p>Si no has sido tú quien cambió la contraseña, por favor contacta con nosotros inmediatamente.</p>
        
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
$pageTitle = 'Restablecer contraseña - ' . getSetting('site_name', 'Portal de Noticias');
$metaDescription = 'Crea una nueva contraseña para tu cuenta en ' . getSetting('site_name', 'Portal de Noticias');

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
                <li class="breadcrumb-item active" aria-current="page">Restablecer contraseña</li>
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
                    <h1 class="h4 mb-0">Restablecer contraseña</h1>
                </div>
                <div class="card-body p-4">
                    <?php if ($resetSuccess): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">¡Contraseña restablecida!</h4>
                        <p>Tu contraseña ha sido actualizada correctamente.</p>
                        <p class="mb-0">Ahora puedes iniciar sesión con tu nueva contraseña.</p>
                    </div>
                    <div class="text-center mt-4">
                        <a href="login.php?password_reset=success" class="btn btn-primary">Iniciar sesión</a>
                    </div>
                    <?php else: ?>
                    
                    <?php if (!empty($resetError)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $resetError; ?>
                    </div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-4">Por favor, introduce tu nueva contraseña.</p>
                    
                    <form action="reset_password.php?token=<?php echo urlencode($token); ?>&email=<?php echo urlencode($email); ?>" method="post" id="resetPasswordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nueva contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Mostrar/ocultar contraseña">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                            <div class="form-text">La contraseña debe tener al menos 8 caracteres.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="reset_password" class="btn btn-primary">Restablecer contraseña</button>
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
    // Mostrar/ocultar contraseña
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('new_password');
    const togglePasswordIcon = document.getElementById('togglePasswordIcon');
    
    if (togglePassword && passwordInput && togglePasswordIcon) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Cambiar el ícono
            togglePasswordIcon.classList.toggle('fa-eye');
            togglePasswordIcon.classList.toggle('fa-eye-slash');
        });
    }
    
    // Validación del formulario
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Validar nueva contraseña
            const newPassword = document.getElementById('new_password');
            if (!newPassword.value || newPassword.value.length < 8) {
                isValid = false;
                newPassword.classList.add('is-invalid');
            } else {
                newPassword.classList.remove('is-invalid');
            }
            
            // Validar confirmación de contraseña
            const confirmPassword = document.getElementById('confirm_password');
            if (!confirmPassword.value || confirmPassword.value !== newPassword.value) {
                isValid = false;
                confirmPassword.classList.add('is-invalid');
            } else {
                confirmPassword.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                event.preventDefault();
                
                // Mostrar mensaje de error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger mt-3';
                errorDiv.textContent = 'Por favor, corrige los errores en el formulario antes de continuar.';
                
                const existingError = resetPasswordForm.querySelector('.alert');
                if (existingError) {
                    resetPasswordForm.removeChild(existingError);
                }
                
                resetPasswordForm.prepend(errorDiv);
            }
        });
        
        // Eliminar validación visual al cambiar el valor
        const inputs = resetPasswordForm.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
    }
});
</script>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>