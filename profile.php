
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

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    // Guardar la URL actual para redirigir después del login
    redirect('login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

// Inicializar variables - CORREGIR ESTO
$userId = $_SESSION['user']['id'];  // ← Cambiar de $_SESSION['user_id'] a $_SESSION['user']['id']
$updateSuccess = false;
$updateError = '';
$passwordSuccess = false;
$passwordError = '';
$imageSuccess = false;
$imageError = '';

// Obtener información del usuario
$db = Database::getInstance();
$user = $db->fetch(
    "SELECT id, username, email, name, bio, role, avatar, twitter, facebook, instagram, linkedin, created_at
     FROM users
     WHERE id = ?",
    [$userId]
);

// Si no existe el usuario, redirigir (esto no debería suceder normalmente)
if (!$user) {
    setFlashMessage('error', 'Usuario no encontrado');
    redirect('logout.php');
}

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // ... resto del código igual ...
    
    if ($result) {
        // Actualizar información en la sesión - CORREGIR ESTO
        $_SESSION['user']['name'] = $name;  // ← Cambiar de $_SESSION['user_name'] a $_SESSION['user']['name']
        
        // Actualizar información en la variable $user
        $user['name'] = $name;
        $user['bio'] = $bio;
        $user['twitter'] = $twitter;
        $user['facebook'] = $facebook;
        $user['instagram'] = $instagram;
        $user['linkedin'] = $linkedin;
        
        $updateSuccess = true;
    } else {
        $updateError = 'Error al actualizar el perfil. Por favor, intenta nuevamente.';
    }
}
  

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $passwordError = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        // Obtener datos del formulario
        $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Verificar contraseña actual
        $currentUser = $db->fetch(
            "SELECT password FROM users WHERE id = ?",
            [$userId]
        );
        
        if (!$currentUser || !password_verify($currentPassword, $currentUser['password'])) {
            $passwordError = 'La contraseña actual es incorrecta.';
        }
        // Validar nueva contraseña
        elseif (strlen($newPassword) < 8) {
            $passwordError = 'La nueva contraseña debe tener al menos 8 caracteres.';
        }
        // Verificar que las contraseñas coincidan
        elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'Las contraseñas no coinciden.';
        }
        else {
            // Hash de la nueva contraseña
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Actualizar contraseña
            $result = $db->query(
                "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
                [$hashedPassword, $userId]
            );
            
            if ($result) {
                $passwordSuccess = true;
            } else {
                $passwordError = 'Error al cambiar la contraseña. Por favor, intenta nuevamente.';
            }
        }
    }
}

// Procesar cambio de avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_avatar'])) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $imageError = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        // Verificar si se subió un archivo
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            
            // Verificar tamaño máximo (2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                $imageError = 'El tamaño del archivo no debe superar los 2MB.';
            } 
            // Verificar tipo de archivo
            elseif (!in_array($file['type'], ['image/jpeg', 'image/jpg', 'image/png'])) {
                $imageError = 'Solo se permiten archivos JPG y PNG.';
            }
            else {
                // Crear directorio si no existe
                $uploadDir = 'assets/img/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generar nombre de archivo único
                $fileName = 'avatar_' . $userId . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $filePath = $uploadDir . $fileName;
                
                // Mover archivo subido
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    // Actualizar avatar en la base de datos
                    $result = $db->query(
                        "UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?",
                        [$filePath, $userId]
                    );
                    
                    if ($result) {
                        // Si hay un avatar anterior, eliminarlo (excepto el default)
                        if (!empty($user['avatar']) && $user['avatar'] !== 'assets/img/authors/default.jpg' && file_exists($user['avatar'])) {
                            unlink($user['avatar']);
                        }
                        
                        // Actualizar información en la variable $user
                        $user['avatar'] = $filePath;
                        
                        $imageSuccess = true;
                    } else {
                        $imageError = 'Error al actualizar el avatar en la base de datos.';
                    }
                } else {
                    $imageError = 'Error al subir el archivo. Por favor, intenta nuevamente.';
                }
            }
        } else {
            $imageError = 'No se ha seleccionado ningún archivo o ha ocurrido un error al subirlo.';
        }
    }
}

// Procesar eliminación de avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_avatar'])) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $imageError = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        // Si hay un avatar actual, eliminarlo (excepto el default)
        if (!empty($user['avatar']) && $user['avatar'] !== 'assets/img/authors/default.jpg' && file_exists($user['avatar'])) {
            unlink($user['avatar']);
        }
        
        // Actualizar avatar en la base de datos (volver al default)
        $result = $db->query(
            "UPDATE users SET avatar = 'assets/img/authors/default.jpg', updated_at = NOW() WHERE id = ?",
            [$userId]
        );
        
        if ($result) {
            // Actualizar información en la variable $user
            $user['avatar'] = 'assets/img/authors/default.jpg';
            
            $imageSuccess = true;
        } else {
            $imageError = 'Error al eliminar el avatar. Por favor, intenta nuevamente.';
        }
    }
}

// Configuración para la página
$pageTitle = 'Mi Perfil - ' . getSetting('site_name', 'Portal de Noticias');
$metaDescription = 'Gestiona tu perfil y preferencias en ' . getSetting('site_name', 'Portal de Noticias');

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Mi Perfil</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Contenido Principal -->
<div class="container mt-4 mb-5">
    <div class="row">
        <!-- Columna lateral (información de perfil) -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar-container mb-3">
                        <img src="<?php echo $user['avatar'] ?: 'assets/img/authors/default.jpg'; ?>" alt="<?php echo $user['name']; ?>" class="rounded-circle img-thumbnail" width="150" height="150">
                    </div>
                    <h5 class="card-title"><?php echo escapeHtml($user['name']); ?></h5>
                    <p class="text-muted">@<?php echo escapeHtml($user['username']); ?>
                        @<?php echo $user['username']; ?>
                        <span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span>
                    </p>
                    
                    <div class="social-icons mt-3">
                        <?php if (!empty($user['twitter'])): ?>
                        <a href="<?php echo $user['twitter']; ?>" target="_blank" class="btn btn-sm btn-outline-info me-1" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['facebook'])): ?>
                        <a href="<?php echo $user['facebook']; ?>" target="_blank" class="btn btn-sm btn-outline-primary me-1" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['instagram'])): ?>
                        <a href="<?php echo $user['instagram']; ?>" target="_blank" class="btn btn-sm btn-outline-danger me-1" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['linkedin'])): ?>
                        <a href="<?php echo $user['linkedin']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-3">
                        <p class="small text-muted mb-1">Miembro desde:</p>
                        <p class="small"><?php echo formatDate($user['created_at'], 'd M, Y'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Menú de navegación -->
            <div class="list-group mt-3">
                <a href="#profile-info" class="list-group-item list-group-item-action active" data-bs-toggle="list">
                    <i class="fas fa-user me-2"></i> Información de perfil
                </a>
                <a href="#change-password" class="list-group-item list-group-item-action" data-bs-toggle="list">
                    <i class="fas fa-key me-2"></i> Cambiar contraseña
                </a>
                <a href="#change-avatar" class="list-group-item list-group-item-action" data-bs-toggle="list">
                    <i class="fas fa-image me-2"></i> Cambiar avatar
                </a>
                <a href="#my-comments" class="list-group-item list-group-item-action" data-bs-toggle="list">
                    <i class="fas fa-comments me-2"></i> Mis comentarios
                </a>
                <?php if ($user['role'] === 'admin' || $user['role'] === 'editor' || $user['role'] === 'author'): ?>
                <a href="admin/dashboard.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt me-2"></i> Panel de administración
                </a>
                <?php endif; ?>
                <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                    <i class="fas fa-sign-out-alt me-2"></i> Cerrar sesión
                </a>
            </div>
        </div>
        
        <!-- Columna principal (formularios) -->
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <div class="tab-content">
                        <!-- Información de perfil -->
                        <div class="tab-pane fade show active" id="profile-info">
                            <h3 class="card-title mb-4">Información de perfil</h3>
                            
                            <?php if ($updateSuccess): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i> Perfil actualizado correctamente.
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($updateError)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $updateError; ?>
                            </div>
                            <?php endif; ?>
                            
                            <form action="profile.php" method="post" id="profileForm">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="username" class="form-label">Nombre de usuario</label>
                                        <input type="text" class="form-control" id="username" value="<?php echo escapeHtml($user['username']); ?>" readonly>
                                        <div class="form-text">El nombre de usuario no se puede cambiar.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Correo electrónico</label>
                                        <input type="email" class="form-control" id="email" value="<?php echo escapeHtml($user['email']); ?>" readonly>
                                        <div class="form-text">Para cambiar tu correo, contacta al administrador.</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nombre completo *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo escapeHtml($user['name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bio" class="form-label">Biografía</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo escapeHtml($user['bio']); ?></textarea>
                                    <div class="form-text">Una breve descripción sobre ti.</div>
                                </div>
                                
                                <h5 class="mt-4 mb-3">Redes sociales</h5>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="twitter" class="form-label">
                                            <i class="fab fa-twitter text-info me-1"></i> Twitter
                                        </label>
                                        <input type="url" class="form-control" id="twitter" name="twitter" value="<?php echo escapeHtml($user['twitter']); ?>" placeholder="https://twitter.com/tu_usuario">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="facebook" class="form-label">
                                            <i class="fab fa-facebook text-primary me-1"></i> Facebook
                                        </label>
                                        <input type="url" class="form-control" id="facebook" name="facebook" value="<?php echo escapeHtml($user['facebook']); ?>" placeholder="https://facebook.com/tu_usuario">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="instagram" class="form-label">
                                            <i class="fab fa-instagram text-danger me-1"></i> Instagram
                                        </label>
                                        <input type="url" class="form-control" id="instagram" name="instagram" value="<?php echo escapeHtml($user['instagram']); ?>" placeholder="https://instagram.com/tu_usuario">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="linkedin" class="form-label">
                                            <i class="fab fa-linkedin text-primary me-1"></i> LinkedIn
                                        </label>
                                        <input type="url" class="form-control" id="linkedin" name="linkedin" value="<?php echo escapeHtml($user['linkedin']); ?>" placeholder="https://linkedin.com/in/tu_perfil">
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        Guardar cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Cambiar contraseña -->
                        <div class="tab-pane fade" id="change-password">
                            <h3 class="card-title mb-4">Cambiar contraseña</h3>
                            
                            <?php if ($passwordSuccess): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i> Contraseña cambiada correctamente.
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($passwordError)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $passwordError; ?>
                            </div>
                            <?php endif; ?>
                            
                            <form action="profile.php" method="post" id="passwordForm">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Contraseña actual *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nueva contraseña *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword" title="Mostrar/ocultar contraseña">
                                            <i class="fas fa-eye" id="toggleNewPasswordIcon"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Al menos 8 caracteres.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmar nueva contraseña *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        Cambiar contraseña
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Cambiar avatar -->
                        <div class="tab-pane fade" id="change-avatar">
                            <h3 class="card-title mb-4">Cambiar avatar</h3>
                            
                            <?php if ($imageSuccess): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i> Avatar actualizado correctamente.
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($imageError)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $imageError; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-4 text-center mb-4">
                                    <div class="avatar-preview">
                                        <img src="<?php echo $user['avatar'] ?: 'assets/img/authors/default.jpg'; ?>" alt="<?php echo $user['name']; ?>" class="img-fluid rounded-circle img-thumbnail" id="avatar-preview">
                                    </div>
                                    
                                    <?php if (!empty($user['avatar']) && $user['avatar'] !== 'assets/img/authors/default.jpg'): ?>
                                    <form action="profile.php" method="post" class="mt-3">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <button type="submit" name="remove_avatar" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash-alt me-1"></i> Eliminar avatar
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-8">
                                    <form action="profile.php" method="post" enctype="multipart/form-data" id="avatarForm">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        
                                        <div class="mb-3">
                                            <label for="avatar" class="form-label">Seleccionar imagen</label>
                                            <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/jpg,image/png" required>
                                            <div class="form-text">Formatos permitidos: JPG, PNG. Tamaño máximo: 2MB.</div>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <button type="submit" name="change_avatar" class="btn btn-primary">
                                                Subir avatar
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mis comentarios -->
                        <div class="tab-pane fade" id="my-comments">
                            <h3 class="card-title mb-4">Mis comentarios</h3>
                            
                            <?php
                            // Obtener los comentarios del usuario
                            $comments = $db->fetchAll(
                                "SELECT c.id, c.comment, c.status, c.created_at, 
                                         n.id as news_id, n.title, n.slug
                                 FROM comments c
                                 JOIN news n ON c.news_id = n.id
                                 WHERE c.email = ?
                                 ORDER BY c.created_at DESC
                                 LIMIT 20",
                                [$user['email']]
                            );
                            ?>
                            
                            <?php if (empty($comments)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> No has realizado ningún comentario aún.
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Noticia</th>
                                            <th>Comentario</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($comments as $comment): ?>
                                        <tr>
                                            <td>
                                                <a href="news.php?slug=<?php echo $comment['slug']; ?>#comment-<?php echo $comment['id']; ?>" class="text-decoration-none">
                                                    <?php echo truncateString($comment['title'], 40); ?>
                                                </a>
                                            </td>
                                            <td><?php echo truncateString($comment['comment'], 50); ?></td>
                                            <td><?php echo formatDate($comment['created_at'], 'd M, Y H:i'); ?></td>
                                            <td>
                                                <?php if ($comment['status'] === 'approved'): ?>
                                                <span class="badge bg-success">Aprobado</span>
                                                <?php elseif ($comment['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pendiente</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">Rechazado</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript para la página -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Activar la pestaña según el hash de la URL
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector(`a[href="${hash}"]`);
        if (tab) {
            const tabInstance = new bootstrap.Tab(tab);
            tabInstance.show();
        }
    }
    
    // Mostrar/ocultar contraseña
    const toggleNewPassword = document.getElementById('toggleNewPassword');
    const newPasswordInput = document.getElementById('new_password');
    const toggleNewPasswordIcon = document.getElementById('toggleNewPasswordIcon');
    
    if (toggleNewPassword && newPasswordInput && toggleNewPasswordIcon) {
        toggleNewPassword.addEventListener('click', function() {
            const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            newPasswordInput.setAttribute('type', type);
            
            // Cambiar el ícono
            toggleNewPasswordIcon.classList.toggle('fa-eye');
            toggleNewPasswordIcon.classList.toggle('fa-eye-slash');
        });
    }
    
    // Vista previa de imagen de avatar
    const avatarInput = document.getElementById('avatar');
    const avatarPreview = document.getElementById('avatar-preview');
    
    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    avatarPreview.src = e.target.result;
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // Validación de formularios
    
    // Formulario de perfil
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Validar nombre
            const name = document.getElementById('name');
            if (!name.value.trim() || name.value.trim().length < 3 || name.value.trim().length > 100) {
                isValid = false;
                name.classList.add('is-invalid');
            } else {
                name.classList.remove('is-invalid');
            }
            
            // Validar URLs de redes sociales
            const twitter = document.getElementById('twitter');
            const facebook = document.getElementById('facebook');
            const instagram = document.getElementById('instagram');
            const linkedin = document.getElementById('linkedin');
            
            const urlPattern = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/;
            
            if (twitter.value.trim() && !urlPattern.test(twitter.value.trim())) {
                isValid = false;
                twitter.classList.add('is-invalid');
            } else {
                twitter.classList.remove('is-invalid');
            }
            
            if (facebook.value.trim() && !urlPattern.test(facebook.value.trim())) {
                isValid = false;
                facebook.classList.add('is-invalid');
            } else {
                facebook.classList.remove('is-invalid');
            }
            
            if (instagram.value.trim() && !urlPattern.test(instagram.value.trim())) {
                isValid = false;
                instagram.classList.add('is-invalid');
            } else {
                instagram.classList.remove('is-invalid');
            }
            
            if (linkedin.value.trim() && !urlPattern.test(linkedin.value.trim())) {
                isValid = false;
                linkedin.classList.add('is-invalid');
            } else {
                linkedin.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                event.preventDefault();
                
                // Mostrar mensaje de error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger mt-3';
                errorDiv.textContent = 'Por favor, corrige los errores en el formulario antes de continuar.';
                
                const existingError = profileForm.querySelector('.alert');
                if (existingError) {
                    profileForm.removeChild(existingError);
                }
                
                profileForm.prepend(errorDiv);
            }
        });
    }
    
    // Formulario de contraseña
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Validar contraseña actual
            const currentPassword = document.getElementById('current_password');
            if (!currentPassword.value) {
                isValid = false;
                currentPassword.classList.add('is-invalid');
            } else {
                currentPassword.classList.remove('is-invalid');
            }
            
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
                
                const existingError = passwordForm.querySelector('.alert');
                if (existingError) {
                    passwordForm.removeChild(existingError);
                }
                
                passwordForm.prepend(errorDiv);
            }
        });
    }
});
</script>

<!-- Estilos adicionales para la página -->
<style>
.avatar-container img {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border: 3px solid #f8f9fa;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
}

.avatar-preview img {
    width: 150px;
    height: 150px;
    object-fit: cover;
}

.social-icons .btn {
    width: 36px;
    height: 36px;
    padding: 6px;
}
</style>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>