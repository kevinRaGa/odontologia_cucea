<?php
require_once 'conexion.php';
require_once 'auth_check.php';
require_odontologo();

$is_admin = $_SESSION['user_role'] == 1;

if(!isset($_GET['id_paciente'])) {
    die("Error: No se especificó el paciente");
}

$id_paciente = intval($_GET['id_paciente']);

try {
    $diagnosticos = $pdo->query("SELECT * FROM diagnostico ORDER BY nombre")->fetchAll();
    $planes = $pdo->query("SELECT * FROM plantratamiento ORDER BY nombre")->fetchAll();
    $odontologos = $pdo->query("SELECT * FROM odontologo ORDER BY nombre")->fetchAll();
    $motivos = $pdo->query("SELECT * FROM motivoconsulta ORDER BY nombre")->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM consulta WHERE id_paciente = ? ORDER BY fecha DESC LIMIT 1");
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

        $stmt = $pdo->prepare("UPDATE consulta SET
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
    <link rel="stylesheet" href="css/second_Form.module.css">
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