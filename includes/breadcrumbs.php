<?php
/**
 * Generador de migas de pan
 * 
 * Muestra la ruta de navegación actual del usuario en el sitio.
 * 
 * @param array $items Array asociativo con las rutas (slug => nombre)
 * @param string $separator Separador entre elementos (por defecto '>')
 * @return string HTML con las migas de pan
 */
function generateBreadcrumbs($items = [], $separator = '<i class="fas fa-angle-right mx-2"></i>') {
    if (!is_array($items) || empty($items)) {
        return '';
    }
    
    $html = '<nav aria-label="breadcrumb" class="breadcrumb-container">';
    $html .= '<ol class="breadcrumb">';
    
    // Añadir enlace al inicio
    $html .= '<li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> Inicio</a></li>';
    
    // Número de elementos
    $count = count($items);
    $i = 1;
    
    // Generar elementos de migas de pan
    foreach ($items as $url => $name) {
        if ($i === $count) {
            // Último elemento (actual)
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($name) . '</li>';
        } else {
            // Elementos intermedios
            $html .= '<li class="breadcrumb-item"><a href="' . $url . '">' . htmlspecialchars($name) . '</a></li>';
        }
        
        $i++;
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Genera migas de pan para una noticia
 * 
 * @param array $news Datos de la noticia
 * @param array $category Datos de la categoría
 * @return string HTML con las migas de pan
 */
function generateNewsBreadcrumbs($news, $category) {
    $items = [];
    
    // Añadir categoría
    $items['category.php?slug=' . $category['slug']] = $category['name'];
    
    // Añadir noticia actual
    $items['#'] = $news['title'];
    
    return generateBreadcrumbs($items);
}

/**
 * Genera migas de pan para una categoría
 * 
 * @param array $category Datos de la categoría
 * @return string HTML con las migas de pan
 */
function generateCategoryBreadcrumbs($category) {
    $items = [];
    
    // Añadir categoría actual
    $items['#'] = $category['name'];
    
    return generateBreadcrumbs($items);
}

/**
 * Genera migas de pan para una etiqueta
 * 
 * @param array $tag Datos de la etiqueta
 * @return string HTML con las migas de pan
 */
function generateTagBreadcrumbs($tag) {
    $items = [];
    
    // Añadir etiqueta actual
    $items['#'] = 'Etiqueta: ' . $tag['name'];
    
    return generateBreadcrumbs($items);
}

/**
 * Genera migas de pan para la página de búsqueda
 * 
 * @param string $query Consulta de búsqueda
 * @return string HTML con las migas de pan
 */
function generateSearchBreadcrumbs($query) {
    $items = [];
    
    // Añadir búsqueda actual
    $items['#'] = 'Búsqueda: ' . htmlspecialchars($query);
    
    return generateBreadcrumbs($items);
}

/**
 * Genera migas de pan para páginas estáticas
 * 
 * @param string $title Título de la página
 * @return string HTML con las migas de pan
 */
function generateStaticPageBreadcrumbs($title) {
    $items = [];
    
    // Añadir página actual
    $items['#'] = $title;
    
    return generateBreadcrumbs($items);
}
?>