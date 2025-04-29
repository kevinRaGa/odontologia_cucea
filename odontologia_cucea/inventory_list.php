<?php
require_once 'auth_check.php';
require_once 'conexion.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['categoria']) ? $_GET['categoria'] : '';

try {
    // Get all categories for filter dropdown
    $categories = $pdo->query("SELECT DISTINCT categoria FROM InventarioDental ORDER BY categoria")->fetchAll();
    
    // Base query
    $query = "
        SELECT *,
        CASE 
            WHEN cantidad <= stock_minimo AND cantidad > 0 THEN 'low-stock'
            WHEN cantidad = 0 THEN 'out-of-stock'
            ELSE ''
        END AS stock_status
        FROM InventarioDental 
        WHERE 1=1
    ";
    
    if (!empty($search)) {
        $query .= " AND nombre LIKE :search";
    }
    
    if (!empty($category_filter)) {
        $query .= " AND categoria = :categoria";
    }
    
    $query .= " ORDER BY fecha_registro DESC";
    
    $stmt = $pdo->prepare($query);
    
    if (!empty($search)) {
        $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }
    
    if (!empty($category_filter)) {
        $stmt->bindValue(':categoria', $category_filter, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $items = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inventario Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --primary-color: #28a745;
            --primary-hover: #218838;
            --secondary-color: #6c757d;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --border-color: #dee2e6;
            --text-color: #212529;
        }

        body {
            background-color: #f5f7fa;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            color: var(--dark-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .search-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            align-items: center;
        }

        .search-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .new-item-btn {
            padding: 12px 20px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            height: 44px;
            box-sizing: border-box;
        }

        .new-item-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .search-input {
            width: 300px;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 1rem;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 8px rgba(40, 167, 69, 0.3);
        }

        .search-btn {
            padding: 12px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            height: 44px;
            font-size: 1rem;
        }

        .search-btn:hover {
            background-color: var(--primary-hover);
        }

        .clear-btn {
            padding: 12px 20px;
            background-color: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            height: 44px;
            font-size: 1rem;
            text-decoration: none;
        }

        .clear-btn:hover {
            background-color: #5a6268;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-dropdown {
            position: relative;
            display: inline-block;
        }

        .filter-btn {
            padding: 12px 20px;
            background-color: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            height: 44px;
            font-size: 1rem;
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

        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th, 
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background-color: var(--light-color);
            font-weight: 600;
            color: var(--dark-color);
            position: sticky;
            top: 0;
        }

        .data-table tr:hover {
            background-color: rgba(40, 167, 69, 0.05);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 13px;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.activo {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--primary-color);
        }

        .status-badge.baja {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        .status-badge.reparacion {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .low-stock {
            background-color: rgba(255, 193, 7, 0.2);
            font-weight: bold;
        }

        .out-of-stock {
            background-color: rgba(220, 53, 69, 0.2);
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <i class="fas fa-boxes"></i> Inventario Dental
            </h1>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>

            <div class="search-container">
                <div class="search-group">
                    <a href="nuevo_item.php" class="new-item-btn">
                        <i class="fas fa-plus"></i> Nuevo Item
                    </a>
                    
                    <form method="GET" action="">
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Buscar por nombre..." 
                            value="<?= htmlspecialchars($search) ?>"
                        >
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        <?php if(!empty($search)): ?>
                            <a href="inventory_list.php" class="clear-btn">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="filter-group">
                    <div class="filter-dropdown">
                        <button type="button" class="filter-btn <?= !empty($category_filter) ? 'active-filter' : '' ?>">
                            <i class="fas fa-filter"></i> 
                            <?= !empty($category_filter) ? 'Filtrado' : 'Filtrar por Categoría' ?>
                        </button>
                        <div class="dropdown-content" id="categoryDropdown">
                            <a href="?<?= http_build_query(array_merge($_GET, ['categoria' => ''])) ?>">
                                Todas las categorías
                            </a>
                            <?php foreach($categories as $category): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['categoria' => $category['categoria']])) ?>">
                                    <?= htmlspecialchars(ucfirst($category['categoria'])) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if(!empty($category_filter)): ?>
                        <a href="inventory_list.php?<?= http_build_query(['search' => $search]) ?>" class="clear-btn">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Cantidad</th>
                            <th>Unidad</th>
                            <th>Estado</th>
                            <th>Fecha Ingreso</th>
                            <th>Fecha Vencimiento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): ?>
                        <tr class="<?= $item['stock_status'] ?>">
                            <td><?= htmlspecialchars($item['nombre']) ?></td>
                            <td><?= ucfirst($item['categoria']) ?></td>
                            <td><?= $item['cantidad'] ?></td>
                            <td><?= ucfirst($item['unidad_medida']) ?></td>
                            <td>
                                <span class="status-badge <?= $item['estado'] ?>">
                                    <?= ucfirst($item['estado']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($item['fecha_ingreso'])) ?></td>
                            <td>
                                <?= $item['fecha_vencimiento'] ? date('d/m/Y', strtotime($item['fecha_vencimiento'])) : 'N/A' ?>
                            </td>
                            <td>
    <a href="editar_item.php?id=<?= $item['id_item'] ?>" class="btn btn-secondary btn-sm">
        <i class="fas fa-edit"></i>
    </a>
    <a href="quick_use.php?id=<?= $item['id_item'] ?>" class="btn btn-warning btn-sm">
        <i class="fas fa-minus-circle"></i> Usar
    </a>
    <a href="eliminar_item.php?id=<?= $item['id_item'] ?>" 
       class="btn btn-danger btn-sm" 
       onclick="return confirm('¿Eliminar este item permanentemente?')">
        <i class="fas fa-trash"></i>
    </a>
</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle dropdown when clicking the filter button
            document.querySelector('.filter-btn').addEventListener('click', function() {
                document.getElementById('categoryDropdown').classList.toggle('show');
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
        });
    </script>
</body>
</html>