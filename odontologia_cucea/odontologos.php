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
        $stmt = $pdo->prepare("DELETE FROM Odontologo WHERE id_odontologo = ?");
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
    FROM Odontologo o
    LEFT JOIN Roles r ON o.id_rol = r.id_rol
    WHERE o.nombre LIKE 'Dra.%' OR o.nombre LIKE 'Dr.%' OR o.nombre LIKE 'PCD%'
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
        }

        .new-dentist-btn {
            padding: 12px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .new-dentist-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }

        th {
            background-color: #3498db;
            color: white;
            padding: 12px 15px;
            text-align: left;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tr:hover {
            background-color: #f1f7fd;
        }

        .email-column {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px 10px;
            font-size: 1.2rem;
        }

        .dropdown-toggle:hover {
            color: #495057;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 4px;
            z-index: 1;
            padding: 5px 0;
        }

        .dropdown:hover .dropdown-menu {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: 8px 16px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            white-space: nowrap;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-item i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }

        .delete-form {
            display: block;
            width: 100%;
        }

        .delete-form button {
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            padding: 8px 16px;
            cursor: pointer;
            color: #333;
            font-size: 14px;
        }

        .delete-form button:hover {
            background-color: #f8f9fa;
        }

        .delete-form button i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <h1>🦷 Gestión de Odontólogos</h1>

            <?php if ($is_admin): ?>
                <a href="agregar_odontologo.php" class="new-dentist-btn">
                    <i class="fas fa-plus"></i> Nuevo Odontólogo
                </a>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Especialidad</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <?php if ($is_admin): ?>
                            <th>Acciones</th>
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
                            <td>
                                <div class="dropdown">
                                    <button class="dropdown-toggle" type="button">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="editar_odontologo.php?id=<?= $odontologo['id_odontologo'] ?>">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <form method="POST" class="delete-form">
                                            <input type="hidden" name="id" value="<?= $odontologo['id_odontologo'] ?>">
                                            <button type="submit" name="eliminar" class="dropdown-item" onclick="return confirm('¿Está seguro de eliminar este odontólogo?')">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>