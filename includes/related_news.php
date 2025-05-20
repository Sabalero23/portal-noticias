<?php
/**
 * Noticias relacionadas
 * 
 * Muestra noticias relacionadas a la noticia actual basado en categoría,
 * etiquetas o palabras clave del título.
 */

/**
 * Obtiene y muestra noticias relacionadas a una noticia específica
 * 
 * @param int $newsId ID de la noticia actual
 * @param int $categoryId ID de la categoría de la noticia
 * @param int $limit Cantidad de noticias a mostrar (por defecto 4)
 * @return string HTML con las noticias relacionadas
 */
function getRelatedNews($newsId, $categoryId, $limit = 4) {
    $db = Database::getInstance();
    
    // Obtener etiquetas de la noticia actual
    $tags = $db->fetchAll(
        "SELECT tag_id FROM news_tags WHERE news_id = ?",
        [$newsId]
    );
    
    $tagIds = array_column($tags, 'tag_id');
    
    // Prioridad 1: Noticias con las mismas etiquetas
    $relatedByTags = [];
    if (!empty($tagIds)) {
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        
        $relatedByTags = $db->fetchAll(
            "SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.published_at, 
                    COUNT(nt.tag_id) as relevance
             FROM news n
             JOIN news_tags nt ON n.id = nt.news_id
             WHERE n.id != ? AND n.status = 'published' AND nt.tag_id IN ({$placeholders})
             GROUP BY n.id
             ORDER BY relevance DESC, n.published_at DESC
             LIMIT ?",
            array_merge([$newsId], $tagIds, [$limit])
        );
    }
    
    // Si no hay suficientes noticias relacionadas por etiquetas, 
    // buscar más por la misma categoría
    $count = count($relatedByTags);
    $relatedByCategoryLimit = $limit - $count;
    
    $relatedByCategory = [];
    if ($relatedByCategoryLimit > 0) {
        $excludeIds = [$newsId];
        foreach ($relatedByTags as $news) {
            $excludeIds[] = $news['id'];
        }
        
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        
        $relatedByCategory = $db->fetchAll(
            "SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.published_at
             FROM news n
             WHERE n.id NOT IN ({$placeholders}) 
                  AND n.status = 'published' 
                  AND n.category_id = ?
             ORDER BY n.published_at DESC
             LIMIT ?",
            array_merge($excludeIds, [$categoryId, $relatedByCategoryLimit])
        );
    }
    
    // Combinar los resultados
    $relatedNews = array_merge($relatedByTags, $relatedByCategory);
    
    // Si aún no hay suficientes noticias, agregar las más recientes de cualquier categoría
    $count = count($relatedNews);
    $remainingLimit = $limit - $count;
    
    if ($remainingLimit > 0 && $count < 2) { // Solo si hay muy pocas relacionadas
        $excludeIds = [$newsId];
        foreach ($relatedNews as $news) {
            $excludeIds[] = $news['id'];
        }
        
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        
        $recentNews = $db->fetchAll(
            "SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.published_at
             FROM news n
             WHERE n.id NOT IN ({$placeholders}) AND n.status = 'published'
             ORDER BY n.published_at DESC
             LIMIT ?",
            array_merge($excludeIds, [$remainingLimit])
        );
        
        $relatedNews = array_merge($relatedNews, $recentNews);
    }
    
    // Si no hay noticias relacionadas, devolver un mensaje
    if (empty($relatedNews)) {
        return '<div class="alert alert-info">No hay noticias relacionadas disponibles.</div>';
    }
    
    // Generar el HTML
    $html = '<div class="related-news">';
    $html .= '<h4 class="section-title">Noticias relacionadas</h4>';
    $html .= '<div class="row">';
    
    foreach ($relatedNews as $news) {
        $html .= '<div class="col-md-6 col-lg-3 mb-4">';
        $html .= '<div class="card h-100">';
        
        // Imagen
        $html .= '<div class="position-relative">';
        $html .= '<a href="news.php?slug=' . $news['slug'] . '">';
        $html .= '<img src="' . $news['image'] . '" class="card-img-top" alt="' . htmlspecialchars($news['title']) . '">';
        $html .= '</a>';
        
        // Fecha
        $html .= '<div class="news-date">';
        $html .= '<span>' . formatDate($news['published_at'], 'd M') . '</span>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Contenido
        $html .= '<div class="card-body">';
        $html .= '<h5 class="card-title">';
        $html .= '<a href="news.php?slug=' . $news['slug'] . '" class="text-decoration-none">' . $news['title'] . '</a>';
        $html .= '</h5>';
        $html .= '</div>';
        
        $html .= '</div>'; // .card
        $html .= '</div>'; // .col
    }
    
    $html .= '</div>'; // .row
    $html .= '</div>'; // .related-news
    
    return $html;
}
?>