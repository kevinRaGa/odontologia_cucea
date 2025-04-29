<?php
require_once 'conexion.php';
require_once 'auth_check.php';
require_odontologo();

if(!isset($_GET['id_paciente'])) {
    die("Error: No se especificó el paciente");
}

$id_paciente = intval($_GET['id_paciente']);

try {
    $diagnosticos = $pdo->query("SELECT * FROM Diagnostico ORDER BY nombre")->fetchAll();
    $planes = $pdo->query("SELECT * FROM PlanTratamiento ORDER BY nombre")->fetchAll();
    $odontologos = $pdo->query("SELECT * FROM Odontologo ORDER BY nombre")->fetchAll();
    $motivos = $pdo->query("SELECT * FROM MotivoConsulta ORDER BY nombre")->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM Consulta WHERE id_paciente = ? ORDER BY fecha DESC LIMIT 1");
    $stmt->execute([$id_paciente]);
    $consulta = $stmt->fetch();
    
} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if(empty($_POST['odontologo'])) {
            throw new Exception("Odontólogo es un campo obligatorio");
        }

        $stmt = $pdo->prepare("UPDATE Consulta SET
            id_motivo = ?,
            id_diagnostico = ?,
            id_plan = ?,
            id_odontologo = ?,
            comentarios = ?,
            pago = ?
            WHERE id_consulta = ?");
        
        $stmt->execute([
            !empty($_POST['motivo']) ? intval($_POST['motivo']) : NULL,
            !empty($_POST['diagnostico']) ? intval($_POST['diagnostico']) : NULL,
            !empty($_POST['plan']) ? intval($_POST['plan']) : NULL,
            intval($_POST['odontologo']),
            !empty($_POST['comentarios']) ? htmlspecialchars($_POST['comentarios']) : NULL,
            !empty($_POST['pago']) ? floatval($_POST['pago']) : 0.00,
            $consulta['id_consulta']
        ]);
        
        header("Location: pacientes.php");
        exit();
        
    } catch(PDOException $e) {
        $error = "Error al registrar: " . $e->getMessage();
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Odontológico</title>
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

        .patient-info {
            background: #e8f4fc;
            border-left: 4px solid #3498db;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .patient-info h3 {
            color: #2c3e50;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .patient-info p {
            color: #495057;
            display: flex;
            align-items: center;
            gap: 8px;
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

        .required:after {
            content: " *";
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <i class="fas fa-file-medical"></i>
                Registro Odontológico
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <div class="patient-info">
                <h3><i class="fas fa-user"></i> Paciente #<?= $id_paciente ?></h3>
                <p><i class="fas fa-calendar-day"></i> Consulta realizada: <?= date('d/m/Y H:i', strtotime($consulta['fecha'])) ?></p>
            </div>

            <form method="POST">
                <div class="info-grid">
                    <div class="form-group">
                        <label class="form-label">Motivo de la consulta:</label>
                        <select name="motivo" class="form-control">
                            <option value="">Seleccione un motivo</option>
                            <?php foreach($motivos as $m): ?>
                                <option value="<?= $m['id_motivo'] ?>" 
                                    <?= ($consulta['id_motivo'] == $m['id_motivo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Odontólogo que atendió:</label>
                        <select name="odontologo" class="form-control" required>
                            <option value="">Seleccione un odontólogo</option>
                            <?php foreach($odontologos as $o): ?>
                                <option value="<?= $o['id_odontologo'] ?>" <?= ($consulta['id_odontologo'] == $o['id_odontologo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($o['nombre']) ?> 
                                    <?= !empty($o['especialidad']) ? '('.htmlspecialchars($o['especialidad']).')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Diagnóstico:</label>
                        <select name="diagnostico" class="form-control">
                            <option value="">Seleccione un diagnóstico</option>
                            <?php foreach($diagnosticos as $d): ?>
                                <option value="<?= $d['id_diagnostico'] ?>" <?= ($consulta['id_diagnostico'] == $d['id_diagnostico']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Plan de tratamiento:</label>
                        <select name="plan" class="form-control">
                            <option value="">Seleccione un plan</option>
                            <?php foreach($planes as $p): ?>
                                <option value="<?= $p['id_plan'] ?>" <?= ($consulta['id_plan'] == $p['id_plan']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Monto del pago:</label>
                        <input type="number" step="0.01" min="0" name="pago" class="form-control" 
                               value="<?= htmlspecialchars($consulta['pago'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Comentarios adicionales:</label>
                        <textarea name="comentarios" class="form-control"><?= htmlspecialchars($consulta['comentarios'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Información
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>