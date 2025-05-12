<?php

require_once 'conexion.php';
require_once 'auth_check.php';

$is_admin = $_SESSION['user_role'] == 1;

// Check edit mode
$edit_mode = false;
$current_paciente = [];

if(isset($_GET['edit_id'])) {
    $edit_mode = true;
    $current_paciente = [
        'id' => $_GET['edit_id'],
        'nombre' => $_GET['nombre'] ?? '',
        'edad' => $_GET['edad'] ?? '',
        'codigo' => $_GET['codigo'] ?? '',
        'id_carrera' => $_GET['carrera'] ?? '',
        'id_semestre' => $_GET['semestre'] ?? '',
        'id_sexo' => $_GET['sexo'] ?? '',
        'enfermedad' => $_GET['enfermedad'] ?? '',
        'peso' => $_GET['peso'] ?? '',
        'talla' => $_GET['talla'] ?? '',
        'comentarios' => $_GET['comentarios'] ?? ''
    ];
}

// Get options for dropdowns
try {
    $carreras = $pdo->query("SELECT * FROM carreraempleo")->fetchAll();
    $semestres = $pdo->query("SELECT * FROM semestre")->fetchAll();
    $sexos = $pdo->query("SELECT * FROM sexo")->fetchAll();
} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

// Process form
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    try {
        // Check for duplicate submission
        if (isset($_SESSION['last_submission']) && (time() - $_SESSION['last_submission'] < 5)) {
            throw new Exception("Por favor espere antes de enviar el formulario nuevamente");
        }
        $_SESSION['last_submission'] = time();

        $pdo->beginTransaction();
        
        // Common validation
        $required = ['nombre', 'edad', 'peso', 'talla', 'sexo', 'carrera', 'fecha'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Validate name format
        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/', $_POST['nombre'])) {
            throw new Exception("El nombre solo puede contener letras y espacios");
        }

        // Validate chronic disease if provided
        if (!empty($_POST['enfermedad']) && !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\-]+$/', $_POST['enfermedad'])) {
            throw new Exception("La enfermedad crónica contiene caracteres no permitidos");
        }

        // Validate code if provided
        if (!empty($_POST['codigo']) && !preg_match('/^[0-9]*$/', $_POST['codigo'])) {
            throw new Exception("El código debe contener solo números");
        }

        if(isset($_POST['edit_id'])) {
            // Update existing patient
            $stmt = $pdo->prepare("UPDATE paciente SET
                nombre = ?,
                edad = ?,
                codigo = ?,
                id_carrera = ?,
                id_semestre = ?,
                id_sexo = ?,
                enfermedad_cronica = ?,
                peso_kg = ?,
                talla_m = ?
                WHERE id_paciente = ?");
            
            $stmt->execute([
                htmlspecialchars($_POST['nombre']),
                intval($_POST['edad']),
                !empty($_POST['codigo']) ? htmlspecialchars($_POST['codigo']) : null,
                intval($_POST['carrera']),
                !empty($_POST['semestre']) ? intval($_POST['semestre']) : null,
                intval($_POST['sexo']),
                !empty($_POST['enfermedad']) ? htmlspecialchars($_POST['enfermedad']) : null,
                floatval($_POST['peso']),
                floatval($_POST['talla']),
                intval($_POST['edit_id'])
            ]);
            
        } else {
            // Check for existing patient
            $checkStmt = $pdo->prepare("SELECT id_paciente FROM paciente 
                                      WHERE nombre = ? AND edad = ? AND (codigo = ? OR ? IS NULL)");
            $checkStmt->execute([
                $_POST['nombre'],
                $_POST['edad'],
                $_POST['codigo'] ?? null,
                $_POST['codigo'] ?? null
            ]);
            
            if($checkStmt->fetch()) {
                throw new Exception("Este paciente ya está registrado");
            }

            // Insert new patient
            $stmt = $pdo->prepare("INSERT INTO paciente (
                nombre, edad, codigo, id_carrera, id_semestre, id_sexo,
                enfermedad_cronica, peso_kg, talla_m
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                htmlspecialchars($_POST['nombre']),
                intval($_POST['edad']),
                !empty($_POST['codigo']) ? htmlspecialchars($_POST['codigo']) : null,
                intval($_POST['carrera']),
                !empty($_POST['semestre']) ? intval($_POST['semestre']) : null,
                intval($_POST['sexo']),
                !empty($_POST['enfermedad']) ? htmlspecialchars($_POST['enfermedad']) : null,
                floatval($_POST['peso']),
                floatval($_POST['talla'])
            ]);

            $idPaciente = $pdo->lastInsertId();
            
            // Insert initial consultation
            $stmtConsulta = $pdo->prepare("INSERT INTO consulta (
                id_paciente, fecha, comentarios
            ) VALUES (?, ?, ?)");
            
            $stmtConsulta->execute([
                $idPaciente,
                $_POST['fecha'],
                !empty($_POST['comentarios']) ? htmlspecialchars($_POST['comentarios']) : null
            ]);
        }

        $pdo->commit();
        
        // Set session flag and redirect
        $_SESSION['form_submitted'] = true;
        
        if(!isset($_POST['edit_id'])) {
            header("Location: second_form.php?id_paciente=".$idPaciente);
        } else {
            header("Location: pacientes.php");
        }
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
    <title><?= $edit_mode ? 'Editar' : 'Registrar' ?> Paciente</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/index.module.css?v=1.1">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.1">
    <style>
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

    <div class="main-content" id="mainContent">
        <div class="container">
            <h1>
                <i class="fas fa-<?= $edit_mode ? 'user-edit' : 'user-plus' ?>"></i>
                <?= $edit_mode ? 'Editar' : 'Registrar' ?> Paciente
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" onsubmit="return validateForm()">
                <?php if($edit_mode): ?>
                    <input type="hidden" name="edit_id" value="<?= $current_paciente['id'] ?>">
                <?php endif; ?>

                <div class="info-grid">
                    <div class="form-group">
                        <label class="form-label required">Nombre completo</label>
                        <input type="text" name="nombre" class="form-control" required
                               pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+"
                               title="Solo letras y espacios"
                               value="<?= $edit_mode ? htmlspecialchars($current_paciente['nombre']) : '' ?>"
                               oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]/g, '')">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Edad</label>
                        <input type="number" name="edad" class="form-control" min="1" max="120" required
                               value="<?= $edit_mode ? $current_paciente['edad'] : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Código único</label>
                        <input type="text" name="codigo" class="form-control" placeholder="Opcional"
                               pattern="[0-9]*" title="Solo números positivos"
                               value="<?= $edit_mode ? htmlspecialchars($current_paciente['codigo']) : '' ?>"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Carrera/Empleo</label>
                        <select name="carrera" class="form-control" required>
                            <option value="">Seleccione una opción</option>
                            <?php foreach($carreras as $c): ?>
                                <option value="<?= $c['id_carrera'] ?>" 
                                    <?= ($edit_mode && $c['id_carrera'] == $current_paciente['id_carrera']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Semestre</label>
                        <select name="semestre" class="form-control" required>
                            <option value="">Seleccione una opción</option>
                            <?php foreach($semestres as $s): ?>
                                <option value="<?= $s['id_semestre'] ?>"
                                    <?= ($edit_mode && $s['id_semestre'] == $current_paciente['id_semestre']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['numero']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Sexo</label>
                        <select name="sexo" class="form-control" required>
                            <option value="">Seleccione una opción</option>
                            <?php foreach($sexos as $s): ?>
                                <option value="<?= $s['id_sexo'] ?>"
                                    <?= ($edit_mode && $s['id_sexo'] == $current_paciente['id_sexo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['genero']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Enfermedad crónica</label>
                        <input type="text" name="enfermedad" class="form-control" placeholder="Dejar en blanco si no aplica"
                               pattern="[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\-]+"
                               title="No se permiten caracteres especiales"
                               value="<?= $edit_mode ? htmlspecialchars($current_paciente['enfermedad']) : '' ?>"
                               oninput="this.value = this.value.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\-]/g, '')">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Peso (kg)</label>
                        <input type="number" step="0.1" name="peso" class="form-control" min="20" max="300" required
                               value="<?= $edit_mode ? $current_paciente['peso'] : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Talla (metros)</label>
                        <input type="number" step="0.01" name="talla" class="form-control" min="1.20" max="2.50" required
                               placeholder="Ejemplo: 1.65"
                               value="<?= $edit_mode ? $current_paciente['talla'] : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Fecha</label>
                        <input type="datetime-local" name="fecha" id="fecha" class="form-control" 
                               required readonly
                               value="<?= date('Y-m-d H:i') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Detalles adicionales</label>
                        <textarea name="comentarios" class="form-control" 
                                  oninput="this.value = this.value.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\-.,]/g, '')"><?= 
                            $edit_mode ? htmlspecialchars($current_paciente['comentarios'] ?? '') : '' 
                        ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <div>
                        <button type="button" onclick="window.history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                    </div>
                    <div>
                        <button type="submit" name="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?= $edit_mode ? 'Guardar Cambios' : 'Registrar Paciente y Continuar' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script>
        // Flatpickr configuration
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

        // Form validation
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

        // Toggle sidebar on mobile
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992 && 
                !event.target.closest('.sidebar') && 
                !event.target.closest('#menuToggle')) {
                document.querySelector('.sidebar').classList.remove('active');
            }
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
        });
    </script>
</body>
</html>