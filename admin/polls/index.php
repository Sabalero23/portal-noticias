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
$auth->requirePermission(['admin', 'editor'], ADMIN_PATH . '/dashboard.php');

// Título de la página
$pageTitle = 'Gestión de Encuestas - Panel de Administración';
$currentMenu = 'polls';

// Parámetros de paginación y filtrado
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filtros
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';

// Preparar la consulta base
$db = Database::getInstance();
$where = [];
$params = [];

if (!empty($status)) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $where[] = "(p.question LIKE ? OR p.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

// Obtener total de registros
$totalQuery = "SELECT COUNT(*) as total FROM polls p" . $whereClause;
$totalResult = $db->fetch($totalQuery, $params);
$total = $totalResult['total'];
$totalPages = ceil($total / $perPage);

// Obtener encuestas con datos adicionales
$query = "SELECT p.id, p.question, p.status, p.start_date, p.end_date, p.created_at, 
                 (SELECT COUNT(*) FROM poll_options WHERE poll_id = p.id) as options_count,
                 (SELECT SUM(votes) FROM poll_options WHERE poll_id = p.id) as total_votes
          FROM polls p" . $whereClause . "
          ORDER BY p.created_at DESC
          LIMIT ? OFFSET ?";

// Añadir parámetros de paginación
$params[] = $perPage;
$params[] = $offset;

$polls = $db->fetchAll($query, $params);

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Token de seguridad inválido');
        redirect('index.php');
    }
    
    // Verificar ID
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        setFlashMessage('error', 'ID de encuesta inválido');
        redirect('index.php');
    }
    
    $pollId = (int)$_POST['id'];
    
    // Iniciar transacción
    $transaction = new Transaction();
    $transaction->begin();
    
    try {
        // Eliminar votos
        $db->query("DELETE FROM poll_votes WHERE poll_id = ?", [$pollId]);
        
        // Eliminar opciones
        $db->query("DELETE FROM poll_options WHERE poll_id = ?", [$pollId]);
        
        // Eliminar encuesta
        $db->query("DELETE FROM polls WHERE id = ?", [$pollId]);
        
        // Confirmar transacción
        $transaction->commit();
        
        // Registrar acción
        logAdminAction('delete', 'Encuesta eliminada: ID ' . $pollId, 'polls', $pollId);
        
        setFlashMessage('success', 'La encuesta ha sido eliminada correctamente');
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $transaction->rollback();
        setFlashMessage('error', 'Error al eliminar la encuesta: ' . $e->getMessage());
    }
    
    redirect('index.php');
}

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
                    <h1 class="m-0">Gestión de Encuestas</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Encuestas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Filtros y búsqueda -->
            <div class="card">
                <div class="card-body">
                    <form action="index.php" method="get" class="mb-0">
                        <div class="row">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Buscar encuestas..." name="q" value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <select name="status" class="form-select">
                                    <option value="">Todos los estados</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activas</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivas</option>
                                    <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Cerradas</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex">
                                <button type="submit" class="btn btn-primary me-2 flex-grow-1">Filtrar</button>
                                <a href="index.php" class="btn btn-secondary flex-grow-1">Limpiar</a>
                                <a href="add.php" class="btn btn-success ms-2 flex-grow-1"><i class="fas fa-plus-circle"></i> Nueva</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Listado de encuestas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Encuestas (<?php echo number_format($total); ?>)</h3>
                </div>
                <div class="card-body p-0">
                    <?php if (count($polls) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pregunta</th>
                                        <th>Opciones</th>
                                        <th>Votos</th>
                                        <th>Estado</th>
                                        <th>Fecha de inicio</th>
                                        <th>Fecha de fin</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($polls as $poll): ?>
                                        <tr>
                                            <td><?php echo $poll['id']; ?></td>
                                            <td>
                                                <a href="edit.php?id=<?php echo $poll['id']; ?>" class="text-decoration-none">
                                                    <?php echo truncateString(htmlspecialchars($poll['question']), 60); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $poll['options_count']; ?></td>
                                            <td><?php echo number_format($poll['total_votes'] ?? 0); ?></td>
                                            <td>
                                                <?php if ($poll['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Activa</span>
                                                <?php elseif ($poll['status'] === 'inactive'): ?>
                                                    <span class="badge bg-secondary">Inactiva</span>
                                                <?php elseif ($poll['status'] === 'closed'): ?>
                                                    <span class="badge bg-danger">Cerrada</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDate($poll['start_date']); ?></td>
                                            <td><?php echo $poll['end_date'] ? formatDate($poll['end_date']) : 'Sin fecha límite'; ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="results.php?id=<?php echo $poll['id']; ?>" class="btn btn-info btn-sm" title="Ver resultados">
                                                        <i class="fas fa-chart-bar"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $poll['id']; ?>" class="btn btn-primary btn-sm" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-sm btn-delete-poll" 
                                                            data-id="<?php echo $poll['id']; ?>" 
                                                            data-question="<?php echo htmlspecialchars($poll['question']); ?>"
                                                            title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer clearfix">
                                <ul class="pagination pagination-sm m-0 float-end">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&q=<?php echo urlencode($search); ?>">
                                                «
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">«</span>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?page=1&status=' . urlencode($status) . '&q=' . urlencode($search) . '">1</a></li>';
                                        if ($startPage > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        if ($i == $page) {
                                            echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                        } else {
                                            echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '&status=' . urlencode($status) . '&q=' . urlencode($search) . '">' . $i . '</a></li>';
                                        }
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&status=' . urlencode($status) . '&q=' . urlencode($search) . '">' . $totalPages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&q=<?php echo urlencode($search); ?>">
                                                »
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">»</span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="alert alert-info m-3">
                            No se encontraron encuestas. <a href="add.php" class="alert-link">Crear una nueva encuesta</a>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar la encuesta "<span id="pollQuestion"></span>"?</p>
                <p class="text-danger">Esta acción eliminará también todas las opciones y votos asociados, y no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <form action="index.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="pollId" value="">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Scripts específicos para esta página -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar modal de eliminación
        const deleteButtons = document.querySelectorAll('.btn-delete-poll');
        const pollIdInput = document.getElementById('pollId');
        const pollQuestionSpan = document.getElementById('pollQuestion');
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const pollId = this.getAttribute('data-id');
                const pollQuestion = this.getAttribute('data-question');
                
                pollIdInput.value = pollId;
                pollQuestionSpan.textContent = pollQuestion;
                
                deleteModal.show();
            });
        });
    });
</script>