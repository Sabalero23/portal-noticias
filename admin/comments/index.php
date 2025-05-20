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

// Título de la página
$pageTitle = 'Gestión de Comentarios - Panel de Administración';
$currentMenu = 'comments';

// Parámetros de paginación y filtrado
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filtros
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$newsId = isset($_GET['news_id']) ? (int)$_GET['news_id'] : 0;
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';

// Construir consulta base
$db = Database::getInstance();
$whereConditions = [];
$params = [];

// Aplicar filtros
if (!empty($status) && in_array($status, ['approved', 'pending', 'spam', 'trash'])) {
    $whereConditions[] = "c.status = ?";
    $params[] = $status;
}

if ($newsId > 0) {
    $whereConditions[] = "c.news_id = ?";
    $params[] = $newsId;
}

if (!empty($search)) {
    $whereConditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.comment LIKE ? OR n.title LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Construir cláusula WHERE
$whereClause = "";
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Obtener total de comentarios para paginación
$countQuery = "SELECT COUNT(*) as total 
               FROM comments c 
               LEFT JOIN news n ON c.news_id = n.id 
               $whereClause";
$totalComments = $db->fetch($countQuery, $params);
$totalPages = ceil($totalComments['total'] / $perPage);

// Ordenación
$sortColumn = isset($_GET['sort_column']) ? sanitize($_GET['sort_column']) : 'created_at';
$sortOrder = isset($_GET['sort_order']) && strtolower($_GET['sort_order']) === 'asc' ? 'ASC' : 'DESC';

// Validar columna de ordenación
$allowedColumns = ['created_at', 'name', 'email', 'status', 'news_title'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'created_at';
}

// Mapeo de columnas para la consulta
$columnMap = [
    'created_at' => 'c.created_at',
    'name' => 'c.name',
    'email' => 'c.email',
    'status' => 'c.status',
    'news_title' => 'n.title'
];

$orderBy = $columnMap[$sortColumn] . ' ' . $sortOrder;

// Obtener comentarios
$query = "SELECT c.id, c.name, c.email, c.website, c.comment, c.status, c.created_at, 
                 c.news_id, c.parent_id, n.title as news_title, n.slug as news_slug 
          FROM comments c
          LEFT JOIN news n ON c.news_id = n.id
          $whereClause
          ORDER BY $orderBy
          LIMIT $perPage OFFSET $offset";

$comments = $db->fetchAll($query, $params);

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
                    <h1 class="m-0">Gestión de Comentarios</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Comentarios</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Filtros y búsqueda -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="index.php" method="get">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar en comentarios...">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">Todos los estados</option>
                                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Aprobados</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                                    <option value="spam" <?php echo $status === 'spam' ? 'selected' : ''; ?>>Spam</option>
                                    <option value="trash" <?php echo $status === 'trash' ? 'selected' : ''; ?>>Eliminados</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <select name="news_id" class="form-select">
                                    <option value="0">Todas las noticias</option>
                                    <?php
                                    // Obtener noticias con comentarios
                                    $news = $db->fetchAll(
                                        "SELECT DISTINCT n.id, n.title 
                                         FROM news n 
                                         JOIN comments c ON n.id = c.news_id 
                                         ORDER BY n.title"
                                    );
                                    
                                    foreach ($news as $item) {
                                        $selected = $newsId == $item['id'] ? 'selected' : '';
                                        echo '<option value="' . $item['id'] . '" ' . $selected . '>' . htmlspecialchars($item['title']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="d-grid">
                                    <a href="index.php" class="btn btn-secondary">Limpiar</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Estadísticas rápidas -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Aprobados</span>
                            <span class="info-box-number">
                                <?php 
                                $approved = $db->fetch("SELECT COUNT(*) as count FROM comments WHERE status = 'approved'");
                                echo number_format($approved['count']);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Pendientes</span>
                            <span class="info-box-number">
                                <?php 
                                $pending = $db->fetch("SELECT COUNT(*) as count FROM comments WHERE status = 'pending'");
                                echo number_format($pending['count']);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger"><i class="fas fa-ban"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Spam</span>
                            <span class="info-box-number">
                                <?php 
                                $spam = $db->fetch("SELECT COUNT(*) as count FROM comments WHERE status = 'spam'");
                                echo number_format($spam['count']);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-secondary"><i class="fas fa-trash"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Eliminados</span>
                            <span class="info-box-number">
                                <?php 
                                $trash = $db->fetch("SELECT COUNT(*) as count FROM comments WHERE status = 'trash'");
                                echo number_format($trash['count']);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de comentarios -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?php 
                        if (!empty($search)) {
                            echo 'Resultados de búsqueda para: "' . htmlspecialchars($search) . '"';
                        } elseif (!empty($status)) {
                            $statusLabels = [
                                'approved' => 'Comentarios aprobados',
                                'pending' => 'Comentarios pendientes',
                                'spam' => 'Comentarios marcados como spam',
                                'trash' => 'Comentarios eliminados'
                            ];
                            echo $statusLabels[$status];
                        } else {
                            echo 'Todos los comentarios';
                        }
                        ?>
                    </h3>
                    
                    <div class="card-tools">
                        <?php if ($totalComments['total'] > 0): ?>
                            <div class="badge bg-secondary">
                                <?php echo number_format($totalComments['total']); ?> comentarios
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($comments)): ?>
                        <div class="alert alert-info m-3">
                            No se encontraron comentarios con los criterios especificados.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="checkAll">
                                            </div>
                                        </th>
                                        <th>
                                            <a href="?page=<?php echo $page; ?>&status=<?php echo $status; ?>&news_id=<?php echo $newsId; ?>&q=<?php echo urlencode($search); ?>&sort_column=name&sort_order=<?php echo $sortColumn === 'name' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>">
                                                Autor
                                                <?php if ($sortColumn === 'name'): ?>
                                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>Comentario</th>
                                        <th>
                                            <a href="?page=<?php echo $page; ?>&status=<?php echo $status; ?>&news_id=<?php echo $newsId; ?>&q=<?php echo urlencode($search); ?>&sort_column=news_title&sort_order=<?php echo $sortColumn === 'news_title' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>">
                                                Noticia
                                                <?php if ($sortColumn === 'news_title'): ?>
                                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?page=<?php echo $page; ?>&status=<?php echo $status; ?>&news_id=<?php echo $newsId; ?>&q=<?php echo urlencode($search); ?>&sort_column=created_at&sort_order=<?php echo $sortColumn === 'created_at' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>">
                                                Fecha
                                                <?php if ($sortColumn === 'created_at'): ?>
                                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?page=<?php echo $page; ?>&status=<?php echo $status; ?>&news_id=<?php echo $newsId; ?>&q=<?php echo urlencode($search); ?>&sort_column=status&sort_order=<?php echo $sortColumn === 'status' && $sortOrder === 'ASC' ? 'desc' : 'asc'; ?>">
                                                Estado
                                                <?php if ($sortColumn === 'status'): ?>
                                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comments as $comment): ?>
                                        <tr id="comment-<?php echo $comment['id']; ?>">
                                            <td>
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input comment-checkbox" value="<?php echo $comment['id']; ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($comment['name']); ?></strong>
                                                <div class="text-muted small"><?php echo htmlspecialchars($comment['email']); ?></div>
                                                <?php if (!empty($comment['website'])): ?>
                                                    <div class="small">
                                                        <a href="<?php echo htmlspecialchars($comment['website']); ?>" target="_blank" rel="nofollow">
                                                            <i class="fas fa-external-link-alt"></i> Web
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($comment['parent_id']): ?>
                                                    <div class="small text-muted mb-1">
                                                        <i class="fas fa-reply"></i> Respuesta a otro comentario
                                                    </div>
                                                <?php endif; ?>
                                                <div class="comment-text">
                                                    <?php echo nl2br(htmlspecialchars(truncateString($comment['comment'], 150))); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="../news/edit.php?id=<?php echo $comment['news_id']; ?>" title="Editar noticia">
                                                    <?php echo htmlspecialchars(truncateString($comment['news_title'], 40)); ?>
                                                </a>
                                                <div class="small">
                                                    <a href="../../news.php?slug=<?php echo $comment['news_slug']; ?>#comment-<?php echo $comment['id']; ?>" target="_blank" title="Ver en el sitio">
                                                        <i class="fas fa-eye"></i> Ver
                                                    </a>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo formatDate($comment['created_at'], 'd/m/Y H:i'); ?>
                                                <div class="small text-muted">
                                                    <?php echo timeAgo($comment['created_at']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                switch ($comment['status']) {
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
                                                    default:
                                                        echo '<span class="badge bg-info">Desconocido</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Acciones
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="edit.php?id=<?php echo $comment['id']; ?>">
                                                                <i class="fas fa-edit"></i> Editar
                                                            </a>
                                                        </li>
                                                        
                                                        <?php if ($comment['status'] === 'pending'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" href="approve.php?id=<?php echo $comment['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>">
                                                                    <i class="fas fa-check"></i> Aprobar
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($comment['status'] !== 'pending'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" href="edit.php?id=<?php echo $comment['id']; ?>&action=pending">
                                                                    <i class="fas fa-clock"></i> Marcar como pendiente
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($comment['status'] !== 'spam'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="edit.php?id=<?php echo $comment['id']; ?>&action=spam">
                                                                    <i class="fas fa-ban"></i> Marcar como spam
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <li><hr class="dropdown-divider"></li>
                                                        
                                                        <?php if ($comment['status'] !== 'trash'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="delete.php?id=<?php echo $comment['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>">
                                                                    <i class="fas fa-trash"></i> Eliminar
                                                                </a>
                                                            </li>
                                                        <?php else: ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" href="edit.php?id=<?php echo $comment['id']; ?>&action=restore">
                                                                    <i class="fas fa-trash-restore"></i> Restaurar
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-danger btn-delete" href="delete.php?id=<?php echo $comment['id']; ?>&permanent=1&csrf_token=<?php echo generateCsrfToken(); ?>">
                                                                    <i class="fas fa-times-circle"></i> Eliminar permanentemente
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Acciones en lote -->
                        <div class="p-3 bg-light border-top">
                            <div class="row align-items-center">
                                <div class="col-md-6 mb-2 mb-md-0">
                                    <div class="d-flex">
                                        <select id="bulk-action" class="form-select me-2" style="width: auto;">
                                            <option value="">Acciones en lote</option>
                                            <option value="approve">Aprobar</option>
                                            <option value="pending">Marcar como pendiente</option>
                                            <option value="spam">Marcar como spam</option>
                                            <option value="trash">Mover a la papelera</option>
                                            <?php if ($status === 'trash'): ?>
                                                <option value="restore">Restaurar</option>
                                                <option value="delete">Eliminar permanentemente</option>
                                            <?php endif; ?>
                                        </select>
                                        <button id="apply-bulk-action" class="btn btn-secondary">Aplicar</button>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <?php if ($totalPages > 1): ?>
                                        <nav aria-label="Paginación">
                                            <ul class="pagination justify-content-md-end mb-0">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&news_id=<?php echo $newsId; ?>&q=<?php echo urlencode($search); ?>&sort_column=<?php echo $sortColumn; ?>&sort_order=<?php echo $sortOrder; ?>">
                                                            <i class="fas fa-chevron-left"></i>
                                                        </a>
                                                    </li>
                                                <?php else: ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link"><i class="fas fa-chevron-left"></i></span>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($totalPages, $page + 2);
                                                
                                                if ($startPage > 1) {
                                                    echo '<li class="page-item"><a class="page-link" href="?page=1&status=' . $status . '&news_id=' . $newsId . '&q=' . urlencode($search) . '&sort_column=' . $sortColumn . '&sort_order=' . $sortOrder . '">1</a></li>';
                                                    if ($startPage > 2) {
                                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                    }
                                                }
                                                
                                                for ($i = $startPage; $i <= $endPage; $i++) {
                                                    if ($i == $page) {
                                                        echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                                    } else {
                                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '&status=' . $status . '&news_id=' . $newsId . '&q=' . urlencode($search) . '&sort_column=' . $sortColumn . '&sort_order=' . $sortOrder . '">' . $i . '</a></li>';
                                                    }
                                                }
                                                
                                                if ($endPage < $totalPages) {
                                                    if ($endPage < $totalPages - 1) {
                                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                    }
                                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&status=' . $status . '&news_id=' . $newsId . '&q=' . urlencode($search) . '&sort_column=' . $sortColumn . '&sort_order=' . $sortOrder . '">' . $totalPages . '</a></li>';
                                                }
                                                ?>
                                                
                                                <?php if ($page < $totalPages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&news_id=<?php echo $newsId; ?>&q=<?php echo urlencode($search); ?>&sort_column=<?php echo $sortColumn; ?>&sort_order=<?php echo $sortOrder; ?>">
                                                            <i class="fas fa-chevron-right"></i>
                                                        </a>
                                                    </li>
                                                <?php else: ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link"><i class="fas fa-chevron-right"></i></span>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para acciones en lote -->
<div class="modal fade" id="confirmBulkModal" tabindex="-1" aria-labelledby="confirmBulkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmBulkModalLabel">Confirmar acción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmBulkMessage">¿Estás seguro de que deseas realizar esta acción en los comentarios seleccionados?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmBulkAction">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Script para acciones en lote -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Seleccionar/deseleccionar todos
        const checkAll = document.getElementById('checkAll');
        const checkboxes = document.querySelectorAll('.comment-checkbox');
        
        if (checkAll) {
            checkAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
        
        // Actualizar "seleccionar todos" cuando se cambian los checkboxes individuales
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                checkAll.checked = [...checkboxes].every(cb => cb.checked);
            });
        });
        
        // Manejar acción en lote
        const applyBulkAction = document.getElementById('apply-bulk-action');
        const bulkAction = document.getElementById('bulk-action');
        const confirmBulkModal = new bootstrap.Modal(document.getElementById('confirmBulkModal'));
        const confirmBulkMessage = document.getElementById('confirmBulkMessage');
        const confirmBulkActionBtn = document.getElementById('confirmBulkAction');
        
        if (applyBulkAction) {
            applyBulkAction.addEventListener('click', function() {
                const action = bulkAction.value;
                const selected = [...checkboxes].filter(cb => cb.checked).map(cb => cb.value);
                
                if (!action) {
                    alert('Por favor, selecciona una acción');
                    return;
                }
                
                if (selected.length === 0) {
                    alert('Por favor, selecciona al menos un comentario');
                    return;
                }
                
                // Configurar mensajes para diferentes acciones
                let message = '';
                switch (action) {
                    case 'approve':
                        message = `¿Estás seguro de que deseas aprobar ${selected.length} comentario(s)?`;
                        break;
                    case 'pending':
                        message = `¿Estás seguro de que deseas marcar ${selected.length} comentario(s) como pendientes?`;
                        break;
                    case 'spam':
                        message = `¿Estás seguro de que deseas marcar ${selected.length} comentario(s) como spam?`;
                        break;
                    case 'trash':
                        message = `¿Estás seguro de que deseas mover ${selected.length} comentario(s) a la papelera?`;
                        break;
                    case 'restore':
                        message = `¿Estás seguro de que deseas restaurar ${selected.length} comentario(s)?`;
                        break;
                    case 'delete':
                        message = `¿Estás seguro de que deseas eliminar permanentemente ${selected.length} comentario(s)? Esta acción no se puede deshacer.`;
                        break;
                }
                
                confirmBulkMessage.textContent = message;
                
                // Configurar handler para confirmar acción
                confirmBulkActionBtn.onclick = function() {
                    // Crear un formulario para enviar la acción
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.action = 'bulk_action.php';
                    
                    // Agregar campo para acción
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = action;
                    form.appendChild(actionInput);
                    
                    // Agregar campo para CSRF token
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = '<?php echo generateCsrfToken(); ?>';
                    form.appendChild(csrfInput);
                    
                    // Agregar campos para IDs seleccionados
                    selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = id;
                        form.appendChild(input);
                    });
                    
                    // Enviar formulario
                    document.body.appendChild(form);
                    form.submit();
                };
                
                confirmBulkModal.show();
            });
        }
    });
</script>