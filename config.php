<?php
// Configuración de error reporting para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de zona horaria
date_default_timezone_set('America/Argentina/Mendoza');

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'alquiler_canchas');

// Configuración de horarios
define('HORA_APERTURA', 16);
define('HORA_CIERRE', 24);

// Configuración de la aplicación
define('TIEMPO_SESION', 7200); // 2 horas
define('MONEDA', 'ARS');
define('SIMBOLO_MONEDA', '$');

// Conexión a la base de datos con manejo de errores mejorado
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );
        } catch(PDOException $e) {
            error_log("Error de conexión a BD: " . $e->getMessage());
            die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
        }
    }
    
    return $conn;
}

// Función para generar horarios disponibles
function generarHorarios() {
    $horarios = [];
    for ($i = HORA_APERTURA; $i < HORA_CIERRE; $i++) {
        $horarios[] = sprintf("%02d:00", $i);
    }
    return $horarios;
}

// Función para verificar disponibilidad REAL de una cancha
function verificarDisponibilidad($cancha_id, $fecha, $hora_inicio, $horas = 1) {
    $conn = getConnection();
    
    try {
        $hora_inicio_int = (int)substr($hora_inicio, 0, 2);
        $horas_ocupadas = [];
        
        for ($i = 0; $i < $horas; $i++) {
            $hora_verificar = sprintf("%02d:00", $hora_inicio_int + $i);
            
            $stmt = $conn->prepare("
                SELECT COUNT(*) as cantidad
                FROM reservas 
                WHERE cancha_id = ? 
                AND fecha = ? 
                AND hora_inicio = ?
                AND estado IN ('pendiente', 'confirmada', 'completada')
                FOR UPDATE
            ");
            
            $stmt->execute([$cancha_id, $fecha, $hora_verificar]);
            $resultado = $stmt->fetch();
            
            if ($resultado['cantidad'] > 0) {
                $horas_ocupadas[] = $hora_verificar;
            }
        }
        
        return [
            'disponible' => empty($horas_ocupadas),
            'horas_ocupadas' => $horas_ocupadas
        ];
        
    } catch (PDOException $e) {
        error_log("Error al verificar disponibilidad: " . $e->getMessage());
        return [
            'disponible' => false,
            'horas_ocupadas' => [],
            'error' => 'Error al verificar disponibilidad'
        ];
    }
}

// Función para crear reserva de forma segura
function crearReservaSegura($datos) {
    $conn = getConnection();
    
    try {
        $conn->beginTransaction();
        
        // 1. Verificar disponibilidad con bloqueo
        $disponibilidad = verificarDisponibilidad(
            $datos['cancha_id'],
            $datos['fecha'],
            $datos['hora_inicio'],
            $datos['horas']
        );
        
        if (!$disponibilidad['disponible']) {
            $conn->rollBack();
            return [
                'success' => false,
                'mensaje' => 'Las siguientes horas ya están ocupadas: ' . implode(', ', $disponibilidad['horas_ocupadas'])
            ];
        }
        
        // 2. Insertar o actualizar cliente
        $stmt = $conn->prepare("SELECT id FROM clientes WHERE telefono = ? FOR UPDATE");
        $stmt->execute([$datos['telefono']]);
        $cliente = $stmt->fetch();
        
        if ($cliente) {
            $cliente_id = $cliente['id'];
            $stmt = $conn->prepare("
                UPDATE clientes 
                SET nombre = ?, email = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $datos['nombre'],
                $datos['email'] ?? null,
                $cliente_id
            ]);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO clientes (nombre, telefono, email) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $datos['nombre'],
                $datos['telefono'],
                $datos['email'] ?? null
            ]);
            $cliente_id = $conn->lastInsertId();
        }
        
        // 3. Obtener precio de la cancha
        $stmt = $conn->prepare("SELECT precio_hora FROM canchas WHERE id = ? AND activa = 1");
        $stmt->execute([$datos['cancha_id']]);
        $cancha = $stmt->fetch();
        
        if (!$cancha) {
            $conn->rollBack();
            return [
                'success' => false,
                'mensaje' => 'La cancha seleccionada no está disponible'
            ];
        }
        
        $precio_hora = $cancha['precio_hora'];
        $total = $precio_hora * $datos['horas'];
        
        // 4. Crear reservas para cada hora
        $stmt = $conn->prepare("
            INSERT INTO reservas (
                cancha_id, cliente_id, fecha, hora_inicio, hora_fin,
                total, estado, seña, saldo_pendiente, metodo_pago, notas
            ) VALUES (?, ?, ?, ?, ?, ?, 'confirmada', ?, ?, ?, ?)
        ");
        
        $hora_inicio_int = (int)substr($datos['hora_inicio'], 0, 2);
        $primera_reserva_id = null;
        
        for ($i = 0; $i < $datos['horas']; $i++) {
            $hora_actual = sprintf("%02d:00", $hora_inicio_int + $i);
            $hora_siguiente = sprintf("%02d:00", $hora_inicio_int + $i + 1);
            
            $seña_hora = ($datos['seña'] ?? 0) / $datos['horas'];
            $saldo_hora = $precio_hora - $seña_hora;
            
            $stmt->execute([
                $datos['cancha_id'],
                $cliente_id,
                $datos['fecha'],
                $hora_actual,
                $hora_siguiente,
                $precio_hora,
                $seña_hora,
                $saldo_hora,
                $datos['metodo_pago'] ?? 'efectivo',
                $datos['notas'] ?? null
            ]);
            
            if ($i === 0) {
                $primera_reserva_id = $conn->lastInsertId();
            }
        }
        
        $conn->commit();
        
        return [
            'success' => true,
            'mensaje' => 'Reserva creada exitosamente',
            'reserva_id' => $primera_reserva_id,
            'total' => $total
        ];
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error al crear reserva: " . $e->getMessage());
        
        if (strpos($e->getMessage(), 'reserva_unica_activa') !== false) {
            return [
                'success' => false,
                'mensaje' => 'Ya existe una reserva activa en ese horario'
            ];
        }
        
        return [
            'success' => false,
            'mensaje' => 'Error al procesar la reserva. Por favor, intente nuevamente.'
        ];
    }
}

// Función para cancelar reserva
function cancelarReserva($reserva_id, $motivo = null, $usuario = 'sistema') {
    $conn = getConnection();
    
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("
            UPDATE reservas 
            SET estado = 'cancelada',
                cancelada_por = ?,
                motivo_cancelacion = ?,
                updated_at = NOW()
            WHERE id = ? AND estado IN ('pendiente', 'confirmada')
        ");
        
        $stmt->execute([$usuario, $motivo, $reserva_id]);
        
        if ($stmt->rowCount() > 0) {
            // Registrar en historial
            $stmt = $conn->prepare("
                INSERT INTO historial_reservas (reserva_id, accion, usuario, detalles)
                VALUES (?, 'cancelada', ?, ?)
            ");
            $stmt->execute([$reserva_id, $usuario, $motivo]);
            
            $conn->commit();
            return ['success' => true, 'mensaje' => 'Reserva cancelada exitosamente'];
        } else {
            $conn->rollBack();
            return ['success' => false, 'mensaje' => 'No se pudo cancelar la reserva'];
        }
        
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error al cancelar reserva: " . $e->getMessage());
        return ['success' => false, 'mensaje' => 'Error al cancelar la reserva'];
    }
}

// Función para sanitizar entradas
function sanitizar($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

// Función para formatear moneda
function formatearMoneda($monto) {
    return SIMBOLO_MONEDA . number_format($monto, 0, ',', '.');
}

// Iniciar sesión con configuración segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Cambiar a 1 si usas HTTPS
    session_start();
}
?>