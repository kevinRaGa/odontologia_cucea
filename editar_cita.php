<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_odontologo();

$is_admin = $_SESSION['user_role'] == 1;

// Get appointment data if editing
$cita = [];
if(isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COALESCE(p.nombre, c.nombre_paciente_temp) as nombre_paciente
        FROM citas c 
        LEFT JOIN paciente p ON c.id_paciente = p.id_paciente 
        WHERE c.id_cita = ?
    ");
    $stmt->execute([intval($_GET['id'])]);
    $cita = $stmt->fetch();
    
    if(!$cita) {
        header("Location: admin.php");
        exit();
    }
}

// Get options for dropdowns
try {
    $odontologos = $pdo->query("SELECT * FROM odontologo")->fetchAll();
    $motivos = $pdo->query("SELECT * FROM motivoconsulta")->fetchAll();
} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

// Process form
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    try {
        $pdo->beginTransaction();
        
        // Validation
        $required = ['fecha_hora', 'motivo', 'odontologo', 'nombre_paciente'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Update appointment - we'll keep the existing patient ID if there was one
        $stmt = $pdo->prepare("UPDATE citas SET
            nombre_paciente_temp = ?,
            id_odontologo = ?,
            fecha_hora = ?,
            motivo = ?,
            estado = ?,
            comentarios = ?
            WHERE id_cita = ?");
        
        $stmt->execute([
            htmlspecialchars(trim($_POST['nombre_paciente'])),
            intval($_POST['odontologo']),
            $_POST['fecha_hora'],
            intval($_POST['motivo']),
            $_POST['estado'] ?? 'programada',
            !empty($_POST['comentarios']) ? htmlspecialchars($_POST['comentarios']) : null,
            intval($_POST['id'])
        ]);

        $pdo->commit();
        header("Location: admin.php");
        exit();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cita Odontológica</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/editar_cita.module.css?v=1.2">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.2">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
            <h1>
                <i class="fas fa-calendar-edit"></i>
                Editar Cita Odontológica
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="id" value="<?= $cita['id_cita'] ?>">

                <div class="info-grid">
                    <div class="form-group">
                        <label class="form-label required">Nombre del Paciente:</label>
                        <input type="text" name="nombre_paciente" class="form-control" required
                               value="<?= htmlspecialchars($cita['nombre_paciente'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Odontólogo:</label>
                        <select name="odontologo" class="form-control" required>
                            <?php foreach($odontologos as $o): ?>
                                <option value="<?= $o['id_odontologo'] ?>"
                                    <?= ($cita['id_odontologo'] == $o['id_odontologo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($o['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Fecha y Hora:</label>
                        <input type="datetime-local" name="fecha_hora" id="fecha_hora" class="form-control" required
                               value="<?= date('Y-m-d\TH:i', strtotime($cita['fecha_hora'])) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Motivo:</label>
                        <select name="motivo" class="form-control" required>
                            <?php foreach($motivos as $m): ?>
                                <option value="<?= $m['id_motivo'] ?>"
                                    <?= ($cita['motivo'] == $m['id_motivo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado:</label>
                        <select name="estado" class="form-control">
                            <option value="programada" <?= ($cita['estado'] == 'programada') ? 'selected' : '' ?>>Programada</option>
                            <option value="completada" <?= ($cita['estado'] == 'completada') ? 'selected' : '' ?>>Completada</option>
                            <option value="cancelada" <?= ($cita['estado'] == 'cancelada') ? 'selected' : '' ?>>Cancelada</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Comentarios:</label>
                        <textarea name="comentarios" class="form-control" rows="2"><?= htmlspecialchars($cita['comentarios'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="window.history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script>
        // Initialize Flatpickr for datetime
        flatpickr("#fecha_hora", {
            enableTime: true,
            time_24hr: true,
            minuteIncrement: 15,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "j F Y - H:i",
            locale: "es",
            minDate: "today",
            minTime: "08:00",
            maxTime: "20:00",
            disable: [
                function(date) {
                    // Disable weekends
                    return (date.getDay() === 0 || date.getDay() === 6);
                }
            ]
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

            // Toggle sidebar on mobile
            document.getElementById('menuToggle').addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });

            // Close sidebar when clicking outside on mobile
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