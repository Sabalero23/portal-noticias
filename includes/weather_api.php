<?php
/**
 * Conexión con API de clima
 * 
 * Proporciona funciones para obtener información meteorológica.
 */

/**
 * Obtiene la configuración de la API de clima
 * 
 * @return array Configuración de la API de clima
 */
function getWeatherConfig() {
    return [
        'api_key' => getSetting('weather_api_key', ''),
        'default_city' => getSetting('weather_default_city', 'Santa Fe'),
        'default_country' => getSetting('weather_default_country', 'AR'),
        'units' => getSetting('weather_units', 'metric'), // metric, imperial
        'language' => getSetting('weather_language', 'es'),
        'cache_time' => intval(getSetting('weather_cache_time', 3600)), // En segundos (1 hora)
    ];
}

/**
 * Verifica si la API de clima está configurada correctamente
 * 
 * @return bool True si está configurada, false si no
 */
function isWeatherApiConfigured() {
    $config = getWeatherConfig();
    return !empty($config['api_key']) && $config['api_key'] !== 'your_api_key_here';
}

/**
 * Obtiene datos de clima para una ciudad específica
 * 
 * @param string $city Nombre de la ciudad
 * @param string $country Código del país (opcional)
 * @return array|false Datos del clima o false si hay error
 */
function getWeatherData($city = '', $country = '') {
    $config = getWeatherConfig();
    
    // Verificar que la API está configurada
    if (!isWeatherApiConfigured()) {
        return false;
    }
    
    // Usar valores por defecto si no se proporcionan
    $city = !empty($city) ? $city : $config['default_city'];
    $country = !empty($country) ? $country : $config['default_country'];
    
    // Construir la clave de caché
    $cacheKey = 'weather_' . strtolower(str_replace(' ', '_', $city)) . '_' . strtolower($country);
    
    // Verificar si hay datos en caché
    $cachedData = getCache($cacheKey);
    if ($cachedData) {
        return $cachedData;
    }
    
    // Construir la consulta
    $query = $city;
    if (!empty($country)) {
        $query .= ',' . $country;
    }
    
    // Construir la URL de la API
    $apiUrl = 'https://api.openweathermap.org/data/2.5/weather?q=' . urlencode($query) . 
              '&appid=' . $config['api_key'] . 
              '&units=' . $config['units'] . 
              '&lang=' . $config['language'];
    
    // Realizar la petición a la API
    $response = makeApiRequest($apiUrl);
    
    // Verificar si la respuesta es válida
    if (!$response || isset($response['cod']) && $response['cod'] != 200) {
        return false;
    }
    
    // Formatear los datos
    $weatherData = formatWeatherData($response);
    
    // Guardar en caché
    setCache($cacheKey, $weatherData, $config['cache_time']);
    
    return $weatherData;
}

/**
 * Obtiene pronóstico del clima para varios días
 * 
 * @param string $city Nombre de la ciudad
 * @param string $country Código del país (opcional)
 * @param int $days Cantidad de días (por defecto 5)
 * @return array|false Datos del pronóstico o false si hay error
 */
function getWeatherForecast($city = '', $country = '', $days = 5) {
    $config = getWeatherConfig();
    
    // Verificar que la API está configurada
    if (!isWeatherApiConfigured()) {
        return false;
    }
    
    // Usar valores por defecto si no se proporcionan
    $city = !empty($city) ? $city : $config['default_city'];
    $country = !empty($country) ? $country : $config['default_country'];
    
    // Limitar días a un máximo de 5
    $days = min($days, 5);
    
    // Construir la clave de caché
    $cacheKey = 'forecast_' . strtolower(str_replace(' ', '_', $city)) . '_' . strtolower($country) . '_' . $days;
    
    // Verificar si hay datos en caché
    $cachedData = getCache($cacheKey);
    if ($cachedData) {
        return $cachedData;
    }
    
    // Construir la consulta
    $query = $city;
    if (!empty($country)) {
        $query .= ',' . $country;
    }
    
    // Construir la URL de la API
    $apiUrl = 'https://api.openweathermap.org/data/2.5/forecast?q=' . urlencode($query) . 
              '&appid=' . $config['api_key'] . 
              '&units=' . $config['units'] . 
              '&lang=' . $config['language'] . 
              '&cnt=' . ($days * 8); // 8 mediciones por día (cada 3 horas)
    
    // Realizar la petición a la API
    $response = makeApiRequest($apiUrl);
    
    // Verificar si la respuesta es válida
    if (!$response || isset($response['cod']) && $response['cod'] != 200) {
        return false;
    }
    
    // Formatear los datos
    $forecastData = formatForecastData($response, $days);
    
    // Guardar en caché
    setCache($cacheKey, $forecastData, $config['cache_time']);
    
    return $forecastData;
}

/**
 * Realiza una petición a la API
 * 
 * @param string $url URL de la API
 * @return array|false Respuesta de la API o false si hay error
 */
function makeApiRequest($url) {
    // Intentar con curl si está disponible
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response === false) {
            return false;
        }
    } 
    // Sino, usar file_get_contents
    else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
            ],
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return false;
        }
    }
    
    // Decodificar la respuesta JSON
    $data = json_decode($response, true);
    
    if (!is_array($data)) {
        return false;
    }
    
    return $data;
}

/**
 * Formatea los datos del clima actual
 * 
 * @param array $data Datos de la API
 * @return array Datos formateados
 */
function formatWeatherData($data) {
    $weather = [
        'city' => $data['name'],
        'country' => isset($data['sys']['country']) ? $data['sys']['country'] : '',
        'description' => isset($data['weather'][0]['description']) ? ucfirst($data['weather'][0]['description']) : '',
        'icon' => isset($data['weather'][0]['icon']) ? $data['weather'][0]['icon'] : '',
        'icon_url' => isset($data['weather'][0]['icon']) ? 'https://openweathermap.org/img/w/' . $data['weather'][0]['icon'] . '.png' : '',
        'temperature' => isset($data['main']['temp']) ? round($data['main']['temp']) : 0,
        'feels_like' => isset($data['main']['feels_like']) ? round($data['main']['feels_like']) : 0,
        'temp_min' => isset($data['main']['temp_min']) ? round($data['main']['temp_min']) : 0,
        'temp_max' => isset($data['main']['temp_max']) ? round($data['main']['temp_max']) : 0,
        'humidity' => isset($data['main']['humidity']) ? $data['main']['humidity'] : 0,
        'pressure' => isset($data['main']['pressure']) ? $data['main']['pressure'] : 0,
        'wind_speed' => isset($data['wind']['speed']) ? $data['wind']['speed'] : 0,
        'wind_direction' => isset($data['wind']['deg']) ? $data['wind']['deg'] : 0,
        'clouds' => isset($data['clouds']['all']) ? $data['clouds']['all'] : 0,
        'sunrise' => isset($data['sys']['sunrise']) ? date('H:i', $data['sys']['sunrise']) : '',
        'sunset' => isset($data['sys']['sunset']) ? date('H:i', $data['sys']['sunset']) : '',
        'timezone' => isset($data['timezone']) ? $data['timezone'] : 0,
        'dt' => isset($data['dt']) ? $data['dt'] : 0,
        'updated_at' => isset($data['dt']) ? date('Y-m-d H:i:s', $data['dt']) : date('Y-m-d H:i:s'),
    ];
    
    return $weather;
}

/**
 * Formatea los datos del pronóstico
 * 
 * @param array $data Datos de la API
 * @param int $days Cantidad de días
 * @return array Datos formateados
 */
function formatForecastData($data, $days) {
    $forecast = [
        'city' => $data['city']['name'],
        'country' => $data['city']['country'],
        'timezone' => $data['city']['timezone'],
        'days' => [],
    ];
    
    // Agrupar pronósticos por día
    $dailyForecasts = [];
    
    foreach ($data['list'] as $item) {
        $date = date('Y-m-d', $item['dt']);
        
        if (!isset($dailyForecasts[$date])) {
            $dailyForecasts[$date] = [];
        }
        
        $dailyForecasts[$date][] = $item;
    }
    
    // Limitar a la cantidad de días solicitados
    $dailyForecasts = array_slice($dailyForecasts, 0, $days, true);
    
    // Procesar cada día
    foreach ($dailyForecasts as $date => $items) {
        $dayData = [
            'date' => $date,
            'day_name' => date_i18n('l', strtotime($date)),
            'day_short' => date_i18n('D', strtotime($date)),
            'items' => [],
            'temp_min' => PHP_INT_MAX,
            'temp_max' => PHP_INT_MIN,
            'summary' => '',
            'icon' => '',
        ];
        
        // Encontrar temperatura mínima y máxima del día, y pronóstico predominante
        $weatherCounts = [];
        
        foreach ($items as $item) {
            // Temperatura mínima y máxima
            $dayData['temp_min'] = min($dayData['temp_min'], $item['main']['temp_min']);
            $dayData['temp_max'] = max($dayData['temp_max'], $item['main']['temp_max']);
            
            // Contar ocurrencias de cada tipo de clima
            $weatherId = $item['weather'][0]['id'];
            if (!isset($weatherCounts[$weatherId])) {
                $weatherCounts[$weatherId] = [
                    'count' => 0,
                    'description' => $item['weather'][0]['description'],
                    'icon' => $item['weather'][0]['icon'],
                ];
            }
            $weatherCounts[$weatherId]['count']++;
            
            // Formatear item individual
            $dayData['items'][] = [
                'time' => date('H:i', $item['dt']),
                'temperature' => round($item['main']['temp']),
                'description' => $item['weather'][0]['description'],
                'icon' => $item['weather'][0]['icon'],
                'icon_url' => 'https://openweathermap.org/img/w/' . $item['weather'][0]['icon'] . '.png',
                'humidity' => $item['main']['humidity'],
                'wind_speed' => $item['wind']['speed'],
                'wind_direction' => $item['wind']['deg'],
            ];
        }
        
        // Encontrar el clima predominante
        arsort($weatherCounts);
        $predominantWeather = reset($weatherCounts);
        
        $dayData['summary'] = ucfirst($predominantWeather['description']);
        $dayData['icon'] = $predominantWeather['icon'];
        $dayData['icon_url'] = 'https://openweathermap.org/img/w/' . $predominantWeather['icon'] . '.png';
        
        // Redondear temperaturas
        $dayData['temp_min'] = round($dayData['temp_min']);
        $dayData['temp_max'] = round($dayData['temp_max']);
        
        $forecast['days'][] = $dayData;
    }
    
    return $forecast;
}

/**
 * Función auxiliar para internacionalización de fechas
 * 
 * @param string $format Formato de fecha
 * @param int $timestamp Timestamp
 * @return string Fecha formateada
 */
function date_i18n($format, $timestamp) {
    $language = substr(getSetting('weather_language', 'es'), 0, 2);
    
    // Nombres de días en español
    $days_es = [
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo',
        'Mon' => 'Lun',
        'Tue' => 'Mar',
        'Wed' => 'Mié',
        'Thu' => 'Jue',
        'Fri' => 'Vie',
        'Sat' => 'Sáb',
        'Sun' => 'Dom',
    ];
    
    // Nombres de meses en español
    $months_es = [
        'January' => 'Enero',
        'February' => 'Febrero',
        'March' => 'Marzo',
        'April' => 'Abril',
        'May' => 'Mayo',
        'June' => 'Junio',
        'July' => 'Julio',
        'August' => 'Agosto',
        'September' => 'Septiembre',
        'October' => 'Octubre',
        'November' => 'Noviembre',
        'December' => 'Diciembre',
        'Jan' => 'Ene',
        'Feb' => 'Feb',
        'Mar' => 'Mar',
        'Apr' => 'Abr',
        'May' => 'May',
        'Jun' => 'Jun',
        'Jul' => 'Jul',
        'Aug' => 'Ago',
        'Sep' => 'Sep',
        'Oct' => 'Oct',
        'Nov' => 'Nov',
        'Dec' => 'Dic',
    ];
    
    // Formatear fecha
    $date = date($format, $timestamp);
    
    // Traducir si es necesario
    if ($language == 'es') {
        $date = str_replace(array_keys($days_es), array_values($days_es), $date);
        $date = str_replace(array_keys($months_es), array_values($months_es), $date);
    }
    
    return $date;
}

/**
 * Obtiene datos de caché
 * 
 * @param string $key Clave de caché
 * @return mixed Datos de caché o null si no existe
 */
function getCache($key) {
    $cacheFile = BASE_PATH . '/cache/' . $key . '.json';
    
    if (!file_exists($cacheFile)) {
        return null;
    }
    
    $data = file_get_contents($cacheFile);
    if (empty($data)) {
        return null;
    }
    
    $cache = json_decode($data, true);
    
    // Verificar si la caché expiró
    if (isset($cache['expires']) && $cache['expires'] < time()) {
        @unlink($cacheFile);
        return null;
    }
    
    return isset($cache['data']) ? $cache['data'] : null;
}

/**
 * Guarda datos en caché
 * 
 * @param string $key Clave de caché
 * @param mixed $data Datos a guardar
 * @param int $ttl Tiempo de vida en segundos
 * @return bool True si se guardó correctamente, false si no
 */
function setCache($key, $data, $ttl = 3600) {
    $cacheDir = BASE_PATH . '/cache';
    
    // Crear directorio de caché si no existe
    if (!is_dir($cacheDir)) {
        if (!mkdir($cacheDir, 0755, true)) {
            return false;
        }
    }
    
    $cacheFile = $cacheDir . '/' . $key . '.json';
    
    $cache = [
        'expires' => time() + $ttl,
        'data' => $data
    ];
    
    $json = json_encode($cache);
    
    return file_put_contents($cacheFile, $json) !== false;
}

/**
 * Genera widget de clima
 * 
 * @param string $city Ciudad (opcional)
 * @param string $country País (opcional)
 * @param string $template Plantilla a utilizar ('simple', 'detailed', 'forecast')
 * @return string HTML del widget de clima
 */
function getWeatherWidget($city = '', $country = '', $template = 'simple') {
    // Verificar si la API está configurada
    if (!isWeatherApiConfigured()) {
        return '<div class="alert alert-warning">API de clima no configurada correctamente.</div>';
    }
    
    // Obtener datos del clima
    $weather = getWeatherData($city, $country);
    
    if (!$weather) {
        return '<div class="alert alert-danger">Error al obtener datos del clima.</div>';
    }
    
    // Seleccionar la plantilla adecuada
    switch ($template) {
        case 'detailed':
            return renderDetailedWeatherWidget($weather);
            
        case 'forecast':
            $forecast = getWeatherForecast($city, $country, 5);
            if (!$forecast) {
                return '<div class="alert alert-danger">Error al obtener pronóstico del clima.</div>';
            }
            return renderForecastWeatherWidget($weather, $forecast);
            
        case 'simple':
        default:
            return renderSimpleWeatherWidget($weather);
    }
}

/**
 * Renderiza widget de clima simple
 * 
 * @param array $weather Datos del clima
 * @return string HTML del widget
 */
function renderSimpleWeatherWidget($weather) {
    $html = '<div class="weather-widget weather-simple">';
    $html .= '<div class="weather-content">';
    
    // Encabezado
    $html .= '<div class="weather-header">';
    $html .= '<h5 class="weather-location">' . $weather['city'] . ', ' . $weather['country'] . '</h5>';
    $html .= '<div class="weather-update small text-muted">Actualizado: ' . date('H:i', $weather['dt']) . '</div>';
    $html .= '</div>';
    
    // Condiciones actuales
    $html .= '<div class="weather-current d-flex align-items-center">';
    
    // Icono y temperatura
    $html .= '<div class="weather-temp-container me-3">';
    $html .= '<img src="' . $weather['icon_url'] . '" alt="' . $weather['description'] . '" class="weather-icon">';
    $html .= '<div class="weather-temp">' . $weather['temperature'] . '°</div>';
    $html .= '</div>';
    
    // Detalles
    $html .= '<div class="weather-details">';
    $html .= '<div class="weather-description">' . $weather['description'] . '</div>';
    $html .= '<div class="weather-minmax small">Máx: ' . $weather['temp_max'] . '° Mín: ' . $weather['temp_min'] . '°</div>';
    $html .= '<div class="weather-humidity small">Humedad: ' . $weather['humidity'] . '%</div>';
    $html .= '</div>';
    
    $html .= '</div>'; // .weather-current
    
    $html .= '</div>'; // .weather-content
    $html .= '</div>'; // .weather-widget
    
    return $html;
}

/**
 * Renderiza widget de clima detallado
 * 
 * @param array $weather Datos del clima
 * @return string HTML del widget
 */
function renderDetailedWeatherWidget($weather) {
    $html = '<div class="weather-widget weather-detailed">';
    $html .= '<div class="weather-content">';
    
    // Encabezado
    $html .= '<div class="weather-header d-flex justify-content-between align-items-center">';
    $html .= '<h5 class="weather-location m-0">' . $weather['city'] . ', ' . $weather['country'] . '</h5>';
    $html .= '<div class="weather-update small text-muted">Actualizado: ' . date('H:i', $weather['dt']) . '</div>';
    $html .= '</div>';
    
    // Condiciones actuales
    $html .= '<div class="weather-current d-flex align-items-center my-3">';
    
    // Icono y temperatura
    $html .= '<div class="weather-temp-container text-center me-4">';
    $html .= '<img src="' . $weather['icon_url'] . '" alt="' . $weather['description'] . '" class="weather-icon">';
    $html .= '<div class="weather-temp display-6">' . $weather['temperature'] . '°</div>';
    $html .= '<div class="weather-description">' . $weather['description'] . '</div>';
    $html .= '</div>';
    
    // Detalles
    $html .= '<div class="weather-details flex-grow-1">';
    $html .= '<div class="row g-2">';
    
    // Columna 1
    $html .= '<div class="col-6">';
    $html .= '<div class="weather-detail-item"><i class="fas fa-temperature-high me-2"></i>Sensación: ' . $weather['feels_like'] . '°</div>';
    $html .= '<div class="weather-detail-item"><i class="fas fa-arrow-up me-2"></i>Máxima: ' . $weather['temp_max'] . '°</div>';
    $html .= '<div class="weather-detail-item"><i class="fas fa-arrow-down me-2"></i>Mínima: ' . $weather['temp_min'] . '°</div>';
    $html .= '</div>';
    
    // Columna 2
    $html .= '<div class="col-6">';
    $html .= '<div class="weather-detail-item"><i class="fas fa-wind me-2"></i>Viento: ' . $weather['wind_speed'] . ' km/h</div>';
    $html .= '<div class="weather-detail-item"><i class="fas fa-tint me-2"></i>Humedad: ' . $weather['humidity'] . '%</div>';
    $html .= '<div class="weather-detail-item"><i class="fas fa-compress-alt me-2"></i>Presión: ' . $weather['pressure'] . ' hPa</div>';
    $html .= '</div>';
    
    $html .= '</div>'; // .row
    $html .= '</div>'; // .weather-details
    
    $html .= '</div>'; // .weather-current
    
    // Amanecer y atardecer
    $html .= '<div class="weather-sun-times d-flex justify-content-around p-2 bg-light rounded mt-2">';
    $html .= '<div class="weather-sunrise text-center"><i class="fas fa-sun mb-1 text-warning"></i><div>Amanecer<br>' . $weather['sunrise'] . '</div></div>';
    $html .= '<div class="weather-sunset text-center"><i class="fas fa-moon mb-1 text-primary"></i><div>Atardecer<br>' . $weather['sunset'] . '</div></div>';
    $html .= '</div>';
    
    $html .= '</div>'; // .weather-content
    $html .= '</div>'; // .weather-widget
    
    return $html;
}

/**
 * Renderiza widget de pronóstico del clima
 * 
 * @param array $weather Datos del clima actual
 * @param array $forecast Datos del pronóstico
 * @return string HTML del widget
 */
function renderForecastWeatherWidget($weather, $forecast) {
    $html = '<div class="weather-widget weather-forecast">';
    $html .= '<div class="weather-content">';
    
    // Encabezado
    $html .= '<div class="weather-header d-flex justify-content-between align-items-center">';
    $html .= '<h5 class="weather-location m-0">' . $weather['city'] . ', ' . $weather['country'] . '</h5>';
    $html .= '<div class="weather-update small text-muted">Actualizado: ' . date('H:i', $weather['dt']) . '</div>';
    $html .= '</div>';
    
    // Condiciones actuales (versión compacta)
    $html .= '<div class="weather-current d-flex align-items-center my-3">';
    $html .= '<img src="' . $weather['icon_url'] . '" alt="' . $weather['description'] . '" class="weather-icon me-3">';
    $html .= '<div class="weather-temp-info">';
    $html .= '<div class="weather-temp h4 mb-0">' . $weather['temperature'] . '°</div>';
    $html .= '<div class="weather-description">' . $weather['description'] . '</div>';
    $html .= '</div>';
    $html .= '</div>'; // .weather-current
    
    // Pronóstico por días
    $html .= '<div class="weather-forecast-days">';
    $html .= '<h6 class="border-bottom pb-2 mb-2">Pronóstico próximos días</h6>';
    
    $html .= '<div class="row">';
    
    // Mostrar solo los primeros 5 días
    $days = array_slice($forecast['days'], 0, 5);
    
    foreach ($days as $day) {
        $html .= '<div class="col text-center forecast-day">';
        $html .= '<div class="forecast-date">' . $day['day_short'] . '</div>';
        $html .= '<img src="' . $day['icon_url'] . '" alt="' . $day['summary'] . '" class="forecast-icon">';
        $html .= '<div class="forecast-temps"><span class="forecast-max">' . $day['temp_max'] . '°</span> <span class="forecast-min text-muted">' . $day['temp_min'] . '°</span></div>';
        $html .= '</div>';
    }
    
    $html .= '</div>'; // .row
    $html .= '</div>'; // .weather-forecast-days
    
    // Enlace para ver más detalles
    $html .= '<div class="text-center mt-3">';
    $html .= '<a href="https://openweathermap.org/city/' . $weather['city'] . '" target="_blank" class="btn btn-sm btn-outline-primary">Ver pronóstico completo</a>';
    $html .= '</div>';
    
    $html .= '</div>'; // .weather-content
    $html .= '</div>'; // .weather-widget
    
    return $html;
}

/**
 * Genera script JavaScript para cargar el widget de clima de forma asíncrona
 * 
 * @param string $city Ciudad
 * @param string $country País
 * @param string $template Plantilla a utilizar
 * @param string $targetId ID del elemento donde se cargará el widget
 * @return string Código JavaScript
 */
function getWeatherWidgetScript($city = '', $country = '', $template = 'simple', $targetId = 'weather-widget') {
    $script = '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const weatherContainer = document.getElementById("' . $targetId . '");
        if (!weatherContainer) return;
        
        // Mostrar indicador de carga
        weatherContainer.innerHTML = \'<div class="text-center py-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p class="mt-2 mb-0">Cargando clima...</p></div>\';
        
        // Cargar datos del clima
        fetch("weather_widget.php?city=' . urlencode($city) . '&country=' . urlencode($country) . '&template=' . urlencode($template) . '")
            .then(response => {
                if (!response.ok) {
                    throw new Error("Error al cargar el clima");
                }
                return response.text();
            })
            .then(html => {
                weatherContainer.innerHTML = html;
            })
            .catch(error => {
                weatherContainer.innerHTML = \'<div class="alert alert-danger">Error al cargar datos del clima.</div>\';
                console.error("Error:", error);
            });
    });
    </script>';
    
    return $script;
}
?>