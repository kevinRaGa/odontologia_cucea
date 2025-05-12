<?php
require_once 'auth_check.php';
require_once 'conexion.php';
require_odontologo();

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM citas WHERE id_cita = ?");
        $stmt->execute([intval($_POST['id'])]);
        
        $pdo->commit();
    } catch(Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error al eliminar la cita: " . $e->getMessage();
    }
}

header("Location: admin.php");
exit();
?>