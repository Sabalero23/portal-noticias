<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Inicializar la DB
$db = Database::getInstance();

// Obtener el término de búsqueda
$q = isset($_GET['q']) ? sanitize($_GET['q']) : '';

// Obtener filtros adicionales
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$tag = isset($_GET['tag']) ? intval($_GET['tag']) : 0;
$date = isset($_GET['date']) ? sanitize($_GET['date']) : '';
$author = isset($_GET['author']) ? intval($_GET['author']) : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'date_desc';

// Configuración de paginación
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = getSetting('posts_per_page', 10);
$offset = ($page - 1) * $perPage;

// Validar término de búsqueda
if (empty($q) && !$category && !$tag && !$date && !$author) {
    redirect('index.php');
}

// Preparar consulta base
$baseQuery = "FROM news n
              JOIN categories c ON n.category_id = c.id
              JOIN users u ON n.author_id = u.id
              WHERE n.status = 'published'";
$params = [];

// Añadir condiciones según los filtros
if (!empty($q)) {
    // Buscar en título, extracto y contenido
    $baseQuery .= " AND (n.title LIKE ? OR n.excerpt LIKE ? OR n.content LIKE ?)";
    $searchTerm = '%' . $q . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($category > 0) {
    $baseQuery .= " AND n.category_id = ?";
    $params[] = $category;
}

if ($tag > 0) {
    $baseQuery .= " AND EXISTS (SELECT 1 FROM news_tags nt WHERE nt.news_id = n.id AND nt.tag_id = ?)";
    $params[] = $tag;
}

if (!empty($date)) {
    // Formato esperado: YYYY-MM
    if (preg_match('/^\d{4}-\d{2}$/', $date)) {
        $baseQuery .= " AND DATE_FORMAT(n.published_at, '%Y-%m') = ?";
        $params[] = $date;
    }
}

if ($author > 0) {
    $baseQuery .= " AND n.author_id = ?";
    $params[] = $author;
}

// Obtener total de resultados
$totalResults = $db->fetch(
    "SELECT COUNT(*) as total " . $baseQuery,
    $params
);

$totalPages = ceil($totalResults['total'] / $perPage);

// Definir orden
$orderBy = "n.published_at DESC"; // Default: más recientes primero

switch ($sort) {
    case 'date_asc':
        $orderBy = "n.published_at ASC";
        break;
    case 'title_asc':
        $orderBy = "n.title ASC";
        break;
    case 'title_desc':
        $orderBy = "n.title DESC";
        break;
    case 'views_desc':
        $orderBy = "n.views DESC";
        break;
}

// Obtener resultados paginados
$results = $db->fetchAll(
    "SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.views, n.published_at,
            c.id as category_id, c.name as category_name, c.slug as category_slug,
            u.id as author_id, u.name as author_name
     " . $baseQuery . "
     ORDER BY " . $orderBy . "
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

// Obtener categorías para el filtro
$categories = $db->fetchAll(
    "SELECT id, name, slug FROM categories ORDER BY name"
);

// Obtener etiquetas populares para el filtro
$popularTags = $db->fetchAll(
    "SELECT t.id, t.name, COUNT(nt.news_id) as count
     FROM tags t
     JOIN news_tags nt ON t.id = nt.tag_id
     GROUP BY t.id
     ORDER BY count DESC
     LIMIT 15"
);

// Obtener autores para el filtro
$authors = $db->fetchAll(
    "SELECT u.id, u.name, COUNT(n.id) as news_count
     FROM users u
     JOIN news n ON u.id = n.author_id
     WHERE n.status = 'published'
     GROUP BY u.id
     HAVING news_count > 0
     ORDER BY news_count DESC"
);

// Obtener fechas para el filtro
$dates = $db->fetchAll(
    "SELECT DATE_FORMAT(published_at, '%Y-%m') as month, 
            DATE_FORMAT(published_at, '%M %Y') as month_name,
            COUNT(*) as count
     FROM news
     WHERE status = 'published'
     GROUP BY month
     ORDER BY month DESC
     LIMIT 12"
);

// Configuración para la página
$pageTitle = 'Resultados de búsqueda' . (!empty($q) ? ' para: ' . $q : '') . ' - ' . getSetting('site_name', 'Portal de Noticias');
$metaDescription = 'Resultados de búsqueda' . (!empty($q) ? ' para: ' . $q : '') . ' en ' . getSetting('site_name', 'Portal de Noticias');

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Búsqueda</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Contenido Principal -->
<div class="container mt-4">
    <div class="row">
        <!-- Columna Principal (Resultados) -->
        <div class="col-lg-8">
            <!-- Formulario de búsqueda destacado -->
            <div class="search-container mb-4 p-4 bg-light rounded">
                <h3 class="mb-3">Buscar en Portal de Noticias</h3>
                <form action="search.php" method="get" class="search-form">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="q" placeholder="Buscar noticias..." value="<?php echo htmlspecialchars($q); ?>" aria-label="Buscar noticias">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Buscar</button>
                    </div>
                    
                    <!-- Filtros avanzados (inicialmente ocultos) -->
                    <div class="advanced-filters" id="advancedFilters">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="category" class="form-label">Categoría</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="0">Todas las categorías</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($category == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo $cat['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="tag" class="form-label">Etiqueta</label>
                                <select class="form-select" id="tag" name="tag">
                                    <option value="0">Todas las etiquetas</option>
                                    <?php foreach ($popularTags as $t): ?>
                                        <option value="<?php echo $t['id']; ?>" <?php echo ($tag == $t['id']) ? 'selected' : ''; ?>>
                                            <?php echo $t['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="date" class="form-label">Fecha</label>
                                <select class="form-select" id="date" name="date">
                                    <option value="">Cualquier fecha</option>
                                    <?php foreach ($dates as $d): ?>
                                        <option value="<?php echo $d['month']; ?>" <?php echo ($date == $d['month']) ? 'selected' : ''; ?>>
                                            <?php echo $d['month_name']; ?> (<?php echo $d['count']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="author" class="form-label">Autor</label>
                                <select class="form-select" id="author" name="author">
                                    <option value="0">Todos los autores</option>
                                    <?php foreach ($authors as $a): ?>
                                        <option value="<?php echo $a['id']; ?>" <?php echo ($author == $a['id']) ? 'selected' : ''; ?>>
                                            <?php echo $a['name']; ?> (<?php echo $a['news_count']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-12">
                                <label for="sort" class="form-label">Ordenar por</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="date_desc" <?php echo ($sort == 'date_desc') ? 'selected' : ''; ?>>Más recientes primero</option>
                                    <option value="date_asc" <?php echo ($sort == 'date_asc') ? 'selected' : ''; ?>>Más antiguos primero</option>
                                    <option value="title_asc" <?php echo ($sort == 'title_asc') ? 'selected' : ''; ?>>Título (A-Z)</option>
                                    <option value="title_desc" <?php echo ($sort == 'title_desc') ? 'selected' : ''; ?>>Título (Z-A)</option>
                                    <option value="views_desc" <?php echo ($sort == 'views_desc') ? 'selected' : ''; ?>>Más vistos</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-link p-0" id="toggleFilters">
                            <span id="toggleFiltersText">Mostrar filtros avanzados</span>
                            <i class="fas fa-chevron-down ms-1" id="toggleFiltersIcon"></i>
                        </button>
                        <button type="submit" class="btn btn-primary float-end">Aplicar filtros</button>
                    </div>
                </form>
            </div>
            
            <!-- Resultados de búsqueda -->
            <div class="search-results">
                <h3 class="section-title mb-3">
                    Resultados de búsqueda
                    <?php if (!empty($q)): ?>
                    para: <span class="text-primary">"<?php echo htmlspecialchars($q); ?>"</span>
                    <?php endif; ?>
                </h3>
                
                <div class="mb-3 text-muted">
                    <?php echo number_format($totalResults['total']); ?> resultados encontrados
                    <?php 
                    $appliedFilters = [];
                    
                    if ($category > 0) {
                        foreach ($categories as $cat) {
                            if ($cat['id'] == $category) {
                                $appliedFilters[] = 'Categoría: ' . $cat['name'];
                                break;
                            }
                        }
                    }
                    
                    if ($tag > 0) {
                        foreach ($popularTags as $t) {
                            if ($t['id'] == $tag) {
                                $appliedFilters[] = 'Etiqueta: ' . $t['name'];
                                break;
                            }
                        }
                    }
                    
                    if (!empty($date)) {
                        foreach ($dates as $d) {
                            if ($d['month'] == $date) {
                                $appliedFilters[] = 'Fecha: ' . $d['month_name'];
                                break;
                            }
                        }
                    }
                    
                    if ($author > 0) {
                        foreach ($authors as $a) {
                            if ($a['id'] == $author) {
                                $appliedFilters[] = 'Autor: ' . $a['name'];
                                break;
                            }
                        }
                    }
                    
                    if (!empty($appliedFilters)) {
                        echo ' • Filtros: ' . implode(', ', $appliedFilters);
                    }
                    ?>
                </div>
                
                <?php if (empty($results)): ?>
                <div class="alert alert-info">
                    No se encontraron resultados para tu búsqueda. Intenta con otras palabras o ajusta los filtros.
                </div>
                <?php endif; ?>
                
                <!-- Lista de resultados -->
                <div class="news-list">
                    <?php foreach ($results as $result): ?>
                    <div class="news-item card mb-4">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <a href="news.php?slug=<?php echo $result['slug']; ?>" class="h-100 d-block position-relative">
                                    <img src="<?php echo $result['image']; ?>" class="img-fluid rounded-start h-100 w-100 object-fit-cover" alt="<?php echo $result['title']; ?>">
                                    <span class="category-label"><?php echo $result['category_name']; ?></span>
                                </a>
                            </div>
                            <div class="col-md-8">
                                <div class="card-body">
                                    <h4 class="card-title">
                                        <a href="news.php?slug=<?php echo $result['slug']; ?>" class="text-decoration-none">
                                            <?php
                                            // Resaltar término de búsqueda en el título si existe
                                            if (!empty($q)) {
                                                echo preg_replace('/(' . preg_quote($q, '/') . ')/i', '<mark>$1</mark>', $result['title']);
                                            } else {
                                                echo $result['title'];
                                            }
                                            ?>
                                        </a>
                                    </h4>
                                    <p class="card-text">
                                        <?php
                                        // Resaltar término de búsqueda en el extracto si existe
                                        if (!empty($q)) {
                                            $excerpt = preg_replace('/(' . preg_quote($q, '/') . ')/i', '<mark>$1</mark>', $result['excerpt']);
                                            echo truncateString($excerpt, 150, true);
                                        } else {
                                            echo truncateString($result['excerpt'], 150);
                                        }
                                        ?>
                                    </p>
                                    <div class="news-meta text-muted small mb-2">
                                        <span class="me-3"><i class="fas fa-user-edit me-1"></i><?php echo $result['author_name']; ?></span>
                                        <span class="me-3"><i class="far fa-calendar-alt me-1"></i><?php echo formatDate($result['published_at'], 'd M, Y'); ?></span>
                                        <span><i class="far fa-eye me-1"></i><?php echo number_format($result['views']); ?> lecturas</span>
                                    </div>
                                    <a href="news.php?slug=<?php echo $result['slug']; ?>" class="btn btn-sm btn-primary">Leer más</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Paginación de resultados">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?q=<?php echo urlencode($q); ?>&category=<?php echo $category; ?>&tag=<?php echo $tag; ?>&date=<?php echo urlencode($date); ?>&author=<?php echo $author; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Columna Lateral (Sidebar) -->
        <div class="col-lg-4">
            <!-- Categorías -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Categorías</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($categories as $cat): 
                            $isActive = ($category == $cat['id']);
                            $count = $db->fetch(
                                "SELECT COUNT(*) as count FROM news WHERE category_id = ? AND status = 'published'", 
                                [$cat['id']]
                            );
                        ?>
                            <li class="list-group-item <?php echo $isActive ? 'active' : ''; ?>">
                                <a href="search.php?q=<?php echo urlencode($q); ?>&category=<?php echo $cat['id']; ?>&tag=<?php echo $tag; ?>&date=<?php echo urlencode($date); ?>&author=<?php echo $author; ?>&sort=<?php echo $sort; ?>" class="d-flex justify-content-between align-items-center text-decoration-none <?php echo $isActive ? 'text-white' : 'text-dark'; ?>">
                                    <?php echo $cat['name']; ?>
                                    <span class="badge <?php echo $isActive ? 'bg-white text-primary' : 'bg-primary text-white'; ?> rounded-pill"><?php echo $count['count']; ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Búsquedas populares -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Búsquedas populares</h5>
                </div>
                <div class="card-body">
                    <div class="tags-cloud">
                        <?php 
                        $popularSearches = $db->fetchAll(
                            "SELECT search_term, COUNT(*) as count
                             FROM search_logs
                             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                             GROUP BY search_term
                             ORDER BY count DESC
                             LIMIT 15"
                        );
                        
                        if (empty($popularSearches)): 
                        ?>
                            <div class="text-muted">No hay búsquedas populares disponibles</div>
                        <?php else: ?>
                            <?php foreach ($popularSearches as $search): ?>
                                <a href="search.php?q=<?php echo urlencode($search['search_term']); ?>" class="tag-link">
                                    <?php echo htmlspecialchars($search['search_term']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Etiquetas populares -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Etiquetas populares</h5>
                </div>
                <div class="card-body">
                    <div class="tags-cloud">
                        <?php foreach ($popularTags as $t): 
                            $isActive = ($tag == $t['id']);
                        ?>
                            <a href="search.php?q=<?php echo urlencode($q); ?>&category=<?php echo $category; ?>&tag=<?php echo $t['id']; ?>&date=<?php echo urlencode($date); ?>&author=<?php echo $author; ?>&sort=<?php echo $sort; ?>" class="tag-link <?php echo $isActive ? 'active' : ''; ?>">
                                <?php echo $t['name']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Autores populares -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Autores</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php 
                        $topAuthors = array_slice($authors, 0, 5);
                        
                        foreach ($topAuthors as $a): 
                            $isActive = ($author == $a['id']);
                        ?>
                            <li class="list-group-item <?php echo $isActive ? 'active' : ''; ?>">
                                <a href="search.php?q=<?php echo urlencode($q); ?>&category=<?php echo $category; ?>&tag=<?php echo $tag; ?>&date=<?php echo urlencode($date); ?>&author=<?php echo $a['id']; ?>&sort=<?php echo $sort; ?>" class="d-flex justify-content-between align-items-center text-decoration-none <?php echo $isActive ? 'text-white' : 'text-dark'; ?>">
                                    <?php echo $a['name']; ?>
                                    <span class="badge <?php echo $isActive ? 'bg-white text-primary' : 'bg-primary text-white'; ?> rounded-pill"><?php echo $a['news_count']; ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Suscripción Newsletter -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Newsletter</h5>
                </div>
                <div class="card-body">
                    <p>Recibe las últimas noticias en tu correo electrónico.</p>
                    <?php include 'includes/newsletter_form.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript para filtros avanzados -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/ocultar filtros avanzados
    const toggleFilters = document.getElementById('toggleFilters');
    const toggleFiltersText = document.getElementById('toggleFiltersText');
    const toggleFiltersIcon = document.getElementById('toggleFiltersIcon');
    const advancedFilters = document.getElementById('advancedFilters');
    
    // Verificar si algún filtro está activo
    const isFilterActive = <?php echo ($category > 0 || $tag > 0 || !empty($date) || $author > 0 || $sort != 'date_desc') ? 'true' : 'false'; ?>;
    
    // Mostrar filtros si están activos
    if (isFilterActive) {
        advancedFilters.style.display = 'block';
        toggleFiltersText.textContent = 'Ocultar filtros avanzados';
        toggleFiltersIcon.classList.remove('fa-chevron-down');
        toggleFiltersIcon.classList.add('fa-chevron-up');
    } else {
        advancedFilters.style.display = 'none';
    }
    
    // Manejar clic en el botón de alternar
    toggleFilters.addEventListener('click', function() {
        if (advancedFilters.style.display === 'none') {
            advancedFilters.style.display = 'block';
            toggleFiltersText.textContent = 'Ocultar filtros avanzados';
            toggleFiltersIcon.classList.remove('fa-chevron-down');
            toggleFiltersIcon.classList.add('fa-chevron-up');
        } else {
            advancedFilters.style.display = 'none';
            toggleFiltersText.textContent = 'Mostrar filtros avanzados';
            toggleFiltersIcon.classList.remove('fa-chevron-up');
            toggleFiltersIcon.classList.add('fa-chevron-down');
        }
    });
    
    // Registrar búsqueda en logs
    <?php if (!empty($q)): ?>
    fetch('log_search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'search_term=<?php echo urlencode($q); ?>&csrf_token=<?php echo generateCsrfToken(); ?>'
    });
    <?php endif; ?>
});
</script>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>>