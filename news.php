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
    // Redireccionar a la página principal si no hay slug
    redirect('index.php');
}

$slug = sanitize($_GET['slug']);

// Obtener información de la noticia
$db = Database::getInstance();
$news = $db->fetch(
    "SELECT n.id, n.title, n.excerpt, n.content, n.image, n.views, n.allow_comments, 
            n.published_at, n.created_at, n.updated_at,
            c.id as category_id, c.name as category_name, c.slug as category_slug,
            u.id as author_id, u.name as author_name, u.bio as author_bio, u.avatar as author_avatar
     FROM news n
     JOIN categories c ON n.category_id = c.id
     JOIN users u ON n.author_id = u.id
     WHERE n.slug = ? AND n.status = 'published'",
    [$slug]
);

// Si no se encuentra la noticia, redireccionar
if (!$news) {
    setFlashMessage('error', 'La noticia solicitada no existe o no está disponible.');
    redirect('index.php');
}

// Incrementar contador de vistas
$db->query(
    "UPDATE news SET views = views + 1 WHERE id = ?",
    [$news['id']]
);

// Registrar vista en el log (opcional)
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$db->query(
    "INSERT INTO view_logs (news_id, ip_address, user_agent) VALUES (?, ?, ?)",
    [$news['id'], $ipAddress, $userAgent]
);

// Obtener etiquetas de la noticia
$tags = $db->fetchAll(
    "SELECT t.id, t.name, t.slug
     FROM tags t
     JOIN news_tags nt ON t.id = nt.tag_id
     WHERE nt.news_id = ?
     ORDER BY t.name",
    [$news['id']]
);

// Obtener noticias relacionadas (misma categoría, excluyendo la actual)
$relatedNews = $db->fetchAll(
    "SELECT n.id, n.title, n.slug, n.image, n.published_at
     FROM news n
     WHERE n.category_id = ? AND n.id != ? AND n.status = 'published'
     ORDER BY n.published_at DESC
     LIMIT 3",
    [$news['category_id'], $news['id']]
);

// Obtener categorías para el menú lateral
$categories = $db->fetchAll(
    "SELECT id, name, slug, 
            (SELECT COUNT(*) FROM news WHERE category_id = c.id AND status = 'published') as news_count
     FROM categories c
     ORDER BY news_count DESC, name ASC
     LIMIT 10"
);

// Obtener noticias populares
$popularNews = $db->fetchAll(
    "SELECT n.id, n.title, n.slug, n.views, n.image
     FROM news n
     WHERE n.status = 'published'
     ORDER BY n.views DESC
     LIMIT 5"
);

// Obtener comentarios aprobados
$comments = $db->fetchAll(
    "SELECT c.id, c.name, c.email, c.website, c.comment, c.created_at, c.parent_id
     FROM comments c
     WHERE c.news_id = ? AND c.status = 'approved'
     ORDER BY c.created_at DESC",
    [$news['id']]
);

// Estructurar comentarios en formato anidado
$commentTree = [];
foreach ($comments as $comment) {
    // Si es un comentario raíz
    if ($comment['parent_id'] === null) {
        $commentTree[$comment['id']] = [
            'comment' => $comment,
            'replies' => []
        ];
    } else {
        // Si es una respuesta
        if (isset($commentTree[$comment['parent_id']])) {
            $commentTree[$comment['parent_id']]['replies'][] = $comment;
        }
    }
}

// Obtener anuncios activos para las posiciones necesarias
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
    'footer' => $db->fetch(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'footer' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY RAND()
         LIMIT 1"
    ),
    'left_extra' => $db->fetchAll(
        "SELECT id, title, image, url
         FROM ads
         WHERE position = 'left_extra' AND status = 'active'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY priority DESC, RAND()
         LIMIT 2"
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

// Incluir cabecera
$pageTitle = $news['title'];
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Contenido principal -->
        <div class="col-lg-8 col-md-12">
            <!-- Breadcrumbs -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="category.php?slug=<?php echo $news['category_slug']; ?>"><?php echo $news['category_name']; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo truncateString($news['title'], 40); ?></li>
                </ol>
            </nav>
            
            <!-- Anuncio de Contenido (arriba de la noticia) -->
            <?php if (isset($ads['content']) && $ads['content']): ?>
                <div class="ad-container text-center mb-4">
                    <a href="<?php echo $ads['content']['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ads['content']['id']; ?>">
                        <img src="<?php echo $ads['content']['image']; ?>" alt="<?php echo $ads['content']['title']; ?>" class="img-fluid">
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Noticia principal -->
            <article class="news-detail">
                <h1 class="news-title mb-3"><?php echo $news['title']; ?></h1>
                
                <div class="news-meta mb-3">
                    <span class="category"><a href="category.php?slug=<?php echo $news['category_slug']; ?>" class="badge bg-primary"><?php echo $news['category_name']; ?></a></span>
                    <span class="date"><i class="far fa-calendar-alt me-1"></i><?php echo formatDate($news['published_at'], 'd M, Y'); ?></span>
                    <span class="author"><i class="fas fa-user-edit me-1"></i><?php echo $news['author_name']; ?></span>
                    <span class="views"><i class="far fa-eye me-1"></i><?php echo number_format($news['views']); ?> vistas</span>
                </div>
                
                <!-- Imagen principal -->
                <div class="news-image mb-4">
                    <img src="<?php echo $news['image']; ?>" alt="<?php echo $news['title']; ?>" class="img-fluid rounded">
                </div>
                
                <!-- Extracto -->
                <div class="news-excerpt mb-4">
                    <p class="lead"><?php echo $news['excerpt']; ?></p>
                </div>
                
                <!-- Contenido de la noticia -->
                <div class="news-content mb-4">
                    <?php echo $news['content']; ?>
                </div>
                
                <!-- Etiquetas -->
                <?php if (!empty($tags)): ?>
                    <div class="news-tags mb-4">
                        <h5>Etiquetas:</h5>
                        <div class="tags-cloud">
                            <?php foreach ($tags as $tag): ?>
                                <a href="tag.php?slug=<?php echo $tag['slug']; ?>" class="tag-link">
                                    <?php echo $tag['name']; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Información del autor -->
                <div class="author-box mb-4 p-4 bg-light rounded">
                    <div class="row">
                        <div class="col-md-2 col-sm-3 text-center mb-3 mb-md-0">
                            <img src="<?php echo $news['author_avatar'] ? $news['author_avatar'] : 'assets/img/default-avatar.png'; ?>" alt="<?php echo $news['author_name']; ?>" class="rounded-circle img-fluid" style="max-width: 80px;">
                        </div>
                        <div class="col-md-10 col-sm-9">
                            <h5><?php echo $news['author_name']; ?></h5>
                            <p class="mb-0"><?php echo $news['author_bio'] ? $news['author_bio'] : 'Autor en ' . getSetting('site_name'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Anuncio de Pie de Página (después del contenido de la noticia) -->
                <?php if (isset($ads['footer']) && $ads['footer']): ?>
                    <div class="ad-container text-center mb-4">
                        <a href="<?php echo $ads['footer']['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ads['footer']['id']; ?>">
                            <img src="<?php echo $ads['footer']['image']; ?>" alt="<?php echo $ads['footer']['title']; ?>" class="img-fluid">
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Noticias relacionadas -->
                <?php if (!empty($relatedNews)): ?>
                    <div class="related-news mb-4">
                        <h3 class="section-title">Noticias relacionadas</h3>
                        <div class="row">
                            <?php foreach ($relatedNews as $related): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <img src="<?php echo $related['image']; ?>" class="card-img-top" alt="<?php echo $related['title']; ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><a href="news.php?slug=<?php echo $related['slug']; ?>" class="text-decoration-none"><?php echo truncateString($related['title'], 60); ?></a></h5>
                                            <p class="card-text"><small class="text-muted"><?php echo formatDate($related['published_at'], 'd M, Y'); ?></small></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Sección de comentarios -->
                <?php if ($news['allow_comments']): ?>
                    <div class="comments-section mt-5" id="comments">
                        <h3 class="section-title">Comentarios (<?php echo count($comments); ?>)</h3>
                        
                        <!-- Formulario de comentarios -->
                        <div class="comment-form mb-4">
                            <form id="commentForm" action="submit_comment.php" method="post">
                                <input type="hidden" name="news_id" value="<?php echo $news['id']; ?>">
                                <input type="hidden" name="parent_id" id="parentId" value="">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                
                                <div class="mb-3" id="replyInfo" style="display: none;">
                                    <div class="alert alert-info">
                                        Respondiendo a: <span id="replyToName"></span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary float-end" id="cancelReply">Cancelar</button>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                        <div class="form-text">Tu email no será publicado.</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="website" class="form-label">Sitio web</label>
                                    <input type="url" class="form-control" id="website" name="website">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Comentario <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="comment" name="comment" rows="5" required></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Enviar comentario</button>
                            </form>
                        </div>
                        
                        <!-- Lista de comentarios -->
                        <div class="comments-list">
                            <?php if (empty($commentTree)): ?>
                                <div class="alert alert-info">
                                    No hay comentarios aún. ¡Sé el primero en comentar!
                                </div>
                            <?php else: ?>
                                <?php foreach ($commentTree as $id => $commentData): ?>
                                    <div class="comment mb-4" id="comment-<?php echo $commentData['comment']['id']; ?>">
                                        <div class="comment-header d-flex">
                                            <div class="comment-avatar me-3">
                                                <div class="avatar-placeholder rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width: 50px; height: 50px;">
                                                    <?php echo strtoupper(substr($commentData['comment']['name'], 0, 1)); ?>
                                                </div>
                                            </div>
                                            <div class="comment-meta">
                                                <h5 class="mb-0"><?php echo htmlspecialchars($commentData['comment']['name']); ?></h5>
                                                <p class="text-muted mb-0">
                                                    <small><?php echo formatDate($commentData['comment']['created_at'], 'd M, Y H:i'); ?></small>
                                                    <?php if (!empty($commentData['comment']['website'])): ?>
                                                        <span class="mx-1">|</span>
                                                        <a href="<?php echo htmlspecialchars($commentData['comment']['website']); ?>" target="_blank" rel="nofollow">Sitio web</a>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="comment-body mt-2">
                                            <p><?php echo nl2br(htmlspecialchars($commentData['comment']['comment'])); ?></p>
                                        </div>
                                        <div class="comment-footer">
                                            <button class="btn btn-sm btn-outline-primary reply-btn" data-id="<?php echo $commentData['comment']['id']; ?>" data-name="<?php echo htmlspecialchars($commentData['comment']['name']); ?>">
                                                <i class="fas fa-reply me-1"></i> Responder
                                            </button>
                                        </div>
                                        
                                        <!-- Respuestas a este comentario -->
                                        <?php if (!empty($commentData['replies'])): ?>
                                            <div class="comment-replies mt-3 ms-5">
                                                <?php foreach ($commentData['replies'] as $reply): ?>
                                                    <div class="comment-reply mb-3" id="comment-<?php echo $reply['id']; ?>">
                                                        <div class="comment-header d-flex">
                                                            <div class="comment-avatar me-3">
                                                                <div class="avatar-placeholder rounded-circle bg-light d-flex align-items-center justify-content-center text-dark" style="width: 40px; height: 40px;">
                                                                    <?php echo strtoupper(substr($reply['name'], 0, 1)); ?>
                                                                </div>
                                                            </div>
                                                            <div class="comment-meta">
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($reply['name']); ?></h6>
                                                                <p class="text-muted mb-0">
                                                                    <small><?php echo formatDate($reply['created_at'], 'd M, Y H:i'); ?></small>
                                                                    <?php if (!empty($reply['website'])): ?>
                                                                        <span class="mx-1">|</span>
                                                                        <a href="<?php echo htmlspecialchars($reply['website']); ?>" target="_blank" rel="nofollow">Sitio web</a>
                                                                    <?php endif; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="comment-body mt-2">
                                                            <p><?php echo nl2br(htmlspecialchars($reply['comment'])); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mt-5">
                        Los comentarios están desactivados para esta noticia.
                    </div>
                <?php endif; ?>
            </article>
        </div>
        
        <!-- Barra lateral -->
        <div class="col-lg-4 col-md-12">
            <!-- Anuncios Laterales -->
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
            
            <!-- Widget de búsqueda -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Buscar</h5>
                    <?php include 'includes/search_form.php'; ?>
                </div>
            </div>
            
            <!-- Noticias Populares -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Noticias Populares</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($popularNews as $popular): ?>
                            <li class="list-group-item">
                                <div class="popular-news-item d-flex">
                                    <div class="thumbnail me-3">
                                        <img src="<?php echo $popular['image']; ?>" alt="<?php echo $popular['title']; ?>" width="80" height="60" class="object-fit-cover">
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><a href="news.php?slug=<?php echo $popular['slug']; ?>" class="text-decoration-none"><?php echo truncateString($popular['title'], 50); ?></a></h6>
                                        <small class="text-muted"><?php echo number_format($popular['views']); ?> lecturas</small>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Widget de categorías -->
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
                                    <span class="badge bg-primary rounded-pill"><?php echo $category['news_count']; ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Widget de suscripción -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Newsletter</h5>
                </div>
                <div class="card-body">
                    <p>Suscríbete para recibir las últimas noticias en tu correo electrónico.</p>
                    <?php include 'includes/newsletter_form.php'; ?>
                </div>
            </div>
            
            <!-- Anuncios Izquierda Extra (debajo del newsletter) -->
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
            
            <!-- Widget de etiquetas populares -->
            <?php
            $popularTags = $db->fetchAll(
                "SELECT t.id, t.name, t.slug, COUNT(nt.news_id) as news_count
                 FROM tags t
                 JOIN news_tags nt ON t.id = nt.tag_id
                 JOIN news n ON nt.news_id = n.id
                 WHERE n.status = 'published'
                 GROUP BY t.id
                 ORDER BY news_count DESC
                 LIMIT 15"
            );
            
            if (!empty($popularTags)):
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Etiquetas Populares</h5>
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
        </div>
    </div>
</div>

<script>
// Script para manejar respuestas a comentarios
document.addEventListener('DOMContentLoaded', function() {
    const commentForm = document.getElementById('commentForm');
    const parentIdInput = document.getElementById('parentId');
    const replyInfo = document.getElementById('replyInfo');
    const replyToName = document.getElementById('replyToName');
    const cancelReply = document.getElementById('cancelReply');
    
    // Botones de respuesta
    const replyButtons = document.querySelectorAll('.reply-btn');
    
    replyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const commentId = this.getAttribute('data-id');
            const commentName = this.getAttribute('data-name');
            
            // Establecer ID del comentario padre
            parentIdInput.value = commentId;
            
            // Mostrar información de respuesta
            replyToName.textContent = commentName;
            replyInfo.style.display = 'block';
            
            // Desplazar la página al formulario
            commentForm.scrollIntoView({ behavior: 'smooth' });
            
            // Poner el foco en el campo de comentario
            document.getElementById('comment').focus();
        });
    });
    
    // Botón cancelar respuesta
    cancelReply.addEventListener('click', function() {
        // Limpiar ID del comentario padre
        parentIdInput.value = '';
        
        // Ocultar información de respuesta
        replyInfo.style.display = 'none';
    });
});
</script>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>