/**
 * Portal de Noticias - JavaScript para el Sidebar del Panel de Administración
 */

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar sidebar
    initSidebar();
});

/**
 * Inicializa la funcionalidad del sidebar
 */
function initSidebar() {
    // Toggle del sidebar (mostrar/ocultar en móviles)
    const sidebarToggler = document.querySelector('[data-widget="pushmenu"]');
    if (sidebarToggler) {
        sidebarToggler.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.toggle('sidebar-open');
        });
    }
    
    // Cerrar sidebar al hacer clic fuera (en móviles)
    document.addEventListener('click', function(e) {
        const sidebar = document.querySelector('.main-sidebar');
        const toggler = document.querySelector('[data-widget="pushmenu"]');
        
        if (window.innerWidth < 992 &&
            document.body.classList.contains('sidebar-open') &&
            !sidebar.contains(e.target) &&
            !toggler.contains(e.target)) {
            document.body.classList.remove('sidebar-open');
        }
    });
    
    // Toggle de submenús
    const menuItems = document.querySelectorAll('.nav-sidebar .nav-item');
    menuItems.forEach(function(item) {
        const link = item.querySelector('.nav-link');
        const treeview = item.querySelector('.nav-treeview');
        
        if (link && treeview) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Cerrar otros menús abiertos si es necesario
                if (!item.classList.contains('menu-open')) {
                    const openMenus = document.querySelectorAll('.nav-sidebar .menu-open');
                    openMenus.forEach(function(openMenu) {
                        // Solo cerrar menús del mismo nivel
                        if (openMenu.parentElement === item.parentElement) {
                            openMenu.classList.remove('menu-open');
                        }
                    });
                }
                
                // Toggle del menú actual
                item.classList.toggle('menu-open');
            });
        }
    });
    
    // Marcar el menú activo basado en la URL actual
    highlightActiveMenu();
}

/**
 * Marca el menú activo basado en la URL actual
 */
function highlightActiveMenu() {
    const currentPath = window.location.pathname;
    const menuLinks = document.querySelectorAll('.nav-sidebar .nav-link');
    
    menuLinks.forEach(function(link) {
        const href = link.getAttribute('href');
        
        if (href && currentPath.includes(href)) {
            // Marcar el enlace como activo
            link.classList.add('active');
            
            // Si está en un submenú, abrir el menú padre
            const parentItem = link.closest('.nav-item');
            if (parentItem && parentItem.parentElement.classList.contains('nav-treeview')) {
                parentItem.parentElement.parentElement.classList.add('menu-open');
            }
        }
    });
}

/**
 * Alterna el estado de la barra lateral entre normal y mini
 */
function toggleSidebarMini() {
    document.body.classList.toggle('sidebar-mini');
}