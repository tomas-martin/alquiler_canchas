<?php
require_once 'config.php';

$conn = getConnection();

// Determinar rango de fechas
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

// Consultas principales
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

// Ingresos por cancha
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

// Reservas por día
$reservas_dia = $conn->prepare("
    SELECT DATE(fecha) as dia, COUNT(*) as cantidad, SUM(total) as ingresos
    FROM reservas
    WHERE fecha BETWEEN ? AND ? AND estado = 'confirmada'
    GROUP BY DATE(fecha)
    ORDER BY fecha DESC
");
$reservas_dia->execute([$fecha_inicio, $fecha_fin]);
$dias_stats = $reservas_dia->fetchAll();

// Top clientes
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

// Horarios más populares
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
    <title>Reportes e Ingresos</title>
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
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
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
        .content { padding: 30px; }
        .filtros {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .filtros h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .filtros-btn {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .filtro-btn {
            padding: 10px 20px;
            background: white;
            color: #333;
            border: 2px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .filtro-btn:hover, .filtro-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .fecha-custom {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .fecha-custom input {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 { font-size: 0.9em; margin-bottom: 10px; opacity: 0.9; }
        .stat-card .value { font-size: 2.5em; font-weight: bold; }
        .stat-card.green {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }
        .stat-card.orange {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }
        .stat-card.blue {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        .section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .section h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table th {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .table tr:hover {
            background: #f8f9fa;
        }
        .progress-bar {
            height: 25px;
            background: #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 5px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-size: 0.85em;
            font-weight: bold;
            transition: width 0.5s;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        @media (max-width: 968px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
        .periodo-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border: 2px solid #3498db;
        }
        .periodo-info strong {
            color: #2980b9;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Reportes e Ingresos</h1>
            <p>Análisis detallado del negocio</p>
        </div>
        
        <div class="nav">
            <a href="index.php">📅 Reservas</a>
            <a href="nueva_reserva.php">➕ Nueva Reserva</a>
            <a href="mis_reservas.php">📋 Buscar Reservas</a>
            <a href="admin.php">⚙️ Administración</a>
        </div>
        
        <div class="content">
            <div class="filtros">
                <h3>🔍 Filtrar por período</h3>
                <div class="filtros-btn">
                    <a href="?filtro=hoy" class="filtro-btn <?php echo $filtro == 'hoy' ? 'active' : ''; ?>">Hoy</a>
                    <a href="?filtro=semana" class="filtro-btn <?php echo $filtro == 'semana' ? 'active' : ''; ?>">Última Semana</a>
                    <a href="?filtro=mes_actual" class="filtro-btn <?php echo $filtro == 'mes_actual' ? 'active' : ''; ?>">Mes Actual</a>
                    <a href="?filtro=mes_pasado" class="filtro-btn <?php echo $filtro == 'mes_pasado' ? 'active' : ''; ?>">Mes Pasado</a>
                </div>
                <form method="GET" class="fecha-custom">
                    <input type="hidden" name="filtro" value="personalizado">
                    <label>Desde:</label>
                    <input type="date" name="fecha_inicio" value="<?php echo $filtro == 'personalizado' ? $fecha_inicio : ''; ?>" required>
                    <label>Hasta:</label>
                    <input type="date" name="fecha_fin" value="<?php echo $filtro == 'personalizado' ? $fecha_fin : ''; ?>" required>
                    <button type="submit" class="filtro-btn">Aplicar</button>
                </form>
            </div>
            
            <div class="periodo-info">
                <strong>📅 Período seleccionado: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></strong>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card green">
                    <h3>💰 Ingresos Totales</h3>
                    <div class="value">$<?php echo number_format($ingresos, 0, ',', '.'); ?></div>
                </div>
                <div class="stat-card orange">
                    <h3>📋 Total Reservas</h3>
                    <div class="value"><?php echo $num_reservas; ?></div>
                </div>
                <div class="stat-card blue">
                    <h3>⏰ Horas Reservadas</h3>
                    <div class="value"><?php echo $horas; ?></div>
                </div>
                <div class="stat-card">
                    <h3>📊 Promedio por Reserva</h3>
                    <div class="value">$<?php echo $num_reservas > 0 ? number_format($ingresos / $num_reservas, 0, ',', '.') : '0'; ?></div>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="section">
                    <h2>🏟️ Ingresos por Cancha</h2>
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
                                    <td>$<?php echo number_format($cancha['total'], 0, ',', '.'); ?></td>
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
                
                <div class="section">
                    <h2>👥 Top 10 Clientes</h2>
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
                                    <td colspan="4" style="text-align: center;">No hay datos disponibles</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes_top as $index => $cliente): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($cliente['telefono']); ?></small>
                                        </td>
                                        <td><?php echo $cliente['reservas']; ?></td>
                                        <td><strong>$<?php echo number_format($cliente['total_gastado'], 0, ',', '.'); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="section">
                <h2>📅 Ingresos por Día</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Día de la Semana</th>
                            <th>Cantidad de Reservas</th>
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
                                <td colspan="5" style="text-align: center;">No hay reservas en este período</td>
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
                                <td><strong>$<?php echo number_format($dia['ingresos'], 0, ',', '.'); ?></strong></td>
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
            
            <div class="section">
                <h2>⏰ Horarios Más Populares</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Horario</th>
                            <th>Cantidad de Reservas</th>
                            <th>Popularidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $max_cantidad = !empty($horarios) ? max(array_column($horarios, 'cantidad')) : 1;
                        if (empty($horarios)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">No hay datos disponibles</td>
                            </tr>
                        <?php else:
                            foreach ($horarios as $horario): 
                                $porcentaje = $max_cantidad > 0 ? ($horario['cantidad'] / $max_cantidad) * 100 : 0;
                        ?>
                            <tr>
                                <td><strong><?php echo substr($horario['hora_inicio'], 0, 5); ?></strong></td>
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