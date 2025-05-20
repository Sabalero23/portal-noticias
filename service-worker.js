/**
 * Portal de Noticias - Service Worker
 * Versión: 1.2.0
 * Última actualización: 11/05/2025
 */
// Nombres de caché
const CACHE_VERSION = '1.2.0';
const STATIC_CACHE = 'static-' + CACHE_VERSION;
const DYNAMIC_CACHE = 'dynamic-' + CACHE_VERSION;
const API_CACHE = 'api-' + CACHE_VERSION;
const IMG_CACHE = 'images-' + CACHE_VERSION;
const NEWS_CACHE = 'news-' + CACHE_VERSION;

// Archivos a cachear inicialmente (recursos esenciales)
const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/offline.php',
    '/assets/themes/default/styles.css',
    '/assets/themes/default/responsive.css',
    '/assets/js/scripts.js',
    '/manifest.json',
    '/assets/img/logo.png',
    '/assets/img/favicon.ico',
    '/assets/img/placeholder.jpg',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Rutas de API o URLs externas a cachear con estrategia API
const API_URLS = [
    'openweathermap.org/data',
    '/api/',
    'api.portalnoticias.com'
];

// Páginas principales para navegación offline
const OFFLINE_PAGES = [
    '/',
    '/index.php',
    '/category.php',
    '/search.php',
    '/about.php',
    '/contact.php',
    '/profile.php'
];

// Límite para caché dinámico
const CACHE_DYNAMIC_LIMIT = 50;
const CACHE_NEWS_LIMIT = 30;
const CACHE_IMAGES_LIMIT = 100;

// Duración máxima del caché para noticias (1 día en ms)
const NEWS_CACHE_DURATION = 24 * 60 * 60 * 1000;

// Flag para indicar si la actualización está pendiente
let isUpdatePending = false;

// Cuando se instala el Service Worker
self.addEventListener('install', event => {
    console.log('[Service Worker] Instalando v' + CACHE_VERSION);
    
    // Pre-cachear recursos estáticos
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[Service Worker] Pre-cacheando recursos estáticos');
                
                // Usar cache.add para cada archivo individualmente para manejar fallos
                const cachePromises = STATIC_ASSETS.map(url => {
                    return cache.add(url).catch(error => {
                        console.error(`[Service Worker] Error al cachear: ${url}`, error);
                        // Continuamos aunque falle un recurso
                        return Promise.resolve();
                    });
                });
                
                return Promise.all(cachePromises);
            })
            .then(() => {
                console.log('[Service Worker] Pre-caché completado');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('[Service Worker] Error al cachear recursos estáticos:', error);
                // Continuamos con la instalación aunque haya errores
                return self.skipWaiting();
            })
    );
});

// Cuando se activa el Service Worker (después de instalar)
self.addEventListener('activate', event => {
    console.log('[Service Worker] Activando v' + CACHE_VERSION);
    
    // Lista de cachés a mantener
    const currentCaches = [STATIC_CACHE, DYNAMIC_CACHE, API_CACHE, IMG_CACHE, NEWS_CACHE];
    
    event.waitUntil(
        // Eliminar cachés antiguos
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        // Verificar si es una versión antigua de nuestros cachés
                        if (!currentCaches.includes(cacheName)) {
                            console.log('[Service Worker] Eliminando caché obsoleto:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('[Service Worker] Reclamando control de clientes');
                return self.clients.claim();
            })
            .catch(error => {
                console.error('[Service Worker] Error durante la activación:', error);
            })
    );
});

/**
 * Limpia el caché cuando excede el límite
 * 
 * @param {string} cacheName Nombre del caché a limpiar
 * @param {number} maxItems Número máximo de items a mantener
 * @param {Function} sortFunction Función opcional para ordenar los elementos (por defecto FIFO)
 */
const trimCache = (cacheName, maxItems, sortFunction = null) => {
    caches.open(cacheName)
        .then(cache => {
            return cache.keys()
                .then(keys => {
                    if (keys.length > maxItems) {
                        console.log(`[Service Worker] Limpiando caché ${cacheName} (${keys.length} > ${maxItems})`);
                        
                        // Si hay una función de ordenación, ordenamos las keys
                        if (sortFunction && typeof sortFunction === 'function') {
                            keys = sortFunction(keys);
                        }
                        
                        // Eliminar elementos antiguos hasta llegar al límite
                        const deletePromises = keys.slice(0, keys.length - maxItems).map(key => {
                            return cache.delete(key);
                        });
                        
                        return Promise.all(deletePromises);
                    }
                });
        })
        .catch(error => {
            console.error(`[Service Worker] Error al limpiar caché ${cacheName}:`, error);
        });
};

/**
 * Comprueba y elimina noticias antiguas del caché
 * basado en su timestamp de cacheo
 */
const cleanExpiredNews = () => {
    const now = Date.now();
    
    caches.open(NEWS_CACHE)
        .then(cache => {
            return cache.keys()
                .then(requests => {
                    const expiredRequests = [];
                    
                    // Procesar cada request para ver si ha expirado
                    const processPromises = requests.map(request => {
                        return cache.match(request)
                            .then(response => {
                                if (!response) return;
                                
                                // Extraer timestamp de los headers
                                const cachedTime = response.headers.get('sw-cache-timestamp');
                                
                                if (cachedTime && (now - parseInt(cachedTime, 10)) > NEWS_CACHE_DURATION) {
                                    expiredRequests.push(request);
                                }
                            });
                    });
                    
                    return Promise.all(processPromises)
                        .then(() => {
                            // Eliminar las solicitudes expiradas
                            if (expiredRequests.length > 0) {
                                console.log(`[Service Worker] Eliminando ${expiredRequests.length} noticias expiradas`);
                                return Promise.all(expiredRequests.map(request => cache.delete(request)));
                            }
                        });
                });
        })
        .catch(error => {
            console.error('[Service Worker] Error al limpiar noticias expiradas:', error);
        });
};

/**
 * Determina si la URL es una página de noticias
 * 
 * @param {URL} url URL a verificar
 * @returns {boolean} True si es una página de noticias
 */
const isNewsPage = (url) => {
    return url.pathname.includes('/news.php') || 
           (url.pathname === '/' || url.pathname === '/index.php') ||
           url.pathname.includes('/category.php') ||
           url.pathname.includes('/tag.php');
};

/**
 * Determina si la URL es una API
 * 
 * @param {URL} url URL a verificar
 * @returns {boolean} True si es una URL de API
 */
const isApiUrl = (url) => {
    return API_URLS.some(apiUrl => url.href.includes(apiUrl));
};

/**
 * Estrategia de caché Stale-While-Revalidate
 * Devuelve la respuesta cacheada mientras actualiza el caché en segundo plano
 * 
 * @param {FetchEvent} event Evento fetch
 * @param {string} cacheName Nombre del caché a usar
 * @param {number} maxItems Cantidad máxima de items a mantener en caché
 * @param {boolean} addTimestamp Si es true, añade un timestamp al guardar en caché
 * @returns {Promise<Response>} Respuesta al fetch
 */
const staleWhileRevalidate = (event, cacheName, maxItems = null, addTimestamp = false) => {
    return caches.open(cacheName)
        .then(cache => {
            return cache.match(event.request)
                .then(cachedResponse => {
                    // Crear la promesa de fetch con reintentos
                    const fetchPromise = fetchWithRetry(event.request, 3)
                        .then(networkResponse => {
                            // Solo cachear respuestas válidas
                            if (networkResponse && networkResponse.status === 200) {
                                // Si necesitamos añadir timestamp, creamos una nueva respuesta
                                if (addTimestamp) {
                                    const clonedResponse = networkResponse.clone();
                                    const headers = new Headers(clonedResponse.headers);
                                    headers.append('sw-cache-timestamp', Date.now().toString());
                                    
                                    return clonedResponse.blob()
                                        .then(body => {
                                            const timestampedResponse = new Response(body, {
                                                status: clonedResponse.status,
                                                statusText: clonedResponse.statusText,
                                                headers: headers
                                            });
                                            
                                            cache.put(event.request, timestampedResponse);
                                            
                                            // Limpiar caché si es necesario
                                            if (maxItems) {
                                                trimCache(cacheName, maxItems);
                                            }
                                            
                                            return networkResponse;
                                        });
                                } else {
                                    // Cachear respuesta sin modificar
                                    cache.put(event.request, networkResponse.clone());
                                    
                                    // Limpiar caché si es necesario
                                    if (maxItems) {
                                        trimCache(cacheName, maxItems);
                                    }
                                }
                            }
                            return networkResponse;
                        })
                        .catch(error => {
                            console.warn(`[Service Worker] Error en fetching: ${event.request.url}`, error);
                            // En caso de error, no hacemos nada y retornamos la respuesta de caché si existe
                        });
                    
                    // Devolver de caché y actualizar en segundo plano
                    return cachedResponse || fetchPromise;
                });
        });
};

/**
 * Estrategia Network First con fallback a cache y respuesta offline
 * 
 * @param {FetchEvent} event Evento fetch
 * @param {string} cacheName Nombre del caché a usar
 * @returns {Promise<Response>} Respuesta al fetch
 */
const networkFirstWithFallback = (event, cacheName) => {
    return fetchWithRetry(event.request, 2)
        .then(response => {
            // Solo cachear respuestas válidas
            if (response && response.status === 200) {
                const clonedResponse = response.clone();
                caches.open(cacheName)
                    .then(cache => {
                        // Para URLs de noticias, añadir timestamp
                        if (isNewsPage(new URL(event.request.url))) {
                            const headers = new Headers(clonedResponse.headers);
                            headers.append('sw-cache-timestamp', Date.now().toString());
                            
                            return clonedResponse.blob()
                                .then(body => {
                                    const timestampedResponse = new Response(body, {
                                        status: clonedResponse.status,
                                        statusText: clonedResponse.statusText,
                                        headers: headers
                                    });
                                    
                                    cache.put(event.request, timestampedResponse);
                                });
                        } else {
                            cache.put(event.request, clonedResponse);
                        }
                    });
            }
            return response;
        })
        .catch(() => {
            return caches.match(event.request)
                .then(cachedResponse => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    
                    // Si es una petición de API y no hay caché, devolvemos una respuesta de error predeterminada
                    if (isApiUrl(new URL(event.request.url))) {
                        return new Response(JSON.stringify({
                            error: true,
                            message: 'No hay conexión a Internet',
                            offline: true,
                            timestamp: Date.now()
                        }), {
                            headers: { 'Content-Type': 'application/json' }
                        });
                    }
                    
                    // Si es una página HTML, redirigir a offline.php
                    const acceptHeader = event.request.headers.get('accept');
                    if (acceptHeader && acceptHeader.includes('text/html')) {
                        return caches.match('/offline.php');
                    }
                    
                    // Si es una imagen, devolver imagen placeholder
                    if (event.request.url.match(/\.(jpg|jpeg|png|gif|webp|svg)$/)) {
                        return caches.match('/assets/img/placeholder.jpg');
                    }
                    
                    return null;
                });
        });
};

/**
 * Estrategia Cache First - intenta primero desde el caché, si no está va a la red
 * 
 * @param {FetchEvent} event Evento fetch
 * @param {string} cacheName Nombre del caché a usar
 * @param {number} maxItems Cantidad máxima de items a mantener en caché
 * @returns {Promise<Response>} Respuesta al fetch
 */
const cacheFirstWithNetwork = (event, cacheName, maxItems = null) => {
    return caches.match(event.request)
        .then(cachedResponse => {
            if (cachedResponse) {
                // Actualizar el caché en segundo plano (stale-while-revalidate)
                if (navigator.onLine) {
                    fetchWithRetry(event.request, 1)
                        .then(networkResponse => {
                            if (networkResponse && networkResponse.status === 200) {
                                caches.open(cacheName)
                                    .then(cache => {
                                        cache.put(event.request, networkResponse);
                                        if (maxItems) {
                                            trimCache(cacheName, maxItems);
                                        }
                                    });
                            }
                        })
                        .catch(() => {
                            // Ignorar errores de actualización en segundo plano
                        });
                }
                return cachedResponse;
            }
            
            // Si no está en caché, intentar desde la red
            return fetchWithRetry(event.request, 2)
                .then(response => {
                    // Solo cachear respuestas válidas
                    if (response && response.status === 200) {
                        const clonedResponse = response.clone();
                        caches.open(cacheName)
                            .then(cache => {
                                cache.put(event.request, clonedResponse);
                                // Limpiar caché si es necesario
                                if (maxItems) {
                                    trimCache(cacheName, maxItems);
                                }
                            });
                    }
                    return response;
                })
                .catch(() => {
                    // Para imágenes fallidas, servir una imagen de respaldo
                    if (event.request.url.match(/\.(jpg|jpeg|png|gif|webp|svg)$/)) {
                        return caches.match('/assets/img/placeholder.jpg');
                    }
                    return null;
                });
        });
};

/**
 * Realiza fetch con reintentos en caso de fallo
 * 
 * @param {Request} request Request a fetchear
 * @param {number} maxRetries Número máximo de reintentos
 * @param {number} retryDelay Delay en ms entre reintentos
 * @returns {Promise<Response>} Respuesta del fetch
 */
const fetchWithRetry = (request, maxRetries = 3, retryDelay = 500) => {
    return new Promise((resolve, reject) => {
        const attemptFetch = (attemptsLeft) => {
            fetch(request.clone())
                .then(resolve)
                .catch(error => {
                    if (attemptsLeft > 0) {
                        setTimeout(() => attemptFetch(attemptsLeft - 1), retryDelay);
                    } else {
                        reject(error);
                    }
                });
        };
        
        attemptFetch(maxRetries);
    });
};

/**
 * Comprueba si hay una nueva versión del Service Worker
 * y avisa a los clientes
 */
const checkForUpdates = () => {
    if (isUpdatePending) return;
    
    isUpdatePending = true;
    
    registration.update()
        .then(() => {
            isUpdatePending = false;
        })
        .catch(error => {
            console.error('[Service Worker] Error al buscar actualizaciones:', error);
            isUpdatePending = false;
        });
};

// Revisar periódicamente actualizaciones del SW (cada hora)
setInterval(checkForUpdates, 60 * 60 * 1000);

// Limpiar periódicamente noticias expiradas (cada 2 horas)
setInterval(cleanExpiredNews, 2 * 60 * 60 * 1000);

// Interceptar peticiones fetch
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    
    // Ignorar peticiones a servicios de análisis, anuncios, etc.
    if (url.hostname.includes('google-analytics.com') || 
        url.hostname.includes('googletagmanager.com') ||
        url.pathname.includes('/ads/') ||
        url.hostname.includes('facebook.net') ||
        url.hostname.includes('hotjar.com')) {
        return;
    }
    
    // Ignorar solicitudes POST (no se pueden cachear adecuadamente)
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Para peticiones a la misma página
    if (url.origin === self.location.origin) {
        // Para archivos estáticos (CSS, JS, fuentes)
        if (event.request.url.match(/\.(css|js|woff2|woff|ttf|eot)$/)) {
            event.respondWith(staleWhileRevalidate(event, STATIC_CACHE));
            return;
        }
        
        // Para imágenes
        if (event.request.url.match(/\.(jpg|jpeg|png|gif|webp|svg)$/)) {
            event.respondWith(cacheFirstWithNetwork(event, IMG_CACHE, CACHE_IMAGES_LIMIT));
            return;
        }
        
        // Para páginas de noticias (HTML)
        if (isNewsPage(url) && event.request.headers.get('accept').includes('text/html')) {
            event.respondWith(networkFirstWithFallback(event, NEWS_CACHE));
            
            // Si estamos online, limpiar noticias antiguas después de responder
            if (navigator.onLine) {
                event.waitUntil(cleanExpiredNews());
            }
            return;
        }
        
        // Para páginas principales (navegación offline)
        if (OFFLINE_PAGES.includes(url.pathname) && 
            event.request.headers.get('accept').includes('text/html')) {
            event.respondWith(networkFirstWithFallback(event, DYNAMIC_CACHE));
            return;
        }
        
        // Para otras páginas PHP (HTML)
        if (event.request.headers.get('accept').includes('text/html')) {
            event.respondWith(networkFirstWithFallback(event, DYNAMIC_CACHE));
            return;
        }
    }
    
    // Para peticiones API (externa o interna)
    if (isApiUrl(url)) {
        event.respondWith(networkFirstWithFallback(event, API_CACHE));
        return;
    }
    
    // Para cualquier otra petición
    event.respondWith(staleWhileRevalidate(event, DYNAMIC_CACHE, CACHE_DYNAMIC_LIMIT));
});

// Escuchar mensajes desde la página
self.addEventListener('message', event => {
    console.log('[Service Worker] Mensaje recibido:', event.data);
    
    if (event.data && event.data.action === 'skipWaiting') {
        self.skipWaiting();
    }
    
    // Precachear páginas
    if (event.data && event.data.action === 'precachePages' && event.data.urls) {
        caches.open(DYNAMIC_CACHE)
            .then(cache => {
                console.log('[Service Worker] Pre-cacheando páginas:', event.data.urls);
                return Promise.all(
                    event.data.urls.map(url => {
                        return fetch(url)
                            .then(response => {
                                if (response.status === 200) {
                                    return cache.put(url, response);
                                }
                            })
                            .catch(error => {
                                console.error(`[Service Worker] Error al precachear ${url}:`, error);
                            });
                    })
                );
            });
    }
    
    // Limpiar cachés
    if (event.data && event.data.action === 'clearCache' && event.data.cacheName) {
        const cacheName = event.data.cacheName;
        console.log('[Service Worker] Limpiando caché:', cacheName);
        
        if (cacheName === 'all') {
            // Limpiar todos los cachés
            caches.keys()
                .then(cacheNames => {
                    return Promise.all(
                        cacheNames.map(name => {
                            console.log('[Service Worker] Eliminando caché:', name);
                            return caches.delete(name);
                        })
                    );
                })
                .then(() => {
                    // Notificar al cliente que se ha completado
                    if (event.source) {
                        event.source.postMessage({
                            message: 'Todos los cachés limpiados correctamente',
                            action: 'cachesCleared',
                            status: 'success'
                        });
                    }
                });
        } else {
            // Limpiar un caché específico
            caches.delete(cacheName)
                .then(success => {
                    if (event.source) {
                        event.source.postMessage({
                            message: success ? 
                                `Caché ${cacheName} limpiado correctamente` : 
                                `No se encontró el caché ${cacheName}`,
                            action: 'cacheCleared',
                            status: success ? 'success' : 'error',
                            cacheName: cacheName
                        });
                    }
                });
        }
    }
});  // Cierre del event listener 'message'

// Funciones para sincronización en segundo plano
function syncComments() {
    return fetch('sync_comments.php')
        .then(response => {
            return response.json();
        })
        .then(data => {
            console.log('[Service Worker] Comentarios sincronizados:', data);
        })
        .catch(error => {
            console.error('[Service Worker] Error al sincronizar comentarios:', error);
        });
}

function syncNewsletter() {
    return fetch('sync_newsletter.php')
        .then(response => {
            return response.json();
        })
        .then(data => {
            console.log('[Service Worker] Newsletter sincronizado:', data);
        })
        .catch(error => {
            console.error('[Service Worker] Error al sincronizar newsletter:', error);
        });
}

// Eventos de sincronización en segundo plano
self.addEventListener('sync', event => {
    console.log('[Service Worker] Sync event fired for tag:', event.tag);
    
    if (event.tag === 'sync-comments') {
        event.waitUntil(syncComments());
    } else if (event.tag === 'sync-newsletter') {
        event.waitUntil(syncNewsletter());
    }
});

// Manejar notificaciones push
self.addEventListener('push', event => {
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            console.error('[Service Worker] Error al parsear datos de push:', e);
            data = {
                title: 'Notificación',
                body: event.data ? event.data.text() : 'Nueva actualización'
            };
        }
    }
    
    const options = {
        body: data.body || 'Nueva actualización disponible',
        icon: data.icon || './assets/img/icons/icon-192x192.png',
        badge: './assets/img/icons/badge-128x128.png',
        data: {
            url: data.url || './'
        },
        actions: [
            {
                action: 'view',
                title: 'Ver ahora'
            },
            {
                action: 'close',
                title: 'Cerrar'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(
            data.title || 'Portal de Noticias', 
            options
        )
    );
});

// Manejar clics en las notificaciones
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'close') {
        return;
    }
    
    const url = event.notification.data.url;
    
    event.waitUntil(
        clients.matchAll({
            type: 'window'
        })
        .then(windowClients => {
            // Buscar si ya hay una ventana abierta y enfocarla
            for (let client of windowClients) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            
            // Si no hay ventana abierta, abrir una nueva
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});