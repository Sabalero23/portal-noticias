<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}

// Obtener información del footer
$companyName = getSetting('company_name', 'Empresa de Medios S.A.');
$address = getSetting('address', 'Av. Principal 123, Ciudad');
$email = getSetting('email_contact', 'contacto@portalnoticias.com');
$phone = getSetting('phone_contact', '+54 (123) 456-7890');

// Obtener categorías populares (las 5 con más noticias)
$db = Database::getInstance();
$popularCategories = $db->fetchAll(
    "SELECT c.id, c.name, c.slug, COUNT(n.id) as news_count
     FROM categories c
     JOIN news n ON c.id = n.category_id
     WHERE n.status = 'published'
     GROUP BY c.id
     ORDER BY news_count DESC
     LIMIT 5"
);

// Obtener etiquetas populares (las 10 con más noticias)
$popularTags = $db->fetchAll(
    "SELECT t.id, t.name, t.slug, COUNT(nt.news_id) as news_count
     FROM tags t
     JOIN news_tags nt ON t.id = nt.tag_id
     JOIN news n ON nt.news_id = n.id
     WHERE n.status = 'published'
     GROUP BY t.id
     ORDER BY news_count DESC
     LIMIT 10"
);

// Verificar si la PWA está habilitada
$pwaEnabled = getSetting('pwa_enabled', '1') === '1';
?>

<!-- Footer -->
<footer class="footer bg-dark text-white mt-5 pt-5">
    <div class="container">
        <div class="row">
            <!-- Columna 1: Sobre nosotros -->
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h5 class="text-uppercase mb-4">Sobre nosotros</h5>
                <p><?php echo truncateString(getSetting('site_description', 'El portal de noticias más actualizado'), 150); ?></p>
                <p class="mb-0"><strong><?php echo $companyName; ?></strong></p>
                <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i><?php echo $address; ?></p>
                <p class="mb-0"><i class="fas fa-envelope me-2"></i><?php echo $email; ?></p>
                <p><i class="fas fa-phone me-2"></i><?php echo $phone; ?></p>
            </div>
            
            <!-- Columna 2: Categorías -->
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h5 class="text-uppercase mb-4">Categorías populares</h5>
                <ul class="list-unstyled">
                    <?php foreach ($popularCategories as $category): ?>
                        <li class="mb-2">
                            <a href="category.php?slug=<?php echo $category['slug']; ?>" class="text-white text-decoration-none">
                                <i class="fas fa-angle-right me-2"></i><?php echo $category['name']; ?> (<?php echo $category['news_count']; ?>)
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Columna 3: Enlaces rápidos -->
            <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                <h5 class="text-uppercase mb-4">Enlaces rápidos</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="index.php" class="text-white text-decoration-none">
                            <i class="fas fa-angle-right me-2"></i>Inicio
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="about.php" class="text-white text-decoration-none">
                            <i class="fas fa-angle-right me-2"></i>Quiénes somos
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="contact.php" class="text-white text-decoration-none">
                            <i class="fas fa-angle-right me-2"></i>Contacto
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="privacy.php" class="text-white text-decoration-none">
                            <i class="fas fa-angle-right me-2"></i>Política de privacidad
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="terms.php" class="text-white text-decoration-none">
                            <i class="fas fa-angle-right me-2"></i>Términos y condiciones
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Columna 4: Etiquetas populares -->
            <div class="col-lg-3 col-md-6">
                <h5 class="text-uppercase mb-4">Etiquetas populares</h5>
                <div class="tags-cloud">
                    <?php foreach ($popularTags as $tag): ?>
                        <a href="tag.php?slug=<?php echo $tag['slug']; ?>" class="tag-link">
                            <?php echo $tag['name']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Newsletter -->
                <h5 class="text-uppercase mb-3 mt-4">Newsletter</h5>
                <p class="small">Suscríbete para recibir las últimas noticias</p>
                <form action="subscribe.php" method="post" class="footer-subscribe">
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Tu email" name="email" required>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <hr class="my-4 bg-secondary">
        
        <!-- Copyright y redes sociales -->
        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo getSetting('site_name', 'Portal de Noticias'); ?>. Todos los derechos reservados.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <div class="social-links">
                    <?php if (!empty(getSetting('facebook', ''))): ?>
                        <a href="<?php echo getSetting('facebook'); ?>" target="_blank" class="me-3 text-white">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty(getSetting('twitter', ''))): ?>
                        <a href="<?php echo getSetting('twitter'); ?>" target="_blank" class="me-3 text-white">
                            <i class="fab fa-twitter"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty(getSetting('instagram', ''))): ?>
                        <a href="<?php echo getSetting('instagram'); ?>" target="_blank" class="me-3 text-white">
                            <i class="fab fa-instagram"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty(getSetting('youtube', ''))): ?>
                        <a href="<?php echo getSetting('youtube'); ?>" target="_blank" class="text-white">
                            <i class="fab fa-youtube"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Botón para volver arriba -->
<button id="back-to-top" class="btn btn-primary btn-sm rounded-circle">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/scripts.js"></script>

<?php if ($pwaEnabled): ?>
<!-- Service Worker para PWA -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('service-worker.js')
                .then(function(registration) {
                    console.log('Service Worker registrado con éxito: ', registration.scope);
                })
                .catch(function(error) {
                    console.log('Registro de Service Worker fallido: ', error);
                });
        });
    }
</script>
<?php endif; ?>

<!-- Script para el clima -->
<?php if (!empty(getSetting('weather_api_key', ''))): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        getWeather();
    });
    
    function getWeather() {
        const apiKey = '<?php echo getSetting('weather_api_key'); ?>';
        const city = '<?php echo getSetting('weather_city', 'Buenos Aires'); ?>';
        const url = `https://api.openweathermap.org/data/2.5/weather?q=${city}&appid=${apiKey}&units=metric&lang=es`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.main && data.weather) {
                    const temp = Math.round(data.main.temp);
                    const weather = data.weather[0].description;
                    document.getElementById('weather-widget').innerHTML = 
                        `<i class="fas fa-cloud me-1"></i> ${city}: ${temp}°C, ${weather}`;
                }
            })
            .catch(error => {
                console.error('Error al obtener el clima:', error);
            });
    }
</script>
<?php endif; ?>
<?php if ($pwaEnabled): ?>
<!-- Service Worker para PWA -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('service-worker.js')
                .then(function(registration) {
                    console.log('Service Worker registrado con éxito: ', registration.scope);
                })
                .catch(function(error) {
                    console.log('Registro de Service Worker fallido: ', error);
                });
        });
    }
</script>

<!-- PWA Install Manager -->
<script src="assets/js/pwa-install.js"></script>
<?php endif; ?>
</body>
</html>