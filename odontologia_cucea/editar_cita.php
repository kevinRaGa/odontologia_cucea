<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_odontologo();

// Get appointment data if editing
$cita = [];
if(isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COALESCE(p.nombre, c.nombre_paciente_temp) as nombre_paciente
        FROM citas c 
        LEFT JOIN Paciente p ON c.id_paciente = p.id_paciente 
        WHERE c.id_cita = ?
    ");
    $stmt->execute([intval($_GET['id'])]);
    $cita = $stmt->fetch();
    
    if(!$cita) {
        header("Location: admin.php");
        exit();
    }
}

// Get options for dropdowns
try {
    $odontologos = $pdo->query("SELECT * FROM Odontologo")->fetchAll();
    $motivos = $pdo->query("SELECT * FROM MotivoConsulta")->fetchAll();
} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

// Process form
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    try {
        $pdo->beginTransaction();
        
        // Validation
        $required = ['fecha_hora', 'motivo', 'odontologo', 'nombre_paciente'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Update appointment - we'll keep the existing patient ID if there was one
        $stmt = $pdo->prepare("UPDATE citas SET
            nombre_paciente_temp = ?,
            id_odontologo = ?,
            fecha_hora = ?,
            motivo = ?,
            estado = ?,
            comentarios = ?
            WHERE id_cita = ?");
        
        $stmt->execute([
            htmlspecialchars(trim($_POST['nombre_paciente'])),
            intval($_POST['odontologo']),
            $_POST['fecha_hora'],
            intval($_POST['motivo']),
            $_POST['estado'] ?? 'programada',
            !empty($_POST['comentarios']) ? htmlspecialchars($_POST['comentarios']) : null,
            intval($_POST['id'])
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
    <title>Editar Cita Odontológica</title>
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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <i class="fas fa-calendar-edit"></i>
                Editar Cita Odontológica
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="id" value="<?= $cita['id_cita'] ?>">

                <div class="info-grid">
                    <div class="form-group">
                        <label class="form-label required">Nombre del Paciente:</label>
                        <input type="text" name="nombre_paciente" class="form-control" required
                               value="<?= htmlspecialchars($cita['nombre_paciente'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Odontólogo:</label>
                        <select name="odontologo" class="form-control" required>
                            <?php foreach($odontologos as $o): ?>
                                <option value="<?= $o['id_odontologo'] ?>"
                                    <?= ($cita['id_odontologo'] == $o['id_odontologo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($o['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Fecha y Hora:</label>
                        <input type="datetime-local" name="fecha_hora" class="form-control" required
                               value="<?= date('Y-m-d\TH:i', strtotime($cita['fecha_hora'])) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Motivo:</label>
                        <select name="motivo" class="form-control" required>
                            <?php foreach($motivos as $m): ?>
                                <option value="<?= $m['id_motivo'] ?>"
                                    <?= ($cita['motivo'] == $m['id_motivo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado:</label>
                        <select name="estado" class="form-control">
                            <option value="programada" <?= ($cita['estado'] == 'programada') ? 'selected' : '' ?>>Programada</option>
                            <option value="completada" <?= ($cita['estado'] == 'completada') ? 'selected' : '' ?>>Completada</option>
                            <option value="cancelada" <?= ($cita['estado'] == 'cancelada') ? 'selected' : '' ?>>Cancelada</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Comentarios:</label>
                        <textarea name="comentarios" class="form-control" rows="2"><?= htmlspecialchars($cita['comentarios'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="window.history.back()" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" name="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>