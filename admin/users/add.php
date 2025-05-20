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

// Inicializar variables
$errors = [];
$oldValues = [];
$success = false;

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors['general'] = 'Token de seguridad inválido';
    } else {
        // Recoger datos del formulario
        $username = isset($_POST['username']) ? sanitize($_POST['username']) : '';
        $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $role = isset($_POST['role']) ? sanitize($_POST['role']) : '';
        $status = isset($_POST['status']) ? sanitize($_POST['status']) : 'active';
        $bio = isset($_POST['bio']) ? sanitize($_POST['bio']) : '';
        
        // Guardar valores antiguos para repoblar el formulario en caso de error
        $oldValues = [
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'bio' => $bio
        ];
        
        // Validar datos
        if (empty($username)) {
            $errors['username'] = 'El nombre de usuario es obligatorio';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors['username'] = 'El nombre de usuario debe tener entre 3 y 50 caracteres';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors['username'] = 'El nombre de usuario solo puede contener letras, números y guiones bajos';
        }
        
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
        
        if (empty($password)) {
            $errors['password'] = 'La contraseña es obligatoria';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres';
        }
        
        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Las contraseñas no coinciden';
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
        
        // Verificar si el nombre de usuario ya existe
        $db = Database::getInstance();
        $existingUser = $db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
        
        if ($existingUser) {
            $errors['username'] = 'Este nombre de usuario ya está en uso';
        }
        
        // Verificar si el email ya existe
        $existingEmail = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        
        if ($existingEmail) {
            $errors['email'] = 'Este email ya está registrado';
        }
        
        // Procesar imagen de avatar si se ha subido
        $avatarPath = '';
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
        
        // Si no hay errores, insertar el nuevo usuario
        if (empty($errors)) {
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $db->query(
                    "INSERT INTO users (username, password, email, name, bio, role, avatar, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$username, $hashedPassword, $email, $name, $bio, $role, $avatarPath, $status]
                );
                
                $userId = $db->lastInsertId();
                
                if ($userId) {
                    // Registrar acción en el log
                    logAdminAction('Creación de usuario', "Usuario creado: $username", 'users', $userId);
                    
                    // Mostrar mensaje de éxito
                    setFlashMessage('success', 'Usuario creado correctamente');
                    
                    // Redirigir a la lista de usuarios
                    redirect('index.php');
                    exit;
                } else {
                    $errors['general'] = 'Error al crear el usuario';
                }
            } catch (Exception $e) {
                $errors['general'] = 'Error al crear el usuario: ' . $e->getMessage();
            }
        }
    }
}

// Título de la página
$pageTitle = 'Añadir Usuario - Panel de Administración';
$currentMenu = 'users_add';

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
                    <h1 class="m-0">Añadir Usuario</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Usuarios</a></li>
                        <li class="breadcrumb-item active">Añadir Usuario</li>
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
                    <h3 class="card-title">Nuevo Usuario</h3>
                </div>
                
                <form action="add.php" method="POST" enctype="multipart/form-data">
                    <div class="card-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <!-- Mensajes de error generales -->
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Nombre de usuario -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="username" class="form-label form-label-required">Nombre de usuario</label>
                                    <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo oldValue('username', $oldValues); ?>" required>
                                    <?php echo showErrorMessage('username', $errors); ?>
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
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Contraseña -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="password" class="form-label form-label-required">Contraseña</label>
                                    <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                                    <?php echo showErrorMessage('password', $errors); ?>
                                </div>
                            </div>
                            
                            <!-- Confirmar contraseña -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="confirm_password" class="form-label form-label-required">Confirmar contraseña</label>
                                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                                    <?php echo showErrorMessage('confirm_password', $errors); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Rol -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="role" class="form-label form-label-required">Rol</label>
                                    <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" id="role" name="role" required>
                                        <option value="">Seleccionar rol</option>
                                        <option value="admin" <?php echo oldValue('role', $oldValues) === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                        <option value="editor" <?php echo oldValue('role', $oldValues) === 'editor' ? 'selected' : ''; ?>>Editor</option>
                                        <option value="author" <?php echo oldValue('role', $oldValues) === 'author' ? 'selected' : ''; ?>>Autor</option>
                                        <option value="subscriber" <?php echo oldValue('role', $oldValues) === 'subscriber' ? 'selected' : ''; ?>>Suscriptor</option>
                                    </select>
                                    <?php echo showErrorMessage('role', $errors); ?>
                                </div>
                            </div>
                            
                            <!-- Estado -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="status" class="form-label form-label-required">Estado</label>
                                    <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" id="status" name="status" required>
                                        <option value="active" <?php echo oldValue('status', $oldValues, 'active') === 'active' ? 'selected' : ''; ?>>Activo</option>
                                        <option value="inactive" <?php echo oldValue('status', $oldValues) === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                        <option value="banned" <?php echo oldValue('status', $oldValues) === 'banned' ? 'selected' : ''; ?>>Baneado</option>
                                    </select>
                                    <?php echo showErrorMessage('status', $errors); ?>
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
                            <i class="fas fa-save me-1"></i> Guardar
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