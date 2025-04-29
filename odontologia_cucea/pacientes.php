<?php
// Database configuration
require_once 'conexion.php';
require_once 'auth_check.php';
require_odontologo();

$is_admin = $_SESSION['user_role'] == 1;

// Handle search
$search = '';
if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%'.trim($_GET['search']).'%';
}

// Handle odontologo filter
$odontologo_filter = isset($_GET['odontologo']) ? intval($_GET['odontologo']) : 0;

// Get all odontologos for the filter dropdown
try {
    $odontologos = $pdo->query("SELECT id_odontologo, nombre FROM Odontologo 
                           WHERE nombre LIKE 'Dra.%' OR nombre LIKE 'Dr.%' OR nombre LIKE 'PCD.%'
                           ORDER BY nombre")->fetchAll();
} catch(PDOException $e) {
    die("Error al cargar odontólogos: " . $e->getMessage());
}

// Base query
$query = "
    SELECT 
        p.id_paciente,
        p.nombre,
        p.edad,
        p.codigo,
        p.peso_kg,
        p.talla_m,
        p.imc,
        p.enfermedad_cronica,
        c.fecha as fecha_consulta,
        m.nombre as motivo,
        d.nombre as diagnostico,
        pt.nombre as plan_tratamiento,
        o.id_odontologo,
        o.nombre as odontologo,
        c.pago,
        c.comentarios as comentarios_consulta,
        ce.nombre as carrera_empleo,
        s.numero as semestre,
        sx.genero as sexo,
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
    LEFT JOIN Consulta c ON p.id_paciente = c.id_paciente
    LEFT JOIN MotivoConsulta m ON c.id_motivo = m.id_motivo
    LEFT JOIN Diagnostico d ON c.id_diagnostico = d.id_diagnostico
    LEFT JOIN PlanTratamiento pt ON c.id_plan = pt.id_plan
    LEFT JOIN Odontologo o ON c.id_odontologo = o.id_odontologo
    LEFT JOIN CarreraEmpleo ce ON p.id_carrera = ce.id_carrera
    LEFT JOIN Semestre s ON p.id_semestre = s.id_semestre
    LEFT JOIN Sexo sx ON p.id_sexo = sx.id_sexo
    WHERE 1=1
";

// Add search condition
if($search) {
    $query .= " AND (p.nombre LIKE :search OR p.codigo LIKE :search)";
}

// Add odontologo filter condition
if($odontologo_filter > 0) {
    $query .= " AND o.id_odontologo = :odontologo_id";
}

$query .= " ORDER BY c.fecha DESC";

// Prepare and execute query
$stmt = $pdo->prepare($query);
if($search) {
    $stmt->bindParam(':search', $search);
}
if($odontologo_filter > 0) {
    $stmt->bindParam(':odontologo_id', $odontologo_filter, PDO::PARAM_INT);
}
$stmt->execute();
$pacientes = $stmt->fetchAll();

// Helper function for IMC badge classes
function getImcBadgeClass($resultado) {
    $resultado = strtolower($resultado);
    if (strpos($resultado, 'bajo peso') !== false) return 'info'; // Bajo peso
    if (strpos($resultado, 'normal') !== false) return 'normal'; // Normal
    if (strpos($resultado, 'sobrepeso') !== false) return 'warning'; // Sobrepeso
    if (strpos($resultado, 'obesidad tipo 1') !== false) return 'danger'; // Obesidad tipo 1
    if (strpos($resultado, 'obesidad tipo 2') !== false) return 'danger'; // Obesidad tipo 2
    if (strpos($resultado, 'obesidad tipo 3') !== false) return 'danger'; // Obesidad tipo 3
    return 'info';
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Pacientes</title>
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
        }

        .search-form {
            display: flex;
            flex-grow: 1;
            gap: 10px;
        }
        
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

        .search-bar {
            width: 350px; /* Fixed width for search bar */
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .search-bar:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.3);
        }

        .search-btn {
            padding: 12px 20px;
            background:rgb(230, 143, 31);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .search-btn:hover {
            background-color: #2980b9;
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

        .filter-dropdown {
            position: relative;
            display: inline-block;
        }

        .filter-btn {
            padding: 12px 20px;
            height: 44px; /* Match other buttons height */
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

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tr:hover {
            background-color: #f1f7fd;
        }

        .text-center {
            text-align: center;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }

        .badge-normal { background-color: #2ecc71; }
        .badge-warning { background-color: #f39c12; }
        .badge-danger { background-color: #e74c3c; }
        .badge-info { background-color: #3498db; }

        .actions {
            display: flex;
            gap: 8px;
        }

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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>📋 Registro de Pacientes Odontológicos</h1>

            <div class="search-container">
                <div class="search-group">
                    <a href="index.php" class="new-patient-btn">
                        <i class="fas fa-plus"></i> Nuevo Paciente
                    </a>
                    
                    <form method="GET" class="search-form">
                        <input type="text" 
                               name="search" 
                               class="search-bar" 
                               placeholder="Buscar paciente..."
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                               id="searchInput">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        
                        <?php if(!empty($search) || $odontologo_filter > 0): ?>
                            <a href="pacientes.php" class="clear-btn">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="filter-dropdown">
                    <button type="button" class="filter-btn <?= $odontologo_filter > 0 ? 'active-filter' : '' ?>">
                        <i class="fas fa-filter"></i> 
                        <?= $odontologo_filter > 0 ? 'Filtrado' : 'Filtrar' ?>
                    </button>
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
                        <th>Fecha</th>
                        <th>Nombre</th>
                        <th>Edad</th>
                        <th>Sexo</th>
                        <th>Semestre</th>
                        <th>Motivo</th>
                        <th>Diagnóstico</th>
                        <th>Peso (kg)</th>
                        <th>Altura (m)</th>
                        <th>IMC</th>
                        <th>Resultado</th>
                        <th>Odontólogo</th>
                        <th>Pago</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pacientes as $paciente): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($paciente['fecha_consulta'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($paciente['nombre'] ?? '') ?></td>
                        <td class="text-center"><?= htmlspecialchars($paciente['edad'] ?? '') ?></td>
                        <td class="text-center"><?= htmlspecialchars($paciente['sexo'] ?? '') ?></td>
                        <td class="text-center"><?= htmlspecialchars($paciente['semestre'] ?? '') ?></td>
                        <td><?= htmlspecialchars($paciente['motivo'] ?? 'No especificado') ?></td>
                        <td><?= htmlspecialchars($paciente['diagnostico'] ?? 'No especificado') ?></td>
                        <td class="text-center"><?= number_format($paciente['peso_kg'] ?? 0, 1) ?></td>
                        <td class="text-center"><?= number_format($paciente['talla_m'] ?? 0, 2) ?></td>
                        <td class="text-center"><?= number_format($paciente['imc'] ?? 0, 1) ?></td>
                        <td class="text-center">
                            <?php if (!empty($paciente['resultado_imc'])): ?>
                                <span class="badge badge-<?= getImcBadgeClass($paciente['resultado_imc']) ?>">
                                    <?= htmlspecialchars($paciente['resultado_imc']) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($paciente['odontologo'] ?? 'No asignado') ?></td>
                        <td class="text-center"><?= number_format($paciente['pago'] ?? 0, 2) ?></td>
                        <td class="actions">
                            <div class="dropdown">
                                <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="perfil_paciente.php?id_paciente=<?= $paciente['id_paciente'] ?>">
                                        <i class="fas fa-user"></i> Ver Perfil
                                    </a>
                                    <a class="dropdown-item" href="editar_paciente.php?id=<?= $paciente['id_paciente'] ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <?php if ($is_admin): ?>
                                        <form method="POST" action="eliminar_paciente.php" class="delete-form">
                                            <input type="hidden" name="id" value="<?= $paciente['id_paciente'] ?>">
                                            <button type="submit" class="dropdown-item" onclick="return confirm('¿Confirmar eliminación?')">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    <?php endif; ?>
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
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // Toggle dropdown when clicking the filter button
        document.querySelector('.filter-btn').addEventListener('click', function() {
            document.getElementById('odontologoDropdown').classList.toggle('show');
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