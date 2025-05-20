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
$pageTitle = 'Redes Sociales - Panel de Administración';
$currentMenu = 'settings_social';

// Obtener configuraciones actuales
$db = Database::getInstance();
$settings = $db->fetchAll("SELECT * FROM settings WHERE setting_group = 'social' ORDER BY id");

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
        $facebook = isset($_POST['facebook']) ? sanitize($_POST['facebook']) : '';
        $twitter = isset($_POST['twitter']) ? sanitize($_POST['twitter']) : '';
        $instagram = isset($_POST['instagram']) ? sanitize($_POST['instagram']) : '';
        $youtube = isset($_POST['youtube']) ? sanitize($_POST['youtube']) : '';
        $linkedIn = isset($_POST['linkedin']) ? sanitize($_POST['linkedin']) : '';
        $pinterest = isset($_POST['pinterest']) ? sanitize($_POST['pinterest']) : '';
        $tiktok = isset($_POST['tiktok']) ? sanitize($_POST['tiktok']) : '';
        $whatsapp = isset($_POST['whatsapp']) ? sanitize($_POST['whatsapp']) : '';
        $telegram = isset($_POST['telegram']) ? sanitize($_POST['telegram']) : '';
        $enableSocialLogin = isset($_POST['enable_social_login']) ? 1 : 0;
        $facebookAppId = isset($_POST['facebook_app_id']) ? sanitize($_POST['facebook_app_id']) : '';
        $facebookAppSecret = isset($_POST['facebook_app_secret']) ? sanitize($_POST['facebook_app_secret']) : '';
        $googleClientId = isset($_POST['google_client_id']) ? sanitize($_POST['google_client_id']) : '';
        $googleClientSecret = isset($_POST['google_client_secret']) ? sanitize($_POST['google_client_secret']) : '';
        $socialShareButtons = isset($_POST['social_share_buttons']) ? serialize($_POST['social_share_buttons']) : serialize([]);
        
        // Validar URLs (opcional)
        $validateUrls = false; // Cambiar a true para validar URLs
        
        if ($validateUrls) {
            $urlsToValidate = [
                'Facebook' => $facebook,
                'Twitter' => $twitter,
                'Instagram' => $instagram,
                'YouTube' => $youtube,
                'LinkedIn' => $linkedIn,
                'Pinterest' => $pinterest,
                'TikTok' => $tiktok
            ];
            
            foreach ($urlsToValidate as $name => $url) {
                if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                    $errors[] = "La URL de $name no es válida";
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
                    'facebook' => $facebook,
                    'twitter' => $twitter,
                    'instagram' => $instagram,
                    'youtube' => $youtube,
                    'linkedin' => $linkedIn,
                    'pinterest' => $pinterest,
                    'tiktok' => $tiktok,
                    'whatsapp' => $whatsapp,
                    'telegram' => $telegram,
                    'enable_social_login' => $enableSocialLogin,
                    'facebook_app_id' => $facebookAppId,
                    'facebook_app_secret' => $facebookAppSecret,
                    'google_client_id' => $googleClientId,
                    'google_client_secret' => $googleClientSecret,
                    'social_share_buttons' => $socialShareButtons
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
                             VALUES (?, ?, 'social', NOW(), NOW())",
                            [$key, $value]
                        );
                    }
                }
                
                // Confirmar transacción
                $transaction->commit();
                
                // Registrar la acción en el log
                logAction('Actualizar configuración', 'Configuración de redes sociales actualizada', $_SESSION['user']['id'] ?? 0);
                
                // Marcar como exitoso
                $success = true;
                
                // Actualizar configuraciones actuales
                $settings = $db->fetchAll("SELECT * FROM settings WHERE setting_group = 'social' ORDER BY id");
                $currentSettings = [];
                foreach ($settings as $setting) {
                    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
                }
                
                // Mostrar mensaje de éxito
                setFlashMessage('success', 'Configuración de redes sociales actualizada correctamente');
                
                // Recargar la página para mostrar el mensaje
                redirect('social.php');
                exit;
                
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $transaction->rollback();
                
                // Registrar error
                $errors[] = 'Error al actualizar la configuración: ' . $e->getMessage();
                error_log('Error al actualizar configuración de redes sociales: ' . $e->getMessage());
            }
        }
    }
}

// Opciones de botones para compartir
$shareButtonOptions = [
    'facebook' => 'Facebook',
    'twitter' => 'Twitter',
    'whatsapp' => 'WhatsApp',
    'telegram' => 'Telegram',
    'linkedin' => 'LinkedIn',
    'pinterest' => 'Pinterest',
    'email' => 'Email'
];

// Obtener valores seleccionados para botones de compartir
$selectedShareButtons = isset($currentSettings['social_share_buttons']) ? unserialize($currentSettings['social_share_buttons']) : [];

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
                    <h1 class="m-0">Redes Sociales</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/settings/index.php">Configuración</a></li>
                        <li class="breadcrumb-item active">Redes Sociales</li>
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
                    <h3 class="card-title">Configuración de Redes Sociales</h3>
                </div>
                <div class="card-body">
                    <form action="social.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="row">
                            <!-- Perfiles Sociales -->
                            <div class="col-md-6">
                                <h4 class="mb-3">Perfiles Sociales</h4>
                                
                                <div class="mb-3">
                                    <label for="facebook" class="form-label">
                                        <i class="fab fa-facebook text-primary me-2"></i> Facebook
                                    </label>
                                    <input type="url" class="form-control" id="facebook" name="facebook" value="<?php echo $currentSettings['facebook'] ?? ''; ?>" placeholder="https://facebook.com/tuempresa">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="twitter" class="form-label">
                                        <i class="fab fa-twitter text-info me-2"></i> Twitter
                                    </label>
                                    <input type="url" class="form-control" id="twitter" name="twitter" value="<?php echo $currentSettings['twitter'] ?? ''; ?>" placeholder="https://twitter.com/tuempresa">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instagram" class="form-label">
                                        <i class="fab fa-instagram text-danger me-2"></i> Instagram
                                    </label>
                                    <input type="url" class="form-control" id="instagram" name="instagram" value="<?php echo $currentSettings['instagram'] ?? ''; ?>" placeholder="https://instagram.com/tuempresa">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="youtube" class="form-label">
                                        <i class="fab fa-youtube text-danger me-2"></i> YouTube
                                    </label>
                                    <input type="url" class="form-control" id="youtube" name="youtube" value="<?php echo $currentSettings['youtube'] ?? ''; ?>" placeholder="https://youtube.com/c/tuempresa">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="linkedin" class="form-label">
                                        <i class="fab fa-linkedin text-primary me-2"></i> LinkedIn
                                    </label>
                                    <input type="url" class="form-control" id="linkedin" name="linkedin" value="<?php echo $currentSettings['linkedin'] ?? ''; ?>" placeholder="https://linkedin.com/company/tuempresa">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="pinterest" class="form-label">
                                        <i class="fab fa-pinterest text-danger me-2"></i> Pinterest
                                    </label>
                                    <input type="url" class="form-control" id="pinterest" name="pinterest" value="<?php echo $currentSettings['pinterest'] ?? ''; ?>" placeholder="https://pinterest.com/tuempresa">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tiktok" class="form-label">
                                        <i class="fab fa-tiktok me-2"></i> TikTok
                                    </label>
                                    <input type="url" class="form-control" id="tiktok" name="tiktok" value="<?php echo $currentSettings['tiktok'] ?? ''; ?>" placeholder="https://tiktok.com/@tuempresa">
                                </div>
                            </div>
                            
                            <!-- Mensajería y Compartir -->
                            <div class="col-md-6">
                                <h4 class="mb-3">Mensajería</h4>
                                
                                <div class="mb-3">
                                    <label for="whatsapp" class="form-label">
                                        <i class="fab fa-whatsapp text-success me-2"></i> WhatsApp
                                    </label>
                                    <input type="text" class="form-control" id="whatsapp" name="whatsapp" value="<?php echo $currentSettings['whatsapp'] ?? ''; ?>" placeholder="549XXXXXXXXXX">
                                    <div class="form-text">Código de país + número sin espacios ni guiones (ej: 5491123456789)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="telegram" class="form-label">
                                        <i class="fab fa-telegram text-info me-2"></i> Telegram
                                    </label>
                                    <input type="text" class="form-control" id="telegram" name="telegram" value="<?php echo $currentSettings['telegram'] ?? ''; ?>" placeholder="@tuempresa">
                                    <div class="form-text">Nombre de usuario o enlace de contacto (ej: @tuempresa)</div>
                                </div>
                                
                                <h4 class="mb-3 mt-4">Botones para Compartir</h4>
                                
                                <div class="mb-3">
                                    <label class="form-label d-block">Botones para compartir en noticias</label>
                                    <div class="row">
                                        <?php foreach ($shareButtonOptions as $value => $label): ?>
                                            <div class="col-md-6">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="share_<?php echo $value; ?>" 
                                                           name="social_share_buttons[]" 
                                                           value="<?php echo $value; ?>"
                                                           <?php echo in_array($value, $selectedShareButtons) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="share_<?php echo $value; ?>">
                                                        <?php echo $label; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Login Social -->
                        <h4 class="mb-3">Login con Redes Sociales</h4>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="enable_social_login" name="enable_social_login" value="1" <?php echo (isset($currentSettings['enable_social_login']) && $currentSettings['enable_social_login'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_social_login">Habilitar inicio de sesión con redes sociales</label>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Facebook Login</h5>
                                
                                <div class="mb-3">
                                    <label for="facebook_app_id" class="form-label">App ID de Facebook</label>
                                    <input type="text" class="form-control" id="facebook_app_id" name="facebook_app_id" value="<?php echo $currentSettings['facebook_app_id'] ?? ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="facebook_app_secret" class="form-label">App Secret de Facebook</label>
                                    <input type="password" class="form-control" id="facebook_app_secret" name="facebook_app_secret" value="<?php echo $currentSettings['facebook_app_secret'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Google Login</h5>
                                
                                <div class="mb-3">
                                    <label for="google_client_id" class="form-label">Client ID de Google</label>
                                    <input type="text" class="form-control" id="google_client_id" name="google_client_id" value="<?php echo $currentSettings['google_client_id'] ?? ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="google_client_secret" class="form-label">Client Secret de Google</label>
                                    <input type="password" class="form-control" id="google_client_secret" name="google_client_secret" value="<?php echo $currentSettings['google_client_secret'] ?? ''; ?>">
                                </div>
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