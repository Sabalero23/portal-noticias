<?php
/**
 * Botones para compartir en redes sociales
 * 
 * Proporciona botones para compartir contenido en diferentes redes sociales.
 */

/**
 * Genera botones para compartir en redes sociales
 * 
 * @param string $url URL a compartir
 * @param string $title Título del contenido
 * @param string $excerpt Resumen del contenido (opcional)
 * @param string $image URL de la imagen para compartir (opcional)
 * @param array $networks Redes sociales a mostrar (por defecto todas)
 * @param string $style Estilo de los botones ('buttons', 'icons', 'minimal')
 * @return string HTML con los botones para compartir
 */
function getSocialShareButtons($url, $title, $excerpt = '', $image = '', $networks = [], $style = 'buttons') {
    // URL codificada para compartir
    $encodedUrl = urlencode($url);
    $encodedTitle = urlencode($title);
    $encodedExcerpt = urlencode($excerpt);
    
    // Redes sociales disponibles
    $availableNetworks = [
        'facebook' => [
            'name' => 'Facebook',
            'icon' => 'fab fa-facebook-f',
            'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl
        ],
        'twitter' => [
            'name' => 'Twitter',
            'icon' => 'fab fa-twitter',
            'url' => 'https://twitter.com/intent/tweet?url=' . $encodedUrl . '&text=' . $encodedTitle
        ],
        'whatsapp' => [
            'name' => 'WhatsApp',
            'icon' => 'fab fa-whatsapp',
            'url' => 'https://api.whatsapp.com/send?text=' . $encodedTitle . ' ' . $encodedUrl
        ],
        'telegram' => [
            'name' => 'Telegram',
            'icon' => 'fab fa-telegram-plane',
            'url' => 'https://t.me/share/url?url=' . $encodedUrl . '&text=' . $encodedTitle
        ],
        'linkedin' => [
            'name' => 'LinkedIn',
            'icon' => 'fab fa-linkedin-in',
            'url' => 'https://www.linkedin.com/shareArticle?mini=true&url=' . $encodedUrl . '&title=' . $encodedTitle . '&summary=' . $encodedExcerpt
        ],
        'pinterest' => [
            'name' => 'Pinterest',
            'icon' => 'fab fa-pinterest-p',
            'url' => 'https://pinterest.com/pin/create/button/?url=' . $encodedUrl . '&media=' . urlencode($image) . '&description=' . $encodedTitle
        ],
        'email' => [
            'name' => 'Email',
            'icon' => 'fas fa-envelope',
            'url' => 'mailto:?subject=' . $encodedTitle . '&body=' . $encodedExcerpt . ' ' . $encodedUrl
        ]
    ];
    
    // Si no se especifican redes, usar todas excepto email
    if (empty($networks)) {
        $networks = array_keys($availableNetworks);
        // Quitar email de la lista por defecto
        if (($key = array_search('email', $networks)) !== false) {
            unset($networks[$key]);
        }
    }
    
    // Generar HTML según el estilo
    switch ($style) {
        case 'icons':
            return renderIconsShareButtons($availableNetworks, $networks);
            
        case 'minimal':
            return renderMinimalShareButtons($availableNetworks, $networks);
            
        case 'buttons':
        default:
            return renderButtonsShareButtons($availableNetworks, $networks);
    }
}

/**
 * Renderiza botones de compartir con estilo completo
 * 
 * @param array $availableNetworks Todas las redes disponibles
 * @param array $networks Redes a mostrar
 * @return string HTML renderizado
 */
function renderButtonsShareButtons($availableNetworks, $networks) {
    $html = '<div class="social-share-buttons">';
    $html .= '<span class="share-label">Compartir:</span>';
    $html .= '<div class="share-buttons">';
    
    foreach ($networks as $network) {
        if (isset($availableNetworks[$network])) {
            $net = $availableNetworks[$network];
            
            $html .= '<a href="' . $net['url'] . '" class="btn btn-sm share-button share-' . $network . '" target="_blank" rel="noopener noreferrer">';
            $html .= '<i class="' . $net['icon'] . '"></i> ' . $net['name'];
            $html .= '</a>';
        }
    }
    
    $html .= '</div>'; // .share-buttons
    $html .= '</div>'; // .social-share-buttons
    
    return $html;
}

/**
 * Renderiza botones de compartir con estilo de iconos
 * 
 * @param array $availableNetworks Todas las redes disponibles
 * @param array $networks Redes a mostrar
 * @return string HTML renderizado
 */
function renderIconsShareButtons($availableNetworks, $networks) {
    $html = '<div class="social-share-icons">';
    $html .= '<span class="share-label">Compartir:</span>';
    $html .= '<div class="share-icons">';
    
    foreach ($networks as $network) {
        if (isset($availableNetworks[$network])) {
            $net = $availableNetworks[$network];
            
            $html .= '<a href="' . $net['url'] . '" class="share-icon share-' . $network . '" target="_blank" rel="noopener noreferrer" title="Compartir en ' . $net['name'] . '">';
            $html .= '<i class="' . $net['icon'] . '"></i>';
            $html .= '</a>';
        }
    }
    
    $html .= '</div>'; // .share-icons
    $html .= '</div>'; // .social-share-icons
    
    return $html;
}

/**
 * Renderiza botones de compartir con estilo minimalista
 * 
 * @param array $availableNetworks Todas las redes disponibles
 * @param array $networks Redes a mostrar
 * @return string HTML renderizado
 */
function renderMinimalShareButtons($availableNetworks, $networks) {
    $html = '<div class="social-share-minimal">';
    $html .= '<div class="share-dropdown dropdown">';
    
    // Botón principal
    $html .= '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="shareDropdown" data-bs-toggle="dropdown" aria-expanded="false">';
    $html .= '<i class="fas fa-share-alt me-1"></i> Compartir';
    $html .= '</button>';
    
    // Menú desplegable
    $html .= '<ul class="dropdown-menu" aria-labelledby="shareDropdown">';
    
    foreach ($networks as $network) {
        if (isset($availableNetworks[$network])) {
            $net = $availableNetworks[$network];
            
            $html .= '<li>';
            $html .= '<a href="' . $net['url'] . '" class="dropdown-item" target="_blank" rel="noopener noreferrer">';
            $html .= '<i class="' . $net['icon'] . ' me-2"></i> ' . $net['name'];
            $html .= '</a>';
            $html .= '</li>';
        }
    }
    
    $html .= '</ul>'; // .dropdown-menu
    $html .= '</div>'; // .share-dropdown
    $html .= '</div>'; // .social-share-minimal
    
    return $html;
}

/**
 * Genera botones de compartir para una noticia
 * 
 * @param array $news Datos de la noticia
 * @param string $style Estilo de los botones
 * @return string HTML con los botones para compartir
 */
function getNewsShareButtons($news, $style = 'buttons') {
    // Construir la URL completa
    $url = SITE_URL . '/news.php?slug=' . $news['slug'];
    
    // Imagen para compartir
    $image = isset($news['image']) ? SITE_URL . '/' . $news['image'] : '';
    
    // Llamar a la función general
    return getSocialShareButtons(
        $url,
        $news['title'],
        isset($news['excerpt']) ? $news['excerpt'] : '',
        $image,
        [], // Usar todas las redes por defecto
        $style
    );
}

/**
 * Genera código para meta tags Open Graph y Twitter Cards
 * 
 * @param string $url URL canónica
 * @param string $title Título del contenido
 * @param string $description Descripción del contenido
 * @param string $image URL de la imagen (opcional)
 * @param string $type Tipo de contenido ('article', 'website', etc.)
 * @return string HTML con meta tags
 */
function getSocialMetaTags($url, $title, $description, $image = '', $type = 'article') {
    $siteName = getSetting('site_name', 'Portal de Noticias');
    
    $html = '';
    
    // Open Graph
    $html .= '<meta property="og:url" content="' . $url . '">' . "\n";
    $html .= '<meta property="og:type" content="' . $type . '">' . "\n";
    $html .= '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $html .= '<meta property="og:site_name" content="' . htmlspecialchars($siteName) . '">' . "\n";
    
    if (!empty($image)) {
        $html .= '<meta property="og:image" content="' . $image . '">' . "\n";
        $html .= '<meta property="og:image:width" content="1200">' . "\n";
        $html .= '<meta property="og:image:height" content="630">' . "\n";
    }
    
    // Twitter Card
    $html .= '<meta name="twitter:card" content="' . (empty($image) ? 'summary' : 'summary_large_image') . '">' . "\n";
    $html .= '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . "\n";
    
    if (!empty($image)) {
        $html .= '<meta name="twitter:image" content="' . $image . '">' . "\n";
    }
    
    // Twitter site
    $twitterAccount = getSetting('twitter', '');
    if (!empty($twitterAccount)) {
        // Extraer el nombre de usuario si es una URL completa
        if (strpos($twitterAccount, 'twitter.com/') !== false) {
            $parts = explode('twitter.com/', $twitterAccount);
            $twitterAccount = '@' . end($parts);
        } elseif (strpos($twitterAccount, '@') === false) {
            $twitterAccount = '@' . $twitterAccount;
        }
        
        $html .= '<meta name="twitter:site" content="' . $twitterAccount . '">' . "\n";
    }
    
    return $html;
}

/**
 * Genera meta tags para una noticia
 * 
 * @param array $news Datos de la noticia
 * @return string HTML con meta tags
 */
function getNewsMetaTags($news) {
    // Construir la URL completa
    $url = SITE_URL . '/news.php?slug=' . $news['slug'];
    
    // Imagen para compartir
    $image = isset($news['image']) ? SITE_URL . '/' . $news['image'] : '';
    
    // Llamar a la función general
    return getSocialMetaTags(
        $url,
        $news['title'],
        isset($news['excerpt']) ? $news['excerpt'] : '',
        $image,
        'article'
    );
}
?>