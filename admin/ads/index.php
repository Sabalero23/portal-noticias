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

// Configuración de la página
$pageTitle = 'Gestión de Anuncios - Panel de Administración';
$currentMenu = 'ads';

// Configuración de paginación
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filtros
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$position = isset($_GET['position']) ? sanitize($_GET['position']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'id_desc';

// Construir consulta SQL
$sql = "SELECT id, title, position, url, start_date, end_date, status, impressions, clicks, priority 
        FROM ads";

$countSql = "SELECT COUNT(*) as total FROM ads";

$params = [];
$conditions = [];

// Aplicar filtros
if (!empty($search)) {
    $conditions[] = "title LIKE ? OR description LIKE ?";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if (!empty($position)) {
    $conditions[] = "position = ?";
    $params[] = $position;
}

if (!empty($status)) {
    $conditions[] = "status = ?";
    $params[] = $status;
}

// Aplicar condiciones WHERE si existen
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
    $countSql .= " WHERE " . implode(' AND ', $conditions);
}

// Determinar ordenación
list($sortField, $sortDirection) = explode('_', $sort);
$sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';

// Validar campo de ordenación
$allowedSortFields = ['id', 'title', 'position', 'impressions', 'clicks', 'priority', 'status', 'start_date'];
if (!in_array($sortField, $allowedSortFields)) {
    $sortField = 'id';
}

$sql .= " ORDER BY $sortField $sortDirection";

// Aplicar límites de paginación
$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

// Ejecutar consultas
$db = Database::getInstance();
$ads = $db->fetchAll($sql, $params);
$totalAds = $db->fetch($countSql, array_slice($params, 0, count($params) - 2));

$totalPages = ceil($totalAds['total'] / $perPage);

// Función para calcular la tasa de clics (CTR)
function calculateCTR($clicks, $impressions) {
    if ($impressions == 0) {
        return 0;
    }
    return ($clicks / $impressions) * 100;
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
                    <h1 class="m-0">Gestión de Anuncios</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Anuncios</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Estadísticas rápidas -->
            <div class="row">
                <?php
                // Obtener estadísticas
                $totalActive = $db->fetch("SELECT COUNT(*) as count FROM ads WHERE status = 'active'")['count'];
                $totalImpressions = $db->fetch("SELECT SUM(impressions) as total FROM ads")['total'] ?? 0;
                $totalClicks = $db->fetch("SELECT SUM(clicks) as total FROM ads")['total'] ?? 0;
                $overallCTR = calculateCTR($totalClicks, $totalImpressions);
                ?>
                
                <!-- Total de anuncios activos -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary"><i class="fas fa-ad"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Anuncios Activos</span>
                            <span class="info-box-number"><?php echo number_format($totalActive); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Total de impresiones -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-eye"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Impresiones</span>
                            <span class="info-box-number"><?php echo number_format($totalImpressions); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Total de clics -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-mouse-pointer"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Clics</span>
                            <span class="info-box-number"><?php echo number_format($totalClicks); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- CTR promedio -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-percentage"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">CTR Promedio</span>
                            <span class="info-box-number"><?php echo number_format($overallCTR, 2); ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulario de búsqueda y filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="index.php" method="get" class="mb-0">
                        <div class="row g-3">
                            <!-- Búsqueda -->
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar anuncios...">
                                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                            
                            <!-- Filtro por posición -->
                            <div class="col-md-3">
                                <select name="position" class="form-select">
                                    <option value="">Todas las posiciones</option>
                                    <option value="header" <?php echo $position === 'header' ? 'selected' : ''; ?>>Encabezado</option>
                                    <option value="left" <?php echo $position === 'left' ? 'selected' : ''; ?>>Columna izquierda</option>
                                    <option value="right" <?php echo $position === 'right' ? 'selected' : ''; ?>>Columna derecha</option>
                                    <option value="content" <?php echo $position === 'content' ? 'selected' : ''; ?>>Contenido</option>
                                    <option value="footer" <?php echo $position === 'footer' ? 'selected' : ''; ?>>Pie de página</option>
                                </select>
                            </div>
                            
                            <!-- Filtro por estado -->
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">Todos los estados</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                            
                            <!-- Ordenar por -->
                            <div class="col-md-2">
                                <select name="sort" class="form-select">
                                    <option value="id_desc" <?php echo $sort === 'id_desc' ? 'selected' : ''; ?>>Más recientes</option>
                                    <option value="id_asc" <?php echo $sort === 'id_asc' ? 'selected' : ''; ?>>Más antiguos</option>
                                    <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Título (A-Z)</option>
                                    <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Título (Z-A)</option>
                                    <option value="impressions_desc" <?php echo $sort === 'impressions_desc' ? 'selected' : ''; ?>>Más impresiones</option>
                                    <option value="clicks_desc" <?php echo $sort === 'clicks_desc' ? 'selected' : ''; ?>>Más clics</option>
                                    <option value="priority_desc" <?php echo $sort === 'priority_desc' ? 'selected' : ''; ?>>Mayor prioridad</option>
                                </select>
                            </div>
                            
                            <!-- Botón reset -->
                            <div class="col-md-1">
                                <a href="index.php" class="btn btn-secondary w-100">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Botón añadir anuncio -->
            <div class="mb-3">
                <a href="add.php" class="btn btn-success">
                    <i class="fas fa-plus-circle me-1"></i> Añadir Nuevo Anuncio
                </a>
            </div>
            
            <!-- Listado de anuncios -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Anuncios</h3>
                    <div class="card-tools">
                        <span class="badge bg-primary"><?php echo number_format($totalAds['total']); ?> anuncios</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th width="5%">ID</th>
                                    <th width="25%">Título</th>
                                    <th width="10%">Imagen</th>
                                    <th width="10%">Posición</th>
                                    <th width="10%">Período</th>
                                    <th width="10%">Estado</th>
                                    <th width="10%">Estadísticas</th>
                                    <th width="10%">Prioridad</th>
                                    <th width="10%">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ads)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">No se encontraron anuncios</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ads as $ad): ?>
                                        <tr>
                                            <td><?php echo $ad['id']; ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <strong><?php echo htmlspecialchars($ad['title']); ?></strong>
                                                    <small class="text-muted">
                                                        <a href="<?php echo $ad['url']; ?>" target="_blank" class="text-muted">
                                                            <i class="fas fa-external-link-alt me-1"></i><?php echo truncateString($ad['url'], 30); ?>
                                                        </a>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                // Obtener la ruta de la imagen
                                                $imagePath = $db->fetch("SELECT image FROM ads WHERE id = ?", [$ad['id']])['image'] ?? '';
                                                if (!empty($imagePath)):
                                                ?>
                                                    <a href="<?php echo BASE_PATH; ?>/<?php echo $imagePath; ?>" target="_blank">
                                                        <img src="/<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="img-thumbnail" style="max-height: 50px;">
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin imagen</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                switch ($ad['position']) {
                                                    case 'header':
                                                        echo '<span class="badge bg-primary">Encabezado</span>';
                                                        break;
                                                    case 'left':
                                                        echo '<span class="badge bg-secondary">Columna izquierda</span>';
                                                        break;
                                                    case 'right':
                                                        echo '<span class="badge bg-info">Columna derecha</span>';
                                                        break;
                                                    case 'content':
                                                        echo '<span class="badge bg-success">Contenido</span>';
                                                        break;
                                                    case 'footer':
                                                        echo '<span class="badge bg-dark">Pie de página</span>';
                                                        break;
                                                    default:
                                                        echo '<span class="badge bg-secondary">Otra</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($ad['start_date']) && !empty($ad['end_date'])): ?>
                                                    <small>
                                                        <i class="far fa-calendar-alt"></i> 
                                                        <?php echo date('d/m/Y', strtotime($ad['start_date'])); ?>
                                                        <br>
                                                        <i class="far fa-calendar-check"></i> 
                                                        <?php echo date('d/m/Y', strtotime($ad['end_date'])); ?>
                                                    </small>
                                                <?php elseif (!empty($ad['start_date'])): ?>
                                                    <small>
                                                        <i class="far fa-calendar-alt"></i> 
                                                        <?php echo date('d/m/Y', strtotime($ad['start_date'])); ?>
                                                        <br>
                                                        <span class="text-muted">Sin fecha de fin</span>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin período definido</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($ad['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="fas fa-eye"></i> <?php echo number_format($ad['impressions']); ?><br>
                                                    <i class="fas fa-mouse-pointer"></i> <?php echo number_format($ad['clicks']); ?><br>
                                                    <i class="fas fa-percentage"></i> <?php echo number_format(calculateCTR($ad['clicks'], $ad['impressions']), 2); ?>%
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $ad['priority'] > 5 ? 'warning' : 'secondary'; ?>">
                                                    <?php echo $ad['priority']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="edit.php?id=<?php echo $ad['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?php echo $ad['id']; ?>" class="btn btn-sm btn-danger btn-delete" title="Eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar este anuncio?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-4">
                    <nav aria-label="Paginación">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>">
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
                                echo '<li class="page-item"><a class="page-link" href="?page=1&q=' . urlencode($search) . '&position=' . urlencode($position) . '&status=' . urlencode($status) . '&sort=' . urlencode($sort) . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '&q=' . urlencode($search) . '&position=' . urlencode($position) . '&status=' . urlencode($status) . '&sort=' . urlencode($sort) . '">' . $i . '</a></li>';
                            }
                            
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&q=' . urlencode($search) . '&position=' . urlencode($position) . '&status=' . urlencode($status) . '&sort=' . urlencode($sort) . '">' . $totalPages . '</a></li>';
                            }
                            ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>">
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
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Scripts adicionales -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips
        const tooltips = document.querySelectorAll('[title]');
        if (tooltips.length > 0) {
            tooltips.forEach(tooltip => {
                new bootstrap.Tooltip(tooltip);
            });
        }
    });
</script>