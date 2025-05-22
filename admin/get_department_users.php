<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'administrador') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

// Check if department ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID de departamento no válido']);
    exit();
}

$dept_id = (int)$_GET['id'];

// Get users assigned to this department
$stmt = $conn->prepare("SELECT id_usuario FROM usuario_departamento WHERE id_departamento = ?");
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

$user_ids = [];
while ($row = $result->fetch_assoc()) {
    $user_ids[] = $row['id_usuario'];
}

header('Content-Type: application/json');
echo json_encode($user_ids);
?>
