<?php
/**
 * Widget de noticias populares
 * 
 * Muestra un listado de las noticias más vistas o más comentadas.
 */

/**
 * Obtiene y muestra las noticias más vistas
 * 
 * @param int $limit Cantidad de noticias a mostrar (por defecto 5)
 * @param string $template Plantilla a utilizar ('sidebar', 'footer', etc.)
 * @return string HTML con las noticias populares
 */
function getMostViewedNews($limit = 5, $template = 'sidebar') {
    $db = Database::getInstance();
    
    $popularNews = $db->fetchAll(
        "SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.views, n.published_at,
                c.name as category_name, c.slug as category_slug
         FROM news n
         JOIN categories c ON n.category_id = c.id
         WHERE n.status = 'published'
         ORDER BY n.views DESC
         LIMIT ?",
        [$limit]
    );
    
    // Si no hay noticias, devolver mensaje
    if (empty($popularNews)) {
        return '<div class="alert alert-info">No hay noticias populares disponibles.</div>';
    }
    
    // Seleccionar la plantilla adecuada
    switch ($template) {
        case 'sidebar':
            return renderSidebarPopularNews($popularNews);
            
        case 'footer':
            return renderFooterPopularNews($popularNews);
            
        case 'widget':
            return renderWidgetPopularNews($popularNews);
            
        default:
            return renderSidebarPopularNews($popularNews);
    }
}

/**
 * Obtiene y muestra las noticias más comentadas
 * 
 * @param int $limit Cantidad de noticias a mostrar (por defecto 5)
 * @param string $template Plantilla a utilizar ('sidebar', 'footer', etc.)
 * @return string HTML con las noticias más comentadas
 */
function getMostCommentedNews($limit = 5, $template = 'sidebar') {
    $db = Database::getInstance();
    
    $commentedNews = $db->fetchAll(
        "SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.published_at,
                c.name as category_name, c.slug as category_slug,
                COUNT(cm.id) as comment_count
         FROM news n
         JOIN categories c ON n.category_id = c.id
         JOIN comments cm ON n.id = cm.news_id
         WHERE n.status = 'published' AND cm.status = 'approved'
         GROUP BY n.id
         ORDER BY comment_count DESC
         LIMIT ?",
        [$limit]
    );
    
    // Si no hay noticias, devolver mensaje
    if (empty($commentedNews)) {
        return '<div class="alert alert-info">No hay noticias comentadas disponibles.</div>';
    }
    
    // Seleccionar la plantilla adecuada
    switch ($template) {
        case 'sidebar':
            return renderSidebarCommentedNews($commentedNews);
            
        case 'footer':
            return renderFooterCommentedNews($commentedNews);
            
        case 'widget':
            return renderWidgetCommentedNews($commentedNews);
            
        default:
            return renderSidebarCommentedNews($commentedNews);
    }
}

/**
 * Renderiza noticias populares en la barra lateral
 * 
 * @param array $news Array con las noticias
 * @return string HTML renderizado
 */
function renderSidebarPopularNews($news) {
    $html = '<div class="popular-news sidebar-widget">';
    
    if (!isset($news[0]['views'])) {
        foreach ($news as $key => $item) {
            $news[$key]['views'] = 0; // Valor por defecto
        }
    }
    
    $html .= '<ul class="list-group list-group-flush">';
    
    foreach ($news as $item) {
        $html .= '<li class="list-group-item px-0">';
        $html .= '<div class="popular-news-item d-flex">';
        
        // Miniatura
        $html .= '<div class="thumbnail me-3">';
        $html .= '<a href="news.php?slug=' . $item['slug'] . '">';
        $html .= '<img src="' . $item['image'] . '" alt="' . htmlspecialchars($item['title']) . '" width="80" height="60" class="object-fit-cover">';
        $html .= '</a>';
        $html .= '</div>';
        
        // Contenido
        $html .= '<div>';
        $html .= '<h6 class="mb-1"><a href="news.php?slug=' . $item['slug'] . '" class="text-decoration-none">' . htmlspecialchars(truncateString($item['title'], 50)) . '</a></h6>';
        
        // Categoría
        $html .= '<div class="news-meta small">';
        $html .= '<a href="category.php?slug=' . $item['category_slug'] . '" class="category-link">' . $item['category_name'] . '</a>';
        
        // Vistas
        $html .= '<span class="ms-2 text-muted"><i class="far fa-eye me-1"></i>' . number_format($item['views']) . '</span>';
        $html .= '</div>';
        
        $html .= '</div>'; // .content
        $html .= '</div>'; // .popular-news-item
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</div>'; // .popular-news
    
    return $html;
}

/**
 * Renderiza noticias comentadas en la barra lateral
 * 
 * @param array $news Array con las noticias
 * @return string HTML renderizado
 */
function renderSidebarCommentedNews($news) {
    $html = '<div class="commented-news sidebar-widget">';
    $html .= '<ul class="list-group list-group-flush">';
    
    foreach ($news as $item) {
        $html .= '<li class="list-group-item px-0">';
        $html .= '<div class="popular-news-item d-flex">';
        
        // Miniatura
        $html .= '<div class="thumbnail me-3">';
        $html .= '<a href="news.php?slug=' . $item['slug'] . '">';
        $html .= '<img src="' . $item['image'] . '" alt="' . htmlspecialchars($item['title']) . '" width="80" height="60" class="object-fit-cover">';
        $html .= '</a>';
        $html .= '</div>';
        
        // Contenido
        $html .= '<div>';
        $html .= '<h6 class="mb-1"><a href="news.php?slug=' . $item['slug'] . '" class="text-decoration-none">' . htmlspecialchars(truncateString($item['title'], 50)) . '</a></h6>';
        
        // Categoría
        $html .= '<div class="news-meta small">';
        $html .= '<a href="category.php?slug=' . $item['category_slug'] . '" class="category-link">' . $item['category_name'] . '</a>';
        
        // Comentarios
        $html .= '<span class="ms-2 text-muted"><i class="far fa-comment me-1"></i>' . $item['comment_count'] . '</span>';
        $html .= '</div>';
        
        $html .= '</div>'; // .content
        $html .= '</div>'; // .popular-news-item
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</div>'; // .commented-news
    
    return $html;
}

/**
 * Renderiza noticias populares en el pie de página
 * 
 * @param array $news Array con las noticias
 * @return string HTML renderizado
 */
function renderFooterPopularNews($news) {
    $html = '<div class="footer-popular-news">';
    $html .= '<div class="row">';
    
    foreach ($news as $item) {
        $html .= '<div class="col-md-6 col-lg-4 mb-3">';
        $html .= '<div class="footer-news-item">';
        
        // Contenido
        $html .= '<h6 class="mb-2"><a href="news.php?slug=' . $item['slug'] . '" class="text-decoration-none text-light">' . htmlspecialchars(truncateString($item['title'], 60)) . '</a></h6>';
        
        // Fecha y vistas
        $html .= '<div class="news-meta small text-muted">';
        $html .= '<span><i class="far fa-calendar-alt me-1"></i>' . formatDate($item['published_at'], 'd M, Y') . '</span>';
        $html .= '<span class="ms-3"><i class="far fa-eye me-1"></i>' . number_format($item['views']) . '</span>';
        $html .= '</div>';
        
        $html .= '</div>'; // .footer-news-item
        $html .= '</div>'; // .col
    }
    
    $html .= '</div>'; // .row
    $html .= '</div>'; // .footer-popular-news
    
    return $html;
}

/**
 * Renderiza noticias comentadas en el pie de página
 * 
 * @param array $news Array con las noticias
 * @return string HTML renderizado
 */
function renderFooterCommentedNews($news) {
    $html = '<div class="footer-commented-news">';
    $html .= '<div class="row">';
    
    foreach ($news as $item) {
        $html .= '<div class="col-md-6 col-lg-4 mb-3">';
        $html .= '<div class="footer-news-item">';
        
        // Contenido
        $html .= '<h6 class="mb-2"><a href="news.php?slug=' . $item['slug'] . '" class="text-decoration-none text-light">' . htmlspecialchars(truncateString($item['title'], 60)) . '</a></h6>';
        
        // Fecha y comentarios
        $html .= '<div class="news-meta small text-muted">';
        $html .= '<span><i class="far fa-calendar-alt me-1"></i>' . formatDate($item['published_at'], 'd M, Y') . '</span>';
        $html .= '<span class="ms-3"><i class="far fa-comment me-1"></i>' . $item['comment_count'] . '</span>';
        $html .= '</div>';
        
        $html .= '</div>'; // .footer-news-item
        $html .= '</div>'; // .col
    }
    
    $html .= '</div>'; // .row
    $html .= '</div>'; // .footer-commented-news
    
    return $html;
}

/**
 * Renderiza noticias populares como widget genérico
 * 
 * @param array $news Array con las noticias
 * @return string HTML renderizado
 */
function renderWidgetPopularNews($news) {
    $html = '<div class="widget-popular-news">';
    
    foreach ($news as $index => $item) {
        $html .= '<div class="widget-news-item' . ($index < count($news) - 1 ? ' mb-3 pb-3 border-bottom' : '') . '">';
        $html .= '<div class="row">';
        
        // Miniatura
        $html .= '<div class="col-4">';
        $html .= '<a href="news.php?slug=' . $item['slug'] . '">';
        $html .= '<img src="' . $item['image'] . '" alt="' . htmlspecialchars($item['title']) . '" class="img-fluid rounded">';
        $html .= '</a>';
        $html .= '</div>';
        
        // Contenido
        $html .= '<div class="col-8">';
        $html .= '<h6 class="mb-1"><a href="news.php?slug=' . $item['slug'] . '" class="text-decoration-none">' . htmlspecialchars(truncateString($item['title'], 50)) . '</a></h6>';
        
        // Fecha y vistas
        $html .= '<div class="news-meta small">';
        $html .= '<span class="text-muted"><i class="far fa-calendar-alt me-1"></i>' . formatDate($item['published_at'], 'd M') . '</span>';
        $html .= '<span class="ms-2 text-muted"><i class="far fa-eye me-1"></i>' . number_format($item['views']) . '</span>';
        $html .= '</div>';
        
        $html .= '</div>'; // .col-8
        $html .= '</div>'; // .row
        $html .= '</div>'; // .widget-news-item
    }
    
    $html .= '</div>'; // .widget-popular-news
    
    return $html;
}

/**
 * Renderiza noticias comentadas como widget genérico
 * 
 * @param array $news Array con las noticias
 * @return string HTML renderizado
 */
function renderWidgetCommentedNews($news) {
    $html = '<div class="widget-commented-news">';
    
    foreach ($news as $index => $item) {
        $html .= '<div class="widget-news-item' . ($index < count($news) - 1 ? ' mb-3 pb-3 border-bottom' : '') . '">';
        $html .= '<div class="row">';
        
        // Miniatura
        $html .= '<div class="col-4">';
        $html .= '<a href="news.php?slug=' . $item['slug'] . '">';
        $html .= '<img src="' . $item['image'] . '" alt="' . htmlspecialchars($item['title']) . '" class="img-fluid rounded">';
        $html .= '</a>';
        $html .= '</div>';
        
        // Contenido
        $html .= '<div class="col-8">';
        $html .= '<h6 class="mb-1"><a href="news.php?slug=' . $item['slug'] . '" class="text-decoration-none">' . htmlspecialchars(truncateString($item['title'], 50)) . '</a></h6>';
        
        // Fecha y comentarios
        $html .= '<div class="news-meta small">';
        $html .= '<span class="text-muted"><i class="far fa-calendar-alt me-1"></i>' . formatDate($item['published_at'], 'd M') . '</span>';
        $html .= '<span class="ms-2 text-muted"><i class="far fa-comment me-1"></i>' . $item['comment_count'] . '</span>';
        $html .= '</div>';
        
        $html .= '</div>'; // .col-8
        $html .= '</div>'; // .row
        $html .= '</div>'; // .widget-news-item
    }
    
    $html .= '</div>'; // .widget-commented-news
    
    return $html;
}
?>