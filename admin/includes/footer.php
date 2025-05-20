<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}

// Determinar nivel de profundidad y ajustar rutas
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
$isSubfolder = strpos($currentScript, '/admin/') !== false && substr_count($currentScript, '/', strpos($currentScript, '/admin/')) > 2;
$basePrefix = $isSubfolder ? '../' : '';
?>

<!-- Main Footer -->
<footer class="main-footer">
    <div class="d-sm-flex justify-content-between">
        <strong>&copy; <?php echo date('Y'); ?> <a href="<?php echo SITE_URL; ?>"><?php echo getSetting('site_name', 'Portal de Noticias'); ?></a>.</strong> Todos los derechos reservados.
        <div class="float-right d-none d-sm-inline-block">
            <b>Versión</b> <?php echo SYSTEM_VERSION; ?>
        </div>
    </div>
</footer>

<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
</aside>
<!-- /.control-sidebar -->

</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="<?php echo $basePrefix; ?>../assets/js/admin.js"></script>

<!-- Scripts específicos de la página -->
<?php if (isset($extraJS)): ?>
    <?php foreach ($extraJS as $js): ?>
        <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Código JavaScript en línea específico de la página -->
<?php if (isset($inlineJS)): ?>
    <script>
        <?php echo $inlineJS; ?>
    </script>
<?php endif; ?>

<!-- Código JavaScript en línea específico de la página -->
<?php if (isset($inlineJS)): ?>
    <script>
        <?php echo $inlineJS; ?>
    </script>
<?php endif; ?>

<!-- Confirmación para eliminación (modificado para excluir botones que ya tienen modal) -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Cerrar alertas después de 5 segundos
        const autoCloseAlerts = document.querySelectorAll('.alert-dismissible');
        autoCloseAlerts.forEach(alert => {
            setTimeout(() => {
                const closeButton = alert.querySelector('.btn-close');
                if (closeButton) {
                    closeButton.click();
                }
            }, 5000);
        });
        
        // Confirmar eliminación solo para botones que no tengan ya un modal
        const deleteButtons = document.querySelectorAll('.btn-delete:not([data-bs-toggle="modal"]):not([data-toggle="modal"])');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('¿Estás seguro de que deseas eliminar este elemento? Esta acción no se puede deshacer.')) {
                    e.preventDefault();
                }
            });
        });
    });
</script>
</body>
</html>