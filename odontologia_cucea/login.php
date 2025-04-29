<?php
// Start secure session
session_start([
    'cookie_lifetime' => 0, // Session lasts until browser closes
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict'
]);

require_once 'conexion.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $target = match($_SESSION['user_role']) {
        1 => 'admin.php',
        2, 3 => 'pacientes.php',
        default => 'login.php'
    };
    header("Location: $target");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        try {
            $stmt = $pdo->prepare("SELECT o.*, r.nombre as rol_nombre 
                                 FROM Odontologo o 
                                 LEFT JOIN Roles r ON o.id_rol = r.id_rol 
                                 WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Set session data
                $_SESSION = [
                    'user_id' => $user['id_odontologo'],
                    'user_name' => $user['nombre'],
                    'user_role' => $user['id_rol'],
                    'role_name' => $user['rol_nombre']
                ];
                
                // Redirect based on role
                $target = match($user['id_rol']) {
                    1 => 'admin.php',
                    2, 3 => 'pacientes.php',
                    default => 'login.php'
                };
                header("Location: $target");
                exit();
            }
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
        }
    }
    
    $error = "Correo o contraseña incorrectos";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Odontología CUCEA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            height: 60px;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        button {
            background-color: #3498db;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="https://f365.cucea.udg.mx/images/logo-cucea.png" alt="CUCEA Logo">
        </div>
        
        <h1><i class="fas fa-lock"></i> Iniciar Sesión</h1>
        
        <?php if(isset($error)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Correo electrónico:</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-key"></i> Contraseña:</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit">
                <i class="fas fa-sign-in-alt"></i> Ingresar
            </button>
        </form>
    </div>
</body>
</html>