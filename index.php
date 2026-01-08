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

// Obtener SOLO reservas confirmadas, pendientes y completadas (no canceladas)
$stmt = $conn->prepare("
    SELECT r.*, c.nombre as cancha_nombre, cl.nombre as cliente_nombre, cl.telefono
    FROM reservas r
    JOIN canchas c ON r.cancha_id = c.id
    JOIN clientes cl ON r.cliente_id = cl.id
    WHERE r.fecha = ? AND r.estado IN ('confirmada', 'pendiente', 'completada')
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
            position: relative;
        }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .actualizar-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .actualizar-btn:hover {
            background: white;
            color: #2ecc71;
        }
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
            position: relative;
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
            cursor: not-allowed;
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
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loading.active {
            display: flex;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            display: none;
        }
        .alert.show {
            display: block;
            animation: slideDown 0.3s;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffc107;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 0.75em;
            border-radius: 3px;
            margin-left: 5px;
        }
        .badge-warning {
            background: #ffc107;
            color: #000;
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
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <div class="container">
        <div class="header">
            <h1>⚽ Sistema de Reservas</h1>
            <p>Canchas de Fútbol - Horario: 16:00 a 00:00</p>
            <button class="actualizar-btn" onclick="location.reload()">🔄 Actualizar</button>
        </div>
        
        <div class="nav">
            <a href="index.php">📅 Reservas</a>
            <a href="nueva_reserva.php">➕ Nueva Reserva</a>
            <a href="mis_reservas.php">📋 Mis Reservas</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="admin.php">⚙️ Administración</a>
        </div>
        
        <div class="content">
            <div id="alert-container"></div>

            <div class="fecha-selector">
                <h2>📅 Seleccionar Fecha</h2>
                <form method="GET" style="margin-top: 15px;">
                    <input type="date" name="fecha" value="<?php echo $fecha; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                    <button type="submit" class="btn">Ver Disponibilidad</button>
                    <a href="index.php" class="btn" style="background: #95a5a6; text-decoration: none; display: inline-block;">🔄 Hoy</a>
                </form>
            </div>
            
            <div class="fecha-actual">
                <h3>Mostrando: <?php 
                    $dias_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    echo $dias_es[date('w', strtotime($fecha))] . ' ' . date('d/m/Y', strtotime($fecha)); 
                ?></h3>
            </div>
            
            <div class="stats-mini">
                <div class="stat-mini" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
                    <h4>Reservas del Día</h4>
                    <div class="value"><?php echo $total_reservas_dia; ?></div>
                </div>
                <div class="stat-mini" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                    <h4>Ingresos del Día</h4>
                    <div class="value"><?php echo formatearMoneda($ingresos_dia); ?></div>
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
                                    <?php echo sanitizar($cancha['nombre']); ?><br>
                                    <small style="font-weight: normal; opacity: 0.9;">
                                        <?php echo formatearMoneda($cancha['precio_hora']); ?>/hora
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
                                        <?php if (isset($disponibilidad[$cancha['id']][$hora])): 
                                            $reserva = $disponibilidad[$cancha['id']][$hora];
                                        ?>
                                            <div class="slot ocupado" 
                                                 title="Reservado por: <?php echo sanitizar($reserva['cliente_nombre']); ?>"
                                                 data-cancha="<?php echo $cancha['id']; ?>"
                                                 data-fecha="<?php echo $fecha; ?>"
                                                 data-hora="<?php echo $hora; ?>">
                                                ❌ OCUPADA
                                                <?php if ($reserva['estado'] !== 'confirmada'): ?>
                                                    <span class="badge badge-warning"><?php echo strtoupper($reserva['estado']); ?></span>
                                                <?php endif; ?>
                                                <div class="cliente-info">
                                                    <?php echo sanitizar($reserva['cliente_nombre']); ?><br>
                                                    📞 <?php echo sanitizar($reserva['telefono']); ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <a href="#" 
                                               onclick="verificarYReservar(event, <?php echo $cancha['id']; ?>, '<?php echo $fecha; ?>', '<?php echo $hora; ?>')"
                                               style="text-decoration: none; color: inherit;">
                                                <div class="slot disponible" 
                                                     title="Click para reservar"
                                                     data-cancha="<?php echo $cancha['id']; ?>"
                                                     data-fecha="<?php echo $fecha; ?>"
                                                     data-hora="<?php echo $hora; ?>">
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
                    <span><strong>❌ Ocupada</strong> - Ya está reservada</span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh cada 30 segundos si estamos viendo el día actual
        <?php if ($fecha == date('Y-m-d')): ?>
        let autoRefreshInterval = setInterval(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>

        // Verificar disponibilidad en tiempo real antes de redirigir
        async function verificarYReservar(event, canchaId, fecha, hora) {
            event.preventDefault();
            
            const loading = document.getElementById('loading');
            loading.classList.add('active');
            
            try {
                const response = await fetch('verificar_disponibilidad.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        cancha_id: canchaId,
                        fecha: fecha,
                        hora: hora
                    })
                });
                
                const data = await response.json();
                
                if (data.disponible) {
                    // Redirigir a nueva reserva
                    window.location.href = `nueva_reserva.php?cancha=${canchaId}&fecha=${fecha}&hora=${hora}`;
                } else {
                    mostrarAlerta('⚠️ Este horario acaba de ser reservado por otro usuario. La página se actualizará.', 'warning');
                    setTimeout(() => location.reload(), 2000);
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarAlerta('⚠️ Error al verificar disponibilidad. Intente nuevamente.', 'warning');
            } finally {
                loading.classList.remove('active');
            }
        }

        function mostrarAlerta(mensaje, tipo = 'warning') {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${tipo} show`;
            alert.textContent = mensaje;
            alertContainer.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // Prevenir doble click en slots
        document.querySelectorAll('.slot').forEach(slot => {
            slot.addEventListener('click', function(e) {
                if (this.classList.contains('ocupado')) {
                    e.preventDefault();
                    mostrarAlerta('⚠️ Este horario ya está ocupado', 'warning');
                }
            });
        });
    </script>
</body>
</html>