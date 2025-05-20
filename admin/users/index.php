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
$auth->requirePermission(['admin'], 'dashboard.php');

// Obtener parámetros de filtro y paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$searchQuery = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$roleFilter = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$sortBy = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'id_desc';

// Inicializar DB
$db = Database::getInstance();

// Construir consulta base
$baseQuery = "SELECT id, username, email, name, role, status, created_at, last_login FROM users";
$countQuery = "SELECT COUNT(*) as total FROM users";
$queryParams = [];
$whereConditions = [];

// Añadir condiciones de búsqueda
if (!empty($searchQuery)) {
    $whereConditions[] = "(username LIKE ? OR email LIKE ? OR name LIKE ?)";
    $queryParams[] = "%$searchQuery%";
    $queryParams[] = "%$searchQuery%";
    $queryParams[] = "%$searchQuery%";
}

if (!empty($roleFilter)) {
    $whereConditions[] = "role = ?";
    $queryParams[] = $roleFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "status = ?";
    $queryParams[] = $statusFilter;
}

// Combinar condiciones WHERE
if (!empty($whereConditions)) {
    $baseQuery .= " WHERE " . implode(' AND ', $whereConditions);
    $countQuery .= " WHERE " . implode(' AND ', $whereConditions);
}

// Añadir ordenación
$sortParts = explode('_', $sortBy);
$sortColumn = $sortParts[0] ?? 'id';
$sortDirection = $sortParts[1] ?? 'desc';

// Validar columna de ordenación
$allowedColumns = ['id', 'username', 'name', 'email', 'role', 'status', 'created_at', 'last_login'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'id';
}

// Validar dirección de ordenación
if ($sortDirection !== 'asc' && $sortDirection !== 'desc') {
    $sortDirection = 'desc';
}

$baseQuery .= " ORDER BY $sortColumn $sortDirection";

// Añadir límite para paginación
$baseQuery .= " LIMIT $perPage OFFSET $offset";

// Obtener total de usuarios para paginación
$totalUsers = $db->fetch($countQuery, $queryParams);
$totalPages = ceil($totalUsers['total'] / $perPage);

// Obtener usuarios según los filtros
$users = $db->fetchAll($baseQuery, $queryParams);

// Título de la página
$pageTitle = 'Usuarios - Panel de Administración';
$currentMenu = 'users';

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
                    <h1 class="m-0">Usuarios</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Usuarios</li>
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
                        <h3 class="card-title">Listado de usuarios</h3>
                        <a href="add.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Añadir nuevo usuario
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Formulario de búsqueda -->
                    <form action="index.php" method="get" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Buscar por nombre, email o usuario...">
                                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="role" class="form-select">
                                    <option value="">Todos los roles</option>
                                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="editor" <?php echo $roleFilter === 'editor' ? 'selected' : ''; ?>>Editor</option>
                                    <option value="author" <?php echo $roleFilter === 'author' ? 'selected' : ''; ?>>Autor</option>
                                    <option value="subscriber" <?php echo $roleFilter === 'subscriber' ? 'selected' : ''; ?>>Suscriptor</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">Todos los estados</option>
                                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                    <option value="banned" <?php echo $statusFilter === 'banned' ? 'selected' : ''; ?>>Baneado</option>
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
                    
                    <!-- Tabla de usuarios -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="5%"><?php echo generateSortLink('id', 'ID', $sortBy); ?></th>
                                    <th width="15%"><?php echo generateSortLink('username', 'Usuario', $sortBy); ?></th>
                                    <th width="20%"><?php echo generateSortLink('name', 'Nombre', $sortBy); ?></th>
                                    <th width="20%"><?php echo generateSortLink('email', 'Email', $sortBy); ?></th>
                                    <th width="10%"><?php echo generateSortLink('role', 'Rol', $sortBy); ?></th>
                                    <th width="10%"><?php echo generateSortLink('status', 'Estado', $sortBy); ?></th>
                                    <th width="10%"><?php echo generateSortLink('created_at', 'Registro', $sortBy); ?></th>
                                    <th width="10%">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php 
                                                switch ($user['role']) {
                                                    case 'admin':
                                                        echo '<span class="badge bg-danger">Administrador</span>';
                                                        break;
                                                    case 'editor':
                                                        echo '<span class="badge bg-success">Editor</span>';
                                                        break;
                                                    case 'author':
                                                        echo '<span class="badge bg-primary">Autor</span>';
                                                        break;
                                                    default:
                                                        echo '<span class="badge bg-secondary">Suscriptor</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                switch ($user['status']) {
                                                    case 'active':
                                                        echo '<span class="badge bg-success">Activo</span>';
                                                        break;
                                                    case 'inactive':
                                                        echo '<span class="badge bg-warning">Inactivo</span>';
                                                        break;
                                                    case 'banned':
                                                        echo '<span class="badge bg-danger">Baneado</span>';
                                                        break;
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo formatDate($user['created_at'], 'd/m/Y'); ?></td>
                                            <td>
    <div class="btn-group">
        <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
            <i class="fas fa-edit"></i>
        </a>
        <?php if ($user['id'] != $_SESSION['user']['id']): ?>
            <a href="#" class="btn btn-sm btn-danger btn-delete" 
               data-user-id="<?php echo $user['id']; ?>" 
               data-username="<?php echo htmlspecialchars($user['username']); ?>"
               title="Eliminar">
                <i class="fas fa-trash"></i>
            </a>
        <?php endif; ?>
    </div>
</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No se encontraron usuarios</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mt-4">
                            <nav aria-label="Paginación de usuarios">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . ($page - 1) . (!empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '') . (!empty($roleFilter) ? '&role=' . urlencode($roleFilter) : '') . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($sortBy) ? '&sort=' . urlencode($sortBy) : ''); ?>" aria-label="Anterior">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '') . (!empty($roleFilter) ? '&role=' . urlencode($roleFilter) : '') . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($sortBy) ? '&sort=' . urlencode($sortBy) : '') . '">1</a></li>';
                                        if ($startPage > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                                            <a class="page-link" href="?page=' . $i . (!empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '') . (!empty($roleFilter) ? '&role=' . urlencode($roleFilter) : '') . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($sortBy) ? '&sort=' . urlencode($sortBy) : '') . '">' . $i . '</a>
                                        </li>';
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . (!empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '') . (!empty($roleFilter) ? '&role=' . urlencode($roleFilter) : '') . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($sortBy) ? '&sort=' . urlencode($sortBy) : '') . '">' . $totalPages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . ($page + 1) . (!empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '') . (!empty($roleFilter) ? '&role=' . urlencode($roleFilter) : '') . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($sortBy) ? '&sort=' . urlencode($sortBy) : ''); ?>" aria-label="Siguiente">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación para eliminar usuario -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteUserModalLabel">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>¡Advertencia!</h5>
                    <p>Estás a punto de eliminar al usuario <strong id="delete-username"></strong>.</p>
                    <p>Esta acción no se puede deshacer y podría afectar el funcionamiento del sistema si el usuario tiene contenido asociado.</p>
                </div>
                <form id="deleteUserForm" action="delete.php" method="GET">
                    <input type="hidden" name="id" id="delete-user-id">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirm_delete_checkbox" required>
                        <label class="form-check-label" for="confirm_delete_checkbox">
                            Entiendo que esta acción no se puede deshacer
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn" disabled>
                    <i class="fas fa-trash me-1"></i> Eliminar usuario
                </button>
            </div>
        </div>
    </div>
</div>
<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Script específico -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal de confirmación para eliminar usuario
    const deleteUserModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    const deleteButtons = document.querySelectorAll('.btn-delete');
    const confirmDeleteCheckbox = document.getElementById('confirm_delete_checkbox');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const deleteUserForm = document.getElementById('deleteUserForm');
    
    // Habilitar/deshabilitar botón de confirmación según checkbox
    if (confirmDeleteCheckbox && confirmDeleteBtn) {
        confirmDeleteCheckbox.addEventListener('change', function() {
            confirmDeleteBtn.disabled = !this.checked;
        });
    }
    
    // Configurar el botón de confirmación para enviar el formulario
    if (confirmDeleteBtn && deleteUserForm) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (confirmDeleteCheckbox.checked) {
                window.location.href = deleteUserForm.action + '?id=' + document.getElementById('delete-user-id').value;
            }
        });
    }
    
    // Configurar los botones de eliminar para abrir el modal
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Obtener ID y nombre del usuario
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');
            
            // Configurar el modal
            document.getElementById('delete-user-id').value = userId;
            document.getElementById('delete-username').textContent = username;
            
            // Restablecer el checkbox
            confirmDeleteCheckbox.checked = false;
            confirmDeleteBtn.disabled = true;
            
            // Mostrar el modal
            deleteUserModal.show();
        });
    });
});
</script>