<?php
// Definir ruta base
define('BASE_PATH', dirname(__DIR__));
define('ADMIN_PATH', __DIR__);

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor', 'author'], 'index.php');

// Título de la página
$pageTitle = 'Dashboard - Panel de Administración';
$currentMenu = 'dashboard';

// Incluir cabecera
include_once 'includes/header.php';
include_once 'includes/sidebar.php';

// Obtener estadísticas
$db = Database::getInstance();

// Total de noticias publicadas
$totalPublishedNews = $db->fetch(
    "SELECT COUNT(*) as total FROM news WHERE status = 'published'"
)['total'];

// Total de noticias pendientes
$totalPendingNews = $db->fetch(
    "SELECT COUNT(*) as total FROM news WHERE status = 'pending'"
)['total'];

// Total de noticias en borrador
$totalDraftNews = $db->fetch(
    "SELECT COUNT(*) as total FROM news WHERE status = 'draft'"
)['total'];

// Total de comentarios
$totalComments = $db->fetch(
    "SELECT COUNT(*) as total FROM comments"
)['total'];

// Comentarios pendientes de moderación
$pendingComments = $db->fetch(
    "SELECT COUNT(*) as total FROM comments WHERE status = 'pending'"
)['total'];

// Total de usuarios
$totalUsers = $db->fetch(
    "SELECT COUNT(*) as total FROM users"
)['total'];

// Total de suscriptores
$totalSubscribers = $db->fetch(
    "SELECT COUNT(*) as total FROM subscribers WHERE status = 'active'"
)['total'];

// Total de vistas (últimos 30 días)
$totalViews = $db->fetch(
    "SELECT SUM(views) as total FROM news"
)['total'];

// Noticias más vistas
$popularNews = $db->fetchAll(
    "SELECT id, title, slug, views 
     FROM news 
     WHERE status = 'published' 
     ORDER BY views DESC 
     LIMIT 5"
);

// Comentarios recientes
$recentComments = $db->fetchAll(
    "SELECT c.id, c.name, c.comment, c.created_at, c.status, n.title as news_title, n.slug as news_slug 
     FROM comments c
     JOIN news n ON c.news_id = n.id
     ORDER BY c.created_at DESC
     LIMIT 5"
);

// Nuevos suscriptores
$recentSubscribers = $db->fetchAll(
    "SELECT id, email, name, created_at 
     FROM subscribers 
     ORDER BY created_at DESC 
     LIMIT 5"
);

// Noticias recientes
$recentNews = $db->fetchAll(
    "SELECT n.id, n.title, n.slug, n.status, n.created_at, u.name as author_name, c.name as category_name
     FROM news n
     JOIN users u ON n.author_id = u.id
     JOIN categories c ON n.category_id = c.id
     ORDER BY n.created_at DESC
     LIMIT 5"
);

// Obtener categorías para el gráfico
$categories = $db->fetchAll(
    "SELECT c.name, COUNT(n.id) as count
     FROM categories c
     LEFT JOIN news n ON c.id = n.category_id AND n.status = 'published'
     GROUP BY c.id
     ORDER BY count DESC
     LIMIT 6"
);

// Preparar datos para gráfico de categorías
$categoryNames = [];
$categoryCounts = [];

foreach ($categories as $category) {
    $categoryNames[] = $category['name'];
    $categoryCounts[] = (int)$category['count'];
}

// Obtener datos para gráfico de vistas por día (últimos 7 días)
$viewsByDay = $db->fetchAll(
    "SELECT DATE(viewed_at) as date, COUNT(*) as count
     FROM view_logs
     WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(viewed_at)
     ORDER BY date ASC"
);

// Preparar datos para gráfico de vistas
$viewDates = [];
$viewCounts = [];

// Rellenar con 0 si no hay datos para algún día
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $viewDates[] = date('d M', strtotime($date));
    
    $found = false;
    foreach ($viewsByDay as $view) {
        if ($view['date'] === $date) {
            $viewCounts[] = (int)$view['count'];
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $viewCounts[] = 0;
    }
}
?>

<!-- Contenido principal -->
<div class="content-wrapper">
    <!-- Cabecera de página -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Dashboard</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Tarjetas de estadísticas -->
            <div class="row">
                <!-- Noticias publicadas -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary"><i class="fas fa-newspaper"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Noticias publicadas</span>
                            <span class="info-box-number"><?php echo number_format($totalPublishedNews); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Comentarios -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-comments"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Comentarios</span>
                            <span class="info-box-number"><?php echo number_format($totalComments); ?></span>
                            <?php if ($pendingComments > 0): ?>
                                <span class="info-box-text text-warning"><?php echo number_format($pendingComments); ?> pendientes</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Usuarios -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-users"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Usuarios</span>
                            <span class="info-box-number"><?php echo number_format($totalUsers); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Suscriptores -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-envelope"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Suscriptores</span>
                            <span class="info-box-number"><?php echo number_format($totalSubscribers); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Estado de noticias -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Publicadas</span>
                            <span class="info-box-number"><?php echo number_format($totalPublishedNews); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Noticias pendientes -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Pendientes</span>
                            <span class="info-box-number"><?php echo number_format($totalPendingNews); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Borradores -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-secondary"><i class="fas fa-pencil-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Borradores</span>
                            <span class="info-box-number"><?php echo number_format($totalDraftNews); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Total de vistas -->
                <div class="col-lg-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger"><i class="fas fa-eye"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total de vistas</span>
                            <span class="info-box-number"><?php echo number_format((int)$totalViews); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Gráfico de vistas diarias -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Vistas en los últimos 7 días</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="viewsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico de noticias por categoría -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Noticias por categoría</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="categoriesChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Noticias más vistas -->
                <div class="col-lg-6">
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
                                            <th class="text-end">Vistas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($popularNews as $news): ?>
                                            <tr>
                                                <td>
                                                    <a href="../news.php?slug=<?php echo $news['slug']; ?>" target="_blank">
                                                        <?php echo $news['title']; ?>
                                                    </a>
                                                </td>
                                                <td class="text-end"><?php echo number_format($news['views']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($popularNews) === 0): ?>
                                            <tr>
                                                <td colspan="2" class="text-center">No hay datos disponibles</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-center">
                            <a href="statistics/news.php" class="text-primary">Ver todas las estadísticas</a>
                        </div>
                    </div>
                </div>
                
                <!-- Comentarios recientes -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Comentarios recientes</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Autor</th>
                                            <th>Comentario</th>
                                            <th>Noticia</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentComments as $comment): ?>
                                            <tr>
                                                <td><?php echo $comment['name']; ?></td>
                                                <td><?php echo truncateString($comment['comment'], 50); ?></td>
                                                <td>
                                                    <a href="../news.php?slug=<?php echo $comment['news_slug']; ?>#comments" target="_blank">
                                                        <?php echo truncateString($comment['news_title'], 20); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($comment['status'] === 'approved'): ?>
                                                        <span class="badge bg-success">Aprobado</span>
                                                    <?php elseif ($comment['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning">Pendiente</span>
                                                    <?php elseif ($comment['status'] === 'spam'): ?>
                                                        <span class="badge bg-danger">Spam</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Eliminado</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($recentComments) === 0): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No hay comentarios recientes</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-center">
                            <a href="comments/index.php" class="text-primary">Ver todos los comentarios</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Noticias recientes -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Noticias recientes</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Título</th>
                                            <th>Autor</th>
                                            <th>Categoría</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentNews as $news): ?>
                                            <tr>
                                                <td>
                                                    <a href="news/edit.php?id=<?php echo $news['id']; ?>">
                                                        <?php echo truncateString($news['title'], 30); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo $news['author_name']; ?></td>
                                                <td><?php echo $news['category_name']; ?></td>
                                                <td>
                                                    <?php if ($news['status'] === 'published'): ?>
                                                        <span class="badge bg-success">Publicada</span>
                                                    <?php elseif ($news['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning">Pendiente</span>
                                                    <?php elseif ($news['status'] === 'draft'): ?>
                                                        <span class="badge bg-secondary">Borrador</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Eliminada</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($recentNews) === 0): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No hay noticias recientes</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-center">
                            <a href="news/index.php" class="text-primary">Ver todas las noticias</a>
                        </div>
                    </div>
                </div>
                
                <!-- Nuevos suscriptores -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Suscriptores recientes</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Email</th>
                                            <th>Nombre</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentSubscribers as $subscriber): ?>
                                            <tr>
                                                <td><?php echo $subscriber['email']; ?></td>
                                                <td><?php echo !empty($subscriber['name']) ? $subscriber['name'] : '<em>Sin nombre</em>'; ?></td>
                                                <td><?php echo formatDate($subscriber['created_at'], 'd/m/Y H:i'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($recentSubscribers) === 0): ?>
                                            <tr>
                                                <td colspan="3" class="text-center">No hay suscriptores recientes</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-center">
                            <a href="subscribers/index.php" class="text-primary">Ver todos los suscriptores</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Enlaces rápidos -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Acciones rápidas</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-sm-6 col-md-3 mb-3">
                                    <a href="news/add.php" class="btn btn-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                        <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                        <span>Nueva noticia</span>
                                    </a>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-3">
                                    <a href="categories/add.php" class="btn btn-success w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                        <i class="fas fa-folder-plus fa-2x mb-2"></i>
                                        <span>Nueva categoría</span>
                                    </a>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-3">
                                    <a href="media/index.php" class="btn btn-info w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                        <i class="fas fa-images fa-2x mb-2"></i>
                                        <span>Gestionar medios</span>
                                    </a>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-3">
                                    <a href="comments/index.php" class="btn btn-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                        <i class="fas fa-comments fa-2x mb-2"></i>
                                        <span>Moderar comentarios</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<!-- ChartJS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
    // Gráfico de vistas diarias
    const viewsCtx = document.getElementById('viewsChart').getContext('2d');
    const viewsChart = new Chart(viewsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($viewDates); ?>,
            datasets: [{
                label: 'Vistas por día',
                data: <?php echo json_encode($viewCounts); ?>,
                backgroundColor: 'rgba(33, 150, 243, 0.2)',
                borderColor: 'rgba(33, 150, 243, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(33, 150, 243, 1)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(33, 150, 243, 1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });
    
    // Gráfico de noticias por categoría
    const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
    const categoriesChart = new Chart(categoriesCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($categoryNames); ?>,
            datasets: [{
                data: <?php echo json_encode($categoryCounts); ?>,
                backgroundColor: [
                    'rgba(33, 150, 243, 0.7)',  // Azul
                    'rgba(76, 175, 80, 0.7)',   // Verde
                    'rgba(255, 152, 0, 0.7)',   // Naranja
                    'rgba(156, 39, 176, 0.7)',  // Púrpura
                    'rgba(244, 67, 54, 0.7)',   // Rojo
                    'rgba(0, 188, 212, 0.7)'    // Cyan
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
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
</script>