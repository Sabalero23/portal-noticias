<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}

// Obtener título y meta descripción para la página actual
$pageTitle = '';
$metaDescription = '';

// Detectar la página actual
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Definir título y meta según la página
switch ($currentPage) {
    case 'index':
        $pageTitle = getSetting('site_name', 'Portal de Noticias');
        $metaDescription = getSetting('site_description', 'El portal de noticias más actualizado');
        break;
    case 'news':
        if (isset($news) && isset($news['title'])) {
            $pageTitle = $news['title'] . ' - ' . getSetting('site_name', 'Portal de Noticias');
            $metaDescription = $news['excerpt'];
        } else {
            $pageTitle = 'Noticia - ' . getSetting('site_name', 'Portal de Noticias');
        }
        break;
    case 'category':
        if (isset($category) && isset($category['name'])) {
            $pageTitle = $category['name'] . ' - ' . getSetting('site_name', 'Portal de Noticias');
            $metaDescription = $category['description'] ?? 'Noticias de ' . $category['name'];
        } else {
            $pageTitle = 'Categoría - ' . getSetting('site_name', 'Portal de Noticias');
        }
        break;
    case 'tag':
        if (isset($tag) && isset($tag['name'])) {
            $pageTitle = 'Etiqueta: ' . $tag['name'] . ' - ' . getSetting('site_name', 'Portal de Noticias');
        } else {
            $pageTitle = 'Etiqueta - ' . getSetting('site_name', 'Portal de Noticias');
        }
        break;
    case 'search':
        $query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
        if (!empty($query)) {
            $pageTitle = 'Resultados para: ' . $query . ' - ' . getSetting('site_name', 'Portal de Noticias');
        } else {
            $pageTitle = 'Búsqueda - ' . getSetting('site_name', 'Portal de Noticias');
        }
        break;
    case 'contact':
        $pageTitle = 'Contacto - ' . getSetting('site_name', 'Portal de Noticias');
        $metaDescription = 'Ponte en contacto con ' . getSetting('site_name', 'Portal de Noticias');
        break;
    case 'about':
        $pageTitle = 'Quiénes Somos - ' . getSetting('site_name', 'Portal de Noticias');
        $metaDescription = 'Conoce más sobre ' . getSetting('site_name', 'Portal de Noticias');
        break;
    default:
        $pageTitle = getSetting('site_name', 'Portal de Noticias');
        $metaDescription = getSetting('site_description', 'El portal de noticias más actualizado');
}

// Obtener redes sociales
$socialMedia = [
    'facebook' => getSetting('facebook', ''),
    'twitter' => getSetting('twitter', ''),
    'instagram' => getSetting('instagram', ''),
    'youtube' => getSetting('youtube', '')
];

// Obtener logo
$logo = getSetting('logo', 'assets/img/logo.png');
$favicon = getSetting('favicon', 'assets/img/favicon.ico');

// Verificar si la PWA está habilitada
$pwaEnabled = getSetting('pwa_enabled', '1') === '1';

// Obtener tema activo
$activeTheme = getSetting('active_theme', 'default');

// Obtener API key del clima e información de localización
$weatherApiKey = getSetting('weather_api_key', '');
$defaultCity = getSetting('weather_api_city', 'Reconquista');
$weatherApiUnits = getSetting('weather_api_units', 'metric');
$isValidWeatherApi = !empty($weatherApiKey) && $weatherApiKey !== 'your_api_key_here';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $metaDescription; ?>">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?php echo $favicon; ?>" type="image/x-icon">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Cargar estilos del tema activo -->
    <link href="assets/themes/<?php echo $activeTheme; ?>/styles.css" rel="stylesheet">
    <link href="assets/themes/<?php echo $activeTheme; ?>/responsive.css" rel="stylesheet">
    
    <?php if ($currentPage === 'news'): ?>
    <link href="assets/themes/<?php echo $activeTheme; ?>/single-news.css" rel="stylesheet">
    <?php endif; ?>
    
    <?php if ($pwaEnabled): ?>
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="<?php echo getSetting('pwa_theme_color', '#2196F3'); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="<?php echo getSetting('pwa_short_name', 'Noticias'); ?>">
    <link rel="apple-touch-icon" href="assets/img/icons/icon-152x152.png">
    <?php endif; ?>
    
    <!-- Open Graph / Social Media -->
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $metaDescription; ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo getCurrentUrl(); ?>">
    <meta property="og:image" content="<?php echo isset($news['image']) ? $news['image'] : SITE_URL . '/assets/img/og-image.jpg'; ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="<?php echo str_replace('https://twitter.com/', '@', $socialMedia['twitter']); ?>">
    <meta name="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta name="twitter:description" content="<?php echo $metaDescription; ?>">
    <meta name="twitter:image" content="<?php echo isset($news['image']) ? $news['image'] : SITE_URL . '/assets/img/og-image.jpg'; ?>">
    
    <!-- JSON-LD para SEO -->
    <?php if ($currentPage === 'news' && isset($news)): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "NewsArticle",
        "headline": "<?php echo $news['title']; ?>",
        "image": "<?php echo $news['image']; ?>",
        "datePublished": "<?php echo $news['published_at']; ?>",
        "dateModified": "<?php echo $news['updated_at']; ?>",
        "author": {
            "@type": "Person",
            "name": "<?php echo $news['author_name']; ?>"
        },
        "publisher": {
            "@type": "Organization",
            "name": "<?php echo getSetting('site_name', 'Portal de Noticias'); ?>",
            "logo": {
                "@type": "ImageObject",
                "url": "<?php echo SITE_URL . '/' . $logo; ?>"
            }
        },
        "description": "<?php echo $news['excerpt']; ?>",
        "mainEntityOfPage": {
            "@type": "WebPage",
            "@id": "<?php echo getCurrentUrl(); ?>"
        }
    }
    </script>
    <?php else: ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "<?php echo getSetting('site_name', 'Portal de Noticias'); ?>",
        "url": "<?php echo SITE_URL; ?>",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "<?php echo SITE_URL; ?>/search.php?q={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    <?php endif; ?>
    
    <!-- Google Analytics -->
    <?php $analyticsId = getSetting('analytics_id', ''); ?>
    <?php if (!empty($analyticsId) && $analyticsId != 'your_analytics_id_here'): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $analyticsId; ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo $analyticsId; ?>');
    </script>
    <?php endif; ?>
    
    <!-- Si la API del clima está configurada, cargar los estilos y script -->
    <?php if ($isValidWeatherApi): ?>
    <style>
    .weather-info {
        text-align: center;
    }
    .weather-location {
        margin-bottom: 10px;
        font-size: 16px;
    }
    .weather-main {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 5px;
    }
    .weather-icon {
        width: 64px;
        height: 64px;
    }
    .weather-temp {
        font-size: 42px;
        font-weight: bold;
        margin-left: 10px;
    }
    .weather-description {
        margin-bottom: 15px;
        font-style: italic;
    }
    .weather-details {
        display: flex;
        justify-content: center;
        gap: 20px;
    }
    .weather-detail {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar si existe el widget del clima
        const weatherWidget = document.getElementById('weather-widget');
        const weatherWidgetMini = document.getElementById('weather-temp-mini');
        
        if (weatherWidget || weatherWidgetMini) {
            // Obtener la ciudad configurada (o usar la ubicación actual)
            fetchWeatherData();
        }
    });

    function fetchWeatherData() {
        // Primero intentamos obtener la ubicación actual
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    // Si tenemos la ubicación, usamos las coordenadas
                    getWeatherByCoords(position.coords.latitude, position.coords.longitude);
                },
                error => {
                    // Si hay error o el usuario rechaza, usamos la ciudad configurada
                    getWeatherByCity();
                }
            );
        } else {
            // Si la geolocalización no está disponible, usamos la ciudad configurada
            getWeatherByCity();
        }
    }

    function getWeatherByCoords(lat, lon) {
        // Obtener clima basado en coordenadas
        fetch(`weather_api.php?lat=${lat}&lon=${lon}`)
            .then(response => response.json())
            .then(data => {
                updateWeatherWidget(data);
                updateMiniWeatherWidget(data);
            })
            .catch(error => {
                console.error('Error al obtener el clima:', error);
                getWeatherByCity(); // Respaldo: obtener por ciudad configurada
            });
    }

    function getWeatherByCity() {
        // Obtener clima basado en la ciudad configurada
        fetch('weather_api.php')
            .then(response => response.json())
            .then(data => {
                updateWeatherWidget(data);
                updateMiniWeatherWidget(data);
            })
            .catch(error => {
                console.error('Error al obtener el clima:', error);
                showWeatherError();
            });
    }

    function updateWeatherWidget(data) {
        const weatherWidget = document.getElementById('weather-widget');
        if (!weatherWidget) return;
        
        if (data.error) {
            showWeatherError(data.error);
            return;
        }
        
        // Iconos según el clima https://openweathermap.org/weather-conditions
        const iconUrl = `https://openweathermap.org/img/wn/${data.weather[0].icon}@2x.png`;
        
        weatherWidget.innerHTML = `
            <div class="weather-info">
                <div class="weather-location">
                    <i class="fas fa-map-marker-alt"></i> ${data.name}, ${data.sys.country}
                </div>
                <div class="weather-main">
                    <img src="${iconUrl}" alt="${data.weather[0].description}" class="weather-icon">
                    <div class="weather-temp">${Math.round(data.main.temp)}°</div>
                </div>
                <div class="weather-description">${capitalizeFirstLetter(data.weather[0].description)}</div>
                <div class="weather-details">
                    <div class="weather-detail">
                        <i class="fas fa-tint"></i> ${data.main.humidity}%
                    </div>
                    <div class="weather-detail">
                        <i class="fas fa-wind"></i> ${Math.round(data.wind.speed * 3.6)} km/h
                    </div>
                </div>
            </div>
        `;
    }
    
    function updateMiniWeatherWidget(data) {
        const weatherWidgetMini = document.getElementById('weather-temp-mini');
        if (!weatherWidgetMini) return;
        
        if (data.error) {
            weatherWidgetMini.innerHTML = 'N/D';
            return;
        }
        
        weatherWidgetMini.innerHTML = `${data.name} ${Math.round(data.main.temp)}°`;
    }

    function showWeatherError(message = 'No se pudo cargar la información del clima') {
        const weatherWidget = document.getElementById('weather-widget');
        if (weatherWidget) {
            weatherWidget.innerHTML = `
                <div class="text-center">
                    <i class="fas fa-cloud-rain text-muted fa-2x mb-2"></i>
                    <p class="mb-0">${message}</p>
                </div>
            `;
        }
        
        const weatherWidgetMini = document.getElementById('weather-temp-mini');
        if (weatherWidgetMini) {
            weatherWidgetMini.innerHTML = 'N/D';
        }
    }

    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
    </script>
    <?php endif; ?>
</head>
<body <?php if ($isValidWeatherApi): ?>data-weather-api-key="<?php echo $weatherApiKey; ?>" data-default-city="<?php echo $defaultCity; ?>"<?php endif; ?>>
    <!-- Top Bar -->
    <div class="top-bar bg-dark text-white py-2">
        <div class="container">
            <div class="row">
                <!-- Columna izquierda - Visible en todas las pantallas -->
                <div class="col-lg-6 col-md-6 col-sm-8 col-7">
                    <div class="top-bar-left d-flex align-items-center">
                        <!-- Fecha - Solo en desktop y tablet -->
                        <span class="me-3 d-none d-md-inline">
                            <i class="fas fa-calendar-alt me-1"></i> 
                            <span class="d-none d-lg-inline"><?php echo date('D, d M Y'); ?></span>
                            <span class="d-inline d-lg-none"><?php echo date('d/m'); ?></span>
                        </span>
                        
                        <!-- Clima - Visible en todas las pantallas -->
                        <?php if ($isValidWeatherApi): ?>
                            <span id="weather-widget-mini" class="weather-mini">
                                <i class="fas fa-cloud me-1"></i> 
                                <span id="weather-temp-mini">
                                    <span class="d-none d-sm-inline">Cargando...</span>
                                    <span class="d-inline d-sm-none">--°</span>
                                </span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Columna derecha - Redes sociales y usuario -->
                <div class="col-lg-6 col-md-6 col-sm-4 col-5">
                    <div class="top-bar-right d-flex justify-content-end align-items-center">
                        <!-- Redes sociales - Solo en tablet y desktop -->
                        <div class="social-links d-none d-md-flex me-2">
                            <?php if (!empty($socialMedia['facebook'])): ?>
                                <a href="<?php echo $socialMedia['facebook']; ?>" class="text-white me-2" target="_blank" title="Facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($socialMedia['twitter'])): ?>
                                <a href="<?php echo $socialMedia['twitter']; ?>" class="text-white me-2" target="_blank" title="Twitter">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($socialMedia['instagram'])): ?>
                                <a href="<?php echo $socialMedia['instagram']; ?>" class="text-white me-2" target="_blank" title="Instagram">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($socialMedia['youtube'])): ?>
                                <a href="<?php echo $socialMedia['youtube']; ?>" class="text-white me-2" target="_blank" title="YouTube">
                                    <i class="fab fa-youtube"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Usuario logueado o enlaces de login/registro -->
                        <?php if (isLoggedIn()): ?>
                            <div class="dropdown">
                                <button class="btn btn-link text-white dropdown-toggle p-0" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Mi cuenta">
                                    <i class="fas fa-user-circle me-1"></i> 
                                    <!-- Mostrar username en desktop, solo icono en móvil -->
                                    <span class="d-none d-sm-inline"><?php echo $_SESSION['user']['username']; ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <?php if (hasRole(['admin', 'editor', 'author'])): ?>
                                    <li><a class="dropdown-item" href="admin/"><i class="fas fa-tachometer-alt me-2"></i>Panel Admin</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <!-- Desktop: mostrar ambos enlaces -->
                            <div class="d-none d-md-block">
                                <a href="login.php" class="text-white me-2" title="Iniciar sesión">
                                    <i class="fas fa-sign-in-alt me-1"></i> Ingresar
                                </a>
                                <span class="text-white-50 mx-1">|</span>
                                <a href="register.php" class="text-white ms-2" title="Registrarse">
                                    <i class="fas fa-user-plus me-1"></i> Registro
                                </a>
                            </div>
                            
                            <!-- Móvil: solo iconos -->
                            <div class="d-block d-md-none">
                                <a href="login.php" class="text-white me-2" title="Iniciar sesión">
                                    <i class="fas fa-sign-in-alt"></i>
                                </a>
                                <a href="register.php" class="text-white" title="Registrarse">
                                    <i class="fas fa-user-plus"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Header -->
    <header class="site-header py-3">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-4 col-md-6 mb-3 mb-md-0">
                    <div class="site-logo">
                        <a href="index.php">
                            <img src="<?php echo $logo; ?>" alt="<?php echo getSetting('site_name', 'Portal de Noticias'); ?>" class="img-fluid">
                        </a>
                    </div>
                </div>
                <div class="col-lg-8 col-md-6">
                    <?php if (isset($ads['header']) && $ads['header']): ?>
                        <div class="header-ad text-center">
                            <a href="<?php echo $ads['header']['url']; ?>" target="_blank" class="ad-link" data-ad-id="<?php echo $ads['header']['id']; ?>">
                                <img src="<?php echo $ads['header']['image']; ?>" alt="<?php echo $ads['header']['title']; ?>" class="img-fluid">
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'index' ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-home"></i> Inicio
                        </a>
                    </li>
                    
                    <?php foreach ($categories as $category): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'category' && isset($_GET['slug']) && $_GET['slug'] === $category['slug']) ? 'active' : ''; ?>" href="category.php?slug=<?php echo $category['slug']; ?>">
                            <?php echo $category['name']; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    
                    <!--  <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'contact' ? 'active' : ''; ?>" href="contact.php">
                            Contacto
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'about' ? 'active' : ''; ?>" href="about.php">
                            Quiénes Somos
                        </a>
                    </li> -->
                </ul>
                
                <!-- Búsqueda rápida 
                <form class="d-flex d-none d-lg-block" action="search.php" method="get">
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm" placeholder="Buscar..." name="q" required>
                        <button class="btn btn-light btn-sm" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form> -->
            </div>
        </div>
    </nav>
    
    <!-- Flash Messages -->
    <?php if ($flashMessage = getFlashMessage()): ?>
    <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $flashMessage['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>