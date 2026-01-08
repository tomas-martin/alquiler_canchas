<?php
require_once 'config.php';

$conn = getConnection();
$mensaje = '';
$error = '';
$reserva_exitosa = false;

// Procesar el formulario
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
    
    // Validar que la fecha no sea pasada
    if ($datos_reserva['fecha'] < date('Y-m-d')) {
        $error = "❌ No puedes reservar en fechas pasadas";
    } else {
        // Validar horario
        $hora_fin_int = (int)substr($datos_reserva['hora_inicio'], 0, 2) + $datos_reserva['horas'];
        
        if ($hora_fin_int > HORA_CIERRE) {
            $error = "❌ La reserva excede el horario de cierre (" . sprintf("%02d:00", HORA_CIERRE) . ")";
        } else {
            // Usar la función segura de creación de reservas
            $resultado = crearReservaSegura($datos_reserva);
            
            if ($resultado['success']) {
                $mensaje = "✅ ¡RESERVA CONFIRMADA CON ÉXITO!<br><br>" .
                          "<strong>Detalles de la reserva:</strong><br>" .
                          "📋 ID de Reserva: #{$resultado['reserva_id']}<br>" .
                          "👤 Cliente: {$datos_reserva['nombre']}<br>" .
                          "📞 Teléfono: {$datos_reserva['telefono']}<br>" .
                          "📅 Fecha: " . date('d/m/Y', strtotime($datos_reserva['fecha'])) . "<br>" .
                          "⏰ Horario: {$datos_reserva['hora_inicio']} ({$datos_reserva['horas']} hora" . ($datos_reserva['horas'] > 1 ? 's' : '') . ")<br>" .
                          "💰 <strong>Total: " . formatearMoneda($resultado['total']) . "</strong>";
                
                if ($datos_reserva['seña'] > 0) {
                    $saldo = $resultado['total'] - $datos_reserva['seña'];
                    $mensaje .= "<br>💵 Seña: " . formatearMoneda($datos_reserva['seña']) . 
                               "<br>💵 Saldo pendiente: " . formatearMoneda($saldo);
                }
                
                $reserva_exitosa = true;
                $_POST = []; // Limpiar formulario
            } else {
                $error = "❌ " . $resultado['mensaje'];
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
        WHERE cancha_id = ? 
        AND fecha = ? 
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
    <title>Nueva Reserva - Sistema Mejorado</title>
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
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideDown 0.3s;
            line-height: 1.8;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
            font-size: 1.05em;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
            font-size: 1.05em;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group select option.ocupado {
            background: #f8d7da;
            color: #721c24;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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
        .btn:hover:not(:disabled) {
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
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
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
            <h1>➕ Nueva Reserva</h1>
            <p>Sistema Mejorado con Validación en Tiempo Real</p>
        </div>
        
        <div class="nav">
            <a href="index.php">📅 Volver a Reservas</a>
            <a href="mis_reservas.php">📋 Mis Reservas</a>
            <a href="admin.php">⚙️ Administración</a>
        </div>
        
        <div class="content">
            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo $mensaje; ?></div>
                <div class="btn-group">
                    <a href="index.php" class="btn" style="text-align: center; text-decoration: none;">
                        🏠 Volver al Inicio
                    </a>
                    <a href="nueva_reserva.php" class="btn btn-secondary" style="text-align: center; text-decoration: none;">
                        ➕ Nueva Reserva
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!$reserva_exitosa): ?>
                <div class="info-box">
                    <strong>⚠️ Importante:</strong> Los horarios marcados con ❌ ya están ocupados y no pueden ser seleccionados. El sistema verifica disponibilidad en tiempo real.
                </div>
                
                <form method="POST" id="formReserva" onsubmit="return validarFormulario(event)">
                    <div class="form-group">
                        <label>Nombre Completo *</label>
                        <input type="text" name="nombre" required 
                               placeholder="Ej: Juan Pérez"
                               pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]{3,100}"
                               title="Ingrese un nombre válido (mínimo 3 caracteres)"
                               value="<?php echo isset($_POST['nombre']) ? sanitizar($_POST['nombre']) : ''; ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Teléfono *</label>
                            <input type="tel" name="telefono" required 
                                   placeholder="Ej: 2612345678"
                                   pattern="[0-9]{7,15}"
                                   title="Ingrese un teléfono válido (solo números)"
                                   value="<?php echo isset($_POST['telefono']) ? sanitizar($_POST['telefono']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Email (Opcional)</label>
                            <input type="email" name="email" 
                                   placeholder="Ej: juan@email.com"
                                   value="<?php echo isset($_POST['email']) ? sanitizar($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Cancha *</label>
                        <select name="cancha_id" id="cancha_id" required onchange="actualizarHorariosDisponibles()">
                            <option value="">Selecciona una cancha</option>
                            <?php foreach ($canchas as $cancha): ?>
                                <option value="<?php echo $cancha['id']; ?>" 
                                        data-precio="<?php echo $cancha['precio_hora']; ?>"
                                        <?php echo ($cancha_preseleccionada == $cancha['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizar($cancha['nombre']); ?> 
                                    - <?php echo formatearMoneda($cancha['precio_hora']); ?>/hora
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
                    
                    <div class="form-row">
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
                                <option value="">Selecciona</option>
                                <option value="1" selected>1 hora</option>
                                <option value="2">2 horas</option>
                                <option value="3">3 horas</option>
                                <option value="4">4 horas</option>
                                <option value="5">5 horas</option>
                                <option value="6">6 horas</option>
                                <option value="7">7 horas</option>
                                <option value="8">8 horas</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Seña / Anticipo (Opcional)</label>
                            <input type="number" name="sena" id="sena" min="0" step="0.01" 
                                   placeholder="Ej: 2000"
                                   onchange="calcularTotal()">
                        </div>
                        
                        <div class="form-group">
                            <label>Método de Pago</label>
                            <select name="metodo_pago" id="metodo_pago">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="mercadopago">MercadoPago</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Notas / Comentarios (Opcional)</label>
                        <textarea name="notas" placeholder="Ej: Traer pelota, necesito vestuarios, etc."></textarea>
                    </div>
                    
                    <div id="precio-info" class="precio-info" style="display: none;"></div>
                    
                    <button type="submit" class="btn" id="btnSubmit">✅ Confirmar Reserva</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let horariosOcupados = <?php echo json_encode($horarios_ocupados); ?>;
        
        async function actualizarHorariosDisponibles() {
            const canchaSelect = document.getElementById('cancha_id');
            const fechaInput = document.getElementById('fecha');
            const horaSelect = document.getElementById('hora_inicio');
            
            if (canchaSelect.value && fechaInput.value) {
                document.getElementById('loading').classList.add('active');
                
                try {
                    const response = await fetch(`get_horarios_ocupados.php?cancha=${canchaSelect.value}&fecha=${fechaInput.value}`);
                    const data = await response.json();
                    horariosOcupados = data;
                    actualizarSelectHorarios();
                    calcularTotal();
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error al cargar horarios disponibles');
                } finally {
                    document.getElementById('loading').classList.remove('active');
                }
            }
        }
        
        function actualizarSelectHorarios() {
            const horaSelect = document.getElementById('hora_inicio');
            const options = horaSelect.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value && horariosOcupados.includes(option.value)) {
                    option.classList.add('ocupado');
                    option.textContent = option.value + ' - ❌ OCUPADO';
                    option.disabled = true;
                } else if (option.value) {
                    option.classList.remove('ocupado');
                    option.textContent = option.value + ' - ✅ Disponible';
                    option.disabled = false;
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
                alert('Por favor complete todos los campos obligatorios');
                return false;
            }
            
            // Verificar disponibilidad en tiempo real
            const horaInicioInt = parseInt(horaInicio.split(':')[0]);
            const horasAVerificar = [];
            
            for (let i = 0; i < horas; i++) {
                horasAVerificar.push(String(horaInicioInt + i).padStart(2, '0') + ':00');
            }
            
            document.getElementById('loading').classList.add('active');
            document.getElementById('btnSubmit').disabled = true;
            
            try {
                // Verificar cada hora
                for (const hora of horasAVerificar) {
                    const response = await fetch('verificar_disponibilidad.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ cancha_id: canchaId, fecha: fecha, hora: hora })
                    });
                    
                    const data = await response.json();
                    
                    if (!data.disponible) {
                        alert(`❌ La hora ${hora} acaba de ser reservada por otro usuario. Por favor, seleccione otro horario.`);
                        document.getElementById('loading').classList.remove('active');
                        document.getElementById('btnSubmit').disabled = false;
                        await actualizarHorariosDisponibles();
                        return false;
                    }
                }
                
                // Si todas las horas están disponibles, enviar formulario
                document.getElementById('formReserva').submit();
                return true;
                
            } catch (error) {
                console.error('Error:', error);
                alert('Error al verificar disponibilidad. Por favor, intente nuevamente.');
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
                
                let html = `<h3>Total: $${total.toLocaleString('es-AR')}</h3>`;
                html += `<p>${horas} hora(s) × $${precio.toLocaleString('es-AR')} = $${total.toLocaleString('es-AR')}</p>`;
                
                if (sena > 0) {
                    if (sena > total) {
                        senaInput.value = total;
                        html += `<p style="color: #e74c3c;">⚠️ La seña no puede ser mayor al total</p>`;
                    } else {
                        html += `<p>💵 Seña: $${sena.toLocaleString('es-AR')}</p>`;
                        html += `<p>💵 Saldo pendiente: $${saldo.toLocaleString('es-AR')}</p>`;
                    }
                }
                
                precioInfo.innerHTML = html;
                precioInfo.style.display = 'block';
            } else {
                precioInfo.style.display = 'none';
            }
        }
        
        // Inicializar
        window.onload = function() {
            actualizarSelectHorarios();
            calcularTotal();
        }
    </script>
</body>
</html>