<?php
require_once 'config.php';

header('Content-Type: application/json');

// Obtener datos del POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['cancha_id']) || !isset($data['fecha']) || !isset($data['hora'])) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'mensaje' => 'Datos incompletos'
    ]);
    exit;
}

$cancha_id = (int)$data['cancha_id'];
$fecha = $data['fecha'];
$hora = $data['hora'];

try {
    $conn = getConnection();
    
    // Verificar disponibilidad en tiempo real con bloqueo
    $stmt = $conn->prepare("
        SELECT COUNT(*) as cantidad
        FROM reservas 
        WHERE cancha_id = ? 
        AND fecha = ? 
        AND hora_inicio = ?
        AND estado IN ('pendiente', 'confirmada', 'completada')
    ");
    
    $stmt->execute([$cancha_id, $fecha, $hora]);
    $resultado = $stmt->fetch();
    
    $disponible = ($resultado['cantidad'] == 0);
    
    echo json_encode([
        'disponible' => $disponible,
        'mensaje' => $disponible ? 'Horario disponible' : 'Horario ocupado',
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    error_log("Error en verificar_disponibilidad: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'mensaje' => 'Error al verificar disponibilidad'
    ]);
}
?>