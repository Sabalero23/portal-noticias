/**
 * PWA Installation Manager
 * Maneja la instalación de la PWA y muestra banners de instalación
 */
class PWAInstaller {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.installButton = null;
        this.installBanner = null;
        this.dismissedTimestamp = localStorage.getItem('pwa-dismissed');
        
        this.init();
    }
    
    init() {
        // Verificar si ya está instalado
        this.checkIfInstalled();
        
        // Escuchar el evento beforeinstallprompt
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('[PWA] beforeinstallprompt event triggered');
            
            // Prevenir el prompt automático
            e.preventDefault();
            
            // Guardar el evento para usarlo después
            this.deferredPrompt = e;
            
            // Mostrar el banner de instalación personalizado
            this.showInstallBanner();
        });
        
        // Escuchar cuando se instala la PWA
        window.addEventListener('appinstalled', (e) => {
            console.log('[PWA] App was installed', e);
            this.hideInstallBanner();
            this.isInstalled = true;
            
            // Limpiar el prompt guardado
            this.deferredPrompt = null;
            
            // Mostrar mensaje de éxito
            this.showInstallSuccessMessage();
        });
        
        // Para Safari iOS - detectar si se agregó a pantalla de inicio
        if (window.navigator.standalone === true) {
            this.isInstalled = true;
            console.log('[PWA] Running in standalone mode (iOS)');
        }
        
        // Crear botón de instalación si no existe
        this.createInstallButton();
        
        // Mostrar banner si no fue rechazado recientemente
        this.showBannerIfAppropriate();
    }
    
    checkIfInstalled() {
        // Verificar si está corriendo como PWA
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
        const isIosStandalone = window.navigator.standalone === true;
        
        if (isStandalone || isIosStandalone) {
            this.isInstalled = true;
            console.log('[PWA] Already installed');
        }
    }
    
    createInstallButton() {
        // Verificar si ya existe un botón
        if (document.getElementById('pwa-install-btn')) return;
        
        // Crear botón flotante de instalación
        const button = document.createElement('button');
        button.id = 'pwa-install-btn';
        button.className = 'btn btn-primary pwa-install-btn d-none';
        button.innerHTML = '<i class="fas fa-download me-1"></i> Instalar App';
        button.title = 'Instalar como aplicación';
        
        button.addEventListener('click', () => {
            this.promptInstall();
        });
        
        // Agregar al body
        document.body.appendChild(button);
        this.installButton = button;
    }
    
    createInstallBanner() {
        // Verificar si ya existe
        if (document.getElementById('pwa-install-banner')) return;
        
        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.className = 'pwa-install-banner';
        
        banner.innerHTML = `
            <div class="pwa-banner-content">
                <div class="pwa-banner-left">
                    <img src="assets/img/icons/icon-72x72.png" alt="App Icon" class="pwa-banner-icon">
                    <div class="pwa-banner-text">
                        <div class="pwa-banner-title">Portal de Noticias</div>
                        <div class="pwa-banner-subtitle">Instala nuestra app para una mejor experiencia</div>
                    </div>
                </div>
                <div class="pwa-banner-right">
                    <button class="btn btn-sm btn-outline-primary me-2" id="pwa-install-banner-btn">
                        Instalar
                    </button>
                    <button class="btn btn-sm btn-light" id="pwa-dismiss-banner-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        
        // Eventos
        banner.querySelector('#pwa-install-banner-btn').addEventListener('click', () => {
            this.promptInstall();
        });
        
        banner.querySelector('#pwa-dismiss-banner-btn').addEventListener('click', () => {
            this.dismissBanner();
        });
        
        // Agregar al body
        document.body.appendChild(banner);
        this.installBanner = banner;
        
        return banner;
    }
    
    showInstallBanner() {
        if (this.isInstalled) return;
        
        // No mostrar si fue rechazado recientemente (7 días)
        if (this.dismissedTimestamp) {
            const dismissedDate = new Date(parseInt(this.dismissedTimestamp));
            const now = new Date();
            const daysDiff = (now - dismissedDate) / (1000 * 60 * 60 * 24);
            
            if (daysDiff < 7) {
                console.log('[PWA] Banner dismissed recently, not showing');
                return;
            }
        }
        
        // Crear banner si no existe
        if (!this.installBanner) {
            this.createInstallBanner();
        }
        
        // Mostrar banner con animación
        setTimeout(() => {
            if (this.installBanner) {
                this.installBanner.classList.add('pwa-banner-show');
            }
        }, 2000); // Mostrar después de 2 segundos
        
        // Mostrar botón flotante también
        if (this.installButton) {
            this.installButton.classList.remove('d-none');
        }
    }
    
    hideInstallBanner() {
        if (this.installBanner) {
            this.installBanner.classList.remove('pwa-banner-show');
            setTimeout(() => {
                if (this.installBanner) {
                    this.installBanner.remove();
                    this.installBanner = null;
                }
            }, 300);
        }
        
        if (this.installButton) {
            this.installButton.classList.add('d-none');
        }
    }
    
    dismissBanner() {
        // Guardar timestamp de rechazo
        localStorage.setItem('pwa-dismissed', Date.now().toString());
        
        // Ocultar banner
        this.hideInstallBanner();
    }
    
    showBannerIfAppropriate() {
        // Solo mostrar en móviles
        if (!this.isMobile()) return;
        
        // No mostrar si ya está instalado
        if (this.isInstalled) return;
        
        // Mostrar después de 30 segundos si hay prompt disponible
        setTimeout(() => {
            if (this.deferredPrompt) {
                this.showInstallBanner();
            }
        }, 30000);
    }
    
    async promptInstall() {
        if (!this.deferredPrompt) {
            console.log('[PWA] No deferred prompt available');
            
            // Para Safari iOS, mostrar instrucciones manuales
            if (this.isIOS()) {
                this.showIOSInstructions();
            } else {
                this.showManualInstructions();
            }
            return;
        }
        
        // Mostrar el prompt de instalación
        this.deferredPrompt.prompt();
        
        // Esperar a que el usuario responda
        const choiceResult = await this.deferredPrompt.userChoice;
        
        console.log('[PWA] User choice:', choiceResult.outcome);
        
        if (choiceResult.outcome === 'accepted') {
            console.log('[PWA] User accepted the install prompt');
        } else {
            console.log('[PWA] User dismissed the install prompt');
            this.dismissBanner();
        }
        
        // Limpiar el prompt
        this.deferredPrompt = null;
    }
    
    showInstallSuccessMessage() {
        // Crear mensaje de éxito
        const successDiv = document.createElement('div');
        successDiv.className = 'alert alert-success alert-dismissible pwa-success-message';
        successDiv.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>
            ¡App instalada correctamente! Ahora puedes acceder desde tu pantalla de inicio.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Agregar al body
        document.body.appendChild(successDiv);
        
        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (successDiv) {
                successDiv.remove();
            }
        }, 5000);
    }
    
    showIOSInstructions() {
        const modal = this.createInstructionModal(
            'Instalar en iOS',
            `
            <div class="text-center">
                <p>Para instalar esta app en tu iPhone o iPad:</p>
                <ol class="text-start">
                    <li>Presiona el botón <i class="fas fa-share"></i> en la barra inferior</li>
                    <li>Selecciona "Añadir a pantalla de inicio"</li>
                    <li>Confirma presionando "Añadir"</li>
                </ol>
                <img src="assets/img/ios-install-instructions.png" alt="Instrucciones iOS" class="img-fluid" style="max-height: 300px;">
            </div>
            `
        );
        
        this.showModal(modal);
    }
    
    showManualInstructions() {
        let instructions = '';
        
        if (this.isAndroid()) {
            instructions = `
                <p>Para instalar esta app en Android:</p>
                <ol>
                    <li>Abre el menú de Chrome (⋮)</li>
                    <li>Selecciona "Instalar app" o "Añadir a pantalla de inicio"</li>
                    <li>Confirma la instalación</li>
                </ol>
            `;
        } else {
            instructions = `
                <p>Para instalar esta app:</p>
                <ul>
                    <li><strong>Chrome/Edge:</strong> Busca el ícono de instalación en la barra de direcciones</li>
                    <li><strong>Firefox:</strong> Ve a Configuración → Instalar</li>
                    <li><strong>Safari:</strong> Ve a Archivo → Instalar</li>
                </ul>
            `;
        }
        
        const modal = this.createInstructionModal('Instalar App', instructions);
        this.showModal(modal);
    }
    
    createInstructionModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        `;
        
        return modal;
    }
    
    showModal(modal) {
        document.body.appendChild(modal);
        const bootstrapModal = new bootstrap.Modal(modal);
        
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
        
        bootstrapModal.show();
    }
    
    isMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }
    
    isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent);
    }
    
    isAndroid() {
        return /Android/i.test(navigator.userAgent);
    }
    
    // Método público para forzar mostrar el banner
    forceShowBanner() {
        localStorage.removeItem('pwa-dismissed');
        this.showInstallBanner();
    }
    
    // Método público para ocultar todo
    hideAll() {
        this.hideInstallBanner();
        localStorage.setItem('pwa-dismissed', Date.now().toString());
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    // Solo inicializar si la PWA está habilitada
    if (document.querySelector('link[rel="manifest"]')) {
        window.pwaInstaller = new PWAInstaller();
        console.log('[PWA] PWA Installer initialized');
    }
});

// Para acceso desde consola (debug)
window.PWAInstaller = PWAInstaller;