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

// Título de la página
$pageTitle = 'Gestión de Noticias - Panel de Administración';
$currentMenu = 'news';

// Parámetros de paginación y filtros
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Filtros
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$author = isset($_GET['author']) ? intval($_GET['author']) : 0;
$q = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'created_at_desc';

// Construir consulta SQL
$db = Database::getInstance();
$params = [];
$where = [];

// Filtro por estado
if (!empty($status)) {
    $where[] = "n.status = ?";
    $params[] = $status;
}

// Filtro por categoría
if ($category > 0) {
    $where[] = "n.category_id = ?";
    $params[] = $category;
}

// Filtro por autor
if ($author > 0) {
    $where[] = "n.author_id = ?";
    $params[] = $author;
}

// Filtro por término de búsqueda
if (!empty($q)) {
    $where[] = "(n.title LIKE ? OR n.excerpt LIKE ? OR n.content LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

// Restricción por rol (autores solo ven sus propias noticias)
if (hasRole(['author']) && !hasRole(['admin', 'editor'])) {
    $where[] = "n.author_id = ?";
    $params[] = $_SESSION['user']['id'];
}

// Ordenación
$sortField = 'n.created_at';
$sortDirection = 'DESC';

if (!empty($sort)) {
    $sortParts = explode('_', $sort);
    if (count($sortParts) == 2) {
        switch ($sortParts[0]) {
            case 'title':
                $sortField = 'n.title';
                break;
            case 'category':
                $sortField = 'c.name';
                break;
            case 'author':
                $sortField = 'u.name';
                break;
            case 'status':
                $sortField = 'n.status';
                break;
            case 'published':
                $sortField = 'n.published_at';
                break;
            case 'created':
                $sortField = 'n.created_at';
                break;
            case 'views':
                $sortField = 'n.views';
                break;
        }
        
        $sortDirection = strtoupper($sortParts[1]) === 'ASC' ? 'ASC' : 'DESC';
    }
}

// Construir cláusula WHERE
$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Obtener total de noticias para paginación
$countQuery = "SELECT COUNT(*) as total FROM news n $whereClause";
$totalResult = $db->fetch($countQuery, $params);
$totalNews = $totalResult ? $totalResult['total'] : 0;
$totalPages = ceil($totalNews / $perPage);

// Asegurarse de que la página actual es válida
if ($page < 1) $page = 1;
if ($totalPages > 0 && $page > $totalPages) $page = $totalPages;

// Recalcular offset con la página validada
$offset = ($page - 1) * $perPage;

// Obtener lista de noticias
$query = "SELECT n.id, n.title, n.slug, n.status, n.featured, n.views, n.created_at, n.published_at,
           c.name as category_name, c.slug as category_slug,
           u.name as author_name
           FROM news n
           LEFT JOIN categories c ON n.category_id = c.id
           LEFT JOIN users u ON n.author_id = u.id
           $whereClause
           ORDER BY $sortField $sortDirection
           LIMIT ? OFFSET ?";

// Crear una copia de los parámetros originales para la consulta de paginación
$queryParams = $params;
// Añadir parámetros de paginación
$queryParams[] = $perPage;
$queryParams[] = $offset;

$news = $db->fetchAll($query, $queryParams);

// Obtener categorías para filtro
$categories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");

// Obtener autores para filtro
if (hasRole(['admin', 'editor'])) {
    $authors = $db->fetchAll("SELECT id, name FROM users WHERE role IN ('admin', 'editor', 'author') ORDER BY name");
} else {
    $authors = [];
}

// Estados para filtro
$statuses = [
    'published' => 'Publicadas',
    'draft' => 'Borradores',
    'pending' => 'Pendientes',
    'trash' => 'Papelera'
];

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
                    <h1 class="m-0">Gestión de Noticias</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Noticias</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Botones de acción -->
            <div class="d-flex justify-content-between mb-4">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Añadir Nueva Noticia
                </a>
                <div>
                    <?php if (!empty($q) || !empty($status) || $category > 0 || $author > 0): ?>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Limpiar Filtros
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filtros de búsqueda -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="index.php" method="get" class="mb-0">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Buscar noticias...">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">Estado</option>
                                    <?php foreach ($statuses as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <select name="category" class="form-select">
                                    <option value="0">Categoría</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category === (int)$cat['id'] ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if (hasRole(['admin', 'editor'])): ?>
                            <div class="col-md-2">
                                <select name="author" class="form-select">
                                    <option value="0">Autor</option>
                                    <?php foreach ($authors as $auth): ?>
                                        <option value="<?php echo $auth['id']; ?>" <?php echo $author === (int)$auth['id'] ? 'selected' : ''; ?>><?php echo $auth['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tabla de noticias -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?php if (!empty($q)): ?>
                            Resultados para "<?php echo htmlspecialchars($q); ?>"
                        <?php elseif (!empty($status)): ?>
                            Noticias: <?php echo $statuses[$status]; ?>
                        <?php elseif ($category > 0): ?>
                            Categoría: <?php
                                $categoryName = '';
                                foreach ($categories as $cat) {
                                    if ($cat['id'] == $category) {
                                        $categoryName = $cat['name'];
                                        break;
                                    }
                                }
                                echo $categoryName;
                            ?>
                        <?php elseif ($author > 0): ?>
                            Autor: <?php
                                $authorName = '';
                                foreach ($authors as $auth) {
                                    if ($auth['id'] == $author) {
                                        $authorName = $auth['name'];
                                        break;
                                    }
                                }
                                echo $authorName;
                            ?>
                        <?php else: ?>
                            Todas las noticias
                        <?php endif; ?>
                        <span class="badge bg-primary ms-2"><?php echo $totalNews; ?></span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (count($news) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="35%">
                                            <?php echo generateSortLink('title', 'Título', $sort, [
                                                'q' => $q,
                                                'status' => $status,
                                                'category' => $category,
                                                'author' => $author
                                            ]); ?>
                                        </th>
                                        <th width="12%">
                                            <?php echo generateSortLink('category', 'Categoría', $sort, [
                                                'q' => $q,
                                                'status' => $status,
                                                'category' => $category,
                                                'author' => $author
                                            ]); ?>
                                        </th>
                                        <th width="12%">
                                            <?php echo generateSortLink('author', 'Autor', $sort, [
                                                'q' => $q,
                                                'status' => $status,
                                                'category' => $category,
                                                'author' => $author
                                            ]); ?>
                                        </th>
                                        <th width="10%">
                                            <?php echo generateSortLink('status', 'Estado', $sort, [
                                                'q' => $q,
                                                'status' => $status,
                                                'category' => $category,
                                                'author' => $author
                                            ]); ?>
                                        </th>
                                        <th width="10%">
                                            <?php echo generateSortLink('views', 'Vistas', $sort, [
                                                'q' => $q,
                                                'status' => $status,
                                                'category' => $category,
                                                'author' => $author
                                            ]); ?>
                                        </th>
                                        <th width="10%">
                                            <?php echo generateSortLink('created', 'Fecha', $sort, [
                                                'q' => $q,
                                                'status' => $status,
                                                'category' => $category,
                                                'author' => $author
                                            ]); ?>
                                        </th>
                                        <th width="15%" class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($news as $index => $item): ?>
                                        <tr>
                                            <td><?php echo $offset + $index + 1; ?></td>
                                            <td>
                                                <a href="edit.php?id=<?php echo $item['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </a>
                                                <?php if ($item['featured']): ?>
                                                    <span class="badge bg-warning ms-1" title="Destacada"><i class="fas fa-star"></i></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['author_name']); ?></td>
                                            <td>
                                                <?php 
                                                switch ($item['status']) {
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
                                            </td>
                                            <td><?php echo number_format($item['views']); ?></td>
                                            <td><?php echo formatDate($item['status'] === 'published' ? $item['published_at'] : $item['created_at'], 'd/m/Y'); ?></td>
                                            <td class="text-end">
                                                <!-- Acciones rápidas -->
                                                <?php if ($item['status'] === 'published'): ?>
                                                    <a href="<?php echo SITE_URL . '/news.php?slug=' . $item['slug']; ?>" target="_blank" class="btn btn-sm btn-info" title="Ver">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <?php if (hasRole(['admin', 'editor']) || ($item['status'] !== 'published' && $_SESSION['user']['id'] == $item['author_id'])): ?>
                                                    <a href="delete.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger btn-delete" title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($totalPages > 1): ?>
                            <div class="d-flex justify-content-center mt-4">
                                <nav aria-label="Paginación">
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo buildUrl('index.php', ['page' => $page - 1, 'q' => $q, 'status' => $status, 'category' => $category, 'author' => $author, 'sort' => $sort]); ?>" aria-label="Anterior">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">&laquo;</span>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // Determinar rango de páginas a mostrar
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $startPage + 4);
                                        
                                        // Ajustar si estamos cerca del final
                                        if ($endPage - $startPage < 4) {
                                            $startPage = max(1, $endPage - 4);
                                        }
                                        
                                        // Mostrar primer página si no está en el rango
                                        if ($startPage > 1) {
                                            echo '<li class="page-item"><a class="page-link" href="' . buildUrl('index.php', ['page' => 1, 'q' => $q, 'status' => $status, 'category' => $category, 'author' => $author, 'sort' => $sort]) . '">1</a></li>';
                                            if ($startPage > 2) {
                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                        }
                                        
                                        // Mostrar páginas del rango calculado
                                        for ($i = $startPage; $i <= $endPage; $i++) {
                                            if ($i == $page) {
                                                echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                            } else {
                                                echo '<li class="page-item"><a class="page-link" href="' . buildUrl('index.php', ['page' => $i, 'q' => $q, 'status' => $status, 'category' => $category, 'author' => $author, 'sort' => $sort]) . '">' . $i . '</a></li>';
                                            }
                                        }
                                        
                                        // Mostrar última página si no está en el rango
                                        if ($endPage < $totalPages) {
                                            if ($endPage < $totalPages - 1) {
                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                            echo '<li class="page-item"><a class="page-link" href="' . buildUrl('index.php', ['page' => $totalPages, 'q' => $q, 'status' => $status, 'category' => $category, 'author' => $author, 'sort' => $sort]) . '">' . $totalPages . '</a></li>';
                                        }
                                        ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo buildUrl('index.php', ['page' => $page + 1, 'q' => $q, 'status' => $status, 'category' => $category, 'author' => $author, 'sort' => $sort]); ?>" aria-label="Siguiente">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">&raquo;</span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">No se encontraron noticias que coincidan con los criterios seleccionados.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Función auxiliar para construir URLs con parámetros -->
<?php
function buildUrl($base, $params) {
    $url = $base;
    $query = [];
    
    foreach ($params as $key => $value) {
        if ($value !== '' && $value !== null && ($key !== 'page' || $value > 1) && 
            ($key !== 'category' || $value > 0) && ($key !== 'author' || $value > 0)) {
            $query[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    
    if (!empty($query)) {
        $url .= '?' . implode('&', $query);
    }
    
    return $url;
}
?>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>