/**
 * Biblioteca de Medios - Scripts corregidos
 * 
 * Este script maneja la funcionalidad de la biblioteca de medios,
 * incluyendo vista de cuadrícula/lista, previsualización y eliminación.
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Media library script loaded');
    
    // Cambio de modo de visualización (grid/list)
    const viewModeButtons = document.querySelectorAll('.view-mode');
    const gridView = document.getElementById('media-view-grid');
    const listView = document.getElementById('media-view-list');
    
    if (viewModeButtons.length && gridView && listView) {
        console.log('View mode components found');
        
        viewModeButtons.forEach(button => {
            button.addEventListener('click', function() {
                console.log('View mode button clicked: ' + this.getAttribute('data-mode'));
                
                // Desactivar todos los botones
                viewModeButtons.forEach(btn => btn.classList.remove('active'));
                
                // Activar el botón actual
                this.classList.add('active');
                
                // Mostrar vista correspondiente
                const mode = this.getAttribute('data-mode');
                if (mode === 'grid') {
                    gridView.style.display = '';
                    listView.style.display = 'none';
                } else {
                    gridView.style.display = 'none';
                    listView.style.display = '';
                }
            });
        });
    } else {
        console.warn('Some view mode components not found');
    }
    
    // Botones de previsualización - IMPORTANTE: Usar delegación de eventos
    const previewModalElement = document.getElementById('mediaPreviewModal');
    
    if (previewModalElement) {
        console.log('Preview modal found');
        
        // Inicializar modal (comprobar si Bootstrap está disponible)
        let previewModal;
        if (typeof bootstrap !== 'undefined') {
            previewModal = new bootstrap.Modal(previewModalElement);
        }
        
        // Usar delegación de eventos para los botones de previsualización
        document.addEventListener('click', function(e) {
            // Verificar si el elemento clickeado o alguno de sus padres tiene la clase 'media-preview-btn'
            const button = e.target.closest('.media-preview-btn');
            
            if (button) {
                console.log('Preview button clicked through delegation');
                
                const path = button.getAttribute('data-path');
                const name = button.getAttribute('data-name');
                const type = button.getAttribute('data-type');
                const size = button.getAttribute('data-size');
                const date = button.getAttribute('data-date');
                const user = button.getAttribute('data-user');
                
                console.log('Preview data:', { path, name, type });
                
                // Actualizar detalles
                const fileNameElement = document.querySelector('.file-name');
                const fileTypeElement = document.querySelector('.file-type');
                const fileSizeElement = document.querySelector('.file-size');
                const fileDateElement = document.querySelector('.file-date');
                const fileUserElement = document.querySelector('.file-user');
                
                if (fileNameElement) fileNameElement.textContent = name;
                if (fileTypeElement) fileTypeElement.textContent = type;
                if (fileSizeElement) fileSizeElement.textContent = size;
                if (fileDateElement) fileDateElement.textContent = date;
                if (fileUserElement) fileUserElement.textContent = user;
                
                // Actualizar contenedor de previsualización
                const previewContainer = document.querySelector('.preview-container');
                if (previewContainer) {
                    previewContainer.innerHTML = '';
                    
                    if (type && type.startsWith('image/')) {
                        // Previsualización de imagen
                        const img = document.createElement('img');
                        img.src = path;
                        img.alt = name;
                        img.classList.add('img-fluid');
                        previewContainer.appendChild(img);
                    } else if (type && type.startsWith('video/')) {
                        // Previsualización de video
                        const video = document.createElement('video');
                        video.src = path;
                        video.controls = true;
                        video.classList.add('img-fluid');
                        previewContainer.appendChild(video);
                    } else if (type && type.startsWith('audio/')) {
                        // Previsualización de audio
                        const audio = document.createElement('audio');
                        audio.src = path;
                        audio.controls = true;
                        audio.classList.add('w-100');
                        previewContainer.appendChild(audio);
                    } else {
                        // Icono para otros tipos
                        const icon = document.createElement('div');
                        icon.className = 'media-icon';
                        
                        let iconClass = 'fas fa-file';
                        if (type === 'application/pdf') {
                            iconClass = 'fas fa-file-pdf';
                        } else if (type === 'application/msword' || type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                            iconClass = 'fas fa-file-word';
                        } else if (type === 'application/vnd.ms-excel' || type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                            iconClass = 'fas fa-file-excel';
                        } else if (type === 'application/zip' || type === 'application/x-rar-compressed') {
                            iconClass = 'fas fa-file-archive';
                        } else if (type === 'text/plain' || type === 'text/csv') {
                            iconClass = 'fas fa-file-alt';
                        }
                        
                        icon.innerHTML = `<i class="${iconClass}"></i>`;
                        previewContainer.appendChild(icon);
                        
                        // Mensaje de no previsualización
                        const message = document.createElement('p');
                        message.className = 'mt-3 text-muted';
                        message.textContent = 'La previsualización no está disponible para este tipo de archivo. Puedes descargarlo para verlo.';
                        previewContainer.appendChild(message);
                    }
                }
                
                // Actualizar enlaces
                const downloadLink = document.querySelector('.download-link');
                if (downloadLink) downloadLink.href = path;
                
                // Mostrar modal
                if (previewModal) {
                    previewModal.show();
                } else {
                    // Fallback si bootstrap no está disponible
                    previewModalElement.style.display = 'block';
                }
            }
        });
    } else {
        console.warn('Preview modal not found');
    }
    
    // Botón para copiar ruta en el modal usando delegación
    document.addEventListener('click', function(e) {
        const button = e.target.closest('.copy-path');
        if (button) {
            console.log('Copy path button clicked');
            
            const downloadLink = document.querySelector('.download-link');
            if (downloadLink) {
                const url = downloadLink.getAttribute('href');
                copyToClipboard(url);
                alert('URL copiada al portapapeles');
            }
        }
    });
    
    // Botones para copiar URL en la vista de cuadrícula/lista usando delegación
    document.addEventListener('click', function(e) {
        const button = e.target.closest('.copy-url');
        if (button) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Copy URL button clicked');
            
            const url = button.getAttribute('data-url');
            if (url) {
                copyToClipboard(url);
                alert('URL copiada al portapapeles');
            } else {
                console.warn('No URL to copy');
            }
        }
    });
    
    // Confirmar eliminación usando delegación
    const deleteModalElement = document.getElementById('deleteConfirmModal');
    let deleteModal;
    
    if (deleteModalElement && typeof bootstrap !== 'undefined') {
        deleteModal = new bootstrap.Modal(deleteModalElement);
    }
    
    document.addEventListener('click', function(e) {
        const button = e.target.closest('.btn-delete');
        if (button) {
            e.preventDefault();
            console.log('Delete button clicked');
            
            const fileId = button.getAttribute('data-id');
            const fileName = button.getAttribute('data-name');
            const csrfToken = button.getAttribute('data-csrf');
            
            // Si tenemos modal de confirmación, úsalo
            if (deleteModal) {
                const deleteFileNameElement = document.getElementById('deleteFileName');
                const confirmDeleteBtnElement = document.getElementById('confirmDeleteBtn');
                
                if (deleteFileNameElement) {
                    deleteFileNameElement.textContent = fileName;
                }
                
                if (confirmDeleteBtnElement) {
                    confirmDeleteBtnElement.href = 'delete.php?id=' + fileId + '&csrf_token=' + csrfToken;
                }
                
                deleteModal.show();
            } else {
                // Fallback al confirm nativo
                if (confirm('¿Estás seguro de que deseas eliminar el archivo "' + fileName + '"? Esta acción no se puede deshacer.')) {
                    window.location.href = 'delete.php?id=' + fileId + '&csrf_token=' + csrfToken;
                }
            }
        }
    });
    
    // Función para copiar al portapapeles
    function copyToClipboard(text) {
        console.log('Copying to clipboard:', text);
        
        // Método moderno (navigator.clipboard)
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text)
                .then(() => console.log('Text copied to clipboard'))
                .catch(err => {
                    console.error('Error copying text: ', err);
                    fallbackCopyToClipboard(text);
                });
        } else {
            fallbackCopyToClipboard(text);
        }
    }
    
    // Método alternativo para copiar al portapapeles
    function fallbackCopyToClipboard(text) {
        console.log('Using fallback clipboard method');
        
        // Crear elemento temporal
        const el = document.createElement('textarea');
        el.value = text;
        el.setAttribute('readonly', '');
        el.style.position = 'absolute';
        el.style.left = '-9999px';
        document.body.appendChild(el);
        
        // Seleccionar y copiar
        el.select();
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                console.log('Fallback: Copying was successful');
            } else {
                console.error('Fallback: Copying failed');
            }
        } catch (err) {
            console.error('Fallback: Copying failed with error', err);
        }
        
        // Eliminar elemento
        document.body.removeChild(el);
    }
    
    // Inicializar la vista correcta (grid o list)
    if (viewModeButtons.length && gridView && listView) {
        const activeButton = document.querySelector('.view-mode.active');
        if (activeButton) {
            const mode = activeButton.getAttribute('data-mode');
            console.log('Initial view mode:', mode);
            
            if (mode === 'grid') {
                gridView.style.display = '';
                listView.style.display = 'none';
            } else {
                gridView.style.display = 'none';
                listView.style.display = '';
            }
        }
    }
    
    // Formatear tipos de archivo para que se muestren correctamente
    function formatFileType(fileType) {
        if (fileType.startsWith('image/')) {
            return 'Imagen ' + fileType.split('/')[1].toUpperCase();
        } else if (fileType.startsWith('video/')) {
            return 'Video ' + fileType.split('/')[1].toUpperCase();
        } else if (fileType.startsWith('audio/')) {
            return 'Audio ' + fileType.split('/')[1].toUpperCase();
        } else if (fileType === 'application/pdf') {
            return 'PDF';
        } else if (fileType === 'application/msword' || fileType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return 'Documento Word';
        } else if (fileType === 'application/vnd.ms-excel' || fileType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            return 'Hoja de cálculo Excel';
        } else if (fileType === 'application/zip') {
            return 'Archivo ZIP';
        } else if (fileType === 'application/x-rar-compressed') {
            return 'Archivo RAR';
        } else if (fileType === 'text/plain') {
            return 'Archivo de texto';
        } else if (fileType === 'text/csv') {
            return 'Archivo CSV';
        } else {
            return fileType;
        }
    }
    
    // Exponer la función formatFileType globalmente para que pueda ser usada en PHP
    window.formatFileType = formatFileType;
});