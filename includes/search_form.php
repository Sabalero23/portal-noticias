<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}
?>
<form action="search.php" method="get" class="search-form">
    <div class="input-group">
        <input type="text" class="form-control" placeholder="Buscar noticias..." name="q" required>
        <button class="btn btn-primary" type="submit">
            <i class="fas fa-search"></i>
        </button>
    </div>
</form>