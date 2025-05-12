<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_odontologo();

// Verify appointment ID
if (!isset($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$id_cita = intval($_GET['id']);

try {
    // Get appointment data with patient and consultation details
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            p.*,
            cons.*,
            m.nombre as motivo_nombre,
            d.nombre as diagnostico_nombre,
            pl.nombre as plan_nombre,
            o.nombre as odontologo_nombre,
            car.nombre as carrera_nombre,
            s.numero as semestre_numero,
            sx.genero as sexo_nombre
        FROM citas c
        LEFT JOIN paciente p ON c.id_paciente = p.id_paciente
        LEFT JOIN consulta cons ON c.id_paciente = cons.id_paciente AND DATE(cons.fecha) = DATE(c.fecha_hora)
        LEFT JOIN motivoconsulta m ON c.motivo = m.id_motivo
        LEFT JOIN diagnostico d ON cons.id_diagnostico = d.id_diagnostico
        LEFT JOIN plantratamiento pl ON cons.id_plan = pl.id_plan
        LEFT JOIN odontologo o ON c.id_odontologo = o.id_odontologo
        LEFT JOIN carreraempleo car ON p.id_carrera = car.id_carrera
        LEFT JOIN semestre s ON p.id_semestre = s.id_semestre
        LEFT JOIN sexo sx ON p.id_sexo = sx.id_sexo
        WHERE c.id_cita = ?
    ");
    $stmt->execute([$id_cita]);
    $cita = $stmt->fetch();

    if (!$cita) {
        header("Location: admin.php");
        exit();
    }

    // Calculate IMC if data exists
    $imc = null;
    if (!empty($cita['peso_kg']) && !empty($cita['talla_m'])) {
        $imc = round($cita['peso_kg'] / ($cita['talla_m'] * $cita['talla_m']), 2);
    }

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de la Cita</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/ver_cita.module.css?v=1.0">
    <link rel="stylesheet" href="css/sidebar.module.css?v=1.2">
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
                <i class="fas fa-eye"></i>
                Detalles de la Cita - <?= date('d/m/Y H:i', strtotime($cita['fecha_hora'])) ?>
            </h1>

            <div class="info-grid">
                <h3 class="section-title">
                    <i class="fas fa-user"></i> Información del Paciente
                </h3>

                <div class="info-card">
                    <div class="info-label">Nombre completo</div>
                    <div class="info-value"><?= htmlspecialchars($cita['nombre_paciente_temp'] ?? $cita['nombre'] ?? 'No registrado') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Edad</div>
                    <div class="info-value"><?= $cita['edad'] ?? 'No registrada' ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Código único</div>
                    <div class="info-value"><?= $cita['codigo'] ? htmlspecialchars($cita['codigo']) : 'No registrado' ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Carrera/Empleo</div>
                    <div class="info-value"><?= $cita['carrera_nombre'] ?? 'No registrada' ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Semestre</div>
                    <div class="info-value"><?= $cita['semestre_numero'] ?? 'No registrado' ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Sexo</div>
                    <div class="info-value"><?= $cita['sexo_nombre'] ?? 'No registrado' ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Enfermedad crónica</div>
                    <div class="info-value"><?= $cita['enfermedad_cronica'] ? htmlspecialchars($cita['enfermedad_cronica']) : 'No registrada' ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Peso (kg)</div>
                    <div class="info-value"><?= $cita['peso_kg'] ? number_format($cita['peso_kg'], 1) : 'No registrado' ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Talla (m)</div>
                    <div class="info-value"><?= $cita['talla_m'] ? number_format($cita['talla_m'], 2) : 'No registrada' ?></div>
                </div>

                <?php if ($imc): ?>
                <div class="info-card">
                    <div class="info-label">IMC</div>
                    <div class="info-value">
                        <?= $imc ?>
                        <span class="badge badge-<?= 
                            $imc < 18.5 ? 'info' : 
                            ($imc <= 24.9 ? 'success' : 
                            ($imc <= 29.9 ? 'warning' : 'danger')) 
                        ?>">
                            <?= 
                                $imc < 18.5 ? 'Bajo peso' : 
                                ($imc <= 24.9 ? 'Normal' : 
                                ($imc <= 29.9 ? 'Sobrepeso' : 'Obesidad')) 
                            ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="consulta-grid">
                <h3 class="section-title">
                    <i class="fas fa-notes-medical"></i> Detalles de la Consulta
                </h3>

                <div class="info-card">
                    <div class="info-label">Fecha y Hora</div>
                    <div class="info-value"><?= date('d/m/Y H:i', strtotime($cita['fecha_hora'])) ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Motivo</div>
                    <div class="info-value"><?= htmlspecialchars($cita['motivo_nombre'] ?? 'No especificado') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Odontólogo</div>
                    <div class="info-value"><?= htmlspecialchars($cita['odontologo_nombre'] ?? 'No especificado') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Diagnóstico</div>
                    <div class="info-value"><?= htmlspecialchars($cita['diagnostico_nombre'] ?? 'No especificado') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Plan de tratamiento</div>
                    <div class="info-value"><?= htmlspecialchars($cita['plan_nombre'] ?? 'No especificado') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label">Estado</div>
                    <div class="info-value">
                        <span class="badge badge-<?= 
                            $cita['estado'] == 'completada' ? 'success' : 
                            ($cita['estado'] == 'cancelada' ? 'danger' : 'info') 
                        ?>">
                            <?= ucfirst($cita['estado']) ?>
                        </span>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-label">Pago</div>
                    <div class="info-value">$<?= $cita['pago'] ? number_format($cita['pago'], 2) : '0.00' ?></div>
                </div>

                <div class="info-card full-width">
                    <div class="info-label">Comentarios</div>
                    <div class="info-value"><?= $cita['comentarios'] ? nl2br(htmlspecialchars($cita['comentarios'])) : 'No hay comentarios' ?></div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" onclick="window.history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
            </div>
        </div>
    </div>

    <script>
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