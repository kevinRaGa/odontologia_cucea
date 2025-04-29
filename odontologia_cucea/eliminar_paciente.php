<?php

// Database configuration
require_once 'conexion.php';
require_once 'auth_check.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    try {
        $pdo->beginTransaction();
        
        // First delete related consultations
        $stmt = $pdo->prepare("DELETE FROM Consulta WHERE id_paciente = ?");
        $stmt->execute([intval($_POST['id'])]);
        
        // Then delete the patient
        $stmt = $pdo->prepare("DELETE FROM Paciente WHERE id_paciente = ?");
        $stmt->execute([intval($_POST['id'])]);
        
        $pdo->commit();
        
        header("Location: pacientes.php");
        exit();
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        die("Error al eliminar: " . $e->getMessage());
    }
}