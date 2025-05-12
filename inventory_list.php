<?php
require_once 'auth_check.php';
require_once 'conexion.php';

$is_admin = $_SESSION['user_role'] == 1;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['categoria']) ? $_GET['categoria'] : '';

try {
    // Get all categories for filter dropdown
    $categories = $pdo->query("SELECT DISTINCT categoria FROM inventariodental ORDER BY categoria")->fetchAll();
    
    // Base query
    $query = "
        SELECT *,
        CASE 
            WHEN cantidad <= stock_minimo AND cantidad > 0 THEN 'low-stock'
            WHEN cantidad = 0 THEN 'out-of-stock'
            ELSE ''
        END AS stock_status
        FROM inventariodental 
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
    <link rel="stylesheet" href="css/inventory_list.module.css">
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