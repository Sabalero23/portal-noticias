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
$pageTitle = 'Estadísticas - Panel de Administración';
$currentMenu = 'statistics';

// Incluir cabecera
include_once ADMIN_PATH . '/includes/header.php';
include_once ADMIN_PATH . '/includes/sidebar.php';

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

// Obtener estadísticas generales
$db = Database::getInstance();

// Total de vistas en el período
$viewsTotal = $db->fetch(
    "SELECT COUNT(*) as total 
     FROM view_logs 
     WHERE viewed_at BETWEEN ? AND ?",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Promedio de vistas diarias
$dateInterval = max(1, ceil((strtotime($end_date) - strtotime($start_date)) / 86400));
$viewsAverage = ($viewsTotal && isset($viewsTotal['total'])) ? round($viewsTotal['total'] / $dateInterval, 2) : 0;

// Día con más vistas
$topDay = $db->fetch(
    "SELECT DATE(viewed_at) as date, COUNT(*) as count 
     FROM view_logs 
     WHERE viewed_at BETWEEN ? AND ? 
     GROUP BY DATE(viewed_at) 
     ORDER BY count DESC 
     LIMIT 1",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Noticias publicadas en el período
$newsPublished = $db->fetch(
    "SELECT COUNT(*) as total 
     FROM news 
     WHERE status = 'published' 
     AND published_at BETWEEN ? AND ?",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Comentarios en el período
$commentsAdded = $db->fetch(
    "SELECT COUNT(*) as total 
     FROM comments 
     WHERE created_at BETWEEN ? AND ?",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Usuarios nuevos en el período
$newUsers = $db->fetch(
    "SELECT COUNT(*) as total 
     FROM users 
     WHERE created_at BETWEEN ? AND ?",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Suscriptores nuevos en el período
$newSubscribers = $db->fetch(
    "SELECT COUNT(*) as total 
     FROM subscribers 
     WHERE created_at BETWEEN ? AND ?",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Datos para gráfico de vistas diarias
$viewsByDay = $db->fetchAll(
    "SELECT DATE(viewed_at) as date, COUNT(*) as count 
     FROM view_logs 
     WHERE viewed_at BETWEEN ? AND ? 
     GROUP BY DATE(viewed_at) 
     ORDER BY date ASC",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Datos para gráfico de vistas por hora del día
$viewsByHour = $db->fetchAll(
    "SELECT HOUR(viewed_at) as hour, COUNT(*) as count 
     FROM view_logs 
     WHERE viewed_at BETWEEN ? AND ? 
     GROUP BY HOUR(viewed_at) 
     ORDER BY hour ASC",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Preparar datos para gráfico de vistas diarias
$dayLabels = [];
$dayData = [];

foreach ($viewsByDay as $day) {
    $dayLabels[] = date('d M', strtotime($day['date']));
    $dayData[] = (int)$day['count'];
}

// Preparar datos para gráfico de vistas por hora
$hourLabels = [];
$hourData = [];

for ($i = 0; $i < 24; $i++) {
    $hourLabels[] = sprintf('%02d:00', $i);
    $hourData[] = 0;
}

foreach ($viewsByHour as $hour) {
    $hourData[(int)$hour['hour']] = (int)$hour['count'];
}

// Top 5 noticias más vistas en el período
$topNews = $db->fetchAll(
    "SELECT n.id, n.title, n.slug, COUNT(vl.id) as view_count 
     FROM news n 
     JOIN view_logs vl ON n.id = vl.news_id 
     WHERE vl.viewed_at BETWEEN ? AND ? 
     GROUP BY n.id 
     ORDER BY view_count DESC 
     LIMIT 5",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Datos para gráfico de categorías más vistas
$topCategories = $db->fetchAll(
    "SELECT c.name, COUNT(vl.id) as view_count 
     FROM categories c 
     JOIN news n ON c.id = n.category_id 
     JOIN view_logs vl ON n.id = vl.news_id 
     WHERE vl.viewed_at BETWEEN ? AND ? 
     GROUP BY c.id 
     ORDER BY view_count DESC 
     LIMIT 6",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Preparar datos para gráfico de categorías
$categoryLabels = [];
$categoryData = [];

foreach ($topCategories as $category) {
    $categoryLabels[] = $category['name'];
    $categoryData[] = (int)$category['view_count'];
}

// Dispositivos más usados (basado en user_agent)
$devices = $db->fetchAll(
    "SELECT 
        CASE 
            WHEN user_agent LIKE '%Android%' THEN 'Android' 
            WHEN user_agent LIKE '%iPhone%' THEN 'iPhone' 
            WHEN user_agent LIKE '%iPad%' THEN 'iPad' 
            WHEN user_agent LIKE '%Windows%' THEN 'Windows' 
            WHEN user_agent LIKE '%Macintosh%' THEN 'Mac' 
            WHEN user_agent LIKE '%Linux%' THEN 'Linux' 
            ELSE 'Otro' 
        END as device, 
        COUNT(*) as count 
     FROM view_logs 
     WHERE viewed_at BETWEEN ? AND ? 
     GROUP BY device 
     ORDER BY count DESC",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Preparar datos para gráfico de dispositivos
$deviceLabels = [];
$deviceData = [];

foreach ($devices as $device) {
    $deviceLabels[] = $device['device'];
    $deviceData[] = (int)$device['count'];
}

// Navegadores más usados
$browsers = $db->fetchAll(
    "SELECT 
        CASE 
            WHEN user_agent LIKE '%Chrome%' AND user_agent NOT LIKE '%Edg%' THEN 'Chrome' 
            WHEN user_agent LIKE '%Firefox%' THEN 'Firefox' 
            WHEN user_agent LIKE '%Safari%' AND user_agent NOT LIKE '%Chrome%' THEN 'Safari' 
            WHEN user_agent LIKE '%Edg%' THEN 'Edge' 
            WHEN user_agent LIKE '%MSIE%' OR user_agent LIKE '%Trident%' THEN 'Internet Explorer' 
            WHEN user_agent LIKE '%Opera%' OR user_agent LIKE '%OPR%' THEN 'Opera' 
            ELSE 'Otro' 
        END as browser, 
        COUNT(*) as count 
     FROM view_logs 
     WHERE viewed_at BETWEEN ? AND ? 
     GROUP BY browser 
     ORDER BY count DESC",
    [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
);

// Preparar datos para gráfico de navegadores
$browserLabels = [];
$browserData = [];

foreach ($browsers as $browser) {
    $browserLabels[] = $browser['browser'];
    $browserData[] = (int)$browser['count'];
}
?>

<!-- Contenido principal -->
<div class="content-wrapper">
    <!-- Cabecera de página -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Estadísticas Generales</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Estadísticas</li>
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
                    <form method="get" action="index.php" class="row g-3 align-items-end">
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
                        <a href="?start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary">Últimos 7 días</a>
                        <a href="?start_date=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary">Últimos 30 días</a>
                        <a href="?start_date=<?php echo date('Y-m-d', strtotime('first day of this month')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-secondary">Este mes</a>
                        <a href="?start_date=<?php echo date('Y-m-d', strtotime('first day of last month')); ?>&end_date=<?php echo date('Y-m-d', strtotime('last day of last month')); ?>" class="btn btn-outline-secondary">Mes anterior</a>
                    </div>
                </div>
            </div>
            
            <!-- Tarjetas de resumen -->
            <div class="row">
                <!-- Total de vistas -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-eye"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total de vistas</span>
                            <span class="info-box-number"><?php echo number_format($viewsTotal['total'] ?? 0); ?></span>
                            <span class="info-box-text">Media: <?php echo number_format($viewsAverage, 2); ?> / día</span>
                        </div>
                    </div>
                </div>
                
                <!-- Noticias publicadas -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-newspaper"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Noticias publicadas</span>
                            <span class="info-box-number"><?php echo number_format($newsPublished['total'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Comentarios -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-comments"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Comentarios</span>
                            <span class="info-box-number"><?php echo number_format($commentsAdded['total'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Suscriptores nuevos -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger"><i class="fas fa-envelope"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Nuevos suscriptores</span>
                            <span class="info-box-number"><?php echo number_format($newSubscribers['total'] ?? 0); ?></span>
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
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-bs-toggle="collapse" data-bs-target="#collapseViews">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse show" id="collapseViews">
                            <div class="card-body">
                                <canvas id="viewsChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico de categorías más vistas -->
                <div class="col-lg-4 col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Categorías más vistas</h5>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-bs-toggle="collapse" data-bs-target="#collapseCategories">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse show" id="collapseCategories">
                            <div class="card-body">
                                <canvas id="categoriesChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Noticias más vistas -->
                <div class="col-lg-8 col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Noticias más vistas</h5>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-bs-toggle="collapse" data-bs-target="#collapseTopNews">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse show" id="collapseTopNews">
                            <div class="card-body table-responsive p-0">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Título</th>
                                            <th class="text-center">Vistas</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topNews as $news): ?>
                                            <tr>
                                                <td><?php echo $news['title']; ?></td>
                                                <td class="text-center"><?php echo number_format($news['view_count']); ?></td>
                                                <td class="text-center">
                                                    <a href="../../news.php?slug=<?php echo $news['slug']; ?>" target="_blank" class="btn btn-sm btn-info" title="Ver">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="../news/edit.php?id=<?php echo $news['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="news.php?id=<?php echo $news['id']; ?>" class="btn btn-sm btn-success" title="Estadísticas">
                                                        <i class="fas fa-chart-line"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($topNews)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center">No hay datos disponibles</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer text-center">
                                <a href="news.php" class="text-primary">Ver estadísticas detalladas por noticia</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico de vistas por hora -->
                <div class="col-lg-4 col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Vistas por hora del día</h5>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-bs-toggle="collapse" data-bs-target="#collapseHours">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse show" id="collapseHours">
                            <div class="card-body">
                                <canvas id="hoursChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Dispositivos -->
                <div class="col-lg-6 col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Dispositivos</h5>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-bs-toggle="collapse" data-bs-target="#collapseDevices">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse show" id="collapseDevices">
                            <div class="card-body">
                                <canvas id="devicesChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Navegadores -->
                <div class="col-lg-6 col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Navegadores</h5>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-bs-toggle="collapse" data-bs-target="#collapseBrowsers">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse show" id="collapseBrowsers">
                            <div class="card-body">
                                <canvas id="browsersChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Botones de acciones -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center">
                            <a href="news.php" class="btn btn-primary mx-2">
                                <i class="fas fa-newspaper me-2"></i>Estadísticas por noticia
                            </a>
                            <a href="categories.php" class="btn btn-success mx-2">
                                <i class="fas fa-folder me-2"></i>Estadísticas por categoría
                            </a>
                            <a href="export.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-info mx-2">
                                <i class="fas fa-file-export me-2"></i>Exportar estadísticas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
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
    
    // Gráfico de vistas diarias
    const viewsCtx = document.getElementById('viewsChart').getContext('2d');
    const viewsChart = new Chart(viewsCtx, {
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
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    padding: 10,
                    cornerRadius: 4,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
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
    
    // Gráfico de categorías
    const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
    const categoriesChart = new Chart(categoriesCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($categoryLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($categoryData); ?>,
                backgroundColor: colors.chartColors,
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    padding: 10,
                    cornerRadius: 4,
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
            },
            cutout: '60%'
        }
    });
    
    // Gráfico de vistas por hora
    const hoursCtx = document.getElementById('hoursChart').getContext('2d');
    const hoursChart = new Chart(hoursCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($hourLabels); ?>,
            datasets: [{
                label: 'Vistas por hora',
                data: <?php echo json_encode($hourData); ?>,
                backgroundColor: 'rgba(0, 188, 212, 0.6)',
                borderColor: colors.info,
                borderWidth: 1
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
                    grid: {
                        drawBorder: false,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
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
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    padding: 10,
                    cornerRadius: 4,
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
    
    // Gráfico de navegadores
    const browsersCtx = document.getElementById('browsersChart').getContext('2d');
    const browsersChart = new Chart(browsersCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($browserLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($browserData); ?>,
                backgroundColor: colors.chartColors.slice(0, <?php echo count($browserLabels); ?>),
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
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    padding: 10,
                    cornerRadius: 4,
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
</script>