<?php
// Start secure session
session_start([
    'cookie_lifetime' => 0,
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
                                 FROM odontologo o 
                                 LEFT JOIN roles r ON o.id_rol = r.id_rol 
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.module.css?v=1.2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid login-container">
        <div class="row g-0 justify-content-center">
            <div class="col-12 col-lg-6">
                <form class="login-form" method="POST">
                    <div class="logo text-center mb-4">
                        <img src="https://f365.cucea.udg.mx/images/logo-cucea.png" alt="CUCEA Logo">
                    </div>
                    <h1>Iniciar Sesión</h1>
                    <p>Bienvenido/a al sistema de odontología CUCEA. Ingresa tus credenciales para continuar.</p>

                    <?php if(isset($error)): ?>
                        <div class="alert error">
                            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Correo electrónico</label>
                        <input type="email" class="form-control" name="email" id="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-key"></i> Contraseña</label>
                        <input type="password" class="form-control" name="password" id="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Ingresar
                    </button>
                </form>
            </div>
            <div class="col-lg-6 illustration d-none d-lg-flex">
                <img src="img/mona_chida.svg" alt="Ilustración dental" class="img-fluid">
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>