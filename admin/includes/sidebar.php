<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}

// Obtener usuario actual
$currentUser = $_SESSION['user'] ?? null;

// Obtener información del sitio
$siteName = getSetting('site_name', 'Portal de Noticias');
$logo = getSetting('logo', 'assets/img/logo.png');

// Determinar nivel de profundidad y ajustar rutas
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
$isSubfolder = strpos($currentScript, '/admin/') !== false && substr_count($currentScript, '/', strpos($currentScript, '/admin/')) > 2;
$basePrefix = $isSubfolder ? '../' : '';

// Ajustar URLs para enlaces
function adjustUrl($url, $isSubfolder) {
    // Si es una URL absoluta (http:// o https://), no necesita ajuste
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        return $url;
    }
    
    // Si ya empieza con "../", no ajustar
    if (strpos($url, '../') === 0) {
        return $url;
    }
    
    // Si estamos en una subcarpeta, añadir el prefijo
    if ($isSubfolder && $url[0] !== '/') {
        return '../' . $url;
    }
    
    return $url;
}
?>

<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
<!-- Brand Logo -->
<a href="<?php echo $basePrefix; ?>dashboard.php" class="brand-link">
    <div style="text-align: center; display: block;">
        <img src="<?php echo $basePrefix; ?>../<?php echo $logo; ?>" alt="<?php echo $siteName; ?>" class="brand-image img-circle elevation-3" style="opacity: .8; float: none; display: inline-block; margin-left: 0; margin-right: 0;">
        <span class="brand-text font-weight-light" style="display: block; margin-top: 5px; text-align: center; width: 100%;"><?php echo $siteName; ?></span>
    </div>
</a>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <?php if (isset($currentUser['avatar']) && !empty($currentUser['avatar'])): ?>
                    <img src="<?php echo $basePrefix; ?>../<?php echo $currentUser['avatar']; ?>" class="img-circle elevation-2" alt="<?php echo $currentUser['name']; ?>">
                <?php else: ?>
                    <img src="<?php echo $basePrefix; ?>../assets/img/authors/default.jpg" class="img-circle elevation-2" alt="<?php echo $currentUser['name']; ?>">
                <?php endif; ?>
            </div>
            <div class="info">
                <a href="<?php echo $basePrefix; ?>profile.php" class="d-block"><?php echo $currentUser['name']; ?></a>
                <small class="text-muted d-block"><?php echo ucfirst($currentUser['role']); ?></small>
            </div>
        </div>
        
        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?php echo $basePrefix; ?>dashboard.php" class="nav-link <?php echo $currentMenu === 'dashboard' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <!-- Noticias -->
                <li class="nav-item <?php echo in_array($currentMenu, ['news', 'news_add', 'news_edit']) ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo in_array($currentMenu, ['news', 'news_add', 'news_edit']) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-newspaper"></i>
                        <p>
                            Noticias
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>news/index.php" class="nav-link <?php echo $currentMenu === 'news' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todas las noticias</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>news/add.php" class="nav-link <?php echo $currentMenu === 'news_add' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Añadir nueva</p>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Categorías -->
                <li class="nav-item <?php echo in_array($currentMenu, ['categories', 'categories_add', 'categories_edit']) ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo in_array($currentMenu, ['categories', 'categories_add', 'categories_edit']) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-folder"></i>
                        <p>
                            Categorías
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>categories/index.php" class="nav-link <?php echo $currentMenu === 'categories' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todas las categorías</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>categories/add.php" class="nav-link <?php echo $currentMenu === 'categories_add' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Añadir nueva</p>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Comentarios -->
                <?php if (hasRole(['admin', 'editor'])): ?>
                <li class="nav-item">
                    <a href="<?php echo $basePrefix; ?>comments/index.php" class="nav-link <?php echo $currentMenu === 'comments' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-comments"></i>
                        <p>
                            Comentarios
                            <?php 
                            // Mostrar contador de comentarios pendientes
                            $db = Database::getInstance();
                            $pendingComments = $db->fetch("SELECT COUNT(*) as count FROM comments WHERE status = 'pending'");
                            if ($pendingComments && $pendingComments['count'] > 0):
                            ?>
                            <span class="badge badge-warning right"><?php echo $pendingComments['count']; ?></span>
                            <?php endif; ?>
                        </p>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Medios -->
                <li class="nav-item">
                    <a href="<?php echo $basePrefix; ?>media/index.php" class="nav-link <?php echo $currentMenu === 'media' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-images"></i>
                        <p>Biblioteca de medios</p>
                    </a>
                </li>
                
                <!-- Encuestas -->
                <?php if (hasRole(['admin', 'editor'])): ?>
                <li class="nav-item <?php echo in_array($currentMenu, ['polls', 'polls_add', 'polls_edit', 'polls_results']) ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo in_array($currentMenu, ['polls', 'polls_add', 'polls_edit', 'polls_results']) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>
                            Encuestas
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>polls/index.php" class="nav-link <?php echo $currentMenu === 'polls' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todas las encuestas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>polls/add.php" class="nav-link <?php echo $currentMenu === 'polls_add' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Añadir nueva</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>polls/results.php" class="nav-link <?php echo $currentMenu === 'polls_results' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Ver resultados</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Anuncios -->
                <?php if (hasRole(['admin', 'editor'])): ?>
                <li class="nav-item <?php echo in_array($currentMenu, ['ads', 'ads_add', 'ads_edit']) ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo in_array($currentMenu, ['ads', 'ads_add', 'ads_edit']) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-ad"></i>
                        <p>
                            Publicidad
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>ads/index.php" class="nav-link <?php echo $currentMenu === 'ads' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todos los anuncios</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>ads/add.php" class="nav-link <?php echo $currentMenu === 'ads_add' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Añadir nuevo</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Suscriptores -->
                <?php if (hasRole(['admin', 'editor'])): ?>
                <li class="nav-item">
                    <a href="<?php echo $basePrefix; ?>subscribers/index.php" class="nav-link <?php echo $currentMenu === 'subscribers' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-envelope"></i>
                        <p>Suscriptores</p>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Usuarios -->
                <?php if (hasRole(['admin'])): ?>
                <li class="nav-item <?php echo in_array($currentMenu, ['users', 'users_add', 'users_edit']) ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo in_array($currentMenu, ['users', 'users_add', 'users_edit']) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>
                            Usuarios
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>users/index.php" class="nav-link <?php echo $currentMenu === 'users' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todos los usuarios</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>users/add.php" class="nav-link <?php echo $currentMenu === 'users_add' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Añadir nuevo</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Estadísticas -->
                <li class="nav-item <?php echo in_array($currentMenu, ['statistics', 'statistics_news', 'statistics_categories', 'statistics_export']) ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo in_array($currentMenu, ['statistics', 'statistics_news', 'statistics_categories', 'statistics_export']) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <p>
                            Estadísticas
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>statistics/index.php" class="nav-link <?php echo $currentMenu === 'statistics' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Panel general</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>statistics/news.php" class="nav-link <?php echo $currentMenu === 'statistics_news' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Por noticia</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>statistics/categories.php" class="nav-link <?php echo $currentMenu === 'statistics_categories' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Por categoría</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>statistics/export.php" class="nav-link <?php echo $currentMenu === 'statistics_export' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Exportar datos</p>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Configuración -->
                <?php if (hasRole(['admin'])): ?>
                <li class="nav-item <?php echo in_array($currentMenu, ['settings', 'settings_site', 'settings_social', 'settings_api', 'settings_themes']) ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo in_array($currentMenu, ['settings', 'settings_site', 'settings_social', 'settings_api', 'settings_themes']) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>
                            Configuración
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>settings/index.php" class="nav-link <?php echo $currentMenu === 'settings' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>General</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>settings/site.php" class="nav-link <?php echo $currentMenu === 'settings_site' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Sitio</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>settings/themes.php" class="nav-link <?php echo $currentMenu === 'settings_themes' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Temas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>settings/social.php" class="nav-link <?php echo $currentMenu === 'settings_social' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Redes sociales</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo $basePrefix; ?>settings/api.php" class="nav-link <?php echo $currentMenu === 'settings_api' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>APIs</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

<!-- AGREGAR AQUÍ EL TUTORIAL -->
<!-- Tutorial -->
<li class="nav-item">
    <a href="tutorial.php" class="nav-link <?php echo $currentMenu === 'tutorial' ? 'active' : ''; ?>">
        <i class="nav-icon fas fa-graduation-cap"></i>
        <p>Tutorial</p>
    </a>
</li>
                
            </ul>
        </nav>
    </div>
</aside>