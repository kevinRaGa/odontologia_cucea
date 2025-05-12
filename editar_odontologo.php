<?php
require_once 'conexion.php';
require_once 'auth_check.php';
require_odontologo();

$is_admin = $_SESSION['user_role'] == 1;

// Get dentist data
$odontologo = [];
$roles = [];
if(isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    try {
        // Get dentist info
        $stmt = $pdo->prepare("SELECT * FROM odontologo WHERE id_odontologo = ?");
        $stmt->execute([$id]);
        $odontologo = $stmt->fetch();
        
        if(!$odontologo) {
            header("Location: odontologos.php");
            exit();
        }
        
        // Get roles
        $roles = $pdo->query("SELECT * FROM roles ORDER BY nombre")->fetchAll();
        
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
        $sql = "UPDATE odontologo SET 
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
    <link rel="stylesheet" href="css/editar_odontologo.module.css?v=1.4">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.3">
</head>
<body>
    <button class="mobile-menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include 'sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="container">
            <h1><i class="fas fa-user-edit"></i> Editar Odontólogo</h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($odontologo)): ?>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $odontologo['id_odontologo'] ?>">

                <div class="form-grid">
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
                                    <?= htmlspecialchars($rol['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="odontologos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Odontólogo
                    </button>
                </div>
            </form>
            <?php else: ?>
                <p>Odontólogo no encontrado</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Updated sidebar handling to match crear_odontologo
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.getElementById('mainContent');
        
        const checkSidebarState = () => {
            if (sidebar.classList.contains('collapsed')) {
                mainContent.classList.add('collapsed');
            } else {
                mainContent.classList.remove('collapsed');
            }
        };
        
        checkSidebarState();
        
        const observer = new MutationObserver(checkSidebarState);
        observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class']
        });

        // Mobile menu handling
        document.getElementById('menuToggle').addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992 && 
                !event.target.closest('.sidebar') && 
                !event.target.closest('#menuToggle')) {
                sidebar.classList.remove('active');
            }
        });
    });
    </script>
</body>
</html>