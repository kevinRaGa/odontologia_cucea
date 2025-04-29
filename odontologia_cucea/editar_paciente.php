<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_odontologo();

// Verify patient ID exists
if(!isset($_GET['id'])) {
    header("Location: pacientes.php");
    exit();
}

$id_paciente = intval($_GET['id']);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Validate required fields
        $required = ['nombre', 'edad', 'peso', 'talla', 'sexo', 'carrera'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Update patient data
        $stmt = $pdo->prepare("
            UPDATE Paciente SET
                nombre = ?,
                edad = ?,
                codigo = ?,
                id_carrera = ?,
                id_semestre = ?,
                id_sexo = ?,
                enfermedad_cronica = ?,
                peso_kg = ?,
                talla_m = ?,
                correo_electronico = ?,
                telefono = ?
            WHERE id_paciente = ?
        ");
        
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
            !empty($_POST['correo']) ? htmlspecialchars($_POST['correo']) : null,
            !empty($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : null,
            $id_paciente
        ]);

        $pdo->commit();
        header("Location: perfil_paciente.php?id_paciente=".$id_paciente);
        exit();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get patient information for form
try {
    // Basic patient info
    $stmt = $pdo->prepare("
        SELECT p.*, 
               c.nombre as carrera_nombre, 
               s.numero as semestre_numero, 
               sx.genero as sexo_nombre
        FROM Paciente p
        LEFT JOIN CarreraEmpleo c ON p.id_carrera = c.id_carrera
        LEFT JOIN Semestre s ON p.id_semestre = s.id_semestre
        LEFT JOIN Sexo sx ON p.id_sexo = sx.id_sexo
        WHERE p.id_paciente = ?
    ");
    $stmt->execute([$id_paciente]);
    $paciente = $stmt->fetch();

    if(!$paciente) {
        header("Location: pacientes.php");
        exit();
    }

    // Get options for dropdowns
    $carreras = $pdo->query("SELECT * FROM CarreraEmpleo")->fetchAll();
    $semestres = $pdo->query("SELECT * FROM Semestre")->fetchAll();
    $sexos = $pdo->query("SELECT * FROM Sexo")->fetchAll();

} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Paciente</title>
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

        .profile-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .required:after {
            content: " *";
            color: #e74c3c;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <i class="fas fa-user-edit"></i>
                Editar Paciente: <?= htmlspecialchars($paciente['nombre']) ?>
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <!-- Basic Information Section -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-id-card"></i> Información Básica
                    </h2>
                    <div class="info-grid">
                        <div class="form-group">
                            <label class="form-label required">Nombre completo</label>
                            <input type="text" name="nombre" class="form-control" 
                                   value="<?= htmlspecialchars($paciente['nombre']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Edad</label>
                            <input type="number" name="edad" class="form-control" min="1" max="120"
                                   value="<?= $paciente['edad'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control"
                                   value="<?= htmlspecialchars($paciente['codigo'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Carrera/Empleo</label>
                            <select name="carrera" class="form-control" required>
                                <option value="">Seleccione una opción</option>
                                <?php foreach($carreras as $c): ?>
                                    <option value="<?= $c['id_carrera'] ?>" 
                                        <?= ($paciente['id_carrera'] == $c['id_carrera']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Semestre</label>
                            <select name="semestre" class="form-control">
                                <option value="">Seleccione una opción</option>
                                <?php foreach($semestres as $s): ?>
                                    <option value="<?= $s['id_semestre'] ?>"
                                        <?= ($paciente['id_semestre'] == $s['id_semestre']) ? 'selected' : '' ?>>
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
                                        <?= ($paciente['id_sexo'] == $s['id_sexo']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['genero']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Health Information Section -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-heartbeat"></i> Información de Salud
                    </h2>
                    <div class="info-grid">
                        <div class="form-group">
                            <label class="form-label">Enfermedad crónica</label>
                            <input type="text" name="enfermedad" class="form-control"
                                   value="<?= htmlspecialchars($paciente['enfermedad_cronica'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Peso (kg)</label>
                            <input type="number" step="0.1" name="peso" class="form-control" min="20" max="300"
                                   value="<?= $paciente['peso_kg'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Talla (metros)</label>
                            <input type="number" step="0.01" name="talla" class="form-control" min="1.20" max="2.50"
                                   value="<?= $paciente['talla_m'] ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i> Información Adicional
                    </h2>
                    <div class="info-grid">
                        <div class="form-group">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" name="correo" class="form-control"
                                   value="<?= htmlspecialchars($paciente['correo_electronico'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Número Telefónico</label>
                            <input type="tel" name="telefono" class="form-control"
                                   value="<?= htmlspecialchars($paciente['telefono'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="window.history.back()" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>