<?php
require_once 'auth_check.php';
require_once 'conexion.php';

if(!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id_item = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("DELETE FROM InventarioDental WHERE id_item = ?");
    $stmt->execute([$id_item]);
    
    header("Location: inventory_list.php?success=Item eliminado correctamente");
    exit();
} catch(PDOException $e) {
    die("Error al eliminar: " . $e->getMessage());
}