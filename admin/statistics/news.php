<?php
// Definir ruta base
define('BASE_PATH', dirname(dirname(__DIR__)));
define('ADMIN_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once ADMIN_PATH . '/includes/functions.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor'], '../../index.php');

// Verificar ID de noticia
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de noticia inválido');
    redirect('../news/index.php');
    exit;
}

$newsId = intval($_GET['id']);
$db = Database::getInstance();

// Obtener datos básicos de la noticia
$news = $db->fetch(
    "SELECT n.*, 
     c.name as category_name,
     u.name as author_name 
     FROM news n
     LEFT JOIN categories c ON n.category_id = c.id
     LEFT JOIN users u ON n.author_id = u.id
     WHERE n.id = ?",
    [$newsId]
);

// Verificar si existe la noticia
if (!$news) {
    setFlashMessage('error', 'Noticia no encontrada');
    redirect('../news/index.php');
    exit;
}

// Título de la página
$pageTitle = 'Estadísticas de Noticia - Panel de Administración';
$currentMenu = 'news';

// Obtener estadísticas
// 1. Total de vistas
$totalViews = $news['views'];

// 2. Vistas por día (últimos 30 días)
$viewsByDay = $db->fetchAll(
    "SELECT 
        DATE(viewed_at) as date, 
        COUNT(*) as count 
     FROM view_logs 
     WHERE news_id = ? 
     AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
     GROUP BY DATE(viewed_at) 
     ORDER BY date ASC",
    [$newsId]
);

// 3. Vistas por hora del día
$viewsByHour = $db->fetchAll(
    "SELECT 
        HOUR(viewed_at) as hour, 
        COUNT(*) as count 
     FROM view_logs 
     WHERE news_id = ? 
     GROUP BY HOUR(viewed_at) 
     ORDER BY hour ASC",
    [$newsId]
);

// 4. Información de comentarios
$commentsData = $db->fetch(
    "SELECT 
        COUNT(*) as total_comments,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_comments,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_comments,
        SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) as spam_comments,
        SUM(CASE WHEN status = 'trash' THEN 1 ELSE 0 END) as trash_comments
     FROM comments 
     WHERE news_id = ?",
    [$newsId]
);

// 5. Dispositivos más comunes (solo si existe la información de user_agent)
$deviceStats = [];
try {
    $deviceStats = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN user_agent LIKE '%Android%' THEN 'Android'
                WHEN user_agent LIKE '%iPhone%' THEN 'iPhone'
                WHEN user_agent LIKE '%iPad%' THEN 'iPad'
                WHEN user_agent LIKE '%Windows%' THEN 'Windows'
                WHEN user_agent LIKE '%Macintosh%' THEN 'Mac'
                WHEN user_agent LIKE '%Linux%' THEN 'Linux'
                ELSE 'Otros'
            END AS device,
            COUNT(*) as count
         FROM view_logs
         WHERE news_id = ? AND user_agent IS NOT NULL
         GROUP BY device
         ORDER BY count DESC",
        [$newsId]
    );
} catch (Exception $e) {
    // Puede que user_agent no exista, simplemente continuamos sin esta estadística
    $deviceStats = [];
}

// Formatear datos para gráficos en JavaScript
$dates = [];
$counts = [];

foreach ($viewsByDay as $view) {
    $dates[] = $view['date'];
    $counts[] = $view['count'];
}

$hourLabels = [];
$hourCounts = [];
for ($i = 0; $i < 24; $i++) {
    $hourLabels[] = sprintf('%02d:00', $i);
    $hourCounts[] = 0;
}

foreach ($viewsByHour as $view) {
    $hourCounts[$view['hour']] = $view['count'];
}

// Incluir cabecera
include_once ADMIN_PATH . '/includes/header.php';
include_once ADMIN_PATH . '/includes/sidebar.php';
?>

<!-- Estilos adicionales -->
<style>
.stats-card {
    transition: all 0.3s;
}
.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}
</style>

<!-- Contenido principal -->
<div class="content-wrapper">
    <!-- Cabecera de página -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Estadísticas de Noticia</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="../news/index.php">Noticias</a></li>
                        <li class="breadcrumb-item active">Estadísticas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Información básica de la noticia -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="card-title mb-2"><?php echo htmlspecialchars($news['title']); ?></h4>
                            <div class="text-muted">
                                <span><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($news['author_name']); ?></span>
                                <span class="mx-2">|</span>
                                <span><i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($news['category_name']); ?></span>
                                
                                <?php if ($news['published_at']): ?>
                                    <span class="mx-2">|</span>
                                    <span><i class="fas fa-calendar me-1"></i> Publicada: <?php echo formatDate($news['published_at'], 'd/m/Y H:i'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="../news/edit.php?id=<?php echo $newsId; ?>" class="btn btn-primary me-2">
                                <i class="fas fa-edit me-1"></i> Editar
                            </a>
                            <?php if ($news['status'] === 'published'): ?>
                                <a href="<?php echo SITE_URL . '/news.php?slug=' . $news['slug']; ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-eye me-1"></i> Ver
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resumen de estadísticas -->
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="card stats-card bg-primary text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title mb-0">Total de Vistas</h5>
                                    <h2 class="mt-2 mb-0"><?php echo number_format($totalViews); ?></h2>
                                </div>
                                <div>
                                    <i class="fas fa-eye fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stats-card bg-success text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title mb-0">Comentarios</h5>
                                    <h2 class="mt-2 mb-0"><?php echo number_format($commentsData['total_comments'] ?? 0); ?></h2>
                                </div>
                                <div>
                                    <i class="fas fa-comments fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stats-card bg-info text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title mb-0">Tasa de Comentarios</h5>
                                    <h2 class="mt-2 mb-0">
                                        <?php 
                                        $commentRate = ($totalViews > 0) 
                                            ? round(($commentsData['total_comments'] ?? 0) / $totalViews * 100, 2)
                                            : 0;
                                        echo $commentRate . '%';
                                        ?>
                                    </h2>
                                </div>
                                <div>
                                    <i class="fas fa-chart-line fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stats-card bg-warning text-dark mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title mb-0">Estado</h5>
                                    <h2 class="mt-2 mb-0">
                                        <?php 
                                        switch ($news['status']) {
                                            case 'published':
                                                echo 'Publicada';
                                                break;
                                            case 'draft':
                                                echo 'Borrador';
                                                break;
                                            case 'pending':
                                                echo 'Pendiente';
                                                break;
                                            case 'trash':
                                                echo 'Papelera';
                                                break;
                                            default:
                                                echo $news['status'];
                                        }
                                        ?>
                                    </h2>
                                </div>
                                <div>
                                    <i class="fas fa-info-circle fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos -->
            <div class="row">
                <!-- Gráfico de vistas por día -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Vistas por Día (Últimos 30 días)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="viewsByDayChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico de vistas por hora del día -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Vistas por Hora del Día</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="viewsByHourChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Comentarios y Dispositivos -->
            <div class="row">
                <!-- Estadísticas de comentarios -->
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Detalle de Comentarios</h5>
                        </div>
                        <div class="card-body">
                            <?php if (($commentsData['total_comments'] ?? 0) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Estado</th>
                                                <th>Cantidad</th>
                                                <th>Porcentaje</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="table-success">
                                                <td>Aprobados</td>
                                                <td><?php echo number_format($commentsData['approved_comments'] ?? 0); ?></td>
                                                <td>
                                                    <?php 
                                                    echo round(($commentsData['approved_comments'] ?? 0) / $commentsData['total_comments'] * 100, 1);
                                                    ?>%
                                                </td>
                                            </tr>
                                            <tr class="table-warning">
                                                <td>Pendientes</td>
                                                <td><?php echo number_format($commentsData['pending_comments'] ?? 0); ?></td>
                                                <td>
                                                    <?php 
                                                    echo round(($commentsData['pending_comments'] ?? 0) / $commentsData['total_comments'] * 100, 1);
                                                    ?>%
                                                </td>
                                            </tr>
                                            <tr class="table-danger">
                                                <td>Spam / Papelera</td>
                                                <td>
                                                    <?php 
                                                    echo number_format(
                                                        ($commentsData['spam_comments'] ?? 0) + 
                                                        ($commentsData['trash_comments'] ?? 0)
                                                    ); 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    echo round(
                                                        (($commentsData['spam_comments'] ?? 0) + 
                                                        ($commentsData['trash_comments'] ?? 0)) / 
                                                        $commentsData['total_comments'] * 100, 1
                                                    );
                                                    ?>%
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-dark">
                                                <th>Total</th>
                                                <th><?php echo number_format($commentsData['total_comments'] ?? 0); ?></th>
                                                <th>100%</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="../comments/index.php?news_id=<?php echo $newsId; ?>" class="btn btn-primary">
                                        <i class="fas fa-comments me-1"></i> Ver todos los comentarios
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i> Esta noticia no tiene comentarios.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Estadísticas de dispositivos -->
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Dispositivos Utilizados</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($deviceStats)): ?>
                                <div class="chart-container">
                                    <canvas id="deviceChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i> No hay datos disponibles sobre dispositivos.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incluir Chart.js para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Datos para el gráfico de vistas por día
    const viewsByDayChart = new Chart(
        document.getElementById('viewsByDayChart').getContext('2d'),
        {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Vistas',
                    data: <?php echo json_encode($counts); ?>,
                    fill: false,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Tendencia de vistas en los últimos 30 días'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Número de vistas'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Fecha'
                        }
                    }
                }
            }
        }
    );
    
    // Datos para el gráfico de vistas por hora del día
    const viewsByHourChart = new Chart(
        document.getElementById('viewsByHourChart').getContext('2d'),
        {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($hourLabels); ?>,
                datasets: [{
                    label: 'Vistas',
                    data: <?php echo json_encode($hourCounts); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribución de vistas por hora'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Número de vistas'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Hora del día'
                        }
                    }
                }
            }
        }
    );
    
    <?php if (!empty($deviceStats)): ?>
    // Datos para el gráfico de dispositivos
    const deviceChart = new Chart(
        document.getElementById('deviceChart').getContext('2d'),
        {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($deviceStats, 'device')); ?>,
                datasets: [{
                    label: 'Dispositivos',
                    data: <?php echo json_encode(array_column($deviceStats, 'count')); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)',
                        'rgba(255, 159, 64, 0.5)',
                        'rgba(199, 199, 199, 0.5)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(199, 199, 199, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribución por dispositivo'
                    },
                    legend: {
                        position: 'right',
                    }
                }
            }
        }
    );
    <?php endif; ?>
});
</script>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>