<?php
require_once 'auth_check.php';
require_once 'conexion.php';
date_default_timezone_set('America/Mexico_City');

$is_admin = $_SESSION['user_role'] == 1;

// Validate admin role
require_odontologo();

// Get admin data
$adminName = htmlspecialchars($_SESSION['user_name']);

// Get patient count
$total_pacientes = $pdo->query("SELECT COUNT(*) FROM paciente")->fetchColumn();

// Get appointments count for today
$stmt = $pdo->query("SELECT fecha_hora FROM citas");
$all_appointments = $stmt->fetchAll();

$citas_hoy = 0;
$today = date('Y-m-d');
foreach ($all_appointments as $appt) {
    if (date('Y-m-d', strtotime($appt['fecha_hora'])) === $today) {
        $citas_hoy++;
    }
}

// Handle odontologo filter
$odontologo_filter = isset($_GET['odontologo']) ? intval($_GET['odontologo']) : 0;

// Get filtered odontologos for the dropdown
$odontologos = $pdo->query("SELECT id_odontologo, nombre FROM odontologo 
                           WHERE nombre LIKE 'Dra.%' OR nombre LIKE 'Dr.%' OR nombre LIKE 'PCD.%'
                           ORDER BY nombre")->fetchAll();

// Base query for appointments
$query = "SELECT c.*, 
    COALESCE(p.nombre, c.nombre_paciente_temp) as nombre_paciente, 
    o.nombre as nombre_odontologo,
    m.nombre as nombre_motivo
    FROM citas c
    LEFT JOIN paciente p ON c.id_paciente = p.id_paciente
    JOIN odontologo o ON c.id_odontologo = o.id_odontologo
    JOIN motivoconsulta m ON c.motivo = m.id_motivo
   WHERE c.estado != 'completada'";

// Add odontologo filter condition
if($odontologo_filter > 0) {
    $query .= " AND o.id_odontologo = :odontologo_id";
}

$query .= " ORDER BY c.fecha_hora DESC";

// Prepare and execute query
$stmt = $pdo->prepare($query);
if($odontologo_filter > 0) {
    $stmt->bindParam(':odontologo_id', $odontologo_filter, PDO::PARAM_INT);
}
$stmt->execute();
$citas = $stmt->fetchAll();

function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'programada': return 'info';
        case 'completada': return 'success';
        case 'cancelada': return 'danger';
        default: return 'info';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración Odontológica</title>
    <link rel="stylesheet" href="css/admin.module.css?v=1.2">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">    
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
            <h1>📋 Listado de Citas Programadas</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>📊 Citas Hoy</h3>
                    <p class="stat-number"><?= $citas_hoy ?></p>
                </div>
                <div class="stat-card">
                    <h3>👥 Pacientes Atendidos</h3>
                    <p class="stat-number"><?= $total_pacientes ?></p>
                </div>
            </div>

            <div class="search-container">
                <div class="search-group">
                    <a href="nueva_cita.php" class="new-patient-btn">
                        <i class="fas fa-plus"></i> Nueva Cita
                    </a>
                </div>
                
                <div class="filter-dropdown">
                    <button type="button" class="filter-btn <?= $odontologo_filter > 0 ? 'active-filter' : '' ?>">
                        <i class="fas fa-filter"></i> 
                        <?= $odontologo_filter > 0 ? 'Filtrado' : 'Filtrar' ?>
                    </button>
                    <?php if($odontologo_filter > 0): ?>
                        <a href="admin.php" class="clear-btn">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                    <div class="dropdown-content" id="odontologoDropdown">
                        <a href="?<?= http_build_query(array_merge($_GET, ['odontologo' => 0])) ?>">
                            Todos los odontólogos
                        </a>
                        <?php foreach($odontologos as $odontologo): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['odontologo' => $odontologo['id_odontologo']])) ?>">
                                <?= htmlspecialchars($odontologo['nombre']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="fecha-hora">Fecha/Hora</th>
                        <th class="paciente">Paciente</th>
                        <th class="odontologo">Odontólogo</th>
                        <th class="motivo">Motivo</th>
                        <th class="estado">Estado</th>
                        <th class="acciones text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($citas as $cita): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($cita['fecha_hora'])) ?></td>
                        <td><?= htmlspecialchars($cita['nombre_paciente'] ?? 'Sin paciente asignado') ?></td>
                        <td><?= htmlspecialchars($cita['nombre_odontologo']) ?></td>
                        <td><?= htmlspecialchars($cita['nombre_motivo']) ?></td>
                        <td>
                            <span class="badge badge-<?= getStatusBadgeClass($cita['estado']) ?>">
                                <?= ucfirst($cita['estado']) ?>
                            </span>
                        </td>
                        <td class="actions">
                            <div class="action-buttons">
                                <a href="completar_cita.php?id_cita=<?= $cita['id_cita'] ?>" class="action-btn btn-completar">
                                    <i class="fas fa-check"></i> Completar
                                </a>
                                <a href="editar_cita.php?id=<?= $cita['id_cita'] ?>" class="action-btn btn-editar">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <form method="POST" action="eliminar_cita.php" class="form-eliminar">
                                    <input type="hidden" name="id" value="<?= $cita['id_cita'] ?>">
                                    <button type="submit" class="action-btn btn-eliminar" onclick="return confirm('¿Confirmar eliminación permanente?')">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

        // Toggle dropdown when clicking the filter button
        document.querySelector('.filter-btn')?.addEventListener('click', function() {
            document.getElementById('odontologoDropdown')?.classList.toggle('show');
        });

        // Close the dropdown if clicked outside
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.filter-btn')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
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