<?php
require_once 'auth_check.php';
require_once 'conexion.php';

$is_admin = $_SESSION['user_role'] == 1;

// Validate admin role
require_odontologo();

// Get admin data
$adminName = htmlspecialchars($_SESSION['user_name']);

// Get patient count
$total_pacientes = $pdo->query("SELECT COUNT(*) FROM Paciente")->fetchColumn();

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
$odontologos = $pdo->query("SELECT id_odontologo, nombre FROM Odontologo 
                           WHERE nombre LIKE 'Dra.%' OR nombre LIKE 'Dr.%' OR nombre LIKE 'PCD.%'
                           ORDER BY nombre")->fetchAll();

// Base query for appointments
$query = "SELECT c.*, 
    COALESCE(p.nombre, c.nombre_paciente_temp) as nombre_paciente, 
    o.nombre as nombre_odontologo,
    m.nombre as nombre_motivo
    FROM citas c
    LEFT JOIN Paciente p ON c.id_paciente = p.id_paciente
    JOIN Odontologo o ON c.id_odontologo = o.id_odontologo
    JOIN MotivoConsulta m ON c.motivo = m.id_motivo
    WHERE 1=1";

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* column sizing */
        table thead th.fecha-hora,
        table tbody td.fecha-hora {
            width: 150px;            
        }
        table thead th.paciente,
        table tbody td.paciente {
            width: 250px;           
        }
        table thead th.odontologo,
        table tbody td.odontologo {
            width: 170px;           
        }
        table thead th.acciones,
        table tbody td.acciones {
            width: 220px;          
        }
        table thead th.estado,
        table tbody td.estado {
            width: 150px;          
        }
        table thead th.motivo,
        table tbody td.motivo {
            width: 170px;          
        }

        /* specific colours */
        .btn-completar { background-color: #28a745; }   /* green */
        .btn-editar    { background-color: #fd7e14; }   /* orange */
        .btn-eliminar  { background-color: #dc3545; }   /* red  */
        
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

        /* Main Content */
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
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }

        .stat-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #3498db;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }

        th {
            background-color: #3498db;
            color: white;
            padding: 12px 15px;
            text-align: left;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-info { background-color: #3498db; }
        .badge-success { background-color: #2ecc71; }
        .badge-danger { background-color: #e74c3c; }

        .action-btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .edit-btn {
            background: #f39c12;
            color: white;
        }

        .delete-btn {
            background: #e74c3c;
            color: white;
        }

        .action-btn:hover {
            filter: brightness(0.9);
        }
        .complete-btn {
        background: #2ecc71;
        color: white;
        }
        
        /* Search and Filter Styles */
        .search-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            align-items: center;
        }

        .search-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .new-patient-btn {
            padding: 12px 20px;
            background:rgb(230, 143, 31);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .new-patient-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .filter-dropdown {
            position: relative;
            display: inline-block;
        }

        .filter-btn {
            padding: 12px 20px;
            height: 44px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .filter-btn:hover {
            background-color: #5a6268;
        }

        .active-filter {
            background-color: #17a2b8 !important;
        }

        .clear-btn {
            padding: 12px 20px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .clear-btn:hover {
            background-color: #5a6268;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
        }

        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }

        .show {
            display: block;
        }

        /* Dropdown Styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px 10px;
            font-size: 1.2rem;
        }

        .dropdown-toggle:hover {
            color: #495057;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 4px;
            z-index: 1;
            padding: 5px 0;
        }

        .dropdown:hover .dropdown-menu {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 8px 16px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            white-space: nowrap;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-item i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }

        .delete-form {
            display: block;
            width: 100%;
        }

        .delete-form button {
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            padding: 8px 16px;
            cursor: pointer;
        }
        .text-center {
        text-align: center;
        }

        .dropdown {
            position: relative;
            display: inline-block;
            text-align: left; 
        }

        .dropdown-menu {
            right: auto;
            left: 50%;
            transform: translateX(-50%);
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <h1>📋 Listado de Citas Programadas</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>📊 Citas Hoy</h3>
                    <p class="stat-number"><?= $citas_hoy ?></p>
                </div>
                <div class="stat-card">
                    <h3>👥 Pacientes Activos</h3>
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
                        <td class="actions text-center">
                            <div class="dropdown" style="display: inline-block;">
                                <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="completar_cita.php?id_cita=<?= $cita['id_cita'] ?>">
                                        <i class="fas fa-check"></i> Completar
                                    </a>
                                    <a class="dropdown-item" href="editar_cita.php?id=<?= $cita['id_cita'] ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <form method="POST" action="eliminar_cita.php" class="delete-form">
                                        <input type="hidden" name="id" value="<?= $cita['id_cita'] ?>">
                                        <button type="submit" class="dropdown-item" onclick="return confirm('¿Confirmar eliminación permanente?')">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>