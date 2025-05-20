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
$auth->requirePermission(['admin', 'editor'], ADMIN_PATH . '/dashboard.php');

// Comprobar ID de encuesta
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de encuesta inválido');
    redirect('index.php');
}

$pollId = (int)$_GET['id'];

// Obtener datos de la encuesta
$db = Database::getInstance();
$poll = $db->fetch(
    "SELECT id, question, status, start_date, end_date, created_at, updated_at
     FROM polls WHERE id = ?",
    [$pollId]
);

// Verificar si la encuesta existe
if (!$poll) {
    setFlashMessage('error', 'La encuesta no existe');
    redirect('index.php');
}

// Obtener opciones de la encuesta con votos
$options = $db->fetchAll(
    "SELECT id, option_text, votes FROM poll_options WHERE poll_id = ? ORDER BY votes DESC",
    [$pollId]
);

// Contar votos totales
$totalVotes = 0;
foreach ($options as $option) {
    $totalVotes += $option['votes'];
}

// Obtener datos de votos por fecha para gráfico
$votesByDate = $db->fetchAll(
    "SELECT DATE(created_at) as date, COUNT(*) as count 
     FROM poll_votes 
     WHERE poll_id = ? 
     GROUP BY DATE(created_at) 
     ORDER BY date DESC 
     LIMIT 30",
    [$pollId]
);

// Reordenar por fecha ascendente para el gráfico
$votesByDate = array_reverse($votesByDate);

// Datos para el gráfico
$chartDates = [];
$chartCounts = [];

foreach ($votesByDate as $vote) {
    $chartDates[] = formatDate($vote['date'], 'd/m/Y');
    $chartCounts[] = $vote['count'];
}

// Obtener datos demográficos si están disponibles (IP única)
$uniqueVoters = $db->fetch(
    "SELECT COUNT(DISTINCT ip_address) as count FROM poll_votes WHERE poll_id = ?",
    [$pollId]
);

// Votos por hora del día
$votesByHour = $db->fetchAll(
    "SELECT HOUR(created_at) as hour, COUNT(*) as count 
     FROM poll_votes 
     WHERE poll_id = ? 
     GROUP BY HOUR(created_at) 
     ORDER BY hour",
    [$pollId]
);

// Datos para el gráfico de horas
$hourLabels = [];
$hourCounts = [];

// Inicializar todas las horas con 0
for ($i = 0; $i < 24; $i++) {
    $hourLabels[$i] = sprintf('%02d:00', $i);
    $hourCounts[$i] = 0;
}

// Llenar con datos reales
foreach ($votesByHour as $vote) {
    $hourCounts[$vote['hour']] = $vote['count'];
}

// Título de la página
$pageTitle = 'Resultados de Encuesta - Panel de Administración';
$currentMenu = 'polls_results';

// Incluir cabecera
include_once ADMIN_PATH . '/includes/header.php';
include_once ADMIN_PATH . '/includes/sidebar.php';
?>

<!-- Contenido principal -->
<div class="content-wrapper">
    <!-- Cabecera de página -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Resultados de Encuesta</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Encuestas</a></li>
                        <li class="breadcrumb-item active">Resultados</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Información de la encuesta -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Información de la Encuesta</h3>
                    <div class="card-tools">
                        <a href="edit.php?id=<?php echo $poll['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th style="width: 30%">ID</th>
                                    <td><?php echo $poll['id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Pregunta</th>
                                    <td><?php echo htmlspecialchars($poll['question']); ?></td>
                                </tr>
                                <tr>
                                    <th>Estado</th>
                                    <td>
                                        <?php if ($poll['status'] === 'active'): ?>
                                            <span class="badge bg-success">Activa</span>
                                        <?php elseif ($poll['status'] === 'inactive'): ?>
                                            <span class="badge bg-secondary">Inactiva</span>
                                        <?php elseif ($poll['status'] === 'closed'): ?>
                                            <span class="badge bg-danger">Cerrada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th style="width: 30%">Fecha de inicio</th>
                                    <td><?php echo formatDate($poll['start_date']); ?></td>
                                </tr>
                                <tr>
                                    <th>Fecha de fin</th>
                                    <td><?php echo $poll['end_date'] ? formatDate($poll['end_date']) : 'Sin fecha límite'; ?></td>
                                </tr>
                                <tr>
                                    <th>Creada</th>
                                    <td><?php echo formatDate($poll['created_at'], 'd/m/Y H:i'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Métricas generales -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo number_format($totalVotes); ?></h3>
                            <p>Total de Votos</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-vote-yea"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo count($options); ?></h3>
                            <p>Opciones</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-list"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo $uniqueVoters ? number_format($uniqueVoters['count']) : 'N/A'; ?></h3>
                            <p>Votantes únicos</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo $totalVotes > 0 && count($options) > 0 ? number_format($totalVotes / count($options), 1) : '0'; ?></h3>
                            <p>Promedio por opción</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resultados principales -->
            <div class="row">
                <!-- Resultados en tabla -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Resultados</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Opción</th>
                                        <th>Votos</th>
                                        <th>Porcentaje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($options as $option): ?>
                                        <?php $percentage = $totalVotes > 0 ? round(($option['votes'] / $totalVotes) * 100, 1) : 0; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($option['option_text']); ?></td>
                                            <td><?php echo number_format($option['votes']); ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <small><?php echo $percentage; ?>%</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th><?php echo number_format($totalVotes); ?></th>
                                        <th>100%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico de resultados -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Gráfico de Resultados</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="resultsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos adicionales -->
            <div class="row">
                <!-- Votos por fecha -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Evolución de Votos</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($chartDates)): ?>
                                <canvas id="votesByDateChart" height="300"></canvas>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No hay suficientes datos para mostrar la evolución de votos.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Votos por hora del día -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Votos por Hora del Día</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($votesByHour)): ?>
                                <canvas id="votesByHourChart" height="300"></canvas>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No hay suficientes datos para mostrar votos por hora.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Acciones</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <a href="edit.php?id=<?php echo $poll['id']; ?>" class="btn btn-primary btn-block mb-3">
                                <i class="fas fa-edit me-1"></i> Editar Encuesta
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="#" class="btn btn-success btn-block mb-3" onclick="exportResults(); return false;">
                                <i class="fas fa-file-export me-1"></i> Exportar Resultados
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="index.php" class="btn btn-secondary btn-block mb-3">
                                <i class="fas fa-arrow-left me-1"></i> Volver al Listado
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Datos para gráficos
        const options = <?php 
            $chartOptions = [];
            foreach ($options as $option) {
                $chartOptions[] = [
                    'label' => $option['option_text'],
                    'votes' => $option['votes']
                ];
            }
            echo json_encode($chartOptions);
        ?>;
        
        const chartDates = <?php echo json_encode($chartDates); ?>;
        const chartCounts = <?php echo json_encode($chartCounts); ?>;
        const hourLabels = <?php echo json_encode(array_values($hourLabels)); ?>;
        const hourCounts = <?php echo json_encode(array_values($hourCounts)); ?>;
        
        // Colores para los gráficos
        const chartColors = [
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 99, 132, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(199, 199, 199, 0.8)',
            'rgba(83, 102, 255, 0.8)',
            'rgba(40, 159, 64, 0.8)',
            'rgba(210, 199, 199, 0.8)',
        ];
        
        // Gráfico de resultados
        if (options.length > 0) {
            const resultsChartCtx = document.getElementById('resultsChart').getContext('2d');
            new Chart(resultsChartCtx, {
                type: 'doughnut',
                data: {
                    labels: options.map(option => option.label),
                    datasets: [{
                        data: options.map(option => option.votes),
                        backgroundColor: chartColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((acc, current) => acc + current, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} votos (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Gráfico de evolución de votos
        if (chartDates.length > 0) {
            const votesByDateChartCtx = document.getElementById('votesByDateChart').getContext('2d');
            new Chart(votesByDateChartCtx, {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets: [{
                        label: 'Votos por día',
                        data: chartCounts,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                        pointRadius: 4,
                        tension: 0.3
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
                    }
                }
            });
        }
        
        // Gráfico de votos por hora
        if (hourCounts.some(count => count > 0)) {
            const votesByHourChartCtx = document.getElementById('votesByHourChart').getContext('2d');
            new Chart(votesByHourChartCtx, {
                type: 'bar',
                data: {
                    labels: hourLabels,
                    datasets: [{
                        label: 'Votos por hora',
                        data: hourCounts,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
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
                    }
                }
            });
        }
    });
    
    // Función para exportar resultados
    function exportResults() {
        // Crear un documento CSV
        const rows = [
            ['ID Encuesta', <?php echo $poll['id']; ?>],
            ['Pregunta', '<?php echo addslashes($poll['question']); ?>'],
            ['Total de votos', <?php echo $totalVotes; ?>],
            ['Estado', '<?php echo $poll['status']; ?>'],
            ['Fecha de inicio', '<?php echo $poll['start_date']; ?>'],
            ['Fecha de fin', '<?php echo $poll['end_date'] ?: "Sin fecha límite"; ?>'],
            ['Fecha de creación', '<?php echo $poll['created_at']; ?>'],
            [''],
            ['Opción', 'Votos', 'Porcentaje']
        ];
        
        // Añadir opciones
        <?php foreach ($options as $option): ?>
            <?php $percentage = $totalVotes > 0 ? round(($option['votes'] / $totalVotes) * 100, 1) : 0; ?>
            rows.push(['<?php echo addslashes($option['option_text']); ?>', <?php echo $option['votes']; ?>, '<?php echo $percentage; ?>%']);
        <?php endforeach; ?>
        
        // Convertir a formato CSV
        let csvContent = '';
        rows.forEach(row => {
            csvContent += row.join(',') + '\r\n';
        });
        
        // Crear enlace de descarga
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", "resultados_encuesta_<?php echo $poll['id']; ?>.csv");
        document.body.appendChild(link);
        
        // Iniciar descarga
        link.click();
        
        // Limpiar
        document.body.removeChild(link);
    }
</script>