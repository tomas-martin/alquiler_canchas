<?php
require_once 'config.php';

// Obtener fecha seleccionada o fecha actual
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Validar que la fecha no sea anterior a hoy
if ($fecha < date('Y-m-d')) {
    $fecha = date('Y-m-d');
}

// Obtener todas las canchas activas
$conn = getConnection();
$stmt = $conn->query("SELECT * FROM canchas WHERE activa = TRUE ORDER BY id");
$canchas = $stmt->fetchAll();

// Obtener reservas para la fecha seleccionada (solo confirmadas)
$stmt = $conn->prepare("
    SELECT r.*, c.nombre as cancha_nombre, cl.nombre as cliente_nombre, cl.telefono
    FROM reservas r
    JOIN canchas c ON r.cancha_id = c.id
    JOIN clientes cl ON r.cliente_id = cl.id
    WHERE r.fecha = ? AND r.estado = 'confirmada'
");
$stmt->execute([$fecha]);
$reservas = $stmt->fetchAll();

// Crear array de disponibilidad
$disponibilidad = [];
foreach ($reservas as $reserva) {
    $disponibilidad[$reserva['cancha_id']][$reserva['hora_inicio']] = $reserva;
}

$horarios = generarHorarios();

// Calcular estadísticas del día
$total_reservas_dia = count($reservas);
$ingresos_dia = array_sum(array_column($reservas, 'total'));
$ocupacion = count($reservas);
$total_slots = count($canchas) * count($horarios);
$porcentaje_ocupacion = $total_slots > 0 ? ($ocupacion / $total_slots) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Reservas - Canchas de Fútbol</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .nav {
            display: flex;
            gap: 15px;
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            flex-wrap: wrap;
        }
        .nav a {
            padding: 10px 20px;
            background: white;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            border: 2px solid #ddd;
            transition: all 0.3s;
        }
        .nav a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .content {
            padding: 30px;
        }
        .fecha-selector {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .fecha-selector h2 {
            margin-bottom: 15px;
            color: #333;
        }
        .fecha-selector input {
            padding: 12px 20px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 5px;
            margin-right: 10px;
        }
        .btn {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-mini {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-mini h4 {
            font-size: 0.85em;
            margin-bottom: 5px;
            opacity: 0.9;
        }
        .stat-mini .value {
            font-size: 1.8em;
            font-weight: bold;
        }
        .tabla-reservas {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow-x: auto;
            display: block;
        }
        .tabla-reservas table {
            width: 100%;
            min-width: 800px;
        }
        .tabla-reservas th {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .tabla-reservas td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            min-width: 150px;
        }
        .tabla-reservas tbody tr:hover {
            background: #f8f9fa;
        }
        .slot {
            padding: 10px;
            text-align: center;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .disponible {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        .disponible:hover {
            background: #28a745;
            color: white;
            transform: scale(1.05);
            box-shadow: 0 3px 10px rgba(40, 167, 69, 0.3);
        }
        .ocupado {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
            cursor: default;
        }
        .ocupado:hover {
            transform: none;
        }
        .ocupado .cliente-info {
            font-size: 0.85em;
            margin-top: 5px;
            font-weight: normal;
        }
        .legend {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .legend-color {
            width: 30px;
            height: 30px;
            border-radius: 5px;
        }
        .fecha-actual {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border: 2px solid #3498db;
        }
        .fecha-actual h3 {
            color: #2980b9;
            font-size: 1.5em;
        }
        @media (max-width: 768px) {
            .tabla-reservas {
                font-size: 12px;
            }
            .slot {
                padding: 5px;
                min-height: 50px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚽ Sistema de Reservas</h1>
            <p>Canchas de Fútbol - Horario: 16:00 a 00:00</p>
        </div>
        
        <div class="nav">
            <a href="index.php">📅 Reservas</a>
            <a href="nueva_reserva.php">➕ Nueva Reserva</a>
            <a href="mis_reservas.php">📋 Mis Reservas</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="admin.php">⚙️ Administración</a>
        </div>
        
        <div class="content">
            <div class="fecha-selector">
                <h2>📅 Seleccionar Fecha</h2>
                <form method="GET" style="margin-top: 15px;">
                    <input type="date" name="fecha" value="<?php echo $fecha; ?>" min="<?php echo date('Y-m-d'); ?>">
                    <button type="submit" class="btn">Ver Disponibilidad</button>
                    <a href="index.php" class="btn" style="background: #95a5a6; text-decoration: none; display: inline-block;">🔄 Hoy</a>
                </form>
            </div>
            
            <div class="fecha-actual">
                <h3>Mostrando: <?php echo date('l d/m/Y', strtotime($fecha)); ?></h3>
            </div>
            
            <div class="stats-mini">
                <div class="stat-mini" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
                    <h4>Reservas del Día</h4>
                    <div class="value"><?php echo $total_reservas_dia; ?></div>
                </div>
                <div class="stat-mini" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                    <h4>Ingresos del Día</h4>
                    <div class="value">$<?php echo number_format($ingresos_dia, 0, ',', '.'); ?></div>
                </div>
                <div class="stat-mini" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                    <h4>Ocupación</h4>
                    <div class="value"><?php echo round($porcentaje_ocupacion); ?>%</div>
                </div>
            </div>
            
            <h2 style="margin-bottom: 15px;">📊 Disponibilidad de Canchas</h2>
            
            <div class="tabla-reservas">
                <table>
                    <thead>
                        <tr>
                            <th style="min-width: 100px;">⏰ Hora</th>
                            <?php foreach ($canchas as $cancha): ?>
                                <th>
                                    <?php echo htmlspecialchars($cancha['nombre']); ?><br>
                                    <small style="font-weight: normal; opacity: 0.9;">
                                        $<?php echo number_format($cancha['precio_hora'], 0, ',', '.'); ?>/hora
                                    </small>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($horarios as $hora): ?>
                            <tr>
                                <td style="font-weight: bold; font-size: 1.1em; background: #f8f9fa;">
                                    <?php echo $hora; ?>
                                </td>
                                <?php foreach ($canchas as $cancha): ?>
                                    <td>
                                        <?php if (isset($disponibilidad[$cancha['id']][$hora])): ?>
                                            <div class="slot ocupado" title="Reservado por: <?php echo htmlspecialchars($disponibilidad[$cancha['id']][$hora]['cliente_nombre']); ?>">
                                                ❌ ALQUILADA
                                                <div class="cliente-info">
                                                    <?php echo htmlspecialchars($disponibilidad[$cancha['id']][$hora]['cliente_nombre']); ?><br>
                                                    📞 <?php echo htmlspecialchars($disponibilidad[$cancha['id']][$hora]['telefono']); ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <a href="nueva_reserva.php?cancha=<?php echo $cancha['id']; ?>&fecha=<?php echo $fecha; ?>&hora=<?php echo $hora; ?>" 
                                               style="text-decoration: none; color: inherit;">
                                                <div class="slot disponible" title="Click para reservar">
                                                    ✅ DISPONIBLE
                                                </div>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color disponible"></div>
                    <span><strong>✅ Disponible</strong> - Click para reservar</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color ocupado"></div>
                    <span><strong>❌ Alquilada</strong> - Ya está reservada</span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh cada 30 segundos si estamos viendo el día actual
        <?php if ($fecha == date('Y-m-d')): ?>
        setTimeout(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>