/**
 * Portal de Noticias - Widget de Clima
 * Versión corregida que elimina duplicaciones y errores
 */

// Variable global para controlar si ya se inicializó el clima
let weatherInitialized = false;

/**
 * Configuración del clima desde atributos del DOM
 */
function getWeatherConfig() {
    const body = document.body;
    const apiKey = body.getAttribute('data-weather-api-key');
    const defaultCity = body.getAttribute('data-default-city') || 'Reconquista';
    const units = body.getAttribute('data-weather-units') || 'metric';
    
    return {
        apiKey,
        defaultCity,
        units,
        isValid: apiKey && apiKey !== '' && apiKey !== 'undefined' && apiKey !== 'null'
    };
}

/**
 * Función principal para inicializar el clima
 */
function initWeather() {
    // Evitar inicialización múltiple
    if (weatherInitialized) {
        console.log('[Weather] Ya inicializado, saltando...');
        return;
    }
    
    const config = getWeatherConfig();
    
    // Verificar si hay widgets de clima en la página
    const weatherWidget = document.getElementById('weather-widget');
    const miniWeatherWidget = document.getElementById('weather-temp-mini');
    
    if (!weatherWidget && !miniWeatherWidget) {
        console.log('[Weather] No se encontraron widgets de clima');
        return;
    }
    
    // Verificar si la API está configurada correctamente
    if (!config.isValid) {
        console.warn('[Weather] API key no válida o no configurada');
        showWeatherError('API del clima no configurada');
        return;
    }
    
    weatherInitialized = true;
    console.log('[Weather] Inicializando widgets de clima...');
    
    // Obtener clima con geolocalización o ciudad por defecto
    getWeatherData(config);
}

/**
 * Obtener datos del clima con geolocalización
 */
function getWeatherData(config) {
    const options = {
        enableHighAccuracy: false,
        timeout: 8000,  // Aumentar timeout a 8 segundos
        maximumAge: 3600000 // 1 hora de caché para la geolocalización
    };
    
    // Intentar obtener ubicación del usuario
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            // Éxito en geolocalización
            position => {
                console.log('[Weather] Geolocalización exitosa');
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                fetchWeatherByCoords(lat, lon, config);
            },
            // Error en geolocalización
            error => {
                console.warn('[Weather] Error de geolocalización:', error.message);
                // Usar ciudad por defecto
                fetchWeatherByCity(config.defaultCity, config);
            },
            options
        );
    } else {
        console.warn('[Weather] Geolocalización no disponible');
        // Usar ciudad por defecto
        fetchWeatherByCity(config.defaultCity, config);
    }
}

/**
 * Obtener clima por coordenadas
 */
function fetchWeatherByCoords(lat, lon, config) {
    const url = `https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lon}&appid=${config.apiKey}&units=${config.units}&lang=es`;
    
    console.log('[Weather] Obteniendo clima por coordenadas...');
    fetchWeatherData(url, config);
}

/**
 * Obtener clima por ciudad
 */
function fetchWeatherByCity(city, config) {
    const url = `https://api.openweathermap.org/data/2.5/weather?q=${city}&appid=${config.apiKey}&units=${config.units}&lang=es`;
    
    console.log('[Weather] Obteniendo clima por ciudad:', city);
    fetchWeatherData(url, config);
}

/**
 * Realizar fetch de datos del clima con manejo de errores
 */
function fetchWeatherData(url, config) {
    // Validar URL antes del fetch
    if (!url || url.includes('undefined') || url.includes('null')) {
        console.error('[Weather] URL inválida:', url);
        showWeatherError('Error en configuración del clima');
        return;
    }
    
    // Mostrar estado de carga
    showWeatherLoading();
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('[Weather] Datos recibidos exitosamente');
            
            // Validar estructura de datos
            if (!data || !data.main || !data.weather || !data.weather[0]) {
                throw new Error('Datos del clima incompletos o inválidos');
            }
            
            // Actualizar ambos widgets
            updateWeatherWidget(data);
            updateMiniWeatherWidget(data);
        })
        .catch(error => {
            console.error('[Weather] Error al obtener datos:', error);
            
            // Intentar con ciudad por defecto si falló con coordenadas
            if (url.includes('lat=') && url.includes('lon=')) {
                console.log('[Weather] Intentando con ciudad por defecto...');
                fetchWeatherByCity(config.defaultCity, config);
            } else {
                showWeatherError('No se pudo obtener información del clima');
            }
        });
}

/**
 * Mostrar estado de carga en los widgets
 */
function showWeatherLoading() {
    const weatherWidget = document.getElementById('weather-widget');
    const miniWidget = document.getElementById('weather-temp-mini');
    
    if (weatherWidget) {
        weatherWidget.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2 mb-0 small">Obteniendo clima...</p>
            </div>
        `;
    }
    
    if (miniWidget) {
        miniWidget.innerHTML = '<span class="text-muted">Cargando...</span>';
    }
}

/**
 * Actualizar widget principal de clima
 */
function updateWeatherWidget(data) {
    const weatherWidget = document.getElementById('weather-widget');
    if (!weatherWidget) return;
    
    try {
        // Extraer datos con validaciones
        const temp = Math.round(data.main.temp || 0);
        const feelsLike = Math.round(data.main.feels_like || data.main.temp || 0);
        const description = data.weather[0].description || 'No disponible';
        const cityName = data.name || 'Ciudad desconocida';
        const country = data.sys?.country || '';
        const humidity = data.main.humidity || 0;
        const windSpeed = Math.round((data.wind?.speed || 0) * 3.6); // m/s a km/h
        const iconCode = data.weather[0].icon || '01d';
        const iconUrl = `https://openweathermap.org/img/wn/${iconCode}@2x.png`;
        
        // Generar HTML del widget
        weatherWidget.innerHTML = `
            <div class="weather-content text-center">
                <div class="weather-location mb-2">
                    <i class="fas fa-map-marker-alt me-1"></i>
                    ${cityName}${country ? ', ' + country : ''}
                </div>
                <div class="weather-main d-flex justify-content-center align-items-center mb-2">
                    <img src="${iconUrl}" alt="${description}" class="weather-icon me-2" width="64" height="64" loading="lazy">
                    <div class="weather-temp">${temp}°C</div>
                </div>
                <div class="weather-description mb-3" style="text-transform: capitalize;">
                    ${description}
                </div>
                <div class="weather-details row text-center">
                    <div class="col-4">
                        <i class="fas fa-thermometer-half text-info"></i>
                        <div class="small">ST: ${feelsLike}°C</div>
                    </div>
                    <div class="col-4">
                        <i class="fas fa-tint text-primary"></i>
                        <div class="small">${humidity}%</div>
                    </div>
                    <div class="col-4">
                        <i class="fas fa-wind text-secondary"></i>
                        <div class="small">${windSpeed} km/h</div>
                    </div>
                </div>
            </div>
        `;
        
        console.log('[Weather] Widget principal actualizado');
    } catch (error) {
        console.error('[Weather] Error al actualizar widget principal:', error);
        showWeatherError('Error al mostrar el clima');
    }
}

/**
 * Actualizar mini widget de clima
 */
function updateMiniWeatherWidget(data) {
    const miniWidget = document.getElementById('weather-temp-mini');
    if (!miniWidget) return;
    
    try {
        const temp = Math.round(data.main.temp || 0);
        const cityName = data.name || 'Ciudad';
        const iconCode = data.weather[0].icon || '01d';
        
        // Mapear código de icono a Font Awesome
        const faIcon = mapWeatherIconToFA(iconCode);
        
        // Actualizar el icono
        const parentSpan = miniWidget.closest('span');
        if (parentSpan) {
            const iconElement = parentSpan.querySelector('i');
            if (iconElement) {
                iconElement.className = `fas ${faIcon} me-1`;
            }
        }
        
        // Actualizar texto
        miniWidget.innerHTML = `${cityName}: ${temp}°C`;
        
        console.log('[Weather] Mini widget actualizado');
    } catch (error) {
        console.error('[Weather] Error al actualizar mini widget:', error);
        if (miniWidget) {
            miniWidget.innerHTML = 'Clima no disponible';
        }
    }
}

/**
 * Mapear códigos de icono de OpenWeatherMap a iconos de Font Awesome
 */
function mapWeatherIconToFA(iconCode) {
    const iconMap = {
        '01d': 'fa-sun',           // cielo despejado día
        '01n': 'fa-moon',          // cielo despejado noche
        '02d': 'fa-cloud-sun',     // pocas nubes día
        '02n': 'fa-cloud-moon',    // pocas nubes noche
        '03d': 'fa-cloud',         // nubes dispersas
        '03n': 'fa-cloud',
        '04d': 'fa-cloud',         // nubes rotas
        '04n': 'fa-cloud',
        '09d': 'fa-cloud-rain',    // lluvia de chubascos
        '09n': 'fa-cloud-rain',
        '10d': 'fa-cloud-sun-rain', // lluvia día
        '10n': 'fa-cloud-moon-rain', // lluvia noche
        '11d': 'fa-bolt',          // tormenta
        '11n': 'fa-bolt',
        '13d': 'fa-snowflake',     // nieve
        '13n': 'fa-snowflake',
        '50d': 'fa-smog',          // neblina
        '50n': 'fa-smog'
    };
    
    return iconMap[iconCode] || 'fa-cloud';
}

/**
 * Mostrar mensaje de error en los widgets
 */
function showWeatherError(message = 'No se pudo cargar la información del clima') {
    const weatherWidget = document.getElementById('weather-widget');
    const miniWidget = document.getElementById('weather-temp-mini');
    
    if (weatherWidget) {
        weatherWidget.innerHTML = `
            <div class="text-center py-3">
                <i class="fas fa-exclamation-triangle text-warning mb-2" style="font-size: 2rem;"></i>
                <p class="mb-1 small">${message}</p>
                <small class="text-muted">Verifica la configuración en el panel admin</small>
            </div>
        `;
    }
    
    if (miniWidget) {
        miniWidget.innerHTML = 'N/D';
        
        // También actualizar el icono a error
        const parentSpan = miniWidget.closest('span');
        if (parentSpan) {
            const iconElement = parentSpan.querySelector('i');
            if (iconElement) {
                iconElement.className = 'fas fa-exclamation-triangle me-1 text-warning';
            }
        }
    }
    
    console.warn('[Weather] Error mostrado:', message);
}

/**
 * Reinicializar el clima (útil para actualizaciones dinámicas)
 */
function reinitWeather() {
    weatherInitialized = false;
    initWeather();
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Pequeño delay para asegurar que todos los elementos estén disponibles
    setTimeout(initWeather, 100);
});

// Reintentar si falla la primera vez (después de 30 segundos)
setTimeout(() => {
    if (!weatherInitialized) {
        console.log('[Weather] Reintentando inicialización...');
        initWeather();
    }
}, 30000);

// Exponer funciones globalmente por compatibilidad
window.initWeather = initWeather;
window.reinitWeather = reinitWeather;