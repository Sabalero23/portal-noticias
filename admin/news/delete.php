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
$auth->requirePermission(['admin', 'editor', 'author'], '../index.php');

// Verificar ID de noticia
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de noticia inválido');
    redirect('index.php');
}

$newsId = intval($_GET['id']);

// Obtener datos de la noticia
$db = Database::getInstance();
$news = $db->fetch(
    "SELECT n.*, u.name as author_name 
     FROM news n
     LEFT JOIN users u ON n.author_id = u.id
     WHERE n.id = ?",
    [$newsId]
);

// Verificar si existe la noticia
if (!$news) {
    setFlashMessage('error', 'Noticia no encontrada');
    redirect('index.php');
}

// Verificar permisos (solo el autor o administradores/editores pueden eliminar)
if (!hasRole(['admin', 'editor']) && $_SESSION['user']['id'] != $news['author_id']) {
    setFlashMessage('error', 'No tienes permisos para eliminar esta noticia');
    redirect('index.php');
}

// Los autores solo pueden eliminar sus propias noticias si no están publicadas
if (hasRole(['author']) && !hasRole(['admin', 'editor']) && $news['status'] === 'published') {
    setFlashMessage('error', 'No puedes eliminar una noticia publicada. Contáctate con un editor o administrador.');
    redirect('index.php');
}

// Verificar confirmación
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

// Verificar token CSRF para protección
$validToken = isset($_GET['token']) && verifyCsrfToken($_GET['token']);

// Si no está confirmado, mostrar página de confirmación
if (!$confirmed || !$validToken) {
    // Título de la página
    $pageTitle = 'Eliminar Noticia - Panel de Administración';
    $currentMenu = 'news';
    
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
                        <h1 class="m-0">Eliminar Noticia</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Noticias</a></li>
                            <li class="breadcrumb-item active">Eliminar</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contenido -->
        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Confirmar Eliminación</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <h5><i class="fas fa-exclamation-circle me-2"></i>¡Atención!</h5>
                                    <p>Estás a punto de eliminar la noticia <strong>"<?php echo htmlspecialchars($news['title']); ?>"</strong>.</p>
                                    <p>Esta acción no se puede deshacer y también eliminará todos los comentarios asociados a esta noticia.</p>
                                </div>
                                
                                <div class="news-info mb-4">
                                    <p><strong>Título:</strong> <?php echo htmlspecialchars($news['title']); ?></p>
                                    <p><strong>Autor:</strong> <?php echo htmlspecialchars($news['author_name']); ?></p>
                                    <p><strong>Estado:</strong> 
                                        <?php 
                                        switch ($news['status']) {
                                            case 'published':
                                                echo '<span class="badge bg-success">Publicada</span>';
                                                break;
                                            case 'draft':
                                                echo '<span class="badge bg-secondary">Borrador</span>';
                                                break;
                                            case 'pending':
                                                echo '<span class="badge bg-warning">Pendiente</span>';
                                                break;
                                            case 'trash':
                                                echo '<span class="badge bg-danger">Papelera</span>';
                                                break;
                                        }
                                        ?>
                                    </p>
                                    <p><strong>Fecha de creación:</strong> <?php echo formatDate($news['created_at'], 'd/m/Y H:i'); ?></p>
                                    
                                    <?php if ($news['published_at']): ?>
                                        <p><strong>Fecha de publicación:</strong> <?php echo formatDate($news['published_at'], 'd/m/Y H:i'); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-center gap-3">
                                    <a href="delete.php?id=<?php echo $newsId; ?>&confirm=yes&token=<?php echo generateCsrfToken(); ?>" class="btn btn-danger">
                                        <i class="fas fa-trash me-1"></i> Sí, Eliminar Noticia
                                    </a>
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php 
    include_once ADMIN_PATH . '/includes/footer.php';
    exit;
}

// Procesar eliminación
try {
    // Iniciar transacción
    $transaction = new Transaction();
    $transaction->begin();
    
    // Guardar información para el log
    $newsTitle = $news['title'];
    
    // 1. Eliminar relaciones con etiquetas
    $db->query(
        "DELETE FROM news_tags WHERE news_id = ?",
        [$newsId]
    );
    
    // 2. Eliminar comentarios
    $db->query(
        "DELETE FROM comments WHERE news_id = ?",
        [$newsId]
    );
    
    // 3. Eliminar registros de vistas
    $db->query(
        "DELETE FROM view_logs WHERE news_id = ?",
        [$newsId]
    );
    
    // 4. Eliminar la noticia
    $deleted = $db->query(
        "DELETE FROM news WHERE id = ?",
        [$newsId]
    );
    
    if (!$deleted) {
        throw new Exception('Error al eliminar la noticia');
    }
    
    // 5. Eliminar imágenes del servidor
    if (!empty($news['image']) && file_exists(BASE_PATH . '/' . $news['image'])) {
        @unlink(BASE_PATH . '/' . $news['image']);
    }
    
    if (!empty($news['thumbnail']) && $news['thumbnail'] !== $news['image'] && file_exists(BASE_PATH . '/' . $news['thumbnail'])) {
        @unlink(BASE_PATH . '/' . $news['thumbnail']);
    }
    
    // Confirmar transacción
    $transaction->commit();
    
    // Registrar acción
    logAdminAction(
        'delete_news',
        'Eliminó la noticia: ' . $newsTitle,
        'news',
        $newsId
    );
    
    // Mensaje de éxito
    setFlashMessage('success', 'Noticia eliminada correctamente');
    
} catch (Exception $e) {
    // Revertir transacción
    $transaction->rollback();
    
    // Mensaje de error
    setFlashMessage('error', 'Error al eliminar la noticia: ' . $e->getMessage());
}

// Redireccionar a la lista de noticias
redirect('index.php');