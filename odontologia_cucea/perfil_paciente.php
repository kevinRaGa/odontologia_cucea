<?php
require_once 'auth_check.php';
require_once 'conexion.php';

// Verify patient ID exists
if(!isset($_GET['id_paciente'])) {
    header("Location: pacientes.php");
    exit();
}

$id_paciente = intval($_GET['id_paciente']);

// Get patient information
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

    // Calculate IMC
    // Function to get badge class for IMC
    function getImcBadgeClass($resultado) {
    $resultado = strtolower($resultado);
    if (strpos($resultado, 'bajo peso') !== false) return 'info'; // Bajo peso
    if (strpos($resultado, 'normal') !== false) return 'normal'; // Normal
    if (strpos($resultado, 'sobrepeso') !== false) return 'warning'; // Sobrepeso
    if (strpos($resultado, 'obesidad tipo 1') !== false) return 'danger'; // Obesidad tipo 1
    if (strpos($resultado, 'obesidad tipo 2') !== false) return 'danger'; // Obesidad tipo 2
    if (strpos($resultado, 'obesidad tipo 3') !== false) return 'danger'; // Obesidad tipo 3
    return 'info'; // Default class
}

    // Get all appointments
    // Fetch patient details with IMC calculation and classification
    $stmt = $pdo->prepare("
    SELECT p.*, 
        c.nombre as carrera_nombre, 
        s.numero as semestre_numero, 
        sx.genero as sexo_nombre,
        CASE
            WHEN p.imc < 18.5 THEN 'Bajo peso'
            WHEN p.imc BETWEEN 18.5 AND 24.9 THEN 'Normal'
            WHEN p.imc BETWEEN 25 AND 29.9 THEN 'Sobrepeso'
            WHEN p.imc BETWEEN 30 AND 34.9 THEN 'Obesidad tipo 1'
            WHEN p.imc BETWEEN 35 AND 39.9 THEN 'Obesidad tipo 2'
            WHEN p.imc >= 40 THEN 'Obesidad tipo 3'
            ELSE 'No calculado'
        END as resultado_imc
    FROM Paciente p
    LEFT JOIN CarreraEmpleo c ON p.id_carrera = c.id_carrera
    LEFT JOIN Semestre s ON p.id_semestre = s.id_semestre
    LEFT JOIN Sexo sx ON p.id_sexo = sx.id_sexo
    WHERE p.id_paciente = ?
    ");
    $stmt->execute([$id_paciente]);
    $paciente = $stmt->fetch();

} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil del Paciente</title>
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

        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #3498db;
        }

        .info-label {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .info-value {
            color: #333;
            font-size: 1.1rem;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            margin-top: 5px;
        }

        .badge-info { background-color: #3498db; }
        .badge-success { background-color: #2ecc71; }
        .badge-warning { background-color: #f39c12; }
        .badge-danger { background-color: #e74c3c; }

        .edit-btn {
            padding: 8px 15px;
            background: #f39c12;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .edit-btn:hover {
            filter: brightness(0.9);
            transform: translateY(-1px);
        }

        .appointments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .appointments-table th {
            background-color: #3498db;
            color: white;
            padding: 12px 15px;
            text-align: left;
        }

        .appointments-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }

        .appointments-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .appointments-table tr:hover {
            background-color: #f1f7fd;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .view-btn {
            background: #3498db;
            color: white;
        }

        .edit-btn-small {
            background: #f39c12;
            color: white;
        }

        .action-btn:hover {
            filter: brightness(0.9);
            transform: translateY(-1px);
        }

        .no-data {
            color: #777;
            font-style: italic;
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
            text-decoration: none;
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
                Perfil del Paciente: <?= htmlspecialchars($paciente['nombre']) ?>
            </h1>

            <!-- Basic Information Section -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-id-card"></i> Información Básica
                </h2>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Nombre Completo</div>
                        <div class="info-value"><?= htmlspecialchars($paciente['nombre']) ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Edad</div>
                        <div class="info-value"><?= $paciente['edad'] ?? '<span class="no-data">No especificado</span>' ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Código</div>
                        <div class="info-value"><?= $paciente['codigo'] ? htmlspecialchars($paciente['codigo']) : '<span class="no-data">No especificado</span>' ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Carrera/Empleo</div>
                        <div class="info-value"><?= $paciente['carrera_nombre'] ?? '<span class="no-data">No especificado</span>' ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Semestre</div>
                        <div class="info-value"><?= $paciente['semestre_numero'] ?? '<span class="no-data">No especificado</span>' ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Sexo</div>
                        <div class="info-value"><?= $paciente['sexo_nombre'] ?? '<span class="no-data">No especificado</span>' ?></div>
                    </div>
                </div>
            </div>

            <!-- Health Information Section -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-heartbeat"></i> Información de Salud
                </h2>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Peso (kg)</div>
                        <div class="info-value"><?= $paciente['peso_kg'] ? number_format($paciente['peso_kg'], 1) : '<span class="no-data">No registrado</span>' ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Talla (m)</div>
                        <div class="info-value"><?= $paciente['talla_m'] ? number_format($paciente['talla_m'], 2) : '<span class="no-data">No registrada</span>' ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">IMC</div>
                        <div class="info-value">
                            <?= number_format($paciente['imc'], 1) ?> 
                            <span class="badge badge-<?= getImcBadgeClass($paciente['resultado_imc']) ?>">
                                <?= htmlspecialchars($paciente['resultado_imc']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Enfermedad Crónica</div>
                        <div class="info-value"><?= $paciente['enfermedad_cronica'] ? htmlspecialchars($paciente['enfermedad_cronica']) : '<span class="no-data">No registrada</span>' ?></div>
                    </div>
                </div>
            </div>

            <!-- Additional Information Section -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i> Información Adicional
                </h2>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Correo Electrónico</div>
                        <div class="info-value"><?= $paciente['correo_electronico'] ? htmlspecialchars($paciente['correo_electronico']) : '<span class="no-data">No registrado</span>' ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Número Telefónico</div>
                        <div class="info-value"><?= $paciente['telefono'] ? htmlspecialchars($paciente['telefono']) : '<span class="no-data">No registrado</span>' ?></div>
                    </div>
                </div>
            </div>

            <!-- Appointments Section -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fas fa-calendar-alt"></i> Citas del Paciente
                </h2>
                <?php if(!empty($citas)): ?>
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Motivo</th>
                                <th>Odontólogo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($citas as $cita): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($cita['fecha_hora'])) ?></td>
                                <td><?= htmlspecialchars($cita['motivo_nombre'] ?? 'No especificado') ?></td>
                                <td><?= htmlspecialchars($cita['odontologo_nombre'] ?? 'No asignado') ?></td>
                                <td>
                                    <span class="badge badge-<?= 
                                        $cita['estado'] == 'Completada' ? 'success' : 
                                        ($cita['estado'] == 'Cancelada' ? 'danger' : 'info') 
                                    ?>">
                                        <?= htmlspecialchars($cita['estado_nombre']) ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="ver_cita.php?id=<?= $cita['id_cita'] ?>" class="action-btn view-btn">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <a href="editar_cita.php?id=<?= $cita['id_cita'] ?>" class="action-btn edit-btn-small">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">El paciente no tiene citas registradas.</p>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="button" onclick="window.history.back()" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
                <a href="editar_paciente.php?id=<?= $id_paciente ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Editar Perfil
                </a>
            </div>
        </div>
    </div>
</body>
</html>