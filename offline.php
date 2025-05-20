<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Título y meta para esta página
$pageTitle = 'Sin conexión - Portal de Noticias';
$metaDescription = 'Actualmente no hay conexión a Internet.';

// Incluir el archivo de configuración sin intentar conectar a la base de datos
if (file_exists('includes/config.php')) {
    include_once 'includes/config.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $metaDescription; ?>">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/img/favicon.ico" type="image/x-icon">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <link href="assets/css/responsive.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .offline-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            text-align: center;
        }
        
        .offline-icon {
            font-size: 5rem;
            color: #6c757d;
            margin-bottom: 2rem;
        }
        
        .cached-container {
            max-width: 600px;
            margin: 2rem auto;
        }
        
        .footer {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="site-header py-3">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-4 col-md-6 mb-3 mb-md-0">
                    <div class="site-logo">
                        <a href="index.php">
                            <img src="assets/img/logo.png" alt="Portal de Noticias" class="img-fluid">
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Contenido -->
    <div class="offline-container">
        <div class="offline-icon">
            <i class="fas fa-wifi"></i>
        </div>
        
        <h1 class="mb-4">Sin conexión a Internet</h1>
        <p class="lead mb-4">No se pudo cargar la página porque no hay conexión a Internet.</p>
        
        <button class="btn btn-primary mb-5" onclick="tryReload()">
            <i class="fas fa-sync-alt me-2"></i>Intentar nuevamente
        </button>
        
        <div class="cached-container">
            <h4 class="mb-3">Contenido guardado</h4>
            <p>Puedes acceder a algunas páginas que hayas visitado anteriormente:</p>
            
            <div id="cached-pages" class="list-group mt-3">
                <!-- El JavaScript llenará esto -->
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Portal de Noticias. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        // Función para recargar la página
        function tryReload() {
            window.location.reload();
        }
        
        // Mostrar páginas en caché
        document.addEventListener('DOMContentLoaded', function() {
            if ('caches' in window) {
                const cachedPagesContainer = document.getElementById('cached-pages');
                
                caches.open('dynamic-v1') // Usar el mismo nombre que en service-worker.js
                    .then(cache => {
                        return cache.keys()
                            .then(requests => {
                                // Filtrar solo las páginas HTML (PHP)
                                const htmlRequests = requests.filter(request => {
                                    return request.url.endsWith('.php') && 
                                           !request.url.includes('offline.php') &&
                                           request.url.includes(window.location.hostname);
                                });
                                
                                if (htmlRequests.length === 0) {
                                    cachedPagesContainer.innerHTML = '<div class="alert alert-info">No hay páginas almacenadas en caché.</div>';
                                    return;
                                }
                                
                                // Crear lista de páginas en caché
                                let html = '';
                                htmlRequests.forEach(request => {
                                    const url = new URL(request.url);
                                    const path = url.pathname;
                                    
                                    let pageName = 'Página';
                                    
                                    // Determinar el nombre de la página basado en la URL
                                    if (path === '/' || path === '/index.php') {
                                        pageName = 'Página principal';
                                    } else if (path.includes('/news.php')) {
                                        pageName = 'Noticia';
                                    } else if (path.includes('/category.php')) {
                                        pageName = 'Categoría';
                                    } else if (path.includes('/tag.php')) {
                                        pageName = 'Etiqueta';
                                    } else if (path.includes('/search.php')) {
                                        pageName = 'Búsqueda';
                                    } else if (path.includes('/contact.php')) {
                                        pageName = 'Contacto';
                                    } else if (path.includes('/about.php')) {
                                        pageName = 'Quiénes somos';
                                    }
                                    
                                    html += `
                                        <a href="${request.url}" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1">${pageName}</h5>
                                            </div>
                                            <small class="text-muted">${url.pathname}${url.search}</small>
                                        </a>
                                    `;
                                });
                                
                                cachedPagesContainer.innerHTML = html;
                            });
                    })
                    .catch(error => {
                        console.error('Error al obtener páginas en caché:', error);
                        cachedPagesContainer.innerHTML = '<div class="alert alert-danger">Error al cargar páginas en caché.</div>';
                    });
            } else {
                const cachedPagesContainer = document.getElementById('cached-pages');
                cachedPagesContainer.innerHTML = '<div class="alert alert-warning">Tu navegador no soporta el acceso a caché.</div>';
            }
        });
    </script>
</body>
</html>