<?php
require_once 'config.php';

header('Content-Type: application/json');

$cancha_id = isset($_GET['cancha']) ? (int)$_GET['cancha'] : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

$conn = getConnection();

$stmt = $conn->prepare("
    SELECT hora_inicio 
    FROM reservas 
    WHERE cancha_id = ? 
    AND fecha = ? 
    AND estado != 'cancelada'
");

$stmt->execute([$cancha_id, $fecha]);
$horarios_ocupados = array_column($stmt->fetchAll(), 'hora_inicio');

echo json_encode($horarios_ocupados);
?>