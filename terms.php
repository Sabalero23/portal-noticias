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
$pageTitle = 'Términos y Condiciones - ' . $siteName;
$metaDescription = 'Términos y condiciones de uso del sitio web ' . $siteName;

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Términos y Condiciones</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Contenido Principal -->
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <h1 class="page-title mb-4">Términos y Condiciones</h1>
            
            <div class="alert alert-info mb-4">
                <p class="mb-0"><strong>Última actualización:</strong> <?php echo $lastUpdate; ?></p>
            </div>
            
            <div class="terms-content">
                <p class="lead">
                    Bienvenido a <?php echo $siteName; ?>. Estos términos y condiciones describen las reglas y regulaciones para el uso de nuestro sitio web.
                </p>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">1. Introducción</h2>
                    </div>
                    <div class="card-body">
                        <p>Al acceder y utilizar este sitio web, aceptas cumplir y estar sujeto a los siguientes términos y condiciones de uso. Si no estás de acuerdo con alguno de estos términos, te recomendamos que no utilices nuestro sitio.</p>
                        
                        <p>Estos términos se aplican a todo el sitio web <?php echo $siteName; ?> y a cualquier correo electrónico u otro tipo de comunicación entre tú y <?php echo $companyName; ?>.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">2. Definiciones</h2>
                    </div>
                    <div class="card-body">
                        <p>A lo largo de estos términos y condiciones, los siguientes términos tendrán los significados asignados a continuación:</p>
                        
                        <ul>
                            <li><strong>"Nosotros", "nuestro" y "nos"</strong> se refiere a <?php echo $companyName; ?>.</li>
                            <li><strong>"Plataforma"</strong> se refiere al sitio web <?php echo $siteName; ?>.</li>
                            <li><strong>"Servicio"</strong> se refiere a los servicios ofrecidos a través de nuestra plataforma.</li>
                            <li><strong>"Contenido"</strong> se refiere a todo el texto, imágenes, vídeos, gráficos y otros materiales que aparecen en nuestra plataforma.</li>
                            <li><strong>"Usuario", "tú" y "tu"</strong> se refiere a la persona que accede a nuestra plataforma y acepta estos términos.</li>
                            <li><strong>"Cuenta"</strong> se refiere al registro personal que permite el acceso a funcionalidades específicas de nuestra plataforma.</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">3. Licencia de uso</h2>
                    </div>
                    <div class="card-body">
                        <p>A menos que se indique lo contrario, <?php echo $companyName; ?> y/o sus licenciantes poseen los derechos de propiedad intelectual de todo el material en <?php echo $siteName; ?>.</p>
                        
                        <p>Se te concede una licencia limitada únicamente para ver el material contenido en este sitio web. Está prohibido:</p>
                        
                        <ul>
                            <li>Republicar material de <?php echo $siteName; ?></li>
                            <li>Vender, alquilar o sublicenciar material de <?php echo $siteName; ?></li>
                            <li>Reproducir, duplicar o copiar material de <?php echo $siteName; ?></li>
                            <li>Redistribuir contenido de <?php echo $siteName; ?></li>
                        </ul>
                        
                        <p>Este acuerdo comenzará en la fecha presente. Nuestros Términos y Condiciones fueron creados con la ayuda de un generador de Términos y Condiciones.</p>
                        
                        <p>Partes de este sitio web ofrecen a los usuarios la oportunidad de publicar e intercambiar opiniones e información en determinadas áreas. <?php echo $companyName; ?> no filtra, edita, publica o revisa los comentarios antes de su presencia en el sitio web. Los comentarios no reflejan los puntos de vista y opiniones de <?php echo $companyName; ?>, sus agentes y/o afiliados. Los comentarios reflejan los puntos de vista y opiniones de la persona que publica sus puntos de vista y opiniones.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">4. Contenido de usuario</h2>
                    </div>
                    <div class="card-body">
                        <p>En estos Términos y Condiciones, "Contenido del Usuario" significa cualquier audio, video, texto, imágenes u otro material que elijas mostrar en este sitio web. Al mostrar tu Contenido de Usuario, estás otorgando a <?php echo $companyName; ?> una licencia no exclusiva, mundial, irrevocable, libre de regalías y sublicenciable para usar, reproducir, adaptar, publicar, traducir y distribuir en cualquier medio.</p>
                        
                        <p>Tu Contenido de Usuario debe ser tuyo y no debe infringir los derechos de terceros. <?php echo $companyName; ?> se reserva el derecho de eliminar cualquier Contenido de Usuario de este sitio web en cualquier momento sin previo aviso.</p>
                        
                        <p>Nos reservamos el derecho de monitorear todos los comentarios y eliminar aquellos que puedan considerarse inapropiados, ofensivos o que incumplan estos Términos y Condiciones.</p>
                        
                        <h5>Garantizas y declaras que:</h5>
                        <ul>
                            <li>Tienes derecho a publicar comentarios en nuestro sitio web y tienes todas las licencias y consentimientos necesarios para hacerlo.</li>
                            <li>Los comentarios no invaden ningún derecho de propiedad intelectual, incluidos, entre otros, los derechos de autor, patentes o marcas comerciales de terceros.</li>
                            <li>Los comentarios no contienen ningún material difamatorio, calumnioso, ofensivo, indecente o ilegal que sea una invasión de la privacidad.</li>
                            <li>Los comentarios no se utilizarán para solicitar o promover negocios o actividades comerciales o ilegales.</li>
                        </ul>
                        
                        <p>Por la presente, otorgas a <?php echo $companyName; ?> una licencia no exclusiva para usar, reproducir, editar y autorizar a otros a usar, reproducir y editar cualquiera de tus comentarios en cualquier forma, formato o medio.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">5. Responsabilidad por el contenido</h2>
                    </div>
                    <div class="card-body">
                        <p>No seremos responsables de ningún contenido que aparezca en tu sitio web. Aceptas protegernos y defendernos contra todas las reclamaciones que surjan en tu sitio web. Ningún enlace debe aparecer en ningún sitio web que pueda interpretarse como difamatorio, obsceno o criminal, o que infrinja, viole o defienda la infracción u otra violación de los derechos de terceros.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">6. Reserva de derechos</h2>
                    </div>
                    <div class="card-body">
                        <p>Nos reservamos el derecho de solicitarte que elimines todos los enlaces o cualquier enlace particular a nuestro sitio web. Apruebas eliminar inmediatamente todos los enlaces a nuestro sitio web cuando así te lo solicitemos. También nos reservamos el derecho de modificar estos términos y condiciones y su política de enlaces en cualquier momento. Al vincular continuamente a nuestro sitio web, aceptas estar vinculado y seguir estos términos y condiciones de vinculación.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">7. Eliminación de enlaces de nuestro sitio web</h2>
                    </div>
                    <div class="card-body">
                        <p>Si encuentras algún enlace en nuestro sitio web que sea ofensivo por cualquier motivo, puedes contactarnos e informarnos en cualquier momento. Consideraremos solicitudes para eliminar enlaces, pero no estamos obligados a hacerlo ni a responder directamente.</p>
                        
                        <p>No aseguramos que la información en este sitio web sea correcta, no garantizamos su integridad o precisión; ni nos comprometemos a asegurar que el sitio web permanezca disponible o que el material en el sitio web se mantenga actualizado.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">8. Exención de responsabilidad</h2>
                    </div>
                    <div class="card-body">
                        <p>En la medida máxima permitida por la ley aplicable, excluimos todas las representaciones, garantías y condiciones relacionadas con nuestro sitio web y el uso de este sitio web. Nada en este descargo de responsabilidad:</p>
                        
                        <ul>
                            <li>Limitará o excluirá nuestra o tu responsabilidad por muerte o lesiones personales;</li>
                            <li>Limitará o excluirá nuestra o tu responsabilidad por fraude o tergiversación fraudulenta;</li>
                            <li>Limitará cualquiera de nuestras o tus responsabilidades de cualquier manera que no esté permitida por la ley aplicable; o</li>
                            <li>Excluirá cualquiera de nuestras o tus responsabilidades que no puedan ser excluidas según la ley aplicable.</li>
                        </ul>
                        
                        <p>Las limitaciones y prohibiciones de responsabilidad establecidas en esta sección y en otras partes de este descargo de responsabilidad: (a) están sujetas al párrafo anterior; y (b) rigen todas las responsabilidades que surjan en virtud del descargo de responsabilidad, incluidas las responsabilidades contractuales, extracontractuales y por incumplimiento de la obligación legal.</p>
                        
                        <p>En la medida en que el sitio web y la información y los servicios en el sitio web se proporcionen de forma gratuita, no seremos responsables de ninguna pérdida o daño de ningún tipo.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">9. Modificaciones</h2>
                    </div>
                    <div class="card-body">
                        <p>Podemos revisar estos términos de servicio del sitio web en cualquier momento sin previo aviso. Al usar este sitio web, aceptas estar sujeto a la versión actual de estos términos de servicio.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">10. Ley aplicable</h2>
                    </div>
                    <div class="card-body">
                        <p>Estos términos y condiciones se rigen e interpretan de acuerdo con las leyes de Argentina, y te sometes irrevocablemente a la jurisdicción exclusiva de los tribunales de esa provincia o localidad.</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="mb-0 fs-4">11. Contacto</h2>
                    </div>
                    <div class="card-body">
                        <p>Si tienes preguntas o inquietudes sobre estos Términos y Condiciones, contáctanos a:</p>
                        
                        <address class="mt-3">
                            <strong><?php echo $companyName; ?></strong><br>
                            <?php echo getSetting('address', 'Av. Principal 123, Ciudad'); ?><br>
                            Email: <a href="mailto:<?php echo $contactEmail; ?>"><?php echo $contactEmail; ?></a><br>
                            Teléfono: <?php echo getSetting('phone_contact', '+54 (123) 456-7890'); ?>
                        </address>
                    </div>
                </div>
            </div>
            
            <div class="mt-5 text-center">
                <a href="privacy.php" class="btn btn-outline-primary me-2">Ver Política de Privacidad</a>
                <a href="contact.php" class="btn btn-primary">Contactar</a>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>