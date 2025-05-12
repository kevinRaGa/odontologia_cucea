<?php
require_once 'auth_check.php';
require_once 'conexion.php';

$is_admin = $_SESSION['user_role'] == 1;

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
        FROM paciente p
        LEFT JOIN carreraempleo c ON p.id_carrera = c.id_carrera
        LEFT JOIN semestre s ON p.id_semestre = s.id_semestre
        LEFT JOIN sexo sx ON p.id_sexo = sx.id_sexo
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
    FROM paciente p
    LEFT JOIN carreraempleo c ON p.id_carrera = c.id_carrera
    LEFT JOIN semestre s ON p.id_semestre = s.id_semestre
    LEFT JOIN sexo sx ON p.id_sexo = sx.id_sexo
    WHERE p.id_paciente = ?
    ");
    $stmt->execute([$id_paciente]);
    $paciente = $stmt->fetch();

    // NEW functionality, Citas to see if it is working
    $stmtCitas = $pdo->prepare("
        SELECT c.id_cita, c.fecha_hora, c.estado, 
               m.nombre as motivo_nombre, 
               o.nombre as odontologo_nombre 
        FROM citas c 
        LEFT JOIN motivoconsulta m ON c.motivo = m.id_motivo 
        LEFT JOIN odontologo o ON c.id_odontologo = o.id_odontologo 
        WHERE c.id_paciente = ? 
        ORDER BY c.fecha_hora DESC
    ");
    $stmtCitas->execute([$id_paciente]);
    $citas = $stmtCitas->fetchAll();

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
    <link rel="stylesheet" href="css/perfil_paciente.module.css?v=1.1">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.1">
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
                <i class="fas fa-user"></i>
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
                            <?php if ($paciente['imc'] !== null): ?>
                                <?= number_format($paciente['imc'], 1) ?>
                                <span class="badge badge-<?= getImcBadgeClass($paciente['resultado_imc']) ?>">
                                    <?= htmlspecialchars($paciente['resultado_imc']) ?>
                                </span>
                            <?php else: ?>
                                <span class="no-data">No calculado</span>
                            <?php endif; ?>
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
                                <td><?= htmlspecialchars($cita['motivo_nombre']) ?></td>
                                <td><?= htmlspecialchars($cita['odontologo_nombre']) ?></td>
                                <td>
                                    <span class="badge badge-<?= 
                                        $cita['estado'] == 'completada' ? 'success' : 
                                        ($cita['estado'] == 'cancelada' ? 'danger' : 'info') 
                                    ?>">
                                        <?= ucfirst($cita['estado']) ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <?php if ($is_admin): ?>
                                        <a href="editar_cita.php?id=<?= $cita['id_cita'] ?>" class="action-btn edit-btn-small">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                    <?php endif; ?>
                                    <a href="ver_cita.php?id=<?= $cita['id_cita'] ?>" class="action-btn view-btn">
                                        <i class="fas fa-eye"></i> Ver
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

    <script>
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