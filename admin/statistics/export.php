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
$auth->requirePermission(['admin', 'editor', 'author'], '../index.php');

// Obtener tipo de exportación
$type = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';

// Obtener ID si aplica
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener rangos de fechas
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Validar fechas
if (!strtotime($start_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
}
if (!strtotime($end_date)) {
    $end_date = date('Y-m-d');
}

// Verificar que end_date sea mayor o igual a start_date
if (strtotime($end_date) < strtotime($start_date)) {
    $temp = $end_date;
    $end_date = $start_date;
    $start_date = $temp;
}

// Obtener formato
$format = isset($_GET['format']) ? sanitize($_GET['format']) : 'csv';
if (!in_array($format, ['csv', 'excel', 'json'])) {
    $format = 'csv';
}

// Base de datos
$db = Database::getInstance();

// Título del archivo
$filename = 'estadisticas_';

// Preparar datos según el tipo de exportación
switch ($type) {
    case 'news':
        // Estadísticas de una noticia específica
        if ($id <= 0) {
            setFlashMessage('error', 'ID de noticia no válido');
            redirect('news.php');
            exit;
        }
        
        // Obtener información de la noticia
        $news = $db->fetch(
            "SELECT n.id, n.title, n.slug, n.published_at, n.views, c.name as category_name, u.name as author_name 
             FROM news n 
             JOIN categories c ON n.category_id = c.id 
             JOIN users u ON n.author_id = u.id 
             WHERE n.id = ?",
            [$id]
        );
        
        if (!$news) {
            setFlashMessage('error', 'Noticia no encontrada');
            redirect('news.php');
            exit;
        }
        
        // Construir título del archivo
        $filename .= 'noticia_' . $id . '_' . date('Ymd');
        
        // Obtener vistas diarias
        $viewsByDay = $db->fetchAll(
            "SELECT DATE(viewed_at) as date, COUNT(*) as views 
             FROM view_logs 
             WHERE news_id = ? AND viewed_at BETWEEN ? AND ? 
             GROUP BY DATE(viewed_at) 
             ORDER BY date ASC",
            [$id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Obtener referrers
        $referrers = $db->fetchAll(
            "SELECT 
                CASE 
                    WHEN referer = '' OR referer IS NULL THEN 'Directo'
                    WHEN referer LIKE '%google%' THEN 'Google'
                    WHEN referer LIKE '%bing%' THEN 'Bing'
                    WHEN referer LIKE '%yahoo%' THEN 'Yahoo'
                    WHEN referer LIKE '%facebook%' THEN 'Facebook'
                    WHEN referer LIKE '%twitter%' OR referer LIKE '%x.com%' THEN 'Twitter/X'
                    WHEN referer LIKE '%instagram%' THEN 'Instagram'
                    WHEN referer LIKE '%linkedin%' THEN 'LinkedIn'
                    WHEN referer LIKE '%pinterest%' THEN 'Pinterest'
                    WHEN referer LIKE '%reddit%' THEN 'Reddit'
                    ELSE 'Otros'
                END as source,
                COUNT(*) as count
             FROM view_logs
             WHERE news_id = ? AND viewed_at BETWEEN ? AND ?
             GROUP BY source
             ORDER BY count DESC",
            [$id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Obtener dispositivos
        $devices = $db->fetchAll(
            "SELECT 
                CASE 
                    WHEN user_agent LIKE '%Android%' THEN 'Android' 
                    WHEN user_agent LIKE '%iPhone%' THEN 'iPhone' 
                    WHEN user_agent LIKE '%iPad%' THEN 'iPad' 
                    WHEN user_agent LIKE '%Windows%' THEN 'Windows' 
                    WHEN user_agent LIKE '%Macintosh%' THEN 'Mac' 
                    WHEN user_agent LIKE '%Linux%' THEN 'Linux' 
                    ELSE 'Otro' 
                END as device, 
                COUNT(*) as count 
             FROM view_logs 
             WHERE news_id = ? AND viewed_at BETWEEN ? AND ? 
             GROUP BY device 
             ORDER BY count DESC",
            [$id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Obtener datos por hora
        $hourlyData = $db->fetchAll(
            "SELECT HOUR(viewed_at) as hour, COUNT(*) as count 
             FROM view_logs 
             WHERE news_id = ? AND viewed_at BETWEEN ? AND ? 
             GROUP BY HOUR(viewed_at) 
             ORDER BY hour ASC",
            [$id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Preparar datos para exportación
        $data = [
            'info' => [
                'Título' => $news['title'],
                'Categoría' => $news['category_name'],
                'Autor' => $news['author_name'],
                'Fecha de publicación' => formatDate($news['published_at'], 'd/m/Y H:i'),
                'Vistas totales' => $news['views'],
                'Período de análisis' => "$start_date a $end_date"
            ],
            'vistas_diarias' => $viewsByDay,
            'fuentes_trafico' => $referrers,
            'dispositivos' => $devices,
            'horas_dia' => $hourlyData
        ];
        
        break;
        
    case 'category':
        // Estadísticas de una categoría específica
        if ($id <= 0) {
            setFlashMessage('error', 'ID de categoría no válido');
            redirect('categories.php');
            exit;
        }
        
        // Obtener información de la categoría
        $category = $db->fetch(
            "SELECT id, name, slug, description 
             FROM categories 
             WHERE id = ?",
            [$id]
        );
        
        if (!$category) {
            setFlashMessage('error', 'Categoría no encontrada');
            redirect('categories.php');
            exit;
        }
        
        // Construir título del archivo
        $filename .= 'categoria_' . $id . '_' . date('Ymd');
        
        // Obtener vistas diarias
        $viewsByDay = $db->fetchAll(
            "SELECT DATE(vl.viewed_at) as date, COUNT(*) as views 
             FROM view_logs vl 
             JOIN news n ON vl.news_id = n.id 
             WHERE n.category_id = ? AND vl.viewed_at BETWEEN ? AND ? 
             GROUP BY DATE(vl.viewed_at) 
             ORDER BY date ASC",
            [$id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Obtener noticias más vistas de la categoría
        $topNews = $db->fetchAll(
            "SELECT n.id, n.title, n.slug, n.published_at, n.views, COUNT(vl.id) as views_period 
             FROM news n 
             JOIN view_logs vl ON n.id = vl.news_id 
             WHERE n.category_id = ? AND vl.viewed_at BETWEEN ? AND ? 
             GROUP BY n.id 
             ORDER BY views_period DESC 
             LIMIT 20",
            [$id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Obtener dispositivos
        $devices = $db->fetchAll(
            "SELECT 
                CASE 
                    WHEN user_agent LIKE '%Android%' THEN 'Android' 
                    WHEN user_agent LIKE '%iPhone%' THEN 'iPhone' 
                    WHEN user_agent LIKE '%iPad%' THEN 'iPad' 
                    WHEN user_agent LIKE '%Windows%' THEN 'Windows' 
                    WHEN user_agent LIKE '%Macintosh%' THEN 'Mac' 
                    WHEN user_agent LIKE '%Linux%' THEN 'Linux' 
                    ELSE 'Otro' 
                END as device, 
                COUNT(*) as count 
             FROM view_logs vl
             JOIN news n ON vl.news_id = n.id
             WHERE n.category_id = ? AND vl.viewed_at BETWEEN ? AND ? 
             GROUP BY device 
             ORDER BY count DESC",
            [$id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Obtener datos por hora
        $hourlyData = $db->fetchAll(
            "SELECT HOUR(vl.viewed_at) as hour, COUNT(*) as count 
             FROM view_logs vl 
             JOIN news n ON vl.news_id = n.id 
             WHERE n.category_id = ? AND vl.viewed_at BETWEEN ? AND ? 
             GROUP BY HOUR(vl.viewed_at) 
             ORDER BY hour ASC",
            [$id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Preparar datos para exportación
        $data = [
            'info' => [
                'Categoría' => $category['name'],
                'Descripción' => $category['description'],
                'Período de análisis' => "$start_date a $end_date"
            ],
            'vistas_diarias' => $viewsByDay,
            'noticias_top' => $topNews,
            'dispositivos' => $devices,
            'horas_dia' => $hourlyData
        ];
        
        break;
        
    case 'categories':
        // Estadísticas de todas las categorías
        $filename .= 'todas_categorias_' . date('Ymd');
        
        // Obtener datos de todas las categorías
        $categories = $db->fetchAll(
            "SELECT 
                c.id, 
                c.name, 
                c.description,
                (SELECT COUNT(*) FROM news WHERE category_id = c.id AND status = 'published') as news_count,
                (SELECT COUNT(*) FROM view_logs vl JOIN news n ON vl.news_id = n.id WHERE n.category_id = c.id) as total_views,
                (SELECT COUNT(*) FROM view_logs vl JOIN news n ON vl.news_id = n.id WHERE n.category_id = c.id AND vl.viewed_at BETWEEN ? AND ?) as period_views
             FROM categories c
             ORDER BY period_views DESC",
            [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Vistas diarias de todas las categorías
        $viewsByDay = $db->fetchAll(
            "SELECT c.name as category, DATE(vl.viewed_at) as date, COUNT(*) as views 
             FROM categories c
             JOIN news n ON c.id = n.category_id
             JOIN view_logs vl ON n.id = vl.news_id
             WHERE vl.viewed_at BETWEEN ? AND ? 
             GROUP BY c.id, DATE(vl.viewed_at) 
             ORDER BY c.name ASC, date ASC",
            [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Preparar datos para exportación
        $data = [
            'info' => [
                'Período de análisis' => "$start_date a $end_date",
                'Total de categorías' => count($categories)
            ],
            'categorias' => $categories,
            'vistas_diarias' => $viewsByDay
        ];
        
        break;
        
    case 'all':
    default:
        // Estadísticas generales
        $filename .= 'generales_' . date('Ymd');
        
        // Obtener estadísticas generales
        $stats = [
            'total_views' => $db->fetch(
                "SELECT COUNT(*) as count 
                 FROM view_logs 
                 WHERE viewed_at BETWEEN ? AND ?",
                [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
            )['count'] ?? 0,
            
            'total_news' => $db->fetch(
                "SELECT COUNT(*) as count 
                 FROM news 
                 WHERE status = 'published'"
            )['count'] ?? 0,
            
            'new_news' => $db->fetch(
                "SELECT COUNT(*) as count 
                 FROM news 
                 WHERE status = 'published' AND published_at BETWEEN ? AND ?",
                [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
            )['count'] ?? 0,
            
            'total_comments' => $db->fetch(
                "SELECT COUNT(*) as count 
                 FROM comments 
                 WHERE created_at BETWEEN ? AND ?",
                [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
            )['count'] ?? 0
        ];
        
        // Vistas diarias
        $viewsByDay = $db->fetchAll(
            "SELECT DATE(viewed_at) as date, COUNT(*) as views 
             FROM view_logs 
             WHERE viewed_at BETWEEN ? AND ? 
             GROUP BY DATE(viewed_at) 
             ORDER BY date ASC",
            [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Noticias más vistas
        $topNews = $db->fetchAll(
            "SELECT n.id, n.title, n.slug, n.views, c.name as category, COUNT(vl.id) as views_period 
             FROM news n 
             JOIN categories c ON n.category_id = c.id
             JOIN view_logs vl ON n.id = vl.news_id 
             WHERE vl.viewed_at BETWEEN ? AND ? 
             GROUP BY n.id 
             ORDER BY views_period DESC 
             LIMIT 20",
            [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Categorías más vistas
        $topCategories = $db->fetchAll(
            "SELECT c.id, c.name, COUNT(vl.id) as views 
             FROM categories c 
             JOIN news n ON c.id = n.category_id 
             JOIN view_logs vl ON n.id = vl.news_id 
             WHERE vl.viewed_at BETWEEN ? AND ? 
             GROUP BY c.id 
             ORDER BY views DESC",
            [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Dispositivos
        $devices = $db->fetchAll(
            "SELECT 
                CASE 
                    WHEN user_agent LIKE '%Android%' THEN 'Android' 
                    WHEN user_agent LIKE '%iPhone%' THEN 'iPhone' 
                    WHEN user_agent LIKE '%iPad%' THEN 'iPad' 
                    WHEN user_agent LIKE '%Windows%' THEN 'Windows' 
                    WHEN user_agent LIKE '%Macintosh%' THEN 'Mac' 
                    WHEN user_agent LIKE '%Linux%' THEN 'Linux' 
                    ELSE 'Otro' 
                END as device, 
                COUNT(*) as count 
             FROM view_logs 
             WHERE viewed_at BETWEEN ? AND ? 
             GROUP BY device 
             ORDER BY count DESC",
            [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Navegadores
        $browsers = $db->fetchAll(
            "SELECT 
                CASE 
                    WHEN user_agent LIKE '%Chrome%' AND user_agent NOT LIKE '%Edg%' THEN 'Chrome' 
                    WHEN user_agent LIKE '%Firefox%' THEN 'Firefox' 
                    WHEN user_agent LIKE '%Safari%' AND user_agent NOT LIKE '%Chrome%' THEN 'Safari' 
                    WHEN user_agent LIKE '%Edg%' THEN 'Edge' 
                    WHEN user_agent LIKE '%MSIE%' OR user_agent LIKE '%Trident%' THEN 'Internet Explorer' 
                    WHEN user_agent LIKE '%Opera%' OR user_agent LIKE '%OPR%' THEN 'Opera' 
                    ELSE 'Otro' 
                END as browser, 
                COUNT(*) as count 
             FROM view_logs 
             WHERE viewed_at BETWEEN ? AND ? 
             GROUP BY browser 
             ORDER BY count DESC",
            [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Datos por hora
        $hourlyData = $db->fetchAll(
            "SELECT HOUR(viewed_at) as hour, COUNT(*) as count 
             FROM view_logs 
             WHERE viewed_at BETWEEN ? AND ? 
             GROUP BY HOUR(viewed_at) 
             ORDER BY hour ASC",
            [$start_date . ' 00:00:00', $end_date . ' 23:59:59']
        );
        
        // Preparar datos para exportación
        $data = [
            'info' => [
                'Período de análisis' => "$start_date a $end_date",
                'Total de vistas' => $stats['total_views'],
                'Total de noticias publicadas' => $stats['total_news'],
                'Noticias nuevas en el período' => $stats['new_news'],
                'Total de comentarios en el período' => $stats['total_comments']
            ],
            'vistas_diarias' => $viewsByDay,
            'noticias_top' => $topNews,
            'categorias_top' => $topCategories,
            'dispositivos' => $devices,
            'navegadores' => $browsers,
            'horas_dia' => $hourlyData
        ];
        
        break;
}

// Realizar la exportación según el formato solicitado
switch ($format) {
    case 'json':
        // Exportar como JSON
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
        
    case 'excel':
        // Exportar como archivo Excel
        // Nota: Requiere la biblioteca PhpSpreadsheet
        // Si no está instalada, se puede usar CSV como fallback
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Fallback a CSV si no está disponible la librería
            exportAsCsv($data, $filename);
            exit;
        }
        
        // Crear nuevo documento Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // Establecer propiedades del documento
        $spreadsheet->getProperties()
            ->setCreator(getSetting('site_name', 'Portal de Noticias'))
            ->setLastModifiedBy(getSetting('site_name', 'Portal de Noticias'))
            ->setTitle('Estadísticas')
            ->setSubject('Estadísticas del ' . $start_date . ' al ' . $end_date)
            ->setDescription('Estadísticas generadas automáticamente')
            ->setKeywords('estadísticas')
            ->setCategory('Análisis');
        
        // Procesar cada conjunto de datos en hojas separadas
        $sheetIndex = 0;
        
        foreach ($data as $sectionName => $sectionData) {
            if ($sheetIndex > 0) {
                $spreadsheet->createSheet();
                $spreadsheet->setActiveSheetIndex($sheetIndex);
            }
            
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(ucfirst($sectionName));
            
            // Agregar datos según el tipo de sección
            if ($sectionName === 'info') {
                $row = 1;
                foreach ($sectionData as $key => $value) {
                    $sheet->setCellValue('A' . $row, $key);
                    $sheet->setCellValue('B' . $row, $value);
                    $row++;
                }
                
                // Formatear la hoja
                $sheet->getColumnDimension('A')->setWidth(25);
                $sheet->getColumnDimension('B')->setWidth(30);
                
                // Establecer estilo para títulos
                $sheet->getStyle('A1:A' . ($row - 1))->getFont()->setBold(true);
            } else {
                // Verificar si tenemos datos
                if (empty($sectionData)) {
                    $sheet->setCellValue('A1', 'No hay datos disponibles');
                    $sheetIndex++;
                    continue;
                }
                
                // Obtener encabezados y datos
                $headers = array_keys((array)$sectionData[0]);
                
                // Colocar encabezados
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue($col . '1', ucfirst(str_replace('_', ' ', $header)));
                    $col++;
                }
                
                // Colocar datos
                $row = 2;
                foreach ($sectionData as $rowData) {
                    $col = 'A';
                    foreach ($rowData as $value) {
                        $sheet->setCellValue($col . $row, $value);
                        $col++;
                    }
                    $row++;
                }
                
                // Formatear la hoja
                foreach (range('A', $col) as $columnID) {
                    $sheet->getColumnDimension($columnID)->setAutoSize(true);
                }
                
                // Establecer estilo para encabezados
                $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')->getFont()->setBold(true);
                $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')->getFill()->getStartColor()->setRGB('DDDDDD');
                
                // Establecer filtros
                $sheet->setAutoFilter('A1:' . chr(ord('A') + count($headers) - 1) . '1');
                
                // Fijar encabezados
                $sheet->freezePane('A2');
            }
            
            $sheetIndex++;
        }
        
        // Activar la primera hoja
        $spreadsheet->setActiveSheetIndex(0);
        
        // Crear objeto para exportar a Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Enviar el archivo al navegador
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        // Guardar archivo en salida estándar
        $writer->save('php://output');
        exit;
        
    case 'csv':
    default:
        // Exportar como CSV
        exportAsCsv($data, $filename);
        exit;
}

/**
 * Exporta los datos en formato CSV
 * 
 * @param array $data Datos a exportar
 * @param string $filename Nombre base del archivo (sin extensión)
 */
function exportAsCsv($data, $filename) {
    // Configurar encabezados
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Abrir archivo de salida
    $output = fopen('php://output', 'w');
    
    // Escribir BOM para soporte de UTF-8 en Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // Procesar cada conjunto de datos
    foreach ($data as $sectionName => $sectionData) {
        // Agregar título de sección
        fputcsv($output, [ucfirst(str_replace('_', ' ', $sectionName))]);
        
        // Verificar el tipo de sección
        if ($sectionName === 'info') {
            // Para sección de información general, formato clave-valor
            foreach ($sectionData as $key => $value) {
                fputcsv($output, [$key, $value]);
            }
        } else {
            // Para secciones con arrays de datos
            if (empty($sectionData)) {
                fputcsv($output, ['No hay datos disponibles']);
                // Agregar línea en blanco entre secciones
                fputcsv($output, []);
                continue;
            }
            
            // Obtener encabezados
            $headers = array_keys((array)$sectionData[0]);
            
            // Convertir encabezados a formato más legible
            $readableHeaders = array_map(function($header) {
                return ucfirst(str_replace('_', ' ', $header));
            }, $headers);
            
            // Escribir encabezados
            fputcsv($output, $readableHeaders);
            
            // Escribir datos
            foreach ($sectionData as $row) {
                fputcsv($output, (array)$row);
            }
        }
        
        // Agregar línea en blanco entre secciones
        fputcsv($output, []);
    }
    
    // Cerrar archivo
    fclose($output);
}