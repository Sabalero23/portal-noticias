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
$auth->requirePermission(['admin', 'editor'], 'index.php');

// Verificar que tenemos un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('error', 'ID de comentario no proporcionado');
    redirect('index.php');
}

$commentId = (int)$_GET['id'];

// Obtener datos del comentario
$db = Database::getInstance();
$comment = $db->fetch(
    "SELECT c.*, n.title as news_title, n.slug as news_slug 
     FROM comments c
     LEFT JOIN news n ON c.news_id = n.id
     WHERE c.id = ?",
    [$commentId]
);

// Verificar si existe el comentario
if (!$comment) {
    setFlashMessage('error', 'Comentario no encontrado');
    redirect('index.php');
}

// Cambio rápido de estado (para acciones del listado)
if (isset($_GET['action']) && in_array($_GET['action'], ['pending', 'spam', 'restore'])) {
    $newStatus = $_GET['action'] === 'restore' ? 'approved' : $_GET['action'];
    
    $updated = $db->query(
        "UPDATE comments SET status = ? WHERE id = ?",
        [$newStatus, $commentId]
    );
    
    if ($updated) {
        $statusMessages = [
            'pending' => 'Comentario marcado como pendiente',
            'spam' => 'Comentario marcado como spam',
            'approved' => 'Comentario restaurado',
        ];
        
        setFlashMessage('success', $statusMessages[$newStatus]);
        
        // Registrar la acción
        logAdminAction(
            'update_comment_status', 
            "Status: " . $newStatus, 
            'comments', 
            $commentId
        );
    } else {
        setFlashMessage('error', 'Error al actualizar el estado del comentario');
    }
    
    redirect('index.php');
}

// Procesar formulario
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
        $website = isset($_POST['website']) ? sanitize($_POST['website']) : '';
        $commentText = isset($_POST['comment']) ? sanitize($_POST['comment']) : '';
        $status = isset($_POST['status']) ? sanitize($_POST['status']) : '';
        
        // Validaciones
        if (empty($name)) {
            $errors[] = 'El nombre es obligatorio';
        }
        
        if (empty($email)) {
            $errors[] = 'El email es obligatorio';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'El email no es válido';
        }
        
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            $errors[] = 'La URL del sitio web no es válida';
        }
        
        if (empty($commentText)) {
            $errors[] = 'El comentario no puede estar vacío';
        }
        
        if (empty($status) || !in_array($status, ['approved', 'pending', 'spam', 'trash'])) {
            $errors[] = 'El estado seleccionado no es válido';
        }
        
        // Si no hay errores, actualizar comentario
        if (empty($errors)) {
            $updated = $db->query(
                "UPDATE comments 
                 SET name = ?, email = ?, website = ?, comment = ?, status = ? 
                 WHERE id = ?",
                [$name, $email, $website, $commentText, $status, $commentId]
            );
            
            if ($updated) {
                $success = true;
                setFlashMessage('success', 'Comentario actualizado correctamente');
                
                // Registrar la acción
                logAdminAction('update_comment', '', 'comments', $commentId);
                
                // Recargar datos del comentario
                $comment = $db->fetch(
                    "SELECT c.*, n.title as news_title, n.slug as news_slug 
                     FROM comments c
                     LEFT JOIN news n ON c.news_id = n.id
                     WHERE c.id = ?",
                    [$commentId]
                );
            } else {
                $errors[] = 'Error al actualizar el comentario';
            }
        }
    }
}

// Título de la página
$pageTitle = 'Editar Comentario - Panel de Administración';
$currentMenu = 'comments';

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
                    <h1 class="m-0">Editar Comentario</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Comentarios</a></li>
                        <li class="breadcrumb-item active">Editar</li>
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
                    <i class="fas fa-check-circle me-2"></i>Comentario actualizado correctamente
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <!-- Formulario de edición -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Datos del comentario</h3>
                        </div>
                        
                        <div class="card-body">
                            <form action="edit.php?id=<?php echo $commentId; ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($comment['name']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($comment['email']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="website" class="form-label">Sitio web</label>
                                    <input type="url" class="form-control" id="website" name="website" value="<?php echo htmlspecialchars($comment['website']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Comentario <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="comment" name="comment" rows="8" required><?php echo htmlspecialchars($comment['comment']); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Estado <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="approved" <?php echo $comment['status'] === 'approved' ? 'selected' : ''; ?>>Aprobado</option>
                                        <option value="pending" <?php echo $comment['status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="spam" <?php echo $comment['status'] === 'spam' ? 'selected' : ''; ?>>Spam</option>
                                        <option value="trash" <?php echo $comment['status'] === 'trash' ? 'selected' : ''; ?>>Eliminado</option>
                                    </select>
                                </div>
                                
                                <div class="mt-4">
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
                
                <div class="col-md-4">
                    <!-- Información adicional -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Información adicional</h3>
                        </div>
                        
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <th>ID</th>
                                    <td><?php echo $commentId; ?></td>
                                </tr>
                                <tr>
                                    <th>Fecha</th>
                                    <td><?php echo formatDate($comment['created_at'], 'd/m/Y H:i'); ?></td>
                                </tr>
                                <tr>
                                    <th>Noticia</th>
                                    <td>
                                        <a href="../news/edit.php?id=<?php echo $comment['news_id']; ?>" title="Editar noticia">
                                            <?php echo htmlspecialchars($comment['news_title']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>IP</th>
                                    <td><?php echo htmlspecialchars($comment['ip_address']); ?></td>
                                </tr>
                                <tr>
                                    <th>Respuesta a</th>
                                    <td>
                                        <?php if ($comment['parent_id']): ?>
                                            <a href="edit.php?id=<?php echo $comment['parent_id']; ?>">
                                                Ver comentario padre
                                            </a>
                                        <?php else: ?>
                                            <em>No es una respuesta</em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="mt-3">
                                <a href="../../news.php?slug=<?php echo $comment['news_slug']; ?>#comment-<?php echo $commentId; ?>" class="btn btn-info btn-sm" target="_blank">
                                    <i class="fas fa-eye me-1"></i> Ver en el sitio
                                </a>
                                
                                <?php if ($comment['status'] !== 'trash'): ?>
                                    <a href="delete.php?id=<?php echo $commentId; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-danger btn-sm float-end btn-delete">
                                        <i class="fas fa-trash me-1"></i> Eliminar
                                    </a>
                                <?php else: ?>
                                    <a href="edit.php?id=<?php echo $commentId; ?>&action=restore" class="btn btn-success btn-sm">
                                        <i class="fas fa-trash-restore me-1"></i> Restaurar
                                    </a>
                                    <a href="delete.php?id=<?php echo $commentId; ?>&permanent=1&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-danger btn-sm float-end btn-delete">
                                        <i class="fas fa-times-circle me-1"></i> Eliminar permanentemente
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Respuestas a este comentario -->
                    <?php 
                    $replies = $db->fetchAll(
                        "SELECT id, name, comment, status, created_at 
                         FROM comments 
                         WHERE parent_id = ? 
                         ORDER BY created_at ASC",
                        [$commentId]
                    );
                    
                    if (!empty($replies)):
                    ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Respuestas a este comentario</h3>
                        </div>
                        
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($replies as $reply): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($reply['name']); ?></h6>
                                            <small class="text-muted"><?php echo formatDate($reply['created_at'], 'd/m/Y H:i'); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars(truncateString($reply['comment'], 100))); ?></p>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small>
                                                <?php
                                                switch ($reply['status']) {
                                                    case 'approved':
                                                        echo '<span class="badge bg-success">Aprobado</span>';
                                                        break;
                                                    case 'pending':
                                                        echo '<span class="badge bg-warning">Pendiente</span>';
                                                        break;
                                                    case 'spam':
                                                        echo '<span class="badge bg-danger">Spam</span>';
                                                        break;
                                                    case 'trash':
                                                        echo '<span class="badge bg-secondary">Eliminado</span>';
                                                        break;
                                                }
                                                ?>
                                            </small>
                                            <a href="edit.php?id=<?php echo $reply['id']; ?>" class="btn btn-sm btn-primary">Ver</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>