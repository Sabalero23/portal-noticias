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

// Verificar ID de noticia
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de noticia inválido');
    redirect('index.php');
}

$newsId = intval($_GET['id']);
$db = Database::getInstance();

// Obtener datos de la noticia
$news = $db->fetch(
    "SELECT n.*, u.name as author_name 
     FROM news n
     LEFT JOIN users u ON n.author_id = u.id
     WHERE n.id = ?",
    [$newsId]
);

// Verificar si existe la noticia
if (!$news) {
    setFlashMessage('error', 'Noticia no encontrada');
    redirect('index.php');
}

// Verificar permisos (solo el autor o administradores/editores pueden editar)
if (!hasRole(['admin', 'editor']) && $_SESSION['user']['id'] != $news['author_id']) {
    setFlashMessage('error', 'No tienes permisos para editar esta noticia');
    redirect('index.php');
}

// Los autores solo pueden editar sus propias noticias si no están publicadas
if (hasRole(['author']) && !hasRole(['admin', 'editor']) && $news['status'] === 'published') {
    setFlashMessage('error', 'No puedes editar una noticia publicada. Contáctate con un editor o administrador.');
    redirect('index.php');
}

// Obtener etiquetas de la noticia
$newsTags = [];
$tagsResult = $db->fetchAll(
    "SELECT tag_id FROM news_tags WHERE news_id = ?",
    [$newsId]
);

if ($tagsResult) {
    foreach ($tagsResult as $tag) {
        $newsTags[] = $tag['tag_id'];
    }
}

// Inicializar variables para formulario
$errors = [];
$success = false;
$newsData = [
    'title' => $news['title'],
    'slug' => $news['slug'],
    'excerpt' => $news['excerpt'],
    'content' => $news['content'],
    'category_id' => $news['category_id'],
    'status' => $news['status'],
    'featured' => $news['featured'],
    'breaking' => $news['breaking'],
    'allow_comments' => $news['allow_comments'],
    'tags' => $newsTags
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
        
        // Verificar si el slug es único (si se cambió)
        if (empty($newsData['slug'])) {
            $newsData['slug'] = generateSlug($newsData['title']);
        } elseif ($newsData['slug'] !== $news['slug']) {
            // Comprobar si el nuevo slug ya existe en otra noticia
            $existingSlug = $db->fetch(
                "SELECT id FROM news WHERE slug = ? AND id != ?",
                [$newsData['slug'], $newsId]
            );
            
            if ($existingSlug) {
                $errors['slug'] = 'Este slug ya está en uso. Por favor, elige otro.';
            }
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
        $imagePath = $news['image']; // Mantener imagen actual por defecto
        $thumbnailPath = $news['thumbnail']; // Mantener thumbnail actual por defecto
        $deleteImage = isset($_POST['delete_image']) && $_POST['delete_image'] === '1';
        
        if ($deleteImage) {
            // Eliminar imagen si se solicitó
            $imagePath = '';
            $thumbnailPath = '';
            
            // Eliminar archivos físicos
            if (!empty($news['image']) && file_exists(BASE_PATH . '/' . $news['image'])) {
                @unlink(BASE_PATH . '/' . $news['image']);
            }
            
            if (!empty($news['thumbnail']) && $news['thumbnail'] !== $news['image'] && file_exists(BASE_PATH . '/' . $news['thumbnail'])) {
                @unlink(BASE_PATH . '/' . $news['thumbnail']);
            }
        } else if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Procesar nueva imagen
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
                // Eliminar imágenes antiguas si existen
                if (!empty($news['image']) && file_exists(BASE_PATH . '/' . $news['image'])) {
                    @unlink(BASE_PATH . '/' . $news['image']);
                }
                
                if (!empty($news['thumbnail']) && $news['thumbnail'] !== $news['image'] && file_exists(BASE_PATH . '/' . $news['thumbnail'])) {
                    @unlink(BASE_PATH . '/' . $news['thumbnail']);
                }
                
                $imagePath = 'assets/img/news/' . $imageResult['filename'];
                
                // Generar miniatura
                $thumbnailFilename = 'thumb_' . $imageResult['filename'];
                $thumbnailPath = 'assets/img/news/' . $thumbnailFilename;
                
                // Crear miniatura
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
        }
        
        // Si no hay errores, actualizar noticia
        if (empty($errors)) {
            try {
                // Iniciar transacción
                $transaction = new Transaction();
                $transaction->begin();
                
                // Determinar si se publica ahora o es borrador
                $publishedAt = $news['published_at'];
                
                if ($newsData['status'] === 'published' && ($news['status'] !== 'published' || $publishedAt === null)) {
                    $publishedAt = date('Y-m-d H:i:s');
                }
                
                // Actualizar noticia
                $updateNews = $db->query(
                    "UPDATE news SET 
                        title = ?, slug = ?, excerpt = ?, content = ?,
                        image = ?, thumbnail = ?, category_id = ?,
                        status = ?, featured = ?, breaking = ?,
                        allow_comments = ?, published_at = ?, updated_at = NOW()
                     WHERE id = ?",
                    [
                        $newsData['title'],
                        $newsData['slug'],
                        $newsData['excerpt'],
                        $newsData['content'],
                        $imagePath,
                        $thumbnailPath,
                        $newsData['category_id'],
                        $newsData['status'],
                        $newsData['featured'],
                        $newsData['breaking'],
                        $newsData['allow_comments'],
                        $publishedAt,
                        $newsId
                    ]
                );
                
                if (!$updateNews) {
                    throw new Exception('Error al actualizar la noticia');
                }
                
                // Actualizar relaciones con etiquetas
                $db->query("DELETE FROM news_tags WHERE news_id = ?", [$newsId]);
                
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
                    'update_news',
                    'Actualizó la noticia: ' . $newsData['title'],
                    'news',
                    $newsId
                );
                
                // Éxito
                $success = true;
                
                // Redireccionar o mostrar mensaje de éxito
                setFlashMessage('success', 'Noticia actualizada correctamente');
                redirect('index.php');
                
            } catch (Exception $e) {
                // Revertir transacción
                $transaction->rollback();
                
                // Registrar error
                $errors['general'] = 'Error al actualizar la noticia: ' . $e->getMessage();
            }
        }
    }
}

// Obtener datos para formulario
// Obtener categorías
$categories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");

// Obtener etiquetas
$tags = $db->fetchAll("SELECT id, name FROM tags ORDER BY name");

// Título de la página
$pageTitle = 'Editar Noticia - Panel de Administración';
$currentMenu = 'news';

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
                    <h1 class="m-0">Editar Noticia</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Noticias</a></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Información de la noticia -->
    <div class="content-header bg-light py-2">
        <div class="container-fluid">
            <h4 class="text-primary mb-2"><?php echo htmlspecialchars($news['title']); ?></h4>
            <p class="text-muted mb-0">
                <strong><?php echo htmlspecialchars($news['author_name']); ?></strong> | 
                <span>Creada: <?php echo formatDate($news['created_at'], 'd/m/Y H:i'); ?></span>
                <?php if ($news['published_at']): ?>
                    | <span>Publicada: <?php echo formatDate($news['published_at'], 'd/m/Y H:i'); ?></span>
                <?php endif; ?>
                | <span>Actualizada: <?php echo formatDate($news['updated_at'], 'd/m/Y H:i'); ?></span>
            </p>
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
            <form action="edit.php?id=<?php echo $newsId; ?>" method="post" enctype="multipart/form-data">
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
                                    <?php if (isset($errors['title'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['title']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Slug -->
                                <div class="mb-3">
                                    <label for="slug" class="form-label">Slug</label>
                                    <input type="text" class="form-control <?php echo isset($errors['slug']) ? 'is-invalid' : ''; ?>" id="slug" name="slug" value="<?php echo htmlspecialchars($newsData['slug']); ?>">
                                    <div class="form-text">Dejar en blanco para generar automáticamente. Use solo letras, números y guiones.</div>
                                    <?php if (isset($errors['slug'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['slug']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Extracto -->
                                <div class="mb-3">
                                    <label for="excerpt" class="form-label">Extracto <span class="text-danger">*</span></label>
                                    <textarea class="form-control <?php echo isset($errors['excerpt']) ? 'is-invalid' : ''; ?>" id="excerpt" name="excerpt" rows="3" required><?php echo htmlspecialchars($newsData['excerpt']); ?></textarea>
                                    <div class="form-text">Breve resumen de la noticia (máx. 500 caracteres)</div>
                                    <?php if (isset($errors['excerpt'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['excerpt']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Contenido -->
                                <div class="mb-3">
                                    <label for="content" class="form-label">Contenido <span class="text-danger">*</span></label>
                                    <textarea class="form-control <?php echo isset($errors['content']) ? 'is-invalid' : ''; ?>" id="content" name="content" rows="10"><?php echo htmlspecialchars($newsData['content']); ?></textarea>
                                    <?php if (isset($errors['content'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['content']; ?></div>
                                    <?php endif; ?>
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
                                        <?php if (hasRole(['admin', 'editor']) || $news['status'] === 'published'): ?>
                                            <option value="published" <?php echo $newsData['status'] === 'published' ? 'selected' : ''; ?>>Publicada</option>
                                        <?php endif; ?>
                                        <option value="trash" <?php echo $newsData['status'] === 'trash' ? 'selected' : ''; ?>>Papelera</option>
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
                                        <i class="fas fa-save me-1"></i> Guardar Cambios
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
                                    <?php if (isset($errors['category_id'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['category_id']; ?></div>
                                    <?php endif; ?>
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
                                    <?php if (isset($errors['image'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['image']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($news['image'])): ?>
                                    <div class="current-image mt-3">
                                        <div class="text-center mb-2">
                                            <img src="<?php echo SITE_URL . '/' . $news['image']; ?>" alt="Imagen actual" class="img-fluid img-thumbnail" style="max-height: 200px;">
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="delete_image" name="delete_image" value="1">
                                            <label class="form-check-label" for="delete_image">
                                                Eliminar imagen actual
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
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