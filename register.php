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
$registrationError = '';
$registrationSuccess = false;
$formData = [
    'username' => '',
    'email' => '',
    'name' => '',
    'password' => '',
    'confirm_password' => ''
];

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $registrationError = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        // Obtener datos del formulario
        $formData = [
            'username' => isset($_POST['username']) ? sanitize($_POST['username']) : '',
            'email' => isset($_POST['email']) ? sanitize($_POST['email']) : '',
            'name' => isset($_POST['name']) ? sanitize($_POST['name']) : '',
            'password' => isset($_POST['password']) ? $_POST['password'] : '',
            'confirm_password' => isset($_POST['confirm_password']) ? $_POST['confirm_password'] : ''
        ];
        
        // Validar campos obligatorios
        if (empty($formData['username']) || empty($formData['email']) || empty($formData['name']) || 
            empty($formData['password']) || empty($formData['confirm_password'])) {
            $registrationError = 'Por favor, completa todos los campos obligatorios.';
        } 
        // Validar nombre de usuario (solo alfanumérico y guiones)
        elseif (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $formData['username'])) {
            $registrationError = 'El nombre de usuario debe tener entre 3 y 20 caracteres y sólo puede contener letras, números, guiones y guiones bajos.';
        }
        // Validar correo electrónico
        elseif (!isValidEmail($formData['email'])) {
            $registrationError = 'Por favor, introduce una dirección de correo electrónico válida.';
        }
        // Validar nombre completo
        elseif (strlen($formData['name']) < 3 || strlen($formData['name']) > 100) {
            $registrationError = 'El nombre completo debe tener entre 3 y 100 caracteres.';
        }
        // Validar contraseña
        elseif (strlen($formData['password']) < 8) {
            $registrationError = 'La contraseña debe tener al menos 8 caracteres.';
        }
        // Validar que las contraseñas coincidan
        elseif ($formData['password'] !== $formData['confirm_password']) {
            $registrationError = 'Las contraseñas no coinciden.';
        }
        // Validar términos y privacidad
        elseif (!isset($_POST['agree_terms']) || $_POST['agree_terms'] !== '1') {
            $registrationError = 'Debes aceptar los términos y condiciones y la política de privacidad.';
        }
        else {
            // Verificar si el nombre de usuario ya existe
            $db = Database::getInstance();
            $existingUser = $db->fetch(
                "SELECT id FROM users WHERE username = ?",
                [$formData['username']]
            );
            
            if ($existingUser) {
                $registrationError = 'El nombre de usuario ya está en uso. Por favor, elige otro.';
            } else {
                // Verificar si el correo electrónico ya existe
                $existingEmail = $db->fetch(
                    "SELECT id FROM users WHERE email = ?",
                    [$formData['email']]
                );
                
                if ($existingEmail) {
                    $registrationError = 'La dirección de correo electrónico ya está registrada. Por favor, utiliza otra.';
                } else {
                    // Todo está bien, proceder con el registro
                    
                    // Hash de la contraseña
                    $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
                    
                    // Generar token de confirmación
                    $confirmationToken = generateToken();
                    
                    // Insertar nuevo usuario
                    $result = $db->query(
                        "INSERT INTO users (username, password, email, name, role, status, confirmation_token, created_at)
                        VALUES (?, ?, ?, ?, 'subscriber', 'inactive', ?, NOW())",
                        [$formData['username'], $hashedPassword, $formData['email'], $formData['name'], $confirmationToken]
                    );
                    
                    if ($result) {
                        // Enviar correo de confirmación
                        $confirmationSuccess = sendConfirmationEmail($formData['email'], $formData['name'], $confirmationToken);
                        
                        if ($confirmationSuccess) {
                            // Limpiar formulario
                            $formData = [
                                'username' => '',
                                'email' => '',
                                'name' => '',
                                'password' => '',
                                'confirm_password' => ''
                            ];
                            
                            $registrationSuccess = true;
                        } else {
                            $registrationError = 'Tu cuenta se ha creado, pero hubo un problema al enviar el correo de confirmación. Por favor, contacta al administrador.';
                        }
                    } else {
                        $registrationError = 'Error al registrar el usuario. Por favor, intenta nuevamente.';
                    }
                }
            }
        }
    }
}

/**
 * Envía un correo electrónico de confirmación al usuario registrado
 * 
 * @param string $email Correo electrónico del usuario
 * @param string $name Nombre completo del usuario
 * @param string $token Token de confirmación
 * @return bool True si se envió el correo correctamente, false en caso contrario
 */
function sendConfirmationEmail($email, $name, $token) {
    // DEBUG: Activar temporalmente
    $debugMode = true; // Cambiar a false en producción
    
    // Generar URL de confirmación
    $confirmUrl = SITE_URL . '/confirm_account.php?token=' . urlencode($token) . '&email=' . urlencode($email);
    
    // Asunto del correo
    $subject = 'Confirma tu cuenta en ' . getSetting('site_name', 'Portal de Noticias');
    
    // Cuerpo del correo en HTML
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="' . SITE_URL . '/' . getSetting('logo', 'assets/img/logo.png') . '" alt="Logo" style="max-width: 200px;">
        </div>
        
        <h2 style="color: #333;">¡Hola ' . htmlspecialchars($name) . '!</h2>
        
        <p>Gracias por registrarte en ' . getSetting('site_name', 'Portal de Noticias') . '. Para activar tu cuenta, por favor haz clic en el siguiente botón:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $confirmUrl . '" style="background-color: #2196F3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Confirmar mi cuenta</a>
        </div>
        
        <p>O copia y pega el siguiente enlace en tu navegador:</p>
        <p style="word-break: break-all; color: #666;">' . $confirmUrl . '</p>
        
        <p>Si no has solicitado este registro, puedes ignorar este mensaje.</p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        
        <p style="color: #777; font-size: 12px; text-align: center;">
            &copy; ' . date('Y') . ' ' . getSetting('site_name', 'Portal de Noticias') . '. Todos los derechos reservados.
        </p>
    </div>
    ';
    
    // DEBUG: Información antes del envío
    if ($debugMode) {
        error_log("=== DEBUG EMAIL CONFIRMATION ===");
        error_log("To: $email");
        error_log("Subject: $subject");
        error_log("SMTP Enabled: " . getSetting('enable_smtp', '0'));
        error_log("SMTP Host: " . getSetting('smtp_host', 'not configured'));
        error_log("=================================");
    }
    
    // Enviar correo
    $result = sendEmail($email, $subject, $body);
    
    // DEBUG: Resultado del envío
    if ($debugMode) {
        error_log("=== EMAIL RESULT ===");
        error_log("Result: " . ($result ? 'SUCCESS' : 'FAILED'));
        if (!$result) {
            error_log("Mail function exists: " . (function_exists('mail') ? 'YES' : 'NO'));
            error_log("Mailer class exists: " . (class_exists('Mailer') ? 'YES' : 'NO'));
        }
        error_log("===================");
    }
    
    return $result;
}

// Configuración para la página
$pageTitle = 'Registro - ' . getSetting('site_name', 'Portal de Noticias');
$metaDescription = 'Regístrate en ' . getSetting('site_name', 'Portal de Noticias') . ' y accede a contenido exclusivo.';

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Registro</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Contenido Principal -->
<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h1 class="h4 mb-0">Crear una cuenta</h1>
                </div>
                <div class="card-body p-4">
                    <?php if ($registrationSuccess): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">¡Registro exitoso!</h4>
                        <p>Tu cuenta ha sido creada correctamente. Hemos enviado un correo electrónico a <strong><?php echo htmlspecialchars($formData['email']); ?></strong> con un enlace de confirmación.</p>
                        <p class="mb-0">Por favor, revisa tu bandeja de entrada (y carpeta de spam) para activar tu cuenta.</p>
                        <hr>
                        <p class="mb-0">¿Ya has confirmado tu cuenta? <a href="login.php">Iniciar sesión</a></p>
                    </div>
                    <?php else: ?>
                    
                    <?php if (!empty($registrationError)): ?>
                    <div class="alert alert-danger">
                        <?php echo $registrationError; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form action="register.php<?php echo isset($_GET['redirect_to']) ? '?redirect_to=' . urlencode($_GET['redirect_to']) : ''; ?>" method="post" id="registerForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Nombre de usuario *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($formData['username']); ?>" required>
                            </div>
                            <div class="form-text">Entre 3 y 20 caracteres, solo letras, números y guiones.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre completo *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Mostrar/ocultar contraseña">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                            <div class="form-text">Al menos 8 caracteres.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmar contraseña *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="agree_terms" name="agree_terms" value="1" required>
                            <label class="form-check-label" for="agree_terms">
                                Acepto los <a href="terms.php" target="_blank">términos y condiciones</a> y la <a href="privacy.php" target="_blank">política de privacidad</a> *
                            </label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="register" class="btn btn-primary">Crear cuenta</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light text-center py-3">
                    <p class="mb-0">¿Ya tienes una cuenta? <a href="login.php<?php echo isset($_GET['redirect_to']) ? '?redirect_to=' . urlencode($_GET['redirect_to']) : ''; ?>" class="text-decoration-none">Iniciar sesión</a></p>
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
    const passwordInput = document.getElementById('password');
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
    const registerForm = document.getElementById('registerForm');
    
    if (registerForm) {
        registerForm.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Validar nombre de usuario
            const username = document.getElementById('username');
            if (!username.value.trim() || !/^[a-zA-Z0-9_-]{3,20}$/.test(username.value.trim())) {
                isValid = false;
                username.classList.add('is-invalid');
            } else {
                username.classList.remove('is-invalid');
            }
            
            // Validar correo electrónico
            const email = document.getElementById('email');
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.value.trim() || !emailPattern.test(email.value.trim())) {
                isValid = false;
                email.classList.add('is-invalid');
            } else {
                email.classList.remove('is-invalid');
            }
            
            // Validar nombre completo
            const name = document.getElementById('name');
            if (!name.value.trim() || name.value.trim().length < 3 || name.value.trim().length > 100) {
                isValid = false;
                name.classList.add('is-invalid');
            } else {
                name.classList.remove('is-invalid');
            }
            
            // Validar contraseña
            const password = document.getElementById('password');
            if (!password.value || password.value.length < 8) {
                isValid = false;
                password.classList.add('is-invalid');
            } else {
                password.classList.remove('is-invalid');
            }
            
            // Validar confirmación de contraseña
            const confirmPassword = document.getElementById('confirm_password');
            if (!confirmPassword.value || confirmPassword.value !== password.value) {
                isValid = false;
                confirmPassword.classList.add('is-invalid');
            } else {
                confirmPassword.classList.remove('is-invalid');
            }
            
            // Validar términos
            const agreeTerms = document.getElementById('agree_terms');
            if (!agreeTerms.checked) {
                isValid = false;
                agreeTerms.classList.add('is-invalid');
            } else {
                agreeTerms.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                event.preventDefault();
                
                // Mostrar mensaje de error general
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger mt-3';
                errorDiv.textContent = 'Por favor, corrige los errores en el formulario antes de continuar.';
                
                const existingError = registerForm.querySelector('.alert');
                if (existingError) {
                    registerForm.removeChild(existingError);
                }
                
                registerForm.prepend(errorDiv);
                
                // Desplazarse al inicio del formulario
                window.scrollTo({ top: registerForm.offsetTop - 100, behavior: 'smooth' });
            }
        });
        
        // Eliminar validación visual al cambiar el valor
        const inputs = registerForm.querySelectorAll('input');
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