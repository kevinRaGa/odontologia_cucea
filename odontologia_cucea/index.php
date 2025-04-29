<?php
require_once 'conexion.php';
require_once 'auth_check.php';

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
    $carreras = $pdo->query("SELECT * FROM CarreraEmpleo")->fetchAll();
    $semestres = $pdo->query("SELECT * FROM Semestre")->fetchAll();
    $sexos = $pdo->query("SELECT * FROM Sexo")->fetchAll();
} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

// Process form
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    try {
        $pdo->beginTransaction();
        
        // Common validation
        $required = ['nombre', 'edad', 'peso', 'talla', 'sexo', 'carrera', 'fecha'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        if(isset($_POST['edit_id'])) {
            // Update existing patient
            $stmt = $pdo->prepare("UPDATE Paciente SET
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
            // Insert new patient
            $stmt = $pdo->prepare("INSERT INTO Paciente (
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
        }

        // If new patient, get ID and redirect to second form
        if(!isset($_POST['edit_id'])) {
            $idPaciente = $pdo->lastInsertId();
            
            // Insert initial consultation
            $stmtConsulta = $pdo->prepare("INSERT INTO Consulta (
                id_paciente, fecha, comentarios
            ) VALUES (?, ?, ?)");
            
            $stmtConsulta->execute([
                $idPaciente,
                $_POST['fecha'],
                !empty($_POST['comentarios']) ? htmlspecialchars($_POST['comentarios']) : null
            ]);

            header("Location: second_form.php?id_paciente=".$idPaciente);
            exit();
        }

        $pdo->commit();
        header("Location: pacientes.php");
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
            justify-content: space-between;
            align-items: center;
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
        }

        textarea.form-control {
            min-height: 80px;
        }

        .btn {
            padding: 12px 20px;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        .required:after {
            content: " *";
            color: #e74c3c;
        }

        .btn-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <span>
                    <i class="fas fa-<?= $edit_mode ? 'user-edit' : 'user-plus' ?>"></i>
                    <?= $edit_mode ? 'Editar' : 'Registrar' ?> Paciente
                </span>
                <a href="pacientes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php if($edit_mode): ?>
                    <input type="hidden" name="edit_id" value="<?= $current_paciente['id'] ?>">
                <?php endif; ?>

                <div class="info-grid">
                    <div class="form-group">
                        <label class="form-label required">Nombre completo</label>
                        <input type="text" name="nombre" class="form-control" required
                               value="<?= $edit_mode ? htmlspecialchars($current_paciente['nombre']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Edad</label>
                        <input type="number" name="edad" class="form-control" min="1" max="120" required
                               value="<?= $edit_mode ? $current_paciente['edad'] : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Código único</label>
                        <input type="text" name="codigo" class="form-control" placeholder="Opcional"
                               value="<?= $edit_mode ? htmlspecialchars($current_paciente['codigo']) : '' ?>">
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
                               value="<?= $edit_mode ? htmlspecialchars($current_paciente['enfermedad']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Peso (kg)</label>
                        <input type="number" step="0.1" name="peso" class="form-control" min="20" max="300" required
                               value="<?= $edit_mode ? $current_paciente['peso'] : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Talla (metros)</label>
                        <input type="number" step="0.01" name="talla" class="form-control" min="1.20" max="2.50" required
                               value="<?= $edit_mode ? $current_paciente['talla'] : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Fecha</label>
                        <input type="datetime-local" name="fecha" class="form-control" 
                               value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Detalles adicionales</label>
                        <textarea name="comentarios" class="form-control"><?= 
                            $edit_mode ? htmlspecialchars($current_paciente['comentarios'] ?? '') : '' 
                        ?></textarea>
                    </div>

                    <div class="btn-container">
                        <button type="submit" name="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?= $edit_mode ? 'Guardar Cambios' : 'Registrar Paciente y Continuar' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>