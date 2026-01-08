<?php
require_once 'config.php';

$conn = getConnection();
$reservas = [];
$cliente = null;
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {
    $reserva_id = (int)$_POST['reserva_id'];
    $stmt = $conn->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id = ?");
    if ($stmt->execute([$reserva_id])) {
        $mensaje = "Reserva cancelada exitosamente";
    }
}

if (isset($_GET['telefono']) && !empty($_GET['telefono'])) {
    $telefono = trim($_GET['telefono']);
    
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE telefono = ?");
    $stmt->execute([$telefono]);
    $cliente = $stmt->fetch();
    
    if ($cliente) {
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
    <title>⚽ Mis Reservas</title>
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
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
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
            background: linear-gradient(135deg, var(--accent-green), var(--accent-blue));
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
        
        .search-box {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .search-box h2 {
            color: var(--accent-green);
            margin-bottom: 25px;
            font-size: 2em;
        }
        
        .search-form {
            display: flex;
            gap: 15px;
            max-width: 600px;
            margin: 0 auto;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .search-form input {
            flex: 1;
            min-width: 300px;
            padding: 15px 20px;
            background: var(--dark-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-light);
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .search-form input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 20px rgba(0, 255, 135, 0.2);
        }
        
        .btn {
            padding: 15px 35px;
            background: linear-gradient(135deg, var(--accent-green), #00cc6a);
            color: var(--dark-bg);
            border: none;
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
        
        .btn-danger {
            background: linear-gradient(135deg, var(--accent-red), #cc0044);
            color: var(--text-light);
        }
        
        .cliente-info {
            background: linear-gradient(135deg, rgba(0, 217, 255, 0.1), rgba(0, 217, 255, 0.05));
            border: 2px solid var(--accent-blue);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        
        .cliente-info h3 {
            color: var(--accent-blue);
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .cliente-info p {
            margin-bottom: 10px;
            font-size: 1.1em;
            color: var(--text-gray);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-green);
        }
        
        .stat-box:nth-child(2)::before { background: var(--accent-blue); }
        .stat-box:nth-child(3)::before { background: var(--accent-red); }
        .stat-box:nth-child(4)::before { background: var(--accent-orange); }
        
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 255, 135, 0.2);
        }
        
        .stat-box h4 {
            color: var(--text-gray);
            font-size: 0.9em;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-box .value {
            font-size: 2.5em;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent-green), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .reservas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        .reserva-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .reserva-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }
        
        .reserva-card.confirmada::before { background: var(--accent-green); }
        .reserva-card.cancelada::before { background: var(--accent-red); opacity: 0.5; }
        .reserva-card.pendiente::before { background: var(--accent-orange); }
        
        .reserva-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .reserva-card.cancelada { opacity: 0.6; }
        
        .reserva-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .reserva-header h4 {
            font-size: 1.3em;
            color: var(--accent-green);
        }
        
        .badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        .reserva-detail {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-gray);
        }
        
        .reserva-detail i {
            width: 25px;
            color: var(--accent-green);
        }
        
        .reserva-footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .total {
            font-size: 1.8em;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent-green), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .no-reservas {
            text-align: center;
            padding: 80px 20px;
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 20px;
        }
        
        .no-reservas i {
            font-size: 5em;
            color: var(--text-gray);
            margin-bottom: 20px;
        }
        
        .no-reservas h3 {
            font-size: 2em;
            margin-bottom: 15px;
            color: var(--text-gray);
        }
        
        @media (max-width: 768px) {
            .header h1 { font-size: 2em; }
            .reservas-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-list"></i> MIS RESERVAS</h1>
            <p>Consulta y gestiona tus reservas</p>
        </div>
        
        <div class="nav">
            <a href="index.php"><i class="fas fa-calendar-alt"></i> Reservas</a>
            <a href="nueva_reserva.php"><i class="fas fa-plus-circle"></i> Nueva</a>
            <a href="admin.php"><i class="fas fa-cog"></i> Admin</a>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert"><i class="fas fa-check-circle"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <div class="search-box">
            <h2><i class="fas fa-search"></i> Buscar mis reservas</h2>
            <form method="GET" class="search-form">
                <input type="tel" name="telefono" placeholder="Ingresa tu teléfono" 
                       value="<?php echo isset($_GET['telefono']) ? htmlspecialchars($_GET['telefono']) : ''; ?>" required>
                <button type="submit" class="btn"><i class="fas fa-search"></i> BUSCAR</button>
            </form>
        </div>
        
        <?php if ($cliente): ?>
            <div class="cliente-info">
                <h3><i class="fas fa-user-circle"></i> Información del Cliente</h3>
                <p><strong><i class="fas fa-user"></i> Nombre:</strong> <?php echo htmlspecialchars($cliente['nombre']); ?></p>
                <p><strong><i class="fas fa-phone"></i> Teléfono:</strong> <?php echo htmlspecialchars($cliente['telefono']); ?></p>
                <?php if ($cliente['email']): ?>
                    <p><strong><i class="fas fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($cliente['email']); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($reservas)): ?>
                <?php
                $total_reservas = count($reservas);
                $reservas_confirmadas = count(array_filter($reservas, fn($r) => $r['estado'] == 'confirmada'));
                $reservas_canceladas = count(array_filter($reservas, fn($r) => $r['estado'] == 'cancelada'));
                $total_gastado = array_sum(array_column(array_filter($reservas, fn($r) => $r['estado'] == 'confirmada'), 'total'));
                ?>
                
                <div class="stats">
                    <div class="stat-box">
                        <h4><i class="fas fa-clipboard-list"></i> Total Reservas</h4>
                        <div class="value"><?php echo $total_reservas; ?></div>
                    </div>
                    <div class="stat-box">
                        <h4><i class="fas fa-check-circle"></i> Confirmadas</h4>
                        <div class="value"><?php echo $reservas_confirmadas; ?></div>
                    </div>
                    <div class="stat-box">
                        <h4><i class="fas fa-times-circle"></i> Canceladas</h4>
                        <div class="value"><?php echo $reservas_canceladas; ?></div>
                    </div>
                    <div class="stat-box">
                        <h4><i class="fas fa-dollar-sign"></i> Total Gastado</h4>
                        <div class="value">$<?php echo number_format($total_gastado, 0, ',', '.'); ?></div>
                    </div>
                </div>
                
                <h2 style="margin-bottom: 25px; color: var(--accent-green); font-size: 2em;">
                    <i class="fas fa-futbol"></i> Tus Reservas
                </h2>
                <div class="reservas-grid">
                    <?php foreach ($reservas as $reserva): ?>
                        <div class="reserva-card <?php echo $reserva['estado']; ?>">
                            <div class="reserva-header">
                                <h4><i class="fas fa-hashtag"></i><?php echo $reserva['id']; ?></h4>
                                <span class="badge badge-<?php echo $reserva['estado'] == 'confirmada' ? 'success' : ($reserva['estado'] == 'cancelada' ? 'danger' : 'warning'); ?>">
                                    <?php echo strtoupper($reserva['estado']); ?>
                                </span>
                            </div>
                            
                            <div class="reserva-detail">
                                <i class="fas fa-futbol"></i>
                                <span><?php echo htmlspecialchars($reserva['cancha_nombre']); ?></span>
                            </div>
                            
                            <div class="reserva-detail">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?php echo date('d/m/Y', strtotime($reserva['fecha'])); ?></span>
                            </div>
                            
                            <div class="reserva-detail">
                                <i class="fas fa-clock"></i>
                                <span><?php echo substr($reserva['hora_inicio'], 0, 5); ?> - <?php echo substr($reserva['hora_fin'], 0, 5); ?></span>
                            </div>
                            
                            <div class="reserva-footer">
                                <div class="total">$<?php echo number_format($reserva['total'], 0, ',', '.'); ?></div>
                                <?php if ($reserva['estado'] == 'confirmada' && $reserva['fecha'] >= date('Y-m-d')): ?>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('¿Cancelar esta reserva?');">
                                        <input type="hidden" name="cancelar" value="1">
                                        <input type="hidden" name="reserva_id" value="<?php echo $reserva['id']; ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-times-circle"></i> CANCELAR
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-reservas">
                    <i class="fas fa-inbox"></i>
                    <h3>No tienes reservas</h3>
                    <p style="color: var(--text-gray); margin-bottom: 25px;">Aún no has realizado ninguna reserva.</p>
                    <a href="nueva_reserva.php" class="btn" style="display: inline-block; text-decoration: none;">
                        <i class="fas fa-plus-circle"></i> HACER RESERVA
                    </a>
                </div>
            <?php endif; ?>
        <?php elseif (isset($_GET['telefono'])): ?>
            <div class="no-reservas">
                <i class="fas fa-search"></i>
                <h3>No se encontraron resultados</h3>
                <p style="color: var(--text-gray);">No hay registros con el teléfono: <strong><?php echo htmlspecialchars($_GET['telefono']); ?></strong></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>