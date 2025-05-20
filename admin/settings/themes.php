<?php
// Definir ruta base
define('BASE_PATH', dirname(dirname(__DIR__)));
define('ADMIN_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Verificar si el usuario está logueado y tiene permisos de administrador
$auth = new Auth();
$auth->requirePermission(['admin'], 'dashboard.php');

// Título de la página
$pageTitle = 'Configuración de Temas - Panel de Administración';
$currentMenu = 'settings_themes';

// Obtener tema actual
$db = Database::getInstance();
$activeTheme = getSetting('active_theme', 'default');

// Buscar temas disponibles
$themesPath = BASE_PATH . '/assets/themes';
$availableThemes = [];

if (is_dir($themesPath)) {
    $themes = scandir($themesPath);
    foreach ($themes as $theme) {
        // Ignorar . y .. y asegurarse de que sea un directorio válido
        if ($theme != '.' && $theme != '..' && is_dir($themesPath . '/' . $theme)) {
            // Verificar si existe el archivo styles.css y responsive.css
            if (file_exists($themesPath . '/' . $theme . '/styles.css') && 
                file_exists($themesPath . '/' . $theme . '/responsive.css')) {
                
                // Leer información del tema si existe
                $themeInfo = [
                    'id' => $theme,
                    'name' => ucfirst($theme),
                    'description' => 'Tema ' . ucfirst($theme),
                    'version' => '1.0',
                    'author' => 'Admin',
                    'screenshot' => ''
                ];
                
                // Si existe un archivo theme.json, leer la información
                if (file_exists($themesPath . '/' . $theme . '/theme.json')) {
                    $themeData = json_decode(file_get_contents($themesPath . '/' . $theme . '/theme.json'), true);
                    if ($themeData) {
                        $themeInfo = array_merge($themeInfo, $themeData);
                    }
                }
                
                // Si existe una captura de pantalla
                if (file_exists($themesPath . '/' . $theme . '/screenshot.jpg')) {
                    $themeInfo['screenshot'] = 'assets/themes/' . $theme . '/screenshot.jpg';
                } elseif (file_exists($themesPath . '/' . $theme . '/screenshot.png')) {
                    $themeInfo['screenshot'] = 'assets/themes/' . $theme . '/screenshot.png';
                }
                
                $availableThemes[] = $themeInfo;
            }
        }
    }
}

// Procesar formulario
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Obtener el tema seleccionado
        $selectedTheme = isset($_POST['active_theme']) ? sanitize($_POST['active_theme']) : 'default';
        
        // Validar que el tema existe
        $themeExists = false;
        foreach ($availableThemes as $theme) {
            if ($theme['id'] === $selectedTheme) {
                $themeExists = true;
                break;
            }
        }
        
        if (!$themeExists) {
            $errors[] = 'El tema seleccionado no existe';
        }
        
        // Si no hay errores, actualizar configuración
        if (empty($errors)) {
            try {
                // Actualizar configuración
                if (isset($selectedTheme)) {
                    // Verificar si la configuración existe
                    $setting = $db->fetch(
                        "SELECT id FROM settings WHERE setting_key = ?",
                        ['active_theme']
                    );
                    
                    if ($setting) {
                        // Actualizar configuración existente
                        $db->query(
                            "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
                            [$selectedTheme, 'active_theme']
                        );
                    } else {
                        // Insertar nueva configuración
                        $db->query(
                            "INSERT INTO settings (setting_key, setting_value, setting_group, created_at, updated_at) 
                             VALUES (?, ?, 'site', NOW(), NOW())",
                            ['active_theme', $selectedTheme]
                        );
                    }
                }
                
                // Registrar la acción en el log
                logAction('Actualizar tema', 'Tema actualizado a ' . $selectedTheme, $_SESSION['user']['id'] ?? 0);
                
                // Actualizar tema activo
                $activeTheme = $selectedTheme;
                
                // Marcar como exitoso
                $success = true;
                
                // Mostrar mensaje de éxito
                setFlashMessage('success', 'Tema actualizado correctamente');
                
                // Recargar la página para mostrar el mensaje
                redirect('themes.php');
                exit;
                
            } catch (Exception $e) {
                // Registrar error
                $errors[] = 'Error al actualizar el tema: ' . $e->getMessage();
                error_log('Error al actualizar tema: ' . $e->getMessage());
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
                    <h1 class="m-0">Configuración de Temas</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/settings/index.php">Configuración</a></li>
                        <li class="breadcrumb-item active">Temas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Alertas de error y éxito -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>Tema actualizado correctamente
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Temas disponibles -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Selección de Tema</h3>
                </div>
                <div class="card-body">
                    <form action="themes.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="row">
                            <?php if (empty($availableThemes)): ?>
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>No se encontraron temas disponibles en la carpeta assets/themes
                                    </div>
                                    <p>Para crear un nuevo tema:</p>
                                    <ol>
                                        <li>Crea una carpeta con el nombre del tema en assets/themes</li>
                                        <li>Dentro de la carpeta, crea los archivos styles.css y responsive.css</li>
                                        <li>Opcionalmente, puedes incluir un archivo theme.json con la información del tema</li>
                                        <li>También puedes incluir una captura de pantalla llamada screenshot.jpg o screenshot.png</li>
                                    </ol>
                                </div>
                            <?php else: ?>
                                <?php foreach ($availableThemes as $theme): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 <?php echo ($activeTheme === $theme['id']) ? 'border-primary' : ''; ?>">
                                        <div class="card-header <?php echo ($activeTheme === $theme['id']) ? 'bg-primary text-white' : ''; ?>">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="active_theme" id="theme_<?php echo $theme['id']; ?>" value="<?php echo $theme['id']; ?>" <?php echo ($activeTheme === $theme['id']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="theme_<?php echo $theme['id']; ?>">
                                                    <?php echo $theme['name']; ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php if (!empty($theme['screenshot'])): ?>
                                        <div class="card-img-top position-relative">
                                            <img src="<?php echo '../../' . $theme['screenshot']; ?>" class="img-fluid w-100" alt="<?php echo $theme['name']; ?>" style="height: 200px; object-fit: cover;">
                                            <?php if ($activeTheme === $theme['id']): ?>
                                            <div class="position-absolute top-0 end-0 p-2">
                                                <span class="badge bg-success">Activo</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title mb-2">
                                                <?php echo $theme['name']; ?>
                                                <?php if (isset($theme['version'])): ?>
                                                <small class="text-muted">v<?php echo $theme['version']; ?></small>
                                                <?php endif; ?>
                                            </h5>
                                                <br>
                                            <p class="card-text"><?php echo $theme['description']; ?></p>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <small class="text-muted">
                                                <?php if (isset($theme['author'])): ?>
                                                <i class="fas fa-user me-1"></i> <?php echo $theme['author']; ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($availableThemes)): ?>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar Cambios
                            </button>
                            <a href="index.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-arrow-left me-1"></i> Volver
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Instrucciones para crear un nuevo tema -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Cómo crear un nuevo tema</h3>
                </div>
                <div class="card-body">
                    <h5>Estructura de archivos</h5>
                    <p>Para crear un nuevo tema, debes seguir esta estructura de archivos:</p>
                    <pre><code>assets/
  themes/
    mi-nuevo-tema/
      styles.css           (obligatorio)
      responsive.css       (obligatorio)
      single-news.css      (opcional - estilos para página de noticia individual)
      theme.json           (opcional - información del tema)
      screenshot.jpg       (opcional - captura de pantalla del tema)</code></pre>
                    
                    <h5 class="mt-4">Archivo theme.json</h5>
                    <p>El archivo theme.json debe contener la siguiente información en formato JSON:</p>
                    <pre><code>{
  "name": "Nombre del tema",
  "description": "Descripción detallada del tema",
  "version": "1.0",
  "author": "Tu Nombre",
  "authorUrl": "https://tudominio.com"
}</code></pre>
                    
                    <h5 class="mt-4">Consejos para crear un tema</h5>
                    <ul>
                        <li>Usa los archivos styles.css y responsive.css del tema "default" como base</li>
                        <li>Modifica colores, fuentes, espaciados y otros elementos visuales</li>
                        <li>Utiliza el inspector del navegador para identificar las clases CSS que necesitas modificar</li>
                        <li>Prueba el tema en diferentes dispositivos y resoluciones</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>