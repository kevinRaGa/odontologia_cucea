<?php
session_start();
require_once 'conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso Denegado</title>
    <link rel="stylesheet" href="css/acceso_denegado.module.css">
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-ban"></i> Acceso Denegado</h1>
        <p>No tienes permiso para acceder a esta sección.</p>
        <div>
            <a href="javascript:history.back()" class="btn">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <a href="logout.php" class="btn">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </div>
</body>
</html>