<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Verificar si se proporcionó un slug
if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    redirect('index.php');
}

// Sanitizar el slug
$slug = sanitize($_GET['slug']);

// Inicializar la DB
$db = Database::getInstance();

// Obtener la etiqueta
$tag = $db->fetch(
    "SELECT id, name, slug, description
     FROM tags
     WHERE slug = ?",
    [$slug]
);

// Si no existe la etiqueta, redirigir a la página principal
if (!$tag) {
    setFlashMessage('error', 'La etiqueta solicitada no existe');
    redirect('index.php');
}

// Configuración de paginación
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = getSetting('posts_per_page', 10);
$offset = ($page - 1) * $perPage;

// Obtener total de noticias con esta etiqueta
$totalNews = $db->fetch(
    "SELECT COUNT(DISTINCT n.id) as total
     FROM news n
     JOIN news_tags nt ON n.id = nt.news_id
     WHERE nt.tag_id = ? AND n.status = 'published'",
    [$tag['id']]
);

$totalPages = ceil($totalNews['total'] / $perPage);

// Obtener noticias con esta etiqueta (paginadas)
$news = $db->fetchAll(
    "SELECT DISTINCT n.id, n.title, n.slug, n.excerpt, n.image, n.views, n.published_at,
            c.id as category_id, c.name as category_name, c.slug as category_slug,
            u.name as author_name
     FROM news n
     JOIN news_tags nt ON n.id = nt.news_id
     JOIN categories c ON n.category_id = c.id
     JOIN users u ON n.author_id = u.id
     WHERE nt.tag_id = ? AND n.status = 'published'
     ORDER BY n.published_at DESC
     LIMIT ? OFFSET ?",
    [$tag['id'], $perPage, $offset]
);

// Obtener etiquetas relacionadas
$relatedTags = $db->fetchAll(
    "SELECT t.id, t.name, t.slug, COUNT(nt2.news_id) as related_count
     FROM tags t
     JOIN news_tags nt ON t.id = nt.tag_id
     JOIN news_tags nt2 ON nt.news_id = nt2.news_id
     WHERE nt2.tag_id = ? AND t.id != ?
     GROUP BY t.id
     ORDER BY related_count DESC
     LIMIT 15",
    [$tag['id'], $tag['id']]
);

// Obtener anuncios
$tagAd = $db->fetch(
    "SELECT id, title, image, url
     FROM ads
     WHERE position = 'content' AND status = 'active'
     AND (start_date IS NULL OR start_date <= CURDATE())
     AND (end_date IS NULL OR end_date >= CURDATE())
     ORDER BY priority DESC, RAND()
     LIMIT 1"
);

// Configuración para la página
$pageTitle = 'Etiqueta: ' . $tag['name'] . ' - ' . getSetting('site_name', 'Portal de Noticias');
$metaDescription = $tag['description'] ?: 'Noticias etiquetadas con ' . $tag['name'];

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Banner de etiqueta -->
<div class="tag-banner bg-primary text-white">
    <div class="container py-3">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2">Etiqueta: <?php echo $tag['name']; ?></h1>
                <?php if ($tag['description']): ?>
                <p class="text-white-50 mb-0"><?php echo $tag['description']; ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge bg-light text-primary fs-6"><?php echo number_format($totalNews['total']); ?> artículos</span>
            </div>
        </div>
    </div>
</div>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Etiqueta: <?php echo $tag['name']; ?></li>
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
    
    <?php if ($tagAd): ?>
    <!-- Anuncio -->
    <div class="ad-container text-center mb-4">
        <a href="<?php echo $tagAd['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $tagAd['id']; ?>">
            <img src="<?php echo $tagAd['image']; ?>" alt="<?php echo $tagAd['title']; ?>" class="img-fluid">
        </a>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Columna Principal (Noticias) -->
        <div class="col-lg-8">
            <h3 class="section-title mb-4">Noticias etiquetadas con '<?php echo $tag['name']; ?>'</h3>
            
            <?php if (empty($news)): ?>
            <div class="alert alert-info">
                No hay noticias disponibles con esta etiqueta.
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
                                <span class="category-label"><?php echo $newsItem['category_name']; ?></span>
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
            
            <!-- Categorías de estas noticias -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Categorías relacionadas</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php 
                        $relatedCategories = $db->fetchAll(
                            "SELECT c.id, c.name, c.slug, COUNT(DISTINCT n.id) as news_count
                             FROM categories c
                             JOIN news n ON c.id = n.category_id
                             JOIN news_tags nt ON n.id = nt.news_id
                             WHERE nt.tag_id = ? AND n.status = 'published'
                             GROUP BY c.id
                             ORDER BY news_count DESC",
                            [$tag['id']]
                        );
                        
                        if (empty($relatedCategories)): 
                        ?>
                            <li class="list-group-item text-center py-4">
                                <div class="text-muted">No hay categorías relacionadas</div>
                            </li>
                        <?php else: ?>
                            <?php foreach ($relatedCategories as $cat): ?>
                                <li class="list-group-item">
                                    <a href="category.php?slug=<?php echo $cat['slug']; ?>" class="d-flex justify-content-between align-items-center text-decoration-none text-dark">
                                        <?php echo $cat['name']; ?>
                                        <span class="badge bg-primary rounded-pill"><?php echo $cat['news_count']; ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Etiquetas relacionadas -->
            <?php if (!empty($relatedTags)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Etiquetas relacionadas</h5>
                </div>
                <div class="card-body">
                    <div class="tags-cloud">
                        <?php foreach ($relatedTags as $relatedTag): ?>
                            <a href="tag.php?slug=<?php echo $relatedTag['slug']; ?>" class="tag-link">
                                <?php echo $relatedTag['name']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Noticias más populares con esta etiqueta -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Populares con esta etiqueta</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php 
                        $popularWithTag = $db->fetchAll(
                            "SELECT n.id, n.title, n.slug, n.image, n.views
                             FROM news n
                             JOIN news_tags nt ON n.id = nt.news_id
                             WHERE nt.tag_id = ? AND n.status = 'published'
                             ORDER BY n.views DESC
                             LIMIT 5",
                            [$tag['id']]
                        );
                        
                        if (empty($popularWithTag)): 
                        ?>
                            <li class="list-group-item text-center py-4">
                                <div class="text-muted">No hay noticias populares disponibles</div>
                            </li>
                        <?php else: ?>
                            <?php foreach ($popularWithTag as $popular): ?>
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

<?php
// Incluir pie de página
include 'includes/footer.php';
?>