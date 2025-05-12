<?php
require_once 'auth_check.php';
require_once 'conexion.php';

$is_admin = $_SESSION['user_role'] == 1;

$categorias = ['Material', 'Equipo', 'Consumible', 'Medicamento'];
$unidades = ['Piezas', 'Cajas', 'Paquetes', 'Frascos', 'Bolsas', 'Rollos'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validación
        $required = ['nombre', 'categoria', 'cantidad', 'unidad_medida', 'fecha_ingreso'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Preparar y ejecutar la consulta
        $stmt = $pdo->prepare("
            INSERT INTO inventariodental (
                nombre, descripcion, categoria, cantidad, unidad_medida,
                stock_minimo, fecha_ingreso, fecha_vencimiento, estado, notas
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            htmlspecialchars($_POST['nombre']),
            !empty($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : null,
            $_POST['categoria'],
            floatval($_POST['cantidad']),
            $_POST['unidad_medida'],
            !empty($_POST['stock_minimo']) ? floatval($_POST['stock_minimo']) : 0,
            $_POST['fecha_ingreso'],
            !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
            $_POST['estado'] ?? 'activo',
            !empty($_POST['notas']) ? htmlspecialchars($_POST['notas']) : null
        ]);

        header("Location: inventory_list.php?success=Item agregado correctamente");
        exit();

    } catch (PDOException $e) {
        $error = "Error al registrar: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Nuevo Item - Inventario Dental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="css/nuevo_item.module.css">
  </head>
  <body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
      <div class="container">
        <h1>
          <i class="fas fa-plus-circle"></i>
          Nuevo Item de Inventario
          <a href="inventory_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        </h1>

        <?php if(isset($error)): ?>
        <div class="alert error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
          <div class="info-grid">
            <div class="form-group">
              <label class="form-label required">Nombre del Item</label>
              <input type="text" name="nombre" class="form-control" required />
            </div>

            <div class="form-group">
              <label class="form-label">Descripción</label>
              <textarea
                name="descripcion"
                class="form-control"
                rows="2"
              ></textarea>
            </div>

            <div class="form-group">
              <label class="form-label required">Categoría</label>
              <select name="categoria" class="form-control" required>
                <option value="">Seleccione categoría</option>
                <?php foreach($categorias as $cat): ?>
                <option value="<?= strtolower($cat) ?>"><?= $cat ?></option>
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
              />
            </div>

            <div class="form-group">
              <label class="form-label required">Unidad de Medida</label>
              <select name="unidad_medida" class="form-control" required>
                <option value="">Seleccione unidad</option>
                <?php foreach($unidades as $unidad): ?>
                <option value="<?= strtolower($unidad) ?>">
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
                value="0"
              />
            </div>

            <div class="form-group">
              <label class="form-label required">Fecha de Ingreso</label>
              <input
                type="date"
                name="fecha_ingreso"
                class="form-control"
                required
                value="<?= date('Y-m-d') ?>"
              />
            </div>

            <div class="form-group">
              <label class="form-label">Fecha de Vencimiento</label>
              <input
                type="date"
                name="fecha_vencimiento"
                class="form-control"
              />
            </div>


            <div class="form-group">
              <label class="form-label">Estado</label>
              <select name="estado" class="form-control">
                <option value="activo" selected>Activo</option>
                <option value="baja">Dado de baja</option>
                <option value="reparacion">En reparación</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Notas</label>
              <textarea name="notas" class="form-control" rows="3"></textarea>
            </div>

            <div class="btn-container">
              <button type="reset" class="btn btn-secondary">
                <i class="fas fa-undo"></i> Limpiar
              </button>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Item
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </body>
</html>
