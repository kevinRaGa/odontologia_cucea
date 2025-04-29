<?php
require_once 'auth_check.php';
require_once 'conexion.php';

// Debug function (remove in production)
function logDebug($data) {
    file_put_contents('export_debug.log', print_r($data, true) . PHP_EOL, FILE_APPEND);
}

// Initialize debug log
logDebug(['REQUEST' => $_GET, 'TIME' => date('Y-m-d H:i:s')]);

// Get and validate filters
$filters = [
    'categoria' => $_GET['categoria'] ?? '',
    'odontologo' => isset($_GET['odontologo']) ? (int)$_GET['odontologo'] : 0,
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? ''
];

// Validate dates
if ($filters['fecha_desde'] && !DateTime::createFromFormat('Y-m-d', $filters['fecha_desde'])) {
    die("Fecha desde no válida");
}
if ($filters['fecha_hasta'] && !DateTime::createFromFormat('Y-m-d', $filters['fecha_hasta'])) {
    die("Fecha hasta no válida");
}

// Base query with JOINs
$query = "
    SELECT 
        r.fecha_uso,
        i.nombre as item_nombre,
        i.categoria,
        r.cantidad_usada,
        i.unidad_medida,
        o.nombre as odontologo_nombre,
        r.nota
    FROM RegistroUso r
    JOIN InventarioDental i ON r.id_item = i.id_item
    JOIN Odontologo o ON r.id_odontologo = o.id_odontologo
    WHERE 1=1
";

// Add filters dynamically
$params = [];

// Category filter
if (!empty($filters['categoria'])) {
    $query .= " AND i.categoria = :categoria";
    $params[':categoria'] = $filters['categoria'];
}

// Dentist filter
if ($filters['odontologo'] > 0) {
    $query .= " AND r.id_odontologo = :odontologo";
    $params[':odontologo'] = $filters['odontologo'];
}

// Date from filter
if (!empty($filters['fecha_desde'])) {
    $query .= " AND DATE(r.fecha_uso) >= :fecha_desde";
    $params[':fecha_desde'] = $filters['fecha_desde'];
}

// Date to filter
if (!empty($filters['fecha_hasta'])) {
    $query .= " AND DATE(r.fecha_uso) <= :fecha_hasta";
    $params[':fecha_hasta'] = $filters['fecha_hasta'];
}

$query .= " ORDER BY r.fecha_uso DESC";

// Debug the final query
logDebug(['QUERY' => $query, 'PARAMS' => $params]);

// Prepare and execute query
try {
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $usage_records = $stmt->fetchAll();
    
    logDebug(['RECORD_COUNT' => count($usage_records)]);
} catch (PDOException $e) {
    logDebug(['ERROR' => $e->getMessage()]);
    die("Error al generar el reporte");
}

// Generate CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="historial_uso_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

// Headers
fputcsv($output, [
    'Fecha',
    'Categoría',
    'Item',
    'Cantidad Usada',
    'Unidad',
    'Odontólogo',
    'Notas'
], ';');

// Data rows
foreach ($usage_records as $record) {
    fputcsv($output, [
        date('d/m/Y H:i', strtotime($record['fecha_uso'])),
        ucfirst($record['categoria']),
        $record['item_nombre'],
        number_format($record['cantidad_usada'], 2),
        ucfirst($record['unidad_medida']),
        $record['odontologo_nombre'],
        $record['nota'] ?? '-'
    ], ';');
}

fclose($output);
exit();