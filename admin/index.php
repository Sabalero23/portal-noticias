<?php
// Definir ruta base
define('BASE_PATH', dirname(__DIR__));
define('ADMIN_PATH', __DIR__);

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Inicializar objetos
$auth = new Auth();

// Verificar si el usuario ya está logueado y tiene permisos de administración
if (isLoggedIn() && hasRole(['admin', 'editor', 'author'])) {
    // Redirigir al dashboard
    redirect('dashboard.php');
}

// Variable para errores
$error = '';

// Procesar inicio de sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido';
    } else {
        // Verificar que tenemos los datos necesarios
        if (!isset($_POST['username']) || empty($_POST['username']) || 
            !isset($_POST['password']) || empty($_POST['password'])) {
            $error = 'Por favor, ingresa tu nombre de usuario y contraseña';
        } else {
            // Intentar login
            $user = $auth->login($_POST['username'], $_POST['password']);
            
            if ($user) {
                // Verificar si tiene permisos de administración
                if (hasRole(['admin', 'editor', 'author'])) {
                    // Redirigir al dashboard
                    redirect('dashboard.php');
                } else {
                    // No tiene permisos de administración
                    $auth->logout();
                    $error = 'No tienes permisos para acceder al panel de administración';
                }
            } else {
                // Login fallido
                $error = 'Nombre de usuario o contraseña incorrectos';
            }
        }
    }
}

// Título de la página
$pageTitle = 'Iniciar Sesión - Panel de Administración';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../<?php echo getSetting('favicon', 'assets/img/favicon.ico'); ?>" type="image/x-icon">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 15px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .logo img {
            max-width: 200px;
            height: auto;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #eee;
            padding: 20px;
            text-align: center;
            font-weight: 600;
            font-size: 1.2rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-control {
            border-radius: 5px;
            padding: 12px;
            height: auto;
        }
        
        .form-control:focus {
            border-color: #2196F3;
            box-shadow: 0 0 0 0.2rem rgba(33, 150, 243, 0.25);
        }
        
        .btn-primary {
            background-color: #2196F3;
            border-color: #2196F3;
            border-radius: 5px;
            padding: 12px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #0d8af0;
            border-color: #0d8af0;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .password-toggle-icon {
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    z-index: 10;
    color: #6c757d;
}

.password-toggle-icon:hover {
    color: #2196F3;
}

/* Ajuste para el input con icono */
.form-floating input[type="password"],
.form-floating input[type="text"] {
    padding-right: 40px;
}
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="../<?php echo getSetting('logo', 'assets/img/logo.png'); ?>" alt="<?php echo getSetting('site_name', 'Portal de Noticias'); ?>">
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-lock me-2"></i>Acceso al Panel de Administración
            </div>
            
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($flashMessage = getFlashMessage()): ?>
                    <div class="alert alert-<?php echo $flashMessage['type']; ?>" role="alert">
                        <?php echo $flashMessage['message']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Nombre de usuario o email" required>
                        <label for="username">Nombre de usuario o email</label>
                    </div>
                    
                    <div class="form-floating mb-4 position-relative">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                        <label for="password">Contraseña</label>
                        <span class="password-toggle-icon position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                        <i class="fas fa-eye" id="togglePassword"></i>
                    </span>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <a href="../index.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>Volver al sitio
                    </a>
                    <span class="mx-2">|</span>
                    <a href="forgot_password.php" class="text-decoration-none">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo getSetting('site_name', 'Portal de Noticias'); ?>. Todos los derechos reservados.</p>
            <p>Versión <?php echo SYSTEM_VERSION; ?></p>
        </div>
    </div>
    
<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Script para mostrar/ocultar contraseña
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            // Cambiar el tipo de input
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Cambiar el icono
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    });
</script>
</body>
</html>