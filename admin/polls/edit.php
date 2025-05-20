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

// Comprobar ID de encuesta
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'ID de encuesta inválido');
    redirect('index.php');
}

$pollId = (int)$_GET['id'];

// Obtener datos de la encuesta
$db = Database::getInstance();
$poll = $db->fetch(
    "SELECT id, question, status, start_date, end_date, created_at, updated_at
     FROM polls WHERE id = ?",
    [$pollId]
);

// Verificar si la encuesta existe
if (!$poll) {
    setFlashMessage('error', 'La encuesta no existe');
    redirect('index.php');
}

// Obtener opciones de la encuesta
$options = $db->fetchAll(
    "SELECT id, option_text, votes FROM poll_options WHERE poll_id = ? ORDER BY id",
    [$pollId]
);

// Inicializar variables
$errors = [];
$success = false;

// Procesar formulario de edición
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
        
        // Opciones existentes y nuevas
        $existingOptions = isset($_POST['existing_options']) && is_array($_POST['existing_options']) ? $_POST['existing_options'] : [];
        $existingOptionIds = array_keys($existingOptions);
        $newOptions = isset($_POST['new_options']) && is_array($_POST['new_options']) ? $_POST['new_options'] : [];
        
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
        
        // Validar opciones existentes
        $validExistingOptions = [];
        foreach ($existingOptions as $id => $text) {
            $text = sanitize($text);
            if (!empty($text)) {
                if (strlen($text) > 255) {
                    $errors[] = 'La opción existente #' . $id . ' no puede superar los 255 caracteres';
                } else {
                    $validExistingOptions[$id] = $text;
                }
            }
        }
        
        // Validar nuevas opciones
        $validNewOptions = [];
        foreach ($newOptions as $index => $text) {
            $text = sanitize($text);
            if (!empty($text)) {
                if (strlen($text) > 255) {
                    $errors[] = 'La nueva opción #' . ($index + 1) . ' no puede superar los 255 caracteres';
                } else {
                    $validNewOptions[] = $text;
                }
            }
        }
        
        // Verificar que queden al menos 2 opciones (entre existentes y nuevas)
        if (count($validExistingOptions) + count($validNewOptions) < 2) {
            $errors[] = 'Debe proporcionar al menos 2 opciones válidas para la encuesta';
        }
        
        // Si no hay errores, actualizar la encuesta
        if (empty($errors)) {
            try {
                // Iniciar transacción
                $transaction = new Transaction();
                $transaction->begin();
                
                // Actualizar datos de la encuesta
                $db->query(
                    "UPDATE polls SET 
                     question = ?, 
                     status = ?, 
                     start_date = ?, 
                     end_date = ?, 
                     updated_at = NOW() 
                     WHERE id = ?",
                    [
                        $question,
                        $status,
                        $start_date,
                        !empty($end_date) ? $end_date : null,
                        $pollId
                    ]
                );
                
                // Obtener todas las opciones existentes de la BD
                $existingOptionsInDb = [];
                foreach ($options as $option) {
                    $existingOptionsInDb[$option['id']] = $option;
                }
                
                // Actualizar opciones existentes
                foreach ($validExistingOptions as $id => $text) {
                    $db->query(
                        "UPDATE poll_options SET option_text = ? WHERE id = ? AND poll_id = ?",
                        [$text, $id, $pollId]
                    );
                }
                
                // Determinar opciones a eliminar (las que estaban en la BD pero no en el formulario)
                $optionsToDelete = array_diff(array_keys($existingOptionsInDb), array_keys($validExistingOptions));
                
                // Eliminar opciones no presentes en el formulario (si no tienen votos)
                foreach ($optionsToDelete as $optionId) {
                    // Solo eliminar si no tiene votos
                    if ($existingOptionsInDb[$optionId]['votes'] == 0) {
                        $db->query("DELETE FROM poll_options WHERE id = ? AND poll_id = ?", [$optionId, $pollId]);
                    } else {
                        // Si tiene votos, no la eliminamos pero mostramos advertencia
                        setFlashMessage('warning', 'No se ha podido eliminar alguna opción porque ya tiene votos');
                    }
                }
                
                // Insertar nuevas opciones
                foreach ($validNewOptions as $option) {
                    $db->query(
                        "INSERT INTO poll_options (poll_id, option_text, votes, created_at) 
                         VALUES (?, ?, 0, NOW())",
                        [$pollId, $option]
                    );
                }
                
                // Confirmar transacción
                $transaction->commit();
                
                // Registrar acción
                logAdminAction('update', 'Encuesta actualizada: ' . $question, 'polls', $pollId);
                
                setFlashMessage('success', 'La encuesta ha sido actualizada correctamente');
                $success = true;
                
                // Recargar datos actualizados
                $poll = $db->fetch(
                    "SELECT id, question, status, start_date, end_date, created_at, updated_at
                     FROM polls WHERE id = ?",
                    [$pollId]
                );
                
                $options = $db->fetchAll(
                    "SELECT id, option_text, votes FROM poll_options WHERE poll_id = ? ORDER BY id",
                    [$pollId]
                );
                
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $transaction->rollback();
                $errors[] = 'Error al actualizar la encuesta: ' . $e->getMessage();
            }
        }
    }
}

// Título de la página
$pageTitle = 'Editar Encuesta - Panel de Administración';
$currentMenu = 'polls_edit';

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
                    <h1 class="m-0">Editar Encuesta</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo ADMIN_PATH; ?>/dashboard.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Encuestas</a></li>
                        <li class="breadcrumb-item active">Editar Encuesta</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido -->
    <div class="content">
        <div class="container-fluid">
            <!-- Mensajes de error y éxito -->
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
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <h5><i class="fas fa-check-circle me-2"></i>Éxito</h5>
                    <p class="mb-0">La encuesta ha sido actualizada correctamente.</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Información de la encuesta -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h3 class="card-title">Información de la Encuesta</h3>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>ID:</strong>
                                    <span><?php echo $poll['id']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>Fecha de creación:</strong>
                                    <span><?php echo formatDate($poll['created_at'], 'd/m/Y H:i'); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>Última actualización:</strong>
                                    <span><?php echo formatDate($poll['updated_at'], 'd/m/Y H:i'); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>Opciones:</strong>
                                    <span><?php echo count($options); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>Total de votos:</strong>
                                    <span>
                                        <?php 
                                            $totalVotes = 0;
                                            foreach ($options as $option) {
                                                $totalVotes += $option['votes'];
                                            }
                                            echo number_format($totalVotes);
                                        ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h3 class="card-title">Acciones</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="results.php?id=<?php echo $poll['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-chart-bar me-1"></i> Ver Resultados
                                </a>
                                <a href="../index.php#poll-<?php echo $poll['id']; ?>" class="btn btn-success" target="_blank">
                                    <i class="fas fa-eye me-1"></i> Ver en el Sitio
                                </a>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash me-1"></i> Eliminar Encuesta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulario de edición -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Editar Encuesta</h3>
                </div>
                <div class="card-body">
                    <form action="edit.php?id=<?php echo $pollId; ?>" method="post" id="editPollForm">
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
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $poll['start_date']; ?>" required>
                                <div class="form-text">Cuándo se empezará a mostrar la encuesta</div>
                            </div>
                            
                            <!-- Fecha de fin -->
                            <div class="col-md-4 mb-3">
                                <label for="end_date" class="form-label">Fecha de fin</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $poll['end_date']; ?>">
                                <div class="form-text">Opcional. Cuándo dejará de mostrarse la encuesta</div>
                            </div>
                        </div>
                        
                        <!-- Opciones existentes de la encuesta -->
                        <h4 class="mt-4 mb-3">Opciones Existentes</h4>
                        <div id="existing-options-container">
                            <?php foreach ($options as $option): ?>
                                <div class="option-item mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">ID <?php echo $option['id']; ?></span>
                                        <input type="text" class="form-control" name="existing_options[<?php echo $option['id']; ?>]" value="<?php echo htmlspecialchars($option['option_text']); ?>" required>
                                        
                                        <?php if ($option['votes'] > 0): ?>
                                            <span class="input-group-text bg-secondary text-white" title="Esta opción ya tiene votos y no se puede eliminar">
                                                <?php echo number_format($option['votes']); ?> votos
                                            </span>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-danger remove-existing-option" data-id="<?php echo $option['id']; ?>">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Nuevas opciones -->
                        <h4 class="mt-4 mb-3">Añadir Nuevas Opciones</h4>
                        <div id="new-options-container">
                            <div class="new-option-item mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">Nueva</span>
                                    <input type="text" class="form-control" name="new_options[]" placeholder="Escribe una nueva opción...">
                                    <button type="button" class="btn btn-danger remove-new-option"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" id="add-new-option" class="btn btn-secondary">
                                <i class="fas fa-plus-circle"></i> Añadir otra opción
                            </button>
                            <div class="form-text mt-2">Debe tener al menos 2 opciones en total (entre existentes y nuevas)</div>
                        </div>
                        
                        <hr>
                        
                        <!-- Botones de acción -->
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Volver
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar la encuesta "<?php echo htmlspecialchars($poll['question']); ?>"?</p>
                <p class="text-danger">Esta acción eliminará también todas las opciones y votos asociados, y no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <form action="index.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $poll['id']; ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . '/includes/footer.php'; ?>

<!-- Scripts específicos para esta página -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elementos DOM
        const existingOptionsContainer = document.getElementById('existing-options-container');
        const newOptionsContainer = document.getElementById('new-options-container');
        const addNewOptionButton = document.getElementById('add-new-option');
        const form = document.getElementById('editPollForm');
        
        // Evento para eliminar opciones existentes
        document.querySelectorAll('.remove-existing-option').forEach(button => {
            button.addEventListener('click', function() {
                if (confirm('¿Estás seguro de que deseas eliminar esta opción?')) {
                    const optionItem = this.closest('.option-item');
                    optionItem.parentNode.removeChild(optionItem);
                }
            });
        });
        
        // Evento para eliminar nuevas opciones
        document.querySelectorAll('.remove-new-option').forEach(button => {
            button.addEventListener('click', function() {
                const optionItem = this.closest('.new-option-item');
                newOptionsContainer.removeChild(optionItem);
            });
        });
        
        // Añadir nueva opción
        addNewOptionButton.addEventListener('click', function() {
            const newOption = document.createElement('div');
            newOption.className = 'new-option-item mb-3';
            newOption.innerHTML = `
                <div class="input-group">
                    <span class="input-group-text">Nueva</span>
                    <input type="text" class="form-control" name="new_options[]" placeholder="Escribe una nueva opción...">
                    <button type="button" class="btn btn-danger remove-new-option"><i class="fas fa-times"></i></button>
                </div>
            `;
            
            newOptionsContainer.appendChild(newOption);
            
            // Enfocar el nuevo campo
            const newInput = newOption.querySelector('input');
            newInput.focus();
            
            // Añadir evento para eliminar
            const removeButton = newOption.querySelector('.remove-new-option');
            removeButton.addEventListener('click', function() {
                newOptionsContainer.removeChild(newOption);
            });
        });
        
        // Validación del formulario
        form.addEventListener('submit', function(e) {
            // Contar opciones existentes válidas
            const existingOptionsInputs = existingOptionsContainer.querySelectorAll('input[type="text"]');
            let validExistingOptions = 0;
            
            existingOptionsInputs.forEach(input => {
                if (input.value.trim() !== '') {
                    validExistingOptions++;
                }
            });
            
            // Contar nuevas opciones válidas
            const newOptionsInputs = newOptionsContainer.querySelectorAll('input[type="text"]');
            let validNewOptions = 0;
            
            newOptionsInputs.forEach(input => {
                if (input.value.trim() !== '') {
                    validNewOptions++;
                }
            });
            
            // Verificar total
            if (validExistingOptions + validNewOptions < 2) {
                e.preventDefault();
                alert('Debe proporcionar al menos 2 opciones válidas para la encuesta');
            }
        });
    });
</script>