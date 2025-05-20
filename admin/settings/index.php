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
$pageTitle = 'Configuración General - Panel de Administración';
$currentMenu = 'settings';

// Obtener configuraciones actuales
$db = Database::getInstance();
$settings = $db->fetchAll("SELECT * FROM settings WHERE setting_group = 'general' ORDER BY id");

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
        $siteName = isset($_POST['site_name']) ? sanitize($_POST['site_name']) : '';
        $siteDescription = isset($_POST['site_description']) ? sanitize($_POST['site_description']) : '';
        $companyName = isset($_POST['company_name']) ? sanitize($_POST['company_name']) : '';
        $emailContact = isset($_POST['email_contact']) ? sanitize($_POST['email_contact']) : '';
        $phoneContact = isset($_POST['phone_contact']) ? sanitize($_POST['phone_contact']) : '';
        $address = isset($_POST['address']) ? sanitize($_POST['address']) : '';
        $postsPerPage = isset($_POST['posts_per_page']) ? (int)$_POST['posts_per_page'] : 10;
        $allowComments = isset($_POST['allow_comments']) ? 1 : 0;
        $moderateComments = isset($_POST['moderate_comments']) ? 1 : 0;
        
        // Validar datos
        if (empty($siteName)) {
            $errors[] = 'El nombre del sitio es obligatorio';
        }
        
        if (empty($emailContact)) {
            $errors[] = 'El email de contacto es obligatorio';
        } elseif (!isValidEmail($emailContact)) {
            $errors[] = 'El email de contacto no es válido';
        }
        
        if ($postsPerPage < 1 || $postsPerPage > 50) {
            $errors[] = 'El número de noticias por página debe estar entre 1 y 50';
        }
        
        // Si no hay errores, actualizar configuraciones
        if (empty($errors)) {
            try {
                // Iniciar transacción
                $transaction = new Transaction();
                $transaction->begin();
                
                // Array de configuraciones a actualizar
                $configToUpdate = [
                    'site_name' => $siteName,
                    'site_description' => $siteDescription,
                    'company_name' => $companyName,
                    'email_contact' => $emailContact,
                    'phone_contact' => $phoneContact,
                    'address' => $address,
                    'posts_per_page' => $postsPerPage,
                    'allow_comments' => $allowComments,
                    'moderate_comments' => $moderateComments
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
                             VALUES (?, ?, 'general', NOW(), NOW())",
                            [$key, $value]
                        );
                    }
                }
                
                // Confirmar transacción
                $transaction->commit();
                
                // Registrar la acción en el log
                logAction('Actualizar configuración', 'Configuración general actualizada', isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0);
                
                // Marcar como exitoso
                $success = true;
                
                // Actualizar configuraciones actuales
                $settings = $db->fetchAll("SELECT * FROM settings WHERE setting_group = 'general' ORDER BY id");
                $currentSettings = [];
                foreach ($settings as $setting) {
                    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
                }
                
                // Mostrar mensaje de éxito
                setFlashMessage('success', 'Configuración general actualizada correctamente');
                
                // Recargar la página para mostrar el mensaje
                redirect('index.php');
                exit;
                
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $transaction->rollback();
                
                // Registrar error
                $errors[] = 'Error al actualizar la configuración: ' . $e->getMessage();
                error_log('Error al actualizar configuración general: ' . $e->getMessage());
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
                    <h1 class="m-0">Configuración General</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Configuración General</li>
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
                    <h3 class="card-title">Configuración General del Portal</h3>
                </div>
                <div class="card-body">
                    <form action="index.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="row">
                            <!-- Información del Sitio -->
                            <div class="col-md-6">
                                <h4 class="mb-3">Información del Sitio</h4>
                                
                                <div class="mb-3">
                                    <label for="site_name" class="form-label">Nombre del Sitio <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo $currentSettings['site_name'] ?? ''; ?>" required>
                                    <div class="form-text">El nombre que aparecerá en el título del sitio y en varios elementos.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="site_description" class="form-label">Descripción del Sitio</label>
                                    <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo $currentSettings['site_description'] ?? ''; ?></textarea>
                                    <div class="form-text">Una breve descripción del portal que aparecerá en los metadatos.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="company_name" class="form-label">Nombre de la Empresa</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo $currentSettings['company_name'] ?? ''; ?>">
                                    <div class="form-text">El nombre legal de la empresa propietaria del portal.</div>
                                </div>
                            </div>
                            
                            <!-- Información de Contacto -->
                            <div class="col-md-6">
                                <h4 class="mb-3">Información de Contacto</h4>
                                
                                <div class="mb-3">
                                    <label for="email_contact" class="form-label">Email de Contacto <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email_contact" name="email_contact" value="<?php echo $currentSettings['email_contact'] ?? ''; ?>" required>
                                    <div class="form-text">Email principal para recibir contactos y notificaciones.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone_contact" class="form-label">Teléfono de Contacto</label>
                                    <input type="text" class="form-control" id="phone_contact" name="phone_contact" value="<?php echo $currentSettings['phone_contact'] ?? ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Dirección</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo $currentSettings['address'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Configuración de Contenido -->
                        <h4 class="mb-3">Configuración de Contenido</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="posts_per_page" class="form-label">Noticias por Página</label>
                                    <input type="number" class="form-control" id="posts_per_page" name="posts_per_page" min="1" max="50" value="<?php echo $currentSettings['posts_per_page'] ?? 10; ?>">
                                    <div class="form-text">Número de noticias a mostrar por página en los listados.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="allow_comments" name="allow_comments" value="1" <?php echo (isset($currentSettings['allow_comments']) && $currentSettings['allow_comments'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_comments">Permitir comentarios en las noticias</label>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="moderate_comments" name="moderate_comments" value="1" <?php echo (isset($currentSettings['moderate_comments']) && $currentSettings['moderate_comments'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="moderate_comments">Moderar comentarios antes de publicarlos</label>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>