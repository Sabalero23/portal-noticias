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
$pageTitle = 'Categorías - Panel de Administración';
$currentMenu = 'categories';

// Parámetros de paginación y búsqueda
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Búsqueda y filtrado
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'name_asc';

// Ordenación
$sortField = 'name';
$sortOrder = 'ASC';

if (strpos($sort, '_') !== false) {
    list($sortField, $sortOrder) = explode('_', $sort);
    $sortField = sanitize($sortField);
    $sortOrder = strtoupper(sanitize($sortOrder));
    
    // Verificar que son valores válidos
    $validFields = ['name', 'id', 'created_at'];
    $validOrders = ['ASC', 'DESC'];
    
    if (!in_array($sortField, $validFields)) {
        $sortField = 'name';
    }
    
    if (!in_array($sortOrder, $validOrders)) {
        $sortOrder = 'ASC';
    }
}

// Consulta base
$db = Database::getInstance();
$whereClause = '';
$params = [];

// Aplicar búsqueda
if (!empty($search)) {
    $whereClause = 'WHERE name LIKE ? OR description LIKE ?';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Obtener total de registros (para paginación)
$totalQuery = "SELECT COUNT(*) as total FROM categories $whereClause";
$totalResult = $db->fetch($totalQuery, $params);
$total = $totalResult['total'];
$totalPages = ceil($total / $perPage);

// Obtener categorías
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM news WHERE category_id = c.id) as news_count,
          p.name as parent_name
          FROM categories c
          LEFT JOIN categories p ON c.parent_id = p.id
          $whereClause
          ORDER BY $sortField $sortOrder
          LIMIT $perPage OFFSET $offset";

$categories = $db->fetchAll($query, $params);

// Obtener categorías para formulario de filtrado (solo nombres e IDs)
$allCategories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name ASC");

// Mensaje flash
$flashMessage = getFlashMessage();

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
                    <h1 class="m-0">Categorías</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Categorías</li>
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
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Gestión de categorías</h3>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nueva categoría
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Formulario de búsqueda -->
                    <form method="get" action="index.php" class="mb-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por nombre...">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <select name="sort" class="form-select">
                                    <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Nombre (A-Z)</option>
                                    <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Nombre (Z-A)</option>
                                    <option value="created_at_desc" <?php echo $sort === 'created_at_desc' ? 'selected' : ''; ?>>Más recientes</option>
                                    <option value="created_at_asc" <?php echo $sort === 'created_at_asc' ? 'selected' : ''; ?>>Más antiguas</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <a href="index.php" class="btn btn-outline-secondary w-100">Limpiar</a>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Tabla de categorías -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="20%">Nombre</th>
                                    <th width="10%">Color</th>
                                    <th width="15%">Categoría padre</th>
                                    <th width="30%">Descripción</th>
                                    <th width="10%">Noticias</th>
                                    <th width="10%">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($categories) > 0): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?php echo $category['id']; ?></td>
                                            <td>
                                                <a href="../category.php?slug=<?php echo $category['slug']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </a>
                                                <br>
                                                <small class="text-muted"><?php echo $category['slug']; ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="color-box me-2" style="background-color: <?php echo $category['color']; ?>"></span>
                                                    <?php echo $category['color']; ?>
                                                </div>
                                            </td>
                                            <td><?php echo !empty($category['parent_name']) ? htmlspecialchars($category['parent_name']) : '<span class="text-muted">Ninguna</span>'; ?></td>
                                            <td><?php echo !empty($category['description']) ? htmlspecialchars(truncateString($category['description'], 100)) : '<span class="text-muted">Sin descripción</span>'; ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $category['news_count']; ?></span>
                                            </td>
                                            <td>
                                                <a href="edit.php?id=<?php echo $category['id']; ?>" class="btn btn-sm btn-primary me-1" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger btn-delete" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <?php if (!empty($search)): ?>
                                                No se encontraron categorías que coincidan con la búsqueda.
                                                <br>
                                                <a href="index.php" class="btn btn-sm btn-outline-secondary mt-2">Ver todas las categorías</a>
                                            <?php else: ?>
                                                No hay categorías disponibles.
                                                <br>
                                                <a href="add.php" class="btn btn-sm btn-primary mt-2">Crear la primera categoría</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Paginación de categorías">
                            <ul class="pagination justify-content-center mt-4">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-angle-left"></i></span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&q=' . urlencode($search) . '&sort=' . $sort . '">1</a></li>';
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="?page=' . $i . '&q=' . urlencode($search) . '&sort=' . $sort . '">' . $i . '</a>';
                                    echo '</li>';
                                }
                                
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&q=' . urlencode($search) . '&sort=' . $sort . '">' . $totalPages . '</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-angle-right"></i></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .color-box {
        width: 18px;
        height: 18px;
        border-radius: 4px;
        display: inline-block;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }
</style>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>