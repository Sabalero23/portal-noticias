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
$pageTitle = 'Añadir Nueva Noticia - Panel de Administración';
$currentMenu = 'news_add';

// Obtener datos para formulario
$db = Database::getInstance();

// Obtener categorías
$categories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");

// Obtener etiquetas
$tags = $db->fetchAll("SELECT id, name FROM tags ORDER BY name");

// Variables para formulario
$errors = [];
$success = false;
$newsData = [
    'title' => '',
    'slug' => '',
    'excerpt' => '',
    'content' => '',
    'category_id' => '',
    'status' => 'draft',
    'featured' => 0,
    'breaking' => 0,
    'allow_comments' => 1,
    'tags' => []
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors['general'] = 'Token de seguridad inválido';
    } else {
        // Recoger datos del formulario
        $newsData['title'] = isset($_POST['title']) ? sanitize($_POST['title']) : '';
        $newsData['slug'] = isset($_POST['slug']) ? sanitize($_POST['slug']) : '';
        $newsData['excerpt'] = isset($_POST['excerpt']) ? sanitize($_POST['excerpt']) : '';
        $newsData['content'] = isset($_POST['content']) ? $_POST['content'] : ''; // No sanitizar para HTML
        $newsData['category_id'] = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $newsData['status'] = isset($_POST['status']) ? sanitize($_POST['status']) : 'draft';
        $newsData['featured'] = isset($_POST['featured']) ? 1 : 0;
        $newsData['breaking'] = isset($_POST['breaking']) ? 1 : 0;
        $newsData['allow_comments'] = isset($_POST['allow_comments']) ? 1 : 0;
        $newsData['tags'] = isset($_POST['tags']) && is_array($_POST['tags']) ? $_POST['tags'] : [];
        
        // Validación
        $validation = validateForm([
            'title' => 'required|min:3|max:255',
            'excerpt' => 'required|min:10|max:500',
            'content' => 'required|min:20',
            'category_id' => 'required|numeric'
        ], $newsData);
        
        $errors = $validation['errors'];
        
        // Verificar si el slug es único
        if (empty($newsData['slug'])) {
            $newsData['slug'] = generateSlug($newsData['title']);
        }
        
        // Comprobar si el slug ya existe
        $existingSlug = $db->fetch(
            "SELECT id FROM news WHERE slug = ?",
            [$newsData['slug']]
        );
        
        if ($existingSlug) {
            $errors['slug'] = 'Este slug ya está en uso. Por favor, elige otro.';
        }
        
        // Validar la categoría
        if ($newsData['category_id'] <= 0) {
            $errors['category_id'] = 'Debes seleccionar una categoría';
        } else {
            $categoryExists = $db->fetch(
                "SELECT id FROM categories WHERE id = ?",
                [$newsData['category_id']]
            );
            
            if (!$categoryExists) {
                $errors['category_id'] = 'La categoría seleccionada no existe';
            }
        }
        
        // Procesar imagen destacada
        $imagePath = '';
        $thumbnailPath = '';
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageResult = processImageUpload(
                $_FILES['image'],
                BASE_PATH . '/assets/img/news',
                ['image/jpeg', 'image/png', 'image/webp'],
                5 * 1024 * 1024, // 5MB
                1200, // max width
                800  // max height
            );
            
            if (!$imageResult['success']) {
                $errors['image'] = $imageResult['message'];
            } else {
                $imagePath = 'assets/img/news/' . $imageResult['filename'];
                
                // Generar miniatura
                $thumbnailFilename = 'thumb_' . $imageResult['filename'];
                $thumbnailPath = 'assets/img/news/' . $thumbnailFilename;
                
                // Crear miniatura (implementar función para crear thumbnail)
                // Esta es solo una representación básica
                $thumbnailCreated = createThumbnail(
                    BASE_PATH . '/' . $imagePath,
                    BASE_PATH . '/' . $thumbnailPath,
                    400, // ancho thumbnail
                    300  // alto thumbnail
                );
                
                if (!$thumbnailCreated) {
                    $thumbnailPath = $imagePath; // Usar imagen original si falla
                }
            }
        } else {
            $errors['image'] = 'Debes subir una imagen destacada';
        }
        
        // Si no hay errores, guardar noticia
if (empty($errors)) {
    try {
        // Iniciar transacción
        $transaction = new Transaction();
        $transaction->begin();
        
        // Determinar si se publica ahora o es borrador
        $publishedAt = null;
        if ($newsData['status'] === 'published') {
            $publishedAt = date('Y-m-d H:i:s');
        }
        
        // Insertar noticia
        $insertNews = $db->query(
            "INSERT INTO news (
                title, slug, excerpt, content, image, thumbnail, 
                category_id, author_id, status, featured, breaking,
                allow_comments, published_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $newsData['title'],
                $newsData['slug'],
                $newsData['excerpt'],
                $newsData['content'],
                $imagePath,
                $thumbnailPath,
                $newsData['category_id'],
                $_SESSION['user']['id'],
                $newsData['status'],
                $newsData['featured'],
                $newsData['breaking'],
                $newsData['allow_comments'],
                $publishedAt
            ]
        );
        
        if (!$insertNews) {
            throw new Exception('Error al guardar la noticia');
        }
        
        $newsId = $db->lastInsertId();
        
        // Guardar relaciones con etiquetas
        if (!empty($newsData['tags'])) {
            foreach ($newsData['tags'] as $tagId) {
                // Verificar que la etiqueta existe
                $tagExists = $db->fetch(
                    "SELECT id FROM tags WHERE id = ?",
                    [$tagId]
                );
                
                if ($tagExists) {
                    $db->query(
                        "INSERT INTO news_tags (news_id, tag_id) VALUES (?, ?)",
                        [$newsId, $tagId]
                    );
                }
            }
        }
        
        // Confirmar transacción
        $transaction->commit();
        
        // Registrar acción
        logAdminAction(
            'create_news',
            'Creó la noticia: ' . $newsData['title'],
            'news',
            $newsId
        );
        
        // Éxito
        $success = true;
        
        // Redireccionar a la lista de noticias o a editar
        setFlashMessage('success', 'Noticia creada correctamente');
        redirect('index.php');
        
    } catch (Exception $e) {
        // Revertir transacción
        $transaction->rollback();
        
        // Registrar error
        $errors['general'] = 'Error al crear la noticia: ' . $e->getMessage();
        
        // Eliminar imagen si se subió (limpieza)
        if (!empty($imagePath) && file_exists(BASE_PATH . '/' . $imagePath)) {
            @unlink(BASE_PATH . '/' . $imagePath);
        }
        
        if (!empty($thumbnailPath) && file_exists(BASE_PATH . '/' . $thumbnailPath)) {
            @unlink(BASE_PATH . '/' . $thumbnailPath);
        }
    }
}
    }
}

// Incluir Trumbowyg (editor ligero)
$extraCSS = [
    'https://cdn.jsdelivr.net/npm/trumbowyg@2.27.3/dist/ui/trumbowyg.min.css'
];

$extraJS = [
    'https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js',
    'https://cdn.jsdelivr.net/npm/trumbowyg@2.27.3/dist/trumbowyg.min.js',
    'https://cdn.jsdelivr.net/npm/trumbowyg@2.27.3/dist/langs/es.min.js'
];

// Script en línea para Trumbowyg
$inlineJS = "
    $(document).ready(function() {
        $('#content').trumbowyg({
            lang: 'es',
            btns: [
                ['viewHTML'],
                ['undo', 'redo'],
                ['formatting'],
                ['strong', 'em', 'del'],
                ['superscript', 'subscript'],
                ['link'],
                ['insertImage'],
                ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
                ['unorderedList', 'orderedList'],
                ['horizontalRule'],
                ['removeformat'],
                ['fullscreen']
            ],
            autogrow: true,
            imageWidthModalEdit: true
        });
    });
    
    // Generar slug automáticamente basado en el título
    document.getElementById('title').addEventListener('blur', function() {
        const slugField = document.getElementById('slug');
        // Solo generar slug si el campo está vacío
        if (slugField.value === '') {
            const title = this.value.trim();
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9\\s-]/g, '') // Eliminar caracteres especiales
                .replace(/\\s+/g, '-')        // Reemplazar espacios por guiones
                .replace(/-+/g, '-');        // Eliminar guiones duplicados
            
            slugField.value = slug;
        }
    });
    
    // Preview de imagen
    document.getElementById('image').addEventListener('change', function() {
        const file = this.files[0];
        const preview = document.getElementById('image-preview');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src=\"' + e.target.result + '\" class=\"img-preview\" alt=\"Vista previa\">';
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
        }
    });
";


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
                    <h1 class="m-0">Añadir Nueva Noticia</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Noticias</a></li>
                        <li class="breadcrumb-item active">Añadir Nueva</li>
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
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
                    <p><?php echo $errors['general']; ?></p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Formulario de noticia -->
            <form action="add.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <div class="row">
                    <!-- Columna principal -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <!-- Título -->
                                <div class="mb-3">
                                    <label for="title" class="form-label">Título <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" id="title" name="title" value="<?php echo htmlspecialchars($newsData['title']); ?>" required>
                                    <?php echo showErrorMessage('title', $errors); ?>
                                </div>
                                
                                <!-- Slug -->
                                <div class="mb-3">
                                    <label for="slug" class="form-label">Slug</label>
                                    <input type="text" class="form-control <?php echo isset($errors['slug']) ? 'is-invalid' : ''; ?>" id="slug" name="slug" value="<?php echo htmlspecialchars($newsData['slug']); ?>">
                                    <div class="form-text">Dejar en blanco para generar automáticamente. Use solo letras, números y guiones.</div>
                                    <?php echo showErrorMessage('slug', $errors); ?>
                                </div>
                                
                                <!-- Extracto -->
                                <div class="mb-3">
                                    <label for="excerpt" class="form-label">Extracto <span class="text-danger">*</span></label>
                                    <textarea class="form-control <?php echo isset($errors['excerpt']) ? 'is-invalid' : ''; ?>" id="excerpt" name="excerpt" rows="3" required><?php echo htmlspecialchars($newsData['excerpt']); ?></textarea>
                                    <div class="form-text">Breve resumen de la noticia (máx. 500 caracteres)</div>
                                    <?php echo showErrorMessage('excerpt', $errors); ?>
                                </div>
                                
                                <!-- Contenido -->
                                <div class="mb-3">
                                    <label for="content" class="form-label">Contenido <span class="text-danger">*</span></label>
                                    <textarea class="form-control <?php echo isset($errors['content']) ? 'is-invalid' : ''; ?>" id="content" name="content" rows="10"><?php echo htmlspecialchars($newsData['content']); ?></textarea>
                                    <?php echo showErrorMessage('content', $errors); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Columna lateral -->
                    <div class="col-md-4">
                        <!-- Opciones de publicación -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Publicación</h5>
                            </div>
                            <div class="card-body">
                                <!-- Estado -->
                                <div class="mb-3">
                                    <label for="status" class="form-label">Estado</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="draft" <?php echo $newsData['status'] === 'draft' ? 'selected' : ''; ?>>Borrador</option>
                                        <option value="pending" <?php echo $newsData['status'] === 'pending' ? 'selected' : ''; ?>>Pendiente de revisión</option>
                                        <?php if (hasRole(['admin', 'editor'])): ?>
                                            <option value="published" <?php echo $newsData['status'] === 'published' ? 'selected' : ''; ?>>Publicada</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <!-- Opciones adicionales -->
                                <div class="mb-3">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" <?php echo $newsData['featured'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="featured">
                                            Destacada
                                        </label>
                                        <div class="form-text">La noticia aparecerá en el slider de destacados</div>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="breaking" name="breaking" value="1" <?php echo $newsData['breaking'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="breaking">
                                            Última hora
                                        </label>
                                        <div class="form-text">La noticia se mostrará como "última hora"</div>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="allow_comments" name="allow_comments" value="1" <?php echo $newsData['allow_comments'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_comments">
                                            Permitir comentarios
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Botones de acción -->
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Guardar Noticia
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Categoría -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Categoría</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <select class="form-select <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" id="category_id" name="category_id" required>
                                        <option value="">Seleccionar categoría</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo $newsData['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php echo showErrorMessage('category_id', $errors); ?>
                                </div>
                                <div class="text-end">
                                    <a href="../categories/add.php" target="_blank" class="btn btn-sm btn-info">
                                        <i class="fas fa-plus-circle me-1"></i> Añadir Categoría
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Etiquetas -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Etiquetas</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <select class="form-select" id="tags" name="tags[]" multiple>
                                        <?php foreach ($tags as $tag): ?>
                                            <option value="<?php echo $tag['id']; ?>" <?php echo in_array($tag['id'], $newsData['tags']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tag['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Selecciona varias etiquetas manteniendo presionada la tecla Ctrl</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Imagen destacada -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Imagen Destacada</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <input type="file" class="form-control <?php echo isset($errors['image']) ? 'is-invalid' : ''; ?>" id="image" name="image" accept="image/jpeg, image/png, image/webp">
                                    <div class="form-text">Formatos permitidos: JPG, PNG, WebP. Tamaño máximo: 5MB.</div>
                                    <?php echo showErrorMessage('image', $errors); ?>
                                </div>
                                <div id="image-preview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>