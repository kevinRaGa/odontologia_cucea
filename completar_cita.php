<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_odontologo();

$is_admin = $_SESSION['user_role'] == 1;

// Verificar ID de cita
if (!isset($_GET['id_cita'])) {
    header("Location: admin.php");
    exit();
}

$id_cita = intval($_GET['id_cita']);

try {
    // Obtener datos de la cita
    $stmt = $pdo->prepare("SELECT * FROM citas WHERE id_cita = ?");
    $stmt->execute([$id_cita]);
    $cita = $stmt->fetch();

    if (!$cita) {
        die("Cita no encontrada");
    }

    // Obtener datos del paciente si existe
    $paciente = [];
    if ($cita['id_paciente']) {
        $stmtPaciente = $pdo->prepare("SELECT * FROM paciente WHERE id_paciente = ?");
        $stmtPaciente->execute([$cita['id_paciente']]);
        $paciente = $stmtPaciente->fetch();
    }

    // Obtener datos de la consulta si existe
    $consulta_data = [];
    if ($cita['id_paciente']) {
        $stmtConsulta = $pdo->prepare("SELECT * FROM consulta WHERE id_paciente = ? ORDER BY fecha DESC LIMIT 1");
        $stmtConsulta->execute([$cita['id_paciente']]);
        $consulta_data = $stmtConsulta->fetch();
    }

    // Obtener opciones para dropdowns
    $carreras     = $pdo->query("SELECT * FROM carreraempleo")->fetchAll();
    $semestres    = $pdo->query("SELECT * FROM semestre")->fetchAll();
    $sexos        = $pdo->query("SELECT * FROM sexo")->fetchAll();
    $motivos      = $pdo->query("SELECT * FROM motivoconsulta")->fetchAll();
    $planes       = $pdo->query("SELECT * FROM plantratamiento")->fetchAll();
    $diagnosticos = $pdo->query("SELECT * FROM diagnostico")->fetchAll();
    $odontologos  = $pdo->query("SELECT * FROM odontologo")->fetchAll();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // Get patient ID from the appointment data
        $id_paciente = $cita['id_paciente'];
        
        // If appointment was created with a temporary name (no patient ID), use the patient data from the form
        if (!$id_paciente && !empty($cita['nombre_paciente_temp'])) {
            // Find or create patient with the temporary name
            $stmtFindPaciente = $pdo->prepare("SELECT id_paciente FROM paciente WHERE nombre = ?");
            $stmtFindPaciente->execute([$cita['nombre_paciente_temp']]);
            $existingPaciente = $stmtFindPaciente->fetch();
            
            if ($existingPaciente) {
                $id_paciente = $existingPaciente['id_paciente'];
            } else {
                // Create new patient with just the name
                $stmtCreatePaciente = $pdo->prepare("INSERT INTO paciente (nombre) VALUES (?)");
                $stmtCreatePaciente->execute([$cita['nombre_paciente_temp']]);
                $id_paciente = $pdo->lastInsertId();
            }
        }

        // Validar campos obligatorios
        $required = ['nombre', 'edad', 'sexo', 'carrera', 'semestre', 'peso', 'talla', 'fecha', 'motivo', 'odontologo'];

        // Calcular IMC
        $peso   = floatval($_POST['peso']);
        $talla  = floatval($_POST['talla']);
        $imc    = round($peso / ($talla * $talla), 2);

        $stmtPaciente = $pdo->prepare("
            UPDATE paciente SET
                nombre = ?,
                edad = ?,
                codigo = ?,
                id_carrera = ?,
                id_semestre = ?,
                id_sexo = ?,
                enfermedad_cronica = ?,
                peso_kg = ?,
                talla_m = ?
            WHERE id_paciente = ?
        ");

        $stmtPaciente->execute([
            $_POST['nombre'], 
            $_POST['edad'],
            $_POST['codigo'] ?? null,
            $_POST['carrera'],
            $_POST['semestre'],
            $_POST['sexo'],
            $_POST['enfermedad'] ?? null,
            $_POST['peso'],
            $_POST['talla'],
            $id_paciente
        ]);

        // Insertar consulta definitiva
        $stmtConsulta = $pdo->prepare(
            "INSERT INTO consulta (id_paciente, id_motivo, id_diagnostico, id_plan, id_odontologo, comentarios, pago, fecha)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmtConsulta->execute([
            $id_paciente,
            $_POST['motivo'],
            $_POST['diagnostico'] ?? null,
            $_POST['plan']       ?? null,
            $_POST['odontologo'],
            $_POST['comentarios']?? null,
            $_POST['pago']       ?? 0.00,
            $_POST['fecha']
        ]);

        $stmtUpdate = $pdo->prepare("UPDATE citas SET estado = 'completada' WHERE id_cita = ?");
        $stmtUpdate->execute([$id_cita]);

        $pdo->commit();
        header("Location: pacientes.php");
        exit();

    } catch (Exception $e) {
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
    <title>Completar Cita</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/completar_cita.module.css?v=1.2">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.2">
    <style>
        /* Additional styles for placeholder transparency */
        input::placeholder {
            opacity: 0.5;
        }
    </style>
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
                <i class="fas fa-calendar-check"></i>
                Completar Cita Odontológica
            </h1>
            
            <?php if (isset($error)): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" onsubmit="return validateForm()">
                <div class="info-grid">
                    <h3 class="section-title">Información del Paciente</h3>

                    <!-- Nombre completo: Solo letras y espacios -->
                    <div class="form-group">
                        <label class="form-label required">Nombre completo</label>
                        <input type="text" name="nombre" class="form-control" required 
                               pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+"
                               title="Solo letras y espacios"
                               value="<?= htmlspecialchars($cita['nombre_paciente_temp'] ?? $paciente['nombre'] ?? '') ?>">
                    </div>

                    <!-- Fecha con Flatpickr -->
                    <div class="form-group">
                        <label class="form-label required">Fecha</label>
                        <input type="datetime-local" name="fecha" id="fecha" class="form-control flatpickr" 
                               required readonly
                               value="<?= date('Y-m-d H:i') ?>">
                    </div>

                    <!-- Edad: Solo números positivos -->
                    <div class="form-group">
                        <label class="form-label required">Edad</label>
                        <input type="number" name="edad" class="form-control" min="1" 
                               pattern="[0-9]*" title="Mayor que 0" required
                               value="<?= htmlspecialchars($paciente['edad'] ?? '') ?>">
                    </div>

                    <!-- Código único: Solo números -->
                    <div class="form-group">
                        <label class="form-label">Código único</label>
                        <input type="text" name="codigo" class="form-control" 
                               pattern="[0-9]*" title="Solo números positivos"
                               value="<?= htmlspecialchars($paciente['codigo'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Carrera/Empleo</label>
                        <select name="carrera" class="form-control" required>
                            <?php foreach ($carreras as $c): ?>
                                <option value="<?= $c['id_carrera'] ?>" <?= ($paciente['id_carrera'] ?? '') == $c['id_carrera'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Semestre</label>
                        <select name="semestre" class="form-control" required>
                            <?php foreach ($semestres as $s): ?>
                                <option value="<?= $s['id_semestre'] ?>" <?= ($paciente['id_semestre'] ?? '') == $s['id_semestre'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['numero']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Sexo</label>
                        <select name="sexo" class="form-control" required>
                            <?php foreach ($sexos as $s): ?>
                                <option value="<?= $s['id_sexo'] ?>" <?= ($paciente['id_sexo'] ?? '') == $s['id_sexo'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['genero']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Enfermedad crónica</label>
                        <input type="text" name="enfermedad" class="form-control" 
                               pattern="[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\-]+"
                               title="No se permiten caracteres especiales"
                               value="<?= htmlspecialchars($paciente['enfermedad_cronica'] ?? '') ?>">
                    </div>

                    <!-- Peso: Solo números positivos -->
                    <div class="form-group">
                        <label class="form-label required">Peso (kg)</label>
                        <input type="number" step="0.1" name="peso" class="form-control" 
                               min="0" required
                               value="<?= htmlspecialchars($paciente['peso_kg'] ?? '') ?>">
                    </div>

                    <!-- Talla: Números con punto decimal -->
                    <div class="form-group">
                        <label class="form-label required">Talla (metros)</label>
                        <input type="number" step="0.01" name="talla" class="form-control" 
                               min="0" required
                               placeholder="Ejemplo: 1.65"
                               value="<?= htmlspecialchars($paciente['talla_m'] ?? '') ?>">
                    </div>
                </div>

                <div class="consulta-grid">
                    <h3 class="section-title">Detalles de la Consulta</h3>

                    <div class="form-group">
                        <label class="form-label required">Motivo</label>
                        <select name="motivo" class="form-control" required>
                            <?php foreach ($motivos as $m): ?>
                                <option value="<?= $m['id_motivo'] ?>" <?= ($cita['motivo'] ?? $consulta_data['id_motivo'] ?? '') == $m['id_motivo'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Odontólogo que atendió</label>
                        <select name="odontologo" class="form-control" required>
                            <?php foreach ($odontologos as $o): ?>
                                <option value="<?= $o['id_odontologo'] ?>" <?= ($cita['id_odontologo'] == $o['id_odontologo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($o['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Diagnóstico</label>
                        <select name="diagnostico" class="form-control">
                            <option value="">Seleccione diagnóstico</option>
                            <?php foreach ($diagnosticos as $d): ?>
                                <option value="<?= $d['id_diagnostico'] ?>" <?= ($consulta_data['id_diagnostico'] ?? '') == $d['id_diagnostico'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Plan de tratamiento</label>
                        <select name="plan" class="form-control">
                            <option value="">Seleccione plan</option>
                            <?php foreach ($planes as $p): ?>
                                <option value="<?= $p['id_plan'] ?>" <?= ($consulta_data['id_plan'] ?? '') == $p['id_plan'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pago</label>
                        <input type="number" step="0.01" name="pago" class="form-control" 
                               min="0" 
                               placeholder="Ejemplo: 125"
                               value="<?= htmlspecialchars($consulta_data['pago'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Comentarios</label>
                        <textarea name="comentarios" class="form-control" rows="4" oninput="this.value = this.value.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\-.,]/g, '')"><?= htmlspecialchars($consulta_data['comentarios'] ?? $cita['comentarios'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="window.history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar y Completar Cita
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script>
        // Configuración de Flatpickr
        flatpickr("#fecha", {
            enableTime: true,
            time_24hr: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "j F Y - H:i",
            locale: "es",
            defaultDate: new Date(),
            minDate: "today"
        });

        // Validación de campos numéricos
        function validateForm() {
            const inputs = document.querySelectorAll('input[type="number"]');
            let valid = true;
            
            inputs.forEach(input => {
                if (input.value < 0) {
                    let fieldName = input.previousElementSibling.textContent;
                    if (input.name === 'edad') {
                        alert("Edad debe ser mayor que 0");
                    } else if (input.name === 'codigo') {
                        alert("Código debe contener solo números positivos");
                    } else {
                        alert("No se permiten valores negativos en " + fieldName);
                    }
                    valid = false;
                }
            });
            
            return valid;
        }

        // Validación en tiempo real para campos de texto
        document.querySelector('input[name="nombre"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]/g, '');
        });

        document.querySelector('input[name="enfermedad"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\-]/g, '');
        });

        // Validación para código (solo números)
        document.querySelector('input[name="codigo"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Handle sidebar collapse state
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('mainContent');
            
            // Check if sidebar is collapsed
            const checkSidebarState = () => {
                if (sidebar.classList.contains('collapsed')) {
                    mainContent.classList.add('collapsed');
                } else {
                    mainContent.classList.remove('collapsed');
                }
            };
            
            // Initial check
            checkSidebarState();
            
            // Observe changes to sidebar
            const observer = new MutationObserver(checkSidebarState);
            observer.observe(sidebar, {
                attributes: true,
                attributeFilter: ['class']
            });

            // Toggle sidebar on mobile
            document.getElementById('menuToggle').addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 992 && 
                    !event.target.closest('.sidebar') && 
                    !event.target.closest('#menuToggle')) {
                    sidebar.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>