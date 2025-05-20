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

// Obtener información del sitio
$siteName = getSetting('site_name', 'Portal de Noticias');
$companyName = getSetting('company_name', 'Empresa de Medios S.A.');
$contactEmail = getSetting('email_contact', 'contacto@portalnoticias.com');

// Fecha de última actualización
$lastUpdate = '10 de mayo de 2025';

// Configuración para la página
$pageTitle = 'Política de Privacidad - ' . $siteName;
$metaDescription = 'Política de privacidad y protección de datos de ' . $siteName;

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Política de Privacidad</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Contenido Principal -->
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <h1 class="page-title mb-4">Política de Privacidad</h1>
            
            <div class="alert alert-info mb-4">
                <p class="mb-0"><strong>Última actualización:</strong> <?php echo $lastUpdate; ?></p>
            </div>
            
            <div class="privacy-content">
                <p class="lead">
                    En <?php echo $siteName; ?> nos comprometemos a proteger y respetar tu privacidad. Esta política establece cómo recopilamos, tratamos y protegemos la información personal que nos proporcionas cuando utilizas nuestro sitio web.
                </p>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">1. Información que recopilamos</h2>
                    </div>
                    <div class="card-body">
                        <p>Podemos recopilar los siguientes tipos de información:</p>
                        
                        <h5>1.1 Información proporcionada voluntariamente</h5>
                        <ul>
                            <li>Información de registro: nombre, dirección de correo electrónico, contraseña (cuando creas una cuenta)</li>
                            <li>Información de perfil: foto de perfil, biografía y enlaces a redes sociales (opcional)</li>
                            <li>Información de comunicación: detalles proporcionados al contactarnos, comentar en noticias o participar en encuestas</li>
                            <li>Información de suscripción: dirección de correo electrónico y preferencias para recibir nuestro newsletter</li>
                        </ul>
                        
                        <h5>1.2 Información recopilada automáticamente</h5>
                        <ul>
                            <li>Información técnica: dirección IP, tipo y versión del navegador, zona horaria, tipos y versiones de plugins del navegador, sistema operativo y plataforma</li>
                            <li>Información de uso: páginas visitadas, tiempo de permanencia, flujo de navegación, términos de búsqueda utilizados</li>
                            <li>Información de ubicación: ubicación general basada en la dirección IP</li>
                        </ul>
                        
                        <h5>1.3 Cookies y tecnologías similares</h5>
                        <p>Utilizamos cookies y tecnologías similares para mejorar tu experiencia en nuestro sitio. Puedes configurar tu navegador para rechazar todas o algunas cookies, pero esto puede afectar a ciertas funcionalidades de nuestro sitio.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">2. Cómo utilizamos tu información</h2>
                    </div>
                    <div class="card-body">
                        <p>Utilizamos la información que recopilamos para:</p>
                        <ul>
                            <li>Proporcionar, mantener y mejorar nuestros servicios</li>
                            <li>Gestionar tu cuenta y proporcionar soporte al cliente</li>
                            <li>Personalizar tu experiencia y mostrarte contenido relevante</li>
                            <li>Procesar y responder a tus comentarios y consultas</li>
                            <li>Enviar newsletters, actualizaciones y comunicaciones relevantes (si te has suscrito)</li>
                            <li>Analizar y mejorar la eficacia de nuestro sitio web</li>
                            <li>Detectar y prevenir actividades fraudulentas o no autorizadas</li>
                            <li>Cumplir con nuestras obligaciones legales</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">3. Compartición de datos</h2>
                    </div>
                    <div class="card-body">
                        <p>No vendemos ni alquilamos tu información personal a terceros. Sin embargo, podemos compartir tu información en las siguientes circunstancias:</p>
                        
                        <h5>3.1 Proveedores de servicios</h5>
                        <p>Compartimos datos con proveedores de servicios de confianza que nos ayudan a operar y mejorar nuestro sitio web (por ejemplo, servicios de alojamiento, análisis de datos, procesamiento de pagos).</p>
                        
                        <h5>3.2 Cumplimiento legal</h5>
                        <p>Podemos divulgar información cuando sea necesario para cumplir con una obligación legal, proteger nuestros derechos o la seguridad de nuestros usuarios.</p>
                        
                        <h5>3.3 Con consentimiento</h5>
                        <p>Podemos compartir información con terceros cuando nos hayas dado tu consentimiento para hacerlo.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">4. Transferencias internacionales</h2>
                    </div>
                    <div class="card-body">
                        <p>La información que recopilamos puede ser transferida y almacenada en servidores ubicados fuera de tu país de residencia, donde las leyes de protección de datos pueden diferir. Tomamos medidas para garantizar que tus datos sigan estando protegidos de acuerdo con esta política.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">5. Seguridad de datos</h2>
                    </div>
                    <div class="card-body">
                        <p>Implementamos medidas de seguridad técnicas y organizativas apropiadas para proteger tu información personal contra pérdida accidental, uso indebido, alteración o acceso no autorizado. Sin embargo, ninguna transmisión de Internet o almacenamiento electrónico es completamente seguro, por lo que no podemos garantizar su seguridad absoluta.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">6. Tus derechos de privacidad</h2>
                    </div>
                    <div class="card-body">
                        <p>Dependiendo de tu ubicación, puedes tener los siguientes derechos:</p>
                        <ul>
                            <li><strong>Acceso:</strong> Derecho a acceder a tus datos personales.</li>
                            <li><strong>Rectificación:</strong> Derecho a corregir datos inexactos o incompletos.</li>
                            <li><strong>Eliminación:</strong> Derecho a solicitar la eliminación de tus datos personales.</li>
                            <li><strong>Restricción:</strong> Derecho a solicitar la limitación del procesamiento de tus datos.</li>
                            <li><strong>Portabilidad:</strong> Derecho a recibir tus datos en un formato estructurado y transferirlos a otro controlador.</li>
                            <li><strong>Objeción:</strong> Derecho a oponerte al procesamiento de tus datos.</li>
                            <li><strong>Retirar consentimiento:</strong> Derecho a retirar tu consentimiento en cualquier momento.</li>
                        </ul>
                        
                        <p>Para ejercer estos derechos, contáctanos a través de <a href="mailto:<?php echo $contactEmail; ?>"><?php echo $contactEmail; ?></a>. Responderemos a tu solicitud dentro del plazo legal aplicable.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">7. Retención de datos</h2>
                    </div>
                    <div class="card-body">
                        <p>Conservamos tu información personal solo durante el tiempo necesario para cumplir con los fines descritos en esta política, a menos que la ley exija un período de retención más largo.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">8. Menores</h2>
                    </div>
                    <div class="card-body">
                        <p>Nuestro sitio web no está dirigido a personas menores de 16 años, y no recopilamos intencionalmente información personal de menores. Si tienes menos de 16 años, no debes proporcionar ninguna información personal. Si eres padre/madre o tutor y crees que tu hijo/a nos ha proporcionado información personal, contáctanos para eliminarla.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">9. Enlaces a sitios de terceros</h2>
                    </div>
                    <div class="card-body">
                        <p>Nuestro sitio web puede contener enlaces a sitios web de terceros. No somos responsables de las prácticas de privacidad o el contenido de estos sitios. Te recomendamos que revises las políticas de privacidad de cualquier sitio web que visites.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">10. Cambios en esta política</h2>
                    </div>
                    <div class="card-body">
                        <p>Podemos actualizar esta política de privacidad periódicamente. La versión más reciente estará siempre disponible en nuestro sitio web, y te notificaremos sobre cambios significativos mediante un aviso visible en el sitio o por correo electrónico.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">11. Contacto</h2>
                    </div>
                    <div class="card-body">
                        <p>Si tienes preguntas o inquietudes sobre esta política de privacidad o sobre cómo manejamos tus datos personales, contáctanos a:</p>
                        
                        <address class="mt-3">
                            <strong><?php echo $companyName; ?></strong><br>
                            <?php echo getSetting('address', 'Av. Principal 123, Ciudad'); ?><br>
                            Email: <a href="mailto:<?php echo $contactEmail; ?>"><?php echo $contactEmail; ?></a><br>
                            Teléfono: <?php echo getSetting('phone_contact', '+54 (123) 456-7890'); ?>
                        </address>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">12. Consentimiento</h2>
                    </div>
                    <div class="card-body">
                        <p>Al utilizar nuestro sitio web, aceptas esta política de privacidad. Si no estás de acuerdo con esta política, por favor, no utilices nuestro sitio.</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-5 text-center">
                <a href="terms.php" class="btn btn-outline-primary me-2">Ver Términos y Condiciones</a>
                <a href="contact.php" class="btn btn-primary">Contactar</a>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>