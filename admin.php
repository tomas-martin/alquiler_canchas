<?php
require_once 'config.php';

$conn = getConnection();
$mensaje = '';
$error = '';

// Procesar acciones
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
                $mensaje = "Estado de cancha actualizado";
            }
            break;
    }
}

// Obtener estadísticas
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

// Obtener próximas reservas
$proximas_reservas = $conn->query("
    SELECT r.*, c.nombre as cancha_nombre, cl.nombre as cliente_nombre, cl.telefono
    FROM reservas r
    JOIN canchas c ON r.cancha_id = c.id
    JOIN clientes cl ON r.cliente_id = cl.id
    WHERE r.fecha >= CURDATE() AND r.estado = 'confirmada'
    ORDER BY r.fecha, r.hora_inicio
    LIMIT 20
")->fetchAll();

// Obtener todas las canchas
$canchas = $conn->query("SELECT * FROM canchas ORDER BY id")->fetchAll();

// Obtener reservas del día actual
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

// Cancha más rentable
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
    <title>Panel de Administración</title>
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
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
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
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            animation: slideIn 0.3s;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        .stat-card.red {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
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
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        .btn-success:hover {
            background: #27ae60;
        }
        .form-inline {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .form-inline input,
        .form-inline select {
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
        .cancha-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .cancha-info h4 {
            margin-bottom: 5px;
            color: #333;
        }
        .cancha-info p {
            color: #666;
            font-size: 0.9em;
        }
        .fecha-selector {
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Panel de Administración</h1>
            <p>Gestión completa del sistema</p>
        </div>
        
        <div class="nav">
            <a href="index.php">📅 Reservas</a>
            <a href="nueva_reserva.php">➕ Nueva Reserva</a>
            <a href="mis_reservas.php">📋 Buscar Reservas</a>
            <a href="reportes.php">📊 Reportes</a>
        </div>
        
        <div class="content">
            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo $mensaje; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card green">
                    <h3>Reservas Hoy</h3>
                    <div class="value"><?php echo $stats['total_reservas_hoy']; ?></div>
                </div>
                <div class="stat-card orange">
                    <h3>Ingresos Hoy</h3>
                    <div class="value">$<?php echo number_format($stats['ingresos_hoy'], 0, ',', '.'); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Reservas Este Mes</h3>
                    <div class="value"><?php echo $stats['reservas_mes']; ?></div>
                </div>
                <div class="stat-card red">
                    <h3>Ingresos Este Mes</h3>
                    <div class="value">$<?php echo number_format($stats['ingresos_mes'], 0, ',', '.'); ?></div>
                </div>
            </div>
            
            <?php if ($cancha_rentable): ?>
            <div class="section">
                <h2>🏆 Cancha Más Rentable</h2>
                <div class="cancha-item">
                    <div class="cancha-info">
                        <h4><?php echo htmlspecialchars($cancha_rentable['nombre']); ?></h4>
                        <p>Ingresos totales: $<?php echo number_format($cancha_rentable['total_ingresos'], 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="grid-2">
                <div class="section">
                    <h2>🏟️ Gestión de Canchas</h2>
                    <?php foreach ($canchas as $cancha): ?>
                        <div class="cancha-item">
                            <div class="cancha-info">
                                <h4><?php echo htmlspecialchars($cancha['nombre']); ?></h4>
                                <p>Precio: $<?php echo number_format($cancha['precio_hora'], 0, ',', '.'); ?>/hora</p>
                                <span class="badge <?php echo $cancha['activa'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $cancha['activa'] ? 'Activa' : 'Inactiva'; ?>
                                </span>
                            </div>
                            <div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="accion" value="actualizar_precio">
                                    <input type="hidden" name="cancha_id" value="<?php echo $cancha['id']; ?>">
                                    <input type="number" name="nuevo_precio" value="<?php echo $cancha['precio_hora']; ?>" 
                                           style="width: 100px; padding: 5px; margin-right: 5px;">
                                    <button type="submit" class="btn btn-primary">💰 Actualizar</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="accion" value="toggle_cancha">
                                    <input type="hidden" name="cancha_id" value="<?php echo $cancha['id']; ?>">
                                    <button type="submit" class="btn <?php echo $cancha['activa'] ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo $cancha['activa'] ? '❌ Desactivar' : '✅ Activar'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="section">
                    <h2>📅 Próximas Reservas</h2>
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
            
            <div class="section">
                <h2>📋 Reservas del Día</h2>
                <div class="fecha-selector">
                    <form method="GET" class="form-inline" style="justify-content: center;">
                        <input type="date" name="fecha" value="<?php echo $fecha_filtro; ?>">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </form>
                </div>
                
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
                                <td colspan="8" style="text-align: center; padding: 30px;">
                                    No hay reservas para esta fecha
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservas_del_dia as $reserva): ?>
                                <tr>
                                    <td>#<?php echo $reserva['id']; ?></td>
                                    <td><?php echo substr($reserva['hora_inicio'], 0, 5); ?> - <?php echo substr($reserva['hora_fin'], 0, 5); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['cancha_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['cliente_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($reserva['telefono']); ?></td>
                                    <td>$<?php echo number_format($reserva['total'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $reserva['estado'] == 'confirmada' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($reserva['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($reserva['estado'] == 'confirmada'): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('¿Seguro que deseas cancelar esta reserva?');">
                                                <input type="hidden" name="accion" value="cancelar_reserva">
                                                <input type="hidden" name="reserva_id" value="<?php echo $reserva['id']; ?>">
                                                <button type="submit" class="btn btn-danger">❌ Cancelar</button>
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