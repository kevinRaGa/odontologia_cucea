<?php
require_once 'conexion.php';
require_once 'auth_check.php';
require_odontologo();

// Get roles for dropdown
$roles = $pdo->query("SELECT * FROM Roles ORDER BY nombre")->fetchAll();

// Process form
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validate required fields
        $required = ['nombre', 'email', 'password', 'id_rol'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Validate email format
        if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Formato de email inválido");
        }

        // Check password strength
        if(strlen($_POST['password']) < 8) {
            throw new Exception("La contraseña debe tener al menos 8 caracteres");
        }

        // Hash password
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Insert new odontólogo
        $stmt = $pdo->prepare("INSERT INTO Odontologo (
            nombre, 
            especialidad, 
            email, 
            password, 
            id_rol
        ) VALUES (?, ?, ?, ?, ?)");
        
        $stmt->execute([
            htmlspecialchars($_POST['nombre']),
            !empty($_POST['especialidad']) ? htmlspecialchars($_POST['especialidad']) : NULL,
            htmlspecialchars($_POST['email']),
            $hashedPassword,
            intval($_POST['id_rol'])
        ]);

        // Redirect to odontologos list
        header("Location: odontologos.php");
        exit();
        
    } catch(PDOException $e) {
        // Check for duplicate email error
        if($e->getCode() == 23000) {
            $error = "Error: El email ya está registrado";
        } else {
            $error = "Error al registrar: " . $e->getMessage();
        }
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
    <title>Registrar Nuevo Odontólogo</title>
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
            align-items: center;
            gap: 10px;
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
            grid-template-columns: repeat(2, 1fr);
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
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        .required:after {
            content: " *";
            color: #e74c3c;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 0.8rem;
            color: #666;
        }

        .strength-weak {
            color: #e74c3c;
        }

        .strength-moderate {
            color: #f39c12;
        }

        .strength-strong {
            color: #2ecc71;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>
                <i class="fas fa-user-plus"></i>
                Registrar Nuevo Odontólogo
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" id="odontologoForm">
                <div class="info-grid">
                    <div class="form-group">
                        <label class="form-label required">Nombre completo</label>
                        <input type="text" name="nombre" class="form-control" required
                               value="<?= isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Especialidad</label>
                        <input type="text" name="especialidad" class="form-control"
                               value="<?= isset($_POST['especialidad']) ? htmlspecialchars($_POST['especialidad']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" required minlength="8">
                        <div class="password-strength" id="passwordStrength">Mínimo 8 caracteres</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Rol</label>
                        <select name="id_rol" class="form-control" required>
                            <option value="">Seleccionar rol</option>
                            <?php foreach($roles as $rol): ?>
                                <option value="<?= $rol['id_rol'] ?>"
                                    <?= (isset($_POST['id_rol']) && $_POST['id_rol'] == $rol['id_rol']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rol['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="window.history.back()" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Registrar Odontólogo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function(e) {
            const strengthElement = document.getElementById('passwordStrength');
            const length = e.target.value.length;
            
            if(length == 0) {
                strengthElement.textContent = 'Mínimo 8 caracteres';
                strengthElement.className = 'password-strength';
            } else if(length < 8) {
                strengthElement.textContent = 'Débil (mínimo 8 caracteres)';
                strengthElement.className = 'password-strength strength-weak';
            } else if(length < 12) {
                strengthElement.textContent = 'Moderada';
                strengthElement.className = 'password-strength strength-moderate';
            } else {
                strengthElement.textContent = 'Fuerte';
                strengthElement.className = 'password-strength strength-strong';
            }
        });
    </script>
</body>
</html>