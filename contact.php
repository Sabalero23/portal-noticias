<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Obtener categorías para el menú
$db = Database::getInstance();
$categories = $db->fetchAll("SELECT id, name, slug FROM categories ORDER BY name");

// Procesar el formulario si se ha enviado
$formSubmitted = false;
$formSuccess = false;
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    // Verificar el token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $formError = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        // Validar campos obligatorios
        $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
        $subject = isset($_POST['subject']) ? sanitize($_POST['subject']) : '';
        $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';
        
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $formError = 'Por favor, completa todos los campos obligatorios.';
        } elseif (!isValidEmail($email)) {
            $formError = 'Por favor, introduce una dirección de correo electrónico válida.';
        } else {
            // Todo está bien, procesar el mensaje
            $db = Database::getInstance();
            
            $result = $db->query(
                "INSERT INTO contact_messages (name, email, subject, message, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$name, $email, $subject, $message, $_SERVER['REMOTE_ADDR']]
            );
            
            if ($result) {
                // Enviar notificación por correo
                $adminEmail = getSetting('email_contact', 'admin@portalnoticias.com');
                
                $emailSubject = 'Nuevo mensaje de contacto: ' . $subject;
                
                $emailBody = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
                    <h2 style="color: #333;">Nuevo mensaje de contacto</h2>
                    
                    <p><strong>Nombre:</strong> ' . htmlspecialchars($name) . '</p>
                    <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                    <p><strong>Asunto:</strong> ' . htmlspecialchars($subject) . '</p>
                    
                    <div style="background-color: #f9f9f9; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;">
                        <p><strong>Mensaje:</strong></p>
                        <p>' . nl2br(htmlspecialchars($message)) . '</p>
                    </div>
                    
                    <p style="color: #777; font-size: 12px; text-align: center; margin-top: 30px;">
                        Este es un mensaje automático enviado desde el formulario de contacto de ' . getSetting('site_name', 'Portal de Noticias') . '.
                    </p>
                </div>
                ';
                
                sendEmail($adminEmail, $emailSubject, $emailBody);
                
                // Generar la respuesta automática al usuario
                $autoReplySubject = 'Hemos recibido tu mensaje - ' . getSetting('site_name', 'Portal de Noticias');
                
                $autoReplyBody = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <img src="' . SITE_URL . '/' . getSetting('logo', 'assets/img/logo.png') . '" alt="Logo" style="max-width: 200px;">
                    </div>
                    
                    <h2 style="color: #333;">¡Gracias por contactarnos!</h2>
                    
                    <p>Hola ' . htmlspecialchars($name) . ',</p>
                    
                    <p>Hemos recibido tu mensaje y queremos agradecerte por ponerte en contacto con nosotros. Un miembro de nuestro equipo revisará tu consulta y te responderá lo antes posible.</p>
                    
                    <div style="background-color: #f9f9f9; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;">
                        <p><strong>Asunto:</strong> ' . htmlspecialchars($subject) . '</p>
                        <p><strong>Mensaje:</strong></p>
                        <p>' . nl2br(htmlspecialchars($message)) . '</p>
                    </div>
                    
                    <p>Si tienes alguna otra pregunta o necesitas asistencia adicional, no dudes en contactarnos nuevamente.</p>
                    
                    <p>Saludos cordiales,<br>
                    El equipo de ' . getSetting('site_name', 'Portal de Noticias') . '</p>
                    
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    
                    <p style="color: #777; font-size: 12px; text-align: center; margin-top: 30px;">
                        Este es un mensaje automático, por favor no respondas a este correo.
                    </p>
                </div>
                ';
                
                sendEmail($email, $autoReplySubject, $autoReplyBody);
                
                $formSubmitted = true;
                $formSuccess = true;
                
                // Limpiar los campos del formulario
                $name = $email = $subject = $message = '';
            } else {
                $formError = 'Error al enviar el mensaje. Por favor, intenta nuevamente más tarde.';
            }
        }
    }
}

// Configuración para la página
$pageTitle = 'Contacto - ' . getSetting('site_name', 'Portal de Noticias');
$metaDescription = 'Ponte en contacto con nosotros. Estamos aquí para ayudarte.';

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Contacto</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Contenido Principal -->
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <h1 class="page-title mb-4">Contacto</h1>
            
            <?php if ($formSubmitted && $formSuccess): ?>
            <div class="alert alert-success">
                <h4 class="alert-heading">¡Mensaje enviado correctamente!</h4>
                <p>Gracias por ponerte en contacto con nosotros. Hemos recibido tu mensaje y te responderemos lo antes posible.</p>
                <p class="mb-0">Te hemos enviado una confirmación a tu dirección de correo electrónico.</p>
            </div>
            <?php else: ?>
            
            <?php if (!empty($formError)): ?>
            <div class="alert alert-danger">
                <?php echo $formError; ?>
            </div>
            <?php endif; ?>
            
            <div class="card mb-5">
                <div class="card-body">
                    <form id="contactForm" action="contact.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre completo *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Asunto *</label>
                            <input type="text" class="form-control" id="subject" name="subject" value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Mensaje *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="privacy" required>
                            <label class="form-check-label" for="privacy">
                                He leído y acepto la <a href="privacy.php" target="_blank">política de privacidad</a> *
                            </label>
                        </div>
                        
                        <button type="submit" name="send_message" class="btn btn-primary">Enviar mensaje</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="contact-info">
                <h3 class="mb-4">Información de contacto</h3>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="contact-item mb-4">
                            <div class="icon bg-primary text-white rounded-circle p-3 mb-3 d-inline-block">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <h5>Correo electrónico</h5>
                            <p><a href="mailto:<?php echo getSetting('email_contact', 'contacto@portalnoticias.com'); ?>"><?php echo getSetting('email_contact', 'contacto@portalnoticias.com'); ?></a></p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="contact-item mb-4">
                            <div class="icon bg-primary text-white rounded-circle p-3 mb-3 d-inline-block">
                                <i class="fas fa-phone"></i>
                            </div>
                            <h5>Teléfono</h5>
                            <p><a href="tel:<?php echo preg_replace('/[^0-9+]/', '', getSetting('phone_contact', '+54 (123) 456-7890')); ?>"><?php echo getSetting('phone_contact', '+54 (123) 456-7890'); ?></a></p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="contact-item mb-4">
                            <div class="icon bg-primary text-white rounded-circle p-3 mb-3 d-inline-block">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h5>Dirección</h5>
                            <p><?php echo getSetting('address', 'Av. Principal 123, Ciudad'); ?></p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="contact-item mb-4">
                            <div class="icon bg-primary text-white rounded-circle p-3 mb-3 d-inline-block">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h5>Horario de atención</h5>
                            <p>Lunes a Viernes: 9:00 - 18:00<br>Sábados: 9:00 - 13:00</p>
                        </div>
                    </div>
                </div>
                
                <div class="social-links mt-4">
                    <h5 class="mb-3">Síguenos en redes sociales</h5>
                    
                    <div class="d-flex">
                        <?php if ($facebook = getSetting('facebook')): ?>
                        <a href="<?php echo $facebook; ?>" target="_blank" class="btn btn-primary me-2">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($twitter = getSetting('twitter')): ?>
                        <a href="<?php echo $twitter; ?>" target="_blank" class="btn btn-info me-2">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($instagram = getSetting('instagram')): ?>
                        <a href="<?php echo $instagram; ?>" target="_blank" class="btn btn-danger me-2">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($youtube = getSetting('youtube')): ?>
                        <a href="<?php echo $youtube; ?>" target="_blank" class="btn btn-danger me-2">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mapa opcional -->
    <div class="map-container mt-5">
        <h3 class="mb-4">Nuestra ubicación</h3>
        <div class="ratio ratio-21x9">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3401.6031809667944!2d-61.48416852358496!3d-31.50173517547232!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x95b5668d23183909%3A0xe2a7b3dbf4efc15b!2sAvellaneda%2C%20Santa%20Fe!5e0!3m2!1ses-419!2sar!4v1716684175071!5m2!1ses-419!2sar" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" class="border-0"></iframe>
        </div>
    </div>
</div>

<!-- Validación con JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contactForm');
    
    if (form) {
        form.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Validar nombre
            const name = document.getElementById('name');
            if (!name.value.trim()) {
                isValid = false;
                name.classList.add('is-invalid');
            } else {
                name.classList.remove('is-invalid');
            }
            
            // Validar email
            const email = document.getElementById('email');
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email.value.trim() || !emailPattern.test(email.value.trim())) {
                isValid = false;
                email.classList.add('is-invalid');
            } else {
                email.classList.remove('is-invalid');
            }
            
            // Validar asunto
            const subject = document.getElementById('subject');
            if (!subject.value.trim()) {
                isValid = false;
                subject.classList.add('is-invalid');
            } else {
                subject.classList.remove('is-invalid');
            }
            
            // Validar mensaje
            const message = document.getElementById('message');
            if (!message.value.trim() || message.value.trim().length < 10) {
                isValid = false;
                message.classList.add('is-invalid');
            } else {
                message.classList.remove('is-invalid');
            }
            
            // Validar checkbox de privacidad
            const privacy = document.getElementById('privacy');
            if (!privacy.checked) {
                isValid = false;
                privacy.classList.add('is-invalid');
            } else {
                privacy.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                event.preventDefault();
                
                // Mostrar mensaje de error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger mt-3';
                errorDiv.textContent = 'Por favor, completa correctamente todos los campos marcados.';
                
                const existingError = form.querySelector('.alert');
                if (existingError) {
                    form.removeChild(existingError);
                }
                
                form.prepend(errorDiv);
                
                // Scroll al inicio del formulario
                window.scrollTo({ top: form.offsetTop - 100, behavior: 'smooth' });
            }
        });
        
        // Eliminar validación visual al cambiar el valor
        const inputs = form.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
    }
});
</script>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>