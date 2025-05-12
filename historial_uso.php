<?php
require_once 'auth_check.php';
require_once 'conexion.php';

$is_admin = $_SESSION['user_role'] == 1;

// Handle filters
$category_filter = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$dentist_filter = isset($_GET['odontologo']) ? intval($_GET['odontologo']) : 0;
$date_from = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$date_to = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Get all categories for filter dropdown
$categories = $pdo->query("SELECT DISTINCT categoria FROM inventariodental ORDER BY categoria")->fetchAll();

// Get all dentists for dropdown
$dentists = $pdo->query("SELECT id_odontologo, nombre FROM odontologo ORDER BY nombre")->fetchAll();

// Base query
$query = "
    SELECT r.*, i.nombre as item_nombre, i.categoria, i.unidad_medida,
           o.nombre as odontologo_nombre
    FROM registrouso r
    JOIN inventariodental i ON r.id_item = i.id_item
    JOIN odontologo o ON r.id_odontologo = o.id_odontologo
    WHERE 1=1
";

// Add filters
if(!empty($category_filter)) {
    $query .= " AND i.categoria = :categoria";
}
if($dentist_filter > 0) {
    $query .= " AND r.id_odontologo = :dentist_id";
}
if(!empty($date_from)) {
    $query .= " AND r.fecha_uso >= :date_from";
}
if(!empty($date_to)) {
    $query .= " AND r.fecha_uso <= :date_to";
}

$query .= " ORDER BY r.fecha_uso DESC";

$stmt = $pdo->prepare($query);

if(!empty($category_filter)) {
    $stmt->bindParam(':categoria', $category_filter);
}
if($dentist_filter > 0) {
    $stmt->bindParam(':dentist_id', $dentist_filter, PDO::PARAM_INT);
}
if(!empty($date_from)) {
    $stmt->bindParam(':date_from', $date_from);
}
if(!empty($date_to)) {
    $stmt->bindParam(':date_to', $date_to);
}

$stmt->execute();
$usage_records = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Uso - Inventario Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/historial_uso.module.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <i class="fas fa-history"></i> Historial de Uso de Inventario
            </h1>

            <form method="GET" action="historial_uso.php" id="filterForm">
                <div class="filter-container">
                    <div class="filter-group">
                        <label class="filter-label">Categoría</label>
                        <select name="categoria" class="filter-control">
                            <option value="">Todas las categorías</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?= $category['categoria'] ?>" <?= $category_filter == $category['categoria'] ? 'selected' : '' ?>>
                                    <?= ucfirst($category['categoria']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Odontólogo</label>
                        <select name="odontologo" class="filter-control">
                            <option value="0">Todos los odontólogos</option>
                            <?php foreach($dentists as $dentist): ?>
                                <option value="<?= $dentist['id_odontologo'] ?>" <?= $dentist_filter == $dentist['id_odontologo'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dentist['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Fecha Desde</label>
                        <input type="date" name="fecha_desde" class="filter-control" value="<?= htmlspecialchars($date_from) ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" class="filter-control" value="<?= htmlspecialchars($date_to) ?>">
                    </div>

                    <div class="filter-actions">
                        <button type="button" onclick="exportToExcel()" class="btn btn-export">
                            <i class="fas fa-file-export"></i> Exportar a Excel
                        </button>
                    </div>
                </div>
            </>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Categoría</th>
                            <th>Item</th>
                            <th>Cantidad</th>
                            <th>Odontólogo</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($usage_records)): ?>
                            <tr>
                                <td colspan="6" class="no-records">No se encontraron registros de uso</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($usage_records as $record): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($record['fecha_uso'])) ?></td>
                                    <td><?= ucfirst($record['categoria']) ?></td>
                                    <td><?= htmlspecialchars($record['item_nombre']) ?></td>
                                    <td><?= number_format($record['cantidad_usada'], 2) ?> <?= $record['unidad_medida'] ?></td>
                                    <td><?= htmlspecialchars($record['odontologo_nombre']) ?></td>
                                    <td><?= !empty($record['nota']) ? htmlspecialchars($record['nota']) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    function exportToExcel() {
        // Get the current form data
        const form = document.getElementById('filterForm');
        const formData = new FormData(form);
        
        // Convert to URL parameters
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            if (value) params.append(key, value);
        }
        
        // Open export with current filters
        window.location.href = 'exportar_historial.php?' + params.toString();
    }
    </script>
</body>
</html>