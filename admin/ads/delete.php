<?php
// Definir ruta base
define('BASE_PATH', dirname(dirname(__DIR__)));
define('ADMIN_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor'], 'index.php');

// Obtener ID del anuncio
$adId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($adId <= 0) {
    setFlashMessage('error', 'ID de anuncio inválido');
    redirect('index.php');
}

// Validar token CSRF si se proporcionó (para peticiones POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Token de seguridad inválido');
        redirect('index.php');
    }
    
    // Confirmar eliminación
    $confirmed = isset($_POST['confirm']) && $_POST['confirm'] === '1';
    
    if (!$confirmed) {
        setFlashMessage('error', 'Debes confirmar la eliminación');
        redirect('index.php');
    }
}

// Obtener información del anuncio
$db = Database::getInstance();
$ad = $db->fetch(
    "SELECT id, title, image FROM ads WHERE id = ?",
    [$adId]
);

if (!$ad) {
    setFlashMessage('error', 'Anuncio no encontrado');
    redirect('index.php');
}

// Procesar eliminación si es una petición POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Eliminar anuncio de la base de datos
    $deleted = $db->query(
        "DELETE FROM ads WHERE id = ?",
        [$adId]
    );
    
    if ($deleted) {
        // Eliminar imagen si existe
        if (!empty($ad['image']) && file_exists(BASE_PATH . '/' . $ad['image'])) {
            @unlink(BASE_PATH . '/' . $ad['image']);
        }
        
        // Registrar acción
        logAdminAction('Anuncio eliminado', 'Título: ' . $ad['title'], 'ads', $adId);
        
        // Mensaje de éxito
        setFlashMessage('success', 'Anuncio eliminado correctamente');
    } else {
        // Mensaje de error
        setFlashMessage('error', 'Error al eliminar el anuncio. Intenta nuevamente.');
    }
    
    // Redireccionar a la lista de anuncios
    redirect('index.php');
    exit;
}

// Configuración de la página
$pageTitle = 'Eliminar Anuncio - Panel de Administración';
$currentMenu = 'ads';

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
                    <h1 class="m-0">Eliminar Anuncio</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Anuncios</a></li>
                        <li class="breadcrumb-item active">Eliminar</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h3 class="card-title">Confirmar eliminación</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>¡Atención!</strong> Esta acción no se puede deshacer. Al eliminar el anuncio, se perderán todos sus datos y estadísticas.
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Información del anuncio:</h5>
                            <ul class="list-group">
                                <li class="list-group-item">
                                    <strong>ID:</strong> <?php echo $ad['id']; ?>
                                </li>
                                <li class="list-group-item">
                                    <strong>Título:</strong> <?php echo htmlspecialchars($ad['title']); ?>
                                </li>
                            </ul>
                        </div>
                        
                        <?php if (!empty($ad['image']) && file_exists(BASE_PATH . '/' . $ad['image'])): ?>
                            <div class="col-md-6">
                                <h5>Imagen:</h5>
                                <img src="../<?php echo $ad['image']; ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="img-thumbnail" style="max-height: 150px;">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form action="delete.php?id=<?php echo $adId; ?>" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="confirm" name="confirm" value="1" required>
                            <label class="form-check-label" for="confirm">
                                Confirmo que deseo eliminar permanentemente este anuncio y entiendo que esta acción no se puede deshacer.
                            </label>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-danger" id="delete-btn" disabled>
                                <i class="fas fa-trash me-1"></i> Eliminar Permanentemente
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
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Script para activar el botón de eliminación -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const confirmCheckbox = document.getElementById('confirm');
        const deleteButton = document.getElementById('delete-btn');
        
        confirmCheckbox.addEventListener('change', function() {
            deleteButton.disabled = !this.checked;
        });
    });
</script>