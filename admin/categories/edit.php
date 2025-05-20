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

// Título de la página
$pageTitle = 'Editar Categoría - Panel de Administración';
$currentMenu = 'categories';

// Verificar ID de categoría
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de categoría no válido');
    redirect('index.php');
    exit;
}

$categoryId = (int)$_GET['id'];
$db = Database::getInstance();

// Obtener datos de la categoría
$category = $db->fetch(
    "SELECT * FROM categories WHERE id = ?",
    [$categoryId]
);

if (!$category) {
    setFlashMessage('error', 'Categoría no encontrada');
    redirect('index.php');
    exit;
}

// Inicializar variables con los datos actuales
$name = $category['name'];
$slug = $category['slug'];
$description = $category['description'];
$color = $category['color'];
// Manejar parent_id para asegurar que sea null si está vacío
$parent_id = !empty($category['parent_id']) ? $category['parent_id'] : null;
$currentImage = $category['image'];
$errors = [];
$success = false;

// Obtener categorías para el desplegable (excluyendo la actual para evitar recursividad)
$categories = $db->fetchAll(
    "SELECT id, name FROM categories WHERE id != ? ORDER BY name ASC",
    [$categoryId]
);

// Contar noticias asociadas a esta categoría
$newsCount = $db->fetch(
    "SELECT COUNT(*) as count FROM news WHERE category_id = ?",
    [$categoryId]
)['count'];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors['general'] = 'Token de seguridad inválido';
    } else {
        // Validar campos
        $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
        $slug = isset($_POST['slug']) ? sanitize($_POST['slug']) : '';
        $description = isset($_POST['description']) ? sanitize($_POST['description']) : '';
        $color = isset($_POST['color']) ? sanitize($_POST['color']) : '#333333';
        $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $deleteImage = isset($_POST['delete_image']) && $_POST['delete_image'] === '1';
        
        // Validar nombre
        if (empty($name)) {
            $errors['name'] = 'El nombre es obligatorio';
        } elseif (strlen($name) > 50) {
            $errors['name'] = 'El nombre no puede exceder los 50 caracteres';
        }
        
        // Validar slug
        if (empty($slug)) {
            // Generar slug desde el nombre
            $slug = generateSlug($name);
        } elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            $errors['slug'] = 'El slug solo puede contener letras minúsculas, números y guiones';
        }
        
        // Verificar si el slug ya existe (excluyendo la categoría actual)
        $existingSlug = $db->fetch(
            "SELECT id FROM categories WHERE slug = ? AND id != ?", 
            [$slug, $categoryId]
        );
        
        if ($existingSlug) {
            $errors['slug'] = 'Este slug ya está en uso. Por favor, elige otro';
        }
        
        // Validar color
        if (empty($color)) {
            $color = '#333333'; // Color por defecto
        } elseif (!preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
            $errors['color'] = 'El color debe tener un formato hexadecimal válido (ej. #FF5733)';
        }
        
        // Validar categoría padre
        if ($parent_id !== null) {
            // Verificar que no es la misma categoría
            if ($parent_id == $categoryId) {
                $errors['parent_id'] = 'Una categoría no puede ser su propia categoría padre';
            } else {
                $parentExists = $db->fetch(
                    "SELECT id FROM categories WHERE id = ?", 
                    [$parent_id]
                );
                
                if (!$parentExists) {
                    $errors['parent_id'] = 'La categoría padre seleccionada no existe';
                }
                
                // Verificar que no hay recursividad (la categoría padre no puede ser hija de esta categoría)
                $childCategories = [];
                getChildCategories($db, $categoryId, $childCategories);
                
                if (in_array($parent_id, $childCategories)) {
                    $errors['parent_id'] = 'No se puede seleccionar una subcategoría como categoría padre';
                }
            }
        }
        
        // Procesar imagen si se proporcionó
        $newImage = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            
            $uploadResult = processImageUpload(
                $_FILES['image'],
                BASE_PATH . '/assets/img/categories',
                $allowedTypes,
                $maxSize
            );
            
            if ($uploadResult['success']) {
                $newImage = 'assets/img/categories/' . $uploadResult['filename'];
            } else {
                $errors['image'] = $uploadResult['message'];
            }
        }
        
        // Si no hay errores, actualizar
        if (empty($errors)) {
            // Definir la imagen a guardar
            $imageToSave = $currentImage;
            
            if ($deleteImage) {
                // Si se marcó eliminar imagen, eliminar la actual
                $imageToSave = '';
                
                // Eliminar archivo físico si existe
                if (!empty($currentImage) && file_exists(BASE_PATH . '/' . $currentImage)) {
                    @unlink(BASE_PATH . '/' . $currentImage);
                }
            } elseif (!empty($newImage)) {
                // Si se subió una nueva imagen, usar esa y eliminar la antigua
                $imageToSave = $newImage;
                
                // Eliminar archivo físico si existe
                if (!empty($currentImage) && file_exists(BASE_PATH . '/' . $currentImage)) {
                    @unlink(BASE_PATH . '/' . $currentImage);
                }
            }
            
            // Actualizar en base de datos
            $result = $db->query(
                "UPDATE categories 
                 SET name = ?, slug = ?, description = ?, color = ?, parent_id = ?, image = ?, updated_at = NOW() 
                 WHERE id = ?",
                [$name, $slug, $description, $color, $parent_id, $imageToSave, $categoryId]
            );
            
            if ($result) {
                // Registrar acción
                logAdminAction('Editar categoría', "Categoría: $name (ID: $categoryId)", 'categories', $categoryId);
                
                // Mostrar mensaje de éxito
                setFlashMessage('success', 'Categoría actualizada correctamente');
                
                // Redireccionar para evitar reenvío del formulario
                redirect('index.php');
                exit;
            } else {
                $errors['general'] = 'Error al actualizar la categoría. Por favor, inténtalo de nuevo.';
            }
        }
    }
}

// Función para obtener categorías hijas recursivamente
function getChildCategories($db, $parentId, &$result) {
    $children = $db->fetchAll(
        "SELECT id FROM categories WHERE parent_id = ?", 
        [$parentId]
    );
    
    foreach ($children as $child) {
        $childId = $child['id'];
        $result[] = $childId;
        getChildCategories($db, $childId, $result);
    }
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
                    <h1 class="m-0">Editar Categoría</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Categorías</a></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Mensajes de error -->
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $errors['general']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Editar categoría: <?php echo htmlspecialchars($category['name']); ?></h3>
                </div>
                <div class="card-body">
                    <!-- Información de depuración (opcional, quitar en producción) -->
                    <?php if (isset($_GET['debug'])): ?>
                    <div class="alert alert-info">
                        <h5>Información de depuración:</h5>
                        <pre><?php print_r($category); ?></pre>
                    </div>
                    <?php endif; ?>
                    
                    <form method="post" action="edit.php?id=<?php echo $categoryId; ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug</label>
                            <input type="text" class="form-control <?php echo isset($errors['slug']) ? 'is-invalid' : ''; ?>" id="slug" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
                            <div class="form-text">Dejar vacío para generar automáticamente desde el nombre.</div>
                            <?php if (isset($errors['slug'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['slug']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Categoría Padre</label>
                            <select class="form-select <?php echo isset($errors['parent_id']) ? 'is-invalid' : ''; ?>" id="parent_id" name="parent_id">
                                <option value="">Ninguna (Categoría principal)</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($parent_id == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['parent_id'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['parent_id']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="color" class="form-label">Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="color" name="color" value="<?php echo htmlspecialchars($color); ?>" title="Elige un color">
                                <input type="text" class="form-control" id="color_hex" value="<?php echo htmlspecialchars($color); ?>" readonly>
                            </div>
                            <?php if (isset($errors['color'])): ?>
                                <div class="invalid-feedback d-block"><?php echo $errors['color']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Imagen</label>
                            <input type="file" class="form-control <?php echo isset($errors['image']) ? 'is-invalid' : ''; ?>" id="image" name="image" accept="image/*">
                            <div class="form-text">Dimensiones recomendadas: 800x400px. Tamaño máximo: 2MB.</div>
                            <?php if (isset($errors['image'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['image']; ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($currentImage)): ?>
                                <div class="mt-2">
                                    <div class="d-flex align-items-center">
                                        <div class="form-check me-3">
                                            <input class="form-check-input" type="checkbox" id="delete_image" name="delete_image" value="1">
                                            <label class="form-check-label" for="delete_image">
                                                Eliminar imagen actual
                                            </label>
                                        </div>
                                        <a href="<?php echo SITE_URL . '/' . $currentImage; ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-eye"></i> Ver imagen
                                        </a>
                                    </div>
                                    <div class="mt-2">
                                        <img src="<?php echo SITE_URL . '/' . $currentImage; ?>" alt="<?php echo htmlspecialchars($name); ?>" class="img-thumbnail" style="max-height: 150px;">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Información adicional -->
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Información adicional</h5>
                                <ul class="list-unstyled mb-0">
                                    <li><strong>ID:</strong> <?php echo $categoryId; ?></li>
                                    <li><strong>Noticias asociadas:</strong> <?php echo $newsCount; ?></li>
                                    <li><strong>Fecha de creación:</strong> <?php echo formatDate($category['created_at'], 'd/m/Y H:i'); ?></li>
                                    <li><strong>Última actualización:</strong> <?php echo formatDate($category['updated_at'], 'd/m/Y H:i'); ?></li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sincronizar campo color con su valor hexadecimal
    const colorInput = document.getElementById('color');
    const colorHexInput = document.getElementById('color_hex');
    
    colorInput.addEventListener('input', function() {
        colorHexInput.value = this.value;
    });
    
    // Generar slug automáticamente desde el nombre
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const originalSlug = '<?php echo addslashes($slug); ?>';
    
    nameInput.addEventListener('input', function() {
        if (slugInput.value === '' || slugInput.value === originalSlug) {
            slugInput.value = nameInput.value
                .toLowerCase()
                .replace(/[^\w\s-]/g, '') // Eliminar caracteres especiales
                .replace(/\s+/g, '-')     // Reemplazar espacios con guiones
                .replace(/--+/g, '-')     // Eliminar guiones duplicados
                .trim();                  // Eliminar espacios al inicio y final
        }
    });
    
    // Manejar checkbox de eliminación de imagen
    const deleteImageCheckbox = document.getElementById('delete_image');
    const imageInput = document.getElementById('image');
    
    if (deleteImageCheckbox) {
        deleteImageCheckbox.addEventListener('change', function() {
            if (this.checked) {
                // Si se marca eliminar imagen, deshabilitar la subida de una nueva
                imageInput.disabled = true;
            } else {
                // Si se desmarca, habilitar la subida de una nueva
                imageInput.disabled = false;
            }
        });
    }
});
</script>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>