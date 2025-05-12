<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_odontologo();

$is_admin = $_SESSION['user_role'] == 1;

// Verify patient ID exists
if(!isset($_GET['id'])) {
    header("Location: pacientes.php");
    exit();
}

$id_paciente = intval($_GET['id']);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Validate required fields
        $required = ['nombre', 'edad', 'peso', 'talla', 'sexo', 'carrera'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Update patient data
        $stmt = $pdo->prepare("
            UPDATE paciente SET
                nombre = ?,
                edad = ?,
                codigo = ?,
                id_carrera = ?,
                id_semestre = ?,
                id_sexo = ?,
                enfermedad_cronica = ?,
                peso_kg = ?,
                talla_m = ?,
                correo_electronico = ?,
                telefono = ?
            WHERE id_paciente = ?
        ");
        
        $stmt->execute([
            htmlspecialchars($_POST['nombre']),
            intval($_POST['edad']),
            !empty($_POST['codigo']) ? htmlspecialchars($_POST['codigo']) : null,
            intval($_POST['carrera']),
            !empty($_POST['semestre']) ? intval($_POST['semestre']) : null,
            intval($_POST['sexo']),
            !empty($_POST['enfermedad']) ? htmlspecialchars($_POST['enfermedad']) : null,
            floatval($_POST['peso']),
            floatval($_POST['talla']),
            !empty($_POST['correo']) ? htmlspecialchars($_POST['correo']) : null,
            !empty($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : null,
            $id_paciente
        ]);

        $pdo->commit();
        header("Location: perfil_paciente.php?id_paciente=".$id_paciente);
        exit();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get patient information for form
try {
    // Basic patient info
    $stmt = $pdo->prepare("
        SELECT p.*, 
               c.nombre as carrera_nombre, 
               s.numero as semestre_numero, 
               sx.genero as sexo_nombre
        FROM paciente p
        LEFT JOIN carreraempleo c ON p.id_carrera = c.id_carrera
        LEFT JOIN semestre s ON p.id_semestre = s.id_semestre
        LEFT JOIN sexo sx ON p.id_sexo = sx.id_sexo
        WHERE p.id_paciente = ?
    ");
    $stmt->execute([$id_paciente]);
    $paciente = $stmt->fetch();

    if(!$paciente) {
        header("Location: pacientes.php");
        exit();
    }

    // Get options for dropdowns
    $carreras = $pdo->query("SELECT * FROM carreraempleo")->fetchAll();
    $semestres = $pdo->query("SELECT * FROM semestre")->fetchAll();
    $sexos = $pdo->query("SELECT * FROM sexo")->fetchAll();

} catch(PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Paciente</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/editar_paciente.module.css?v=1.1">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.1">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include 'sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="container">
            <h1>
                <i class="fas fa-user-edit"></i>
                Editar Paciente: <?= htmlspecialchars($paciente['nombre']) ?>
            </h1>
            
            <?php if(isset($error)): ?>
                <div class="alert error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <!-- Basic Information Section -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-id-card"></i> Información Básica
                    </h2>
                    <div class="info-grid">
                        <div class="form-group">
                            <label class="form-label required">Nombre completo</label>
                            <input type="text" name="nombre" class="form-control" 
                                   value="<?= htmlspecialchars($paciente['nombre']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Edad</label>
                            <input type="number" name="edad" class="form-control" min="1" max="120"
                                   value="<?= $paciente['edad'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control"
                                   value="<?= htmlspecialchars($paciente['codigo'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Carrera/Empleo</label>
                            <select name="carrera" class="form-control" required>
                                <option value="">Seleccione una opción</option>
                                <?php foreach($carreras as $c): ?>
                                    <option value="<?= $c['id_carrera'] ?>" 
                                        <?= ($paciente['id_carrera'] == $c['id_carrera']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Semestre</label>
                            <select name="semestre" class="form-control">
                                <option value="">Seleccione una opción</option>
                                <?php foreach($semestres as $s): ?>
                                    <option value="<?= $s['id_semestre'] ?>"
                                        <?= ($paciente['id_semestre'] == $s['id_semestre']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['numero']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Sexo</label>
                            <select name="sexo" class="form-control" required>
                                <option value="">Seleccione una opción</option>
                                <?php foreach($sexos as $s): ?>
                                    <option value="<?= $s['id_sexo'] ?>"
                                        <?= ($paciente['id_sexo'] == $s['id_sexo']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['genero']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Health Information Section -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-heartbeat"></i> Información de Salud
                    </h2>
                    <div class="info-grid">
                        <div class="form-group">
                            <label class="form-label">Enfermedad crónica</label>
                            <input type="text" name="enfermedad" class="form-control"
                                   value="<?= htmlspecialchars($paciente['enfermedad_cronica'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Peso (kg)</label>
                            <input type="number" step="0.1" name="peso" class="form-control" min="20" max="300"
                                   value="<?= $paciente['peso_kg'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Talla (metros)</label>
                            <input type="number" step="0.01" name="talla" class="form-control" min="1.20" max="2.50"
                                   value="<?= $paciente['talla_m'] ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i> Información Adicional
                    </h2>
                    <div class="info-grid">
                        <div class="form-group">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" name="correo" class="form-control"
                                   value="<?= htmlspecialchars($paciente['correo_electronico'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Número Telefónico</label>
                            <input type="tel" name="telefono" class="form-control"
                                   value="<?= htmlspecialchars($paciente['telefono'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="window.history.back()" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
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