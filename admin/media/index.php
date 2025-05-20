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
$auth->requirePermission(['admin', 'editor', 'author'], '../index.php');

// Título de la página
$pageTitle = 'Biblioteca de Medios - Panel de Administración';
$currentMenu = 'media';

// Directorio de medios
$uploadsDir = BASE_PATH . '/assets/img/uploads/';
$uploadsUrl = SITE_URL . '/assets/img/uploads/';

// Crear el directorio si no existe
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Procesamiento de búsqueda y filtros
$search = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$month = isset($_GET['month']) ? sanitize($_GET['month']) : '';
$user = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'date_desc';
$view = isset($_GET['view']) ? sanitize($_GET['view']) : 'grid';

// Construir la consulta SQL base
$sql = "SELECT m.*, u.name as user_name 
        FROM media m
        LEFT JOIN users u ON m.user_id = u.id
        WHERE 1=1";
$params = [];

// Aplicar filtros
if (!empty($search)) {
    $sql .= " AND (m.file_name LIKE ? OR m.file_type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type)) {
    $sql .= " AND m.file_type LIKE ?";
    $params[] = "%$type%";
}

if (!empty($month)) {
    $sql .= " AND DATE_FORMAT(m.uploaded_at, '%Y-%m') = ?";
    $params[] = $month;
}

if ($user > 0) {
    $sql .= " AND m.user_id = ?";
    $params[] = $user;
}

// Aplicar ordenación
switch ($sort) {
    case 'name_asc':
        $sql .= " ORDER BY m.file_name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY m.file_name DESC";
        break;
    case 'size_asc':
        $sql .= " ORDER BY m.file_size ASC";
        break;
    case 'size_desc':
        $sql .= " ORDER BY m.file_size DESC";
        break;
    case 'type_asc':
        $sql .= " ORDER BY m.file_type ASC";
        break;
    case 'type_desc':
        $sql .= " ORDER BY m.file_type DESC";
        break;
    case 'date_asc':
        $sql .= " ORDER BY m.uploaded_at ASC";
        break;
    case 'date_desc':
    default:
        $sql .= " ORDER BY m.uploaded_at DESC";
        break;
}

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 24; // 4x6 grid
$offset = ($page - 1) * $perPage;

// Obtener total de registros para paginación
$db = Database::getInstance();
$countSql = str_replace("SELECT m.*, u.name as user_name", "SELECT COUNT(*) as total", $sql);
$totalRecords = $db->fetch($countSql, $params)['total'];
$totalPages = ceil($totalRecords / $perPage);

// Agregar límite para paginación
$sql .= " LIMIT $perPage OFFSET $offset";

// Obtener medios
$media = $db->fetchAll($sql, $params);

// Obtener tipos de archivos únicos para filtro
$fileTypes = $db->fetchAll("SELECT DISTINCT file_type FROM media ORDER BY file_type ASC");

// Obtener usuarios para filtro
$users = $db->fetchAll("SELECT id, name FROM users ORDER BY name ASC");

// Obtener meses con archivos para filtro
$months = $db->fetchAll("SELECT DISTINCT DATE_FORMAT(uploaded_at, '%Y-%m') as month_year, 
                       DATE_FORMAT(uploaded_at, '%M %Y') as month_name 
                FROM media 
                ORDER BY month_year DESC");

// Detectar si es una petición AJAX para carga de medios asíncrona
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Si es una petición AJAX, devolver solo el grid de medios
if ($isAjax) {
    ob_start();
    if ($view === 'grid') {
        includeMediaGrid($media, $uploadsUrl);
    } else {
        includeMediaList($media, $uploadsUrl);
    }
    $html = ob_get_clean();
    
    echo json_encode([
        'html' => $html,
        'pagination' => generatePagination($page, $totalPages, 'index.php', [
            'q' => $search,
            'type' => $type,
            'month' => $month,
            'user' => $user,
            'sort' => $sort,
            'view' => $view
        ])
    ]);
    exit;
}

// Función para renderizar el grid de medios
function includeMediaGrid($media, $uploadsUrl) {
    global $db;
    if (empty($media)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No se encontraron archivos. Utiliza el botón "Subir Archivos" para añadir contenido multimedia.
        </div>
    <?php else: ?>
        <div class="row g-3 media-grid">
            <?php foreach ($media as $file): 
                $filePath = SITE_URL . '/' . $file['file_path'];
                $isImage = strpos($file['file_type'], 'image/') === 0;
                $fileIcon = getFileIcon($file['file_type']);
                $fileExtension = strtoupper(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                $csrfToken = generateCsrfToken();
            ?>
                <div class="col-lg-2 col-md-3 col-sm-4 col-6">
                    <div class="card media-item">
                        <div class="card-media">
                            <?php if ($isImage): ?>
                                <img src="<?php echo $filePath; ?>" class="card-img-top media-preview-btn" 
                                     data-id="<?php echo $file['id']; ?>"
                                     data-path="<?php echo $filePath; ?>"
                                     data-name="<?php echo $file['file_name']; ?>"
                                     data-type="<?php echo $file['file_type']; ?>"
                                     data-size="<?php echo formatBytes($file['file_size']); ?>"
                                     data-date="<?php echo formatDate($file['uploaded_at'], 'd/m/Y H:i'); ?>"
                                     data-user="<?php echo $file['user_name']; ?>"
                                     alt="<?php echo $file['file_name']; ?>">
                            <?php else: ?>
                                <div class="file-icon media-preview-btn"
                                     data-id="<?php echo $file['id']; ?>"
                                     data-path="<?php echo $filePath; ?>"
                                     data-name="<?php echo $file['file_name']; ?>"
                                     data-type="<?php echo $file['file_type']; ?>"
                                     data-size="<?php echo formatBytes($file['file_size']); ?>"
                                     data-date="<?php echo formatDate($file['uploaded_at'], 'd/m/Y H:i'); ?>"
                                     data-user="<?php echo $file['user_name']; ?>">
                                    <i class="<?php echo $fileIcon; ?>"></i>
                                    <span class="file-ext"><?php echo $fileExtension; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-2">
                            <h6 class="file-name" title="<?php echo $file['file_name']; ?>"><?php echo truncateString($file['file_name'], 15); ?></h6>
                            <div class="file-info">
                                <small class="text-muted"><?php echo formatBytes($file['file_size']); ?></small>
                                <small class="text-muted"><?php echo formatDate($file['uploaded_at'], 'd/m/Y'); ?></small>
                            </div>
                            <div class="file-actions mt-2">
                                <a href="<?php echo $filePath; ?>" class="btn btn-sm btn-info" target="_blank" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-primary copy-url" 
                                        data-url="<?php echo $filePath; ?>" title="Copiar URL">
                                    <i class="fas fa-link"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger btn-delete" 
                                        data-id="<?php echo $file['id']; ?>"
                                        data-name="<?php echo $file['file_name']; ?>"
                                        data-csrf="<?php echo $csrfToken; ?>"
                                        title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif;
}

// Función para renderizar la lista de medios
function includeMediaList($media, $uploadsUrl) {
    global $db;
    if (empty($media)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No se encontraron archivos. Utiliza el botón "Subir Archivos" para añadir contenido multimedia.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Tamaño</th>
                        <th>Subido por</th>
                        <th>Fecha</th>
                        <th style="width: 120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($media as $file): 
                        $filePath = $uploadsUrl . basename($file['file_path']);
                        $isImage = strpos($file['file_type'], 'image/') === 0;
                        $fileIcon = getFileIcon($file['file_type']);
                        $csrfToken = generateCsrfToken();
                    ?>
                        <tr>
                            <td>
                                <?php if ($isImage): ?>
                                    <div class="media-thumbnail">
                                        <img src="<?php echo $filePath; ?>" alt="<?php echo $file['file_name']; ?>" class="img-thumbnail media-preview-btn"
                                             data-id="<?php echo $file['id']; ?>"
                                             data-path="<?php echo $filePath; ?>"
                                             data-name="<?php echo $file['file_name']; ?>"
                                             data-type="<?php echo $file['file_type']; ?>"
                                             data-size="<?php echo formatBytes($file['file_size']); ?>"
                                             data-date="<?php echo formatDate($file['uploaded_at'], 'd/m/Y H:i'); ?>"
                                             data-user="<?php echo $file['user_name']; ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="media-icon-sm media-preview-btn"
                                         data-id="<?php echo $file['id']; ?>"
                                         data-path="<?php echo $filePath; ?>"
                                         data-name="<?php echo $file['file_name']; ?>"
                                         data-type="<?php echo $file['file_type']; ?>"
                                         data-size="<?php echo formatBytes($file['file_size']); ?>"
                                         data-date="<?php echo formatDate($file['uploaded_at'], 'd/m/Y H:i'); ?>"
                                         data-user="<?php echo $file['user_name']; ?>">
                                        <i class="<?php echo $fileIcon; ?>"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo $filePath; ?>" target="_blank" class="file-link">
                                    <?php echo $file['file_name']; ?>
                                </a>
                            </td>
                            <td><?php echo $file['file_type']; ?></td>
                            <td><?php echo formatBytes($file['file_size']); ?></td>
                            <td><?php echo $file['user_name']; ?></td>
                            <td><?php echo formatDate($file['uploaded_at'], 'd/m/Y H:i'); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?php echo $filePath; ?>" class="btn btn-sm btn-info" target="_blank" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-primary copy-url" 
                                            data-url="<?php echo $filePath; ?>" title="Copiar URL">
                                        <i class="fas fa-link"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete" 
                                            data-id="<?php echo $file['id']; ?>"
                                            data-name="<?php echo $file['file_name']; ?>"
                                            data-csrf="<?php echo $csrfToken; ?>"
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif;
}

// Función para obtener icono según tipo de archivo
function getFileIcon($fileType) {
    if (strpos($fileType, 'image/') === 0) {
        return 'fas fa-file-image';
    }
    if (strpos($fileType, 'video/') === 0) {
        return 'fas fa-file-video';
    }
    if (strpos($fileType, 'audio/') === 0) {
        return 'fas fa-file-audio';
    }
    if (strpos($fileType, 'application/pdf') === 0) {
        return 'fas fa-file-pdf';
    }
    if (strpos($fileType, 'application/msword') === 0 || 
        strpos($fileType, 'application/vnd.openxmlformats-officedocument.wordprocessingml') === 0) {
        return 'fas fa-file-word';
    }
    if (strpos($fileType, 'application/vnd.ms-excel') === 0 || 
        strpos($fileType, 'application/vnd.openxmlformats-officedocument.spreadsheetml') === 0) {
        return 'fas fa-file-excel';
    }
    if (strpos($fileType, 'application/vnd.ms-powerpoint') === 0 || 
        strpos($fileType, 'application/vnd.openxmlformats-officedocument.presentationml') === 0) {
        return 'fas fa-file-powerpoint';
    }
    if (strpos($fileType, 'text/') === 0) {
        return 'fas fa-file-alt';
    }
    if (strpos($fileType, 'application/zip') === 0 || 
        strpos($fileType, 'application/x-rar') === 0 ||
        strpos($fileType, 'application/x-7z-compressed') === 0) {
        return 'fas fa-file-archive';
    }
    
    return 'fas fa-file';
}

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
                    <h1 class="m-0">Biblioteca de Medios</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Biblioteca de Medios</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Explorador de Medios</h3>
                        <div class="btn-group">
                            <a href="upload.php" class="btn btn-primary">
                                <i class="fas fa-upload me-1"></i> Subir Archivos
                            </a>
                            <button type="button" class="btn btn-outline-secondary view-mode <?php echo $view === 'grid' ? 'active' : ''; ?>" data-mode="grid">
                                <i class="fas fa-th"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary view-mode <?php echo $view === 'list' ? 'active' : ''; ?>" data-mode="list">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filtros y búsqueda -->
                    <form method="get" action="index.php" id="filter-form">
                        <input type="hidden" name="view" value="<?php echo $view; ?>" id="view-mode-input">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="q" value="<?php echo $search; ?>" placeholder="Buscar archivos...">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-3">
                                        <select name="type" class="form-select" id="type-filter">
                                            <option value="">Todos los tipos</option>
                                            <?php foreach ($fileTypes as $fileType): ?>
                                                <option value="<?php echo $fileType['file_type']; ?>" <?php echo $type === $fileType['file_type'] ? 'selected' : ''; ?>>
                                                    <?php echo $fileType['file_type']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <select name="month" class="form-select" id="month-filter">
                                            <option value="">Todos los periodos</option>
                                            <?php foreach ($months as $monthData): ?>
                                                <option value="<?php echo $monthData['month_year']; ?>" <?php echo $month === $monthData['month_year'] ? 'selected' : ''; ?>>
                                                    <?php echo $monthData['month_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <select name="user" class="form-select" id="user-filter">
                                            <option value="0">Todos los usuarios</option>
                                            <?php foreach ($users as $userData): ?>
                                                <option value="<?php echo $userData['id']; ?>" <?php echo $user === (int)$userData['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $userData['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <select name="sort" class="form-select" id="sort-filter">
                                            <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Más recientes</option>
                                            <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Más antiguos</option>
                                            <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Nombre (A-Z)</option>
                                            <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Nombre (Z-A)</option>
                                            <option value="size_asc" <?php echo $sort === 'size_asc' ? 'selected' : ''; ?>>Tamaño (menor)</option>
                                            <option value="size_desc" <?php echo $sort === 'size_desc' ? 'selected' : ''; ?>>Tamaño (mayor)</option>
                                            <option value="type_asc" <?php echo $sort === 'type_asc' ? 'selected' : ''; ?>>Tipo (A-Z)</option>
                                            <option value="type_desc" <?php echo $sort === 'type_desc' ? 'selected' : ''; ?>>Tipo (Z-A)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Información de filtros activos -->
                    <?php if (!empty($search) || !empty($type) || !empty($month) || $user > 0): ?>
                        <div class="filter-info mb-4">
                            <div class="d-flex align-items-center">
                                <span class="me-2"><i class="fas fa-filter"></i> Filtros activos:</span>
                                <?php if (!empty($search)): ?>
                                    <span class="badge bg-primary me-2">Búsqueda: <?php echo $search; ?></span>
                                <?php endif; ?>
                                
                                <?php if (!empty($type)): ?>
                                    <span class="badge bg-info me-2">Tipo: <?php echo $type; ?></span>
                                <?php endif; ?>
                                
                                <?php if (!empty($month)): 
                                    $monthName = '';
                                    foreach ($months as $m) {
                                        if ($m['month_year'] === $month) {
                                            $monthName = $m['month_name'];
                                            break;
                                        }
                                    }
                                ?>
                                    <span class="badge bg-warning me-2">Periodo: <?php echo $monthName; ?></span>
                                <?php endif; ?>
                                
                                <?php if ($user > 0): 
                                    $userName = '';
                                    foreach ($users as $u) {
                                        if ($u['id'] == $user) {
                                            $userName = $u['name'];
                                            break;
                                        }
                                    }
                                ?>
                                    <span class="badge bg-success me-2">Usuario: <?php echo $userName; ?></span>
                                <?php endif; ?>
                                
                                <a href="index.php?view=<?php echo $view; ?>" class="btn btn-sm btn-outline-secondary ms-auto">
                                    <i class="fas fa-times me-1"></i>Limpiar filtros
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-photo-video"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total archivos</span>
                                    <span class="info-box-number"><?php echo number_format($totalRecords); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php 
                        // Calcular estadísticas adicionales
                        $totalSize = $db->fetch("SELECT SUM(file_size) as total FROM media")['total'];
                        $imageCount = $db->fetch("SELECT COUNT(*) as count FROM media WHERE file_type LIKE 'image/%'")['count'];
                        $otherCount = $totalRecords - $imageCount;
                        ?>
                        
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-image"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Imágenes</span>
                                    <span class="info-box-number"><?php echo number_format($imageCount); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-file-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Otros archivos</span>
                                    <span class="info-box-number"><?php echo number_format($otherCount); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-database"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Espacio usado</span>
                                    <span class="info-box-number"><?php echo formatBytes($totalSize); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Grid/Lista de medios -->
                    <div id="media-view-grid" style="display: <?php echo $view === 'grid' ? 'block' : 'none'; ?>">
                        <?php includeMediaGrid($media, $uploadsUrl); ?>
                    </div>
                    
                    <div id="media-view-list" style="display: <?php echo $view === 'list' ? 'block' : 'none'; ?>">
                        <?php includeMediaList($media, $uploadsUrl); ?>
                    </div>
                    
                    <!-- Paginación -->
                    <div id="pagination-container">
                        <?php echo generatePagination($page, $totalPages, 'index.php', [
                            'q' => $search,
                            'type' => $type,
                            'month' => $month,
                            'user' => $user,
                            'sort' => $sort,
                            'view' => $view
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de previsualización -->
<div class="modal fade" id="mediaPreviewModal" tabindex="-1" aria-labelledby="mediaPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mediaPreviewModalLabel">Detalles del archivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="preview-container"></div>
                    </div>
                    <div class="col-md-4">
                        <h5>Información</h5>
                        <ul class="list-group mb-3">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Nombre:</strong>
                                <span class="file-name"></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Nombre:</strong>
                                <span class="file-name"></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Tipo:</strong>
                                <span class="file-type"></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Tamaño:</strong>
                                <span class="file-size"></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Fecha:</strong>
                                <span class="file-date"></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Subido por:</strong>
                                <span class="file-user"></span>
                            </li>
                        </ul>
                        <div class="d-grid gap-2">
                            <a href="#" class="btn btn-primary download-link" download>
                                <i class="fas fa-download me-2"></i>Descargar archivo
                            </a>
                            <button type="button" class="btn btn-info copy-path">
                                <i class="fas fa-link me-2"></i>Copiar URL
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar el archivo <strong id="deleteFileName"></strong>? Esta acción no se puede deshacer.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Solo se pueden eliminar archivos que no estén siendo utilizados en el sitio.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Eliminar
                </a>
            </div>
        </div>
    </div>
</div>

<!-- CSS específico -->
<style>
    .media-grid {
        margin-bottom: 1.5rem;
    }
    
    .media-item {
        height: 100%;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    
    .media-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .card-media {
        height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    }
    
    .card-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .file-icon {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
    
    .file-icon i {
        font-size: 3rem;
        color: #6c757d;
    }
    
    .file-ext {
        background-color: #6c757d;
        color: white;
        padding: 2px 5px;
        border-radius: 3px;
        font-size: 0.7rem;
        margin-top: 10px;
    }
    
    .file-name {
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .file-info {
        display: flex;
        justify-content: space-between;
        font-size: 0.7rem;
    }
    
    .file-actions {
        display: flex;
        justify-content: space-between;
    }
    
    /* Vista de lista */
    .media-thumbnail {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .media-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 3px;
    }
    
    .media-icon-sm {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f8f9fa;
        border-radius: 3px;
    }
    
    .media-icon-sm i {
        font-size: 1.2rem;
        color: #6c757d;
    }
    
    .file-link {
        color: var(--bs-dark);
        text-decoration: none;
    }
    
    .file-link:hover {
        color: var(--bs-primary);
        text-decoration: underline;
    }
    
    /* Estilos para la previsualización */
    .preview-container {
        min-height: 300px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 10px;
        background-color: #f8f9fa;
    }
    
    .preview-container img, 
    .preview-container video, 
    .preview-container audio {
        max-width: 100%;
        max-height: 300px;
    }
    
    .media-icon {
        font-size: 5rem;
        color: #6c757d;
        margin-bottom: 1rem;
    }
    
    /* Ajustes responsivos */
    @media (max-width: 767.98px) {
        .card-media {
            height: 120px;
        }
        
        .file-icon i {
            font-size: 2.5rem;
        }
        
        .view-mode {
            padding: 0.25rem 0.5rem;
        }
        
        .file-actions .btn-sm {
            padding: 0.15rem 0.3rem;
            font-size: 0.75rem;
        }
    }
</style>

<?php 
// Archivos JS adicionales
$extraJS = ['../../assets/js/media.js'];

// Incluir pie de página
include_once ADMIN_PATH . '/includes/footer.php';
?>