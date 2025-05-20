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
$pageTitle = 'Configuración de APIs - Panel de Administración';
$currentMenu = 'settings_api';

// Obtener configuraciones actuales
$db = Database::getInstance();
$settings = $db->fetchAll("SELECT * FROM settings WHERE setting_group = 'api' ORDER BY id");

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
        $weatherApiKey = isset($_POST['weather_api_key']) ? sanitize($_POST['weather_api_key']) : '';
        $weatherApiCity = isset($_POST['weather_api_city']) ? sanitize($_POST['weather_api_city']) : '';
        $weatherApiUnits = isset($_POST['weather_api_units']) ? sanitize($_POST['weather_api_units']) : 'metric';
        $newsApiKey = isset($_POST['news_api_key']) ? sanitize($_POST['news_api_key']) : '';
        $googleMapsApiKey = isset($_POST['google_maps_api_key']) ? sanitize($_POST['google_maps_api_key']) : '';
        $recaptchaSiteKey = isset($_POST['recaptcha_site_key']) ? sanitize($_POST['recaptcha_site_key']) : '';
        $recaptchaSecretKey = isset($_POST['recaptcha_secret_key']) ? sanitize($_POST['recaptcha_secret_key']) : '';
        $enableRecaptcha = isset($_POST['enable_recaptcha']) ? 1 : 0;
        $mailchimpApiKey = isset($_POST['mailchimp_api_key']) ? sanitize($_POST['mailchimp_api_key']) : '';
        $mailchimpListId = isset($_POST['mailchimp_list_id']) ? sanitize($_POST['mailchimp_list_id']) : '';
        $smtpHost = isset($_POST['smtp_host']) ? sanitize($_POST['smtp_host']) : '';
        $smtpPort = isset($_POST['smtp_port']) ? (int)$_POST['smtp_port'] : 587;
        $smtpUsername = isset($_POST['smtp_username']) ? sanitize($_POST['smtp_username']) : '';
        $smtpPassword = isset($_POST['smtp_password']) ? $_POST['smtp_password'] : '';
        $smtpSender = isset($_POST['smtp_sender']) ? sanitize($_POST['smtp_sender']) : '';
        $enableSmtp = isset($_POST['enable_smtp']) ? 1 : 0;
        
        // Si no hay errores, actualizar configuraciones
        if (empty($errors)) {
            try {
                // Iniciar transacción
                $transaction = new Transaction();
                $transaction->begin();
                
                // Array de configuraciones a actualizar
                $configToUpdate = [
                    'weather_api_key' => $weatherApiKey,
                    'weather_api_city' => $weatherApiCity,
                    'weather_api_units' => $weatherApiUnits,
                    'news_api_key' => $newsApiKey,
                    'google_maps_api_key' => $googleMapsApiKey,
                    'recaptcha_site_key' => $recaptchaSiteKey,
                    'recaptcha_secret_key' => $recaptchaSecretKey,
                    'enable_recaptcha' => $enableRecaptcha,
                    'mailchimp_api_key' => $mailchimpApiKey,
                    'mailchimp_list_id' => $mailchimpListId,
                    'smtp_host' => $smtpHost,
                    'smtp_port' => $smtpPort,
                    'smtp_username' => $smtpUsername,
                    'smtp_sender' => $smtpSender,
                    'enable_smtp' => $enableSmtp
                ];
                
                // Sólo actualizar contraseña SMTP si se proporcionó
                if (!empty($smtpPassword)) {
                    $configToUpdate['smtp_password'] = $smtpPassword;
                }
                
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
                             VALUES (?, ?, 'api', NOW(), NOW())",
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
                $settings = $db->fetchAll("SELECT * FROM settings WHERE setting_group = 'api' ORDER BY id");
                $currentSettings = [];
                foreach ($settings as $setting) {
                    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
                }
                
                // Mostrar mensaje de éxito
                setFlashMessage('success', 'Configuración de APIs actualizada correctamente');
                
                // Recargar la página para mostrar el mensaje
                redirect('api.php');
                exit;
                
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $transaction->rollback();
                
                // Registrar error
                $errors[] = 'Error al actualizar la configuración: ' . $e->getMessage();
                error_log('Error al actualizar configuración de APIs: ' . $e->getMessage());
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
                    <h1 class="m-0">Configuración de APIs</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/settings/index.php">Configuración</a></li>
                        <li class="breadcrumb-item active">APIs</li>
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
                    <h3 class="card-title">Configuración de APIs Externas</h3>
                </div>
                <div class="card-body">
                    <form action="api.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <!-- Pestañas de navegación -->
                        <ul class="nav nav-tabs mb-3" id="apiTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="weather-tab" data-bs-toggle="tab" data-bs-target="#weather" type="button" role="tab" aria-controls="weather" aria-selected="true">
                                    <i class="fas fa-cloud-sun me-1"></i> Clima
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="maps-tab" data-bs-toggle="tab" data-bs-target="#maps" type="button" role="tab" aria-controls="maps" aria-selected="false">
                                    <i class="fas fa-map-marker-alt me-1"></i> Maps
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="captcha-tab" data-bs-toggle="tab" data-bs-target="#captcha" type="button" role="tab" aria-controls="captcha" aria-selected="false">
                                    <i class="fas fa-robot me-1"></i> reCAPTCHA
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">
                                    <i class="fas fa-envelope me-1"></i> Email
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="others-tab" data-bs-toggle="tab" data-bs-target="#others" type="button" role="tab" aria-controls="others" aria-selected="false">
                                    <i class="fas fa-cogs me-1"></i> Otras APIs
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Contenido de las pestañas -->
                        <div class="tab-content" id="apiTabsContent">
                            <!-- Clima (OpenWeatherMap) -->
                            <div class="tab-pane fade show active" id="weather" role="tabpanel" aria-labelledby="weather-tab">
                                <h4 class="mb-3">API del Clima (OpenWeatherMap)</h4>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="weather_api_key" class="form-label">API Key de OpenWeatherMap</label>
                                            <input type="text" class="form-control" id="weather_api_key" name="weather_api_key" value="<?php echo $currentSettings['weather_api_key'] ?? ''; ?>">
                                            <div class="form-text">Obtén tu API Key en <a href="https://openweathermap.org/api" target="_blank">openweathermap.org/api</a></div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="weather_api_city" class="form-label">Ciudad predeterminada</label>
                                            <input type="text" class="form-control" id="weather_api_city" name="weather_api_city" value="<?php echo $currentSettings['weather_api_city'] ?? 'Buenos Aires'; ?>">
                                            <div class="form-text">Ciudad que se mostrará por defecto en el widget del clima</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="weather_api_units" class="form-label">Unidades de temperatura</label>
                                    <select class="form-select" id="weather_api_units" name="weather_api_units">
                                        <option value="metric" <?php echo (isset($currentSettings['weather_api_units']) && $currentSettings['weather_api_units'] == 'metric') ? 'selected' : ''; ?>>Celsius (°C)</option>
                                        <option value="imperial" <?php echo (isset($currentSettings['weather_api_units']) && $currentSettings['weather_api_units'] == 'imperial') ? 'selected' : ''; ?>>Fahrenheit (°F)</option>
                                    </select>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>Configura la API del clima para mostrar información meteorológica actualizada en el portal.
                                </div>
                            </div>
                            
                            <!-- Google Maps API -->
                            <div class="tab-pane fade" id="maps" role="tabpanel" aria-labelledby="maps-tab">
                                <h4 class="mb-3">Google Maps API</h4>
                                
                                <div class="mb-3">
                                    <label for="google_maps_api_key" class="form-label">API Key de Google Maps</label>
                                    <input type="text" class="form-control" id="google_maps_api_key" name="google_maps_api_key" value="<?php echo $currentSettings['google_maps_api_key'] ?? ''; ?>">
                                    <div class="form-text">Obtén tu API Key en <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">Google Cloud Console</a></div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>Esta API se usa para integrar mapas en el portal, como ubicaciones de eventos o para la página de contacto.
                                </div>
                            </div>
                            
                            <!-- Configuración reCAPTCHA -->
                            <div class="tab-pane fade" id="captcha" role="tabpanel" aria-labelledby="captcha-tab">
                                <h4 class="mb-3">Google reCAPTCHA</h4>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="enable_recaptcha" name="enable_recaptcha" value="1" <?php echo (isset($currentSettings['enable_recaptcha']) && $currentSettings['enable_recaptcha'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enable_recaptcha">Habilitar reCAPTCHA en formularios</label>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="recaptcha_site_key" class="form-label">Site Key</label>
                                            <input type="text" class="form-control" id="recaptcha_site_key" name="recaptcha_site_key" value="<?php echo $currentSettings['recaptcha_site_key'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="recaptcha_secret_key" class="form-label">Secret Key</label>
                                            <input type="password" class="form-control" id="recaptcha_secret_key" name="recaptcha_secret_key" value="<?php echo $currentSettings['recaptcha_secret_key'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>Configurar reCAPTCHA para proteger los formularios del sitio contra spam. Obtén tus claves en <a href="https://www.google.com/recaptcha/admin" target="_blank">google.com/recaptcha/admin</a>
                                </div>
                            </div>
                            
                            <!-- Configuración de Email -->
                            <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h4 class="mb-3">Configuración SMTP</h4>
                                        
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" class="form-check-input" id="enable_smtp" name="enable_smtp" value="1" <?php echo (isset($currentSettings['enable_smtp']) && $currentSettings['enable_smtp'] == 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_smtp">Usar SMTP para envío de emails</label>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="smtp_host" class="form-label">Host SMTP</label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo $currentSettings['smtp_host'] ?? ''; ?>" placeholder="smtp.gmail.com">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="smtp_port" class="form-label">Puerto SMTP</label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo $currentSettings['smtp_port'] ?? '587'; ?>" placeholder="587">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="smtp_username" class="form-label">Usuario SMTP</label>
                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo $currentSettings['smtp_username'] ?? ''; ?>" placeholder="tu@email.com">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="smtp_password" class="form-label">Contraseña SMTP</label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" placeholder="Dejar vacío para mantener la actual">
                                            <?php if (isset($currentSettings['smtp_password']) && !empty($currentSettings['smtp_password'])): ?>
                                                <div class="form-text">La contraseña está configurada. Déjala en blanco para mantener la actual.</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="smtp_sender" class="form-label">Remitente de emails</label>
                                            <input type="text" class="form-control" id="smtp_sender" name="smtp_sender" value="<?php echo $currentSettings['smtp_sender'] ?? ''; ?>" placeholder="Portal de Noticias <noticias@tudominio.com>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h4 class="mb-3">Mailchimp (Newsletter)</h4>
                                        
                                        <div class="mb-3">
                                            <label for="mailchimp_api_key" class="form-label">API Key de Mailchimp</label>
                                            <input type="text" class="form-control" id="mailchimp_api_key" name="mailchimp_api_key" value="<?php echo $currentSettings['mailchimp_api_key'] ?? ''; ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="mailchimp_list_id" class="form-label">ID de Lista (Audiencia)</label>
                                            <input type="text" class="form-control" id="mailchimp_list_id" name="mailchimp_list_id" value="<?php echo $currentSettings['mailchimp_list_id'] ?? ''; ?>">
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>Integra Mailchimp para gestionar suscriptores al newsletter. Obten tus credenciales en <a href="https://mailchimp.com/developer/" target="_blank">mailchimp.com/developer</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Otras APIs -->
                            <div class="tab-pane fade" id="others" role="tabpanel" aria-labelledby="others-tab">
                                <h4 class="mb-3">News API</h4>
                                
                                <div class="mb-3">
                                    <label for="news_api_key" class="form-label">API Key de News API</label>
                                    <input type="text" class="form-control" id="news_api_key" name="news_api_key" value="<?php echo $currentSettings['news_api_key'] ?? ''; ?>">
                                    <div class="form-text">Obtén tu API Key en <a href="https://newsapi.org/" target="_blank">newsapi.org</a></div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>News API te permite agregar contenido de otras fuentes de noticias externas al portal.
                                </div>
                                
                                <h4 class="mb-3 mt-4">Otras APIs</h4>
                                <p>Para configurar APIs adicionales, agrega los campos necesarios en la base de datos y actualiza este formulario.</p>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar Configuración
                            </button>
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