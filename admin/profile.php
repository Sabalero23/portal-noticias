<?php
// Definir ruta base
define('BASE_PATH', dirname(__DIR__));
define('ADMIN_PATH', __DIR__);

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor', 'author'], 'index.php');

// Obtener datos del usuario actual desde la sesión
if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    setFlashMessage('error', 'Sesión inválida. Por favor, inicia sesión nuevamente.');
    redirect('logout.php');
    exit;
}

$userId = $_SESSION['user']['id'];
$db = Database::getInstance();
$user = $db->fetch(
    "SELECT id, username, email, name, bio, role, avatar, twitter, facebook, instagram, linkedin, created_at, last_login 
     FROM users 
     WHERE id = ?",
    [$userId]
);

// Si no se encontró el usuario, redirigir
if (!$user) {
    setFlashMessage('error', 'Usuario no encontrado');
    redirect('dashboard.php');
    exit;
}

// Procesar actualización del perfil
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Validar datos
        $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
        $bio = isset($_POST['bio']) ? sanitize($_POST['bio']) : '';
        $twitter = isset($_POST['twitter']) ? sanitize($_POST['twitter']) : '';
        $facebook = isset($_POST['facebook']) ? sanitize($_POST['facebook']) : '';
        $instagram = isset($_POST['instagram']) ? sanitize($_POST['instagram']) : '';
        $linkedin = isset($_POST['linkedin']) ? sanitize($_POST['linkedin']) : '';
        $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Validar nombre
        if (empty($name)) {
            $errors[] = 'El nombre es obligatorio';
        } elseif (strlen($name) < 3 || strlen($name) > 100) {
            $errors[] = 'El nombre debe tener entre 3 y 100 caracteres';
        }
        
        // Validar email
        if (empty($email)) {
            $errors[] = 'El email es obligatorio';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'El email no es válido';
        } else {
            // Verificar que el email no esté en uso por otro usuario
            $existingUser = $db->fetch(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$email, $userId]
            );
            
            if ($existingUser) {
                $errors[] = 'El email ya está en uso por otro usuario';
            }
        }
        
        // Validar redes sociales (opcional)
        if (!empty($twitter) && !preg_match('/^[a-zA-Z0-9_]{1,15}$/', $twitter)) {
            $errors[] = 'El nombre de usuario de Twitter no es válido (sin @, solo letras, números y guiones bajos)';
        }
        
        if (!empty($facebook) && !filter_var('https://facebook.com/' . $facebook, FILTER_VALIDATE_URL)) {
            $errors[] = 'El ID de Facebook no es válido';
        }
        
        if (!empty($instagram) && !preg_match('/^[a-zA-Z0-9_.]{1,30}$/', $instagram)) {
            $errors[] = 'El nombre de usuario de Instagram no es válido';
        }
        
        if (!empty($linkedin) && !preg_match('/^[a-zA-Z0-9-]{1,100}$/', $linkedin)) {
            $errors[] = 'El ID de LinkedIn no es válido';
        }
        
        // Procesar cambio de contraseña si se proporcionaron los campos
        $updatePassword = false;
        if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
            // Validar contraseña actual
            if (empty($currentPassword)) {
                $errors[] = 'La contraseña actual es obligatoria para cambiar la contraseña';
            } else {
                // Obtener la contraseña actual del usuario
                $userPassword = $db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
                if (!$userPassword || !isset($userPassword['password'])) {
                    $errors[] = 'Error al verificar la contraseña. Por favor, inténtalo de nuevo.';
                } elseif (!password_verify($currentPassword, $userPassword['password'])) {
                    $errors[] = 'La contraseña actual es incorrecta';
                }
            }
            
            // Validar nueva contraseña
            if (empty($newPassword)) {
                $errors[] = 'La nueva contraseña es obligatoria';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres';
            }
            
            // Validar confirmación
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Las contraseñas no coinciden';
            }
            
            $updatePassword = true;
        }
        
        // Procesar imagen de avatar
        $updateAvatar = false;
        $avatarPath = $user['avatar'];
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['avatar']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = 'El archivo debe ser una imagen (JPG, PNG o GIF)';
            } else {
                // Validar tamaño (máximo 2MB)
                if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                    $errors[] = 'La imagen no puede superar los 2MB';
                } else {
                    $updateAvatar = true;
                }
            }
        }
        
        // Si no hay errores, actualizar perfil
        if (empty($errors)) {
            try {
                // Preparar datos para actualizar
                $updateData = [
                    'name' => $name,
                    'email' => $email,
                    'bio' => $bio,
                    'twitter' => $twitter,
                    'facebook' => $facebook,
                    'instagram' => $instagram,
                    'linkedin' => $linkedin,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Si se va a actualizar la contraseña
                if ($updatePassword) {
                    $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
                
                // Actualizar datos básicos del usuario
                $placeholders = [];
                $values = [];
                
                foreach ($updateData as $key => $value) {
                    $placeholders[] = "$key = ?";
                    $values[] = $value;
                }
                
                // Añadir ID al final de los valores
                $values[] = $userId;
                
                $sql = "UPDATE users SET " . implode(', ', $placeholders) . " WHERE id = ?";
                $updated = $db->query($sql, $values);
                
                if (!$updated) {
                    throw new Exception('Error al actualizar los datos del usuario');
                }
                
                // Si se va a actualizar el avatar
                if ($updateAvatar) {
                    // Crear directorio si no existe
                    $uploadDir = BASE_PATH . '/assets/img/authors/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generar nombre único para el archivo
                    $fileExt = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $fileName = 'avatar_' . $userId . '_' . time() . '.' . $fileExt;
                    $filePath = $uploadDir . $fileName;
                    
                    // Mover archivo
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filePath)) {
                        // Actualizar ruta en la base de datos
                        $relativePath = 'assets/img/authors/' . $fileName;
                        $avatarUpdated = $db->query(
                            "UPDATE users SET avatar = ? WHERE id = ?",
                            [$relativePath, $userId]
                        );
                        
                        if (!$avatarUpdated) {
                            throw new Exception('Error al actualizar la imagen de perfil');
                        }
                        
                        // Actualizar variable para mostrar
                        $avatarPath = $relativePath;
                    } else {
                        $errors[] = 'Error al subir la imagen. Intenta nuevamente.';
                        $success = false;
                    }
                }
                
                // Actualizar sesión con nuevos datos
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                if ($updateAvatar && !empty($avatarPath)) {
                    $_SESSION['user']['avatar'] = $avatarPath;
                }
                
                // Marcar éxito
                $success = true;
                
                // Cargar datos actualizados
                $user = $db->fetch(
                    "SELECT id, username, email, name, bio, role, avatar, twitter, facebook, instagram, linkedin, created_at, last_login 
                     FROM users 
                     WHERE id = ?",
                    [$userId]
                );
                
                // Mensaje de éxito
                setFlashMessage('success', 'Perfil actualizado correctamente');
                
                // Recargar la página para mostrar los cambios
                redirect('profile.php');
                exit;
                
            } catch (Exception $e) {
                $errors[] = 'Error al actualizar el perfil: ' . $e->getMessage();
                $success = false;
            }
        }
    }
}

// Título de la página
$pageTitle = 'Mi Perfil - Panel de Administración';
$currentMenu = 'profile';

// Incluir cabecera
include_once 'includes/header.php';
include_once 'includes/sidebar.php';
?>

<!-- Contenido principal -->
<div class="content-wrapper">
    <!-- Cabecera de página -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Mi Perfil</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Mi Perfil</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Mensajes de error y éxito -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>Perfil actualizado correctamente
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Columna izquierda: Información del perfil -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body box-profile text-center">
                            <div class="profile-picture-container mb-3">
                                <?php if (!empty($user['avatar'])): ?>
                                    <img src="../<?php echo $user['avatar']; ?>" class="profile-picture rounded-circle" alt="<?php echo $user['name']; ?>">
                                <?php else: ?>
                                    <div class="profile-picture-placeholder rounded-circle d-flex align-items-center justify-content-center bg-light">
                                        <i class="fas fa-user fa-4x text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <h3 class="profile-username"><?php echo $user['name']; ?></h3>
                            <p class="text-muted">
                                <?php
                                switch ($user['role']) {
                                    case 'admin':
                                        echo '<span class="badge bg-danger">Administrador</span>';
                                        break;
                                    case 'editor':
                                        echo '<span class="badge bg-success">Editor</span>';
                                        break;
                                    case 'author':
                                        echo '<span class="badge bg-info">Autor</span>';
                                        break;
                                    default:
                                        echo '<span class="badge bg-secondary">Suscriptor</span>';
                                }
                                ?>
                            </p>
                            
                            <ul class="list-group mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong><i class="fas fa-envelope me-2"></i>Email</strong>
                                    <span><?php echo $user['email']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong><i class="fas fa-user me-2"></i>Usuario</strong>
                                    <span><?php echo $user['username']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong><i class="fas fa-calendar me-2"></i>Miembro desde</strong>
                                    <span><?php echo formatDate($user['created_at'], 'd/m/Y'); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong><i class="fas fa-clock me-2"></i>Último acceso</strong>
                                    <span><?php echo $user['last_login'] ? formatDate($user['last_login'], 'd/m/Y H:i') : 'N/A'; ?></span>
                                </li>
                            </ul>
                            
                            <?php if (!empty($user['bio'])): ?>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Biografía</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0"><?php echo nl2br($user['bio']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Redes sociales -->
                            <?php
                            $hasSocialMedia = !empty($user['twitter']) || !empty($user['facebook']) || !empty($user['instagram']) || !empty($user['linkedin']);
                            ?>
                            
                            <?php if ($hasSocialMedia): ?>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Redes Sociales</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="social-links">
                                            <?php if (!empty($user['twitter'])): ?>
                                                <a href="https://twitter.com/<?php echo $user['twitter']; ?>" target="_blank" class="btn btn-outline-primary me-1">
                                                    <i class="fab fa-twitter"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($user['facebook'])): ?>
                                                <a href="https://facebook.com/<?php echo $user['facebook']; ?>" target="_blank" class="btn btn-outline-primary me-1">
                                                    <i class="fab fa-facebook-f"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($user['instagram'])): ?>
                                                <a href="https://instagram.com/<?php echo $user['instagram']; ?>" target="_blank" class="btn btn-outline-danger me-1">
                                                    <i class="fab fa-instagram"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($user['linkedin'])): ?>
                                                <a href="https://linkedin.com/in/<?php echo $user['linkedin']; ?>" target="_blank" class="btn btn-outline-primary">
                                                    <i class="fab fa-linkedin-in"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Columna derecha: Formulario de edición -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Editar Perfil</h3>
                        </div>
                        <div class="card-body">
                            <form action="profile.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                
                                <div class="row">
                                    <!-- Información básica -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?php echo $user['name']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="avatar" class="form-label">Imagen de perfil</label>
                                    <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg, image/png, image/gif">
                                    <div class="form-text">Imagen de perfil (JPG, PNG o GIF, máx. 2MB)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bio" class="form-label">Biografía</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo $user['bio']; ?></textarea>
                                </div>
                                
                                <!-- Información de redes sociales -->
                                <h5 class="mt-4 mb-3">Redes Sociales</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="twitter" class="form-label">Twitter</label>
                                            <div class="input-group">
                                                <span class="input-group-text">@</span>
                                                <input type="text" class="form-control" id="twitter" name="twitter" value="<?php echo $user['twitter']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="facebook" class="form-label">Facebook</label>
                                            <div class="input-group">
                                                <span class="input-group-text">facebook.com/</span>
                                                <input type="text" class="form-control" id="facebook" name="facebook" value="<?php echo $user['facebook']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="instagram" class="form-label">Instagram</label>
                                            <div class="input-group">
                                                <span class="input-group-text">@</span>
                                                <input type="text" class="form-control" id="instagram" name="instagram" value="<?php echo $user['instagram']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="linkedin" class="form-label">LinkedIn</label>
                                            <div class="input-group">
                                                <span class="input-group-text">linkedin.com/in/</span>
                                                <input type="text" class="form-control" id="linkedin" name="linkedin" value="<?php echo $user['linkedin']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Cambio de contraseña -->
                                <h5 class="mt-4 mb-3">Cambiar Contraseña</h5>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>Deja estos campos en blanco si no deseas cambiar tu contraseña
                                </div>
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Contraseña actual</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">Nueva contraseña</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirmar nueva contraseña</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Guardar cambios
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary ms-2">
                                        <i class="fas fa-times me-1"></i> Cancelar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estilos adicionales para la página de perfil -->
<style>
    .profile-picture-container {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto 20px;
    }
    
    .profile-picture {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border: 3px solid #fff;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .profile-picture-placeholder {
        width: 100%;
        height: 100%;
        border: 3px solid #fff;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .social-links {
        display: flex;
        justify-content: center;
    }
</style>

<?php include_once 'includes/footer.php'; ?>

<!-- Script para manejo de alertas -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar todos los tooltips
        const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        if (tooltips.length > 0 && typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            tooltips.forEach(tooltip => {
                new bootstrap.Tooltip(tooltip);
            });
        }
        
        // Autocierre de alertas después de 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert.alert-success, .alert.alert-info');
            alerts.forEach(alert => {
                try {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        if (bsAlert && typeof bsAlert.close === 'function') {
                            bsAlert.close();
                        }
                    } else {
                        // Fallback si bootstrap.Alert no está disponible
                        alert.classList.remove('show');
                        setTimeout(() => {
                            if (alert.parentNode) {
                                alert.parentNode.removeChild(alert);
                            }
                        }, 150);
                    }
                } catch (e) {
                    // Si hay un error al cerrar la alerta, intentamos el método manual
                    console.error('Error al cerrar alerta:', e);
                    if (alert.parentNode) {
                        alert.classList.remove('show');
                        setTimeout(() => alert.parentNode.removeChild(alert), 150);
                    }
                }
            });
        }, 5000);
    });
</script>