<?php
require_once 'config.php';

$conn = getConnection();
$reservas = [];
$cliente = null;
$mensaje = '';

// Procesar cancelación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {
    $reserva_id = (int)$_POST['reserva_id'];
    $stmt = $conn->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id = ?");
    if ($stmt->execute([$reserva_id])) {
        $mensaje = "✅ Reserva cancelada exitosamente";
    }
}

// Buscar reservas por teléfono
if (isset($_GET['telefono']) && !empty($_GET['telefono'])) {
    $telefono = trim($_GET['telefono']);
    
    // Buscar cliente
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE telefono = ?");
    $stmt->execute([$telefono]);
    $cliente = $stmt->fetch();
    
    if ($cliente) {
        // Obtener todas las reservas del cliente
        $stmt = $conn->prepare("
            SELECT r.*, c.nombre as cancha_nombre, c.precio_hora
            FROM reservas r
            JOIN canchas c ON r.cancha_id = c.id
            WHERE r.cliente_id = ?
            ORDER BY r.fecha DESC, r.hora_inicio DESC
        ");
        $stmt->execute([$cliente['id']]);
        $reservas = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Reservas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
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
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .search-box {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .search-box h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .search-form {
            display: flex;
            gap: 10px;
            max-width: 500px;
            margin: 0 auto;
            flex-wrap: wrap;
            justify-content: center;
        }
        .search-form input {
            flex: 1;
            min-width: 250px;
            padding: 12px 20px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .search-form input:focus {
            outline: none;
            border-color: #667eea;
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
        .btn-danger {
            background: #e74c3c;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .cliente-info {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 2px solid #3498db;
        }
        .cliente-info h3 {
            color: #2980b9;
            margin-bottom: 15px;
        }
        .cliente-info p {
            margin-bottom: 8px;
            font-size: 1.1em;
        }
        .reservas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .reserva-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .reserva-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        .reserva-card.confirmada {
            border-left: 5px solid #2ecc71;
        }
        .reserva-card.cancelada {
            border-left: 5px solid #e74c3c;
            opacity: 0.7;
        }
        .reserva-card.pendiente {
            border-left: 5px solid #f39c12;
        }
        .reserva-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }
        .reserva-header h4 {
            font-size: 1.3em;
            color: #333;
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
        .reserva-detail {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .reserva-detail strong {
            min-width: 80px;
            color: #666;
        }
        .reserva-footer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total {
            font-size: 1.4em;
            font-weight: bold;
            color: #2ecc71;
        }
        .no-reservas {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-reservas h3 {
            font-size: 2em;
            margin-bottom: 15px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-box h4 {
            font-size: 0.9em;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .stat-box .value {
            font-size: 2em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Mis Reservas</h1>
            <p>Consulta y gestiona tus reservas</p>
        </div>
        
        <div class="nav">
            <a href="index.php">📅 Reservas</a>
            <a href="nueva_reserva.php">➕ Nueva Reserva</a>
            <a href="admin.php">⚙️ Administración</a>
        </div>
        
        <div class="content">
            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo $mensaje; ?></div>
            <?php endif; ?>
            
            <div class="search-box">
                <h2>🔍 Buscar mis reservas</h2>
                <form method="GET" class="search-form">
                    <input type="tel" name="telefono" placeholder="Ingresa tu número de teléfono" 
                           value="<?php echo isset($_GET['telefono']) ? htmlspecialchars($_GET['telefono']) : ''; ?>" 
                           required>
                    <button type="submit" class="btn">Buscar</button>
                </form>
            </div>
            
            <?php if ($cliente): ?>
                <div class="cliente-info">
                    <h3>👤 Información del Cliente</h3>
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($cliente['nombre']); ?></p>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($cliente['telefono']); ?></p>
                    <?php if ($cliente['email']): ?>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($cliente['email']); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($reservas)): ?>
                    <?php
                    // Calcular estadísticas
                    $total_reservas = count($reservas);
                    $reservas_confirmadas = count(array_filter($reservas, fn($r) => $r['estado'] == 'confirmada'));
                    $reservas_canceladas = count(array_filter($reservas, fn($r) => $r['estado'] == 'cancelada'));
                    $total_gastado = array_sum(array_column(array_filter($reservas, fn($r) => $r['estado'] == 'confirmada'), 'total'));
                    ?>
                    
                    <div class="stats">
                        <div class="stat-box">
                            <h4>Total Reservas</h4>
                            <div class="value"><?php echo $total_reservas; ?></div>
                        </div>
                        <div class="stat-box" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
                            <h4>Confirmadas</h4>
                            <div class="value"><?php echo $reservas_confirmadas; ?></div>
                        </div>
                        <div class="stat-box" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                            <h4>Canceladas</h4>
                            <div class="value"><?php echo $reservas_canceladas; ?></div>
                        </div>
                        <div class="stat-box" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                            <h4>Total Gastado</h4>
                            <div class="value">$<?php echo number_format($total_gastado, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                    
                    <h2 style="margin-bottom: 20px;">Tus Reservas</h2>
                    <div class="reservas-grid">
                        <?php foreach ($reservas as $reserva): ?>
                            <div class="reserva-card <?php echo $reserva['estado']; ?>">
                                <div class="reserva-header">
                                    <h4>Reserva #<?php echo $reserva['id']; ?></h4>
                                    <span class="badge badge-<?php echo $reserva['estado'] == 'confirmada' ? 'success' : ($reserva['estado'] == 'cancelada' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($reserva['estado']); ?>
                                    </span>
                                </div>
                                
                                <div class="reserva-detail">
                                    <strong>🏟️ Cancha:</strong>
                                    <span><?php echo htmlspecialchars($reserva['cancha_nombre']); ?></span>
                                </div>
                                
                                <div class="reserva-detail">
                                    <strong>📅 Fecha:</strong>
                                    <span><?php echo date('d/m/Y', strtotime($reserva['fecha'])); ?></span>
                                </div>
                                
                                <div class="reserva-detail">
                                    <strong>⏰ Horario:</strong>
                                    <span><?php echo substr($reserva['hora_inicio'], 0, 5); ?> - <?php echo substr($reserva['hora_fin'], 0, 5); ?></span>
                                </div>
                                
                                <div class="reserva-footer">
                                    <div class="total">$<?php echo number_format($reserva['total'], 0, ',', '.'); ?></div>
                                    <?php if ($reserva['estado'] == 'confirmada' && $reserva['fecha'] >= date('Y-m-d')): ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('¿Estás seguro de cancelar esta reserva?');">
                                            <input type="hidden" name="cancelar" value="1">
                                            <input type="hidden" name="reserva_id" value="<?php echo $reserva['id']; ?>">
                                            <button type="submit" class="btn btn-danger">❌ Cancelar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-reservas">
                        <h3>😔 No tienes reservas</h3>
                        <p>Aún no has realizado ninguna reserva con este teléfono.</p>
                        <a href="nueva_reserva.php" class="btn" style="display: inline-block; margin-top: 20px; text-decoration: none;">
                            ➕ Hacer una Reserva
                        </a>
                    </div>
                <?php endif; ?>
            <?php elseif (isset($_GET['telefono'])): ?>
                <div class="no-reservas">
                    <h3>🔍 No se encontraron resultados</h3>
                    <p>No hay ningún cliente registrado con el teléfono: <strong><?php echo htmlspecialchars($_GET['telefono']); ?></strong></p>
                    <p style="margin-top: 15px;">Verifica que el número sea correcto o realiza una nueva reserva.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>