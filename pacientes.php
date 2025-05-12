<?php
require_once 'conexion.php';
require_once 'auth_check.php';
require_odontologo();

$is_admin = $_SESSION['user_role'] == 1;

// Pagination settings
$items_per_page = isset($_GET['items_per_page']) ? intval($_GET['items_per_page']) : 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Handle search
$search = '';
if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%'.trim($_GET['search']).'%';
}

// Handle filters
$odontologo_filter = isset($_GET['odontologo']) ? intval($_GET['odontologo']) : 0;
$show_incomplete = isset($_GET['show_incomplete']) ? 1 : 0;

// Get all odontologos
try {
    $odontologos = $pdo->query("SELECT id_odontologo, nombre FROM odontologo 
                           WHERE nombre LIKE 'Dra.%' OR nombre LIKE 'Dr.%' OR nombre LIKE 'PCD.%'
                           ORDER BY nombre")->fetchAll();
} catch(PDOException $e) {
    die("Error al cargar odontólogos: " . $e->getMessage());
}

// Base query with proper joins
$base_query = "
    FROM paciente p
    LEFT JOIN (
        SELECT id_paciente, MAX(fecha) as ultima_fecha 
        FROM consulta 
        GROUP BY id_paciente
    ) c ON p.id_paciente = c.id_paciente
    LEFT JOIN consulta c2 ON c.id_paciente = c2.id_paciente AND c.ultima_fecha = c2.fecha
    LEFT JOIN motivoconsulta m ON c2.id_motivo = m.id_motivo
    LEFT JOIN diagnostico d ON c2.id_diagnostico = d.id_diagnostico
    LEFT JOIN plantratamiento pt ON c2.id_plan = pt.id_plan
    LEFT JOIN odontologo o ON c2.id_odontologo = o.id_odontologo
    LEFT JOIN carreraempleo ce ON p.id_carrera = ce.id_carrera
    LEFT JOIN semestre s ON p.id_semestre = s.id_semestre
    LEFT JOIN sexo sx ON p.id_sexo = sx.id_sexo
    WHERE 1=1
";

// Add search condition
if($search) {
    $base_query .= " AND (p.nombre LIKE :search OR p.codigo LIKE :search)";
}

// Add odontologo filter
if($odontologo_filter > 0) {
    $base_query .= " AND o.id_odontologo = :odontologo_id";
}

// Add completeness filter
if(!$show_incomplete) {
    $base_query .= " AND p.edad IS NOT NULL
               AND p.id_sexo IS NOT NULL
               AND p.peso_kg IS NOT NULL
               AND p.talla_m IS NOT NULL
               AND p.id_carrera IS NOT NULL
               AND p.id_semestre IS NOT NULL";
} else {
    $base_query .= " AND (p.edad IS NULL
                OR p.id_sexo IS NULL
                OR p.peso_kg IS NULL
                OR p.talla_m IS NULL
                OR p.id_carrera IS NULL
                OR p.id_semestre IS NULL)";
}

// Get total number of records
$count_query = "SELECT COUNT(*) as total $base_query";
$count_stmt = $pdo->prepare($count_query);

if($search) {
    $count_stmt->bindParam(':search', $search);
}
if($odontologo_filter > 0) {
    $count_stmt->bindParam(':odontologo_id', $odontologo_filter, PDO::PARAM_INT);
}

$count_stmt->execute();
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $items_per_page);

// Main query with pagination
$main_query = "
    SELECT 
        p.id_paciente,
        p.nombre,
        p.edad,
        p.codigo,
        p.peso_kg,
        p.talla_m,
        p.imc,
        p.enfermedad_cronica,
        c2.fecha as fecha_consulta,
        m.nombre as motivo,
        d.nombre as diagnostico,
        pt.nombre as plan_tratamiento,
        o.id_odontologo,
        o.nombre as odontologo,
        c2.pago,
        c2.comentarios as comentarios_consulta,
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
    $base_query
    ORDER BY c2.fecha DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($main_query);

// Bind parameters
if($search) {
    $stmt->bindParam(':search', $search);
}
if($odontologo_filter > 0) {
    $stmt->bindParam(':odontologo_id', $odontologo_filter, PDO::PARAM_INT);
}

$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$pacientes = $stmt->fetchAll();

function getImcBadgeClass($resultado) {
    $resultado = strtolower($resultado);
    if (strpos($resultado, 'bajo peso') !== false) return 'info';
    if (strpos($resultado, 'normal') !== false) return 'normal';
    if (strpos($resultado, 'sobrepeso') !== false) return 'warning';
    if (strpos($resultado, 'obesidad tipo 1') !== false) return 'danger';
    if (strpos($resultado, 'obesidad tipo 2') !== false) return 'danger';
    if (strpos($resultado, 'obesidad tipo 3') !== false) return 'danger';
    return 'info';
}

function buildPaginationLink($page, $params) {
    $params['page'] = $page;
    return 'pacientes.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Pacientes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/pacientes.module.css?v=1.2">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.1">
</head>
<body>
    <button class="mobile-menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include 'sidebar.php'; ?>

    <div class="main-content" id="mainContent">
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
                    </form>
                </div>
                
                <div class="filter-dropdown">
                    <button type="button" class="filter-btn <?= $odontologo_filter > 0 ? 'active-filter' : '' ?>">
                        <i class="fas fa-filter"></i> 
                        <?= $odontologo_filter > 0 ? 'Filtrado' : 'Filtrar' ?>
                    </button>
                    <div class="dropdown-content" id="odontologoDropdown">
                        <a href="?<?= http_build_query(array_merge($_GET, ['odontologo' => 0, 'page' => 1])) ?>">
                            Todos los odontólogos
                        </a>
                        <?php foreach($odontologos as $odontologo): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['odontologo' => $odontologo['id_odontologo'], 'page' => 1])) ?>">
                                <?= htmlspecialchars($odontologo['nombre']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="filter-btn <?= $show_incomplete ? 'active-filter' : '' ?>" 
                            onclick="toggleIncomplete()" style="margin-left: 10px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= $show_incomplete ? 'Ocultar incompletos' : 'Mostrar incompletos' ?>
                    </button>

                    <?php if(!empty($search) || $odontologo_filter > 0 || $show_incomplete): ?>
                        <a href="pacientes.php" class="clear-btn">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Nombre</th>
                            <th>Edad</th>
                            <th>Sexo</th>
                            <th>Motivo</th>
                            <th>Diagnóstico</th>
                            <th>Peso (kg)</th>
                            <th>Altura (m)</th>
                            <th>IMC</th>
                            <th>Resultado</th>
                            <th>Odontólogo</th>
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
                            <td class="actions">
                                <div class="action-buttons">
                                    <a href="perfil_paciente.php?id_paciente=<?= $paciente['id_paciente'] ?>" class="action-btn btn-view">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <a href="editar_paciente.php?id=<?= $paciente['id_paciente'] ?>" class="action-btn btn-edit">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <?php if ($is_admin): ?>
                                        <form method="POST" action="eliminar_paciente.php" class="form-eliminar">
                                            <input type="hidden" name="id" value="<?= $paciente['id_paciente'] ?>">
                                            <button type="submit" class="action-btn btn-delete" onclick="return confirm('¿Confirmar eliminación?')">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if($total_pages > 1 || $items_per_page != 10): ?>
            <div class="pagination-container">
                <div class="pagination-controls">
                    <!-- Items per page selector -->
                    <div class="items-per-page">
                        <span>Mostrar:</span>
                        <select id="itemsPerPage" onchange="updateItemsPerPage(this.value)">
                            <option value="10" <?= $items_per_page == 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $items_per_page == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $items_per_page == 50 ? 'selected' : '' ?>>50</option>
                        </select>
                    </div>

                    <!-- Page navigation -->
                    <div class="page-numbers">
                        <?php if($total_pages > 1): ?>
                            <a href="<?= buildPaginationLink(1, $_GET) ?>" class="page-item <?= $current_page == 1 ? 'disabled' : '' ?>">
                                Primera
                            </a>
                            <a href="<?= buildPaginationLink($current_page - 1, $_GET) ?>" class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                &laquo; Anterior
                            </a>

                            <?php 
                            $start = max(1, $current_page - 2);
                            $end = min($total_pages, $current_page + 2);
                            for($i = $start; $i <= $end; $i++): ?>
                                <a href="<?= buildPaginationLink($i, $_GET) ?>" class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <a href="<?= buildPaginationLink($current_page + 1, $_GET) ?>" class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                Siguiente &raquo;
                            </a>
                            <a href="<?= buildPaginationLink($total_pages, $_GET) ?>" class="page-item <?= $current_page == $total_pages ? 'disabled' : '' ?>">
                                Última
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="total-records">
                        Mostrando <?= ($offset + 1) ?> - <?= min($offset + $items_per_page, $total_records) ?> de <?= $total_records ?> registros
                    </div>
                </div>
            </div>
            <?php endif; ?>
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

        // Search input enter key handler
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // Toggle incomplete patients
        function toggleIncomplete() {
            const url = new URL(window.location.href);
            if(url.searchParams.has('show_incomplete')) {
                url.searchParams.delete('show_incomplete');
            } else {
                url.searchParams.set('show_incomplete', '1');
            }
            url.searchParams.set('page', '1'); // Reset to first page
            window.location.href = url.toString();
        }

        // Update items per page
        function updateItemsPerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('items_per_page', value);
            url.searchParams.set('page', '1'); // Reset to first page
            window.location.href = url.toString();
        }
    </script>
</body>
</html>