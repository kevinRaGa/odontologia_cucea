<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_odontologo();

$is_admin = $_SESSION['user_role'] == 1;

// Get options for dropdowns
try {
    $odontologos = $pdo->query("SELECT * FROM odontologo")->fetchAll();
    $motivos = $pdo->query("SELECT * FROM motivoconsulta ORDER BY nombre")->fetchAll();
    $pacientes = $pdo->query("SELECT * FROM paciente ORDER BY nombre")->fetchAll();
} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

// Process form
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    try {
        $pdo->beginTransaction();
        
        // Validation
        $required = ['fecha_hora', 'motivo', 'odontologo'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Date validation - prevent past dates
        $appointmentDate = new DateTime($_POST['fecha_hora']);
        $today = new DateTime();
        $today->setTime(0, 0, 0); // Set to midnight for date-only comparison
        
        if ($appointmentDate < $today) {
            throw new Exception("No se pueden crear citas para fechas pasadas");
        }

        // Handle patient selection
        $id_paciente = null;
        $nombre_paciente_temp = null;
        
        if(!empty($_POST['paciente_existente'])) {
            $id_paciente = intval($_POST['paciente_existente']);
        } elseif(!empty($_POST['nuevo_paciente'])) {
            $nombre_paciente_temp = htmlspecialchars(trim($_POST['nuevo_paciente']));
            
            // Validate patient name
            if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $nombre_paciente_temp)) {
                throw new Exception("El nombre del paciente solo puede contener letras y espacios");
            }
        
            // Always create a new patient record, even if name exists
            $stmt = $pdo->prepare("INSERT INTO paciente (nombre) VALUES (?)");
            $stmt->execute([$nombre_paciente_temp]);
            $id_paciente = $pdo->lastInsertId();
            
            // Clear temp name since we now have an ID
            $nombre_paciente_temp = null;
        }

        // Insert new appointment
        $stmt = $pdo->prepare("INSERT INTO citas (
            id_paciente, nombre_paciente_temp, id_odontologo, fecha_hora, motivo, estado, comentarios
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $id_paciente,
            $nombre_paciente_temp,
            intval($_POST['odontologo']),
            $_POST['fecha_hora'],
            intval($_POST['motivo']),
            $_POST['estado'] ?? 'programada',
            !empty($_POST['comentarios']) ? htmlspecialchars($_POST['comentarios']) : null
        ]);

        $pdo->commit();
        header("Location: admin.php");
        exit();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Cita Odontológica</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <link rel="stylesheet" href="css/nueva_cita.module.css?v=1.2">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.2">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container">
            <h1>
                <i class="fas fa-calendar-plus"></i>
                Nueva Cita Odontológica
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="info-grid">
                    <div class="form-group">
                        <label class="form-label">Paciente:</label>
                        <div class="patient-options">
                            <div class="patient-option">
                                <label class="radio-label">
                                    <input type="radio" name="patient_type" id="existing_patient" value="existing" checked 
                                           onclick="document.getElementById('existing-patient-section').style.display='block';
                                                    document.getElementById('new-patient-section').style.display='none';">
                                    <i class="fas fa-user"></i> Paciente existente
                                </label>
                            </div>
                            <div class="patient-option">
                                <label class="radio-label">
                                    <input type="radio" name="patient_type" id="new_patient" value="new"
                                           onclick="document.getElementById('existing-patient-section').style.display='none';
                                                    document.getElementById('new-patient-section').style.display='block';">
                                    <i class="fas fa-user-plus"></i> Nuevo paciente
                                </label>
                            </div>
                        </div>

                        <div id="existing-patient-section">
                            <select name="paciente_existente" id="paciente-search" class="form-control" style="width: 100%">
                                <option value="">Buscar paciente...</option>
                                <?php foreach($pacientes as $p): ?>
                                    <option value="<?= $p['id_paciente'] ?>">
                                        <?= htmlspecialchars($p['nombre']) ?> 
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="new-patient-section" class="hidden">
                            <input type="text" name="nuevo_paciente" id="nuevo_paciente" class="form-control" 
                                placeholder="Nombre completo del nuevo paciente"
                                oninput="validateName(this)">
                            <small class="text-error" id="name-error" style="color: red; display: none;">
                                Solo se permiten letras y espacios
                            </small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Odontólogo:</label>
                        <select name="odontologo" class="form-control" required>
                            <option value="">Seleccione un odontólogo</option>
                            <?php foreach($odontologos as $o): ?>
                                <option value="<?= $o['id_odontologo'] ?>">
                                    <?= htmlspecialchars($o['nombre']) ?>
                                    <?= !empty($o['especialidad']) ? '('.htmlspecialchars($o['especialidad']).')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Fecha y Hora:</label>
                        <input type="datetime-local" name="fecha_hora" id="fecha_hora" class="form-control" required
                               value="<?= date('Y-m-d\TH:i') ?>" min="<?= date('Y-m-d\TH:i') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Motivo:</label>
                        <select name="motivo" class="form-control" required>
                            <option value="">Seleccione un motivo</option>
                            <?php foreach($motivos as $m): ?>
                                <option value="<?= $m['id_motivo'] ?>">
                                    <?= htmlspecialchars($m['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado:</label>
                        <select name="estado" class="form-control" disabled>
                            <option value="programada" selected>Programada</option>
                        </select>
                        <input type="hidden" name="estado" value="programada">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Comentarios:</label>
                        <textarea name="comentarios" class="form-control" rows="3" 
                                  oninput="validateComments(this)"></textarea>
                        <small class="text-error" id="comments-error" style="color: red; display: none;">
                            No se permiten caracteres especiales ni números
                        </small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="window.history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cita
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize sidebar state
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (sidebar.classList.contains('collapsed')) {
                mainContent.classList.add('collapsed');
            }

            // Initialize patient type selection
            const patientType = document.querySelector('input[name="patient_type"]:checked').value;
            if(patientType === 'new') {
                document.getElementById('existing-patient-section').style.display = 'none';
                document.getElementById('new-patient-section').style.display = 'block';
            }
            
            // Initialize Select2 for patient search
            $('#paciente-search').select2({
                placeholder: "Buscar paciente...",
                allowClear: true,
                width: 'resolve'
            });

            // Add event listeners to radio buttons to clear selections
            document.getElementById('existing_patient').addEventListener('change', function() {
                if(this.checked) {
                    // Clear new patient field
                    document.querySelector('input[name="nuevo_paciente"]').value = '';
                }
            });

            document.getElementById('new_patient').addEventListener('change', function() {
                if(this.checked) {
                    // Clear existing patient selection
                    $('#paciente-search').val(null).trigger('change');
                }
            });

            // Initialize Flatpickr for datetime with past date prevention
            flatpickr("#fecha_hora", {
                enableTime: true,
                time_24hr: true,
                minuteIncrement: 15,
                dateFormat: "Y-m-d H:i",
                altInput: true,
                altFormat: "j F Y - H:i",
                locale: "es",
                minDate: "today",
                defaultDate: new Date(),
                minTime: "08:00",
                maxTime: "20:00",
                disable: [
                    function(date) {
                        // Disable weekends
                        return (date.getDay() === 0 || date.getDay() === 6);
                    }
                ]
            });

            // Observe sidebar changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'class') {
                        if (sidebar.classList.contains('collapsed')) {
                            mainContent.classList.add('collapsed');
                        } else {
                            mainContent.classList.remove('collapsed');
                        }
                    }
                });
            });

            observer.observe(sidebar, {
                attributes: true,
                attributeFilter: ['class']
            });
        });

        function validateName(input) {
            const nameError = document.getElementById('name-error');
            // Only allow letters, spaces, and accented characters
            const regex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/;
            
            // Remove any numbers or special characters
            input.value = input.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]/g, '');
            
            if (!regex.test(input.value)) {
                nameError.style.display = 'block';
                input.setCustomValidity('Nombre inválido: solo letras y espacios permitidos');
            } else {
                nameError.style.display = 'none';
                input.setCustomValidity('');
            }
        }

        function validateComments(textarea) {
            const commentsError = document.getElementById('comments-error');
            // Only allow letters, spaces, and basic punctuation
            const regex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.,;:¿?¡!()\-]+$/;
            
            // Remove any numbers or special characters
            textarea.value = textarea.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.,;:¿?¡!()\-]/g, '');
            
            if (!regex.test(textarea.value)) {
                commentsError.style.display = 'block';
                textarea.setCustomValidity('Comentarios inválidos: no se permiten números ni caracteres especiales');
            } else {
                commentsError.style.display = 'none';
                textarea.setCustomValidity('');
            }
        }

        // Update the form submission to prevent invalid names and comments
        document.querySelector('form').addEventListener('submit', function(e) {
            const nameInput = document.getElementById('nuevo_paciente');
            const commentsInput = document.querySelector('textarea[name="comentarios"]');
            
            // Validate name if new patient is selected
            if (nameInput && nameInput.value) {
                const nameRegex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/;
                if (!nameRegex.test(nameInput.value)) {
                    e.preventDefault();
                    alert('Por favor ingrese un nombre válido (solo letras y espacios)');
                    nameInput.focus();
                    return;
                }
            }
            
            // Validate comments
            if (commentsInput && commentsInput.value) {
                const commentsRegex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.,;:¿?¡!()\-]+$/;
                if (!commentsRegex.test(commentsInput.value)) {
                    e.preventDefault();
                    alert('Por favor ingrese comentarios válidos (no se permiten números ni caracteres especiales)');
                    commentsInput.focus();
                    return;
                }
            }
        });
    </script>
</body>
</html>