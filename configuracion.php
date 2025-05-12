<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_admin();

$is_admin = $_SESSION['user_role'] == 1;

// Configuraciones disponibles con sus respectivos ID fields
$configuraciones = [
    'semestre' => [
        'name' => 'Semestres', 
        'field' => 'numero', 
        'id' => 'id_semestre',
        'query' => 'SELECT * FROM semestre ORDER BY CASE WHEN numero = "NA" THEN 0 ELSE CAST(numero AS SIGNED) END, numero'
    ],
    'sexo' => ['name' => 'Géneros', 'field' => 'genero', 'id' => 'id_sexo'],
    'carreraempleo' => ['name' => 'Carreras/Empleos', 'field' => 'nombre', 'id' => 'id_carrera'],
    'motivoconsulta' => ['name' => 'Motivos de Consulta', 'field' => 'nombre', 'id' => 'id_motivo'],
    'diagnostico' => ['name' => 'Diagnósticos', 'field' => 'nombre', 'id' => 'id_diagnostico'],
    'plantratamiento' => ['name' => 'Planes de Tratamiento', 'field' => 'nombre', 'id' => 'id_plan'],
    'titulos' => ['name' => 'Títulos', 'field' => 'nombre', 'id' => 'id_titulo'],
    'categorias' => [
        'name' => 'Categorías Inventario', 
        'field' => 'categoria', 
        'id' => 'id_item',
        'query' => 'SELECT DISTINCT categoria, MIN(id_item) as id_item FROM inventariodental GROUP BY categoria',
        'insert' => 'INSERT INTO inventariodental (categoria) VALUES (?)'
    ],
    'unidades_medida' => [
        'name' => 'Unidades de Medida', 
        'field' => 'unidad_medida', 
        'id' => 'id_item',
        'query' => 'SELECT DISTINCT unidad_medida, MIN(id_item) as id_item FROM inventariodental GROUP BY unidad_medida',
        'insert' => 'INSERT INTO inventariodental (unidad_medida) VALUES (?)'
    ]
];

// Manejar operaciones CRUD
$current_config = $_GET['config'] ?? '';
$error = $success = '';

if(isset($configuraciones[$current_config])) {
    $config = $configuraciones[$current_config];
    $field = $config['field'];
    $id_field = $config['id'];

    // Añadir nuevo registro
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        try {
            $value = trim($_POST['value']);
            
            if(empty($value)) throw new Exception("El valor no puede estar vacío");
            
            // Verificar duplicados
            $check_query = isset($config['query']) ? 
                "SELECT COUNT(*) FROM ($config[query]) as temp WHERE $field = ?" :
                "SELECT COUNT(*) FROM $current_config WHERE $field = ?";
            
            $stmt = $pdo->prepare($check_query);
            $stmt->execute([$value]);
            if($stmt->fetchColumn() > 0) throw new Exception("Este valor ya existe");

            $insert_query = $config['insert'] ?? "INSERT INTO $current_config ($field) VALUES (?)";
            $pdo->prepare($insert_query)->execute([$value]);
            $success = "Registro añadido exitosamente";
        } catch(Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Eliminar registro
    if(isset($_GET['delete'])) {
        try {
            // For categories and units, we need to update existing items
            if(in_array($current_config, ['categorias', 'unidades_medida'])) {
                $new_value = $current_config === 'categorias' ? 'material' : 'piezas';
                $pdo->prepare("UPDATE inventariodental SET $field = ? WHERE $field = ?")
                    ->execute([$new_value, $_GET['delete']]);
                $success = "Elementos actualizados y categoría eliminada";
            } else {
                $pdo->prepare("DELETE FROM $current_config WHERE $id_field = ?")
                    ->execute([$_GET['delete']]);
                $success = "Registro eliminado exitosamente";
            }
        } catch(Exception $e) {
            $error = "Error al eliminar: " . $e->getMessage();
        }
    }

    // Obtener registros
    $query = $config['query'] ?? "SELECT * FROM $current_config ORDER BY $field";
    $registros = $pdo->query($query)->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/configuracion.module.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content config-content">
        <div class="container">
            <?php if(empty($current_config)): ?>
                <h1><i class="fas fa-cogs"></i> Configuración del Sistema</h1>
                
                <div class="config-grid">
                    <?php foreach($configuraciones as $key => $config): ?>
                        <div class="config-card">
                            <div class="config-icon">
                                <i class="fas fa-list-alt"></i>
                            </div>
                            <h3><?= $config['name'] ?></h3>
                            <a href="configuracion.php?config=<?= $key ?>" class="btn-config">
                                Administrar <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="config-header">
                    <h1>
                        <i class="fas fa-list"></i> 
                        <?= $configuraciones[$current_config]['name'] ?>
                        <a href="configuracion.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </h1>

                    <?php if($error): ?>
                        <div class="alert error"><?= $error ?></div>
                    <?php elseif($success): ?>
                        <div class="alert success"><?= $success ?></div>
                    <?php endif; ?>
                </div>

                <div class="config-management">
                    <form method="POST" class="config-form">
                        <div class="form-group">
                            <input type="text" name="value" required 
                                   placeholder="Nuevo valor para <?= $configuraciones[$current_config]['name'] ?>">
                            <button type="submit" class="btn-add">
                                <i class="fas fa-plus"></i> Añadir
                            </button>
                        </div>
                    </form>

                    <div class="config-list">
                        <h3>Valores Existentes</h3>
                        <div class="scroll-container">
                            <?php foreach($registros as $registro): ?>
                                <div class="config-item">
                                    <span><?= htmlspecialchars($registro[$field]) ?></span>
                                    <a href="configuracion.php?config=<?= $current_config ?>&delete=<?= $registro[$field] ?>" 
                                       class="btn-delete"
                                       onclick="return confirm('¿Eliminar permanentemente?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>