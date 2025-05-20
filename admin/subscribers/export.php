<?php
// Definir ruta base
define('BASE_PATH', dirname(dirname(__DIR__)));
define('ADMIN_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor'], '../index.php');

// Iniciar base de datos
$db = Database::getInstance();

// Obtener filtro de estado (si existe)
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Construir la consulta SQL
$sql = "SELECT id, email, name, status, confirmed, categories, created_at, updated_at FROM subscribers";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " WHERE status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY created_at DESC";

// Obtener suscriptores
$subscribers = $db->fetchAll($sql, $params);

// Si no hay suscriptores, mostrar mensaje y volver
if (empty($subscribers)) {
    setFlashMessage('info', 'No hay suscriptores para exportar con los filtros seleccionados.');
    redirect('index.php');
    exit;
}

// Registrar la acción en el log
logAdminAction('Exportar suscriptores', 
               'Se exportaron ' . count($subscribers) . ' suscriptores' . 
               (!empty($statusFilter) ? " con estado: $statusFilter" : ""), 
               'subscribers');

// Configurar encabezados para descarga de CSV
$filename = 'suscriptores_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Crear el manejador de salida
$output = fopen('php://output', 'w');

// Configurar la codificación UTF-8 para evitar problemas con caracteres especiales
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Escribir encabezados del CSV
fputcsv($output, [
    'ID',
    'Email',
    'Nombre',
    'Estado',
    'Confirmado',
    'Categorías',
    'Fecha de registro',
    'Última actualización'
]);

// Escribir datos de suscriptores
foreach ($subscribers as $subscriber) {
    // Formatear datos para el CSV
    $confirmedStatus = $subscriber['confirmed'] ? 'Sí' : 'No';
    
    // Procesar categorías (si es una cadena separada por comas)
    $categories = '';
    if (!empty($subscriber['categories'])) {
        // Si son IDs, obtener nombres de categorías
        $categoryIds = explode(',', $subscriber['categories']);
        $categoryNames = [];
        
        foreach ($categoryIds as $categoryId) {
            $category = $db->fetch(
                "SELECT name FROM categories WHERE id = ?",
                [(int)$categoryId]
            );
            
            if ($category) {
                $categoryNames[] = $category['name'];
            }
        }
        
        $categories = !empty($categoryNames) ? implode(', ', $categoryNames) : '';
    }
    
    // Traducir estados a español
switch ($subscriber['status']) {
    case 'active':
        $status = 'Activo';
        break;
    case 'inactive':
        $status = 'Inactivo';
        break;
    case 'unsubscribed':
        $status = 'Dado de baja';
        break;
    default:
        $status = 'Desconocido';
}

// Formatear fechas
$createdAt = date('d/m/Y H:i:s', strtotime($subscriber['created_at']));
$updatedAt = !empty($subscriber['updated_at']) 
           ? date('d/m/Y H:i:s', strtotime($subscriber['updated_at'])) 
           : '';

// Escribir fila en el CSV
fputcsv($output, [
    $subscriber['id'],
    $subscriber['email'],
    $subscriber['name'] ?? '',
    $status,
    $confirmedStatus,
    $categories,
    $createdAt,
    $updatedAt
]);
}

// Cerrar el archivo
fclose($output);
exit; // Detener la ejecución