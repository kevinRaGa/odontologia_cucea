<?php
// Database configuration
require_once 'conexion.php';
require_once 'auth_check.php';
require_odontologo();

// Check if the user's role is admin (role 1)
$is_admin = $_SESSION['user_role'] == 1;

// Handle dentist deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM odontologo WHERE id_odontologo = ?");
        $stmt->execute([intval($_POST['id'])]);
        header("Location: odontologos.php");
        exit();
    } catch(PDOException $e) {
        die("Error al eliminar: " . $e->getMessage());
    }
}

// Get all dentists
$odontologos = $pdo->query("
    SELECT 
        o.id_odontologo,
        o.nombre,
        o.especialidad,
        o.email,
        r.nombre as rol 
    FROM odontologo o
    LEFT JOIN roles r ON o.id_rol = r.id_rol
    WHERE o.nombre LIKE 'DRA.%' OR o.nombre LIKE 'DR.%' OR o.nombre LIKE 'PCD%'
    ORDER BY o.nombre
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Odontólogos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/odontologos.module.css?v=1.2">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.2">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container">
            <h1>🦷 Gestión de Odontólogos</h1>

            <div class="action-bar">
                <?php if ($is_admin): ?>
                    <a href="agregar_odontologo.php" class="new-btn">
                        <i class="fas fa-plus"></i> Nuevo Odontólogo
                    </a>
                <?php endif; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="nombre">Nombre</th>
                        <th class="especialidad">Especialidad</th>
                        <th class="email">Email</th>
                        <th class="rol">Rol</th>
                        <?php if ($is_admin): ?>
                            <th class="acciones text-center">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($odontologos as $odontologo): ?>
                    <tr>
                        <td><?= htmlspecialchars($odontologo['nombre']) ?></td>
                        <td><?= htmlspecialchars($odontologo['especialidad'] ?? 'Sin especialidad') ?></td>
                        <td class="email-column"><?= htmlspecialchars($odontologo['email'] ?? 'Sin email') ?></td>
                        <td><?= htmlspecialchars($odontologo['rol'] ?? 'Sin rol asignado') ?></td>
                        <?php if ($is_admin): ?>
                            <td class="actions">
                                <div class="action-buttons">
                                    <a href="editar_odontologo.php?id=<?= $odontologo['id_odontologo'] ?>" class="action-btn btn-editar">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <form method="POST" action="odontologos.php" class="form-eliminar">
                                        <input type="hidden" name="id" value="<?= $odontologo['id_odontologo'] ?>">
                                        <button type="submit" name="eliminar" class="action-btn btn-eliminar" onclick="return confirm('¿Está seguro de eliminar este odontólogo?')">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile
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
            
            // Check if sidebar is collapsed
            const checkSidebarState = () => {
                if (sidebar.classList.contains('collapsed')) {
                    mainContent.classList.add('collapsed');
                } else {
                    mainContent.classList.remove('collapsed');
                }
            };
            
            // Initial check
            checkSidebarState();
            
            // Observe changes to sidebar
            const observer = new MutationObserver(checkSidebarState);
            observer.observe(sidebar, {
                attributes: true,
                attributeFilter: ['class']
            });
        });
    </script>
</body>
</html>