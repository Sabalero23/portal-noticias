<?php
// Definir ruta base
define('BASE_PATH', dirname(__DIR__));
define('ADMIN_PATH', __DIR__);

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Inicializar Auth
$auth = new Auth();

// Cerrar sesión
$auth->logout();

// Redirigir al inicio de sesión
redirect('index.php');