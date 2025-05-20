<?php
// Definir ruta base
define('BASE_PATH', dirname(dirname(__DIR__)));
define('ADMIN_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor', 'author'], '../index.php');

// Título de la página
$pageTitle = 'Estadísticas por Categoría - Panel de Administración';
$currentMenu = 'statistics_categories';

// Incluir cabecera
include_once ADMIN_PATH . '/includes/header.php';
include_once ADMIN_PATH . '/includes/sidebar.php';

// Obtener ID de categoría (opcional)
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener rangos de fechas
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Validar fechas
if (!strtotime($start_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
}
if (!strtotime($end_date)) {
    $end_date = date('Y-m-d');
}

// Verificar que end_date sea mayor o igual a start_date
if (strtotime($end_date) < strtotime($start_date)) {
    $temp = $end_date;
    $end_date = $start_date;
    $start_date = $temp;
}

// Base de datos
$db = Database::getInstance();

// Si se proporcionó ID, mostrar estadísticas detalladas de esa categoría
if ($categoryId > 0) {
    // Obtener información de la categoría
    $category = $db->fetch(
        "SELECT id, name, slug, description, color 
         FROM categories 
         WHERE id = ?",
        [$categoryId]
    );
    
    if (!$category) {
        // Si no se encuentra la categoría, redirigir
        setFlashMessage('error', 'Categoría no encontrada');
        redirect('categories.php');
        exit;
    }
    
    // Obtener estadísticas de la categoría en el período
    $viewsTotal = $db->fetch(
        "SELECT COUNT(*) as total 
         FROM view_logs vl 
         JOIN news n ON vl.news_id = n.id 
         WHERE n.category_id = ? AND vl.viewed_at BETWEEN ? AND ?",
        [$categoryId, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
    );
    
    // Promedio de vistas diarias en el período
    $dateInterval = max(1, ceil((strtotime($end_date) - strtotime($start_date)) / 86400));
    $viewsAverage = ($viewsTotal && isset($viewsTotal['total'])) ? round($viewsTotal['total'] / $dateInterval, 2) : 0;
    
    // Total de noticias en la categoría
    $totalNews = $db->fetch(
        "SELECT COUNT(*) as total 
         FROM news 
         WHERE category_id = ? AND status = 'published'",
        [$categoryId]
    );
    
    // Noticias publicadas en el período
    $newsInPeriod = $db->fetch(
        "SELECT COUNT(*) as total 
         FROM news 
         WHERE category_id = ? AND status = 'published' AND published_at BETWEEN ? AND ?",
        [$categoryId, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
    );
    
    // Obtener datos para gráfico de vistas diarias
    $viewsByDay = $db->fetchAll(
        "SELECT DATE(vl.viewed_at) as date, COUNT(*) as count 
         FROM view_logs vl 
         JOIN news n ON vl.news_id = n.id 
         WHERE n.category_id = ? AND vl.viewed_at BETWEEN ? AND ? 
         GROUP BY DATE(vl.viewed_at) 
         ORDER BY date ASC",
        [$categoryId, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
    );
    
    // Preparar datos para gráfico de vistas diarias
    $dayLabels = [];
    $dayData = [];
    
    // Inicializar array con todas las fechas del período
    $currentDate = new DateTime($start_date);
    $endDateObj = new DateTime($end_date);
    $endDateObj->setTime(23, 59, 59);
    
    while ($currentDate <= $endDateObj) {
        $currentDateStr = $currentDate->format('Y-m-d');
        $dayLabels[] = $currentDate->format('d M');
        $dayData[$currentDateStr] = 0;
        $currentDate->modify('+1 day');
    }
    
    // Rellenar con los datos reales
    foreach ($viewsByDay as $day) {
        $dayData[$day['date']] = (int)$day['count'];
    }
    
    // Obtener top noticias de la categoría
    $topNews = $db->fetchAll(
        "SELECT n.id, n.title, n.slug, n.views, COUNT(vl.id) as view_count 
         FROM news n 
         JOIN view_logs vl ON n.id = vl.news_id 
         WHERE n.category_id = ? AND vl.viewed_at BETWEEN ? AND ? 
         GROUP BY n.id 
         ORDER BY view_count DESC 
         LIMIT 10",
        [$categoryId, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
    );
    
    // Datos para gráfico comparativo con otras categorías
    $categoriesComparison = $db->fetchAll(
        "SELECT c.id, c.name, c.color, COUNT(vl.id) as view_count 
         FROM categories c 
         JOIN news n ON c.id = n.category_id 
         JOIN view_logs vl ON n.id = vl.news_id 
         WHERE vl.viewed_at BETWEEN ? AND ? 
         GROUP BY c.id 
         ORDER BY view_count DESC",
        [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
    );
    
    // Preparar datos para gráfico de comparación
    $categoryLabels = [];
    $categoryData = [];
    $categoryColors = [];
    
    foreach ($categoriesComparison as $cat) {
        $categoryLabels[] = $cat['name'];
        $categoryData[] = (int)$cat['view_count'];
        $categoryColors[] = $cat['color'] ?: '#2196F3';
    }
    
    // Distribución por dispositivo para esta categoría
    $devices = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN user_agent LIKE '%Android%' THEN 'Android' 
                WHEN user_agent LIKE '%iPhone%' THEN 'iPhone' 
                WHEN user_agent LIKE '%iPad%' THEN 'iPad' 
                WHEN user_agent LIKE '%Windows%' THEN 'Windows' 
                Continuaré con el archivo categories.php y los archivos restantes:
php// Continuación de categories.php
                WHEN user_agent LIKE '%Macintosh%' THEN 'Mac' 
                WHEN user_agent LIKE '%Linux%' THEN 'Linux' 
                ELSE 'Otro' 
            END as device, 
            COUNT(*) as count 
         FROM view_logs vl
         JOIN news n ON vl.news_id = n.id 
         WHERE n.category_id = ? AND vl.viewed_at BETWEEN ? AND ? 
         GROUP BY device 
         ORDER BY count DESC",
        [$categoryId, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
    );
    
    // Preparar datos para gráfico de dispositivos
    $deviceLabels = [];
    $deviceData = [];
    
    foreach ($devices as $device) {
        $deviceLabels[] = $device['device'];
        $deviceData[] = (int)$device['count'];
    }
    
    // Noticias recientes de la categoría
    $recentNews = $db->fetchAll(
        "SELECT n.id, n.title, n.slug, n.published_at, n.views, u.name as author_name 
         FROM news n 
         JOIN users u ON n.author_id = u.id 
         WHERE n.category_id = ? AND n.status = 'published' 
         ORDER BY n.published_at DESC 
         LIMIT 5",
        [$categoryId]
    );
    
} else {
    // Listado de categorías con estadísticas
    
    // Calcular intervalo de días para comparación
    $dateInterval = max(1, ceil((strtotime($end_date) - strtotime($start_date)) / 86400));
    
    // Obtener total de vistas por categoría en el período
    $categories = $db->fetchAll(
        "SELECT 
            c.id, 
            c.name, 
            c.slug, 
            c.color, 
            c.description,
            (SELECT COUNT(*) FROM news WHERE category_id = c.id AND status = 'published') as news_count,
            (SELECT COUNT(*) FROM view_logs vl JOIN news n ON vl.news_id = n.id WHERE n.category_id = c.id) as total_views,
            (SELECT COUNT(*) FROM view_logs vl JOIN news n ON vl.news_id = n.id WHERE n.category_id = c.id AND vl.viewed_at BETWEEN ? AND ?) as period_views
         FROM categories c
         ORDER BY period_views DESC",
        [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
    );
    
    // Datos para gráfico de tarta de vistas por categoría
    $categoryLabels = [];
    $categoryData = [];
    $categoryColors = [];
    
    foreach ($categories as $category) {
        if ($category['period_views'] > 0) {
            $categoryLabels[] = $category['name'];
            $categoryData[] = (int)$category['period_views'];
            $categoryColors[] = $category['color'] ?: '#2196F3';
        }
    }
    
    // Datos para gráfico de vistas diarias totales
    $viewsByDay = $db->fetchAll(
        "SELECT DATE(viewed_at) as date, COUNT(*) as count 
         FROM view_logs 
         WHERE viewed_at BETWEEN ? AND ? 
         GROUP BY DATE(viewed_at) 
         ORDER BY date ASC",
        [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
    );
    
    // Preparar datos para gráfico de vistas diarias
    $dayLabels = [];
    $dayData = [];
    
    foreach ($viewsByDay as $day) {
        $dayLabels[] = date('d M', strtotime($day['date']));
        $dayData[] = (int)$day['count'];
    }
    
    // Top categorías por crecimiento (comparado con período anterior)
    $previousStart = date('Y-m-d', strtotime($start_date . ' -' . $dateInterval . ' days'));
    $previousEnd = date('Y-m-d', strtotime($start_date . ' -1 day'));
    
    $growthCategories = [];
    
    foreach ($categories as $category) {
        // Vistas en período anterior
        $previousViews = $db->fetch(
            "SELECT COUNT(*) as count 
             FROM view_logs vl 
             JOIN news n ON vl.news_id = n.id 
             WHERE n.category_id = ? AND vl.viewed_at BETWEEN ? AND ?",
            [$category['id'], $previousStart . ' 00:00:00', $previousEnd . ' 23:59:59']
        );
        
        $prevCount = $previousViews['count'] ?? 0;
        $currentCount = $category['period_views'] ?? 0;
        
        // Calcular crecimiento
        if ($prevCount > 0) {
            $growth = (($currentCount - $prevCount) / $prevCount) * 100;
        } elseif ($currentCount > 0) {
            $growth = 100; // Si no había vistas antes pero ahora sí, 100% de crecimiento
        } else {
            $growth = 0; // Si no hay vistas en ningún período
        }
        
        $growthCategories[] = [
            'id' => $category['id'],
            'name' => $category['name'],
            'color' => $category['color'],
            'previous_views' => $prevCount,
            'current_views' => $currentCount,
            'growth' => $growth
        ];
    }
    
    // Ordenar por crecimiento
    usort($growthCategories, function($a, $b) {
        return $b['growth'] <=> $a['growth'];
    });
    
    // Limitar a top 5
    $growthCategories = array_slice($growthCategories, 0, 5);
}
?>

<!-- Contenido principal -->
<div class="content-wrapper">
    <!-- Cabecera de página -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <?php if ($categoryId > 0): ?>
                            Estadísticas de: <?php echo $category['name']; ?>
                        <?php else: ?>
                            Estadísticas por Categoría
                        <?php endif; ?>
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Estadísticas</a></li>
                        <li class="breadcrumb-item active">Por Categoría</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            
            <!-- Selector de fechas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Período de análisis</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="<?php echo $categoryId > 0 ? 'categories.php' : 'categories.php'; ?>" class="row g-3 align-items-end">
                        <?php if ($categoryId > 0): ?>
                            <input type="hidden" name="id" value="<?php echo $categoryId; ?>">
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Fecha de inicio</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">Fecha de fin</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filtrar
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-white">
                    <div class="btn-group">
                        <a href="?<?php echo $categoryId > 0 ? 'id=' . $categoryId . '&' : ''; ?>start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary">Últimos 7 días</a>
                        <a href="?<?php echo $categoryId > 0 ? 'id=' . $categoryId . '&' : ''; ?>start_date=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary">Últimos 30 días</a>
                        <a href="?<?php echo $categoryId > 0 ? 'id=' . $categoryId . '&' : ''; ?>start_date=<?php echo date('Y-m-d', strtotime('first day of this month')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary">Este mes</a>
                        <a href="?<?php echo $categoryId > 0 ? 'id=' . $categoryId . '&' : ''; ?>start_date=<?php echo date('Y-m-d', strtotime('first day of last month')); ?>&end_date=<?php echo date('Y-m-d', strtotime('last day of last month')); ?>" class="btn btn-outline-secondary">Mes anterior</a>
                    </div>
                </div>
            </div>
            
            <?php if ($categoryId > 0): ?>
                <!-- Detalles de categoría -->
                
                <!-- Información de la categoría -->
                <div class="card mb-4">
                    <div class="card-header" style="background-color: <?php echo $category['color'] ?: '#2196F3'; ?>; color: white;">
                        <h5 class="card-title mb-0">Categoría: <?php echo $category['name']; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <?php if (!empty($category['description'])): ?>
                                    <p class="mb-3"><?php echo $category['description']; ?></p>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <a href="../../category.php?slug=<?php echo $category['slug']; ?>" target="_blank" class="btn btn-sm btn-info">
                                        <i class="fas fa-external-link-alt me-1"></i>Ver categoría
                                    </a>
                                    <a href="../categories/edit.php?id=<?php echo $categoryId; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit me-1"></i>Editar
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box">
                                    <span class="info-box-icon bg-primary"><i class="fas fa-eye"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Vistas en el período</span>
                                        <span class="info-box-number"><?php echo number_format($viewsTotal['total'] ?? 0); ?></span>
                                    </div>
                                </div>
                                <div class="info-box">
                                    <span class="info-box-icon bg-success"><i class="fas fa-newspaper"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Noticias totales</span>
                                        <span class="info-box-number"><?php echo number_format($totalNews['total'] ?? 0); ?></span>
                                    </div>
                                </div>
                                <div class="info-box">
                                    <span class="info-box-icon bg-info"><i class="fas fa-plus-circle"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Noticias nuevas en el período</span>
                                        <span class="info-box-number"><?php echo number_format($newsInPeriod['total'] ?? 0); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Gráfico de vistas diarias -->
                    <div class="col-lg-8 col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Vistas diarias</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="viewsChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico de dispositivos -->
                    <div class="col-lg-4 col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Dispositivos</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="devicesChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Noticias más vistas -->
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Noticias más vistas</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Título</th>
                                                <th class="text-center">Vistas en el período</th>
                                                <th class="text-center">Vistas totales</th>
                                                <th class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topNews as $news): ?>
                                                <tr>
                                                    <td><?php echo $news['title']; ?></td>
                                                    <td class="text-center"><?php echo number_format($news['view_count']); ?></td>
                                                    <td class="text-center"><?php echo number_format($news['views']); ?></td>
                                                    <td class="text-center">
                                                        <a href="news.php?id=<?php echo $news['id']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-info" title="Ver estadísticas">
                                                            <i class="fas fa-chart-line"></i>
                                                        </a>
                                                        <a href="../../news.php?slug=<?php echo $news['slug']; ?>" target="_blank" class="btn btn-sm btn-success" title="Ver noticia">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="../news/edit.php?id=<?php echo $news['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($topNews)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No hay datos disponibles</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Comparativa con otras categorías -->
                    <div class="col-lg-8 col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Comparativa con otras categorías</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="categoriesChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Noticias recientes -->
                    <div class="col-lg-4 col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Noticias recientes</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group">
                                    <?php foreach ($recentNews as $news): ?>
                                        <a href="news.php?id=<?php echo $news['id']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo $news['title']; ?></h6>
                                                <small><?php echo formatDate($news['published_at'], 'd/m/Y'); ?></small>
                                            </div>
                                            <small>
                                                <span class="text-muted me-3">Por: <?php echo $news['author_name']; ?></span>
                                                <span class="text-muted"><i class="fas fa-eye me-1"></i><?php echo number_format($news['views']); ?> vistas</span>
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($recentNews)): ?>
                                        <div class="list-group-item text-center">No hay noticias recientes</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de acción -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <a href="categories.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-list me-1"></i>Lista de categorías
                                </a>
                                <a href="export.php?type=category&id=<?php echo $categoryId; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-primary me-2">
                                    <i class="fas fa-file-export me-1"></i>Exportar datos
                                </a>
                                <a href="../../category.php?slug=<?php echo $category['slug']; ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-external-link-alt me-1"></i>Ver categoría
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            
            <?php else: ?>
                <!-- Listado de categorías -->
                
                <div class="row">
                    <!-- Gráfico de distribución por categoría -->
                    <div class="col-lg-6 col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Distribución de vistas por categoría</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="pieChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico de vistas diarias totales -->
                    <div class="col-lg-6 col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Vistas diarias totales</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="lineChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Categorías con mayor crecimiento -->
                    <div class="col-lg-6 col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Categorías con mayor crecimiento</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Categoría</th>
                                                <th class="text-center">Período anterior</th>
                                                <th class="text-center">Período actual</th>
                                                <th class="text-center">Crecimiento</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($growthCategories as $cat): ?>
                                                <tr>
                                                    <td>
                                                        <a href="?id=<?php echo $cat['id']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                                                            <span class="badge rounded-pill" style="background-color: <?php echo $cat['color'] ?: '#2196F3'; ?>; width: 12px; height: 12px; display: inline-block; margin-right: 5px;"></span>
                                                            <?php echo $cat['name']; ?>
                                                        </a>
                                                    </td>
                                                    <td class="text-center"><?php echo number_format($cat['previous_views']); ?></td>
                                                    <td class="text-center"><?php echo number_format($cat['current_views']); ?></td>
                                                    <td class="text-center">
                                                        <?php 
                                                        $growthClass = $cat['growth'] > 0 ? 'text-success' : ($cat['growth'] < 0 ? 'text-danger' : 'text-muted');
                                                        $growthIcon = $cat['growth'] > 0 ? 'fa-arrow-up' : ($cat['growth'] < 0 ? 'fa-arrow-down' : 'fa-minus');
                                                        ?>
                                                        <span class="<?php echo $growthClass; ?>">
                                                            <i class="fas <?php echo $growthIcon; ?> me-1"></i>
                                                            <?php echo number_format(abs($cat['growth']), 1); ?>%
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($growthCategories)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No hay datos disponibles</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico de barras comparativo -->
                    <div class="col-lg-6 col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Comparativa de vistas por categoría</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="barChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de categorías -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Todas las categorías</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Categoría</th>
                                        <th class="text-center">Noticias</th>
                                        <th class="text-center">Vistas en el período</th>
                                        <th class="text-center">Vistas totales</th>
                                        <th class="text-center">Porcentaje</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Calcular total de vistas en el período
                                    $totalPeriodViews = 0;
                                    foreach ($categories as $cat) {
                                        $totalPeriodViews += (int)$cat['period_views'];
                                    }
                                    
                                    foreach ($categories as $cat): 
                                        $percentage = $totalPeriodViews > 0 ? round(($cat['period_views'] / $totalPeriodViews) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="badge rounded-pill" style="background-color: <?php echo $cat['color'] ?: '#2196F3'; ?>; width: 12px; height: 12px; display: inline-block; margin-right: 5px;"></span>
                                                <?php echo $cat['name']; ?>
                                            </td>
                                            <td class="text-center"><?php echo number_format($cat['news_count']); ?></td>
                                            <td class="text-center"><?php echo number_format($cat['period_views']); ?></td>
                                            <td class="text-center"><?php echo number_format($cat['total_views']); ?></td>
                                            <td class="text-center"><?php echo $percentage; ?>%</td>
                                            <td class="text-center">
                                                <a href="?id=<?php echo $cat['id']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-info" title="Ver estadísticas">
                                                    <i class="fas fa-chart-line"></i>
                                                </a>
                                                <a href="../../category.php?slug=<?php echo $cat['slug']; ?>" target="_blank" class="btn btn-sm btn-success" title="Ver categoría">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="../categories/edit.php?id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($categories)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No hay categorías disponibles</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de acción -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <a href="index.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-chart-bar me-1"></i>Panel general
                                </a>
                                <a href="news.php" class="btn btn-info me-2">
                                    <i class="fas fa-newspaper me-1"></i>Estadísticas por noticia
                                </a>
                                <a href="export.php?type=categories&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-primary">
                                    <i class="fas fa-file-export me-1"></i>Exportar estadísticas
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- ChartJS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
    // Configuración común para los gráficos
    Chart.defaults.font.family = 'Roboto, "Segoe UI", Arial, sans-serif';
    Chart.defaults.color = '#6c757d';
    
    // Paleta de colores
    const colors = {
        primary: '#2196F3',
        success: '#4CAF50',
        info: '#00BCD4',
        warning: '#FFC107',
        danger: '#F44336',
        secondary: '#6c757d',
        chartColors: [
            '#2196F3', '#4CAF50', '#FFC107', '#F44336', 
            '#9C27B0', '#00BCD4', '#673AB7', '#FF9800', 
            '#795548', '#607D8B', '#3F51B5', '#E91E63'
        ]
    };
    
    <?php if ($categoryId > 0): ?>
        // Gráfico de vistas diarias para una categoría
        const viewsCtx = document.getElementById('viewsChart').getContext('2d');
        const viewsChart = new Chart(viewsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dayLabels); ?>,
                datasets: [{
                    label: 'Vistas diarias',
                    data: <?php echo json_encode(array_values($dayData)); ?>,
                    backgroundColor: 'rgba(33, 150, 243, 0.2)',
                    borderColor: '<?php echo $category['color'] ?: colors.primary; ?>',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '<?php echo $category['color'] ?: colors.primary; ?>'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Gráfico de dispositivos
        const devicesCtx = document.getElementById('devicesChart').getContext('2d');
        const devicesChart = new Chart(devicesCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($deviceLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($deviceData); ?>,
                    backgroundColor: colors.chartColors.slice(0, <?php echo count($deviceLabels); ?>),
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Gráfico de comparativa de categorías
        const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
        const categoriesChart = new Chart(categoriesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($categoryLabels); ?>,
                datasets: [{
                    label: 'Vistas',
                    data: <?php echo json_encode($categoryData); ?>,
                    backgroundColor: <?php echo json_encode($categoryColors); ?>,
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Vistas: ${context.raw.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    <?php else: ?>
        // Gráfico de distribución por categoría (tarta)
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        const pieChart = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categoryLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($categoryData); ?>,
                    backgroundColor: <?php echo json_encode($categoryColors); ?>,
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '50%'
            }
        });
        
        // Gráfico de vistas diarias
        const lineCtx = document.getElementById('lineChart').getContext('2d');
        const lineChart = new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dayLabels); ?>,
                datasets: [{
                    label: 'Vistas diarias',
                    data: <?php echo json_encode($dayData); ?>,
                    backgroundColor: 'rgba(33, 150, 243, 0.2)',
                    borderColor: colors.primary,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: colors.primary
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Comparativa de vistas por categoría (barras)
        const barCtx = document.getElementById('barChart').getContext('2d');
        const barChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($categoryLabels); ?>,
                datasets: [{
                    label: 'Vistas',
                    data: <?php echo json_encode($categoryData); ?>,
                    backgroundColor: <?php echo json_encode($categoryColors); ?>,
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Vistas: ${context.raw.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    <?php endif; ?>
</script>