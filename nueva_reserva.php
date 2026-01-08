<?php
require_once 'config.php';

$conn = getConnection();
$mensaje = '';
$error = '';
$reserva_exitosa = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos_reserva = [
        'nombre' => trim($_POST['nombre']),
        'telefono' => trim($_POST['telefono']),
        'email' => trim($_POST['email']) ?: null,
        'cancha_id' => (int)$_POST['cancha_id'],
        'fecha' => $_POST['fecha'],
        'hora_inicio' => $_POST['hora_inicio'],
        'horas' => (int)$_POST['horas'],
        'seña' => isset($_POST['sena']) ? (float)$_POST['sena'] : 0,
        'metodo_pago' => $_POST['metodo_pago'] ?? 'efectivo',
        'notas' => trim($_POST['notas']) ?: null
    ];
    
    if ($datos_reserva['fecha'] < date('Y-m-d')) {
        $error = "No puedes reservar en fechas pasadas";
    } else {
        $hora_fin_int = (int)substr($datos_reserva['hora_inicio'], 0, 2) + $datos_reserva['horas'];
        
        if ($hora_fin_int > HORA_CIERRE) {
            $error = "La reserva excede el horario de cierre";
        } else {
            $resultado = crearReservaSegura($datos_reserva);
            
            if ($resultado['success']) {
                $mensaje = "✅ ¡RESERVA CONFIRMADA!<br><br>" .
                          "<strong>Detalles:</strong><br>" .
                          "📋 ID: #{$resultado['reserva_id']}<br>" .
                          "👤 Cliente: {$datos_reserva['nombre']}<br>" .
                          "📞 Teléfono: {$datos_reserva['telefono']}<br>" .
                          "📅 Fecha: " . date('d/m/Y', strtotime($datos_reserva['fecha'])) . "<br>" .
                          "⏰ Horario: {$datos_reserva['hora_inicio']} ({$datos_reserva['horas']}h)<br>" .
                          "💰 Total: " . formatearMoneda($resultado['total']);
                
                if ($datos_reserva['seña'] > 0) {
                    $saldo = $resultado['total'] - $datos_reserva['seña'];
                    $mensaje .= "<br>💵 Seña: " . formatearMoneda($datos_reserva['seña']) . 
                               "<br>💵 Saldo: " . formatearMoneda($saldo);
                }
                
                $reserva_exitosa = true;
                $_POST = [];
            } else {
                $error = $resultado['mensaje'];
            }
        }
    }
}

$cancha_preseleccionada = isset($_GET['cancha']) ? (int)$_GET['cancha'] : 0;
$fecha_preseleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$hora_preseleccionada = isset($_GET['hora']) ? $_GET['hora'] : '';

$canchas = $conn->query("SELECT * FROM canchas WHERE activa = TRUE ORDER BY id")->fetchAll();
$horarios = generarHorarios();

$horarios_ocupados = [];
if ($cancha_preseleccionada && $fecha_preseleccionada) {
    $stmt = $conn->prepare("
        SELECT hora_inicio FROM reservas 
        WHERE cancha_id = ? AND fecha = ? 
        AND estado IN ('pendiente', 'confirmada', 'completada')
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
    <title>⚽ Nueva Reserva</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --dark-bg: #0a0e27;
            --card-bg: #141b2d;
            --accent-green: #00ff87;
            --accent-blue: #00d9ff;
            --accent-red: #ff0055;
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
            max-width: 900px;
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
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            border-left: 4px solid;
            animation: slideIn 0.3s;
            line-height: 1.8;
            font-size: 1.05em;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(0, 255, 135, 0.1), rgba(0, 255, 135, 0.05));
            border-color: var(--accent-green);
            color: var(--accent-green);
        }
        
        .alert-error {
            background: linear-gradient(135deg, rgba(255, 0, 85, 0.1), rgba(255, 0, 85, 0.05));
            border-color: var(--accent-red);
            color: var(--accent-red);
        }
        
        .form-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .info-box {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 215, 0, 0.05));
            border: 2px solid #ffd700;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            color: #ffd700;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--accent-green);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9em;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            background: var(--dark-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-light);
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 20px rgba(0, 255, 135, 0.2);
        }
        
        .form-group select option.ocupado {
            background: rgba(255, 0, 85, 0.2);
            color: var(--accent-red);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--accent-green), #00cc6a);
            color: var(--dark-bg);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 700;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 255, 135, 0.4);
        }
        
        .btn:disabled {
            background: var(--border-color);
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: var(--card-bg);
            color: var(--text-light);
            border: 2px solid var(--border-color);
        }
        
        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        
        .precio-info {
            background: linear-gradient(135deg, rgba(0, 217, 255, 0.1), rgba(0, 217, 255, 0.05));
            border: 2px solid var(--accent-blue);
            padding: 25px;
            border-radius: 15px;
            margin-top: 25px;
            text-align: center;
        }
        
        .precio-info h3 {
            color: var(--accent-blue);
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .precio-info p {
            color: var(--text-gray);
            font-size: 1.1em;
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
        
        .loading.active { display: flex; }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(0, 255, 135, 0.2);
            border-top: 4px solid var(--accent-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .btn-group { grid-template-columns: 1fr; }
            .header h1 { font-size: 2em; }
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-plus-circle"></i> NUEVA RESERVA</h1>
            <p>Sistema de Reservas con Validación en Tiempo Real</p>
        </div>
        
        <div class="nav">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Volver</a>
            <a href="mis_reservas.php"><i class="fas fa-list"></i> Mis Reservas</a>
            <a href="admin.php"><i class="fas fa-cog"></i> Admin</a>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
            <div class="btn-group">
                <a href="index.php" class="btn" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-home"></i> Inicio
                </a>
                <a href="nueva_reserva.php" class="btn btn-secondary" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-plus"></i> Nueva
                </a>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!$reserva_exitosa): ?>
            <div class="info-box">
                <i class="fas fa-info-circle"></i> <strong>Importante:</strong> Los horarios con ❌ están ocupados. El sistema verifica disponibilidad en tiempo real.
            </div>
            
            <div class="form-card">
                <form method="POST" id="formReserva" onsubmit="return validarFormulario(event)">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nombre Completo *</label>
                        <input type="text" name="nombre" required 
                               placeholder="Ej: Juan Pérez"
                               pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]{3,100}">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Teléfono *</label>
                            <input type="tel" name="telefono" required 
                                   placeholder="2612345678"
                                   pattern="[0-9]{7,15}">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="email" placeholder="juan@email.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-futbol"></i> Cancha *</label>
                        <select name="cancha_id" id="cancha_id" required onchange="actualizarHorariosDisponibles()">
                            <option value="">Selecciona una cancha</option>
                            <?php foreach ($canchas as $cancha): ?>
                                <option value="<?php echo $cancha['id']; ?>" 
                                        data-precio="<?php echo $cancha['precio_hora']; ?>"
                                        <?php echo ($cancha_preseleccionada == $cancha['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizar($cancha['nombre']); ?> - <?php echo formatearMoneda($cancha['precio_hora']); ?>/h
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Fecha *</label>
                        <input type="date" name="fecha" id="fecha" required 
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo $fecha_preseleccionada; ?>"
                               onchange="actualizarHorariosDisponibles()">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Hora de Inicio *</label>
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
                            <label><i class="fas fa-hourglass-half"></i> Horas *</label>
                            <select name="horas" id="horas" required onchange="calcularTotal()">
                                <option value="1" selected>1 hora</option>
                                <?php for($i = 2; $i <= 8; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> horas</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-money-bill-wave"></i> Seña / Anticipo</label>
                            <input type="number" name="sena" id="sena" min="0" step="0.01" 
                                   placeholder="0" onchange="calcularTotal()">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-credit-card"></i> Método de Pago</label>
                            <select name="metodo_pago">
                                <option value="efectivo">💵 Efectivo</option>
                                <option value="transferencia">🏦 Transferencia</option>
                                <option value="tarjeta">💳 Tarjeta</option>
                                <option value="mercadopago">📱 MercadoPago</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-comment"></i> Notas</label>
                        <textarea name="notas" rows="3" placeholder="Comentarios adicionales..."></textarea>
                    </div>
                    
                    <div id="precio-info" class="precio-info" style="display: none;"></div>
                    
                    <button type="submit" class="btn" id="btnSubmit">
                        <i class="fas fa-check-circle"></i> CONFIRMAR RESERVA
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let horariosOcupados = <?php echo json_encode($horarios_ocupados); ?>;
        
        async function actualizarHorariosDisponibles() {
            const canchaId = document.getElementById('cancha_id').value;
            const fecha = document.getElementById('fecha').value;
            
            if (canchaId && fecha) {
                document.getElementById('loading').classList.add('active');
                
                try {
                    const response = await fetch(`get_horarios_ocupados.php?cancha=${canchaId}&fecha=${fecha}`);
                    horariosOcupados = await response.json();
                    actualizarSelectHorarios();
                    calcularTotal();
                } catch (error) {
                    alert('Error al cargar horarios');
                } finally {
                    document.getElementById('loading').classList.remove('active');
                }
            }
        }
        
        function actualizarSelectHorarios() {
            const select = document.getElementById('hora_inicio');
            Array.from(select.options).forEach(opt => {
                if (opt.value && horariosOcupados.includes(opt.value)) {
                    opt.classList.add('ocupado');
                    opt.textContent = opt.value + ' - ❌ OCUPADO';
                    opt.disabled = true;
                } else if (opt.value) {
                    opt.classList.remove('ocupado');
                    opt.textContent = opt.value + ' - ✅ Disponible';
                    opt.disabled = false;
                }
            });
        }
        
        async function validarFormulario(event) {
            event.preventDefault();
            
            const horaInicio = document.getElementById('hora_inicio').value;
            const horas = parseInt(document.getElementById('horas').value);
            const canchaId = document.getElementById('cancha_id').value;
            const fecha = document.getElementById('fecha').value;
            
            if (!horaInicio || !horas || !canchaId || !fecha) {
                alert('Complete todos los campos obligatorios');
                return false;
            }
            
            const horaInicioInt = parseInt(horaInicio.split(':')[0]);
            const horasAVerificar = [];
            
            for (let i = 0; i < horas; i++) {
                horasAVerificar.push(String(horaInicioInt + i).padStart(2, '0') + ':00');
            }
            
            document.getElementById('loading').classList.add('active');
            document.getElementById('btnSubmit').disabled = true;
            
            try {
                for (const hora of horasAVerificar) {
                    const response = await fetch('verificar_disponibilidad.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ cancha_id: canchaId, fecha: fecha, hora: hora })
                    });
                    
                    const data = await response.json();
                    
                    if (!data.disponible) {
                        alert(`❌ La hora ${hora} fue reservada. Seleccione otro horario.`);
                        document.getElementById('loading').classList.remove('active');
                        document.getElementById('btnSubmit').disabled = false;
                        await actualizarHorariosDisponibles();
                        return false;
                    }
                }
                
                document.getElementById('formReserva').submit();
                return true;
                
            } catch (error) {
                alert('Error al verificar disponibilidad');
                document.getElementById('loading').classList.remove('active');
                document.getElementById('btnSubmit').disabled = false;
                return false;
            }
        }
        
        function calcularTotal() {
            const canchaSelect = document.getElementById('cancha_id');
            const horasSelect = document.getElementById('horas');
            const senaInput = document.getElementById('sena');
            const precioInfo = document.getElementById('precio-info');
            
            if (canchaSelect.value && horasSelect.value) {
                const precio = parseFloat(canchaSelect.options[canchaSelect.selectedIndex].dataset.precio);
                const horas = parseInt(horasSelect.value);
                const total = precio * horas;
                const sena = parseFloat(senaInput.value) || 0;
                const saldo = total - sena;
                
                let html = `<h3><i class="fas fa-dollar-sign"></i> Total: $${total.toLocaleString('es-AR')}</h3>`;
                html += `<p>${horas} hora(s) × $${precio.toLocaleString('es-AR')} = $${total.toLocaleString('es-AR')}</p>`;
                
                if (sena > 0) {
                    if (sena > total) {
                        senaInput.value = total;
                        html += `<p style="color: var(--accent-red);">⚠️ La seña no puede exceder el total</p>`;
                    } else {
                        html += `<p>💵 Seña: $${sena.toLocaleString('es-AR')}</p>`;
                        html += `<p>💵 Saldo: $${saldo.toLocaleString('es-AR')}</p>`;
                    }
                }
                
                precioInfo.innerHTML = html;
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
</body>
</html>