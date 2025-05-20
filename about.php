<?php
// Definir ruta base
define('BASE_PATH', __DIR__);

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Inicializar la DB
$db = Database::getInstance();

// Obtener categorías para el menú
$db = Database::getInstance();
$categories = $db->fetchAll("SELECT id, name, slug FROM categories ORDER BY name");

// Obtener información de la empresa
$siteName = getSetting('site_name', 'Portal de Noticias');
$siteDescription = getSetting('site_description', 'El portal de noticias más actualizado');
$companyName = getSetting('company_name', 'Empresa de Medios S.A.');

// Obtener equipo editorial (usuarios con roles de admin, editor o author)
$team = $db->fetchAll(
    "SELECT id, name, username, email, bio, role, avatar, twitter, facebook, instagram, linkedin
     FROM users
     WHERE status = 'active' AND role IN ('admin', 'editor', 'author')
     ORDER BY FIELD(role, 'admin', 'editor', 'author'), name"
);

// Configuración para la página
$pageTitle = 'Quiénes somos - ' . $siteName;
$metaDescription = 'Conoce más sobre ' . $siteName . ' y nuestro equipo editorial.';

// Incluir encabezado
include 'includes/header.php';
?>

<!-- Migas de pan -->
<div class="bg-light py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Quiénes somos</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Contenido Principal -->
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <h1 class="page-title mb-4">Quiénes somos</h1>
            
            <!-- Información sobre el portal -->
            <div class="about-intro mb-5">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <img src="assets/img/about/office.jpg" alt="<?php echo $siteName; ?>" class="img-fluid rounded shadow">
                    </div>
                    <div class="col-md-6">
                        <h2>Nuestra historia</h2>
                        <p class="lead"><?php echo $siteDescription; ?></p>
                        <p>Somos un medio de comunicación comprometido con la verdad y la objetividad. Desde nuestra fundación, nos hemos dedicado a brindar información de calidad y de interés para nuestra comunidad, con un enfoque riguroso y profesional.</p>
                        <p>En <?php echo $siteName; ?> creemos en el periodismo de calidad, la investigación profunda y la difusión responsable de la información. Nuestro objetivo es mantener a nuestros lectores informados sobre los acontecimientos más relevantes a nivel local, nacional e internacional.</p>
                    </div>
                </div>
            </div>
            
            <!-- Nuestra Misión, Visión y Valores -->
            <div class="mission-values bg-light p-4 rounded mb-5">
                <div class="row">
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="icon-circle bg-primary text-white mb-3 mx-auto">
                                    <i class="fas fa-bullseye"></i>
                                </div>
                                <h3 class="card-title">Misión</h3>
                                <p class="card-text">Proporcionar información veraz, oportuna y relevante que contribuya al conocimiento, análisis y reflexión de nuestros lectores, promoviendo el debate constructivo y el desarrollo de una sociedad mejor informada.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="icon-circle bg-success text-white mb-3 mx-auto">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <h3 class="card-title">Visión</h3>
                                <p class="card-text">Ser reconocidos como un referente informativo de confianza, innovación y calidad periodística, que se adapta a las nuevas tecnologías manteniendo siempre los valores fundamentales del periodismo profesional.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="icon-circle bg-info text-white mb-3 mx-auto">
                                    <i class="fas fa-star"></i>
                                </div>
                                <h3 class="card-title">Valores</h3>
                                <ul class="list-unstyled text-start">
                                    <li><i class="fas fa-check-circle text-success me-2"></i> Veracidad y rigor informativo</li>
                                    <li><i class="fas fa-check-circle text-success me-2"></i> Independencia editorial</li>
                                    <li><i class="fas fa-check-circle text-success me-2"></i> Responsabilidad social</li>
                                    <li><i class="fas fa-check-circle text-success me-2"></i> Respeto a la diversidad</li>
                                    <li><i class="fas fa-check-circle text-success me-2"></i> Innovación constante</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Nuestro Equipo -->
            <div class="team-section mb-5">
                <h2 class="section-title text-center mb-4">Nuestro Equipo Editorial</h2>
                <p class="text-center mb-5">Contamos con un equipo de profesionales comprometidos con la calidad informativa y la ética periodística.</p>
                
                <div class="row">
                    <?php if (empty($team)): ?>
                    <div class="col-12 text-center">
                        <div class="alert alert-info">
                            No hay información disponible sobre el equipo editorial en este momento.
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($team as $member): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card team-card h-100">
                                <div class="card-body text-center">
                                    <div class="team-avatar mb-3">
                                        <img src="<?php echo $member['avatar'] ?: 'assets/img/authors/default.jpg'; ?>" alt="<?php echo $member['name']; ?>" class="rounded-circle">
                                    </div>
                                    <h4 class="card-title"><?php echo $member['name']; ?></h4>
                                    <p class="text-muted">
                                        <?php 
                                        switch ($member['role']) {
                                            case 'admin':
                                                echo 'Director Editorial';
                                                break;
                                            case 'editor':
                                                echo 'Editor';
                                                break;
                                            case 'author':
                                                echo 'Periodista';
                                                break;
                                            default:
                                                echo 'Colaborador';
                                        }
                                        ?>
                                    </p>
                                    <?php if ($member['bio']): ?>
                                    <p class="card-text"><?php echo truncateString($member['bio'], 150); ?></p>
                                    <?php else: ?>
                                    <p class="card-text">Profesional del periodismo comprometido con la verdad y la calidad informativa.</p>
                                    <?php endif; ?>
                                    
                                    <div class="team-social mt-3">
                                        <?php if ($member['twitter']): ?>
                                        <a href="<?php echo $member['twitter']; ?>" target="_blank" class="social-icon">
                                            <i class="fab fa-twitter"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($member['facebook']): ?>
                                        <a href="<?php echo $member['facebook']; ?>" target="_blank" class="social-icon">
                                            <i class="fab fa-facebook-f"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($member['instagram']): ?>
                                        <a href="<?php echo $member['instagram']; ?>" target="_blank" class="social-icon">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($member['linkedin']): ?>
                                        <a href="<?php echo $member['linkedin']; ?>" target="_blank" class="social-icon">
                                            <i class="fab fa-linkedin-in"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Nuestra Trayectoria -->
            <div class="timeline-section mb-5">
                <h2 class="section-title text-center mb-4">Nuestra Trayectoria</h2>
                
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>2015</h4>
                            <p>Fundación del portal de noticias con un pequeño equipo de periodistas comprometidos con la información local.</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>2017</h4>
                            <p>Expansión de la cobertura a noticias nacionales e internacionales. Crecimiento del equipo editorial.</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>2019</h4>
                            <p>Implementación de la versión móvil y desarrollo de nuevas secciones especializadas.</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>2021</h4>
                            <p>Renovación completa de la plataforma y lanzamiento de la aplicación móvil para Android e iOS.</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>2023</h4>
                            <p>Consolidación como uno de los portales de noticias más visitados de la región, con más de un millón de visitas mensuales.</p>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>2025</h4>
                            <p>Actualidad: Continuamos innovando y mejorando nuestra plataforma para ofrecer la mejor experiencia informativa.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="stats-section bg-primary text-white p-5 rounded mb-5">
                <h2 class="text-center mb-5">Nuestros Números</h2>
                
                <div class="row text-center">
                    <div class="col-md-3 col-6 mb-4 mb-md-0">
                        <div class="stat-item">
                            <div class="stat-number">+1M</div>
                            <div class="stat-label">Visitas Mensuales</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-4 mb-md-0">
                        <div class="stat-item">
                            <div class="stat-number">+10K</div>
                            <div class="stat-label">Artículos Publicados</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6">
                        <div class="stat-item">
                            <div class="stat-number">+20</div>
                            <div class="stat-label">Periodistas</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6">
                        <div class="stat-item">
                            <div class="stat-number">9</div>
                            <div class="stat-label">Categorías</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Información Corporativa -->
            <div class="corporate-info mb-5">
                <h2 class="section-title mb-4">Información Corporativa</h2>
                
                <div class="card">
                    <div class="card-body">
                        <p><strong>Razón Social:</strong> <?php echo $companyName; ?></p>
                        <p><strong>Dirección:</strong> <?php echo getSetting('address', 'Av. Principal 123, Ciudad'); ?></p>
                        <p><strong>Correo Electrónico:</strong> <?php echo getSetting('email_contact', 'contacto@portalnoticias.com'); ?></p>
                        <p><strong>Teléfono:</strong> <?php echo getSetting('phone_contact', '+54 (123) 456-7890'); ?></p>
                        
                        <h5 class="mt-4 mb-3">Documentos Legales</h5>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="privacy.php" class="text-decoration-none">Política de Privacidad</a>
                                <span class="badge bg-primary rounded-pill"><i class="fas fa-file-alt"></i></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="terms.php" class="text-decoration-none">Términos y Condiciones</a>
                                <span class="badge bg-primary rounded-pill"><i class="fas fa-file-alt"></i></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Contáctanos -->
            <div class="contact-cta text-center mb-5">
                <h2 class="mb-4">¿Tienes alguna pregunta o sugerencia?</h2>
                <p class="lead mb-4">Estamos para escucharte. No dudes en ponerte en contacto con nosotros.</p>
                <a href="contact.php" class="btn btn-primary btn-lg">Contáctanos</a>
            </div>
        </div>
    </div>
</div>

<!-- CSS personalizado para la página -->
<style>
.icon-circle {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.team-avatar img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border: 3px solid #f8f9fa;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
}

.social-icon {
    display: inline-flex;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: #f8f9fa;
    color: #333;
    align-items: center;
    justify-content: center;
    margin: 0 5px;
    transition: all 0.3s ease;
}

.social-icon:hover {
    background-color: #2196F3;
    color: white;
}

.timeline {
    position: relative;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.timeline::after {
    content: '';
    position: absolute;
    width: 4px;
    background-color: #ddd;
    top: 0;
    bottom: 0;
    left: 50%;
    margin-left: -2px;
}

.timeline-item {
    padding: 10px 40px;
    position: relative;
    width: 50%;
    left: 0;
    margin-bottom: 30px;
}

.timeline-item:nth-child(odd) {
    left: 50%;
}

.timeline-dot {
    position: absolute;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background-color: #2196F3;
    top: 15px;
    left: -10px;
    z-index: 1;
}

.timeline-item:nth-child(odd) .timeline-dot {
    left: -10px;
}

.timeline-content {
    padding: 20px;
    background-color: white;
    border-radius: 6px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 10px;
}

.stat-label {
    font-size: 16px;
    text-transform: uppercase;
}

@media screen and (max-width: 767px) {
    .timeline::after {
        left: 20px;
    }
    
    .timeline-item {
        width: 100%;
        padding-left: 50px;
        padding-right: 0;
        left: 0 !important;
    }
    
    .timeline-item:nth-child(odd) .timeline-dot {
        left: 11px;
    }
    
    .timeline-dot {
        left: 11px;
    }
}
</style>

<?php
// Incluir pie de página
include 'includes/footer.php';
?>