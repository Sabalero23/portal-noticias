<?php
// Definir ruta base
define('BASE_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Inicializar Auth
$auth = new Auth();

// Cerrar sesión
$auth->logout();

// Redirigir al inicio de sesión
redirect('index.php');