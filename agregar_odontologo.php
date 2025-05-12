<?php
require_once 'conexion.php';
require_once 'auth_check.php';
require_admin();

$is_admin = $_SESSION['user_role'] == 1;

// Get roles and titles for dropdown
$roles = $pdo->query("SELECT * FROM roles ORDER BY nombre")->fetchAll();
$titulos = $pdo->query("SELECT * FROM titulos ORDER BY id_titulo")->fetchAll();

// Process form
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // [Keep all your existing PHP form processing code exactly the same...]
    } catch(PDOException $e) {
        // [Keep your existing error handling...]
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
    <link rel="stylesheet" href="css/agregar_odontologo.module.css?v=1.3">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.3">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include 'sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="container">
            <h1><i class="fas fa-user-plus"></i> Registrar Nuevo Odontólogo</h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="dentist-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_titulo" class="form-label required">Título</label>
                        <select name="id_titulo" id="id_titulo" class="form-control" required>
                            <option value="">Seleccionar título</option>
                            <?php foreach($titulos as $titulo): ?>
                                <option value="<?= $titulo['id_titulo'] ?>"
                                    <?= (isset($_POST['id_titulo']) && $_POST['id_titulo'] == $titulo['id_titulo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($titulo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nombre" class="form-label required">Nombre completo</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" required
                               pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+"
                               title="Solo letras y espacios"
                               value="<?= isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : '' ?>"
                               placeholder="Sin título (se agregará automáticamente)">
                    </div>

                    <div class="form-group">
                        <label for="especialidad" class="form-label">Especialidad</label>
                        <input type="text" name="especialidad" id="especialidad" class="form-control"
                               value="<?= isset($_POST['especialidad']) ? htmlspecialchars($_POST['especialidad']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label required">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label required">Contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" class="form-control" required minlength="8">
                            <button type="button" class="toggle-password" aria-label="Mostrar contraseña">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordStrength">
                            <span class="strength-text">Mínimo 8 caracteres</span>
                            <div class="strength-meter">
                                <span class="strength-bar"></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="id_rol" class="form-label required">Rol</label>
                        <select name="id_rol" id="id_rol" class="form-control" required>
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
                    <button type="button" onclick="window.history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Registrar Odontólogo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile (same as completar_cita.php)
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992 && 
                !event.target.closest('.sidebar') && 
                !event.target.closest('#menuToggle')) {
                document.querySelector('.sidebar').classList.remove('active');
            }
        });

        // Handle sidebar collapse state
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
        });

        // Password strength indicator (same visual style as completar_cita.php)
        const passwordInput = document.getElementById('password');
        const strengthText = document.querySelector('.strength-text');
        const strengthBar = document.querySelector('.strength-bar');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            updateStrengthIndicator(strength);
        });

        function updateStrengthIndicator(strength) {
            const colors = ['#e74c3c', '#f39c12', '#f1c40f', '#2ecc71', '#27ae60'];
            const messages = ['Muy débil', 'Débil', 'Moderada', 'Fuerte', 'Muy fuerte'];
            
            strength = Math.min(strength, 4);
            
            strengthText.textContent = messages[strength];
            strengthBar.style.width = `${(strength + 1) * 20}%`;
            strengthBar.style.backgroundColor = colors[strength];
        }

        // Toggle password visibility
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        // Input validation for name field
        document.getElementById('nombre').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]/g, '');
        });
    </script>
</body>
</html>