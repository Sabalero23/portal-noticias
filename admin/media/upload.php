<?php
// Definir ruta base
define('BASE_PATH', dirname(__DIR__, 2));
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
$pageTitle = 'Subir Archivos - Biblioteca de Medios';
$currentMenu = 'media';

// Inicialización de variables
$errors = [];
$successCount = 0;
$failedCount = 0;

// Directorio donde se guardarán los archivos
$uploadDir = BASE_PATH . '/assets/img/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Configuración de subida
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/zip' => 'zip',
    'application/x-rar-compressed' => 'rar',
    'text/plain' => 'txt',
    'text/csv' => 'csv'
];
$maxSize = 10 * 1024 * 1024; // 10MB

// Procesar la subida de archivos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido. Por favor, recarga la página e intenta nuevamente.';
    } else {
        // Verificar si se subieron archivos
        if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
            $filesCount = count($_FILES['files']['name']);
            
            // Iterar sobre cada archivo
            for ($i = 0; $i < $filesCount; $i++) {
                // Verificar errores de subida
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
                    $failedCount++;
                    continue;
                }
                
                // Verificar tipo de archivo
                $fileType = $_FILES['files']['type'][$i];
                if (!array_key_exists($fileType, $allowedTypes)) {
                    $errors[] = 'El archivo "' . $_FILES['files']['name'][$i] . '" no es de un tipo permitido.';
                    $failedCount++;
                    continue;
                }
                
                // Verificar tamaño
                if ($_FILES['files']['size'][$i] > $maxSize) {
                    $errors[] = 'El archivo "' . $_FILES['files']['name'][$i] . '" excede el tamaño máximo permitido de ' . formatBytes($maxSize) . '.';
                    $failedCount++;
                    continue;
                }
                
                // Generar nombre único
                $fileExt = $allowedTypes[$fileType];
                $fileName = $_FILES['files']['name'][$i];
                $safeFileName = preg_replace('/[^a-zA-Z0-9_.-]/', '', pathinfo($fileName, PATHINFO_FILENAME));
                $uniqueName = $safeFileName . '_' . uniqid() . '.' . $fileExt;
                $filePath = 'assets/img/uploads/' . $uniqueName;
                $fullPath = $uploadDir . $uniqueName;
                
                // Mover archivo
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $fullPath)) {
                    // Obtener datos del usuario y archivo
                    $userId = $_SESSION['user']['id'];
                    $fileSize = $_FILES['files']['size'][$i];
                    
                    // Insertar registro en la base de datos
                    $db = Database::getInstance();
                    $result = $db->query(
                        "INSERT INTO media (file_name, file_path, file_type, file_size, user_id, uploaded_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())",
                        [$fileName, $filePath, $fileType, $fileSize, $userId]
                    );
                    
                    if ($result) {
                        $successCount++;
                        
                        // Registrar en log de actividad
                        logAdminAction('upload_file', 'Archivo subido: ' . $fileName, 'media', $db->lastInsertId());
                    } else {
                        // Error al insertar en la BD, eliminar archivo
                        unlink($fullPath);
                        $errors[] = 'Error al registrar el archivo "' . $fileName . '" en la base de datos.';
                        $failedCount++;
                    }
                } else {
                    $errors[] = 'Error al subir el archivo "' . $fileName . '". Por favor, intenta nuevamente.';
                    $failedCount++;
                }
            }
            
            // Mostrar mensaje de éxito si hay archivos subidos correctamente
            if ($successCount > 0) {
                setFlashMessage('success', 'Se ' . ($successCount === 1 ? 'ha subido' : 'han subido') . ' ' . $successCount . ' ' . ($successCount === 1 ? 'archivo' : 'archivos') . ' correctamente.');
                
                // Redirigir a la biblioteca de medios si todo fue exitoso
                if ($failedCount === 0) {
                    redirect('index.php');
                    exit;
                }
            }
        } else {
            $errors[] = 'No se seleccionaron archivos para subir.';
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
                    <h1 class="m-0">Subir Archivos</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Medios</a></li>
                        <li class="breadcrumb-item active">Subir Archivos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Errores -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Errores</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>
            
            <!-- Formulario de subida -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Seleccionar Archivos</h3>
                </div>
                
                <div class="card-body">
                    <form action="upload.php" method="post" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <div class="file-upload-container">
                                        <div id="dropZone" class="drop-zone">
                                            <div class="drop-zone-content text-center">
                                                <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-primary"></i>
                                                <h5>Arrastra archivos aquí o haz clic para seleccionar</h5>
                                                <p class="text-muted">Formatos permitidos: JPG, PNG, GIF, WEBP, SVG, PDF, DOC, DOCX, XLS, XLSX, ZIP, RAR, TXT, CSV</p>
                                                <p class="text-muted">Tamaño máximo: <?php echo formatBytes($maxSize); ?></p>
                                                <button type="button" id="browseButton" class="btn btn-primary">
                                                    <i class="fas fa-folder-open me-2"></i> Seleccionar Archivos
                                                </button>
                                            </div>
                                            <input type="file" name="files[]" id="fileInput" multiple class="d-none">
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="filePreviewContainer" class="file-preview-container d-none">
                                    <h5 class="mb-3">Archivos seleccionados: <span id="fileCount">0</span></h5>
                                    <div id="filePreview" class="row"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Volver a la Biblioteca
                            </a>
                            
                            <button type="submit" id="uploadButton" class="btn btn-success" disabled>
                                <i class="fas fa-upload me-2"></i> Subir Archivos
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Información adicional -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Consejos para subir archivos</h3>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-image"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Imágenes</span>
                                    <span class="info-box-number">JPG, PNG, GIF, WEBP, SVG</span>
                                    <span class="info-box-text text-muted">Resolución óptima: 1200x800 px</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-file-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Documentos</span>
                                    <span class="info-box-number">PDF, DOC, DOCX, XLS, XLSX, TXT, CSV</span>
                                    <span class="info-box-text text-muted">Mejor compatibilidad: PDF</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-archive"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Comprimidos</span>
                                    <span class="info-box-number">ZIP, RAR</span>
                                    <span class="info-box-text text-muted">Mejor compatibilidad: ZIP</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <h5><i class="fas fa-info-circle me-2"></i>Recomendaciones</h5>
                        <ul>
                            <li>Usa nombres de archivo descriptivos (sin caracteres especiales).</li>
                            <li>Para imágenes destinadas a noticias, utiliza una relación de aspecto 16:9 o 4:3.</li>
                            <li>Optimiza las imágenes antes de subirlas para mejorar el rendimiento del sitio.</li>
                            <li>Puedes subir varios archivos a la vez arrastrándolos al área indicada.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Progress Modal -->
<div class="modal fade" id="progressModal" tabindex="-1" aria-labelledby="progressModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="progressModalLabel">Subiendo archivos...</h5>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
                <p>Por favor, espera mientras se suben los archivos. No cierres esta ventana.</p>
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estilos adicionales -->
<style>
    .drop-zone {
        border: 2px dashed #ccc;
        border-radius: 5px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .drop-zone.dragover {
        border-color: #2196F3;
        background-color: rgba(33, 150, 243, 0.05);
    }
    
    .drop-zone-content {
        color: #666;
    }
    
    .file-preview-container {
        margin-top: 20px;
    }
    
    .file-preview-item {
        position: relative;
        margin-bottom: 15px;
    }
    
    .file-preview {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 10px;
        height: 100%;
        background-color: #f9f9f9;
    }
    
    .file-preview-image {
        width: 100%;
        height: 120px;
        object-fit: cover;
        border-radius: 4px;
        margin-bottom: 10px;
    }
    
    .file-preview-icon {
        font-size: 2.5rem;
        color: #6c757d;
        text-align: center;
        margin: 15px 0;
    }
    
    .file-preview-info {
        font-size: 0.85rem;
    }
    
    .file-preview-name {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
        margin-bottom: 5px;
    }
    
    .file-preview-size {
        color: #6c757d;
    }
    
    .file-preview-remove {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: rgba(0, 0, 0, 0.5);
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
    }
    
    .file-preview-remove:hover {
        background-color: rgba(220, 53, 69, 0.8);
    }
    
    .file-invalid {
        border-color: #dc3545;
    }
    
    .file-invalid .file-preview-info {
        color: #dc3545;
    }
</style>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Scripts adicionales -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const browseButton = document.getElementById('browseButton');
        const uploadButton = document.getElementById('uploadButton');
        const filePreviewContainer = document.getElementById('filePreviewContainer');
        const filePreview = document.getElementById('filePreview');
        const fileCount = document.getElementById('fileCount');
        const uploadForm = document.getElementById('uploadForm');
        
        // Verificar si el elemento progressModal existe
        const progressModalElement = document.getElementById('progressModal');
        const progressModal = progressModalElement ? new bootstrap.Modal(progressModalElement) : null;
        
        // Formatos permitidos
        const allowedTypes = {
            'image/jpeg': 'jpg',
            'image/png': 'png',
            'image/gif': 'gif',
            'image/webp': 'webp',
            'image/svg+xml': 'svg',
            'application/pdf': 'pdf',
            'application/msword': 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
            'application/vnd.ms-excel': 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
            'application/zip': 'zip',
            'application/x-rar-compressed': 'rar',
            'text/plain': 'txt',
            'text/csv': 'csv'
        };
        
        // Tamaño máximo (10MB)
        const maxSize = 10 * 1024 * 1024;
        
        // Eventos de arrastrar y soltar
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropZone.classList.add('dragover');
        }
        
        function unhighlight() {
            dropZone.classList.remove('dragover');
        }
        
        // Manejar archivos soltados
        dropZone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                handleFiles(files);
            }
        });
        
        // Eventos de clic para seleccionar archivos
        if (browseButton) {
            browseButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (fileInput) {
                    fileInput.click();
                }
            });
        }
        
        if (dropZone) {
            dropZone.addEventListener('click', function(e) {
                // Solo activar si el clic es directamente en el dropZone, no en sus hijos
                if (e.target === dropZone || e.target.closest('.drop-zone-content')) {
                    e.preventDefault();
                    if (fileInput) {
                        fileInput.click();
                    }
                }
            });
        }
        
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                e.stopPropagation();
                if (this.files && this.files.length > 0) {
                    handleFiles(this.files);
                }
            });
        }
        
        // Manejar archivos seleccionados
        function handleFiles(files) {
            if (!files || files.length === 0) return;
            
            // Mostrar contenedor de vista previa
            if (filePreviewContainer) {
                filePreviewContainer.classList.remove('d-none');
            }
            
            if (fileCount) {
                fileCount.textContent = files.length;
            }
            
            // Limpiar vista previa anterior
            if (filePreview) {
                filePreview.innerHTML = '';
            }
            
            // Recorrer archivos y generar vistas previas
            let validFiles = true;
            
            Array.from(files).forEach((file, index) => {
                const col = document.createElement('div');
                col.className = 'col-md-3 col-sm-4 col-6 file-preview-item';
                
                const preview = document.createElement('div');
                preview.className = 'file-preview';
                
                // Verificar tipo de archivo
                const isValidType = Object.keys(allowedTypes).includes(file.type);
                
                // Verificar tamaño
                const isValidSize = file.size <= maxSize;
                
                // Marcar como inválido si no cumple requisitos
                if (!isValidType || !isValidSize) {
                    preview.classList.add('file-invalid');
                    validFiles = false;
                }
                
                // Crear contenido de vista previa
                if (file.type.startsWith('image/')) {
                    // Vista previa de imagen
                    const img = document.createElement('img');
                    img.className = 'file-preview-image';
                    preview.appendChild(img);
                    
                    // Cargar imagen
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Icono para otros tipos de archivo
                    const icon = document.createElement('div');
                    icon.className = 'file-preview-icon';
                    
                    let iconClass = 'fas fa-file';
                    if (file.type === 'application/pdf') {
                        iconClass = 'fas fa-file-pdf';
                    } else if (file.type === 'application/msword' || file.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                        iconClass = 'fas fa-file-word';
                    } else if (file.type === 'application/vnd.ms-excel' || file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                        iconClass = 'fas fa-file-excel';
                    } else if (file.type === 'application/zip' || file.type === 'application/x-rar-compressed') {
                        iconClass = 'fas fa-file-archive';
                    } else if (file.type === 'text/plain' || file.type === 'text/csv') {
                        iconClass = 'fas fa-file-alt';
                    }
                    
                    icon.innerHTML = `<i class="${iconClass}"></i>`;
                    preview.appendChild(icon);
                }
                
                // Información del archivo
                const info = document.createElement('div');
                info.className = 'file-preview-info';
                
                const name = document.createElement('span');
                name.className = 'file-preview-name';
                name.title = file.name;
                name.textContent = file.name;
                info.appendChild(name);
                
                const size = document.createElement('span');
                size.className = 'file-preview-size';
                size.textContent = formatBytes(file.size);
                
                // Mostrar error si es inválido
                if (!isValidType) {
                    size.textContent += ' - Tipo de archivo no permitido';
                } else if (!isValidSize) {
                    size.textContent += ' - Excede el tamaño máximo';
                }
                
                info.appendChild(size);
                preview.appendChild(info);
                
                // Botón para eliminar
                const removeBtn = document.createElement('button');
                removeBtn.className = 'file-preview-remove';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.title = 'Eliminar';
                removeBtn.setAttribute('type', 'button');
                removeBtn.setAttribute('data-index', index);
                removeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const index = parseInt(this.getAttribute('data-index'));
                    removeFile(index);
                });
                preview.appendChild(removeBtn);
                
                col.appendChild(preview);
                if (filePreview) {
                    filePreview.appendChild(col);
                }
            });
            
            // Habilitar botón de subida si hay archivos válidos
            if (uploadButton) {
                uploadButton.disabled = !validFiles || files.length === 0;
            }
        }
        
        // Eliminar archivo de la lista
        function removeFile(index) {
            // Crear nuevo FileList sin el archivo eliminado
            const dt = new DataTransfer();
            const files = fileInput.files;
            
            for (let i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }
            
            fileInput.files = dt.files;
            
            // Actualizar vista previa
            handleFiles(fileInput.files);
            
            // Si no quedan archivos, ocultar contenedor
            if (fileInput.files.length === 0 && filePreviewContainer) {
                filePreviewContainer.classList.add('d-none');
                if (uploadButton) {
                    uploadButton.disabled = true;
                }
            }
        }
        
        // Formatear tamaño en bytes
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        
        // Mostrar modal de progreso al enviar formulario
        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                if (fileInput && fileInput.files.length > 0 && progressModal) {
                    progressModal.show();
                    
                    // Simular progreso (esto es solo visual, el progreso real dependería de un sistema de carga AJAX)
                    const progressBar = document.querySelector('.progress-bar');
                    if (progressBar) {
                        let progress = 0;
                        
                        const interval = setInterval(() => {
                            progress += 5;
                            if (progress > 90) {
                                clearInterval(interval);
                            }
                            progressBar.style.width = progress + '%';
                            progressBar.setAttribute('aria-valuenow', progress);
                        }, 300);
                    }
                }
            });
        }
    });
</script>