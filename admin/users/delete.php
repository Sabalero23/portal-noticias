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

// No permitir eliminar el propio usuario
if ($userId == $_SESSION['user']['id']) {
    setFlashMessage('error', 'No puedes eliminar tu propia cuenta');
    redirect('index.php');
    exit;
}

// Inicializar DB
$db = Database::getInstance();

// Obtener datos del usuario a eliminar
$user = $db->fetch(
    "SELECT id, username, name FROM users WHERE id = ?",
    [$userId]
);

// Verificar si el usuario existe
if (!$user) {
    setFlashMessage('error', 'Usuario no encontrado');
    redirect('index.php');
    exit;
}

// Verificar si se ha enviado un token de confirmación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === '1') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Token de seguridad inválido');
        redirect('index.php');
        exit;
    }
    
    try {
        // Iniciar una transacción
        $transaction = new Transaction();
        $transaction->begin();
        
        // Guardar datos para el registro de actividad
        $username = $user['username'];
        
        // Eliminar usuario
        $deleted = $db->query("DELETE FROM users WHERE id = ?", [$userId]);
        
        if (!$deleted) {
            throw new Exception('Error al eliminar el usuario');
        }
        
        // Confirmar transacción
        $transaction->commit();
        
        // Registrar acción en el log
        logAdminAction('Eliminación de usuario', "Usuario eliminado: $username", 'users');
        
        // Mensaje de éxito
        setFlashMessage('success', 'Usuario eliminado correctamente');
        
        // Redirigir a la lista de usuarios
        redirect('index.php');
        exit;
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $transaction->rollback();
        
        // Mensaje de error
        setFlashMessage('error', 'Error al eliminar el usuario: ' . $e->getMessage());
        
        // Redirigir a la lista de usuarios
        redirect('index.php');
        exit;
    }
}

// Título de la página
$pageTitle = 'Eliminar Usuario - Panel de Administración';
$currentMenu = 'users';

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
                    <h1 class="m-0">Eliminar Usuario</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Usuarios</a></li>
                        <li class="breadcrumb-item active">Eliminar Usuario</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <div class="card card-danger">
                <div class="card-header">
                    <h3 class="card-title">Confirmar eliminación</h3>
                </div>
                
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>¡Advertencia!</h5>
                        <p>Estás a punto de eliminar al usuario <strong><?php echo htmlspecialchars($user['username']); ?></strong> (<?php echo htmlspecialchars($user['name']); ?>).</p>
                        <p>Esta acción no se puede deshacer y podría afectar el funcionamiento del sistema si el usuario tiene contenido asociado.</p>
                    </div>
                    
                    <form action="delete.php?id=<?php echo $userId; ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="confirm_delete" value="1">
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="confirm_checkbox" required>
                            <label class="form-check-label" for="confirm_checkbox">
                                Entiendo que esta acción no se puede deshacer
                            </label>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-danger" id="delete_button" disabled>
                                <i class="fas fa-trash me-1"></i> Eliminar usuario
                            </button>
                            <a href="index.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-arrow-left me-1"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Script específico para este formulario -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Habilitar/deshabilitar botón de eliminación según checkbox
    const confirmCheckbox = document.getElementById('confirm_checkbox');
    const deleteButton = document.getElementById('delete_button');
    
    if (confirmCheckbox && deleteButton) {
        confirmCheckbox.addEventListener('change', function() {
            deleteButton.disabled = !this.checked;
        });
    }
});
</script>