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
$auth->requirePermission(['admin', 'editor'], '../index.php');

// Título de la página y menú activo
$pageTitle = 'Suscriptores - Panel de Administración';
$currentMenu = 'subscribers';

// Inicializar DB
$db = Database::getInstance();

// Configurar paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Inicializar filtros
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$searchQuery = isset($_GET['q']) ? sanitize($_GET['q']) : '';

// Construir la consulta SQL base
$sqlCount = "SELECT COUNT(*) as total FROM subscribers";
$sqlSubscribers = "SELECT id, email, name, status, confirmed, created_at, updated_at FROM subscribers";

// Construir la cláusula WHERE para filtros
$whereClause = [];
$params = [];

if (!empty($statusFilter)) {
    $whereClause[] = "status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereClause[] = "(email LIKE ? OR name LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

// Aplicar filtros a las consultas
if (!empty($whereClause)) {
    $whereStr = " WHERE " . implode(' AND ', $whereClause);
    $sqlCount .= $whereStr;
    $sqlSubscribers .= $whereStr;
}

// Ordenación
$orderBy = " ORDER BY created_at DESC";
$sqlSubscribers .= $orderBy;

// Paginación
$sqlSubscribers .= " LIMIT ? OFFSET ?";
$subscribersParams = array_merge($params, [$perPage, $offset]);

// Obtener total de registros
$totalResults = $db->fetch($sqlCount, $params);
$totalSubscribers = $totalResults['total'] ?? 0;
$totalPages = ceil($totalSubscribers / $perPage);

// Obtener suscriptores para la página actual
$subscribers = $db->fetchAll($sqlSubscribers, $subscribersParams);

// Obtener estadísticas
$totalActive = $db->fetch("SELECT COUNT(*) as total FROM subscribers WHERE status = 'active'")['total'] ?? 0;
$totalInactive = $db->fetch("SELECT COUNT(*) as total FROM subscribers WHERE status = 'inactive'")['total'] ?? 0;
$totalUnsubscribed = $db->fetch("SELECT COUNT(*) as total FROM subscribers WHERE status = 'unsubscribed'")['total'] ?? 0;
$totalConfirmed = $db->fetch("SELECT COUNT(*) as total FROM subscribers WHERE confirmed = 1")['total'] ?? 0;
$totalUnconfirmed = $db->fetch("SELECT COUNT(*) as total FROM subscribers WHERE confirmed = 0")['total'] ?? 0;

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
                    <h1 class="m-0">Suscriptores</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Suscriptores</li>
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
                <div class="col-lg-2 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary"><i class="fas fa-users"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total</span>
                            <span class="info-box-number"><?php echo number_format($totalSubscribers); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-user-check"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Activos</span>
                            <span class="info-box-number"><?php echo number_format($totalActive); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-user-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Inactivos</span>
                            <span class="info-box-number"><?php echo number_format($totalInactive); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger"><i class="fas fa-user-times"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Dados de baja</span>
                            <span class="info-box-number"><?php echo number_format($totalUnsubscribed); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-envelope-open"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Confirmados</span>
                            <span class="info-box-number"><?php echo number_format($totalConfirmed); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-secondary"><i class="fas fa-envelope"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Sin confirmar</span>
                            <span class="info-box-number"><?php echo number_format($totalUnconfirmed); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros y búsqueda -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="index.php" method="get" class="mb-0">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Buscar por email o nombre...">
                                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">Todos los estados</option>
                                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Activos</option>
                                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                                    <option value="unsubscribed" <?php echo $statusFilter === 'unsubscribed' ? 'selected' : ''; ?>>Dados de baja</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                            </div>
                            
                            <div class="col-md-2">
                                <a href="index.php" class="btn btn-secondary w-100">Limpiar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Acciones masivas -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="export.php<?php echo !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''; ?>" class="btn btn-success w-100">
                                <i class="fas fa-file-export me-2"></i>Exportar suscriptores
                            </a>
                        </div>
                        
                        <div class="col-md-4">
                            <button type="button" class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#bulkStatusModal">
                                <i class="fas fa-user-tag me-2"></i>Cambiar estado masivo
                            </button>
                        </div>
                        
                        <div class="col-md-4">
                            <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                                <i class="fas fa-trash me-2"></i>Eliminar seleccionados
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Listado de suscriptores -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?php if (!empty($searchQuery) || !empty($statusFilter)): ?>
                            Resultados de búsqueda (<?php echo number_format($totalSubscribers); ?>)
                        <?php else: ?>
                            Listado de suscriptores
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <form id="subscribersForm" action="delete.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" id="action_type" name="action_type" value="delete">
                        <input type="hidden" id="new_status" name="new_status" value="">
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 2%">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="checkAll">
                                            </div>
                                        </th>
                                        <th style="width: 30%">Email</th>
                                        <th style="width: 20%">Nombre</th>
                                        <th style="width: 15%">Estado</th>
                                        <th style="width: 15%">Confirmado</th>
                                        <th style="width: 15%">Fecha</th>
                                        <th style="width: 10%">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($subscribers)): ?>
                                        <?php foreach ($subscribers as $subscriber): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input subscriber-checkbox" type="checkbox" name="selected_ids[]" value="<?php echo $subscriber['id']; ?>">
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                                <td><?php echo !empty($subscriber['name']) ? htmlspecialchars($subscriber['name']) : '<em>Sin nombre</em>'; ?></td>
                                                <td>
                                                    <?php
                                                    switch ($subscriber['status']) {
                                                        case 'active':
                                                            echo '<span class="badge bg-success">Activo</span>';
                                                            break;
                                                        case 'inactive':
                                                            echo '<span class="badge bg-warning">Inactivo</span>';
                                                            break;
                                                        case 'unsubscribed':
                                                            echo '<span class="badge bg-danger">Dado de baja</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-secondary">Desconocido</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($subscriber['confirmed']): ?>
                                                        <span class="badge bg-success">Sí</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($subscriber['created_at'], 'd/m/Y H:i'); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Acciones
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <button type="button" class="dropdown-item change-status" data-id="<?php echo $subscriber['id']; ?>" data-status="active" <?php echo $subscriber['status'] === 'active' ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-check-circle text-success me-2"></i>Activar
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button type="button" class="dropdown-item change-status" data-id="<?php echo $subscriber['id']; ?>" data-status="inactive" <?php echo $subscriber['status'] === 'inactive' ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-pause-circle text-warning me-2"></i>Desactivar
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button type="button" class="dropdown-item change-status" data-id="<?php echo $subscriber['id']; ?>" data-status="unsubscribed" <?php echo $subscriber['status'] === 'unsubscribed' ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-times-circle text-danger me-2"></i>Dar de baja
                                                                </button>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <button type="button" class="dropdown-item text-danger delete-subscriber" data-id="<?php echo $subscriber['id']; ?>" data-email="<?php echo htmlspecialchars($subscriber['email']); ?>">
                                                                    <i class="fas fa-trash me-2"></i>Eliminar
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <p class="text-muted mb-0">No se encontraron suscriptores.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
                
                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="text-muted">Mostrando <?php echo count($subscribers); ?> de <?php echo $totalSubscribers; ?> suscriptores</p>
                            </div>
                            <div class="col-md-6">
                                <?php
                                // Construir la URL base para la paginación
                                $paginationUrl = 'index.php?';
                                $queryParams = [];
                                
                                if (!empty($statusFilter)) {
                                    $queryParams[] = 'status=' . urlencode($statusFilter);
                                }
                                
                                if (!empty($searchQuery)) {
                                    $queryParams[] = 'q=' . urlencode($searchQuery);
                                }
                                
                                if (!empty($queryParams)) {
                                    $paginationUrl .= implode('&', $queryParams) . '&';
                                }
                                
                                echo generatePagination($page, $totalPages, $paginationUrl, []);
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para cambio masivo de estado -->
<div class="modal fade" id="bulkStatusModal" tabindex="-1" aria-labelledby="bulkStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkStatusModalLabel">Cambiar estado de suscriptores</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Selecciona el nuevo estado para los suscriptores seleccionados:</p>
                <div class="form-group">
                    <select id="bulkStatusSelect" class="form-select">
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                        <option value="unsubscribed">Dado de baja</option>
                    </select>
                </div>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>Esta acción afectará a todos los suscriptores seleccionados.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="applyBulkStatus">Aplicar cambios</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para eliminación masiva -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkDeleteModalLabel">Eliminar suscriptores</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><strong>¡Atención!</strong> Estás a punto de eliminar permanentemente los suscriptores seleccionados.
                </div>
                <p>Esta acción no se puede deshacer. ¿Estás seguro de que deseas continuar?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmBulkDelete">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para eliminar un suscriptor individual -->
<div class="modal fade" id="deleteSubscriberModal" tabindex="-1" aria-labelledby="deleteSubscriberModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteSubscriberModalLabel">Eliminar suscriptor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><strong>¡Atención!</strong> Estás a punto de eliminar permanentemente el siguiente suscriptor:
                </div>
                <p id="deleteSubscriberEmail" class="mb-0"></p>
                <input type="hidden" id="deleteSubscriberId" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteSubscriber">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Incluir pie de página -->
<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Script específico para esta página -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Seleccionar/deseleccionar todos
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            document.querySelectorAll('.subscriber-checkbox').forEach(function(checkbox) {
                checkbox.checked = checkAll.checked;
            });
        });
    }
    
    // Cambiar estado individual
    document.querySelectorAll('.change-status').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const status = this.dataset.status;
            
            // Preparar formulario
            document.getElementById('action_type').value = 'status';
            document.getElementById('new_status').value = status;
            
            // Desmarcar todos
            document.querySelectorAll('.subscriber-checkbox').forEach(function(checkbox) {
                checkbox.checked = false;
            });
            
            // Marcar solo el ID seleccionado
            const form = document.getElementById('subscribersForm');
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_ids[]';
            input.value = id;
            form.appendChild(input);
            
            // Enviar formulario
            form.submit();
        });
    });
    
    // Cambio masivo de estado
    document.getElementById('applyBulkStatus').addEventListener('click', function() {
        const selectedIds = getSelectedIds();
        if (selectedIds.length === 0) {
            alert('Por favor, selecciona al menos un suscriptor.');
            return;
        }
        
        const newStatus = document.getElementById('bulkStatusSelect').value;
        document.getElementById('action_type').value = 'status';
        document.getElementById('new_status').value = newStatus;
        document.getElementById('subscribersForm').submit();
    });
    
    // Eliminar suscriptor individual
    document.querySelectorAll('.delete-subscriber').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const email = this.dataset.email;
            
            // Mostrar modal de confirmación
            const modal = new bootstrap.Modal(document.getElementById('deleteSubscriberModal'));
            document.getElementById('deleteSubscriberEmail').textContent = email;
            document.getElementById('deleteSubscriberId').value = id;
            modal.show();
        });
    });
    
    // Confirmar eliminación individual
    document.getElementById('confirmDeleteSubscriber').addEventListener('click', function() {
        const id = document.getElementById('deleteSubscriberId').value;
        
        // Preparar formulario
        document.getElementById('action_type').value = 'delete';
        
        // Desmarcar todos
        document.querySelectorAll('.subscriber-checkbox').forEach(function(checkbox) {
            checkbox.checked = false;
        });
        
        // Marcar solo el ID seleccionado
        const form = document.getElementById('subscribersForm');
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_ids[]';
        input.value = id;
        form.appendChild(input);
        
        // Enviar formulario
        form.submit();
    });
    
    // Eliminar masivamente
    document.getElementById('confirmBulkDelete').addEventListener('click', function() {
        const selectedIds = getSelectedIds();
        if (selectedIds.length === 0) {
            alert('Por favor, selecciona al menos un suscriptor.');
            return;
        }
        
        document.getElementById('action_type').value = 'delete';
        document.getElementById('subscribersForm').submit();
    });
    
    // Función para obtener IDs seleccionados
    function getSelectedIds() {
        const checkboxes = document.querySelectorAll('.subscriber-checkbox:checked');
        return Array.from(checkboxes).map(checkbox => checkbox.value);
    }
});
</script>