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
    $hora_inicio = substr($reserva['hora_inicio'], 0, 5); // HH:MM
    $hora_fin    = substr($reserva['hora_fin'], 0, 5);    // HH:MM

    $hora_actual = strtotime($hora_inicio);
    $hora_fin_ts = strtotime($hora_fin);

    while ($hora_actual < $hora_fin_ts) {
        $hora_slot = date('H:i', $hora_actual);
        $disponibilidad[$reserva['cancha_id']][$hora_slot] = $reserva;
        $hora_actual = strtotime('+1 hour', $hora_actual);
    }
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
    <title>⚽ Sistema de Reservas - Canchas de Fútbol</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        :root {
            --dark-bg: #0a0e27;
            --card-bg: #141b2d;
            --accent-green: #00ff87;
            --accent-blue: #00d9ff;
            --accent-red: #ff0055;
            --text-light: #ffffff;
            --text-gray: #a0aec0;
            --border-color: #1e2a47;
            --success: #00ff87;
            --danger: #ff0055;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--dark-bg);
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(0, 255, 135, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(0, 217, 255, 0.05) 0%, transparent 50%);
            min-height: 100vh;
            color: var(--text-light);
            position: relative;
            overflow-x: hidden;
        }
        
        /* Patrón de fútbol en el fondo */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                repeating-linear-gradient(0deg, transparent, transparent 35px, rgba(0, 255, 135, 0.03) 35px, rgba(0, 255, 135, 0.03) 36px),
                repeating-linear-gradient(90deg, transparent, transparent 35px, rgba(0, 255, 135, 0.03) 35px, rgba(0, 255, 135, 0.03) 36px);
            opacity: 0.3;
            z-index: 0;
            pointer-events: none;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        .header {
            background: linear-gradient(135deg, var(--card-bg) 0%, #1a2332 100%);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .header::before {
            content: '⚽';
            position: absolute;
            font-size: 200px;
            opacity: 0.05;
            right: -50px;
            top: -50px;
            animation: spin 20s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .header h1 {
            font-size: 3em;
            background: linear-gradient(135deg, var(--accent-green), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            font-weight: 900;
            position: relative;
        }
        
        .header p {
            color: var(--text-gray);
            font-size: 1.2em;
        }
        
        .actualizar-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 255, 135, 0.1);
            border: 2px solid var(--accent-green);
            color: var(--accent-green);
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .actualizar-btn:hover {
            background: var(--accent-green);
            color: var(--dark-bg);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 255, 135, 0.3);
        }
        
        .nav {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .nav a {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            color: var(--text-light);
            text-decoration: none;
            padding: 18px 25px;
            border-radius: 15px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 255, 135, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .nav a:hover::before {
            left: 100%;
        }
        
        .nav a:hover {
            border-color: var(--accent-green);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 255, 135, 0.2);
        }
        
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-mini {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 25px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .stat-mini::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-green);
        }
        
        .stat-mini:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 255, 135, 0.2);
        }
        
        .stat-mini.ocupacion::before { background: var(--accent-blue); }
        .stat-mini.ingresos::before { background: #ffd700; }
        
        .stat-mini h4 {
            color: var(--text-gray);
            font-size: 0.9em;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-mini .value {
            font-size: 2.5em;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent-green), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .fecha-selector {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .fecha-selector h2 {
            color: var(--accent-green);
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .fecha-selector input {
            background: var(--dark-bg);
            border: 2px solid var(--border-color);
            color: var(--text-light);
            padding: 15px 20px;
            border-radius: 10px;
            font-size: 16px;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .fecha-selector input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 20px rgba(0, 255, 135, 0.2);
        }
        
        .btn {
            background: linear-gradient(135deg, var(--accent-green), #00cc6a);
            color: var(--dark-bg);
            border: none;
            padding: 15px 35px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 255, 135, 0.4);
        }
        
        .btn-secondary {
            background: var(--card-bg);
            color: var(--text-light);
            border: 2px solid var(--border-color);
        }
        
        .fecha-actual {
            background: linear-gradient(135deg, rgba(0, 255, 135, 0.1), rgba(0, 217, 255, 0.1));
            border: 2px solid var(--accent-green);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .fecha-actual h3 {
            color: var(--accent-green);
            font-size: 1.5em;
        }
        
        .tabla-reservas {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 20px;
            overflow-x: auto;
        }
        
        .tabla-reservas table {
            width: 100%;
            min-width: 1000px;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .tabla-reservas th {
            background: linear-gradient(135deg, var(--dark-bg), #151d30);
            color: var(--accent-green);
            padding: 20px 15px;
            text-align: left;
            font-weight: 700;
            border-bottom: 2px solid var(--accent-green);
            position: sticky;
            top: 0;
            z-index: 10;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.85em;
        }
        
        .tabla-reservas td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .tabla-reservas tbody tr {
            transition: all 0.3s;
        }
        
        .tabla-reservas tbody tr:hover {
            background: rgba(0, 255, 135, 0.05);
        }
        
        .slot {
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            border: 2px solid transparent;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .disponible {
            background: linear-gradient(135deg, rgba(0, 255, 135, 0.1), rgba(0, 255, 135, 0.05));
            border-color: var(--accent-green);
            color: var(--accent-green);
        }
        
        .disponible::before {
            content: '⚽';
            position: absolute;
            font-size: 40px;
            opacity: 0.1;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .disponible:hover {
            background: var(--accent-green);
            color: var(--dark-bg);
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(0, 255, 135, 0.3);
        }
        
        .ocupado {
            background: linear-gradient(135deg, rgba(255, 0, 85, 0.2), rgba(255, 0, 85, 0.1));
            border-color: var(--danger);
            color: var(--danger);
            cursor: not-allowed;
        }
        
        .ocupado .cliente-info {
            font-size: 0.8em;
            margin-top: 8px;
            font-weight: 500;
            color: var(--text-gray);
            letter-spacing: 0;
            text-transform: none;
        }
        
        .legend {
            display: flex;
            gap: 30px;
            justify-content: center;
            margin-top: 30px;
            padding: 25px;
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 15px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .legend-color {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid;
        }
        
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 14, 39, 0.95);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading.active {
            display: flex;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(0, 255, 135, 0.2);
            border-top: 4px solid var(--accent-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .alert {
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
            display: none;
            border-left: 4px solid;
            background: var(--card-bg);
        }
        
        .alert.show {
            display: block;
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .alert-warning {
            border-color: #ffd700;
            color: #ffd700;
        }
        
        @media (max-width: 768px) {
            .header h1 { font-size: 2em; }
            .tabla-reservas { padding: 10px; }
            .slot { padding: 10px; min-height: 60px; font-size: 11px; }
            .nav { grid-template-columns: 1fr; }
        }
        
        /* Scroll personalizado */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--dark-bg);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--accent-green);
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent-blue);
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-futbol"></i> SISTEMA DE RESERVAS</h1>
            <p>Canchas de Fútbol Profesional | Horario: 16:00 - 00:00</p>
            <button class="actualizar-btn" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>
        </div>
        
        <div class="nav">
            <a href="index.php"><i class="fas fa-calendar-alt"></i> Reservas</a>
            <a href="nueva_reserva.php"><i class="fas fa-plus-circle"></i> Nueva Reserva</a>
            <a href="mis_reservas.php"><i class="fas fa-list"></i> Mis Reservas</a>
            <a href="reportes.php"><i class="fas fa-chart-line"></i> Reportes</a>
            <a href="admin.php"><i class="fas fa-cog"></i> Administración</a>
        </div>
        
        <div id="alert-container"></div>

        <div class="fecha-selector">
            <h2><i class="fas fa-calendar-day"></i> Seleccionar Fecha</h2>
            <form method="GET" style="margin-top: 20px;">
                <input type="date" name="fecha" value="<?php echo $fecha; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                <button type="submit" class="btn">Ver Disponibilidad</button>
                <a href="index.php" class="btn btn-secondary" style="text-decoration: none; display: inline-block; padding: 15px 35px;">
                    <i class="fas fa-redo"></i> Hoy
                </a>
            </form>
        </div>
        
        <div class="fecha-actual">
            <h3>
                <i class="fas fa-calendar-check"></i>
                <?php 
                    $dias_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    echo $dias_es[date('w', strtotime($fecha))] . ' ' . date('d/m/Y', strtotime($fecha)); 
                ?>
            </h3>
        </div>
        
        <div class="stats-mini">
            <div class="stat-mini">
                <h4><i class="fas fa-clipboard-check"></i> Reservas del Día</h4>
                <div class="value"><?php echo $total_reservas_dia; ?></div>
            </div>
            <div class="stat-mini ingresos">
                <h4><i class="fas fa-dollar-sign"></i> Ingresos del Día</h4>
                <div class="value"><?php echo formatearMoneda($ingresos_dia); ?></div>
            </div>
            <div class="stat-mini ocupacion">
                <h4><i class="fas fa-percentage"></i> Ocupación</h4>
                <div class="value"><?php echo round($porcentaje_ocupacion); ?>%</div>
            </div>
        </div>
        
        <h2 style="margin-bottom: 20px; color: var(--accent-green); font-size: 2em;">
            <i class="fas fa-calendar-alt"></i> Disponibilidad de Canchas
        </h2>
        
        <div class="tabla-reservas">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-clock"></i> HORA</th>
                        <?php foreach ($canchas as $cancha): ?>
                            <th>
                                <i class="fas fa-futbol"></i> <?php echo sanitizar($cancha['nombre']); ?><br>
                                <small style="font-weight: normal; opacity: 0.7;">
                                    <?php echo formatearMoneda($cancha['precio_hora']); ?>/hora
                                </small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($horarios as $hora): ?>
                        <tr>
                            <td style="font-weight: bold; font-size: 1.2em; color: var(--accent-green);">
                                <i class="fas fa-clock"></i> <?php echo $hora; ?>
                            </td>
                            <?php foreach ($canchas as $cancha): ?>
                                <td>
                                    <?php if (isset($disponibilidad[$cancha['id']][$hora])): 
                                        $reserva = $disponibilidad[$cancha['id']][$hora];
                                    ?>
                                        <div class="slot ocupado" 
                                             title="Reservado por: <?php echo sanitizar($reserva['cliente_nombre']); ?>">
                                            <i class="fas fa-times-circle"></i> OCUPADA
                                            <div class="cliente-info">
                                                <i class="fas fa-user"></i> <?php echo sanitizar($reserva['cliente_nombre']); ?><br>
                                                <i class="fas fa-phone"></i> <?php echo sanitizar($reserva['telefono']); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <a href="#" 
                                           onclick="verificarYReservar(event, <?php echo $cancha['id']; ?>, '<?php echo $fecha; ?>', '<?php echo $hora; ?>')"
                                           style="text-decoration: none; color: inherit;">
                                            <div class="slot disponible" title="Click para reservar">
                                                <i class="fas fa-check-circle"></i> DISPONIBLE
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
                <span><strong><i class="fas fa-check-circle"></i> DISPONIBLE</strong> - Click para reservar</span>
            </div>
            <div class="legend-item">
                <div class="legend-color ocupado"></div>
                <span><strong><i class="fas fa-times-circle"></i> OCUPADA</strong> - Ya está reservada</span>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh cada 30 segundos
        <?php if ($fecha == date('Y-m-d')): ?>
        setInterval(() => location.reload(), 30000);
        <?php endif; ?>

        async function verificarYReservar(event, canchaId, fecha, hora) {
            event.preventDefault();
            const loading = document.getElementById('loading');
            loading.classList.add('active');
            
            try {
                const response = await fetch('verificar_disponibilidad.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ cancha_id: canchaId, fecha: fecha, hora: hora })
                });
                
                const data = await response.json();
                
                if (data.disponible) {
                    window.location.href = `nueva_reserva.php?cancha=${canchaId}&fecha=${fecha}&hora=${hora}`;
                } else {
                    mostrarAlerta('⚠️ Este horario acaba de ser reservado. Actualizando...', 'warning');
                    setTimeout(() => location.reload(), 2000);
                }
            } catch (error) {
                mostrarAlerta('⚠️ Error al verificar disponibilidad.', 'warning');
            } finally {
                loading.classList.remove('active');
            }
        }

        function mostrarAlerta(mensaje, tipo = 'warning') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${tipo} show`;
            alert.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${mensaje}`;
            document.getElementById('alert-container').appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        document.querySelectorAll('.slot.ocupado').forEach(slot => {
            slot.addEventListener('click', (e) => {
                e.preventDefault();
                mostrarAlerta('⚠️ Este horario ya está ocupado', 'warning');
            });
        });
    </script>
</body>
</html>