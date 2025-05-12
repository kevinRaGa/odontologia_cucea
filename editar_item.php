<?php
require_once 'auth_check.php';
require_once 'conexion.php';

$is_admin = $_SESSION['user_role'] == 1;

if(!isset($_GET['id'])) {
    header("Location: inventory_list.php");
    exit();
}

$id_item = intval($_GET['id']);

$categorias = ['Material', 'Equipo', 'Consumible', 'Medicamento'];
$unidades = ['Piezas', 'Cajas', 'Paquetes', 'Frascos', 'Bolsas', 'Rollos'];

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
        // Validación
        $required = ['nombre', 'categoria', 'cantidad', 'unidad_medida'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        $stmt = $pdo->prepare("
            UPDATE inventariodental SET
                nombre = ?,
                descripcion = ?,
                categoria = ?,
                cantidad = ?,
                unidad_medida = ?,
                stock_minimo = ?,
                fecha_vencimiento = ?,
                estado = ?,
                notas = ?
            WHERE id_item = ?
        ");
        
        $stmt->execute([
            htmlspecialchars($_POST['nombre']),
            !empty($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : null,
            $_POST['categoria'],
            floatval($_POST['cantidad']),
            $_POST['unidad_medida'],
            !empty($_POST['stock_minimo']) ? floatval($_POST['stock_minimo']) : 0,
            !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
            $_POST['estado'] ?? 'activo',
            !empty($_POST['notas']) ? htmlspecialchars($_POST['notas']) : null,
            $id_item
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
    <title>Editar Item - Inventario Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/editar_item.module.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <i class="fas fa-edit"></i>
                Editar Item: <?= htmlspecialchars($item['nombre']) ?>
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="info-grid">
                    <div class="form-group">
                        <label class="form-label required">Nombre del Item</label>
                        <input 
                            type="text" 
                            name="nombre" 
                            class="form-control" 
                            required 
                            value="<?= htmlspecialchars($item['nombre']) ?>"
                        />
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea
                            name="descripcion"
                            class="form-control"
                            rows="2"
                        ><?= htmlspecialchars($item['descripcion'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Categoría</label>
                        <select name="categoria" class="form-control" required>
                            <option value="">Seleccione categoría</option>
                            <?php foreach($categorias as $cat): ?>
                            <option 
                                value="<?= strtolower($cat) ?>" 
                                <?= ($item['categoria'] == strtolower($cat)) ? 'selected' : '' ?>
                            >
                                <?= $cat ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Cantidad</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="cantidad"
                            class="form-control"
                            required
                            value="<?= htmlspecialchars($item['cantidad']) ?>"
                        />
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Unidad de Medida</label>
                        <select name="unidad_medida" class="form-control" required>
                            <option value="">Seleccione unidad</option>
                            <?php foreach($unidades as $unidad): ?>
                            <option 
                                value="<?= strtolower($unidad) ?>"
                                <?= ($item['unidad_medida'] == strtolower($unidad)) ? 'selected' : '' ?>
                            >
                                <?= $unidad ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Stock Mínimo</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="stock_minimo"
                            class="form-control"
                            value="<?= htmlspecialchars($item['stock_minimo'] ?? 0) ?>"
                        />
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fecha de Ingreso</label>
                        <input
                            type="date"
                            name="fecha_ingreso"
                            class="form-control"
                            value="<?= htmlspecialchars($item['fecha_ingreso']) ?>"
                            readonly
                        />
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fecha de Vencimiento</label>
                        <input
                            type="date"
                            name="fecha_vencimiento"
                            class="form-control"
                            value="<?= htmlspecialchars($item['fecha_vencimiento'] ?? '') ?>"
                        />
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-control">
                            <option value="activo" <?= ($item['estado'] == 'activo') ? 'selected' : '' ?>>Activo</option>
                            <option value="baja" <?= ($item['estado'] == 'baja') ? 'selected' : '' ?>>Dado de baja</option>
                            <option value="reparacion" <?= ($item['estado'] == 'reparacion') ? 'selected' : '' ?>>En reparación</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notas</label>
                        <textarea name="notas" class="form-control" rows="3"><?= htmlspecialchars($item['notas'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="window.history.back()" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>