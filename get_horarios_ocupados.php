<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$cancha_id = isset($_GET['cancha']) ? (int)$_GET['cancha'] : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

if (!$cancha_id || !$fecha) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

try {
    $conn = getConnection();
    
    // Solo obtener reservas activas (no canceladas)
    $stmt = $conn->prepare("
        SELECT hora_inicio 
        FROM reservas 
        WHERE cancha_id = ? 
        AND fecha = ? 
        AND estado IN ('pendiente', 'confirmada', 'completada')
        ORDER BY hora_inicio
    ");
    
    $stmt->execute([$cancha_id, $fecha]);
    $horarios_ocupados = array_column($stmt->fetchAll(), 'hora_inicio');
    
    echo json_encode($horarios_ocupados);
    
} catch (PDOException $e) {
    error_log("Error en get_horarios_ocupados: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
?>