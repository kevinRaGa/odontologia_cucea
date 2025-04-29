<?php
require_once 'auth_check.php';
require_once 'conexion.php';

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
            INSERT INTO InventarioDental (
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
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
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
            justify-content: space-between;
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

      .info-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
          gap: 20px;
          margin-bottom: 20px;
      }

      .form-group {
          background: #f8f9fa;
          border-radius: 8px;
          padding: 15px;
          border-left: 4px solid var(--primary-color);
      }

      .form-label {
          display: block;
          font-weight: bold;
          color: var(--dark-color);
          margin-bottom: 8px;
          font-size: 0.9rem;
      }

      .form-control {
          width: 100%;
          padding: 10px;
          border: 1px solid var(--border-color);
          border-radius: 4px;
          font-size: 1rem;
          transition: border-color 0.15s;
      }

      .form-control:focus {
          border-color: var(--primary-color);
          outline: 0;
          box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
      }

      select.form-control {
          appearance: none;
          background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
          background-repeat: no-repeat;
          background-position: right 10px center;
          background-size: 1em;
      }

      textarea.form-control {
          min-height: 100px;
      }

      .required:after {
          content: " *";
          color: var(--danger-color);
      }

      .btn-container {
          grid-column: 1 / -1;
          display: flex;
          justify-content: flex-end;
          gap: 10px;
      }
    </style>
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
