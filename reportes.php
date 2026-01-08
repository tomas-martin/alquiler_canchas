<?php
require_once 'config.php';

$conn = getConnection();

$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'mes_actual';
$fecha_inicio = '';
$fecha_fin = date('Y-m-d');

switch ($filtro) {
    case 'hoy':
        $fecha_inicio = date('Y-m-d');
        $fecha_fin = date('Y-m-d');
        break;
    case 'semana':
        $fecha_inicio = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'mes_actual':
        $fecha_inicio = date('Y-m-01');
        break;
    case 'mes_pasado':
        $fecha_inicio = date('Y-m-01', strtotime('first day of last month'));
        $fecha_fin = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'personalizado':
        $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
        $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
        break;
}

$ingresos_totales = $conn->prepare("
    SELECT COALESCE(SUM(total), 0) as total
    FROM reservas
    WHERE fecha BETWEEN ? AND ? AND estado = 'confirmada'
");
$ingresos_totales->execute([$fecha_inicio, $fecha_fin]);
$ingresos = $ingresos_totales->fetch()['total'];

$total_reservas = $conn->prepare("
    SELECT COUNT(*) as total
    FROM reservas
    WHERE fecha BETWEEN ? AND ? AND estado = 'confirmada'
");
$total_reservas->execute([$fecha_inicio, $fecha_fin]);
$num_reservas = $total_reservas->fetch()['total'];

$horas_reservadas = $conn->prepare("
    SELECT COUNT(*) as total
    FROM reservas
    WHERE fecha BETWEEN ? AND ? AND estado = 'confirmada'
");
$horas_reservadas->execute([$fecha_inicio, $fecha_fin]);
$horas = $horas_reservadas->fetch()['total'];

$ingresos_cancha = $conn->prepare("
    SELECT c.nombre, COALESCE(SUM(r.total), 0) as total, COUNT(r.id) as reservas
    FROM canchas c
    LEFT JOIN reservas r ON c.id = r.cancha_id 
        AND r.fecha BETWEEN ? AND ? 
        AND r.estado = 'confirmada'
    GROUP BY c.id, c.nombre
    ORDER BY total DESC
");
$ingresos_cancha->execute([$fecha_inicio, $fecha_fin]);
$canchas_stats = $ingresos_cancha->fetchAll();

$reservas_dia = $conn->prepare("
    SELECT DATE(fecha) as dia, COUNT(*) as cantidad, SUM(total) as ingresos
    FROM reservas
    WHERE fecha BETWEEN ? AND ? AND estado = 'confirmada'
    GROUP BY DATE(fecha)
    ORDER BY dia DESC
");
$reservas_dia->execute([$fecha_inicio, $fecha_fin]);
$dias_stats = $reservas_dia->fetchAll();

$top_clientes = $conn->prepare("
    SELECT cl.nombre, cl.telefono, COUNT(r.id) as reservas, SUM(r.total) as total_gastado
    FROM clientes cl
    JOIN reservas r ON cl.id = r.cliente_id
    WHERE r.fecha BETWEEN ? AND ? AND r.estado = 'confirmada'
    GROUP BY cl.id, cl.nombre, cl.telefono
    ORDER BY total_gastado DESC
    LIMIT 10
");
$top_clientes->execute([$fecha_inicio, $fecha_fin]);
$clientes_top = $top_clientes->fetchAll();

$horarios_populares = $conn->prepare("
    SELECT hora_inicio, COUNT(*) as cantidad
    FROM reservas
    WHERE fecha BETWEEN ? AND ? AND estado = 'confirmada'
    GROUP BY hora_inicio
    ORDER BY cantidad DESC
    LIMIT 5
");
$horarios_populares->execute([$fecha_inicio, $fecha_fin]);
$horarios = $horarios_populares->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Reportes e Ingresos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --dark-bg: #0a0e27;
            --card-bg: #141b2d;
            --accent-green: #00ff87;
            --accent-blue: #00d9ff;
            --accent-red: #ff0055;
            --accent-orange: #ff8c00;
            --accent-purple: #a855f7;
            --text-light: #ffffff;
            --text-gray: #a0aec0;
            --border-color: #1e2a47;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--dark-bg);
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(0, 255, 135, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(0, 217, 255, 0.05) 0%, transparent 50%);
            min-height: 100vh;
            color: var(--text-light);
            padding: 20px;
        }
        
        .container { max-width: 1800px; margin: 0 auto; }
        
        .header {
            background: linear-gradient(135deg, var(--card-bg) 0%, #1a2332 100%);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .header h1 {
            font-size: 2.5em;
            background: linear-gradient(135deg, var(--accent-orange), var(--accent-red));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            font-weight: 900;
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
            padding: 15px 20px;
            border-radius: 15px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .nav a:hover {
            border-color: var(--accent-green);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 255, 135, 0.2);
        }
        
        .filtros {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .filtros h3 {
            color: var(--accent-green);
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .filtros-btn {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .filtro-btn {
            padding: 12px 25px;
            background: var(--dark-bg);
            color: var(--text-light);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        
        .filtro-btn:hover,
        .filtro-btn.active {
            background: var(--accent-green);
            color: var(--dark-bg);
            border-color: var(--accent-green);
            transform: translateY(-2px);
        }
        
        .fecha-custom {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .fecha-custom input {
            padding: 12px 15px;
            background: var(--dark-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-light);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 25px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        
        .stat-card:nth-child(1)::before { background: var(--accent-green); }
        .stat-card:nth-child(2)::before { background: var(--accent-orange); }
        .stat-card:nth-child(3)::before { background: var(--accent-blue); }
        .stat-card:nth-child(4)::before { background: var(--accent-purple); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 255, 135, 0.2);
        }
        
        .stat-card h3 {
            color: var(--text-gray);
            font-size: 0.9em;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card .value {
            font-size: 2.5em;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent-green), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .section {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .section h2 {
            color: var(--accent-green);
            margin-bottom: 25px;
            font-size: 1.8em;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
        }
        
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            background: var(--dark-bg);
            color: var(--accent-green);
            padding: 15px;
            text-align: left;
            font-weight: 700;
            border-bottom: 2px solid var(--accent-green);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.85em;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-gray);
        }
        
        .table tr:hover {
            background: rgba(0, 255, 135, 0.05);
        }
        
        .progress-bar {
            height: 28px;
            background: var(--dark-bg);
            border-radius: 14px;
            overflow: hidden;
            margin-top: 8px;
            border: 1px solid var(--border-color);
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-green), var(--accent-blue));
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 12px;
            color: var(--dark-bg);
            font-size: 0.85em;
            font-weight: 700;
            transition: width 0.5s;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .periodo-info {
            background: linear-gradient(135deg, rgba(0, 217, 255, 0.1), rgba(0, 217, 255, 0.05));
            border: 2px solid var(--accent-blue);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .periodo-info strong {
            color: var(--accent-blue);
            font-size: 1.2em;
        }
        
        @media (max-width: 968px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> REPORTES E INGRESOS</h1>
            <p>Análisis detallado del negocio</p>
        </div>
        
        <div class="nav">
            <a href="index.php"><i class="fas fa-calendar-alt"></i> Reservas</a>
            <a href="nueva_reserva.php"><i class="fas fa-plus-circle"></i> Nueva</a>
            <a href="mis_reservas.php"><i class="fas fa-list"></i> Buscar</a>
            <a href="admin.php"><i class="fas fa-cog"></i> Admin</a>
        </div>
        
        <div class="filtros">
            <h3><i class="fas fa-filter"></i> Filtrar por período</h3>
            <div class="filtros-btn">
                <a href="?filtro=hoy" class="filtro-btn <?php echo $filtro == 'hoy' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-day"></i> Hoy
                </a>
                <a href="?filtro=semana" class="filtro-btn <?php echo $filtro == 'semana' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week"></i> Semana
                </a>
                <a href="?filtro=mes_actual" class="filtro-btn <?php echo $filtro == 'mes_actual' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> Mes Actual
                </a>
                <a href="?filtro=mes_pasado" class="filtro-btn <?php echo $filtro == 'mes_pasado' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-minus"></i> Mes Pasado
                </a>
            </div>
            <form method="GET" class="fecha-custom">
                <input type="hidden" name="filtro" value="personalizado">
                <label style="color: var(--accent-green); font-weight: 600;">Desde:</label>
                <input type="date" name="fecha_inicio" value="<?php echo $filtro == 'personalizado' ? $fecha_inicio : ''; ?>" required>
                <label style="color: var(--accent-green); font-weight: 600;">Hasta:</label>
                <input type="date" name="fecha_fin" value="<?php echo $filtro == 'personalizado' ? $fecha_fin : ''; ?>" required>
                <button type="submit" class="filtro-btn">
                    <i class="fas fa-search"></i> Aplicar
                </button>
            </form>
        </div>
        
        <div class="periodo-info">
            <strong><i class="fas fa-calendar-check"></i> Período: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></strong>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-dollar-sign"></i> Ingresos Totales</h3>
                <div class="value">$<?php echo number_format($ingresos, 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clipboard-list"></i> Total Reservas</h3>
                <div class="value"><?php echo $num_reservas; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> Horas Reservadas</h3>
                <div class="value"><?php echo $horas; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-chart-bar"></i> Promedio/Reserva</h3>
                <div class="value">$<?php echo $num_reservas > 0 ? number_format($ingresos / $num_reservas, 0, ',', '.') : '0'; ?></div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="section">
                <h2><i class="fas fa-futbol"></i> Ingresos por Cancha</h2>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cancha</th>
                                <th>Reservas</th>
                                <th>Ingresos</th>
                                <th>Rendimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $max_ingresos = !empty($canchas_stats) ? max(array_column($canchas_stats, 'total')) : 1;
                            foreach ($canchas_stats as $cancha): 
                                $porcentaje = $max_ingresos > 0 ? ($cancha['total'] / $max_ingresos) * 100 : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cancha['nombre']); ?></strong></td>
                                    <td><?php echo $cancha['reservas']; ?></td>
                                    <td><strong>$<?php echo number_format($cancha['total'], 0, ',', '.'); ?></strong></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%">
                                                <?php echo round($porcentaje); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-users"></i> Top 10 Clientes</h2>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Reservas</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes_top)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 30px;">
                                        <i class="fas fa-inbox" style="font-size: 2em; color: var(--text-gray);"></i><br>
                                        No hay datos
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes_top as $index => $cliente): ?>
                                    <tr>
                                        <td><strong><?php echo $index + 1; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong><br>
                                            <small style="color: var(--text-gray);"><?php echo htmlspecialchars($cliente['telefono']); ?></small>
                                        </td>
                                        <td><?php echo $cliente['reservas']; ?></td>
                                        <td><strong style="color: var(--accent-green);">$<?php echo number_format($cliente['total_gastado'], 0, ',', '.'); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2><i class="fas fa-calendar-day"></i> Ingresos por Día</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Día</th>
                            <th>Reservas</th>
                            <th>Ingresos</th>
                            <th>Rendimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $max_ingresos_dia = !empty($dias_stats) ? max(array_column($dias_stats, 'ingresos')) : 1;
                        $dias_semana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                        
                        if (empty($dias_stats)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-inbox" style="font-size: 3em; color: var(--text-gray); display: block; margin-bottom: 10px;"></i>
                                    No hay reservas en este período
                                </td>
                            </tr>
                        <?php else:
                            foreach ($dias_stats as $dia): 
                                $porcentaje = $max_ingresos_dia > 0 ? ($dia['ingresos'] / $max_ingresos_dia) * 100 : 0;
                                $dia_semana = $dias_semana[date('w', strtotime($dia['dia']))];
                        ?>
                            <tr>
                                <td><strong><?php echo date('d/m/Y', strtotime($dia['dia'])); ?></strong></td>
                                <td><?php echo $dia_semana; ?></td>
                                <td><?php echo $dia['cantidad']; ?></td>
                                <td><strong style="color: var(--accent-green);">$<?php echo number_format($dia['ingresos'], 0, ',', '.'); ?></strong></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%">
                                            <?php echo round($porcentaje); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="section">
            <h2><i class="fas fa-clock"></i> Horarios Más Populares</h2>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Horario</th>
                            <th>Reservas</th>
                            <th>Popularidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $max_cantidad = !empty($horarios) ? max(array_column($horarios, 'cantidad')) : 1;
                        if (empty($horarios)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-inbox" style="font-size: 2em; color: var(--text-gray);"></i><br>
                                    No hay datos
                                </td>
                            </tr>
                        <?php else:
                            foreach ($horarios as $horario): 
                                $porcentaje = $max_cantidad > 0 ? ($horario['cantidad'] / $max_cantidad) * 100 : 0;
                        ?>
                            <tr>
                                <td><strong style="font-size: 1.2em;"><?php echo substr($horario['hora_inicio'], 0, 5); ?></strong></td>
                                <td><?php echo $horario['cantidad']; ?> reservas</td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%">
                                            <?php echo round($porcentaje); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>