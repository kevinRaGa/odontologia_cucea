<?php
// auth_check.php
session_start();

// Universal authentication check
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

// Role validation functions
function require_admin() {
    if ($_SESSION['user_role'] != 1) {
        header("Location: acceso_denegado.php");
        exit();
    }
}

function require_odontologo() {
    $allowed_roles = [1, 2, 3]; // Admin + Dentist
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        header("Location: acceso_denegado.php");
        exit();
    }
}

function require_asistente() {
    $allowed_roles = [1, 3]; // Admin + Assistant
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        header("Location: acceso_denegado.php");
        exit();
    }
}

?>