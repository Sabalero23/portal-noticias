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

// Obtener ID del anuncio
$adId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($adId <= 0) {
    setFlashMessage('error', 'ID de anuncio inválido');
    redirect('index.php');
}

// Obtener datos del anuncio
$db = Database::getInstance();
$ad = $db->fetch(
    "SELECT * FROM ads WHERE id = ?",
    [$adId]
);

if (!$ad) {
    setFlashMessage('error', 'Anuncio no encontrado');
    redirect('index.php');
}

// Configuración de la página
$pageTitle = 'Editar Anuncio - Panel de Administración';
$currentMenu = 'ads_edit';

// Obtener posiciones disponibles
$positions = [
    'header' => 'Encabezado',
    'left' => 'Columna izquierda',
    'right' => 'Columna derecha',
    'content' => 'Contenido',
    'footer' => 'Pie de página',
    'left_bottom' => 'Izquierda debajo',
    'right_bottom' => 'Derecha debajo',
    'left_extra' => 'Izquierda extra',
    'right_extra' => 'Derecha extra'
];


// Inicializar variables
$errors = [];
$success = false;
$formData = [
    'title' => $ad['title'],
    'description' => $ad['description'],
    'url' => $ad['url'],
    'position' => $ad['position'],
    'sector' => $ad['sector'],
    'start_date' => $ad['start_date'] ? date('Y-m-d', strtotime($ad['start_date'])) : '',
    'end_date' => $ad['end_date'] ? date('Y-m-d', strtotime($ad['end_date'])) : '',
    'status' => $ad['status'],
    'priority' => $ad['priority']
];

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Obtener y validar datos del formulario
        $formData = [
            'title' => isset($_POST['title']) ? sanitize($_POST['title']) : '',
            'description' => isset($_POST['description']) ? sanitize($_POST['description']) : '',
            'url' => isset($_POST['url']) ? sanitize($_POST['url']) : '',
            'position' => isset($_POST['position']) ? sanitize($_POST['position']) : '',
            'sector' => isset($_POST['sector']) ? sanitize($_POST['sector']) : '',
            'start_date' => isset($_POST['start_date']) ? sanitize($_POST['start_date']) : '',
            'end_date' => isset($_POST['end_date']) ? sanitize($_POST['end_date']) : '',
            'status' => isset($_POST['status']) ? sanitize($_POST['status']) : 'active',
            'priority' => isset($_POST['priority']) ? intval($_POST['priority']) : 0
        ];
        
        // Validación de campos
        if (empty($formData['title'])) {
            $errors[] = 'El título es obligatorio';
        } elseif (strlen($formData['title']) > 100) {
            $errors[] = 'El título no puede exceder los 100 caracteres';
        }
        
        if (empty($formData['url'])) {
            $errors[] = 'La URL es obligatoria';
        } elseif (!filter_var($formData['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'La URL no es válida';
        }
        
        if (empty($formData['position']) || !array_key_exists($formData['position'], $positions)) {
            $errors[] = 'La posición seleccionada no es válida';
        }
        
        // Validar fechas si se proporcionan
        if (!empty($formData['start_date']) && !strtotime($formData['start_date'])) {
            $errors[] = 'La fecha de inicio no es válida';
        }
        
        if (!empty($formData['end_date']) && !strtotime($formData['end_date'])) {
            $errors[] = 'La fecha de fin no es válida';
        }
        
        if (!empty($formData['start_date']) && !empty($formData['end_date'])) {
            $startTimestamp = strtotime($formData['start_date']);
            $endTimestamp = strtotime($formData['end_date']);
            
            if ($endTimestamp < $startTimestamp) {
                $errors[] = 'La fecha de fin no puede ser anterior a la fecha de inicio';
            }
        }
        
        if (!in_array($formData['status'], ['active', 'inactive'])) {
            $errors[] = 'El estado seleccionado no es válido';
        }
        
        if ($formData['priority'] < 0 || $formData['priority'] > 10) {
            $errors[] = 'La prioridad debe ser un número entre 0 y 10';
        }
        
        // Procesar imagen si se carga una nueva
        $imageUploaded = false;
        $imagePath = $ad['image']; // Mantener la imagen actual por defecto
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['image']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = 'La imagen debe ser en formato JPG, PNG o GIF';
            } else {
                // Validar tamaño (máximo 2MB)
                if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                    $errors[] = 'La imagen no puede superar los 2MB';
                } else {
                    $uploadDir = BASE_PATH . '/assets/img/ads/';
                    
                    // Crear directorio si no existe
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generar nombre único para el archivo
                    $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $fileName = 'ad_' . time() . '_' . uniqid() . '.' . $extension;
                    $uploadFile = $uploadDir . $fileName;
                    
                    // Mover el archivo
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                        $imageUploaded = true;
                        $imagePath = 'assets/img/ads/' . $fileName;
                        
                        // Eliminar imagen anterior si existe y no es la imagen por defecto
                        if (!empty($ad['image']) && file_exists(BASE_PATH . '/' . $ad['image'])) {
                            @unlink(BASE_PATH . '/' . $ad['image']);
                        }
                    } else {
                        $errors[] = 'Error al subir la imagen. Intenta nuevamente.';
                    }
                }
            }
        } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Error al subir el archivo, pero se intentó subir uno
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
                UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido por el formulario',
                UPLOAD_ERR_PARTIAL => 'El archivo fue subido parcialmente',
                UPLOAD_ERR_NO_TMP_DIR => 'No se encuentra el directorio temporal',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco',
                UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo'
            ];
            
            $errorCode = $_FILES['image']['error'];
            $errors[] = 'Error al subir la imagen: ' . ($errorMessages[$errorCode] ?? 'Error desconocido');
        }
        
        // Si no hay errores, actualizar en la base de datos
        if (empty($errors)) {
            // Preparar fechas para la base de datos
            $startDate = !empty($formData['start_date']) ? date('Y-m-d', strtotime($formData['start_date'])) : null;
            $endDate = !empty($formData['end_date']) ? date('Y-m-d', strtotime($formData['end_date'])) : null;
            
            // Actualizar anuncio
            $updated = $db->query(
                "UPDATE ads SET 
                 title = ?, 
                 description = ?, 
                 image = ?, 
                 url = ?, 
                 position = ?, 
                 sector = ?, 
                 start_date = ?, 
                 end_date = ?, 
                 status = ?, 
                 priority = ?, 
                 updated_at = NOW()
                 WHERE id = ?",
                [
                    $formData['title'],
                    $formData['description'],
                    $imagePath,
                    $formData['url'],
                    $formData['position'],
                    $formData['sector'],
                    $startDate,
                    $endDate,
                    $formData['status'],
                    $formData['priority'],
                    $adId
                ]
            );
            
            if ($updated) {
                // Registrar acción
                logAdminAction('Anuncio actualizado', 'Título: ' . $formData['title'], 'ads', $adId);
                
                // Mostrar mensaje de éxito
                setFlashMessage('success', 'Anuncio actualizado correctamente');
                
                // Redireccionar a la lista de anuncios
                redirect('index.php');
                exit;
            } else {
                $errors[] = 'Error al actualizar el anuncio. Intenta nuevamente.';
            }
        }
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
                    <h1 class="m-0">Editar Anuncio</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Anuncios</a></li>
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
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Formulario -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Información del Anuncio</h3>
                </div>
                <div class="card-body">
                    <form action="edit.php?id=<?php echo $adId; ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="row">
                            <!-- Título -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                                </div>
                            </div>
                            
                            <!-- URL -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="url" class="form-label">URL <span class="text-danger">*</span></label>
                                    <input type="url" class="form-control" id="url" name="url" value="<?php echo htmlspecialchars($formData['url']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Descripción -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                        </div>
                        
                        <!-- Imagen actual y carga de nueva imagen -->
                        <div class="mb-3">
                            <label for="image" class="form-label">Imagen</label>
                            <?php if (!empty($ad['image']) && file_exists(BASE_PATH . '/' . $ad['image'])): ?>
                                <div class="mb-2">
                                    <strong>Imagen actual:</strong>
                                    <div class="mt-2 mb-3">
                                        <img src="../<?php echo $ad['image']; ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="img-thumbnail" style="max-height: 200px;">
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="image" name="image" accept="image/jpeg, image/png, image/gif">
                            <div class="form-text">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB. Deja este campo vacío si no deseas cambiar la imagen.</div>
                        </div>
                        
                        <div id="imagePreview" class="mt-2 mb-3" style="display: none;">
                            <strong>Nueva imagen:</strong>
                            <div class="mt-2">
                                <img src="#" alt="Vista previa" class="img-thumbnail" style="max-height: 200px;">
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Posición -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="position" class="form-label">Posición <span class="text-danger">*</span></label>
                                    <select class="form-select" id="position" name="position" required>
                                        <?php foreach ($positions as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo $formData['position'] === $value ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Sector -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="sector" class="form-label">Sector/Categoría</label>
                                    <input type="text" class="form-control" id="sector" name="sector" value="<?php echo htmlspecialchars($formData['sector']); ?>">
                                </div>
                            </div>
                            
                            <!-- Prioridad -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Prioridad (0-10)</label>
                                    <input type="number" class="form-control" id="priority" name="priority" min="0" max="10" value="<?php echo $formData['priority']; ?>">
                                    <div class="form-text">Un valor más alto indica mayor prioridad.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Fecha de inicio -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Fecha de inicio</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $formData['start_date']; ?>">
                                </div>
                            </div>
                            
                            <!-- Fecha de fin -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">Fecha de fin</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $formData['end_date']; ?>">
                                </div>
                            </div>
                            
                            <!-- Estado -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Estado</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Activo</option>
                                        <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estadísticas -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Estadísticas</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <p class="mb-0"><strong>Impresiones:</strong> <?php echo number_format($ad['impressions']); ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <p class="mb-0"><strong>Clics:</strong> <?php echo number_format($ad['clicks']); ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <p class="mb-0">
                                                    <strong>CTR:</strong> 
                                                    <?php
                                                    $ctr = ($ad['impressions'] > 0) ? ($ad['clicks'] / $ad['impressions']) * 100 : 0;
                                                    echo number_format($ctr, 2) . '%';
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Actualizar Anuncio
                            </button>
                            <a href="index.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Script para vista previa de imagen -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = imagePreview.querySelector('img');
        
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                // Verificar tipo de archivo
                const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Por favor, selecciona una imagen en formato JPG, PNG o GIF.');
                    this.value = '';
                    imagePreview.style.display = 'none';
                    return;
                }
                
                // Verificar tamaño (máximo 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('La imagen no puede superar los 2MB.');
                    this.value = '';
                    imagePreview.style.display = 'none';
                    return;
                }
                
                // Mostrar vista previa
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.style.display = 'none';
            }
        });
        
        // Validación de fechas
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        endDateInput.addEventListener('change', function() {
            if (startDateInput.value && this.value) {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(this.value);
                
                if (endDate < startDate) {
                    alert('La fecha de fin no puede ser anterior a la fecha de inicio.');
                    this.value = '';
                }
            }
        });
    });
</script>