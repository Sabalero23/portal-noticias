<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Obtener categorías para el menú
$db = Database::getInstance();
$categories = $db->fetchAll("SELECT id, name, slug FROM categories ORDER BY name");

// Verificar si se proporcionó un slug
if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    redirect('index.php');
}

// Sanitizar el slug
$slug = sanitize($_GET['slug']);

// Después de sanitizar el slug
echo "<!-- Debug: Slug después de sanitizar: " . htmlspecialchars($slug) . " -->";

// Inicializar la DB
$db = Database::getInstance();

// Obtener la categoría
$category = $db->fetch(
    "SELECT id, name, slug, description, color, image
     FROM categories
     WHERE slug = ?",
    [$slug]
);

// Después de obtener la categoría
echo "<!-- Debug: Categoría obtenida: " . print_r($category, true) . " -->";

// Si no existe la categoría, redirigir a la página principal
if (!$category) {
    setFlashMessage('error', 'La categoría solicitada no existe');
    redirect('index.php');
}

// Configuración de paginación
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = getSetting('posts_per_page', 10);
$offset = ($page - 1) * $perPage;

// Obtener total de noticias en esta categoría
$totalNews = $db->fetch(
    "SELECT COUNT(*) as total
     FROM news
     WHERE category_id = ? AND status = 'published'",
    [$category['id']]
);

$totalPages = ceil($totalNews['total'] / $perPage);

// Obtener noticias de esta categoría (paginadas)
$news = $db->fetchAll(
    "SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.views, n.published_at,
            u.name as author_name
     FROM news n
     JOIN users u ON n.author_id = u.id
     WHERE n.category_id = ? AND n.status = 'published'
     ORDER BY n.published_at DESC
     LIMIT ? OFFSET ?",
    [$category['id'], $perPage, $offset]
);

// Obtener subcategorías (si existen)
$subcategories = $db->fetchAll(
    "SELECT id, name, slug, IFNULL(description, '') as description
     FROM categories
     WHERE parent_id = ?
     ORDER BY name",
    [$category['id']]
);

// Obtener anuncios
$categoryAd = $db->fetch(
    "SELECT id, title, image, url
     FROM ads
     WHERE position = 'content' AND status = 'active'
     AND (sector = ? OR sector IS NULL)
     AND (start_date IS NULL OR start_date <= CURDATE())
     AND (end_date IS NULL OR end_date >= CURDATE())
     ORDER BY priority DESC, RAND()
     LIMIT 1",
    [$category['slug']]
);

// Configuración para la página
$pageTitle = $category['name'] . ' - ' . getSetting('site_name', 'Portal de Noticias');
$metaDescription = (isset($category['description']) && !empty($category['description'])) ? $category['description'] : 'Noticias sobre ' . $category['name'];
$customBgColor = $category['color'] ?: '#2196F3';

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Banner de categoría -->
<div class="category-banner" style="background-color: <?php echo $customBgColor; ?>;">
    <div class="container py-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="text-white mb-2"><?php echo $category['name']; ?></h1>
                <?php if (isset($category['description']) && !empty($category['description'])): ?>
<p class="text-white-50 mb-0"><?php echo $category['description']; ?></p>
<?php endif; ?>
            </div>
            <?php if (!empty($category['image'])): ?>
            <div class="col-md-4 text-end">
                <img src="<?php echo $category['image']; ?>" alt="<?php echo $category['name']; ?>" class="img-fluid rounded" style="max-height: 120px;">
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $category['name']; ?></li>
            </ol>
        </nav>
    </div>
</div>

<!-- Contenido Principal -->
<div class="container mt-4">
    <?php if (getFlashMessage('success')): ?>
        <div class="alert alert-success">
            <?php echo getFlashMessage('success', true); ?>
        </div>
    <?php endif; ?>
    
    <?php if (getFlashMessage('error')): ?>
        <div class="alert alert-danger">
            <?php echo getFlashMessage('error', true); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($subcategories)): ?>
    <!-- Subcategorías -->
    <div class="mb-4">
        <h5 class="mb-3">Subcategorías de <?php echo $category['name']; ?>:</h5>
        <div class="row g-3">
            <?php foreach ($subcategories as $subcategory): ?>
            <div class="col-md-4 col-sm-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="category.php?slug=<?php echo $subcategory['slug']; ?>" class="text-decoration-none">
                                <?php echo $subcategory['name']; ?>
                            </a>
                        </h5>
                        <?php if (isset($subcategory['description']) && !empty($subcategory['description'])): ?>
                        <p class="card-text small"><?php echo truncateString($subcategory['description'], 100); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent">
                        <?php 
                        $count = $db->fetch(
                            "SELECT COUNT(*) as count FROM news WHERE category_id = ? AND status = 'published'", 
                            [$subcategory['id']]
                        );
                        ?>
                        <small class="text-muted"><?php echo $count['count']; ?> artículos</small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($categoryAd): ?>
    <!-- Anuncio de categoría -->
    <div class="ad-container text-center mb-4">
        <a href="<?php echo $categoryAd['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $categoryAd['id']; ?>">
            <img src="<?php echo $categoryAd['image']; ?>" alt="<?php echo $categoryAd['title']; ?>" class="img-fluid">
        </a>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Columna Principal (Noticias) -->
        <div class="col-lg-8">
            <h3 class="section-title mb-4">Noticias en <?php echo $category['name']; ?></h3>
            
            <?php if (empty($news)): ?>
            <div class="alert alert-info">
                No hay noticias disponibles en esta categoría.
            </div>
            <?php endif; ?>
            
            <!-- Lista de noticias -->
            <div class="news-list">
                <?php foreach ($news as $newsItem): ?>
                <div class="news-item card mb-4">
                    <div class="row g-0">
                        <div class="col-md-4">
                            <a href="news.php?slug=<?php echo $newsItem['slug']; ?>" class="h-100 d-block position-relative">
                                <img src="<?php echo $newsItem['image']; ?>" class="img-fluid rounded-start h-100 w-100 object-fit-cover" alt="<?php echo $newsItem['title']; ?>">
                            </a>
                        </div>
                        <div class="col-md-8">
                            <div class="card-body">
                                <h4 class="card-title">
                                    <a href="news.php?slug=<?php echo $newsItem['slug']; ?>" class="text-decoration-none">
                                        <?php echo $newsItem['title']; ?>
                                    </a>
                                </h4>
                                <p class="card-text"><?php echo truncateString($newsItem['excerpt'], 150); ?></p>
                                <div class="news-meta text-muted small mb-2">
                                    <span class="me-3"><i class="fas fa-user-edit me-1"></i><?php echo $newsItem['author_name']; ?></span>
                                    <span class="me-3"><i class="far fa-calendar-alt me-1"></i><?php echo formatDate($newsItem['published_at'], 'd M, Y'); ?></span>
                                    <span><i class="far fa-eye me-1"></i><?php echo number_format($newsItem['views']); ?> lecturas</span>
                                </div>
                                <a href="news.php?slug=<?php echo $newsItem['slug']; ?>" class="btn btn-sm btn-primary">Leer más</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Paginación de noticias">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?slug=<?php echo $slug; ?>&page=<?php echo $page - 1; ?>" aria-label="Anterior">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?slug=' . $slug . '&page=1">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                            <a class="page-link" href="?slug=' . $slug . '&page=' . $i . '">' . $i . '</a>
                        </li>';
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?slug=' . $slug . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?slug=<?php echo $slug; ?>&page=<?php echo $page + 1; ?>" aria-label="Siguiente">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        
        <!-- Columna Lateral (Sidebar) -->
        <div class="col-lg-4">
            <!-- Formulario de Búsqueda -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Buscar</h5>
                    <?php include 'includes/search_form.php'; ?>
                </div>
            </div>
            
            <!-- Categorías -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Categorías</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php 
                        $categories = $db->fetchAll("SELECT id, name, slug FROM categories WHERE parent_id IS NULL ORDER BY name");
                        foreach ($categories as $cat):
                            $isActive = ($cat['id'] == $category['id']);
                            $count = $db->fetch("SELECT COUNT(*) as count FROM news WHERE category_id = ? AND status = 'published'", [$cat['id']]);
                        ?>
                            <li class="list-group-item <?php echo $isActive ? 'active' : ''; ?>">
                                <a href="category.php?slug=<?php echo $cat['slug']; ?>" class="d-flex justify-content-between align-items-center text-decoration-none <?php echo $isActive ? 'text-white' : 'text-dark'; ?>">
                                    <?php echo $cat['name']; ?>
                                    <span class="badge <?php echo $isActive ? 'bg-white text-primary' : 'bg-primary text-white'; ?> rounded-pill"><?php echo $count['count']; ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Noticias más populares de esta categoría -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Populares en <?php echo $category['name']; ?></h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php 
                        $popularInCategory = $db->fetchAll(
                            "SELECT id, title, slug, image, views
                             FROM news
                             WHERE status = 'published' AND category_id = ?
                             ORDER BY views DESC
                             LIMIT 5",
                            [$category['id']]
                        );
                        
                        if (empty($popularInCategory)): 
                        ?>
                            <li class="list-group-item text-center py-4">
                                <div class="text-muted">No hay noticias populares disponibles</div>
                            </li>
                        <?php else: ?>
                            <?php foreach ($popularInCategory as $popular): ?>
                                <li class="list-group-item">
                                    <div class="popular-news-item d-flex">
                                        <div class="thumbnail me-3">
                                            <img src="<?php echo $popular['image']; ?>" alt="<?php echo $popular['title']; ?>" width="80" height="60" class="object-fit-cover">
                                        </div>
                                        <div>
                                            <h6 class="mb-1">
                                                <a href="news.php?slug=<?php echo $popular['slug']; ?>" class="text-decoration-none">
                                                    <?php echo truncateString($popular['title'], 50); ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted"><?php echo number_format($popular['views']); ?> lecturas</small>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Etiquetas relacionadas con esta categoría -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Etiquetas relacionadas</h5>
                </div>
                <div class="card-body">
                    <div class="tags-cloud">
                        <?php 
                        $relatedTags = $db->fetchAll(
                            "SELECT t.id, t.name, t.slug, COUNT(nt.news_id) as news_count
                             FROM tags t
                             JOIN news_tags nt ON t.id = nt.tag_id
                             JOIN news n ON nt.news_id = n.id
                             WHERE n.status = 'published' AND n.category_id = ?
                             GROUP BY t.id
                             ORDER BY news_count DESC
                             LIMIT 20",
                            [$category['id']]
                        );
                        
                        if (empty($relatedTags)): 
                        ?>
                            <div class="text-muted">No hay etiquetas disponibles</div>
                        <?php else: ?>
                            <?php foreach ($relatedTags as $tag): ?>
                                <a href="tag.php?slug=<?php echo $tag['slug']; ?>" class="tag-link">
                                    <?php echo $tag['name']; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Suscripción Newsletter -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Newsletter</h5>
                </div>
                <div class="card-body">
                    <p>Recibe las últimas noticias de <?php echo $category['name']; ?> en tu correo.</p>
                    <?php include 'includes/newsletter_form.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>