<?php
require_once 'conexion.php';
require_once 'auth_check.php';
require_odontologo();

// Get dentist data
$odontologo = [];
$roles = [];
if(isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    try {
        // Get dentist info
        $stmt = $pdo->prepare("SELECT * FROM Odontologo WHERE id_odontologo = ?");
        $stmt->execute([$id]);
        $odontologo = $stmt->fetch();
        
        if(!$odontologo) {
            header("Location: odontologos.php");
            exit();
        }
        
        // Get roles
        $roles = $pdo->query("SELECT * FROM Roles ORDER BY nombre")->fetchAll();
        
    } catch(PDOException $e) {
        die("Error al cargar datos: " . $e->getMessage());
    }
}

// Process form
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    try {
        $required = ['nombre', 'email', 'id_rol'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Validate email
        if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Formato de email inválido");
        }

        // Update query
        $sql = "UPDATE Odontologo SET 
                nombre = ?,
                especialidad = ?,
                email = ?,
                id_rol = ?";
        
        $params = [
            htmlspecialchars($_POST['nombre']),
            !empty($_POST['especialidad']) ? htmlspecialchars($_POST['especialidad']) : null,
            htmlspecialchars($_POST['email']),
            intval($_POST['id_rol'])
        ];

        // Handle password update if provided
        if(!empty($_POST['password'])) {
            if(strlen($_POST['password']) < 8) {
                throw new Exception("La contraseña debe tener al menos 8 caracteres");
            }
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id_odontologo = ?";
        $params[] = intval($_POST['id']);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header("Location: odontologos.php");
        exit();
        
    } catch(PDOException $e) {
        $error = $e->getCode() == 23000 ? "Email ya registrado" : "Error al actualizar: " . $e->getMessage();
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
    <title>Editar Odontólogo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            min-height: 100vh;
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
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            border-left: 4px solid #3498db;
        }

        .form-label {
            display: block;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
        }

        .password-note {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
            font-style: italic;
        }

        .btn {
            padding: 12px 20px;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        .required:after {
            content: " *";
            color: #e74c3c;
        }

        .btn-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <span>
                    <i class="fas fa-user-edit"></i>
                    Editar Odontólogo
                </span>
                <a href="odontologos.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <?php if(!empty($odontologo)): ?>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $odontologo['id_odontologo'] ?>">

                <div class="info-grid">
                    <div class="form-group">
                        <label class="form-label required">Nombre completo</label>
                        <input type="text" name="nombre" class="form-control" required
                               value="<?= htmlspecialchars($odontologo['nombre'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Especialidad</label>
                        <input type="text" name="especialidad" class="form-control"
                               value="<?= htmlspecialchars($odontologo['especialidad'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($odontologo['email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" minlength="8">
                        <div class="password-note">Dejar en blanco para mantener la contraseña actual</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Rol</label>
                        <select name="id_rol" class="form-control" required>
                            <option value="">Seleccionar rol</option>
                            <?php foreach($roles as $rol): ?>
                                <option value="<?= $rol['id_rol'] ?>"
                                    <?= ($odontologo['id_rol'] == $rol['id_rol']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rol['nombre']) ?> - 
                                    <?= htmlspecialchars($rol['descripcion']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="btn-container">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Actualizar Odontólogo
                        </button>
                    </div>
                </div>
            </form>
            <?php else: ?>
                <p>Odontólogo no encontrado</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>