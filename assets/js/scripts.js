/**
 * Portal de Noticias - Scripts principales
 * Archivo limpio sin funciones duplicadas de clima
 */

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    // Hacer sticky los sidebars en pantallas grandes
    if (window.innerWidth >= 992) {
        const leftSidebar = document.querySelector('.order-md-1');
        const rightSidebar = document.querySelector('.order-md-3');
        
        if (leftSidebar) {
            leftSidebar.classList.add('sticky-sidebar');
        }
        
        if (rightSidebar) {
            rightSidebar.classList.add('sticky-sidebar');
        }
    }
    
    // Inicializar lazy loading
    initLazyLoading();
    
    // Inicializar animaciones al scroll
    initScrollAnimations();
    
    // Inicializar sistema de anuncios
    initAdsTracking();
    
    // Inicializar botón volver arriba
    initBackToTop();
    
    // Inicializar tooltips de Bootstrap si existen
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Inicializar modo oscuro si está disponible
    initDarkMode();
});

/**
 * Lazy loading de imágenes
 */
function initLazyLoading() {
    if ('loading' in HTMLImageElement.prototype) {
        // El navegador soporta lazy loading nativo
        const images = document.querySelectorAll('img:not(.no-lazy)');
        images.forEach(img => {
            if (!img.hasAttribute('loading')) {
                img.setAttribute('loading', 'lazy');
            }
        });
    } else {
        // Fallback para navegadores que no soportan lazy loading nativo
        const lazyImages = document.querySelectorAll('img[data-src], .lazy');
        
        if (lazyImages.length > 0 && 'IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        
                        if (img.dataset.srcset) {
                            img.srcset = img.dataset.srcset;
                            img.removeAttribute('data-srcset');
                        }
                        
                        img.classList.remove('lazy');
                        img.addEventListener('load', () => {
                            img.classList.add('loaded');
                        });
                        
                        imageObserver.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });
            
            lazyImages.forEach(img => {
                imageObserver.observe(img);
            });
        }
    }
}

/**
 * Animaciones al hacer scroll
 */
function initScrollAnimations() {
    const animatedElements = document.querySelectorAll('.animate-on-scroll');
    
    if (animatedElements.length > 0 && 'IntersectionObserver' in window) {
        const animationObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        animatedElements.forEach(element => {
            animationObserver.observe(element);
        });
    } else {
        // Fallback: simplemente mostrar los elementos
        animatedElements.forEach(element => {
            element.classList.add('animated');
        });
    }
}

/**
 * Sistema de seguimiento de anuncios
 */
function initAdsTracking() {
    const adLinks = document.querySelectorAll('.ad-link');
    
    adLinks.forEach(link => {
        link.addEventListener('click', function(event) {
            const adId = this.getAttribute('data-ad-id');
            
            if (adId) {
                // Registrar clic de forma asíncrona
                fetch('ad_click.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ad_id=' + encodeURIComponent(adId)
                }).catch(error => {
                    console.error('Error al registrar clic en anuncio:', error);
                });
                
                // Pequeño delay para permitir que se registre el clic
                event.preventDefault();
                setTimeout(() => {
                    window.open(this.href, '_blank');
                }, 100);
            }
        });
    });
    
    // Registro de impresiones automático (usando Intersection Observer)
    if ('IntersectionObserver' in window) {
        const ads = document.querySelectorAll('.ad-container[data-ad-id]');
        
        const adObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const adId = entry.target.getAttribute('data-ad-id');
                    if (adId) {
                        // Registrar impresión
                        fetch('ad_impression.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'ad_id=' + encodeURIComponent(adId)
                        }).catch(error => {
                            console.error('Error al registrar impresión:', error);
                        });
                        
                        // Dejar de observar este anuncio
                        adObserver.unobserve(entry.target);
                    }
                }
            });
        }, {
            threshold: 0.5 // Se considera "visto" cuando está 50% visible
        });
        
        ads.forEach(ad => {
            adObserver.observe(ad);
        });
    }
}

/**
 * Botón volver arriba
 */
function initBackToTop() {
    const backToTopButton = document.getElementById('back-to-top');
    
    if (backToTopButton) {
        // Mostrar/ocultar botón basado en scroll
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 100) {
                backToTopButton.style.display = 'block';
                backToTopButton.style.opacity = '1';
            } else {
                backToTopButton.style.opacity = '0';
                setTimeout(() => {
                    if (window.pageYOffset <= 100) {
                        backToTopButton.style.display = 'none';
                    }
                }, 300);
            }
        });
        
        // Funcionalidad del botón
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
}

/**
 * Modo oscuro (si está implementado)
 */
function initDarkMode() {
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    
    if (darkModeToggle) {
        // Verificar preferencia guardada
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        // Actualizar estado del toggle
        updateDarkModeToggle(savedTheme === 'dark');
        
        // Event listener para el toggle
        darkModeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateDarkModeToggle(newTheme === 'dark');
        });
    }
}

/**
 * Actualizar estado visual del toggle de modo oscuro
 */
function updateDarkModeToggle(isDark) {
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    
    if (darkModeToggle) {
        const icon = darkModeToggle.querySelector('i');
        if (icon) {
            icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        }
        
        darkModeToggle.setAttribute('title', isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
    }
}

/**
 * Funciones auxiliares para formularios
 */

// Validación básica de email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Mostrar mensaje de carga en botón
function showButtonLoading(button, originalText = null) {
    if (!originalText) {
        originalText = button.textContent;
    }
    
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Cargando...';
    button.setAttribute('data-original-text', originalText);
}

// Restaurar estado original del botón
function hideButtonLoading(button) {
    const originalText = button.getAttribute('data-original-text');
    
    button.disabled = false;
    button.textContent = originalText || 'Enviar';
    button.removeAttribute('data-original-text');
}

// Mostrar notificación toast
function showToast(message, type = 'info', duration = 5000) {
    // Crear contenedor de toasts si no existe
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '1055';
        document.body.appendChild(toastContainer);
    }
    
    // Crear toast
    const toastId = 'toast-' + Date.now();
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    // Mostrar toast
    const toastElement = document.getElementById(toastId);
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const toast = new bootstrap.Toast(toastElement, {
            delay: duration
        });
        toast.show();
        
        // Eliminar del DOM después de ocultar
        toastElement.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    } else {
        // Fallback si Bootstrap no está disponible
        toastElement.style.display = 'block';
        setTimeout(() => {
            toastElement.remove();
        }, duration);
    }
}

/**
 * Función para manejar errores de JavaScript de forma global
 */
window.addEventListener('error', function(event) {
    console.error('Error JavaScript:', {
        message: event.error?.message || event.message,
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
        stack: event.error?.stack
    });
    
    // En desarrollo, mostrar errores al usuario
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        showToast(`Error JS: ${event.error?.message || event.message}`, 'danger', 10000);
    }
});

/**
 * Función para manejar promesas rechazadas
 */
window.addEventListener('unhandledrejection', function(event) {
    console.error('Promesa rechazada no manejada:', event.reason);
    
    // En desarrollo, mostrar errores al usuario
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        showToast(`Error de promesa: ${event.reason}`, 'danger', 10000);
    }
});

/**
 * Funciones para el servicio worker
 */
function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/service-worker.js', {
                scope: '/'
            })
            .then(function(registration) {
                console.log('Service Worker registrado exitosamente:', registration.scope);
                
                // Verificar si hay una actualización disponible
                registration.addEventListener('updatefound', function() {
                    const newWorker = registration.installing;
                    
                    newWorker.addEventListener('statechange', function() {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // Nueva versión disponible
                            showUpdateAvailable();
                        }
                    });
                });
            })
            .catch(function(error) {
                console.error('Error al registrar Service Worker:', error);
            });
        });
    }
}

/**
 * Mostrar notificación de actualización disponible
 */
function showUpdateAvailable() {
    const message = 'Nueva versión disponible. ¿Deseas actualizar?';
    
    if (confirm(message)) {
        // Indicar al service worker que omita la espera
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ action: 'skipWaiting' });
        }
        
        // Recargar la página
        window.location.reload();
    }
}

// Auto-inicializar Service Worker
registerServiceWorker();

// Hacer disponibles las funciones globalmente
window.showToast = showToast;
window.showButtonLoading = showButtonLoading;
window.hideButtonLoading = hideButtonLoading;
window.isValidEmail = isValidEmail;