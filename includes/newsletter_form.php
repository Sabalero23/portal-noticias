<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}
?>
<form action="subscribe.php" method="post" class="newsletter-form">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <div class="mb-3">
        <input type="text" class="form-control" placeholder="Nombre (opcional)" name="name">
    </div>
    <div class="mb-3">
        <input type="email" class="form-control" placeholder="Tu email" name="email" required>
    </div>
    <div class="mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="accept-terms" name="accept_terms" value="1" required>
            <label class="form-check-label small" for="accept-terms">
                Acepto recibir noticias y contenido promocional
            </label>
        </div>
    </div>
    
    <?php 
    // Obtener categorías para suscripción selectiva
    $db = Database::getInstance();
    $newsletterCategories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");
    
    if (!empty($newsletterCategories)):
    ?>
    <div class="mb-3">
        <p class="small mb-2">Selecciona las categorías que te interesan (opcional):</p>
        <div class="row">
            <?php foreach ($newsletterCategories as $category): ?>
                <div class="col-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="cat-<?php echo $category['id']; ?>" name="categories[]" value="<?php echo $category['id']; ?>">
                        <label class="form-check-label small" for="cat-<?php echo $category['id']; ?>">
                            <?php echo $category['name']; ?>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <button class="btn btn-primary w-100" type="submit">Suscribirse</button>
</form>