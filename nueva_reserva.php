<?php
require_once 'config.php';

$conn = getConnection();
$mensaje = '';
$error = '';

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $cancha_id = (int)$_POST['cancha_id'];
    $fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $horas = (int)$_POST['horas'];
    
    // Validar que la fecha no sea pasada
    if ($fecha < date('Y-m-d')) {
        $error = "No puedes reservar en fechas pasadas";
    } else {
        // Calcular hora fin
        $hora_fin_int = (int)substr($hora_inicio, 0, 2) + $horas;
        $hora_fin = sprintf("%02d:00", $hora_fin_int);
        
        // Validar que no exceda el horario de cierre
        if ($hora_fin_int > HORA_CIERRE) {
            $error = "La reserva excede el horario de cierre (00:00)";
        } else {
            try {
                $conn->beginTransaction();
                
                // Verificar disponibilidad EXACTA para cada hora solicitada
                $horas_ocupadas = [];
                for ($i = 0; $i < $horas; $i++) {
                    $hora_verificar = sprintf("%02d:00", (int)substr($hora_inicio, 0, 2) + $i);
                    
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) FROM reservas 
                        WHERE cancha_id = ? 
                        AND fecha = ? 
                        AND hora_inicio = ?
                        AND estado != 'cancelada'
                    ");
                    $stmt->execute([$cancha_id, $fecha, $hora_verificar]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $horas_ocupadas[] = $hora_verificar;
                    }
                }
                
                if (!empty($horas_ocupadas)) {
                    $error = "❌ Las siguientes horas ya están OCUPADAS: " . implode(', ', $horas_ocupadas) . ". Por favor, selecciona otro horario.";
                    $conn->rollBack();
                } else {
                    // Insertar o buscar cliente
                    $stmt = $conn->prepare("SELECT id FROM clientes WHERE telefono = ?");
                    $stmt->execute([$telefono]);
                    $cliente = $stmt->fetch();
                    
                    if ($cliente) {
                        $cliente_id = $cliente['id'];
                        // Actualizar datos del cliente
                        $stmt = $conn->prepare("UPDATE clientes SET nombre = ?, email = ? WHERE id = ?");
                        $stmt->execute([$nombre, $email, $cliente_id]);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO clientes (nombre, telefono, email) VALUES (?, ?, ?)");
                        $stmt->execute([$nombre, $telefono, $email]);
                        $cliente_id = $conn->lastInsertId();
                    }
                    
                    // Obtener precio de la cancha
                    $stmt = $conn->prepare("SELECT precio_hora, nombre FROM canchas WHERE id = ?");
                    $stmt->execute([$cancha_id]);
                    $cancha = $stmt->fetch();
                    $total = $cancha['precio_hora'] * $horas;
                    
                    // Crear reserva para cada hora
                    $stmt = $conn->prepare("
                        INSERT INTO reservas (cancha_id, cliente_id, fecha, hora_inicio, hora_fin, total, estado) 
                        VALUES (?, ?, ?, ?, ?, ?, 'confirmada')
                    ");
                    
                    for ($i = 0; $i < $horas; $i++) {
                        $hora_actual = sprintf("%02d:00", (int)substr($hora_inicio, 0, 2) + $i);
                        $hora_siguiente = sprintf("%02d:00", (int)substr($hora_inicio, 0, 2) + $i + 1);
                        $stmt->execute([$cancha_id, $cliente_id, $fecha, $hora_actual, $hora_siguiente, $cancha['precio_hora']]);
                    }
                    
                    $conn->commit();
                    $mensaje = "✅ ¡RESERVA CONFIRMADA! <br><br>
                                <strong>Detalles:</strong><br>
                                Cancha: {$cancha['nombre']}<br>
                                Fecha: " . date('d/m/Y', strtotime($fecha)) . "<br>
                                Horario: {$hora_inicio} a {$hora_fin}<br>
                                Cliente: {$nombre}<br>
                                Teléfono: {$telefono}<br>
                                <strong>Total a pagar: $" . number_format($total, 0, ',', '.') . "</strong>";
                    
                    // Limpiar formulario
                    $_POST = [];
                }
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Error al procesar la reserva: " . $e->getMessage();
            }
        }
    }
}

// Obtener datos precargados de la URL
$cancha_preseleccionada = isset($_GET['cancha']) ? (int)$_GET['cancha'] : 0;
$fecha_preseleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$hora_preseleccionada = isset($_GET['hora']) ? $_GET['hora'] : '';

// Obtener canchas activas
$canchas = $conn->query("SELECT * FROM canchas WHERE activa = TRUE ORDER BY id")->fetchAll();
$horarios = generarHorarios();

// Si hay cancha y fecha seleccionadas, obtener horarios ocupados
$horarios_ocupados = [];
if ($cancha_preseleccionada && $fecha_preseleccionada) {
    $stmt = $conn->prepare("
        SELECT hora_inicio FROM reservas 
        WHERE cancha_id = ? AND fecha = ? AND estado != 'cancelada'
    ");
    $stmt->execute([$cancha_preseleccionada, $fecha_preseleccionada]);
    $horarios_ocupados = array_column($stmt->fetchAll(), 'hora_inicio');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Reserva</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
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
        .header h1 { font-size: 2em; }
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
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideDown 0.3s;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
            font-size: 1.1em;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
            font-size: 1.1em;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group select option.ocupado {
            background: #f8d7da;
            color: #721c24;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .precio-info {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
            border: 2px solid #0066cc;
        }
        .precio-info h3 {
            color: #0066cc;
            font-size: 1.8em;
            margin-bottom: 10px;
        }
        .precio-info p {
            color: #333;
            font-size: 1.1em;
        }
        .info-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box strong {
            color: #856404;
        }
    </style>
    <script>
        let horariosOcupados = <?php echo json_encode($horarios_ocupados); ?>;
        
        function actualizarHorariosDisponibles() {
            const canchaSelect = document.getElementById('cancha_id');
            const fechaInput = document.getElementById('fecha');
            const horaSelect = document.getElementById('hora_inicio');
            
            if (canchaSelect.value && fechaInput.value) {
                // Hacer petición AJAX para obtener horarios ocupados
                fetch(`get_horarios_ocupados.php?cancha=${canchaSelect.value}&fecha=${fechaInput.value}`)
                    .then(response => response.json())
                    .then(data => {
                        horariosOcupados = data;
                        actualizarSelectHorarios();
                        calcularTotal();
                    });
            }
        }
        
        function actualizarSelectHorarios() {
            const horaSelect = document.getElementById('hora_inicio');
            const options = horaSelect.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value && horariosOcupados.includes(option.value)) {
                    option.classList.add('ocupado');
                    option.textContent = option.textContent.split(' - ')[0] + ' - ❌ OCUPADO';
                    option.disabled = true;
                } else if (option.value) {
                    option.classList.remove('ocupado');
                    option.textContent = option.value + ' - ✅ Disponible';
                    option.disabled = false;
                }
            });
        }
        
        function validarHorasDisponibles() {
            const horaInicio = document.getElementById('hora_inicio').value;
            const horas = parseInt(document.getElementById('horas').value);
            
            if (!horaInicio || !horas) return true;
            
            const horaInicioInt = parseInt(horaInicio.split(':')[0]);
            
            for (let i = 0; i < horas; i++) {
                const horaVerificar = String(horaInicioInt + i).padStart(2, '0') + ':00';
                if (horariosOcupados.includes(horaVerificar)) {
                    alert(`❌ La hora ${horaVerificar} ya está OCUPADA. Por favor, selecciona otro horario.`);
                    return false;
                }
            }
            
            return true;
        }
        
        function calcularTotal() {
            const canchaSelect = document.getElementById('cancha_id');
            const horasSelect = document.getElementById('horas');
            const precioInfo = document.getElementById('precio-info');
            
            if (canchaSelect.value && horasSelect.value) {
                const precio = parseFloat(canchaSelect.options[canchaSelect.selectedIndex].dataset.precio);
                const horas = parseInt(horasSelect.value);
                const total = precio * horas;
                
                precioInfo.innerHTML = `<h3>Total: $${total.toLocaleString('es-AR')}</h3>
                                       <p>${horas} hora(s) × $${precio.toLocaleString('es-AR')} = $${total.toLocaleString('es-AR')}</p>`;
                precioInfo.style.display = 'block';
            } else {
                precioInfo.style.display = 'none';
            }
        }
        
        window.onload = function() {
            actualizarSelectHorarios();
            calcularTotal();
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>➕ Nueva Reserva</h1>
            <p>Completa el formulario para reservar una cancha</p>
        </div>
        
        <div class="nav">
            <a href="index.php">📅 Volver a Reservas</a>
            <a href="admin.php">⚙️ Administración</a>
        </div>
        
        <div class="content">
            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo $mensaje; ?></div>
                <a href="index.php" class="btn" style="margin-top: 15px; display: inline-block; text-align: center; text-decoration: none;">
                    🏠 Volver al Inicio
                </a>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!$mensaje): ?>
                <div class="info-box">
                    <strong>⚠️ Importante:</strong> Los horarios marcados con ❌ ya están ocupados y no pueden ser seleccionados.
                </div>
                
                <form method="POST" onsubmit="return validarHorasDisponibles()">
                    <div class="form-group">
                        <label>Nombre Completo *</label>
                        <input type="text" name="nombre" required 
                               placeholder="Ej: Juan Pérez"
                               value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono *</label>
                        <input type="tel" name="telefono" required 
                               placeholder="Ej: 2612345678"
                               value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Email (Opcional)</label>
                        <input type="email" name="email" 
                               placeholder="Ej: juan@email.com"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Cancha *</label>
                        <select name="cancha_id" id="cancha_id" required onchange="actualizarHorariosDisponibles()">
                            <option value="">Selecciona una cancha</option>
                            <?php foreach ($canchas as $cancha): ?>
                                <option value="<?php echo $cancha['id']; ?>" 
                                        data-precio="<?php echo $cancha['precio_hora']; ?>"
                                        <?php echo ($cancha_preseleccionada == $cancha['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cancha['nombre']); ?> 
                                    - $<?php echo number_format($cancha['precio_hora'], 0, ',', '.'); ?>/hora
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha *</label>
                        <input type="date" name="fecha" id="fecha" required 
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo $fecha_preseleccionada; ?>"
                               onchange="actualizarHorariosDisponibles()">
                    </div>
                    
                    <div class="form-group">
                        <label>Hora de Inicio *</label>
                        <select name="hora_inicio" id="hora_inicio" required onchange="calcularTotal()">
                            <option value="">Selecciona hora</option>
                            <?php foreach ($horarios as $hora): ?>
                                <option value="<?php echo $hora; ?>" 
                                        <?php echo ($hora_preseleccionada == $hora) ? 'selected' : ''; ?>>
                                    <?php echo $hora; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Cantidad de Horas *</label>
                        <select name="horas" id="horas" required onchange="calcularTotal()">
                            <option value="">Selecciona horas</option>
                            <option value="1" selected>1 hora</option>
                            <option value="2">2 horas</option>
                            <option value="3">3 horas</option>
                            <option value="4">4 horas</option>
                            <option value="5">5 horas</option>
                        </select>
                    </div>
                    
                    <div id="precio-info" class="precio-info" style="display: none;"></div>
                    
                    <button type="submit" class="btn">✅ Confirmar Reserva</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>