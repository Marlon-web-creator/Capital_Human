<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'empleado') {
    header('Location: ../../index.php');
    exit();
}

date_default_timezone_set('America/Bogota');

$host = 'localhost';
$dbname = 'capital_human';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("SET time_zone = '-05:00'");
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
function obtenerFotoPerfil($foto_perfil)
{
    if ($foto_perfil && file_exists('../../models/uploads/perfiles/' . $foto_perfil)) {
        return '../../models/uploads/perfiles/' . $foto_perfil;
    }
    return '/Capital_HumanMVC/public/images/logo-banco.png';
}

$usuario_id = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT nombre_completo, foto_perfil FROM empleados WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $empleado_actual = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empleado_actual) {
        $empleado_name = $empleado_actual['nombre_completo'];
        $foto_perfil_path = obtenerFotoPerfil($empleado_actual['foto_perfil']);
    } else {
        $empleado_name = 'Usuario';
        $foto_perfil_path = '/Capital_HumanMVC/public/images/logo-banco.png';
    }
} catch (PDOException $e) {
    $empleado_name = 'Usuario';
    $foto_perfil_path = '/Capital_HumanMVC/public/images/logo-banco.png';
}

if (!isset($foto_perfil_path)) {
    $foto_perfil_path = '/Capital_HumanMVC/public/images/logo-banco.png';
}
$mensaje = '';
$tipo_mensaje = '';

// FUNCIÓN PARA DETERMINAR EL ESTADO DE ASISTENCIA
function determinarEstadoAsistencia($hora_entrada)
{
    $hora_limite_presente = '10:15:00';
    $hora_limite_tardanza = '11:15:00';
    $hora_limite_tarde = '14:00:00';

    $entrada = new DateTime($hora_entrada);
    $limite_presente = new DateTime($hora_limite_presente);
    $limite_tardanza = new DateTime($hora_limite_tardanza);
    $limite_tarde = new DateTime($hora_limite_tarde);

    if ($entrada >= $limite_tarde) {
        return 'presente';
    }

    if ($entrada <= $limite_presente) {
        return 'presente';
    } elseif ($entrada <= $limite_tardanza) {
        return 'tardanza';
    } else {
        return 'ausente';
    }
}

// FUNCIÓN PARA MARCAR AUSENCIAS AUTOMÁTICAS
function marcarAusenciasAutomaticas($pdo)
{
    $fecha_actual = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
    $hora_limite_ausencia = '11:15:00';
    $hora_actual = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('H:i:s');

    if ($hora_actual >= $hora_limite_ausencia) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM empleados WHERE estado = 'activo'");
            $stmt->execute();
            $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($empleados as $empleado) {
                $check_stmt = $pdo->prepare("SELECT id, hora_entrada FROM asistencias WHERE empleado_id = ? AND fecha = ?");
                $check_stmt->execute([$empleado['id'], $fecha_actual]);
                $asistencia = $check_stmt->fetch();

                if (!$asistencia) {
                    $insert_stmt = $pdo->prepare("INSERT INTO asistencias (empleado_id, fecha, estado, notas) VALUES (?, ?, 'ausente', 'Ausencia automática - No registró entrada')");
                    $insert_stmt->execute([$empleado['id'], $fecha_actual]);
                } elseif ($asistencia['hora_entrada']) {
                    $estado_actual = determinarEstadoAsistencia($asistencia['hora_entrada']);
                    if ($estado_actual === 'ausente') {
                        $update_stmt = $pdo->prepare("UPDATE asistencias SET estado = 'ausente', notas = 'Entrada tardía - Considerado ausente' WHERE id = ?");
                        $update_stmt->execute([$asistencia['id']]);
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Error al marcar ausencias automáticas: " . $e->getMessage());
        }
    }
}

marcarAusenciasAutomaticas($pdo);

// FUNCIONES ENTRADA/SALIDA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['marcar_entrada'])) {
        try {
            $fecha_actual = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
            $hora_actual = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('H:i:s');

            $check_stmt = $pdo->prepare("SELECT id, hora_entrada FROM asistencias WHERE empleado_id = ? AND fecha = ?");
            $check_stmt->execute([$usuario_id, $fecha_actual]);
            $asistencia_existente = $check_stmt->fetch();

            if ($asistencia_existente && $asistencia_existente['hora_entrada']) {
                $mensaje = "Ya tienes registrada tu entrada para hoy";
                $tipo_mensaje = "error";
            } else {
                $estado = determinarEstadoAsistencia($hora_actual);

                $hora_limite_entrada = '11:15:00';
                $hora_limite_tarde = '14:00:00';
                $entrada_time = new DateTime($hora_actual);
                $limite_entrada = new DateTime($hora_limite_entrada);
                $limite_tarde = new DateTime($hora_limite_tarde);

                if ($entrada_time > $limite_entrada && $entrada_time < $limite_tarde) {
                    $mensaje = "Es demasiado tarde para registrar entrada. Después de las 11:15 AM se considera ausencia. Contacta con Recursos Humanos.";
                    $tipo_mensaje = "error";
                } else {
                    if ($asistencia_existente) {
                        $notas = '';
                        if ($estado === 'ausente') {
                            $notas = 'Entrada registrada después del horario permitido';
                        }
                        $stmt = $pdo->prepare("UPDATE asistencias SET hora_entrada = ?, estado = ?, notas = ? WHERE id = ?");
                        $stmt->execute([$hora_actual, $estado, $notas, $asistencia_existente['id']]);
                    } else {
                        $notas = '';
                        if ($estado === 'ausente') {
                            $notas = 'Entrada registrada después del horario permitido';
                        }
                        $stmt = $pdo->prepare("INSERT INTO asistencias (empleado_id, fecha, hora_entrada, estado, notas) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$usuario_id, $fecha_actual, $hora_actual, $estado, $notas]);
                    }

                    $hora_formateada = (new DateTime($hora_actual))->format('H:i');
                    switch ($estado) {
                        case 'presente':
                            $mensaje = "Entrada registrada correctamente a las " . $hora_formateada;
                            $tipo_mensaje = "success";
                            break;
                        case 'tardanza':
                            $mensaje = "Entrada registrada con tardanza a las " . $hora_formateada . " (después de las 10:15 AM)";
                            $tipo_mensaje = "warning";
                            break;
                        case 'ausente':
                            $mensaje = "Entrada registrada como ausencia a las " . $hora_formateada . " (después de las 11:15 AM). Contacta con Recursos Humanos.";
                            $tipo_mensaje = "error";
                            break;
                    }
                }
            }
        } catch (PDOException $e) {
            $mensaje = "Error al registrar entrada: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }

    if (isset($_POST['marcar_salida'])) {
        try {
            $fecha_actual = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
            $hora_actual = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('H:i:s');

            $check_stmt = $pdo->prepare("SELECT id, hora_entrada, hora_salida FROM asistencias WHERE empleado_id = ? AND fecha = ?");
            $check_stmt->execute([$usuario_id, $fecha_actual]);
            $asistencia_existente = $check_stmt->fetch();

            if (!$asistencia_existente || !$asistencia_existente['hora_entrada']) {
                $mensaje = "Debes marcar tu entrada antes de registrar la salida";
                $tipo_mensaje = "error";
            } elseif ($asistencia_existente['hora_salida']) {
                $mensaje = "Ya tienes registrada tu salida para hoy";
                $tipo_mensaje = "error";
            } else {
                $stmt = $pdo->prepare("UPDATE asistencias SET hora_salida = ? WHERE id = ?");
                $stmt->execute([$hora_actual, $asistencia_existente['id']]);

                $mensaje = "Salida registrada correctamente a las " . (new DateTime($hora_actual))->format('H:i');
                $tipo_mensaje = "success";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al registrar salida: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// OBTENER ASISTENCIA DE HOY
$fecha_actual = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
try {
    $stmt = $pdo->prepare("SELECT * FROM asistencias WHERE empleado_id = ? AND fecha = ?");
    $stmt->execute([$usuario_id, $fecha_actual]);
    $asistencia_hoy = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $asistencia_hoy = null;
}

$fecha_limite = (new DateTime('now', new DateTimeZone('America/Bogota')))->modify('-30 days')->format('Y-m-d');
try {
    $stmt = $pdo->prepare("
        SELECT fecha, hora_entrada, hora_salida, estado, notas
        FROM asistencias 
        WHERE empleado_id = ? AND fecha >= ? 
        ORDER BY fecha DESC
    ");
    $stmt->execute([$usuario_id, $fecha_limite]);
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $historial = [];
}

$primer_dia_mes = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('Y-m-01');
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_dias,
            SUM(CASE WHEN estado = 'presente' THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN estado = 'tardanza' THEN 1 ELSE 0 END) as tardanzas,
            SUM(CASE WHEN estado = 'ausente' THEN 1 ELSE 0 END) as ausentes,
            SUM(CASE WHEN estado = 'permiso' THEN 1 ELSE 0 END) as permisos
        FROM asistencias 
        WHERE empleado_id = ? AND fecha >= ?
    ");
    $stmt->execute([$usuario_id, $primer_dia_mes]);
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $estadisticas = [
        'total_dias' => 0,
        'presentes' => 0,
        'tardanzas' => 0,
        'ausentes' => 0,
        'permisos' => 0
    ];
}

function formatearHora($hora)
{
    if (!$hora) return '-';
    try {
        return (new DateTime($hora))->format('H:i');
    } catch (Exception $e) {
        return '-';
    }
}

function obtenerClaseEstado($estado)
{
    switch ($estado) {
        case 'presente':
            return 'status-success';
        case 'tardanza':
            return 'status-warning';
        case 'permiso':
            return 'status-info';
        case 'pendiente':
            return 'status-secondary';
        default:
            return 'status-danger';
    }
}

function obtenerIconoEstado($estado)
{
    switch ($estado) {
        case 'presente':
            return 'bi-check-circle';
        case 'tardanza':
            return 'bi-clock';
        case 'permiso':
            return 'bi-calendar-event';
        case 'pendiente':
            return 'bi-hourglass-split';
        default:
            return 'bi-x-circle';
    }
}

function formatearFecha($fecha)
{
    if (!$fecha) return '-';
    try {
        return (new DateTime($fecha))->format('d/m/Y');
    } catch (Exception $e) {
        return '-';
    }
}

function obtenerEstadoEntradaActual()
{
    $hora_actual = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('H:i:s');
    $hora_limite_presente = '10:15:00';
    $hora_limite_tardanza = '11:15:00';
    $hora_limite_tarde = '14:00:00';

    $entrada = new DateTime($hora_actual);
    $limite_presente = new DateTime($hora_limite_presente);
    $limite_tardanza = new DateTime($hora_limite_tardanza);
    $limite_tarde = new DateTime($hora_limite_tarde);

    if ($entrada >= $limite_tarde) {
        return ['estado' => 'presente', 'mensaje' => 'Turno de tarde - Entrada normal'];
    } elseif ($entrada <= $limite_presente) {
        return ['estado' => 'presente', 'mensaje' => 'Entrada a tiempo'];
    } elseif ($entrada <= $limite_tardanza) {
        return ['estado' => 'tardanza', 'mensaje' => 'Entrada con tardanza'];
    } else {
        return ['estado' => 'ausente', 'mensaje' => 'Muy tarde - Se considerará ausencia'];
    }
}

$estado_entrada_actual = obtenerEstadoEntradaActual();

$fecha_hoy_formato = (new DateTime('now', new DateTimeZone('America/Bogota')))->format('d/m/Y');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Asistencias | Capital Human</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Capital_HumanMVC/public/css/client/asistencias8.css">
    <style>
        .attendance-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .attendance-warning.danger {
            background: #f8d7da;
            border-color: #f5c6cb;
        }

        .attendance-warning.success {
            background: #d1edff;
            border-color: #bee5eb;
        }

        .attendance-info {
            background: #e8f4f8;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .time-indicator {
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .time-indicator.success {
            background: #d4edda;
            color: #155724;
        }

        .time-indicator.warning {
            background: #fff3cd;
            color: #856404;
        }

        .time-indicator.danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>
    <div class="employee-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="/Capital_HumanMVC/public/images/logo-blanco.png" alt="Capital Human Logo" class="sidebar-logo">
                <h2>Capital Human</h2>
            </div>

            <ul class="sidebar-menu">
                <li>
                    <a href="employee_dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
                </li>
                <li>
                    <a href="mi_perfil.php"><i class="bi bi-person-fill"></i> Mi Perfil</a>
                </li>
                <li>
                    <a href="mis_nominas.php"><i class="bi bi-cash-stack"></i> Mis Nóminas</a>
                </li>
                <li class="active">
                    <a href="mis_asistencias.php"><i class="bi bi-calendar-check"></i> Mis Asistencias</a>
                </li>
                <li>
                    <a href="mis_mensajes.php"><i class="bi bi-calendar-event"></i> Mis Mensajes</a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="/Capital_HumanMVC/index.php?action=logout"><i class="bi bi-box-arrow-left"></i> Cerrar Sesión</a>
            </div>
        </aside>
        <main class="main-content">
            <!-- TOP BAR -->
            <div class="topbar">
                <button id="sidebar-toggle" class="menu-toggle">
                    <i class="bi bi-list"></i>
                </button>

                <div class="topbar-right">
                    <div class="user-profile">
                        <img src="<?php echo htmlspecialchars($foto_perfil_path); ?>"
                            alt="Empleado Avatar"
                            onerror="this.src='../../images/empleado-avatar.png'">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($empleado_name); ?></span>
                            <span class="user-role">Empleado</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="page-header">
                    <h1>Mis Asistencias</h1>
                    <p>Controla tu asistencia diaria y revisa tu historial</p>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : ($tipo_mensaje === 'warning' ? 'warning' : 'danger'); ?> alert-dismissible">
                        <i class="bi bi-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'warning' ? 'exclamation-triangle' : 'exclamation-triangle'); ?>"></i>
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close-alert" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>

                <div class="attendance-info">
                    <h5><i class="bi bi-info-circle"></i> Información de Horarios</h5>
                    <p><strong>Horario de Ingreso:</strong></p>
                    <ul style="margin-left: 20px; margin-bottom: 10px;">
                        <li>⏰ <strong>Hasta 10:15 AM:</strong> <span class="time-indicator success">Presente</span></li>
                        <li>⏰ <strong>10:16 AM - 11:15 AM:</strong> <span class="time-indicator warning">Tardanza</span></li>
                        <li>⏰ <strong>Después de 11:15 AM:</strong> <span class="time-indicator danger">Ausencia</span></li>
                    </ul>
                </div>

                <?php if (!$asistencia_hoy || !$asistencia_hoy['hora_entrada']): ?>
                    <div class="attendance-warning <?php echo $estado_entrada_actual['estado'] === 'presente' ? 'success' : ($estado_entrada_actual['estado'] === 'tardanza' ? '' : 'danger'); ?>">
                        <i class="bi bi-<?php echo $estado_entrada_actual['estado'] === 'presente' ? 'check-circle' : ($estado_entrada_actual['estado'] === 'tardanza' ? 'exclamation-triangle' : 'x-circle'); ?>"></i>
                        <div>
                            <strong>Estado actual si marcas entrada ahora:</strong>
                            <?php echo $estado_entrada_actual['mensaje']; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="attendance-card">
                    <div class="card-header">
                        <h3><i class="bi bi-clock"></i> Marcar Asistencia - <?php echo $fecha_hoy_formato; ?></h3>
                        <div class="current-time" id="currentTime"></div>
                    </div>
                    <div class="card-body">
                        <div class="attendance-status">
                            <?php if ($asistencia_hoy): ?>
                                <div class="status-info">
                                    <div class="status-item">
                                        <span class="label">Estado:</span>
                                        <span class="status-badge <?php echo obtenerClaseEstado($asistencia_hoy['estado']); ?>">
                                            <i class="bi <?php echo obtenerIconoEstado($asistencia_hoy['estado']); ?>"></i>
                                            <?php echo ucfirst($asistencia_hoy['estado']); ?>
                                        </span>
                                    </div>
                                    <?php if ($asistencia_hoy['hora_entrada']): ?>
                                        <div class="status-item">
                                            <span class="label">Entrada:</span>
                                            <span class="time"><?php echo formatearHora($asistencia_hoy['hora_entrada']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($asistencia_hoy['hora_salida']): ?>
                                        <div class="status-item">
                                            <span class="label">Salida:</span>
                                            <span class="time"><?php echo formatearHora($asistencia_hoy['hora_salida']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($asistencia_hoy['notas']): ?>
                                        <div class="status-item">
                                            <span class="label">Observaciones:</span>
                                            <span class="notes"><?php echo htmlspecialchars($asistencia_hoy['notas']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-attendance">
                                    <i class="bi bi-hourglass-split"></i>
                                    <p>No has marcado asistencia hoy</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="attendance-buttons">
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="marcar_entrada" class="btn btn-success"
                                    <?php echo ($asistencia_hoy && $asistencia_hoy['hora_entrada']) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-box-arrow-in-right"></i>
                                    <?php echo ($asistencia_hoy && $asistencia_hoy['hora_entrada']) ? 'Entrada Registrada' : 'Marcar Entrada'; ?>
                                </button>
                            </form>

                            <form method="POST" style="display: inline;">
                                <button type="submit" name="marcar_salida" class="btn btn-danger"
                                    <?php echo (!$asistencia_hoy || !$asistencia_hoy['hora_entrada'] || $asistencia_hoy['hora_salida']) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-box-arrow-right"></i>
                                    <?php echo ($asistencia_hoy && $asistencia_hoy['hora_salida']) ? 'Salida Registrada' : 'Marcar Salida'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card success">
                        <i class="bi bi-check-circle"></i>
                        <div class="stat-number"><?php echo $estadisticas['presentes']; ?></div>
                        <div class="stat-label">Presentes</div>
                    </div>
                    <div class="stat-card warning">
                        <i class="bi bi-clock"></i>
                        <div class="stat-number"><?php echo $estadisticas['tardanzas']; ?></div>
                        <div class="stat-label">Tardanzas</div>
                    </div>
                    <div class="stat-card danger">
                        <i class="bi bi-x-circle"></i>
                        <div class="stat-number"><?php echo $estadisticas['ausentes']; ?></div>
                        <div class="stat-label">Ausentes</div>
                    </div>
                    <div class="stat-card info">
                        <i class="bi bi-calendar-event"></i>
                        <div class="stat-number"><?php echo $estadisticas['permisos']; ?></div>
                        <div class="stat-label">Permisos</div>
                    </div>
                </div>

                <div class="table-card">
                    <div class="card-header">
                        <h5><i class="bi bi-calendar-date"></i> Historial de Asistencias (Últimos 30 días)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Entrada</th>
                                    <th>Salida</th>
                                    <th>Estado</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($historial)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No hay registros de asistencia</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($historial as $registro): ?>
                                        <tr>
                                            <td><?php echo formatearFecha($registro['fecha']); ?></td>
                                            <td><?php echo formatearHora($registro['hora_entrada']); ?></td>
                                            <td><?php echo formatearHora($registro['hora_salida']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo obtenerClaseEstado($registro['estado']); ?>">
                                                    <i class="bi <?php echo obtenerIconoEstado($registro['estado']); ?>"></i>
                                                    <?php echo ucfirst($registro['estado']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $registro['notas'] ? htmlspecialchars($registro['notas']) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        function updateCurrentTime() {
            const now = new Date();
            const options = {
                timeZone: 'America/Bogota',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            };
            const timeString = now.toLocaleTimeString('es-CO', options);
            const dateString = now.toLocaleDateString('es-CO', {
                timeZone: 'America/Bogota',
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            document.getElementById('currentTime').innerHTML = `
                <div class="time-display">
                    <div class="time">${timeString}</div>
                    <div class="date">${dateString}</div>
                </div>
            `;
        }

        updateCurrentTime();
        setInterval(updateCurrentTime, 1000);

        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.employee-container').classList.toggle('sidebar-collapsed');
        });

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>

</html>