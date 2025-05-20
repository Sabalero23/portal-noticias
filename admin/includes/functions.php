<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}

/**
 * Genera una tabla HTML para listados
 * 
 * @param array $columns Columnas de la tabla [id => nombre]
 * @param array $rows Filas de datos
 * @param array $options Opciones adicionales
 * @return string HTML de la tabla
 */
function generateTable($columns, $rows, $options = []) {
    $html = '<div class="table-responsive">';
    $html .= '<table class="table table-striped table-hover">';
    
    // Encabezado de la tabla
    $html .= '<thead>';
    $html .= '<tr>';
    
    foreach ($columns as $id => $name) {
        $class = isset($options['column_class'][$id]) ? ' class="' . $options['column_class'][$id] . '"' : '';
        $html .= '<th' . $class . '>' . $name . '</th>';
    }
    
    if (isset($options['actions']) && $options['actions']) {
        $html .= '<th class="text-end">Acciones</th>';
    }
    
    $html .= '</tr>';
    $html .= '</thead>';
    
    // Cuerpo de la tabla
    $html .= '<tbody>';
    
    if (count($rows) > 0) {
        foreach ($rows as $row) {
            $html .= '<tr>';
            
            foreach ($columns as $id => $name) {
                $class = isset($options['column_class'][$id]) ? ' class="' . $options['column_class'][$id] . '"' : '';
                $value = isset($row[$id]) ? $row[$id] : '';
                
                // Aplicar formato si está definido
                if (isset($options['format'][$id]) && is_callable($options['format'][$id])) {
                    $value = $options['format'][$id]($value, $row);
                }
                
                $html .= '<td' . $class . '>' . $value . '</td>';
            }
            
            // Acciones
            if (isset($options['actions']) && $options['actions']) {
                $html .= '<td class="text-end">';
                
                // Acciones personalizadas
                if (isset($options['custom_actions']) && is_callable($options['custom_actions'])) {
                    $html .= $options['custom_actions']($row);
                }
                
                // Acción ver
                if (isset($options['view']) && $options['view']) {
                    $viewUrl = sprintf($options['view'], $row['id']);
                    $html .= '<a href="' . $viewUrl . '" class="btn btn-sm btn-info" title="Ver"><i class="fas fa-eye"></i></a> ';
                }
                
                // Acción editar
                if (isset($options['edit']) && $options['edit']) {
                    $editUrl = sprintf($options['edit'], $row['id']);
                    $html .= '<a href="' . $editUrl . '" class="btn btn-sm btn-primary" title="Editar"><i class="fas fa-edit"></i></a> ';
                }
                
                // Acción eliminar
                if (isset($options['delete']) && $options['delete']) {
                    $deleteUrl = sprintf($options['delete'], $row['id']);
                    $html .= '<a href="' . $deleteUrl . '" class="btn btn-sm btn-danger btn-delete" title="Eliminar"><i class="fas fa-trash"></i></a>';
                }
                
                $html .= '</td>';
            }
            
            $html .= '</tr>';
        }
    } else {
        // Sin datos
        $colSpan = count($columns);
        if (isset($options['actions']) && $options['actions']) {
            $colSpan++;
        }
        
        $html .= '<tr>';
        $html .= '<td colspan="' . $colSpan . '" class="text-center">No hay datos disponibles</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Genera paginación para listados
 * 
 * @param int $page Página actual
 * @param int $totalPages Total de páginas
 * @param string $url URL base para enlaces
 * @param array $params Parámetros adicionales para URL
 * @return string HTML de paginación
 */
function generatePagination($page, $totalPages, $url, $params = []) {
    if ($totalPages <= 1) {
        return '';
    }
    
    // Generar query string
    $queryString = '';
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            if ($key !== 'page') {
                $queryString .= '&' . $key . '=' . urlencode($value);
            }
        }
    }
    
    $html = '<nav aria-label="Paginación">';
    $html .= '<ul class="pagination justify-content-center">';
    
    // Anterior
    if ($page > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $url . '?page=' . ($page - 1) . $queryString . '" aria-label="Anterior">';
        $html .= '<span aria-hidden="true">&laquo;</span>';
        $html .= '</a>';
        $html .= '</li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link" aria-hidden="true">&laquo;</span>';
        $html .= '</li>';
    }
    
    // Páginas
    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
    
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=1' . $queryString . '">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $i . $queryString . '">' . $i . '</a></li>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $totalPages . $queryString . '">' . $totalPages . '</a></li>';
    }
    
    // Siguiente
    if ($page < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $url . '?page=' . ($page + 1) . $queryString . '" aria-label="Siguiente">';
        $html .= '<span aria-hidden="true">&raquo;</span>';
        $html .= '</a>';
        $html .= '</li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link" aria-hidden="true">&raquo;</span>';
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Genera un formulario de búsqueda
 * 
 * @param string $action URL de acción
 * @param string $placeholder Texto de placeholder
 * @param string $query Término de búsqueda actual
 * @param array $filters Filtros adicionales
 * @return string HTML del formulario
 */
function generateSearchForm($action, $placeholder = 'Buscar...', $query = '', $filters = []) {
    $html = '<form action="' . $action . '" method="get" class="mb-4">';
    $html .= '<div class="row g-3">';
    
    // Input de búsqueda
    $html .= '<div class="col-md-6">';
    $html .= '<div class="input-group">';
    $html .= '<input type="text" class="form-control" name="q" value="' . htmlspecialchars($query) . '" placeholder="' . htmlspecialchars($placeholder) . '">';
    $html .= '<button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Filtros adicionales
    if (!empty($filters)) {
        foreach ($filters as $filter) {
            $html .= '<div class="col-md-3">';
            
            if ($filter['type'] === 'select') {
                $html .= '<select name="' . $filter['name'] . '" class="form-select">';
                $html .= '<option value="">' . $filter['placeholder'] . '</option>';
                
                foreach ($filter['options'] as $value => $label) {
                    $selected = (isset($_GET[$filter['name']]) && $_GET[$filter['name']] == $value) ? ' selected' : '';
                    $html .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
                }
                
                $html .= '</select>';
            } else {
                $html .= '<input type="' . $filter['type'] . '" class="form-control" name="' . $filter['name'] . '" placeholder="' . $filter['placeholder'] . '" value="' . (isset($_GET[$filter['name']]) ? htmlspecialchars($_GET[$filter['name']]) : '') . '">';
            }
            
            $html .= '</div>';
        }
    }
    
    // Botón de reset
    $html .= '<div class="col-md-3">';
    $html .= '<a href="' . $action . '" class="btn btn-secondary w-100">Limpiar filtros</a>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</form>';
    
    return $html;
}

/**
 * Genera enlaces de ordenación para tablas
 * 
 * @param string $column Columna para ordenar
 * @param string $label Etiqueta a mostrar
 * @param string $currentSort Ordenación actual (column_asc/column_desc)
 * @param array $params Parámetros adicionales para URL
 * @return string HTML del enlace
 */
function generateSortLink($column, $label, $currentSort, $params = []) {
    $sortParts = explode('_', $currentSort);
    $currentColumn = $sortParts[0] ?? '';
    $currentDirection = $sortParts[1] ?? 'asc';
    
    $newDirection = ($currentColumn === $column && $currentDirection === 'asc') ? 'desc' : 'asc';
    $newSort = $column . '_' . $newDirection;
    
    // Generar query string
    $queryParams = $params;
    $queryParams['sort'] = $newSort;
    
    $queryString = '?';
    foreach ($queryParams as $key => $value) {
        $queryString .= $key . '=' . urlencode($value) . '&';
    }
    $queryString = rtrim($queryString, '&');
    
    // Determinar ícono
    $icon = '';
    if ($currentColumn === $column) {
        $icon = ($currentDirection === 'asc') ? '<i class="fas fa-sort-up ms-1"></i>' : '<i class="fas fa-sort-down ms-1"></i>';
    } else {
        $icon = '<i class="fas fa-sort ms-1 text-muted"></i>';
    }
    
    return '<a href="' . $queryString . '" class="text-decoration-none text-reset">' . $label . $icon . '</a>';
}

/**
 * Asegura que un usuario tiene permisos necesarios o redirige
 * 
 * @param string|array $roles Roles permitidos
 * @param string $redirectUrl URL a redireccionar si no tiene permiso
 */
function ensurePermission($roles, $redirectUrl = '../index.php') {
    if (!hasRole($roles)) {
        setFlashMessage('error', 'No tienes permisos para acceder a esta página');
        redirect($redirectUrl);
    }
}

/**
 * Procesa la carga de una imagen
 * 
 * @param array $file Archivo ($_FILES['nombre'])
 * @param string $destination Carpeta destino
 * @param array $allowedTypes Tipos MIME permitidos
 * @param int $maxSize Tamaño máximo en bytes
 * @param int $maxWidth Ancho máximo (0 = sin límite)
 * @param int $maxHeight Alto máximo (0 = sin límite)
 * @return array [success, message, filename, filepath]
 */
function processImageUpload($file, $destination, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5242880, $maxWidth = 0, $maxHeight = 0) {
    $result = [
        'success' => false,
        'message' => '',
        'filename' => '',
        'filepath' => ''
    ];
    
    // Verificar errores
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $result['message'] = 'No se ha subido ningún archivo';
        return $result;
    }
    
    // Verificar si es una imagen válida
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $result['message'] = 'El archivo no es una imagen válida';
        return $result;
    }
    
    // Obtener tipo MIME de la imagen
    $mime = $imageInfo['mime'];
    
    // Verificar tipo MIME
    if (!in_array($mime, $allowedTypes)) {
        $result['message'] = 'Tipo de archivo no permitido. Sólo se permiten: ' . implode(', ', $allowedTypes);
        return $result;
    }
    
    // Verificar tamaño
    if ($file['size'] > $maxSize) {
        $result['message'] = 'El archivo es demasiado grande. Tamaño máximo: ' . formatBytes($maxSize);
        return $result;
    }
    
    // Verificar dimensiones (si aplica)
    if ($maxWidth > 0 || $maxHeight > 0) {
        list($width, $height) = $imageInfo;
        
        if (($maxWidth > 0 && $width > $maxWidth) || ($maxHeight > 0 && $height > $maxHeight)) {
            $result['message'] = 'La imagen excede las dimensiones máximas: ' . $maxWidth . 'x' . $maxHeight;
            return $result;
        }
    }
    
    // Crear directorio si no existe
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Mapeo de tipos MIME a extensiones
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    // Determinar extensión correcta
    $extension = isset($extensions[$mime]) ? $extensions[$mime] : 'jpg';
    
    // Generar nombre único
    $filename = generateToken(16) . '.' . $extension;
    $filepath = $destination . '/' . $filename;
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $result['success'] = true;
        $result['message'] = 'Archivo subido correctamente';
        $result['filename'] = $filename;
        $result['filepath'] = $filepath;
    } else {
        $result['message'] = 'Error al mover el archivo';
    }
    
    return $result;
}

/**
 * Formatea bytes a formato legible
 * 
 * @param int $bytes Bytes a formatear
 * @param int $precision Precisión decimal
 * @return string Formato legible
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Genera un slug único verificando la base de datos
 * 
 * @param string $string Cadena a convertir
 * @param string $table Tabla para verificar
 * @param string $column Columna a verificar
 * @param int $excludeId ID a excluir (para edición)
 * @return string Slug único
 */
function generateUniqueSlug($string, $table, $column = 'slug', $excludeId = null) {
    $db = Database::getInstance();
    $slug = generateSlug($string);
    $originalSlug = $slug;
    $counter = 1;
    
    // Verificar si ya existe
    while (true) {
        $query = "SELECT id FROM $table WHERE $column = ?";
        $params = [$slug];
        
        if ($excludeId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $existing = $db->fetch($query, $params);
        
        if (!$existing) {
            break;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

/**
 * Genera opciones para un select a partir de una tabla
 * 
 * @param string $table Tabla
 * @param string $valueColumn Columna para value
 * @param string $labelColumn Columna para label
 * @param string $where Condición WHERE (opcional)
 * @param array $params Parámetros para condición (opcional)
 * @param string $orderBy Ordenación (opcional)
 * @return array Opciones [value => label]
 */
function getSelectOptions($table, $valueColumn, $labelColumn, $where = '', $params = [], $orderBy = '') {
    $db = Database::getInstance();
    
    $query = "SELECT $valueColumn, $labelColumn FROM $table";
    
    if (!empty($where)) {
        $query .= " WHERE $where";
    }
    
    if (!empty($orderBy)) {
        $query .= " ORDER BY $orderBy";
    }
    
    $rows = $db->fetchAll($query, $params);
    $options = [];
    
    foreach ($rows as $row) {
        $options[$row[$valueColumn]] = $row[$labelColumn];
    }
    
    return $options;
}

/**
 * Registra la actividad del usuario
 * 
 * @param string $action Acción realizada
 * @param string $details Detalles adicionales
 * @param string $module Módulo afectado
 * @param int $itemId ID del elemento afectado
 */
function logAdminAction($action, $details = '', $module = '', $itemId = 0) {
    // Si no hay usuario logueado, no registrar
    if (!isLoggedIn()) {
        return;
    }
    
    $db = Database::getInstance();
    $userId = $_SESSION['user']['id'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $db->query(
        "INSERT INTO admin_log (user_id, action, details, module, item_id, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [$userId, $action, $details, $module, $itemId, $ipAddress, $userAgent]
    );
}

/**
 * Genera un campo de editor TinyMCE
 * 
 * @param string $name Nombre del campo
 * @param string $value Valor actual
 * @param array $options Opciones adicionales
 * @return string HTML del editor
 */
function generateEditor($name, $value = '', $options = []) {
    $id = $options['id'] ?? $name;
    $height = $options['height'] ?? 400;
    $placeholder = $options['placeholder'] ?? 'Escribe aquí...';
    $required = isset($options['required']) && $options['required'] ? 'required' : '';
    
    $html = '<textarea id="' . $id . '" name="' . $name . '" class="form-control editor" placeholder="' . $placeholder . '" ' . $required . '>' . htmlspecialchars($value) . '</textarea>';
    
    // Inicializar TinyMCE
    $html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            tinymce.init({
                selector: "#' . $id . '",
                height: ' . $height . ',
                plugins: "advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste code help wordcount",
                toolbar: "undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link image media | help",
                menubar: "file edit view insert format tools table help",
                toolbar_mode: "floating",
                entity_encoding: "raw",
                relative_urls: false,
                remove_script_host: false,
                convert_urls: true,
                branding: false,
                placeholder: "' . $placeholder . '",
                file_picker_callback: function(callback, value, meta) {
                    // Abrir selector de imágenes
                    tinymceFileBrowser(callback, value, meta);
                }
            });
        });
    </script>';
    
    return $html;
}

/**
 * Valida los campos de un formulario
 * 
 * @param array $fields Campos a validar [nombre => reglas]
 * @param array $data Datos a validar
 * @return array [isValid, errors]
 */
function validateForm($fields, $data) {
    $result = [
        'isValid' => true,
        'errors' => []
    ];
    
    foreach ($fields as $field => $rules) {
        $rules = explode('|', $rules);
        $value = $data[$field] ?? '';
        
        foreach ($rules as $rule) {
            // Regla con parámetro (ej: min:3)
            if (strpos($rule, ':') !== false) {
                list($ruleName, $ruleParam) = explode(':', $rule);
            } else {
                $ruleName = $rule;
                $ruleParam = '';
            }
            
            // Validar regla
            switch ($ruleName) {
                case 'required':
                    if (empty($value)) {
                        $result['errors'][$field] = 'Este campo es obligatorio';
                        $result['isValid'] = false;
                        break 2; // Salir del bucle interno
                    }
                    break;
                
                case 'email':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $result['errors'][$field] = 'Por favor, ingresa un email válido';
                        $result['isValid'] = false;
                        break 2;
                    }
                    break;
                
                case 'min':
                    if (!empty($value) && strlen($value) < (int)$ruleParam) {
                        $result['errors'][$field] = 'Debe tener al menos ' . $ruleParam . ' caracteres';
                        $result['isValid'] = false;
                        break 2;
                    }
                    break;
                
                case 'max':
                    if (!empty($value) && strlen($value) > (int)$ruleParam) {
                        $result['errors'][$field] = 'No debe exceder los ' . $ruleParam . ' caracteres';
                        $result['isValid'] = false;
                        break 2;
                    }
                    break;
                
                case 'numeric':
                    if (!empty($value) && !is_numeric($value)) {
                        $result['errors'][$field] = 'Debe ser un valor numérico';
                        $result['isValid'] = false;
                        break 2;
                    }
                    break;
                
                case 'url':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        $result['errors'][$field] = 'Por favor, ingresa una URL válida';
                        $result['isValid'] = false;
                        break 2;
                    }
                    break;
                
                case 'date':
                    if (!empty($value) && !strtotime($value)) {
                        $result['errors'][$field] = 'Por favor, ingresa una fecha válida';
                        $result['isValid'] = false;
                        break 2;
                    }
                    break;
                
                case 'match':
                    if (!empty($value) && $value !== ($data[$ruleParam] ?? '')) {
                        $result['errors'][$field] = 'Los campos no coinciden';
                        $result['isValid'] = false;
                        break 2;
                    }
                    break;
            }
        }
    }
    
    return $result;
}

/**
 * Muestra un mensaje de error de formulario
 * 
 * @param string $field Nombre del campo
 * @param array $errors Array de errores
 * @return string HTML del mensaje
 */
function showErrorMessage($field, $errors) {
    if (isset($errors[$field])) {
        return '<div class="invalid-feedback d-block">' . $errors[$field] . '</div>';
    }
    
    return '';
}

/**
 * Obtiene el valor anterior en caso de error de validación
 * 
 * @param string $field Nombre del campo
 * @param array $oldValues Valores anteriores
 * @param mixed $default Valor por defecto
 * @return mixed Valor a mostrar
 */
function oldValue($field, $oldValues, $default = '') {
    return $oldValues[$field] ?? $default;
}

/**
 * Crea una miniatura a partir de una imagen original
 * 
 * @param string $sourceImage Ruta completa a la imagen original
 * @param string $targetImage Ruta completa donde guardar la miniatura
 * @param int $width Ancho máximo de la miniatura
 * @param int $height Alto máximo de la miniatura
 * @return bool True si se creó correctamente, false si no
 */
function createThumbnail($sourceImage, $targetImage, $width = 400, $height = 300) {
    // Obtener información de la imagen original
    $imageInfo = getimagesize($sourceImage);
    
    if ($imageInfo === false) {
        return false;
    }
    
    // Crear imagen basada en el tipo
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($sourceImage);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($sourceImage);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($sourceImage);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($sourceImage);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    // Calcular dimensiones manteniendo proporciones
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    
    // Calcular relación de aspecto
    if ($sourceWidth > $sourceHeight) {
        $ratio = $width / $sourceWidth;
        $newWidth = (int)$width;
        $newHeight = (int)($sourceHeight * $ratio);
        
        if ($newHeight > $height) {
            $ratio = $height / $newHeight;
            $newHeight = $height;
            $newWidth = $newWidth * $ratio;
        }
    } else {
        $ratio = $height / $sourceHeight;
        $newHeight = (int)$height;
        $newWidth = (int)($sourceWidth * $ratio);
        
        if ($newWidth > $width) {
            $ratio = $width / $newWidth;
            $newWidth = $width;
            $newHeight = $newHeight * $ratio;
        }
    }
    
    // Crear imagen destino
    $target = imagecreatetruecolor($newWidth, $newHeight);
    
    // Mantener transparencia para PNG
    if ($imageInfo[2] === IMAGETYPE_PNG) {
        imagecolortransparent($target, imagecolorallocate($target, 0, 0, 0));
        imagealphablending($target, false);
        imagesavealpha($target, true);
    }
    
    // Redimensionar
    if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight)) {
        imagedestroy($source);
        imagedestroy($target);
        return false;
    }
    
    // Guardar imagen
    $success = false;
    
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($target, $targetImage, 90);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($target, $targetImage, 9);
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($target, $targetImage);
            break;
        case IMAGETYPE_WEBP:
            $success = imagewebp($target, $targetImage, 90);
            break;
    }
    
    // Liberar memoria
    imagedestroy($source);
    imagedestroy($target);
    
    return $success;
}