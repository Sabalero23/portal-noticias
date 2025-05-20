<?php
// Definir ruta base
define('BASE_PATH', dirname(__DIR__));
define('ADMIN_PATH', __DIR__);

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once ADMIN_PATH . '/includes/functions.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor', 'author'], '../index.php');

// Obtener información del sitio
$siteName = getSetting('site_name', 'Portal de Noticias');
$logo = getSetting('logo', 'assets/img/logo.png');

// Título de la página
$pageTitle = 'Tutorial del Sistema - Panel de Administración';
$currentMenu = 'tutorial';

// Incluir cabecera
include_once ADMIN_PATH . '/includes/header.php';
include_once ADMIN_PATH . '/includes/sidebar.php';
?>

<style>
.tutorial-section {
    margin-bottom: 2rem;
}
.tutorial-card {
    border-left: 4px solid #007bff;
    background: #f8f9fa;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-radius: 0.375rem;
}
.tutorial-card.admin {
    border-left-color: #dc3545;
}
.tutorial-card.editor {
    border-left-color: #fd7e14;
}
.tutorial-card.author {
    border-left-color: #198754;
}
.step {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 0.5rem;
}
.step-number {
    background: #007bff;
    color: white;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    font-weight: bold;
}
.keyboard-shortcut {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 0.9em;
}
.feature-highlight {
    background: #e7f3ff;
    border: 1px solid #b3d7ff;
    border-radius: 0.375rem;
    padding: 1rem;
    margin: 1rem 0;
}
.warning-box {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 0.375rem;
    padding: 1rem;
    margin: 1rem 0;
}
.tip-box {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    border-radius: 0.375rem;
    padding: 1rem;
    margin: 1rem 0;
}
</style>

<!-- Contenido principal -->
<div class="content-wrapper">
    <!-- Cabecera de página -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Tutorial del Sistema</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item active">Tutorial</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            
            <!-- Introducción -->
            <div class="card mb-4">
                <div class="card-body">
                    <h3><i class="fas fa-graduation-cap me-2"></i>Bienvenido a <?php echo $siteName; ?></h3>
                    <p class="lead">Esta guía te ayudará a dominar todas las funciones del sistema según tu rol de usuario.</p>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-user-shield fa-3x text-danger mb-3"></i>
                                <h5>Administradores</h5>
                                <p>Control total del sistema</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-user-edit fa-3x text-warning mb-3"></i>
                                <h5>Editores</h5>
                                <p>Gestión de contenido y usuarios</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-pen-alt fa-3x text-success mb-3"></i>
                                <h5>Autores</h5>
                                <p>Creación de noticias</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navegación Rápida -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4><i class="fas fa-map me-2"></i>Navegación Rápida</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Secciones Principales</h6>
                            <ul class="list-unstyled">
                                <li><a href="#primeros-pasos" class="text-decoration-none"><i class="fas fa-play me-2"></i>Primeros Pasos</a></li>
                                <li><a href="#gestion-noticias" class="text-decoration-none"><i class="fas fa-newspaper me-2"></i>Gestión de Noticias</a></li>
                                <li><a href="#categorias-tags" class="text-decoration-none"><i class="fas fa-tags me-2"></i>Categorías y Etiquetas</a></li>
                                <li><a href="#comentarios" class="text-decoration-none"><i class="fas fa-comments me-2"></i>Gestión de Comentarios</a></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Funciones Avanzadas</h6>
                            <ul class="list-unstyled">
                                <li><a href="#publicidad" class="text-decoration-none"><i class="fas fa-bullhorn me-2"></i>Sistema de Publicidad</a></li>
                                <li><a href="#encuestas" class="text-decoration-none"><i class="fas fa-poll me-2"></i>Encuestas</a></li>
                                <li><a href="#suscriptores" class="text-decoration-none"><i class="fas fa-envelope me-2"></i>Newsletter</a></li>
                                <li><a href="#configuracion" class="text-decoration-none"><i class="fas fa-cog me-2"></i>Configuración</a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="tip-box">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Tip:</strong> Usa <span class="keyboard-shortcut">Ctrl + F</span> para buscar rápidamente en esta página.
                    </div>
                </div>
            </div>

            <!-- Primeros Pasos -->
            <div id="primeros-pasos" class="tutorial-section">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-play-circle me-2"></i>Primeros Pasos</h4>
                    </div>
                    <div class="card-body">
                        <div class="tutorial-card">
                            <h5><i class="fas fa-tachometer-alt me-2"></i>Panel Principal (Dashboard)</h5>
                            <p>Al iniciar sesión, accedes al dashboard donde puedes ver:</p>
                            <ul>
                                <li><strong>Estadísticas generales:</strong> Noticias, usuarios, comentarios</li>
                                <li><strong>Actividad reciente:</strong> Últimas acciones del sistema</li>
                                <li><strong>Accesos rápidos:</strong> A las funciones más usadas</li>
                            </ul>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-bars me-2"></i>Menú de Navegación</h5>
                            <p>El menú lateral te permite acceder a todas las secciones:</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul>
                                        <li><i class="fas fa-newspaper text-primary"></i> Noticias</li>
                                        <li><i class="fas fa-tags text-info"></i> Categorías</li>
                                        <li><i class="fas fa-comments text-warning"></i> Comentarios</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul>
                                        <li><i class="fas fa-users text-success"></i> Usuarios</li>
                                        <li><i class="fas fa-bullhorn text-danger"></i> Publicidad</li>
                                        <li><i class="fas fa-cog text-secondary"></i> Configuración</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-user-circle me-2"></i>Perfil de Usuario</h5>
                            <p>Personaliza tu perfil desde el menú superior derecho:</p>
                            <div class="step">
                                <span class="step-number">1</span>
                                Haz clic en tu nombre (esquina superior derecha)
                            </div>
                            <div class="step">
                                <span class="step-number">2</span>
                                Selecciona "Mi Perfil"
                            </div>
                            <div class="step">
                                <span class="step-number">3</span>
                                Actualiza tu información, foto y redes sociales
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gestión de Noticias -->
            <div id="gestion-noticias" class="tutorial-section">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-newspaper me-2"></i>Gestión de Noticias</h4>
                    </div>
                    <div class="card-body">
                        <div class="tutorial-card">
                            <h5><i class="fas fa-plus-circle me-2"></i>Crear Nueva Noticia</h5>
                            <div class="step">
                                <span class="step-number">1</span>
                                Ve a <strong>Noticias > Añadir Nueva</strong>
                            </div>
                            <div class="step">
                                <span class="step-number">2</span>
                                Completa el <strong>título</strong> (se genera el slug automáticamente)
                            </div>
                            <div class="step">
                                <span class="step-number">3</span>
                                Escribe un <strong>extracto</strong> atractivo (máx. 500 caracteres)
                            </div>
                            <div class="step">
                                <span class="step-number">4</span>
                                Redacta el <strong>contenido</strong> usando el editor WYSIWYG
                            </div>
                            <div class="step">
                                <span class="step-number">5</span>
                                Selecciona una <strong>categoría</strong> y <strong>etiquetas</strong>
                            </div>
                            <div class="step">
                                <span class="step-number">6</span>
                                Sube una <strong>imagen destacada</strong> (formatos: JPG, PNG, WebP)
                            </div>
                            <div class="step">
                                <span class="step-number">7</span>
                                Configura <strong>opciones</strong>: destacada, última hora, comentarios
                            </div>
                            <div class="step">
                                <span class="step-number">8</span>
                                Elige el <strong>estado</strong>: Borrador, Pendiente o Publicada
                            </div>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-edit me-2"></i>Editor de Contenido</h5>
                            <p>El editor Trumbowyg ofrece herramientas completas:</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Formato de Texto</h6>
                                    <ul>
                                        <li>Negrita, cursiva, subrayado</li>
                                        <li>Títulos y subtítulos</li>
                                        <li>Listas ordenadas y no ordenadas</li>
                                        <li>Alineación de texto</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Contenido Multimedia</h6>
                                    <ul>
                                        <li>Insertar imágenes</li>
                                        <li>Enlaces externos</li>
                                        <li>Líneas horizontales</li>
                                        <li>Vista HTML directa</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-star me-2"></i>Noticias Destacadas</h5>
                            <div class="feature-highlight">
                                <p><strong>¿Qué son las noticias destacadas?</strong></p>
                                <p>Las noticias marcadas como "destacadas" aparecen en el slider principal de la portada, dándoles máxima visibilidad.</p>
                            </div>
                            <p>Para destacar una noticia:</p>
                            <ol>
                                <li>En el formulario de noticia, marca ✓ <strong>"Destacada"</strong></li>
                                <li>La noticia aparecerá automáticamente en el carousel del home</li>
                                <li>Máximo 5 noticias destacadas se muestran en el slider</li>
                            </ol>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-list me-2"></i>Estados de Noticias</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><span class="badge bg-secondary">Borrador</span></h6>
                                    <p>Noticia guardada pero no visible al público. Solo visible para el autor.</p>
                                    
                                    <h6><span class="badge bg-warning">Pendiente</span></h6>
                                    <p>Noticia enviada para revisión editorial (solo autores).</p>
                                </div>
                                <div class="col-md-6">
                                    <h6><span class="badge bg-success">Publicada</span></h6>
                                    <p>Noticia visible en el sitio web para todos los visitantes.</p>
                                    
                                    <h6><span class="badge bg-danger">Papelera</span></h6>
                                    <p>Noticia eliminada (se puede restaurar).</p>
                                </div>
                            </div>
                        </div>

                        <?php if (hasRole(['author'])): ?>
                        <div class="tutorial-card author">
                            <h5><i class="fas fa-user-edit me-2"></i>Para Autores</h5>
                            <div class="warning-box">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Nota:</strong> Como autor, solo puedes ver y editar tus propias noticias. Para publicar directamente, tu estado debe ser "Publicada" (si tienes permisos) o "Pendiente" para revisión editorial.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="step">
                                <span class="step-number">5</span>
                                Activa la encuesta cambiando estado a <strong>"Activa"</strong>
                            </div>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-chart-pie me-2"></i>Resultados de Encuestas</h5>
                            <p>Para ver los resultados:</p>
                            <div class="step">
                                <span class="step-number">1</span>
                                Ve a <strong>Encuestas > Ver Resultados</strong>
                            </div>
                            <div class="step">
                                <span class="step-number">2</span>
                                Selecciona la encuesta deseada
                            </div>
                            <div class="step">
                                <span class="step-number">3</span>
                                Revisa gráficos y porcentajes de cada opción
                            </div>
                            
                            <div class="tip-box">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>Tip:</strong> Solo puede haber una encuesta activa a la vez. Aparece automáticamente en el sidebar del sitio.
                            </div>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-cogs me-2"></i>Configurar Encuesta</h5>
                            <ul>
                                <li><strong>Estados:</strong> Activa, Inactiva, Cerrada</li>
                                <li><strong>Duración:</strong> Se puede programar inicio y fin automático</li>
                                <li><strong>Votación:</strong> Un voto por IP para evitar spam</li>
                                <li><strong>Visualización:</strong> Resultados en tiempo real</li>
                            </ul>
                        </div>

            <!-- Newsletter y Suscriptores -->
            <?php if (hasRole(['admin', 'editor'])): ?>
            <div id="suscriptores" class="tutorial-section">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-envelope me-2"></i>Newsletter y Suscriptores</h4>
                    </div>
                    <div class="card-body">
                        <div class="tutorial-card">
                            <h5><i class="fas fa-users me-2"></i>Gestión de Suscriptores</h5>
                            <p>Los visitantes pueden suscribirse desde el sitio web. Puedes:</p>
                            <ul>
                                <li><strong>Ver lista:</strong> Todos los suscriptores con estados</li>
                                <li><strong>Estados:</strong> Activo, Inactivo, Dado de baja</li>
                                <li><strong>Exportar:</strong> Lista en formato CSV o Excel</li>
                                <li><strong>Filtrar:</strong> Por estado, fecha de suscripción</li>
                            </ul>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-paper-plane me-2"></i>Envío de Newsletter</h5>
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Importante:</strong> Asegúrate de tener configurado el SMTP antes de enviar newsletters.
                            </div>
                            
                            <p>Para enviar un newsletter:</p>
                            <div class="step">
                                <span class="step-number">1</span>
                                Ve a <strong>Suscriptores > Enviar Newsletter</strong>
                            </div>
                            <div class="step">
                                <span class="step-number">2</span>
                                Redacta el <strong>asunto</strong> del email
                            </div>
                            <div class="step">
                                <span class="step-number">3</span>
                                Escribe el <strong>contenido</strong> en HTML
                            </div>
                            <div class="step">
                                <span class="step-number">4</span>
                                Envía <strong>email de prueba</strong> a ti mismo
                            </div>
                            <div class="step">
                                <span class="step-number">5</span>
                                Confirma y <strong>envía a todos</strong> los suscriptores activos
                            </div>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-chart-line me-2"></i>Análisis de Newsletter</h5>
                            <p>Métricas disponibles:</p>
                            <ul>
                                <li><strong>Envíos totales:</strong> Cantidad de emails enviados</li>
                                <li><strong>Tasa de entrega:</strong> Emails entregados vs rebotados</li>
                                <li><strong>Aperturas:</strong> Cuántos suscriptores abrieron el email</li>
                                <li><strong>Clicks:</strong> Enlaces clickeados dentro del newsletter</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Configuración del Sistema -->
            <?php if (hasRole(['admin'])): ?>
            <div id="configuracion" class="tutorial-section">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-cog me-2"></i>Configuración del Sistema</h4>
                    </div>
                    <div class="card-body">
                        <div class="tutorial-card admin">
                            <h5><i class="fas fa-info-circle me-2"></i>Configuración General</h5>
                            <p>En <strong>Configuración > General</strong> puedes modificar:</p>
                            <ul>
                                <li><strong>Nombre del sitio:</strong> Título principal</li>
                                <li><strong>Descripción:</strong> Para SEO y meta tags</li>
                                <li><strong>Logo:</strong> Imagen del sitio</li>
                                <li><strong>Favicon:</strong> Icono del navegador</li>
                                <li><strong>Información de contacto:</strong> Email, teléfono, dirección</li>
                            </ul>
                        </div>

                        <div class="tutorial-card admin">
                            <h5><i class="fas fa-share-alt me-2"></i>Redes Sociales</h5>
                            <p>Configura enlaces a redes sociales:</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul>
                                        <li><i class="fab fa-facebook text-primary"></i> Facebook</li>
                                        <li><i class="fab fa-twitter text-info"></i> Twitter</li>
                                        <li><i class="fab fa-instagram text-warning"></i> Instagram</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul>
                                        <li><i class="fab fa-youtube text-danger"></i> YouTube</li>
                                        <li><i class="fab fa-linkedin text-primary"></i> LinkedIn</li>
                                        <li><i class="fab fa-tiktok text-dark"></i> TikTok</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="tutorial-card admin">
                            <h5><i class="fas fa-plug me-2"></i>APIs y Servicios</h5>
                            <p>Configuración de servicios externos:</p>
                            
                            <h6><i class="fas fa-cloud-sun me-2"></i>API del Clima</h6>
                            <ul>
                                <li><strong>Proveedor:</strong> OpenWeatherMap</li>
                                <li><strong>Configurar:</strong> API key, ciudad, unidades</li>
                                <li><strong>Ubicación:</strong> Sidebar del sitio web</li>
                            </ul>

                            <h6><i class="fas fa-chart-bar me-2"></i>Google Analytics</h6>
                            <ul>
                                <li><strong>Tracking ID:</strong> GA-XXXXXXXX-X</li>
                                <li><strong>Eventos:</strong> Clicks en noticias, anuncios</li>
                                <li><strong>Páginas:</strong> Seguimiento automático</li>
                            </ul>

                            <h6><i class="fas fa-envelope me-2"></i>SMTP Email</h6>
                            <ul>
                                <li><strong>Servidor:</strong> Gmail, Outlook, servidor propio</li>
                                <li><strong>Puerto:</strong> 587 (TLS) o 465 (SSL)</li>
                                <li><strong>Autenticación:</strong> Usuario y contraseña</li>
                            </ul>
                        </div>

                        <div class="tutorial-card admin">
                            <h5><i class="fas fa-palette me-2"></i>Temas y Apariencia</h5>
                            <p>Personaliza la apariencia del sitio:</p>
                            <div class="step">
                                <span class="step-number">1</span>
                                Ve a <strong>Configuración > Temas</strong>
                            </div>
                            <div class="step">
                                <span class="step-number">2</span>
                                Explora temas disponibles con vista previa
                            </div>
                            <div class="step">
                                <span class="step-number">3</span>
                                Activa el tema deseado
                            </div>
                            <div class="step">
                                <span class="step-number">4</span>
                                Los cambios se aplican inmediatamente
                            </div>

                            <div class="feature-highlight">
                                <h6>Temas Disponibles:</h6>
                                <ul class="mb-0">
                                    <li><strong>Default:</strong> Diseño clásico de 3 columnas</li>
                                    <li><strong>Modern:</strong> Estilo contemporáneo</li>
                                    <li><strong>Minimalist:</strong> Enfoque limpio</li>
                                    <li><strong>Technology:</strong> Especializado en tech</li>
                                    <li><strong>Sports:</strong> Optimizado para deportes</li>
                                </ul>
                            </div>
                        </div>

                        <div class="tutorial-card admin">
                            <h5><i class="fas fa-shield-alt me-2"></i>Seguridad y Mantenimiento</h5>
                            <p>Configuraciones de seguridad:</p>
                            <ul>
                                <li><strong>Modo mantenimiento:</strong> Deshabilita el sitio temporalmente</li>
                                <li><strong>Comentarios:</strong> Activar/desactivar globally</li>
                                <li><strong>Moderación:</strong> Aprobación automática o manual</li>
                                <li><strong>Logs:</strong> Registro de actividad administrativa</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Funciones Adicionales -->
            <div id="funciones-adicionales" class="tutorial-section">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-tools me-2"></i>Funciones Adicionales</h4>
                    </div>
                    <div class="card-body">
                        <div class="tutorial-card">
                            <h5><i class="fas fa-search me-2"></i>Búsqueda Avanzada</h5>
                            <p>El sistema incluye búsqueda poderosa en todas las secciones:</p>
                            <ul>
                                <li><strong>Filtros múltiples:</strong> Estado, categoría, autor, fecha</li>
                                <li><strong>Búsqueda de texto:</strong> En títulos, contenido, extractos</li>
                                <li><strong>Ordenación:</strong> Por fecha, título, vistas, autor</li>
                                <li><strong>Paginación:</strong> Resultados organizados en páginas</li>
                            </ul>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-file-export me-2"></i>Exportación de Datos</h5>
                            <p>Exporta información del sistema:</p>
                            <ul>
                                <li><strong>Suscriptores:</strong> Lista en CSV/Excel</li>
                                <li><strong>Estadísticas:</strong> Reportes de noticias</li>
                                <li><strong>Comentarios:</strong> Por noticia o general</li>
                                <li><strong>Logs:</strong> Actividad administrativa</li>
                            </ul>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-mobile-alt me-2"></i>Responsive y PWA</h5>
                            <p>El sitio es completamente responsive y ready para PWA:</p>
                            <ul>
                                <li><strong>Móvil:</strong> Interfaz optimizada para smartphones</li>
                                <li><strong>Tablet:</strong> Adaptación automática a tablets</li>
                                <li><strong>Desktop:</strong> Experiencia completa en PC</li>
                                <li><strong>PWA:</strong> Se puede instalar como app nativa</li>
                            </ul>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-keyboard me-2"></i>Atajos de Teclado</h5>
                            <p>Mejora tu productividad con estos atajos:</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul>
                                        <li><span class="keyboard-shortcut">Ctrl + S</span> Guardar noticia</li>
                                        <li><span class="keyboard-shortcut">Ctrl + P</span> Publicar noticia</li>
                                        <li><span class="keyboard-shortcut">Ctrl + F</span> Buscar en página</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul>
                                        <li><span class="keyboard-shortcut">Alt + N</span> Nueva noticia</li>
                                        <li><span class="keyboard-shortcut">Alt + H</span> Ir al dashboard</li>
                                        <li><span class="keyboard-shortcut">Esc</span> Cerrar modales</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Consejos y Mejores Prácticas -->
            <div id="consejos" class="tutorial-section">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-lightbulb me-2"></i>Consejos y Mejores Prácticas</h4>
                    </div>
                    <div class="card-body">
                        <div class="tutorial-card">
                            <h5><i class="fas fa-pen-fancy me-2"></i>Escritura de Noticias</h5>
                            <ul>
                                <li><strong>Títulos atractivos:</strong> Concisos pero descriptivos (máx. 60 caracteres para SEO)</li>
                                <li><strong>Extractos convincentes:</strong> Resumen que invite a leer más</li>
                                <li><strong>Párrafos cortos:</strong> Facilita la lectura online</li>
                                <li><strong>Palabras clave:</strong> Incluye términos de búsqueda relevantes</li>
                                <li><strong>Enlaces internos:</strong> Conecta noticias relacionadas</li>
                            </ul>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-image me-2"></i>Optimización de Imágenes</h5>
                            <ul>
                                <li><strong>Tamaño recomendado:</strong> 1200x800px para noticias destacadas</li>
                                <li><strong>Formatos:</strong> JPG para fotos, PNG para gráficos, WebP para mejor compresión</li>
                                <li><strong>Peso:</strong> Máximo 2MB por imagen</li>
                                <li><strong>Alt text:</strong> Describe la imagen para SEO y accesibilidad</li>
                                <li><strong>Nombres descriptivos:</strong> Evita "IMG_001.jpg"</li>
                            </ul>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-search me-2"></i>SEO y Posicionamiento</h5>
                            <ul>
                                <li><strong>URLs amigables:</strong> El sistema genera slugs automáticamente</li>
                                <li><strong>Meta descripción:</strong> El extracto se usa automáticamente</li>
                                <li><strong>Categorización:</strong> Organize el contenido lógicamente</li>
                                <li><strong>Enlaces internos:</strong> Conecta contenido relacionado</li>
                                <li><strong>Actualización frecuente:</strong> Publica contenido regularmente</li>
                            </ul>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-users me-2"></i>Engagement del Usuario</h5>
                            <ul>
                                <li><strong>Contenido relevante:</strong> Conoce a tu audiencia</li>
                                <li><strong>Llamadas a la acción:</strong> Invita a comentar y compartir</li>
                                <li><strong>Respuesta rápida:</strong> Contesta comentarios pronto</li>
                                <li><strong>Redes sociales:</strong> Promociona en plataformas sociales</li>
                                <li><strong>Newsletter:</strong> Mantén informados a los suscriptores</li>
                            </ul>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-shield-alt me-2"></i>Seguridad y Privacidad</h5>
                            <ul>
                                <li><strong>Contraseñas fuertes:</strong> Usa combinaciones complejas</li>
                                <li><strong>Cierre de sesión:</strong> Siempre cierra sesión en PCs públicos</li>
                                <li><strong>Permisos mínimos:</strong> Solo da los permisos necesarios</li>
                                <li><strong>Backups regulares:</strong> Respalda contenido importante</li>
                                <li><strong>Verificación de contenido:</strong> Revisa antes de publicar</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div id="troubleshooting" class="tutorial-section">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Solución de Problemas</h4>
                    </div>
                    <div class="card-body">
                        <div class="tutorial-card">
                            <h5><i class="fas fa-times-circle me-2"></i>Problemas Comunes</h5>
                            
                            <h6>❌ No puedo subir imágenes</h6>
                            <p><strong>Soluciones:</strong></p>
                            <ul>
                                <li>Verifica que la imagen sea menor a 5MB</li>
                                <li>Usa formatos JPG, PNG o WebP</li>
                                <li>Contacta al administrador si persiste</li>
                            </ul>

                            <h6>❌ El editor no carga correctamente</h6>
                            <p><strong>Soluciones:</strong></p>
                            <ul>
                                <li>Refresca la página (F5)</li>
                                <li>Desactiva bloqueadores de anuncios</li>
                                <li>Prueba en otro navegador</li>
                            </ul>

                            <h6>❌ No puedo publicar noticias</h6>
                            <p><strong>Soluciones:</strong></p>
                            <ul>
                                <li>Verifica que tengas permisos de publicación</li>
                                <li>Como autor, usa estado "Pendiente" para revisión</li>
                                <li>Completa todos los campos obligatorios</li>
                            </ul>

                            <h6>❌ Los emails no se envían</h6>
                            <p><strong>Soluciones:</strong></p>
                            <ul>
                                <li>Verifica configuración SMTP (solo admin)</li>
                                <li>Revisa la carpeta de spam del destinatario</li>
                                <li>Contacta al administrador del sistema</li>
                            </ul>
                        </div>

                        <div class="tutorial-card">
                            <h5><i class="fas fa-question-circle me-2"></i>¿Necesitas Ayuda?</h5>
                            <p>Si tienes problemas no listados aquí:</p>
                            <ul>
                                <li><strong>Administrador del sitio:</strong> Contacta al admin principal</li>
                                <li><strong>Soporte técnico:</strong> medios@cellcomweb.com.ar</li>
                                <li><strong>Teléfono:</strong> +54 3482 549555</li>
                                <li><strong>Documentación completa:</strong> Revisa el README.md del sistema</li>
                            </ul>

                            <div class="tip-box">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>Tip:</strong> Incluye capturas de pantalla cuando reportes un problema. Ayuda mucho en el diagnóstico.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shortcuts y Acceso Rápido -->
            <div class="card mt-4">
                <div class="card-header">
                    <h4><i class="fas fa-rocket me-2"></i>Acceso Rápido</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <h6>🖊️ Crear Contenido</h6>
                            <ul class="list-unstyled">
                                <li><a href="news/add.php" class="text-decoration-none"><i class="fas fa-plus-circle me-1"></i>Nueva Noticia</a></li>
                                <?php if (hasRole(['admin', 'editor'])): ?>
                                <li><a href="categories/add.php" class="text-decoration-none"><i class="fas fa-folder-plus me-1"></i>Nueva Categoría</a></li>
                                <li><a href="polls/add.php" class="text-decoration-none"><i class="fas fa-poll me-1"></i>Nueva Encuesta</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="col-md-3">
                            <h6>📝 Gestionar Contenido</h6>
                            <ul class="list-unstyled">
                                <li><a href="news/" class="text-decoration-none"><i class="fas fa-list me-1"></i>Todas las Noticias</a></li>
                                <li><a href="comments/" class="text-decoration-none"><i class="fas fa-comments me-1"></i>Comentarios</a></li>
                                <li><a href="media/" class="text-decoration-none"><i class="fas fa-images me-1"></i>Biblioteca</a></li>
                            </ul>
                        </div>
                        <?php if (hasRole(['admin'])): ?>
                        <div class="col-md-3">
                            <h6>⚙️ Administrar</h6>
                            <ul class="list-unstyled">
                                <li><a href="users/" class="text-decoration-none"><i class="fas fa-users me-1"></i>Usuarios</a></li>
                                <li><a href="ads/" class="text-decoration-none"><i class="fas fa-bullhorn me-1"></i>Publicidad</a></li>
                                <li><a href="settings/" class="text-decoration-none"><i class="fas fa-cog me-1"></i>Configuración</a></li>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <h6>📊 Estadísticas</h6>
                            <ul class="list-unstyled">
                                <li><a href="statistics/" class="text-decoration-none"><i class="fas fa-chart-bar me-1"></i>Ver Estadísticas</a></li>
                                <li><a href="../" target="_blank" class="text-decoration-none"><i class="fas fa-eye me-1"></i>Ver Sitio Web</a></li>
                                <li><a href="profile.php" class="text-decoration-none"><i class="fas fa-user me-1"></i>Mi Perfil</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer del Tutorial -->
            <div class="card mt-4 bg-light">
                <div class="card-body text-center">
                    <h5><i class="fas fa-graduation-cap me-2"></i>¡Felicitaciones!</h5>
                    <p>Ahora conoces todas las funciones principales de <?php echo $siteName; ?>.</p>
                    <p class="mb-0"><strong>¿Listo para crear contenido increíble?</strong></p>
                    <div class="mt-3">
                        <a href="news/add.php" class="btn btn-primary me-2">
                            <i class="fas fa-pen-alt me-1"></i>Crear Primera Noticia
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-tachometer-alt me-1"></i>Ir al Dashboard
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Script para navegación suave -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Navegación suave a secciones
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Resaltar sección activa en el scroll
    window.addEventListener('scroll', function() {
        const sections = document.querySelectorAll('.tutorial-section');
        const scrollPos = window.pageYOffset + 100;

        sections.forEach(section => {
            const top = section.offsetTop;
            const bottom = top + section.offsetHeight;

            if (scrollPos >= top && scrollPos <= bottom) {
                // Remover clase activa de todos los enlaces
                document.querySelectorAll('a[href^="#"]').forEach(link => {
                    link.classList.remove('text-primary', 'fw-bold');
                });

                // Agregar clase activa al enlace correspondiente
                const activeLink = document.querySelector(`a[href="#${section.id}"]`);
                if (activeLink) {
                    activeLink.classList.add('text-primary', 'fw-bold');
                }
            }
        });
    });
});

// Easter egg: atajo para ir al dashboard
document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key === 'h') {
        window.location.href = 'dashboard.php';
    }
});
</script>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>