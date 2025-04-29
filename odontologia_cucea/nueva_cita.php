<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_odontologo();

// Get options for dropdowns
try {
    $odontologos = $pdo->query("SELECT * FROM Odontologo")->fetchAll();
    $motivos = $pdo->query("SELECT * FROM MotivoConsulta ORDER BY nombre")->fetchAll();
    $pacientes = $pdo->query("SELECT * FROM Paciente ORDER BY nombre")->fetchAll();
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

        // Check if creating new patient
        $id_paciente = null;
        $nombre_paciente_temp = null;
        
        if(!empty($_POST['paciente_existente'])) {
            $id_paciente = intval($_POST['paciente_existente']);
        } elseif(!empty($_POST['nuevo_paciente'])) {
            $nombre_paciente_temp = htmlspecialchars(trim($_POST['nuevo_paciente']));
            
            // Optionally create patient immediately
            if(isset($_POST['crear_paciente'])) {
                $stmt = $pdo->prepare("INSERT INTO Paciente (nombre) VALUES (?)");
                $stmt->execute([$nombre_paciente_temp]);
                $id_paciente = $pdo->lastInsertId();
                $nombre_paciente_temp = null;
            }
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            padding: 25px;
            margin-top: 20px;
        }

        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #3498db;
        }

        .form-label {
            display: block;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        .patient-options {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .patient-option {
            flex: 1;
            text-align: center;
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 10px;
            background: #e9ecef;
            border-radius: 5px;
            transition: all 0.2s;
        }

        .radio-label:hover {
            background: #dee2e6;
        }

        .hidden {
            display: none;
        }

        .required:after {
            content: " *";
            color: #e74c3c;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
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
                                        (<?= $p['codigo'] ?? 'Sin código' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="new-patient-section" class="hidden">
                            <input type="text" name="nuevo_paciente" class="form-control" placeholder="Nombre completo del nuevo paciente">
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
                        <input type="datetime-local" name="fecha_hora" class="form-control" required
                               value="<?= date('Y-m-d\TH:i') ?>">
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
                        <select name="estado" class="form-control">
                            <option value="programada">Programada</option>
                            <option value="completada">Completada</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Comentarios:</label>
                        <textarea name="comentarios" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="window.history.back()" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" name="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Guardar Cita
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            // Initialize Flatpickr for datetime
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
        });
    </script>
</body>
</html>