<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_odontologo();

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
        $stmtPaciente = $pdo->prepare("SELECT * FROM Paciente WHERE id_paciente = ?");
        $stmtPaciente->execute([$cita['id_paciente']]);
        $paciente = $stmtPaciente->fetch();
    }

    // Obtener datos de la consulta si existe
    $consulta_data = [];
    if ($cita['id_paciente']) {
        $stmtConsulta = $pdo->prepare("SELECT * FROM Consulta WHERE id_paciente = ? ORDER BY fecha DESC LIMIT 1");
        $stmtConsulta->execute([$cita['id_paciente']]);
        $consulta_data = $stmtConsulta->fetch();
    }

    // Obtener opciones para dropdowns
    $carreras     = $pdo->query("SELECT * FROM CarreraEmpleo")->fetchAll();
    $semestres    = $pdo->query("SELECT * FROM Semestre")->fetchAll();
    $sexos        = $pdo->query("SELECT * FROM Sexo")->fetchAll();
    $motivos      = $pdo->query("SELECT * FROM MotivoConsulta")->fetchAll();
    $planes       = $pdo->query("SELECT * FROM PlanTratamiento")->fetchAll();
    $diagnosticos = $pdo->query("SELECT * FROM Diagnostico")->fetchAll();
    $odontologos  = $pdo->query("SELECT * FROM Odontologo")->fetchAll();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // Validar campos obligatorios
        $required = ['nombre', 'edad', 'sexo', 'carrera', 'semestre', 'peso', 'talla', 'fecha', 'motivo', 'odontologo'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Calcular IMC
        $peso   = floatval($_POST['peso']);
        $talla  = floatval($_POST['talla']);
        $imc    = round($peso / ($talla * $talla), 2);

        // Insertar nuevo paciente
        $stmtPaciente = $pdo->prepare(
            "INSERT INTO Paciente (nombre, edad, codigo, id_carrera, id_semestre, id_sexo, enfermedad_cronica, peso_kg, talla_m)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmtPaciente->execute([
            $_POST['nombre'],
            $_POST['edad'],
            $_POST['codigo']    ?? null,
            $_POST['carrera'],
            $_POST['semestre'],
            $_POST['sexo'],
            $_POST['enfermedad']?? null,
            $_POST['peso'],
            $_POST['talla']
        ]);
        $id_paciente = $pdo->lastInsertId();

        // Insertar consulta definitiva
        $stmtConsulta = $pdo->prepare(
            "INSERT INTO Consulta (id_paciente, id_motivo, id_diagnostico, id_plan, id_odontologo, comentarios, pago, fecha)
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

        // Eliminar cita temporal
        $stmtDelete = $pdo->prepare("DELETE FROM citas WHERE id_cita = ?");
        $stmtDelete->execute([$id_cita]);

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
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .consulta-grid {
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

        textarea.form-control {
            min-height: 100px;
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

        .required:after {
            content: " *";
            color: #e74c3c;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .section-title {
            grid-column: 1 / -1;
            color: #2c3e50;
            font-size: 1.2rem;
            margin: 15px 0 5px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        /* Remove number input arrows */
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <i class="fas fa-calendar-check"></i>
                Completar Cita Odontológica
            </h1>
            
            <?php if (isset($error)): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="info-grid">
                    <h3 class="section-title">Información del Paciente</h3>

                    <div class="form-group">
                        <label class="form-label required">Nombre completo</label>
                        <input type="text" name="nombre" class="form-control" required 
                               value="<?= htmlspecialchars($cita['nombre_paciente_temp'] ?? $paciente['nombre'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Fecha</label>
                        <input type="datetime-local" name="fecha" class="form-control" required 
                               value="<?= date('Y-m-d\TH:i', strtotime($cita['fecha_hora'])) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Edad</label>
                        <input type="number" name="edad" class="form-control" required 
                               value="<?= htmlspecialchars($paciente['edad'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Código único</label>
                        <input type="text" name="codigo" class="form-control" 
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
                               value="<?= htmlspecialchars($paciente['enfermedad_cronica'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Peso (kg)</label>
                        <input type="number" step="0.1" name="peso" class="form-control" required 
                               value="<?= htmlspecialchars($paciente['peso_kg'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Talla (metros)</label>
                        <input type="number" step="0.01" name="talla" class="form-control" required 
                               value="<?= htmlspecialchars($paciente['talla_m'] ?? '') ?>" placeholder="Ejemplo: 1.65">
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
                               value="<?= htmlspecialchars($consulta_data['pago'] ?? '0.00') ?>" placeholder="Ejemplo: 150.00">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Comentarios</label>
                        <textarea name="comentarios" class="form-control" rows="4"><?= htmlspecialchars($consulta_data['comentarios'] ?? $cita['comentarios'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="window.history.back()" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Guardar y Completar Cita
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>