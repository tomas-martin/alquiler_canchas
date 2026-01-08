<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Cambia esto por tu contraseña de MySQL
define('DB_NAME', 'alquiler_canchas');

// Configuración de horarios
define('HORA_APERTURA', 16);
define('HORA_CIERRE', 24);

// Conexión a la base de datos
function getConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch(PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Función para generar horarios disponibles
function generarHorarios() {
    $horarios = [];
    for ($i = HORA_APERTURA; $i < HORA_CIERRE; $i++) {
        $horarios[] = sprintf("%02d:00", $i);
    }
    return $horarios;
}

// Iniciar sesión
session_start();
?>