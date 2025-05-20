<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}

// Función para generar URL absoluta al directorio admin
function adminUrl($path = '') {
    // Determinar la URL base del admin
    $adminDir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Si estamos en un subdirectorio de admin, obtener la ruta base de admin
    if (strpos($adminDir, '/admin/') !== false) {
        $adminBase = substr($adminDir, 0, strpos($adminDir, '/admin/') + 7);
    } else if (basename($adminDir) === 'admin') {
        $adminBase = $adminDir . '/';
    } else {
        $adminBase = $adminDir . '/'; // Fallback
    }
    
    return $adminBase . ltrim($path, '/');
}

// Función para generar URL absoluta a los assets
function assetUrl($path = '') {
    // La carpeta assets está un nivel arriba de admin
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $adminPos = strpos($scriptName, '/admin/');
    
    if ($adminPos !== false) {
        $baseUrl = substr($scriptName, 0, $adminPos);
    } else {
        $baseUrl = dirname(dirname($_SERVER['SCRIPT_NAME']));
    }
    
    return $baseUrl . '/assets/' . ltrim($path, '/');
}

// Obtener usuario actual
$currentUser = $_SESSION['user'] ?? null;

// Verificar si hay notificaciones
$notifications = [];

// Verificar comentarios pendientes
if (hasRole(['admin', 'editor'])) {
    $db = Database::getInstance();
    $pendingComments = $db->fetch(
        "SELECT COUNT(*) as count FROM comments WHERE status = 'pending'"
    );
    
    if ($pendingComments && $pendingComments['count'] > 0) {
        $notifications[] = [
            'text' => $pendingComments['count'] . ' comentarios pendientes de moderación',
            'url' => 'comments/index.php?status=pending',
            'icon' => 'fas fa-comment'
        ];
    }
}

// Verificar noticias pendientes
if (hasRole(['admin', 'editor'])) {
    $db = Database::getInstance();
    $pendingNews = $db->fetch(
        "SELECT COUNT(*) as count FROM news WHERE status = 'pending'"
    );
    
    if ($pendingNews && $pendingNews['count'] > 0) {
        $notifications[] = [
            'text' => $pendingNews['count'] . ' noticias pendientes de revisión',
            'url' => 'news/index.php?status=pending',
            'icon' => 'fas fa-newspaper'
        ];
    }
}

// Obtener logo
$logo = getSetting('logo', 'assets/img/logo.png');
$favicon = getSetting('favicon', 'assets/img/favicon.ico');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?php echo assetUrl(substr($favicon, 7)); ?>" type="image/x-icon">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo assetUrl('css/admin.css'); ?>" rel="stylesheet">
    
    <!-- CSS específico de la página -->
    <?php if (isset($extraCSS)): ?>
        <?php foreach ($extraCSS as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="<?php echo adminUrl('dashboard.php'); ?>" class="nav-link">Dashboard</a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="<?php echo substr(assetUrl(), 0, -7); ?>" class="nav-link" target="_blank">Ver sitio</a>
                </li>
            </ul>
            
            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                <!-- Notifications Dropdown Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-bs-toggle="dropdown" href="#">
                        <i class="far fa-bell"></i>
                        <?php if (count($notifications) > 0): ?>
                            <span class="badge bg-warning"><?php echo count($notifications); ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <span class="dropdown-item dropdown-header"><?php echo count($notifications); ?> Notificaciones</span>
                        <div class="dropdown-divider"></div>
                        
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <a href="<?php echo adminUrl($notification['url']); ?>" class="dropdown-item">
                                    <i class="<?php echo $notification['icon']; ?> mr-2"></i> <?php echo $notification['text']; ?>
                                </a>
                                <div class="dropdown-divider"></div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <a href="#" class="dropdown-item">
                                <i class="fas fa-check mr-2"></i> No hay notificaciones nuevas
                            </a>
                            <div class="dropdown-divider"></div>
                        <?php endif; ?>
                    </div>
                </li>
                
                <!-- User Dropdown Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-bs-toggle="dropdown" href="#">
                        <i class="far fa-user"></i> <?php echo $currentUser['username']; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="<?php echo adminUrl('profile.php'); ?>" class="dropdown-item">
                            <i class="fas fa-user mr-2"></i> Mi perfil
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo adminUrl('logout.php'); ?>" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i> Cerrar sesión
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
        
        <!-- Flash Messages (Alertas) -->
        <?php if ($flashMessage = getFlashMessage()): ?>
            <div class="alert-container">
                <div class="container-fluid">
                    <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo $flashMessage['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>