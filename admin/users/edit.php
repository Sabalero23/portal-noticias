<?php
// Definir ruta base
define('BASE_PATH', dirname(dirname(__DIR__)));
define('ADMIN_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once ADMIN_PATH . '/includes/functions.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin'], 'dashboard.php');

// Verificar si se proporcionó un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de usuario no válido');
    redirect('index.php');
    exit;
}

$userId = (int)$_GET['id'];

// Inicializar DB
$db = Database::getInstance();

// Obtener datos del usuario
$user = $db->fetch(
    "SELECT id, username, email, name, bio, role, avatar, status, created_at, last_login 
     FROM users 
     WHERE id = ?",
    [$userId]
);

// Verificar si el usuario existe
if (!$user) {
    setFlashMessage('error', 'Usuario no encontrado');
    redirect('index.php');
    exit;
}

// Inicializar variables
$errors = [];
$oldValues = $user;
$success = false;

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors['general'] = 'Token de seguridad inválido';
    } else {
        // Recoger datos del formulario
        $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $role = isset($_POST['role']) ? sanitize($_POST['role']) : '';
        $status = isset($_POST['status']) ? sanitize($_POST['status']) : 'active';
        $bio = isset($_POST['bio']) ? sanitize($_POST['bio']) : '';
        
        // Guardar valores antiguos para repoblar el formulario en caso de error
        $oldValues = [
            'id' => $user['id'],
            'username' => $user['username'], // No se puede cambiar el username
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'bio' => $bio,
            'avatar' => $user['avatar'],
            'created_at' => $user['created_at'],
            'last_login' => $user['last_login']
        ];
        
        // Validar datos
        if (empty($name)) {
            $errors['name'] = 'El nombre completo es obligatorio';
        } elseif (strlen($name) < 3 || strlen($name) > 100) {
            $errors['name'] = 'El nombre debe tener entre 3 y 100 caracteres';
        }
        
        if (empty($email)) {
            $errors['email'] = 'El email es obligatorio';
        } elseif (!isValidEmail($email)) {
            $errors['email'] = 'El email no es válido';
        }
        
        // Verificar si el email ya existe (excluyendo el usuario actual)
        if ($email !== $user['email']) {
            $existingEmail = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            
            if ($existingEmail) {
                $errors['email'] = 'Este email ya está registrado';
            }
        }
        
        // Validar contraseña solo si se ha proporcionado
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $errors['password'] = 'La contraseña debe tener al menos 8 caracteres';
            }
            
            if ($password !== $confirmPassword) {
                $errors['confirm_password'] = 'Las contraseñas no coinciden';
            }
        }
        
        if (empty($role)) {
            $errors['role'] = 'El rol es obligatorio';
        } elseif (!in_array($role, ['admin', 'editor', 'author', 'subscriber'])) {
            $errors['role'] = 'El rol seleccionado no es válido';
        }
        
        if (empty($status)) {
            $errors['status'] = 'El estado es obligatorio';
        } elseif (!in_array($status, ['active', 'inactive', 'banned'])) {
            $errors['status'] = 'El estado seleccionado no es válido';
        }
        
        // Si se está editando el usuario actual, verificar que no se esté desactivando
        if ($userId == $_SESSION['user']['id'] && $status !== 'active') {
            $errors['status'] = 'No puedes desactivar tu propia cuenta';
        }
        
        // Verificar que no se esté cambiando el rol del usuario actual
        if ($userId == $_SESSION['user']['id'] && $role !== $user['role'] && $user['role'] === 'admin') {
            $errors['role'] = 'No puedes cambiar tu propio rol de administrador';
        }
        
        // Procesar imagen de avatar si se ha subido
        $avatarPath = $user['avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = processImageUpload(
                $_FILES['avatar'],
                BASE_PATH . '/assets/img/authors',
                ['image/jpeg', 'image/png', 'image/gif'],
                2 * 1024 * 1024, // 2MB
                500, // Ancho máximo
                500  // Alto máximo
            );
            
            if (!$uploadResult['success']) {
                $errors['avatar'] = $uploadResult['message'];
            } else {
                $avatarPath = 'assets/img/authors/' . $uploadResult['filename'];
            }
        }
        
        // Si no hay errores, actualizar el usuario
        if (empty($errors)) {
            try {
                // Preparar datos para actualizar
                $updateFields = [
                    'name' => $name,
                    'email' => $email,
                    'bio' => $bio,
                    'role' => $role,
                    'status' => $status,
                    'avatar' => $avatarPath,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Añadir contraseña si se ha proporcionado
                if (!empty($password)) {
                    $updateFields['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                
                // Construir consulta SQL
                $sql = "UPDATE users SET ";
                $params = [];
                
                foreach ($updateFields as $field => $value) {
                    $sql .= "$field = ?, ";
                    $params[] = $value;
                }
                
                // Eliminar la última coma y espacio
                $sql = rtrim($sql, ', ');
                
                // Añadir condición WHERE
                $sql .= " WHERE id = ?";
                $params[] = $userId;
                
                // Ejecutar consulta
                $updated = $db->query($sql, $params);
                
                if ($updated) {
                    // Registrar acción en el log
                    logAdminAction('Actualización de usuario', "Usuario actualizado: {$user['username']}", 'users', $userId);
                    
                    // Si estamos actualizando nuestro propio usuario, actualizar la sesión
                    if ($userId == $_SESSION['user']['id']) {
                        $_SESSION['user']['name'] = $name;
                        $_SESSION['user']['email'] = $email;
                        
                        if (!empty($password)) {
                            // Forzar cierre de sesión si se ha cambiado la contraseña
                            setFlashMessage('success', 'Tu información se ha actualizado. Por favor, inicia sesión nuevamente con tu nueva contraseña.');
                            redirect(ADMIN_PATH . '/logout.php');
                            exit;
                        }
                    }
                    
                    // Mostrar mensaje de éxito
                    setFlashMessage('success', 'Usuario actualizado correctamente');
                    
                    // Redirigir a la lista de usuarios
                    redirect('index.php');
                    exit;
                } else {
                    $errors['general'] = 'Error al actualizar el usuario';
                }
            } catch (Exception $e) {
                $errors['general'] = 'Error al actualizar el usuario: ' . $e->getMessage();
            }
        }
    }
}

// Título de la página
$pageTitle = 'Editar Usuario - Panel de Administración';
$currentMenu = 'users_edit';

// Incluir cabecera
include_once ADMIN_PATH . '/includes/header.php';
include_once ADMIN_PATH . '/includes/sidebar.php';
?>

<!-- Contenido principal -->
<div class="content-wrapper">
    <!-- Cabecera de página -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Editar Usuario</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Usuarios</a></li>
                        <li class="breadcrumb-item active">Editar Usuario</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Editar Usuario: <?php echo htmlspecialchars($user['username']); ?></h3>
                </div>
                
                <form action="edit.php?id=<?php echo $userId; ?>" method="POST" enctype="multipart/form-data">
                    <div class="card-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <!-- Mensajes de error generales -->
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Nombre de usuario (no editable) -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="username" class="form-label">Nombre de usuario</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                    <div class="form-text">El nombre de usuario no se puede cambiar.</div>
                                </div>
                            </div>
                            
                            <!-- Email -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="email" class="form-label form-label-required">Email</label>
                                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo oldValue('email', $oldValues); ?>" required>
                                    <?php echo showErrorMessage('email', $errors); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Nombre completo -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="name" class="form-label form-label-required">Nombre completo</label>
                                    <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo oldValue('name', $oldValues); ?>" required>
                                    <?php echo showErrorMessage('name', $errors); ?>
                                </div>
                            </div>
                            
                            <!-- Avatar -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="avatar" class="form-label">Avatar</label>
                                    <input type="file" class="form-control <?php echo isset($errors['avatar']) ? 'is-invalid' : ''; ?>" id="avatar" name="avatar" accept="image/jpeg, image/png, image/gif">
                                    <div class="form-text">Imagen de perfil (JPG, PNG o GIF, máx. 2MB, dimensiones recomendadas 500x500)</div>
                                    <?php echo showErrorMessage('avatar', $errors); ?>
                                    
                                    <?php if (!empty($user['avatar'])): ?>
                                    <div class="mt-2" id="avatar-preview-container">
                                        <img src="<?php echo SITE_URL . '/' . $user['avatar']; ?>" class="img-preview img-thumbnail" alt="Avatar actual" style="max-width: 200px;">
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Contraseña -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="password" class="form-label">Contraseña</label>
                                    <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password">
                                    <div class="form-text">Dejar en blanco para mantener la contraseña actual.</div>
                                    <?php echo showErrorMessage('password', $errors); ?>
                                </div>
                            </div>
                            
                            <!-- Confirmar contraseña -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password">
                                    <?php echo showErrorMessage('confirm_password', $errors); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Rol -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="role" class="form-label form-label-required">Rol</label>
                                    <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" id="role" name="role" required <?php echo ($userId == $_SESSION['user']['id'] && $user['role'] === 'admin') ? 'disabled' : ''; ?>>
                                        <option value="">Seleccionar rol</option>
                                        <option value="admin" <?php echo oldValue('role', $oldValues) === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                        <option value="editor" <?php echo oldValue('role', $oldValues) === 'editor' ? 'selected' : ''; ?>>Editor</option>
                                        <option value="author" <?php echo oldValue('role', $oldValues) === 'author' ? 'selected' : ''; ?>>Autor</option>
                                        <option value="subscriber" <?php echo oldValue('role', $oldValues) === 'subscriber' ? 'selected' : ''; ?>>Suscriptor</option>
                                    </select>
                                    <?php if ($userId == $_SESSION['user']['id'] && $user['role'] === 'admin'): ?>
                                        <input type="hidden" name="role" value="admin">
                                        <div class="form-text">No puedes cambiar tu propio rol de administrador.</div>
                                    <?php endif; ?>
                                    <?php echo showErrorMessage('role', $errors); ?>
                                </div>
                            </div>
                            
                            <!-- Estado -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="status" class="form-label form-label-required">Estado</label>
                                    <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" id="status" name="status" required <?php echo $userId == $_SESSION['user']['id'] ? 'disabled' : ''; ?>>
                                        <option value="active" <?php echo oldValue('status', $oldValues, 'active') === 'active' ? 'selected' : ''; ?>>Activo</option>
                                        <option value="inactive" <?php echo oldValue('status', $oldValues) === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                        <option value="banned" <?php echo oldValue('status', $oldValues) === 'banned' ? 'selected' : ''; ?>>Baneado</option>
                                    </select>
                                    <?php if ($userId == $_SESSION['user']['id']): ?>
                                        <input type="hidden" name="status" value="active">
                                        <div class="form-text">No puedes desactivar tu propia cuenta.</div>
                                    <?php endif; ?>
                                    <?php echo showErrorMessage('status', $errors); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Información adicional -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Fecha de registro</label>
                                    <input type="text" class="form-control" value="<?php echo formatDate($user['created_at'], 'd/m/Y H:i'); ?>" disabled>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Último acceso</label>
                                    <input type="text" class="form-control" value="<?php echo $user['last_login'] ? formatDate($user['last_login'], 'd/m/Y H:i') : 'Nunca'; ?>" disabled>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Biografía -->
                        <div class="form-group mb-3">
                            <label for="bio" class="form-label">Biografía</label>
                            <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo oldValue('bio', $oldValues); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Guardar cambios
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Script específico para este formulario -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar vista previa de la imagen seleccionada
    const avatarInput = document.getElementById('avatar');
    
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Verificar que sea una imagen
                if (!file.type.match('image.*')) {
                    alert('Por favor, selecciona una imagen');
                    return;
                }
                
                // Previsualizar imagen
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const previewContainer = document.getElementById('avatar-preview-container');
                    
                    if (!previewContainer) {
                        // Crear contenedor si no existe
                        const newPreview = document.createElement('div');
                        newPreview.id = 'avatar-preview-container';
                        newPreview.className = 'mt-2';
                        newPreview.innerHTML = `<img src="${e.target.result}" class="img-preview img-thumbnail" alt="Vista previa" style="max-width: 200px;">`;
                        
                        // Insertar después del input
                        avatarInput.parentNode.appendChild(newPreview);
                    } else {
                        // Actualizar imagen existente
                        const previewImg = previewContainer.querySelector('img');
                        previewImg.src = e.target.result;
                    }
                };
                
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>