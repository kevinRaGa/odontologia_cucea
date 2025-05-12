<?php
require_once 'auth_check.php';
require_once 'conexion.php';

$is_admin = $_SESSION['user_role'] == 1;

if(!isset($_GET['id'])) {
    header("Location: inventory_list.php");
    exit();
}

$id_item = intval($_GET['id']);

// Get item details
try {
    $stmt = $pdo->prepare("SELECT * FROM inventariodental WHERE id_item = ?");
    $stmt->execute([$id_item]);
    $item = $stmt->fetch();
    
    if(!$item) {
        header("Location: inventory_list.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $cantidad_usada = floatval($_POST['cantidad']);
        
        if($cantidad_usada <= 0) {
            throw new Exception("La cantidad debe ser mayor que cero");
        }
        
        if($cantidad_usada > $item['cantidad']) {
            throw new Exception("No hay suficiente stock disponible");
        }
        
        // Update inventory
        $stmt = $pdo->prepare("
            UPDATE inventariodental SET
                cantidad = cantidad - ?
            WHERE id_item = ?
        ");
        
        $stmt->execute([$cantidad_usada, $id_item]);
        
        // Record the usage in a new table
        $stmt = $pdo->prepare("
            INSERT INTO registrouso (
                id_item, cantidad_usada, fecha_uso, id_odontologo, nota
            ) VALUES (?, ?, NOW(), ?, ?)
        ");
        
        $stmt->execute([
            $id_item,
            $cantidad_usada,
            $_SESSION['user_id'],
            !empty($_POST['nota']) ? htmlspecialchars($_POST['nota']) : null
        ]);
        
        header("Location: inventory_list.php?success=Item actualizado correctamente");
        exit();
        
    } catch(PDOException $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Uso - Inventario Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/quick_use.module.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <i class="fas fa-minus-circle"></i>
                Registrar Uso de: <?= htmlspecialchars($item['nombre']) ?>
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Stock Actual</label>
                    <div class="current-stock"><?= $item['cantidad'] ?> <?= ucfirst($item['unidad_medida']) ?></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Cantidad a Usar</label>
                    <input 
                        type="number" 
                        name="cantidad" 
                        class="form-control" 
                        step="0.01" 
                        min="0.01" 
                        max="<?= $item['cantidad'] ?>" 
                        required
                    />
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nota (Opcional)</label>
                    <textarea name="nota" class="form-control" rows="3"></textarea>
                    <small>Ejemplo: "Usado en procedimiento de limpieza para paciente XYZ"</small>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-top: 30px;">
                    <a href="inventory_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Registrar Uso
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>