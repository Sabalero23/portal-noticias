<?php
// Definir ruta base
define('BASE_PATH', dirname(dirname(__DIR__)));
define('ADMIN_PATH', dirname(__DIR__));

// Incluir archivos necesarios
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db_connection.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once ADMIN_PATH . '/includes/functions.php';

// Verificar si el usuario está logueado y tiene permisos
$auth = new Auth();
$auth->requirePermission(['admin', 'editor'], ADMIN_PATH . '/dashboard.php');

// Título de la página
$pageTitle = 'Añadir Nueva Encuesta - Panel de Administración';
$currentMenu = 'polls_add';

// Inicializar variables
$errors = [];
$success = false;
$poll = [
    'question' => '',
    'status' => 'active',
    'start_date' => date('Y-m-d'),
    'end_date' => '',
    'options' => ['', '']
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Validar datos
        $question = isset($_POST['question']) ? sanitize($_POST['question']) : '';
        $status = isset($_POST['status']) ? sanitize($_POST['status']) : '';
        $start_date = isset($_POST['start_date']) ? sanitize($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize($_POST['end_date']) : '';
        $options = isset($_POST['options']) && is_array($_POST['options']) ? $_POST['options'] : [];
        
        // Validar pregunta
        if (empty($question)) {
            $errors[] = 'La pregunta de la encuesta es obligatoria';
        } elseif (strlen($question) > 255) {
            $errors[] = 'La pregunta no puede superar los 255 caracteres';
        }
        
        // Validar estado
        if (!in_array($status, ['active', 'inactive', 'closed'])) {
            $errors[] = 'El estado seleccionado no es válido';
        }
        
        // Validar fechas
        if (empty($start_date)) {
            $errors[] = 'La fecha de inicio es obligatoria';
        } elseif (!validateDate($start_date)) {
            $errors[] = 'La fecha de inicio no es válida';
        }
        
        if (!empty($end_date) && !validateDate($end_date)) {
            $errors[] = 'La fecha de fin no es válida';
        }
        
        if (!empty($start_date) && !empty($end_date) && strtotime($start_date) > strtotime($end_date)) {
            $errors[] = 'La fecha de fin debe ser posterior a la fecha de inicio';
        }
        
        // Validar opciones
        $validOptions = [];
        foreach ($options as $index => $option) {
            $option = sanitize($option);
            if (!empty($option)) {
                if (strlen($option) > 255) {
                    $errors[] = 'La opción #' . ($index + 1) . ' no puede superar los 255 caracteres';
                } else {
                    $validOptions[] = $option;
                }
            }
        }
        
        if (count($validOptions) < 2) {
            $errors[] = 'Debe proporcionar al menos 2 opciones válidas para la encuesta';
        }
        
        // Si no hay errores, guardar la encuesta
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                
                // Iniciar transacción
                $transaction = new Transaction();
                $transaction->begin();
                
                // Insertar encuesta
                $db->query(
                    "INSERT INTO polls (question, status, start_date, end_date, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, NOW(), NOW())",
                    [
                        $question,
                        $status,
                        $start_date,
                        !empty($end_date) ? $end_date : null
                    ]
                );
                
                $pollId = $db->lastInsertId();
                
                // Insertar opciones
                foreach ($validOptions as $option) {
                    $db->query(
                        "INSERT INTO poll_options (poll_id, option_text, votes, created_at) 
                         VALUES (?, ?, 0, NOW())",
                        [$pollId, $option]
                    );
                }
                
                // Confirmar transacción
                $transaction->commit();
                
                // Registrar acción
                logAdminAction('create', 'Nueva encuesta creada: ' . $question, 'polls', $pollId);
                
                setFlashMessage('success', 'La encuesta ha sido creada correctamente');
                redirect('index.php');
                
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $transaction->rollback();
                $errors[] = 'Error al guardar la encuesta: ' . $e->getMessage();
            }
        }
        
        // Si hay errores, mantener los datos enviados
        if (!empty($errors)) {
            $poll = [
                'question' => $question,
                'status' => $status,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'options' => $options
            ];
        }
    }
}

// Incluir cabecera
include_once ADMIN_PATH . '/includes/header.php';
include_once ADMIN_PATH . '/includes/sidebar.php';

/**
 * Validar formato de fecha (Y-m-d)
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
?>

<!-- Contenido principal -->
<div class="content-wrapper">
    <!-- Cabecera de página -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Añadir Nueva Encuesta</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Encuestas</a></li>
                        <li class="breadcrumb-item active">Añadir Nueva</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Mensajes de error -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Formulario -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Datos de la Encuesta</h3>
                </div>
                <div class="card-body">
                    <form action="add.php" method="post" id="pollForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <!-- Pregunta de la encuesta -->
                        <div class="mb-3">
                            <label for="question" class="form-label">Pregunta <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="question" name="question" value="<?php echo htmlspecialchars($poll['question']); ?>" required>
                            <div class="form-text">La pregunta principal de la encuesta (máx. 255 caracteres)</div>
                        </div>
                        
                        <div class="row">
                            <!-- Estado -->
                            <div class="col-md-4 mb-3">
                                <label for="status" class="form-label">Estado <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $poll['status'] === 'active' ? 'selected' : ''; ?>>Activa</option>
                                    <option value="inactive" <?php echo $poll['status'] === 'inactive' ? 'selected' : ''; ?>>Inactiva</option>
                                    <option value="closed" <?php echo $poll['status'] === 'closed' ? 'selected' : ''; ?>>Cerrada</option>
                                </select>
                                <div class="form-text">
                                    Activa: visible y se puede votar<br>
                                    Inactiva: no visible<br>
                                    Cerrada: visible pero no se puede votar
                                </div>
                            </div>
                            
                            <!-- Fecha de inicio -->
                            <div class="col-md-4 mb-3">
                                <label for="start_date" class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($poll['start_date']); ?>" required>
                                <div class="form-text">Cuándo se empezará a mostrar la encuesta</div>
                            </div>
                            
                            <!-- Fecha de fin -->
                            <div class="col-md-4 mb-3">
                                <label for="end_date" class="form-label">Fecha de fin</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($poll['end_date']); ?>">
                                <div class="form-text">Opcional. Cuándo dejará de mostrarse la encuesta</div>
                            </div>
                        </div>
                        
                        <!-- Opciones de la encuesta -->
                        <h4 class="mt-4 mb-3">Opciones de la Encuesta</h4>
                        <div id="options-container">
                            <?php foreach ($poll['options'] as $index => $option): ?>
                                <div class="option-item mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">Opción <?php echo $index + 1; ?></span>
                                        <input type="text" class="form-control" name="options[]" value="<?php echo htmlspecialchars($option); ?>" required>
                                        <?php if ($index > 1): ?>
                                            <button type="button" class="btn btn-danger remove-option"><i class="fas fa-times"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" id="add-option" class="btn btn-secondary">
                                <i class="fas fa-plus-circle"></i> Añadir otra opción
                            </button>
                            <div class="form-text mt-2">Debe proporcionar al menos 2 opciones</div>
                        </div>
                        
                        <hr>
                        
                        <!-- Botones de acción -->
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Volver
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar Encuesta
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Scripts específicos para esta página -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const optionsContainer = document.getElementById('options-container');
        const addOptionButton = document.getElementById('add-option');
        let optionCount = <?php echo count($poll['options']); ?>;
        
        // Añadir nueva opción
        addOptionButton.addEventListener('click', function() {
            optionCount++;
            
            const newOption = document.createElement('div');
            newOption.className = 'option-item mb-3';
            newOption.innerHTML = `
                <div class="input-group">
                    <span class="input-group-text">Opción ${optionCount}</span>
                    <input type="text" class="form-control" name="options[]" required>
                    <button type="button" class="btn btn-danger remove-option"><i class="fas fa-times"></i></button>
                </div>
            `;
            
            optionsContainer.appendChild(newOption);
            
            // Enfocar el nuevo campo
            const newInput = newOption.querySelector('input');
            newInput.focus();
            
            // Añadir evento para eliminar
            const removeButton = newOption.querySelector('.remove-option');
            removeButton.addEventListener('click', function() {
                optionsContainer.removeChild(newOption);
                updateOptionNumbers();
            });
        });
        
        // Eventos para botones de eliminar existentes
        document.querySelectorAll('.remove-option').forEach(button => {
            button.addEventListener('click', function() {
                const optionItem = this.closest('.option-item');
                optionsContainer.removeChild(optionItem);
                updateOptionNumbers();
            });
        });
        
        // Actualizar números de opciones
        function updateOptionNumbers() {
            document.querySelectorAll('.option-item').forEach((item, index) => {
                const label = item.querySelector('.input-group-text');
                label.textContent = `Opción ${index + 1}`;
            });
            
            optionCount = document.querySelectorAll('.option-item').length;
        }
        
        // Validación del formulario
        const form = document.getElementById('pollForm');
        form.addEventListener('submit', function(e) {
            const options = document.querySelectorAll('input[name="options[]"]');
            let validOptionsCount = 0;
            
            options.forEach(option => {
                if (option.value.trim() !== '') {
                    validOptionsCount++;
                }
            });
            
            if (validOptionsCount < 2) {
                e.preventDefault();
                alert('Debe proporcionar al menos 2 opciones válidas para la encuesta');
            }
        });
    });
</script>