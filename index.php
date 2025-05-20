<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Configuración de paginación
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 25; // Mostrar 25 noticias por página
$offset = ($page - 1) * $perPage;

// Obtener categorías para el menú
$db = Database::getInstance();
$categories = $db->fetchAll("SELECT id, name, slug FROM categories ORDER BY name");

// Obtener noticias destacadas para el slider
$featuredNews = $db->fetchAll(
    "SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.published_at, 
            c.name as category_name, c.slug as category_slug,
            u.name as author_name
     FROM news n
     JOIN categories c ON n.category_id = c.id
     JOIN users u ON n.author_id = u.id
     WHERE n.status = 'published' AND n.featured = 1
     ORDER BY n.published_at DESC
     LIMIT 5"
);

// Obtener noticias recientes (paginadas)
$totalNews = $db->fetch("SELECT COUNT(*) as total FROM news WHERE status = 'published'");
$totalPages = ceil($totalNews['total'] / $perPage);

$recentNews = $db->fetchAll(
    "SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.published_at, 
            c.name as category_name, c.slug as category_slug,
            u.name as author_name
     FROM news n
     JOIN categories c ON n.category_id = c.id
     JOIN users u ON n.author_id = u.id
     WHERE n.status = 'published'
     ORDER BY n.published_at DESC
     LIMIT ? OFFSET ?",
    [$perPage, $offset]
);

// Obtener noticias populares
$popularNews = $db->fetchAll(
    "SELECT n.id, n.title, n.slug, n.views, n.image
     FROM news n
     WHERE n.status = 'published'
     ORDER BY n.views DESC
     LIMIT 5"
);

// Obtener encuesta activa
$activePoll = $db->fetch(
    "SELECT p.id, p.question
     FROM polls p
     WHERE p.status = 'active' AND p.start_date <= NOW() AND (p.end_date IS NULL OR p.end_date >= NOW())
     ORDER BY p.start_date DESC
     LIMIT 1"
);

if ($activePoll) {
    $pollOptions = $db->fetchAll(
        "SELECT id, option_text, votes
         FROM poll_options
         WHERE poll_id = ?
         ORDER BY id",
        [$activePoll['id']]
    );
}

// Obtener anuncios activos por posición
$ads = [
    'header' => $db->fetch(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'header' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY RAND()
         LIMIT 1"
    ),
    'left' => $db->fetchAll(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'left' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY priority DESC, RAND()
         LIMIT 5"
    ),
    'right' => $db->fetchAll(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'right' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY priority DESC, RAND()
         LIMIT 5"
    ),
    'content' => $db->fetch(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'content' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY RAND()
         LIMIT 1"
    ),
    'left_bottom' => $db->fetchAll(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'left_bottom' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY priority DESC, RAND()
         LIMIT 3"
    ),
    'right_bottom' => $db->fetchAll(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'right_bottom' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY priority DESC, RAND()
         LIMIT 3"
    ),
    'left_extra' => $db->fetchAll(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'left_extra' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY priority DESC, RAND()
         LIMIT 2"
    ),
    'right_extra' => $db->fetchAll(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'right_extra' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY priority DESC, RAND()
         LIMIT 2"
    ),
    'footer' => $db->fetch(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'footer' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY RAND()
         LIMIT 1"
    )
];

// Registrar impresiones de anuncios
foreach ($ads as $position => $adData) {
    if (is_array($adData) && !isset($adData[0]) && isset($adData['id'])) {
        // Anuncio único
        $db->query(
            "UPDATE ads SET impressions = impressions + 1 WHERE id = ?",
            [$adData['id']]
        );
    } elseif (is_array($adData) && isset($adData[0])) {
        // Array de anuncios
        foreach ($adData as $ad) {
            if (isset($ad['id'])) {
                $db->query(
                    "UPDATE ads SET impressions = impressions + 1 WHERE id = ?",
                    [$ad['id']]
                );
            }
        }
    }
}

// Obtener etiquetas populares
$popularTags = $db->fetchAll(
    "SELECT t.id, t.name, t.slug, COUNT(nt.news_id) as news_count
     FROM tags t
     JOIN news_tags nt ON t.id = nt.tag_id
     JOIN news n ON nt.news_id = n.id
     WHERE n.status = 'published'
     GROUP BY t.id
     ORDER BY news_count DESC
     LIMIT 10"
);

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Slider / Noticias Destacadas -->
<div class="slider-container">
    <div id="main-slider" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <?php for ($i = 0; $i < count($featuredNews); $i++): ?>
                <button type="button" data-bs-target="#main-slider" data-bs-slide-to="<?php echo $i; ?>" <?php echo $i === 0 ? 'class="active"' : ''; ?>></button>
            <?php endfor; ?>
        </div>
        <div class="carousel-inner">
            <?php foreach ($featuredNews as $index => $news): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <img src="<?php echo $news['image']; ?>" class="d-block w-100" alt="<?php echo $news['title']; ?>">
                    <div class="carousel-caption">
                        <span class="category-badge"><?php echo $news['category_name']; ?></span>
                        <h2><a href="news.php?slug=<?php echo $news['slug']; ?>"><?php echo $news['title']; ?></a></h2>
                        <p class="d-none d-md-block"><?php echo truncateString($news['excerpt'], 150); ?></p>
                        <div class="meta">
                            <span class="author"><?php echo $news['author_name']; ?></span>
                            <span class="date"><?php echo formatDate($news['published_at'], 'd M, Y'); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#main-slider" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#main-slider" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
        </button>
    </div>
</div>

<!-- Contenido Principal (3 columnas) -->
<div class="container mt-4">
    <div class="row equal-height-row">
        <!-- Columna Izquierda (20% - Publicidad) -->
        <div class="col-lg-3 col-md-4 order-3 order-md-1">
            <!-- Anuncios Laterales Izquierda -->
            <?php if (!empty($ads['left'])): ?>
                <div class="sidebar-ads mb-4">
                    <?php foreach ($ads['left'] as $ad): ?>
                        <div class="ad-container text-center mb-3">
                            <a href="<?php echo $ad['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ad['id']; ?>">
                                <img src="<?php echo $ad['image']; ?>" alt="<?php echo $ad['title']; ?>" class="img-fluid">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Categorías -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Categorías</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($categories as $category): ?>
                            <li class="list-group-item">
                                <a href="category.php?slug=<?php echo $category['slug']; ?>" class="d-flex justify-content-between align-items-center text-decoration-none text-dark">
                                    <?php echo $category['name']; ?>
                                    <span class="badge bg-primary rounded-pill">
                                        <?php 
                                        $count = $db->fetch("SELECT COUNT(*) as count FROM news WHERE category_id = ? AND status = 'published'", [$category['id']]);
                                        echo $count['count'];
                                        ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Anuncios Laterales Izquierda Debajo -->
            <?php if (!empty($ads['left_bottom'])): ?>
                <div class="sidebar-ads mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Publicidad</h5>
                        </div>
                        <div class="card-body p-2">
                            <?php foreach ($ads['left_bottom'] as $ad): ?>
                                <div class="ad-container text-center mb-3">
                                    <a href="<?php echo $ad['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ad['id']; ?>">
                                        <img src="<?php echo $ad['image']; ?>" alt="<?php echo $ad['title']; ?>" class="img-fluid">
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Etiquetas Populares -->
            <?php if (!empty($popularTags)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Etiquetas</h5>
                </div>
                <div class="card-body">
                    <div class="tags-cloud">
                        <?php foreach ($popularTags as $tag): ?>
                            <a href="tag.php?slug=<?php echo $tag['slug']; ?>" class="tag-link">
                                <?php echo $tag['name']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
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
            
            <!-- Anuncios Izquierda Extra -->
            <?php if (!empty($ads['left_extra'])): ?>
                <div class="sidebar-ads mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Contenido Patrocinado</h5>
                        </div>
                        <div class="card-body p-2">
                            <?php foreach ($ads['left_extra'] as $ad): ?>
                                <div class="ad-container text-center mb-3">
                                    <a href="<?php echo $ad['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ad['id']; ?>">
                                        <img src="<?php echo $ad['image']; ?>" alt="<?php echo $ad['title']; ?>" class="img-fluid">
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Columna Central (60% - Noticias) -->
        <div class="col-lg-6 col-md-8 order-1 order-md-2">
            <?php if (isset($ads['content']) && $ads['content']): ?>
                <div class="ad-container text-center mb-4">
                    <a href="<?php echo $ads['content']['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ads['content']['id']; ?>">
                        <img src="<?php echo $ads['content']['image']; ?>" alt="<?php echo $ads['content']['title']; ?>" class="img-fluid">
                    </a>
                </div>
            <?php endif; ?>

            <h3 class="section-title">Últimas Noticias</h3>
            
            <div class="news-list">
                <?php foreach ($recentNews as $news): ?>
                    <div class="news-item card mb-4">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <div class="position-relative h-100">
                                    <a href="news.php?slug=<?php echo $news['slug']; ?>">
                                        <img src="<?php echo $news['image']; ?>" class="img-fluid rounded-start h-100 w-100 object-fit-cover" alt="<?php echo $news['title']; ?>">
                                    </a>
                                    <span class="category-label"><?php echo $news['category_name']; ?></span>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card-body">
                                    <h4 class="card-title">
                                        <a href="news.php?slug=<?php echo $news['slug']; ?>" class="text-decoration-none"><?php echo $news['title']; ?></a>
                                    </h4>
                                    <p class="card-text"><?php echo truncateString($news['excerpt'], 150); ?></p>
                                    <div class="news-meta">
                                        <span class="author"><i class="fas fa-user-edit me-1"></i><?php echo $news['author_name']; ?></span>
                                        <span class="date"><i class="far fa-calendar-alt me-1"></i><?php echo formatDate($news['published_at'], 'd M, Y'); ?></span>
                                        <span class="comments">
                                            <?php 
                                            $commentCount = $db->fetch("SELECT COUNT(*) as count FROM comments WHERE news_id = ? AND status = 'approved'", [$news['id']]);
                                            echo '<i class="far fa-comment me-1"></i>' . $commentCount['count']; 
                                            ?>
                                        </span>
                                    </div>
                                    <a href="news.php?slug=<?php echo $news['slug']; ?>" class="btn btn-primary btn-sm mt-2">Leer más</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($recentNews)): ?>
                    <div class="alert alert-info">
                        No hay noticias disponibles en este momento.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <nav aria-label="Paginación de noticias">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Anterior">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                                    <a class="page-link" href="?page=' . $i . '">' . $i . '</a>
                                </li>';
                            }
                            
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                            }
                            ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Siguiente">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
            
            <!-- Anuncios después de paginación (posición footer) - ESTRUCTURADO IGUAL QUE ANUNCIOS DE CONTENIDO -->
            <?php if (isset($ads['footer']) && $ads['footer']): ?>
                <div class="ad-container text-center mb-4">
                    <a href="<?php echo $ads['footer']['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ads['footer']['id']; ?>">
                        <img src="<?php echo $ads['footer']['image']; ?>" alt="<?php echo $ads['footer']['title']; ?>" class="img-fluid">
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Columna Derecha (20% - Publicidad) -->
        <div class="col-lg-3 col-md-12 order-2 order-md-3">
            <!-- Formulario de Búsqueda -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Buscar</h5>
                    <?php include 'includes/search_form.php'; ?>
                </div>
            </div>
            
            <!-- Anuncios Laterales Derecha -->
            <?php if (!empty($ads['right'])): ?>
                <div class="sidebar-ads mb-4">
                    <?php foreach ($ads['right'] as $ad): ?>
                        <div class="ad-container text-center mb-3">
                            <a href="<?php echo $ad['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ad['id']; ?>">
                                <img src="<?php echo $ad['image']; ?>" alt="<?php echo $ad['title']; ?>" class="img-fluid">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Noticias Populares -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Noticias Populares</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($popularNews as $news): ?>
                            <li class="list-group-item">
                                <div class="popular-news-item d-flex">
                                    <div class="thumbnail me-3">
                                        <img src="<?php echo $news['image']; ?>" alt="<?php echo $news['title']; ?>" width="80" height="60" class="object-fit-cover">
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><a href="news.php?slug=<?php echo $news['slug']; ?>" class="text-decoration-none"><?php echo truncateString($news['title'], 50); ?></a></h6>
                                        <small class="text-muted"><?php echo number_format($news['views']); ?> lecturas</small>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
                        <!-- Encuesta Activa -->
            <?php if (isset($activePoll) && $activePoll): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Encuesta</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="card-title"><?php echo $activePoll['question']; ?></h6>
                        
                        <form id="poll-form" action="poll_vote.php" method="post" class="poll-form-ajax">
                            <input type="hidden" name="poll_id" value="<?php echo $activePoll['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            
                            <?php 
                            // Verificar si el usuario ya votó
                            $userVoted = false;
                            if (isset($_COOKIE['poll_' . $activePoll['id']])) {
                                $userVoted = true;
                            }
                            
                            // Calcular total de votos
                            $totalVotes = 0;
                            foreach ($pollOptions as $option) {
                                $totalVotes += $option['votes'];
                            }
                            ?>
                            
                            <?php if (!$userVoted): ?>
                                <!-- Formulario para votar -->
                                <?php foreach ($pollOptions as $option): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="option_id" id="option<?php echo $option['id']; ?>" value="<?php echo $option['id']; ?>" required>
                                        <label class="form-check-label" for="option<?php echo $option['id']; ?>">
                                            <?php echo $option['option_text']; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                
                                <button type="submit" class="btn btn-primary mt-2">Votar</button>
                            <?php else: ?>
                                <!-- Mostrar resultados -->
                                <?php foreach ($pollOptions as $option): 
                                    $percentage = $totalVotes > 0 ? round(($option['votes'] / $totalVotes) * 100) : 0;
                                ?>
                                    <div class="poll-result mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?php echo $option['option_text']; ?></span>
                                            <span><?php echo $percentage; ?>%</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="text-center mt-2">
                                    <small class="text-muted">Total votos: <?php echo $totalVotes; ?></small>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- Modal para mensajes de la encuesta -->
                <div class="modal fade" id="pollModal" tabindex="-1" aria-labelledby="pollModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="pollModalLabel">Encuesta</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <p id="pollModalMessage"></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Anuncios Derecha Extra -->
            <?php if (!empty($ads['right_extra'])): ?>
                <div class="sidebar-ads mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recomendados para ti</h5>
                        </div>
                        <div class="card-body p-2">
                            <?php foreach ($ads['right_extra'] as $ad): ?>
                                <div class="ad-container text-center mb-3">
                                    <a href="<?php echo $ad['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ad['id']; ?>">
                                        <img src="<?php echo $ad['image']; ?>" alt="<?php echo $ad['title']; ?>" class="img-fluid">
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            

            
            <!-- Clima (si está configurado correctamente) -->
            <?php if (isValidSetting('weather_api_key')): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Clima</h5>
                    </div>
                    <div class="card-body text-center">
                        <div id="weather-widget" class="py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-2 mb-0">Cargando información del clima...</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Anuncios Laterales Derecha Debajo -->
            <?php if (!empty($ads['right_bottom'])): ?>
                <div class="sidebar-ads mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Publicidad</h5>
                        </div>
                        <div class="card-body p-2">
                            <?php foreach ($ads['right_bottom'] as $ad): ?>
                                <div class="ad-container text-center mb-3">
                                    <a href="<?php echo $ad['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ad['id']; ?>">
                                        <img src="<?php echo $ad['image']; ?>" alt="<?php echo $ad['title']; ?>" class="img-fluid">
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Código para capturar "admin" y redirigir al panel de administración
(function() {
    let adminSequence = "";
    const targetWord = "admin";

    // Crear el elemento de notificación pero no agregarlo al DOM todavía
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 20px 40px;
        border-radius: 8px;
        font-size: 18px;
        font-weight: bold;
        z-index: 9999;
        opacity: 0;
        transition: opacity 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    `;
    notification.textContent = 'Redirigiendo a Admin...';
    
    document.addEventListener('keydown', function(event) {
        // Solo detectar letras a-z
        if (/^[a-zA-Z]$/.test(event.key)) {
            // Agregar la letra a la secuencia
            adminSequence += event.key.toLowerCase();
            
            // Si la secuencia es más larga que la palabra objetivo, eliminar caracteres antiguos
            if (adminSequence.length > targetWord.length) {
                adminSequence = adminSequence.substring(adminSequence.length - targetWord.length);
            }
            
            // Comprobar si la secuencia coincide con la palabra objetivo
            if (adminSequence === targetWord) {
                // Mostrar notificación
                document.body.appendChild(notification);
                
                // Hacer visible la notificación con transición
                setTimeout(() => {
                    notification.style.opacity = "1";
                }, 10);
                
                // Esperar un momento y luego redirigir
                setTimeout(() => {
                    window.location.href = "admin/";
                }, 1500); // Esperar 1.5 segundos antes de redireccionar
                
                // Limpiar la secuencia
                adminSequence = "";
            }
        } else {
            // Si se presiona cualquier otra tecla, reiniciar la secuencia
            adminSequence = "";
        }
    });
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si existe el formulario de encuesta
    const pollForm = document.getElementById('poll-form');
    if (pollForm) {
        pollForm.addEventListener('submit', function(event) {
            // Detener el envío normal del formulario
            event.preventDefault();
            
            // Obtener los datos del formulario
            const formData = new FormData(pollForm);
            
            // Verificar si se seleccionó una opción
            if (!document.querySelector('input[name="option_id"]:checked')) {
                showPollModal('Error', 'Por favor, selecciona una opción para votar.');
                return false;
            }
            
            // Enviar solicitud mediante fetch API
            fetch('poll_vote.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Mostrar mensaje en modal
                showPollModal('Encuesta', data.message);
                
                // Si fue exitoso, actualizar la visualización de la encuesta
                if (data.success) {
                    updatePollResults(data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showPollModal('Error', 'Ocurrió un error al procesar tu voto. Por favor, intenta nuevamente.');
            });
            
            return false;
        });
    }
    
    // Función para mostrar modal
    function showPollModal(title, message) {
        const modalTitle = document.getElementById('pollModalLabel');
        const modalMessage = document.getElementById('pollModalMessage');
        
        modalTitle.textContent = title;
        modalMessage.textContent = message;
        
        // Mostrar el modal usando Bootstrap
        const modal = new bootstrap.Modal(document.getElementById('pollModal'));
        modal.show();
    }
    
    // Función para actualizar los resultados de la encuesta
    function updatePollResults(data) {
        const pollContainer = document.getElementById('poll-form').closest('.card-body');
        
        // Limpiar contenido actual
        pollContainer.innerHTML = '';
        
        // Mostrar la pregunta de la encuesta (si está disponible)
        const pollQuestion = document.querySelector('.card-title').textContent;
        const questionElement = document.createElement('h6');
        questionElement.className = 'card-title';
        questionElement.textContent = pollQuestion;
        pollContainer.appendChild(questionElement);
        
        // Crear contenedor de resultados
        data.options.forEach(option => {
            const resultDiv = document.createElement('div');
            resultDiv.className = 'poll-result mb-3';
            resultDiv.innerHTML = `
                <div class="d-flex justify-content-between mb-1">
                    <span>${option.text}</span>
                    <span>${option.percentage}%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: ${option.percentage}%" 
                        aria-valuenow="${option.percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            `;
            pollContainer.appendChild(resultDiv);
        });
        
        // Agregar total de votos
        const totalDiv = document.createElement('div');
        totalDiv.className = 'text-center mt-2';
        totalDiv.innerHTML = `<small class="text-muted">Total votos: ${data.total_votes}</small>`;
        pollContainer.appendChild(totalDiv);
    }
});
</script>
<?php
// Incluir pie de página
include 'includes/footer.php';
?>