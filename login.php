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
$loginError = '';
$username = '';
$rememberMe = false;

// Procesar formulario de inicio de sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $loginError = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        // Obtener datos del formulario
        $username = isset($_POST['username']) ? sanitize($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';
        
        // Validar campos
        if (empty($username) || empty($password)) {
            $loginError = 'Por favor, completa todos los campos.';
        } else {
            // Intentar iniciar sesión
            $result = loginUser($username, $password, $rememberMe);
            
            if ($result === true) {
                // Redirigir según 'redirect_to' o a la página principal
                $redirectTo = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : 'index.php';
                redirect($redirectTo);
            } else {
                $loginError = $result; // Mensaje de error
            }
        }
    }
}

// Configuración para la página
$pageTitle = 'Iniciar Sesión - ' . getSetting('site_name', 'Portal de Noticias');
$metaDescription = 'Inicia sesión en tu cuenta de ' . getSetting('site_name', 'Portal de Noticias');

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Iniciar Sesión</li>
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
                    <h1 class="h4 mb-0">Iniciar Sesión</h1>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($loginError)): ?>
                    <div class="alert alert-danger">
                        <?php echo $loginError; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['registered']) && $_GET['registered'] === 'success'): ?>
                    <div class="alert alert-success">
                        ¡Registro exitoso! Ahora puedes iniciar sesión con tus credenciales.
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['password_reset']) && $_GET['password_reset'] === 'success'): ?>
                    <div class="alert alert-success">
                        Tu contraseña ha sido restablecida exitosamente. Ahora puedes iniciar sesión con tu nueva contraseña.
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
                    <div class="alert alert-success">
                        Has cerrado sesión exitosamente.
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['session_expired'])): ?>
                    <div class="alert alert-warning">
                        Tu sesión ha expirado. Por favor, inicia sesión nuevamente.
                    </div>
                    <?php endif; ?>
                    
                    <form action="login.php<?php echo isset($_GET['redirect_to']) ? '?redirect_to=' . urlencode($_GET['redirect_to']) : ''; ?>" method="post" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario o correo electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Mostrar/ocultar contraseña">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" value="1" <?php echo $rememberMe ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="remember_me">Mantener sesión iniciada</label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="login" class="btn btn-primary">Iniciar Sesión</button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <a href="forgot_password.php" class="text-decoration-none">¿Olvidaste tu contraseña?</a>
                    </div>
                </div>
                <div class="card-footer bg-light text-center py-3">
                    <p class="mb-0">¿No tienes una cuenta? <a href="register.php<?php echo isset($_GET['redirect_to']) ? '?redirect_to=' . urlencode($_GET['redirect_to']) : ''; ?>" class="text-decoration-none">Regístrate</a></p>
                </div>
            </div>
            
            <!-- Inicio rápido con redes sociales (opcional para futuras implementaciones) -->
            <div class="social-login text-center mt-4 d-none">
                <p class="text-muted">O inicia sesión con:</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-primary">
                        <i class="fab fa-facebook-f me-2"></i>Facebook
                    </button>
                    <button type="button" class="btn btn-outline-danger">
                        <i class="fab fa-google me-2"></i>Google
                    </button>
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
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Validar nombre de usuario
            const username = document.getElementById('username');
            if (!username.value.trim()) {
                isValid = false;
                username.classList.add('is-invalid');
            } else {
                username.classList.remove('is-invalid');
            }
            
            // Validar contraseña
            const password = document.getElementById('password');
            if (!password.value) {
                isValid = false;
                password.classList.add('is-invalid');
            } else {
                password.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                event.preventDefault();
            }
        });
        
        // Eliminar validación visual al cambiar el valor
        const inputs = loginForm.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
    }
});
</script>

<!-- Estilos adicionales para la página -->
<style>
.social-login button {
    width: 140px;
}
</style>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>