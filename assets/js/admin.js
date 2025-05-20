/**
 * Portal de Noticias - JavaScript para el Panel de Administración
 */

document.addEventListener('DOMContentLoaded', function() {
    // Funcionalidad del menú lateral
    initSidebar();
    
    // Inicializar tooltips
    initTooltips();
    
    // Previsualización de imágenes
    initImagePreview();
    
    // Validación de formularios
    initFormValidation();
    
    // Cerrar alertas automáticamente
    initAutoCloseAlerts();
    
    // Selector de fecha y hora
    initDateTimePickers();
    
    // Inicializar selectores con búsqueda
    initSelect2();
    
    // Inicializar editores TinyMCE (si existen)
    if (typeof tinymce !== 'undefined') {
        initTinyMCE();
    }
});

/**
 * Inicializa la funcionalidad del menú lateral
 */
function initSidebar() {
    // Toggle del menú lateral en móviles
    const sidebarToggler = document.querySelector('[data-widget="pushmenu"]');
    if (sidebarToggler) {
        sidebarToggler.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.toggle('sidebar-open');
            document.body.classList.toggle('sidebar-expanded');
        });
    }
    
    // Cerrar menú cuando se hace clic fuera en móviles
    document.addEventListener('click', function(e) {
        if (document.body.classList.contains('sidebar-open') && 
            !e.target.closest('.main-sidebar') && 
            !e.target.closest('[data-widget="pushmenu"]')) {
            document.body.classList.remove('sidebar-open');
        }
    });
    
    // Expandir/contraer submenús
    const menuItems = document.querySelectorAll('.nav-sidebar .nav-item');
    menuItems.forEach(function(item) {
        const link = item.querySelector('.nav-link');
        const treeview = item.querySelector('.nav-treeview');
        
        if (link && treeview) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                item.classList.toggle('menu-open');
            });
        }
    });
}

/**
 * Inicializa los tooltips de Bootstrap
 */
function initTooltips() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
}

/**
 * Inicializa la previsualización de imágenes en formularios
 */
function initImagePreview() {
    const imageInputs = document.querySelectorAll('input[type="file"][data-preview]');
    
    imageInputs.forEach(function(input) {
        const previewId = input.getAttribute('data-preview');
        const preview = document.getElementById(previewId);
        
        if (preview) {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                } else {
                    preview.src = '';
                    preview.style.display = 'none';
                }
            });
        }
    });
}

/**
 * Inicializa la validación de formularios
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Cierra las alertas automáticamente después de un tiempo
 */
function initAutoCloseAlerts() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const closeButton = alert.querySelector('.btn-close');
            if (closeButton) {
                closeButton.click();
            }
        }, 5000);
    });
}

/**
 * Inicializa los selectores de fecha y hora
 */
function initDateTimePickers() {
    // Si flatpickr está disponible
    if (typeof flatpickr !== 'undefined') {
        // Selectores de fecha
        flatpickr('.datepicker', {
            dateFormat: 'Y-m-d',
            locale: 'es',
            allowInput: true
        });
        
        // Selectores de hora
        flatpickr('.timepicker', {
            enableTime: true,
            noCalendar: true,
            dateFormat: 'H:i',
            time_24hr: true,
            locale: 'es',
            allowInput: true
        });
        
        // Selectores de fecha y hora
        flatpickr('.datetimepicker', {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            time_24hr: true,
            locale: 'es',
            allowInput: true
        });
    }
}

/**
 * Inicializa los selectores con búsqueda
 */
function initSelect2() {
    // Si select2 está disponible
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    }
}

/**
 * Inicializa los editores TinyMCE
 */
function initTinyMCE() {
    // Comprueba si hay editores simples (sin configuración avanzada)
    const simpleEditors = document.querySelectorAll('textarea.editor:not(.editor-advanced)');
    
    if (simpleEditors.length > 0) {
        tinymce.init({
            selector: 'textarea.editor:not(.editor-advanced)',
            height: 300,
            menubar: false,
            plugins: [
                'advlist autolink lists link image charmap preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste code help wordcount'
            ],
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link image | help',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }',
            branding: false,
            relative_urls: false,
            remove_script_host: false,
            convert_urls: true
        });
    }
    
    // Comprueba si hay editores avanzados
    const advancedEditors = document.querySelectorAll('textarea.editor-advanced');
    
    if (advancedEditors.length > 0) {
        tinymce.init({
            selector: 'textarea.editor-advanced',
            height: 500,
            menubar: true,
            plugins: [
                'advlist autolink lists link image charmap preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste code help wordcount'
            ],
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link image media | code table | help',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }',
            branding: false,
            relative_urls: false,
            remove_script_host: false,
            convert_urls: true,
            file_picker_callback: function(callback, value, meta) {
                // Abrir selector de medios del portal
                openMediaSelector(callback, value, meta);
            }
        });
    }
}

/**
 * Abre el selector de medios para TinyMCE
 */
function openMediaSelector(callback, value, meta) {
    // Crear un modal para seleccionar archivos
    let modal = document.getElementById('media-selector-modal');
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'media-selector-modal';
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-hidden', 'true');
        
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Seleccionar archivo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="nav nav-tabs">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#media-library-tab">Biblioteca</button>
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#media-upload-tab">Subir archivo</button>
                        </div>
                        <div class="tab-content mt-3">
                            <div class="tab-pane fade show active" id="media-library-tab">
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="media-search" placeholder="Buscar archivos...">
                                    <button class="btn btn-outline-secondary" type="button"><i class="fas fa-search"></i></button>
                                </div>
                                <div class="image-selector" id="media-files-container"></div>
                                <div class="loader-container" id="media-loader">
                                    <div class="loader"></div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="media-upload-tab">
                                <form id="media-upload-form">
                                    <div class="mb-3">
                                        <label for="media-file" class="form-label">Seleccionar archivo</label>
                                        <input type="file" class="form-control" id="media-file" name="file">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Subir</button>
                                </form>
                                <div class="alert alert-success mt-3" id="media-upload-success" style="display: none;"></div>
                                <div class="alert alert-danger mt-3" id="media-upload-error" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="media-select-btn" disabled>Seleccionar</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Inicializar modal
        const modalEl = new bootstrap.Modal(modal);
        
        // Cargar archivos
        loadMediaFiles();
        
        // Evento de búsqueda
        const searchInput = document.getElementById('media-search');
        searchInput.addEventListener('input', debounce(function() {
            loadMediaFiles(this.value);
        }, 300));
        
        // Evento de selección
        const filesContainer = document.getElementById('media-files-container');
        filesContainer.addEventListener('click', function(e) {
            const item = e.target.closest('.image-item');
            if (item) {
                // Quitar selección anterior
                const selected = filesContainer.querySelector('.selected');
                if (selected) {
                    selected.classList.remove('selected');
                }
                
                // Marcar como seleccionado
                item.classList.add('selected');
                
                // Habilitar botón de seleccionar
                document.getElementById('media-select-btn').disabled = false;
            }
        });
        
        // Evento de subida
        const uploadForm = document.getElementById('media-upload-form');
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Mostrar loader
            uploadForm.querySelector('button').innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Subiendo...';
            uploadForm.querySelector('button').disabled = true;
            
            // Subir archivo
            fetch('media/upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                uploadForm.querySelector('button').innerHTML = 'Subir';
                uploadForm.querySelector('button').disabled = false;
                
                if (data.success) {
                    // Mostrar mensaje de éxito
                    const successEl = document.getElementById('media-upload-success');
                    successEl.textContent = data.message;
                    successEl.style.display = 'block';
                    
                    // Ocultar mensaje después de 3 segundos
                    setTimeout(() => {
                        successEl.style.display = 'none';
                    }, 3000);
                    
                    // Recargar archivos
                    loadMediaFiles();
                    
                    // Cambiar a la pestaña de biblioteca
                    document.querySelector('[data-bs-target="#media-library-tab"]').click();
                } else {
                    // Mostrar mensaje de error
                    const errorEl = document.getElementById('media-upload-error');
                    errorEl.textContent = data.message;
                    errorEl.style.display = 'block';
                    
                    // Ocultar mensaje después de 3 segundos
                    setTimeout(() => {
                        errorEl.style.display = 'none';
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                uploadForm.querySelector('button').innerHTML = 'Subir';
                uploadForm.querySelector('button').disabled = false;
                
                // Mostrar mensaje de error
                const errorEl = document.getElementById('media-upload-error');
                errorEl.textContent = 'Error al subir el archivo. Intenta nuevamente.';
                errorEl.style.display = 'block';
                
                // Ocultar mensaje después de 3 segundos
                setTimeout(() => {
                    errorEl.style.display = 'none';
                }, 3000);
            });
        });
        
        // Evento de selección
        document.getElementById('media-select-btn').addEventListener('click', function() {
            const selected = filesContainer.querySelector('.selected');
            if (selected) {
                const fileUrl = selected.getAttribute('data-url');
                const fileType = selected.getAttribute('data-type');
                
                // Llamar al callback con la URL seleccionada
                callback(fileUrl, { title: selected.getAttribute('data-name') });
                
                // Cerrar modal
                modalEl.hide();
            }
        });
    }
    
    // Abrir modal
    const modalEl = bootstrap.Modal.getInstance(modal);
    modalEl.show();
}

/**
 * Carga los archivos multimedia de la biblioteca
 */
function loadMediaFiles(search = '') {
    const container = document.getElementById('media-files-container');
    const loader = document.getElementById('media-loader');
    
    // Mostrar loader
    container.style.display = 'none';
    loader.style.display = 'flex';
    
    // Cargar archivos
    fetch('media/get_files.php' + (search ? '?q=' + encodeURIComponent(search) : ''))
        .then(response => response.json())
        .then(data => {
            // Ocultar loader
            loader.style.display = 'none';
            container.style.display = 'flex';
            
            // Limpiar contenedor
            container.innerHTML = '';
            
            if (data.files && data.files.length > 0) {
                // Mostrar archivos
                data.files.forEach(file => {
                    const item = document.createElement('div');
                    item.className = 'image-item';
                    item.setAttribute('data-url', file.url);
                    item.setAttribute('data-name', file.name);
                    item.setAttribute('data-type', file.type);
                    
                    if (file.type.startsWith('image/')) {
                        item.innerHTML = `
                            <img src="${file.thumbnail || file.url}" alt="${file.name}">
                            <div class="image-name">${file.name}</div>
                        `;
                    } else {
                        // Icono para archivos no imagen
                        const iconClass = getFileIconClass(file.type);
                        item.innerHTML = `
                            <div class="file-icon">
                                <i class="${iconClass} fa-3x"></i>
                            </div>
                            <div class="image-name">${file.name}</div>
                        `;
                    }
                    
                    container.appendChild(item);
                });
            } else {
                // Mostrar mensaje de no hay archivos
                container.innerHTML = '<div class="text-center w-100">No se encontraron archivos</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Ocultar loader
            loader.style.display = 'none';
            container.style.display = 'flex';
            
            // Mostrar mensaje de error
            container.innerHTML = '<div class="text-center w-100 text-danger">Error al cargar archivos</div>';
        });
}

/**
 * Obtiene la clase de icono según el tipo de archivo
 */
function getFileIconClass(fileType) {
    if (fileType.startsWith('image/')) {
        return 'fas fa-file-image';
    } else if (fileType === 'application/pdf') {
        return 'fas fa-file-pdf';
    } else if (fileType === 'application/msword' || fileType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
        return 'fas fa-file-word';
    } else if (fileType === 'application/vnd.ms-excel' || fileType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
        return 'fas fa-file-excel';
    } else if (fileType === 'application/vnd.ms-powerpoint' || fileType === 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
        return 'fas fa-file-powerpoint';
    } else if (fileType.startsWith('text/')) {
        return 'fas fa-file-alt';
    } else if (fileType.startsWith('audio/')) {
        return 'fas fa-file-audio';
    } else if (fileType.startsWith('video/')) {
        return 'fas fa-file-video';
    } else if (fileType === 'application/zip' || fileType === 'application/x-rar-compressed') {
        return 'fas fa-file-archive';
    } else if (fileType === 'application/json' || fileType === 'application/xml') {
        return 'fas fa-file-code';
    } else {
        return 'fas fa-file';
    }
}

/**
 * Función para limitar la frecuencia de llamadas a una función
 */
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
}

/**
 * Selector de archivo para TinyMCE
 */
function tinymceFileBrowser(callback, value, meta) {
    // Abre el selector de medios
    openMediaSelector(callback, value, meta);
}

/**
 * Función para confirmar eliminación
 */
function confirmDelete(message = '¿Estás seguro de que deseas eliminar este elemento? Esta acción no se puede deshacer.') {
    return confirm(message);
}

/**
 * Genera un slug a partir de un texto
 */
function generateSlug(text) {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^\w\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
}

/**
 * Actualiza un campo con un slug generado
 */
function updateSlug(sourceId, targetId) {
    const sourceEl = document.getElementById(sourceId);
    const targetEl = document.getElementById(targetId);
    
    if (sourceEl && targetEl) {
        sourceEl.addEventListener('input', function() {
            // Solo actualizar si el campo de slug está vacío o si tiene la bandera de auto-slug
            if (targetEl.value === '' || targetEl.getAttribute('data-auto-slug') === 'true') {
                targetEl.value = generateSlug(this.value);
                targetEl.setAttribute('data-auto-slug', 'true');
            }
        });
        
        // Cuando el usuario edita manualmente el slug, quitar la bandera
        targetEl.addEventListener('input', function() {
            this.setAttribute('data-auto-slug', 'false');
        });
    }
}

/**
 * Inicializa la vista previa en tiempo real
 */
function initLivePreview(sourceId, targetId) {
    const sourceEl = document.getElementById(sourceId);
    const targetEl = document.getElementById(targetId);
    
    if (sourceEl && targetEl) {
        // Función para actualizar la vista previa
        const updatePreview = function() {
            targetEl.innerHTML = sourceEl.value;
        };
        
        // Actualizar vista previa al inicio
        updatePreview();
        
        // Actualizar vista previa al escribir
        sourceEl.addEventListener('input', updatePreview);
        
        // Si es un editor TinyMCE
        if (typeof tinymce !== 'undefined') {
            // Esperar a que se inicialice TinyMCE
            const interval = setInterval(function() {
                const editor = tinymce.get(sourceId);
                
                if (editor) {
                    clearInterval(interval);
                    
                    // Evento de cambio en el editor
                    editor.on('change', function() {
                        targetEl.innerHTML = editor.getContent();
                    });
                }
            }, 500);
        }
    }
}

/**
 * Toggle para campos de contraseña
 */
function initPasswordToggle() {
    const togglers = document.querySelectorAll('.password-toggle');
    
    togglers.forEach(function(toggler) {
        toggler.addEventListener('click', function() {
            const input = document.getElementById(this.getAttribute('data-target'));
            
            if (input) {
                // Cambiar tipo de input
                input.type = input.type === 'password' ? 'text' : 'password';
                
                // Cambiar icono
                this.innerHTML = input.type === 'password' ? 
                    '<i class="fas fa-eye"></i>' : 
                    '<i class="fas fa-eye-slash"></i>';
            }
        });
    });
}

/**
 * Inicializa la subida de archivos con vista previa
 */
function initFileUploadWithPreview() {
    const fileUploads = document.querySelectorAll('.file-upload-with-preview');
    
    fileUploads.forEach(function(container) {
        const input = container.querySelector('input[type="file"]');
        const preview = container.querySelector('.file-preview');
        const placeholder = container.querySelector('.file-placeholder');
        const removeBtn = container.querySelector('.remove-file');
        
        if (input && preview) {
            // Evento de cambio en el input
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        // Mostrar vista previa según tipo de archivo
                        const file = input.files[0];
                        const fileType = file.type;
                        
                        if (fileType.startsWith('image/')) {
                            // Vista previa de imagen
                            preview.innerHTML = `<img src="${e.target.result}" alt="${file.name}" class="img-fluid">`;
                        } else {
                            // Icono según tipo de archivo
                            const iconClass = getFileIconClass(fileType);
                            preview.innerHTML = `
                                <div class="text-center p-3">
                                    <i class="${iconClass} fa-4x mb-2"></i>
                                    <div>${file.name}</div>
                                </div>
                            `;
                        }
                        
                        // Mostrar vista previa y botón de eliminar
                        preview.style.display = 'block';
                        if (placeholder) placeholder.style.display = 'none';
                        if (removeBtn) removeBtn.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                } else {
                    // Limpiar vista previa
                    preview.innerHTML = '';
                    preview.style.display = 'none';
                    if (placeholder) placeholder.style.display = 'block';
                    if (removeBtn) removeBtn.style.display = 'none';
                }
            });
            
            // Evento de eliminar archivo
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    input.value = '';
                    preview.innerHTML = '';
                    preview.style.display = 'none';
                    if (placeholder) placeholder.style.display = 'block';
                    removeBtn.style.display = 'none';
                    
                    // Disparar evento de cambio para actualizar el formulario
                    const event = new Event('change', { bubbles: true });
                    input.dispatchEvent(event);
                });
            }
        }
    });
}