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
$pageTitle = 'Configuración del Sitio - Panel de Administración';
$currentMenu = 'settings_site';

// Obtener configuraciones actuales
$db = Database::getInstance();
$settings = $db->fetchAll("SELECT * FROM settings WHERE setting_group IN ('general', 'site') ORDER BY id");

// Convertir a array asociativo
$currentSettings = [];
foreach ($settings as $setting) {
    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
}

// Procesar formulario
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Obtener datos del formulario
        $siteUrl = isset($_POST['site_url']) ? rtrim(sanitize($_POST['site_url']), '/') : '';
        $dateFormat = isset($_POST['date_format']) ? sanitize($_POST['date_format']) : 'd/m/Y';
        $timeFormat = isset($_POST['time_format']) ? sanitize($_POST['time_format']) : 'H:i';
        $timezone = isset($_POST['timezone']) ? sanitize($_POST['timezone']) : 'America/Argentina/Buenos_Aires';
        $maintenanceMode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $maintenanceMessage = isset($_POST['maintenance_message']) ? sanitize($_POST['maintenance_message']) : '';
        $footerText = isset($_POST['footer_text']) ? sanitize($_POST['footer_text']) : '';
        $enablePwa = isset($_POST['enable_pwa']) ? 1 : 0;
        $analyticsId = isset($_POST['analytics_id']) ? sanitize($_POST['analytics_id']) : '';
        
        // Validar datos
        if (empty($siteUrl)) {
            $errors[] = 'La URL del sitio es obligatoria';
        } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'La URL del sitio no es válida';
        }
        
        // Procesar subida de logo
        $logoPath = $currentSettings['logo'] ?? '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
            $fileType = $_FILES['logo']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = 'El archivo del logo debe ser una imagen (JPG, PNG, GIF o SVG)';
            } else {
                // Validar tamaño (máximo 2MB)
                if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                    $errors[] = 'El logo no puede superar los 2MB';
                } else {
                    // Procesar subida
                    $uploadResult = processImageUpload(
                        $_FILES['logo'],
                        BASE_PATH . '/assets/img',
                        $allowedTypes,
                        2 * 1024 * 1024
                    );
                    
                    if ($uploadResult['success']) {
                        $logoPath = 'assets/img/' . $uploadResult['filename'];
                    } else {
                        $errors[] = 'Error al subir el logo: ' . $uploadResult['message'];
                    }
                }
            }
        }
        
        // Procesar subida de favicon
        $faviconPath = $currentSettings['favicon'] ?? '';
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            // Validar tipo de archivo
            $allowedTypes = ['image/x-icon', 'image/png', 'image/jpeg', 'image/gif'];
            $fileType = $_FILES['favicon']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = 'El archivo del favicon debe ser una imagen (ICO, PNG, JPG o GIF)';
            } else {
                // Validar tamaño (máximo 1MB)
                if ($_FILES['favicon']['size'] > 1 * 1024 * 1024) {
                    $errors[] = 'El favicon no puede superar 1MB';
                } else {
                    // Procesar subida
                    $uploadResult = processImageUpload(
                        $_FILES['favicon'],
                        BASE_PATH . '/assets/img',
                        $allowedTypes,
                        1 * 1024 * 1024
                    );
                    
                    if ($uploadResult['success']) {
                        $faviconPath = 'assets/img/' . $uploadResult['filename'];
                    } else {
                        $errors[] = 'Error al subir el favicon: ' . $uploadResult['message'];
                    }
                }
            }
        }
        
        // Si no hay errores, actualizar configuraciones
        if (empty($errors)) {
            try {
                // Iniciar transacción
                $transaction = new Transaction();
                $transaction->begin();

                
                // Array de configuraciones a actualizar
                $configToUpdate = [
                    'site_url' => $siteUrl,
                    'logo' => $logoPath,
                    'favicon' => $faviconPath,
                    'date_format' => $dateFormat,
                    'time_format' => $timeFormat,
                    'timezone' => $timezone,
                    'maintenance_mode' => $maintenanceMode,
                    'maintenance_message' => $maintenanceMessage,
                    'footer_text' => $footerText,
                    'enable_pwa' => $enablePwa,
                    'analytics_id' => $analyticsId
                ];
                
                // Actualizar cada configuración
                foreach ($configToUpdate as $key => $value) {
                    // Verificar si la configuración existe
                    $setting = $db->fetch(
                        "SELECT id FROM settings WHERE setting_key = ?",
                        [$key]
                    );
                    
                    if ($setting) {
                        // Actualizar configuración existente
                        $db->query(
                            "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
                            [$value, $key]
                        );
                    } else {
                        // Insertar nueva configuración
                        $db->query(
                            "INSERT INTO settings (setting_key, setting_value, setting_group, created_at, updated_at) 
                             VALUES (?, ?, 'site', NOW(), NOW())",
                            [$key, $value]
                        );
                    }
                }
                
                // Confirmar transacción
                $transaction->commit();
                
                // Registrar la acción en el log
                logAction('Actualizar configuración', '...', $_SESSION['user']['id'] ?? 0);
                
                // Marcar como exitoso
                $success = true;
                
                // Actualizar configuraciones actuales
                $settings = $db->fetchAll("SELECT * FROM settings WHERE setting_group IN ('general', 'site') ORDER BY id");
                $currentSettings = [];
                foreach ($settings as $setting) {
                    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
                }
                
                // Mostrar mensaje de éxito
                setFlashMessage('success', 'Configuración del sitio actualizada correctamente');
                
                // Recargar la página para mostrar el mensaje
                redirect('site.php');
                exit;
                
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $transaction->rollback();
                
                // Registrar error
                $errors[] = 'Error al actualizar la configuración: ' . $e->getMessage();
                error_log('Error al actualizar configuración del sitio: ' . $e->getMessage());
            }
        }
    }
}

// Lista de zonas horarias
$timezones = [
    'America/Argentina/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
    'America/Bogota' => 'America/Bogota',
    'America/Caracas' => 'America/Caracas',
    'America/Chicago' => 'America/Chicago',
    'America/Lima' => 'America/Lima',
    'America/Los_Angeles' => 'America/Los_Angeles',
    'America/Mexico_City' => 'America/Mexico_City',
    'America/New_York' => 'America/New_York',
    'America/Santiago' => 'America/Santiago',
    'Europe/London' => 'Europe/London',
    'Europe/Madrid' => 'Europe/Madrid',
    'Europe/Paris' => 'Europe/Paris',
    'UTC' => 'UTC'
];

// Formatos de fecha
$dateFormats = [
    'd/m/Y' => date('d/m/Y'),
    'm/d/Y' => date('m/d/Y'),
    'Y-m-d' => date('Y-m-d'),
    'd-m-Y' => date('d-m-Y'),
    'd M, Y' => date('d M, Y'),
    'M d, Y' => date('M d, Y'),
    'F d, Y' => date('F d, Y')
];

// Formatos de hora
$timeFormats = [
    'H:i' => date('H:i'),
    'h:i A' => date('h:i A'),
    'h:i a' => date('h:i a'),
    'H:i:s' => date('H:i:s'),
    'h:i:s A' => date('h:i:s A')
];

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
                    <h1 class="m-0">Configuración del Sitio</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/settings/index.php">Configuración</a></li>
                        <li class="breadcrumb-item active">Sitio</li>
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
                    <i class="fas fa-check-circle me-2"></i>Configuración actualizada correctamente
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Formulario de configuración -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Configuración del Sitio</h3>
                </div>
                <div class="card-body">
                    <form action="site.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="row">
                            <!-- Configuración General del Sitio -->
                            <div class="col-md-6">
                                <h4 class="mb-3">Configuración General</h4>
                                
                                <div class="mb-3">
                                    <label for="site_url" class="form-label">URL del Sitio <span class="text-danger">*</span></label>
                                    <input type="url" class="form-control" id="site_url" name="site_url" value="<?php echo $currentSettings['site_url'] ?? ''; ?>" required>
                                    <div class="form-text">URL completa del sitio web (ej: https://www.tusitioweb.com)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">Zona Horaria</label>
                                    <select class="form-select" id="timezone" name="timezone">
                                        <?php foreach ($timezones as $key => $value): ?>
                                            <option value="<?php echo $key; ?>" <?php echo (isset($currentSettings['timezone']) && $currentSettings['timezone'] == $key) ? 'selected' : ''; ?>>
                                                <?php echo $value; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="date_format" class="form-label">Formato de Fecha</label>
                                            <select class="form-select" id="date_format" name="date_format">
                                                <?php foreach ($dateFormats as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo (isset($currentSettings['date_format']) && $currentSettings['date_format'] == $key) ? 'selected' : ''; ?>>
                                                        <?php echo $value; ?> (<?php echo $key; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="time_format" class="form-label">Formato de Hora</label>
                                            <select class="form-select" id="time_format" name="time_format">
                                                <?php foreach ($timeFormats as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo (isset($currentSettings['time_format']) && $currentSettings['time_format'] == $key) ? 'selected' : ''; ?>>
                                                        <?php echo $value; ?> (<?php echo $key; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="footer_text" class="form-label">Texto del Pie de Página</label>
                                    <textarea class="form-control" id="footer_text" name="footer_text" rows="3"><?php echo $currentSettings['footer_text'] ?? ''; ?></textarea>
                                    <div class="form-text">Texto personalizado que aparecerá en el pie de página.</div>
                                </div>
                            </div>
                            
                            <!-- Imágenes y Logos -->
                            <div class="col-md-6">
                                <h4 class="mb-3">Imágenes y Logos</h4>
                                
                                <div class="mb-3">
                                    <label for="logo" class="form-label">Logo del Sitio</label>
                                    <input type="file" class="form-control" id="logo" name="logo" accept="image/jpeg, image/png, image/gif, image/svg+xml">
                                    <div class="form-text">Imagen del logo principal (tamaño recomendado: 200x50 px)</div>
                                    <?php if (!empty($currentSettings['logo'])): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo '../../' . $currentSettings['logo']; ?>" alt="Logo actual" class="img-preview" style="max-height: 50px;">
                                            <p class="form-text">Logo actual: <?php echo $currentSettings['logo']; ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="favicon" class="form-label">Favicon</label>
                                    <input type="file" class="form-control" id="favicon" name="favicon" accept="image/x-icon, image/png, image/jpeg, image/gif">
                                    <div class="form-text">Icono del sitio (tamaño recomendado: 32x32 o 16x16 px)</div>
                                    <?php if (!empty($currentSettings['favicon'])): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo '../../' . $currentSettings['favicon']; ?>" alt="Favicon actual" class="img-preview" style="max-height: 32px;">
                                            <p class="form-text">Favicon actual: <?php echo $currentSettings['favicon']; ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <h4 class="mb-3 mt-4">Análisis y PWA</h4>
                                
                                <div class="mb-3">
                                    <label for="analytics_id" class="form-label">ID de Google Analytics</label>
                                    <input type="text" class="form-control" id="analytics_id" name="analytics_id" value="<?php echo $currentSettings['analytics_id'] ?? ''; ?>">
                                    <div class="form-text">Ejemplo: G-XXXXXXXXXX o UA-XXXXXXXXX-X</div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="enable_pwa" name="enable_pwa" value="1" <?php echo (isset($currentSettings['enable_pwa']) && $currentSettings['enable_pwa'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enable_pwa">Habilitar funcionalidad PWA (Progressive Web App)</label>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Modo de Mantenimiento -->
                        <h4 class="mb-3">Modo de Mantenimiento</h4>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo (isset($currentSettings['maintenance_mode']) && $currentSettings['maintenance_mode'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="maintenance_mode">Activar modo de mantenimiento</label>
                            <div class="form-text">El sitio mostrará una página de mantenimiento a todos los visitantes. Los administradores seguirán teniendo acceso.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="maintenance_message" class="form-label">Mensaje de Mantenimiento</label>
                            <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3"><?php echo $currentSettings['maintenance_message'] ?? 'Estamos realizando tareas de mantenimiento. Por favor, vuelva más tarde.'; ?></textarea>
                            <div class="form-text">Mensaje que se mostrará a los visitantes durante el mantenimiento.</div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar Configuración
                            </button>
                            <a href="themes.php" class="btn btn-info ms-2">
                                <i class="fas fa-paint-brush me-1"></i> Gestionar Temas
                            </a>
                            <a href="index.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-arrow-left me-1"></i> Volver
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>