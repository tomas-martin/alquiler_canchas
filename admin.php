<?php
require_once 'config.php';

$conn = getConnection();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];
    
    switch ($accion) {
        case 'cancelar_reserva':
            $reserva_id = (int)$_POST['reserva_id'];
            $stmt = $conn->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id = ?");
            if ($stmt->execute([$reserva_id])) {
                $mensaje = "Reserva cancelada exitosamente";
            }
            break;
            
        case 'actualizar_precio':
            $cancha_id = (int)$_POST['cancha_id'];
            $nuevo_precio = (float)$_POST['nuevo_precio'];
            $stmt = $conn->prepare("UPDATE canchas SET precio_hora = ? WHERE id = ?");
            if ($stmt->execute([$nuevo_precio, $cancha_id])) {
                $mensaje = "Precio actualizado exitosamente";
            }
            break;
            
        case 'toggle_cancha':
            $cancha_id = (int)$_POST['cancha_id'];
            $stmt = $conn->prepare("UPDATE canchas SET activa = NOT activa WHERE id = ?");
            if ($stmt->execute([$cancha_id])) {
                $mensaje = "Estado actualizado";
            }
            break;
    }
}

$stats = [];
$stats['total_reservas_hoy'] = $conn->query("
    SELECT COUNT(*) FROM reservas 
    WHERE fecha = CURDATE() AND estado = 'confirmada'
")->fetchColumn();

$stats['ingresos_hoy'] = $conn->query("
    SELECT COALESCE(SUM(total), 0) FROM reservas 
    WHERE fecha = CURDATE() AND estado = 'confirmada'
")->fetchColumn();

$stats['reservas_mes'] = $conn->query("
    SELECT COUNT(*) FROM reservas 
    WHERE MONTH(fecha) = MONTH(CURDATE()) 
    AND YEAR(fecha) = YEAR(CURDATE()) 
    AND estado = 'confirmada'
")->fetchColumn();

$stats['ingresos_mes'] = $conn->query("
    SELECT COALESCE(SUM(total), 0) FROM reservas 
    WHERE MONTH(fecha) = MONTH(CURDATE()) 
    AND YEAR(fecha) = YEAR(CURDATE()) 
    AND estado = 'confirmada'
")->fetchColumn();

$proximas_reservas = $conn->query("
    SELECT r.*, c.nombre as cancha_nombre, cl.nombre as cliente_nombre, cl.telefono
    FROM reservas r
    JOIN canchas c ON r.cancha_id = c.id
    JOIN clientes cl ON r.cliente_id = cl.id
    WHERE r.fecha >= CURDATE() AND r.estado = 'confirmada'
    ORDER BY r.fecha, r.hora_inicio
    LIMIT 20
")->fetchAll();

$canchas = $conn->query("SELECT * FROM canchas ORDER BY id")->fetchAll();

$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$reservas_dia = $conn->prepare("
    SELECT r.*, c.nombre as cancha_nombre, cl.nombre as cliente_nombre, cl.telefono, cl.email
    FROM reservas r
    JOIN canchas c ON r.cancha_id = c.id
    JOIN clientes cl ON r.cliente_id = cl.id
    WHERE r.fecha = ? AND r.estado != 'cancelada'
    ORDER BY r.hora_inicio
");
$reservas_dia->execute([$fecha_filtro]);
$reservas_del_dia = $reservas_dia->fetchAll();

$cancha_rentable = $conn->query("
    SELECT c.nombre, COALESCE(SUM(r.total), 0) as total_ingresos
    FROM canchas c
    LEFT JOIN reservas r ON c.id = r.cancha_id AND r.estado = 'confirmada'
    GROUP BY c.id, c.nombre
    ORDER BY total_ingresos DESC
    LIMIT 1
")->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ Panel de Administración</title>
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
            background: linear-gradient(135deg, var(--accent-red), var(--accent-orange));
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
        
        .alert {
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            border-left: 4px solid var(--accent-green);
            background: linear-gradient(135deg, rgba(0, 255, 135, 0.1), rgba(0, 255, 135, 0.05));
            color: var(--accent-green);
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
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
            background: var(--accent-green);
        }
        
        .stat-card:nth-child(2)::before { background: var(--accent-orange); }
        .stat-card:nth-child(3)::before { background: var(--accent-blue); }
        .stat-card:nth-child(4)::before { background: var(--accent-red); }
        
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
        
        .badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-success {
            background: var(--accent-green);
            color: var(--dark-bg);
        }
        
        .badge-danger {
            background: var(--accent-red);
            color: var(--text-light);
        }
        
        .badge-warning {
            background: var(--accent-orange);
            color: var(--dark-bg);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--accent-red), #cc0044);
            color: var(--text-light);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), #0099cc);
            color: var(--text-light);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--accent-green), #00cc6a);
            color: var(--dark-bg);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 135, 0.3);
        }
        
        .cancha-item {
            background: var(--dark-bg);
            border: 2px solid var(--border-color);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        
        .cancha-item:hover {
            border-color: var(--accent-green);
            transform: translateX(5px);
        }
        
        .cancha-info h4 {
            color: var(--accent-green);
            margin-bottom: 8px;
            font-size: 1.2em;
        }
        
        .cancha-info p {
            color: var(--text-gray);
        }
        
        .form-inline {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .form-inline input,
        .form-inline select {
            padding: 10px 15px;
            background: var(--dark-bg);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-light);
        }
        
        .form-inline input:focus,
        .form-inline select:focus {
            outline: none;
            border-color: var(--accent-green);
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .fecha-selector {
            margin-bottom: 25px;
            text-align: center;
        }
        
        .fecha-selector input {
            padding: 12px 20px;
            background: var(--dark-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-light);
            margin-right: 10px;
        }
        
        @media (max-width: 968px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-cog"></i> PANEL DE ADMINISTRACIÓN</h1>
            <p>Gestión completa del sistema</p>
        </div>
        
        <div class="nav">
            <a href="index.php"><i class="fas fa-calendar-alt"></i> Reservas</a>
            <a href="nueva_reserva.php"><i class="fas fa-plus-circle"></i> Nueva</a>
            <a href="mis_reservas.php"><i class="fas fa-list"></i> Buscar</a>
            <a href="reportes.php"><i class="fas fa-chart-line"></i> Reportes</a>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert"><i class="fas fa-check-circle"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-calendar-check"></i> Reservas Hoy</h3>
                <div class="value"><?php echo $stats['total_reservas_hoy']; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-dollar-sign"></i> Ingresos Hoy</h3>
                <div class="value">$<?php echo number_format($stats['ingresos_hoy'], 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-calendar-alt"></i> Reservas Mes</h3>
                <div class="value"><?php echo $stats['reservas_mes']; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-coins"></i> Ingresos Mes</h3>
                <div class="value">$<?php echo number_format($stats['ingresos_mes'], 0, ',', '.'); ?></div>
            </div>
        </div>
        
        <?php if ($cancha_rentable): ?>
        <div class="section">
            <h2><i class="fas fa-trophy"></i> Cancha Más Rentable</h2>
            <div class="cancha-item">
                <div class="cancha-info">
                    <h4><i class="fas fa-futbol"></i> <?php echo htmlspecialchars($cancha_rentable['nombre']); ?></h4>
                    <p>Ingresos totales: <strong>$<?php echo number_format($cancha_rentable['total_ingresos'], 0, ',', '.'); ?></strong></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="grid-2">
            <div class="section">
                <h2><i class="fas fa-futbol"></i> Gestión de Canchas</h2>
                <?php foreach ($canchas as $cancha): ?>
                    <div class="cancha-item">
                        <div class="cancha-info">
                            <h4><?php echo htmlspecialchars($cancha['nombre']); ?></h4>
                            <p>Precio: $<?php echo number_format($cancha['precio_hora'], 0, ',', '.'); ?>/hora</p>
                            <span class="badge <?php echo $cancha['activa'] ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo $cancha['activa'] ? 'ACTIVA' : 'INACTIVA'; ?>
                            </span>
                        </div>
                        <div class="form-inline">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="accion" value="actualizar_precio">
                                <input type="hidden" name="cancha_id" value="<?php echo $cancha['id']; ?>">
                                <input type="number" name="nuevo_precio" value="<?php echo $cancha['precio_hora']; ?>" 
                                       style="width: 120px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Actualizar
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="accion" value="toggle_cancha">
                                <input type="hidden" name="cancha_id" value="<?php echo $cancha['id']; ?>">
                                <button type="submit" class="btn <?php echo $cancha['activa'] ? 'btn-danger' : 'btn-success'; ?>">
                                    <i class="fas fa-<?php echo $cancha['activa'] ? 'times' : 'check'; ?>-circle"></i>
                                    <?php echo $cancha['activa'] ? 'Desactivar' : 'Activar'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-clock"></i> Próximas Reservas</h2>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Cancha</th>
                                <th>Cliente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($proximas_reservas, 0, 10) as $reserva): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($reserva['fecha'])); ?></td>
                                    <td><?php echo substr($reserva['hora_inicio'], 0, 5); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['cancha_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['cliente_nombre']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2><i class="fas fa-calendar-day"></i> Reservas del Día</h2>
            <div class="fecha-selector">
                <form method="GET" class="form-inline" style="justify-content: center;">
                    <input type="date" name="fecha" value="<?php echo $fecha_filtro; ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                </form>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Hora</th>
                            <th>Cancha</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservas_del_dia)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-inbox" style="font-size: 3em; color: var(--text-gray); display: block; margin-bottom: 10px;"></i>
                                    No hay reservas para esta fecha
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservas_del_dia as $reserva): ?>
                                <tr>
                                    <td><strong>#<?php echo $reserva['id']; ?></strong></td>
                                    <td><?php echo substr($reserva['hora_inicio'], 0, 5); ?> - <?php echo substr($reserva['hora_fin'], 0, 5); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['cancha_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['cliente_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['telefono']); ?></td>
                                    <td><strong>$<?php echo number_format($reserva['total'], 0, ',', '.'); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $reserva['estado'] == 'confirmada' ? 'success' : 'warning'; ?>">
                                            <?php echo strtoupper($reserva['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($reserva['estado'] == 'confirmada'): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('¿Cancelar esta reserva?');">
                                                <input type="hidden" name="accion" value="cancelar_reserva">
                                                <input type="hidden" name="reserva_id" value="<?php echo $reserva['id']; ?>">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-times-circle"></i> Cancelar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>