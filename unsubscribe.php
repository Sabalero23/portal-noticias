<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Verificar si se proporcionó el email
if (!isset($_GET['email']) || empty($_GET['email'])) {
    setFlashMessage('error', 'Enlace de cancelación inválido');
    redirect('index.php');
}

// Sanitizar email
$email = sanitize($_GET['email']);

// Validar email
if (!isValidEmail($email)) {
    setFlashMessage('error', 'Dirección de email inválida');
    redirect('index.php');
}

// Buscar suscriptor
$db = Database::getInstance();
$subscriber = $db->fetch(
    "SELECT id, name, status, confirmed FROM subscribers WHERE email = ?",
    [$email]
);

// Si no existe, mostrar mensaje de error
if (!$subscriber) {
    setFlashMessage('error', 'No se encontró ninguna suscripción con esta dirección de correo electrónico');
    redirect('index.php');
}

// Procesar la solicitud de cancelación
$unsubscribeRequested = false;
$unsubscribeConfirmed = false;
$unsubscribeError = '';

// Verificar si se está cancelando o confirmando la cancelación
if (isset($_GET['token']) && !empty($_GET['token'])) {
    // Confirmación de cancelación de suscripción
    $token = sanitize($_GET['token']);
    
    // Verificar token
    $validToken = $db->fetch(
        "SELECT id FROM subscribers WHERE email = ? AND unsubscribe_token = ?",
        [$email, $token]
    );
    
    if ($validToken) {
        // Actualizar estado a 'unsubscribed'
        $updated = $db->query(
            "UPDATE subscribers SET status = 'unsubscribed', updated_at = NOW() WHERE id = ?",
            [$subscriber['id']]
        );
        
        if ($updated) {
            $unsubscribeConfirmed = true;
        } else {
            $unsubscribeError = 'Error al cancelar tu suscripción. Por favor, intenta nuevamente.';
        }
    } else {
        $unsubscribeError = 'Token inválido. Por favor, solicita un nuevo enlace de cancelación.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_unsubscribe'])) {
    // Generar token de cancelación
    $unsubscribeToken = generateToken();
    
    // Guardar token en la base de datos
    $updated = $db->query(
        "UPDATE subscribers SET unsubscribe_token = ? WHERE id = ?",
        [$unsubscribeToken, $subscriber['id']]
    );
    
    if ($updated) {
        // Enviar correo de confirmación
        $emailSent = sendUnsubscribeConfirmationEmail($email, $subscriber['name'], $unsubscribeToken);
        
        if ($emailSent) {
            $unsubscribeRequested = true;
        } else {
            $unsubscribeError = 'Error al enviar el correo de confirmación. Por favor, intenta nuevamente.';
        }
    } else {
        $unsubscribeError = 'Error al procesar tu solicitud. Por favor, intenta nuevamente.';
    }
}

// Función para enviar email de confirmación de cancelación
function sendUnsubscribeConfirmationEmail($email, $name, $token) {
    // Nombre para mostrar
    $displayName = !empty($name) ? $name : 'Suscriptor';
    
    // Construir URL de confirmación
    $confirmUrl = SITE_URL . '/unsubscribe.php?email=' . urlencode($email) . '&token=' . urlencode($token);
    
    // Asunto del correo
    $subject = 'Confirma la cancelación de tu suscripción a ' . getSetting('site_name', 'Portal de Noticias');
    
    // Cuerpo del correo
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="' . SITE_URL . '/' . getSetting('logo', 'assets/img/logo.png') . '" alt="Logo" style="max-width: 200px;">
        </div>
        
        <h2 style="color: #333;">Confirmación de cancelación de suscripción</h2>
        
        <p>Hola ' . htmlspecialchars($displayName) . ',</p>
        
        <p>Hemos recibido una solicitud para cancelar tu suscripción al newsletter de ' . getSetting('site_name', 'Portal de Noticias') . '.</p>
        
        <p>Si no has sido tú quien solicitó esta cancelación, puedes ignorar este mensaje y tu suscripción seguirá activa.</p>
        
        <p>Si deseas confirmar la cancelación de tu suscripción, haz clic en el siguiente botón:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $confirmUrl . '" style="background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">Confirmar cancelación</a>
        </div>
        
        <p>O copia y pega el siguiente enlace en tu navegador:</p>
        <p style="word-break: break-all; color: #666;">' . $confirmUrl . '</p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        
        <p style="color: #777; font-size: 12px; text-align: center;">
            &copy; ' . date('Y') . ' ' . getSetting('site_name', 'Portal de Noticias') . '. Todos los derechos reservados.
        </p>
    </div>
    ';
    
    // Enviar el correo
    return sendEmail($email, $subject, $body);
}

// Configuración para la página
$pageTitle = 'Cancelar suscripción - ' . getSetting('site_name', 'Portal de Noticias');
$metaDescription = 'Cancelar suscripción al newsletter de ' . getSetting('site_name', 'Portal de Noticias');

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Cancelar suscripción</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Contenido Principal -->
<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h1 class="h4 mb-0">Cancelar suscripción</h1>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($unsubscribeError)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $unsubscribeError; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($unsubscribeConfirmed): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">¡Suscripción cancelada!</h4>
                        <p>Tu suscripción al newsletter ha sido cancelada exitosamente.</p>
                        <p class="mb-0">Ya no recibirás más correos de <?php echo getSetting('site_name', 'Portal de Noticias'); ?>.</p>
                    </div>
                    <div class="text-center mt-4">
                        <a href="index.php" class="btn btn-primary">Volver a la página principal</a>
                    </div>
                    <?php elseif ($unsubscribeRequested): ?>
                    <div class="alert alert-info">
                        <h4 class="alert-heading">Confirmación enviada</h4>
                        <p>Hemos enviado un correo de confirmación a <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
                        <p class="mb-0">Por favor, revisa tu bandeja de entrada (y carpeta de spam) y haz clic en el enlace de confirmación para completar la cancelación de tu suscripción.</p>
                    </div>
                    <div class="text-center mt-4">
                        <a href="index.php" class="btn btn-primary">Volver a la página principal</a>
                    </div>
                    <?php else: ?>
                    <div class="mb-4">
                        <p>Estás a punto de cancelar tu suscripción al newsletter de <?php echo getSetting('site_name', 'Portal de Noticias'); ?> para la dirección de correo electrónico:</p>
                        <div class="alert alert-secondary">
                            <strong><?php echo htmlspecialchars($email); ?></strong>
                        </div>
                        <p>Una vez que confirmes, ya no recibirás nuestros boletines ni actualizaciones. ¿Estás seguro de que deseas continuar?</p>
                    </div>
                    
                    <form action="unsubscribe.php?email=<?php echo urlencode($email); ?>" method="post" id="unsubscribeForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" name="confirm_unsubscribe" class="btn btn-danger">Cancelar suscripción</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>