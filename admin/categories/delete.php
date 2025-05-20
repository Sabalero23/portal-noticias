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
$auth->requirePermission(['admin', 'editor'], 'index.php');

// Verificar ID de categoría
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de categoría no válido');
    redirect('index.php');
    exit;
}

$categoryId = (int)$_GET['id'];
$db = Database::getInstance();

// Verificar token CSRF
$validToken = false;
if (isset($_GET['token'])) {
    $validToken = verifyCsrfToken($_GET['token']);
}

// Si se accede directamente sin confirmar, mostrar página de confirmación
if (!isset($_GET['confirm']) || $_GET['confirm'] !== '1' || !$validToken) {
    // Obtener datos de la categoría
    $category = $db->fetch(
        "SELECT c.*, 
         (SELECT COUNT(*) FROM news WHERE category_id = c.id) as news_count,
         (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as subcategories_count
         FROM categories c
         WHERE c.id = ?",
        [$categoryId]
    );
    
    if (!$category) {
        setFlashMessage('error', 'Categoría no encontrada');
        redirect('index.php');
        exit;
    }
    
    // Título de la página
    $pageTitle = 'Eliminar Categoría - Panel de Administración';
    $currentMenu = 'categories';
    
    // Incluir cabecera
    include_once ADMIN_PATH . '/includes/header.php';
    include_once ADMIN_PATH . '/includes/sidebar.php';
    
    // Generar token CSRF
    $csrfToken = generateCsrfToken();
    ?>
    
    <!-- Contenido principal -->
    <div class="content-wrapper">
        <!-- Cabecera de página -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Eliminar Categoría</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Categorías</a></li>
                            <li class="breadcrumb-item active">Eliminar</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contenido -->
        <div class="content">
            <div class="container-fluid">
                <!-- Información de depuración (opcional, quitar en producción) -->
                <?php if (isset($_GET['debug'])): ?>
                <div class="alert alert-info">
                    <h5>Información de depuración:</h5>
                    <pre><?php print_r($category); ?></pre>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h3 class="card-title">Confirmar eliminación</h3>
                    </div>
                    <div class="card-body">
                        <p>Estás a punto de eliminar la categoría <strong><?php echo htmlspecialchars($category['name']); ?></strong>.</p>
                        
                        <?php if ($category['news_count'] > 0): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i> 
                                Esta categoría tiene <strong><?php echo $category['news_count']; ?> noticias</strong> asociadas. 
                                Al eliminarla, todas las noticias pasarán a categoría "Sin Categoría".
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($category['subcategories_count'] > 0): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i> 
                                Esta categoría tiene <strong><?php echo $category['subcategories_count']; ?> subcategorías</strong>. 
                                Al eliminarla, todas las subcategorías pasarán a ser categorías principales.
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-danger">
                            <strong>¡Atención!</strong> Esta acción no se puede deshacer.
                        </div>
                        
                        <!-- Detalles de la categoría -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th style="width: 30%">ID</th>
                                            <td><?php echo $category['id']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Nombre</th>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Slug</th>
                                            <td><?php echo htmlspecialchars($category['slug']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Descripción</th>
                                            <td><?php echo !empty($category['description']) ? htmlspecialchars($category['description']) : '<em>Sin descripción</em>'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Color</th>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="color-box me-2" style="background-color: <?php echo $category['color']; ?>"></span>
                                                    <?php echo $category['color']; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="col-md-6">
                                <?php if (!empty($category['image'])): ?>
                                    <div class="text-center">
                                        <p><strong>Imagen de la categoría:</strong></p>
                                        <img src="<?php echo SITE_URL . '/' . $category['image']; ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" class="img-fluid img-thumbnail" style="max-height: 200px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-center mt-4">
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <a href="delete.php?id=<?php echo $categoryId; ?>&confirm=1&token=<?php echo $csrfToken; ?>" class="btn btn-danger">
                                <i class="fas fa-trash me-1"></i> Sí, eliminar categoría
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .color-box {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            display: inline-block;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
    </style>
    
    <?php
    include_once ADMIN_PATH . '/includes/footer.php';
    exit;
}

// Si llegamos aquí, se confirmó la eliminación
// Obtener datos de la categoría antes de eliminarla
$category = $db->fetch(
    "SELECT * FROM categories WHERE id = ?",
    [$categoryId]
);

if (!$category) {
    setFlashMessage('error', 'Categoría no encontrada');
    redirect('index.php');
    exit;
}

// Iniciar una transacción para asegurar la integridad de los datos
$transaction = new Transaction($db->getConnection());
$transaction->begin();

try {
    // 1. Actualizar subcategorías (poner parent_id a NULL)
    $db->query(
        "UPDATE categories SET parent_id = NULL, updated_at = NOW() WHERE parent_id = ?",
        [$categoryId]
    );
    
    // 2. Actualizar noticias (mover a categoría "Sin categoría" o categoría por defecto)
    $defaultCategoryId = getSetting('default_category_id', null);
    
    if ($defaultCategoryId === null) {
        // Buscar o crear categoría "Sin categoría"
        $uncategorized = $db->fetch(
            "SELECT id FROM categories WHERE slug = 'sin-categoria'"
        );
        
        if ($uncategorized) {
            $defaultCategoryId = $uncategorized['id'];
        } else {
            // Crear categoría "Sin categoría"
            $db->query(
                "INSERT INTO categories (name, slug, description, color, created_at, updated_at) 
                 VALUES ('Sin categoría', 'sin-categoria', 'Noticias sin categoría asignada', '#999999', NOW(), NOW())"
            );
            $defaultCategoryId = $db->lastInsertId();
        }
        
        // Guardar en configuración
        $db->query(
            "INSERT INTO settings (setting_key, setting_value, setting_group) 
             VALUES ('default_category_id', ?, 'content')
             ON DUPLICATE KEY UPDATE setting_value = ?",
            [$defaultCategoryId, $defaultCategoryId]
        );
    }
    
    // Mover noticias a la categoría por defecto
    $db->query(
        "UPDATE news SET category_id = ?, updated_at = NOW() WHERE category_id = ?",
        [$defaultCategoryId, $categoryId]
    );
    
    // 3. Eliminar la imagen si existe
    if (!empty($category['image']) && file_exists(BASE_PATH . '/' . $category['image'])) {
        @unlink(BASE_PATH . '/' . $category['image']);
    }
    
    // 4. Eliminar la categoría
    $deleted = $db->query(
        "DELETE FROM categories WHERE id = ?",
        [$categoryId]
    );
    
    if (!$deleted) {
        throw new Exception('Error al eliminar la categoría de la base de datos');
    }
    
    // Confirmar transacción
    $transaction->commit();
    
    // Registrar acción
    logAdminAction('Eliminar categoría', "Categoría: {$category['name']} (ID: $categoryId)", 'categories', $categoryId);
    
    // Mensaje de éxito
    setFlashMessage('success', 'Categoría eliminada correctamente');
} catch (Exception $e) {
    // Revertir cambios en caso de error
    $transaction->rollback();
    
    // Mensaje de error
    setFlashMessage('error', 'Error al eliminar la categoría: ' . $e->getMessage());
}

// Redireccionar al listado
redirect('index.php');