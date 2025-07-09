<?php
date_default_timezone_set('America/Bogota');
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'empleado') {
    header('Location: ../../index.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=capital_human;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '-05:00'");
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$usuario_id = $_SESSION['usuario_id'];
$empleado_name = "Empleado";
$foto_perfil_path = '/Capital_HumanMVC/public/images/avatar.png';
$fecha_hoy = date('Y-m-d');
$mes_actual = date('Y-m');

function obtenerFotoPerfil($foto_perfil)
{
    $ruta_uploads = '/Capital_HumanMVC/app/models/uploads/perfiles/';
    $ruta_default = '/Capital_HumanMVC/public/images/avatar.png';

    if ($foto_perfil && file_exists($_SERVER['DOCUMENT_ROOT'] . $ruta_uploads . $foto_perfil)) {
        return $ruta_uploads . $foto_perfil;
    }

    return $ruta_default;
}

function formatearMoneda($numero)
{
    return '$' . number_format($numero, 0, ',', '.');
}

function formatearFecha($fecha)
{
    return empty($fecha) ? 'N/A' : date('d/m/Y', strtotime($fecha));
}

function formatearHora($hora)
{
    return empty($hora) ? 'N/A' : date('H:i', strtotime($hora));
}

function obtenerEstadoColor($estado)
{
    $colores = [
        'presente' => '#10B981',
        'tardanza' => '#F59E0B',
        'ausente' => '#EF4444',
        'permiso' => '#3B82F6'
    ];
    return $colores[$estado] ?? '#6B7280';
}

function obtenerEstadoTexto($estado)
{
    $textos = [
        'presente' => 'Presente',
        'tardanza' => 'Tardanza',
        'ausente' => 'Ausente',
        'permiso' => 'Permiso'
    ];
    return $textos[$estado] ?? 'Sin registro';
}

function obtenerPrimerNombre($nombre_completo)
{
    if (empty($nombre_completo)) return 'Usuario';
    return explode(' ', trim($nombre_completo))[0];
}

try {
    $stmt = $pdo->prepare("SELECT nombre_completo, foto_perfil, fecha_nacimiento FROM empleados WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $empleado_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empleado_data) {
        $empleado_name = $empleado_data['nombre_completo'] ?? 'Empleado';
        $foto_perfil_path = obtenerFotoPerfil($empleado_data['foto_perfil']);
    }
} catch (PDOException $e) {
    error_log("Error al obtener datos del empleado: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("SELECT estado FROM asistencias WHERE empleado_id = ? AND DATE(fecha) = ?");
    $stmt->execute([$usuario_id, $fecha_hoy]);
    $asistencia_hoy = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_registros,
        COUNT(CASE WHEN estado IN ('presente', 'tardanza') THEN 1 END) as dias_trabajados,
        COUNT(CASE WHEN estado = 'presente' THEN 1 END) as dias_presente,
        COUNT(CASE WHEN estado = 'tardanza' THEN 1 END) as dias_tardanza,
        COUNT(CASE WHEN estado = 'ausente' THEN 1 END) as dias_ausente,
        COUNT(CASE WHEN estado = 'permiso' THEN 1 END) as dias_permiso
    FROM asistencias 
    WHERE empleado_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
    $stmt->execute([$usuario_id, $mes_actual]);
    $estadisticas_mes = $stmt->fetch(PDO::FETCH_ASSOC);

    $porcentaje_asistencia = $estadisticas_mes['total_registros'] > 0
        ? round(($estadisticas_mes['dias_trabajados'] / $estadisticas_mes['total_registros']) * 100, 1)
        : 0;

    $stmt = $pdo->prepare("SELECT 
        (SELECT JSON_OBJECT('total', total, 'estado', estado, 'fecha_creacion', fecha_creacion) 
         FROM nominas WHERE empleado_id = ? ORDER BY fecha_creacion DESC LIMIT 1) as ultima_nomina,
        COUNT(*) as total_nominas,
        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as nominas_pendientes,
        COALESCE(SUM(total), 0) as total_mes
    FROM nominas 
    WHERE empleado_id = ? AND DATE_FORMAT(fecha_creacion, '%Y-%m') = ?");
    $stmt->execute([$usuario_id, $usuario_id, $mes_actual]);
    $nominas_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $ultima_nomina = $nominas_data['ultima_nomina'] ? json_decode($nominas_data['ultima_nomina'], true) : null;

    $stmt = $pdo->prepare("SELECT DATE(fecha) as fecha, estado, hora_entrada, hora_salida 
        FROM asistencias 
        WHERE empleado_id = ? AND fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY fecha DESC");
    $stmt->execute([$usuario_id]);
    $historial_asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $asistencia_hoy = null;
    $estadisticas_mes = ['total_registros' => 0, 'dias_trabajados' => 0, 'dias_presente' => 0, 'dias_tardanza' => 0, 'dias_ausente' => 0, 'dias_permiso' => 0];
    $porcentaje_asistencia = 0;
    $ultima_nomina = null;
    $nominas_data = ['nominas_pendientes' => 0, 'total_mes' => 0];
    $historial_asistencias = [];
    error_log("Error al obtener métricas del empleado: " . $e->getMessage());
}

$notificaciones = [];

if (!$asistencia_hoy) {
    $notificaciones[] = [
        'tipo' => 'warning',
        'mensaje' => 'No has registrado tu asistencia hoy',
        'icono' => 'bi-clock-fill',
        'enlace' => 'mis_asistencias.php'
    ];
}

if ($nominas_data['nominas_pendientes'] > 0) {
    $notificaciones[] = [
        'tipo' => 'info',
        'mensaje' => "Tienes {$nominas_data['nominas_pendientes']} nómina(s) pendiente(s) de pago",
        'icono' => 'bi-cash-stack',
        'enlace' => 'mis_nominas.php'
    ];
}

if ($empleado_data && $empleado_data['fecha_nacimiento']) {
    $cumpleanos = new DateTime($empleado_data['fecha_nacimiento']);
    $hoy = new DateTime();
    $cumpleanos->setDate($hoy->format('Y'), $cumpleanos->format('m'), $cumpleanos->format('d'));

    if ($cumpleanos < $hoy) {
        $cumpleanos->add(new DateInterval('P1Y'));
    }

    $dias_hasta_cumpleanos = $hoy->diff($cumpleanos)->days;

    if ($dias_hasta_cumpleanos <= 7) {
        $mensaje = $dias_hasta_cumpleanos == 0 ? "¡Feliz cumpleaños!" : "Tu cumpleaños es en {$dias_hasta_cumpleanos} días";
        $notificaciones[] = [
            'tipo' => 'success',
            'mensaje' => $mensaje,
            'icono' => 'bi-cake2',
            'enlace' => null
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Dashboard | Capital Human</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Capital_HumanMVC/public/css/client/employee5.css">
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
                <li class="active">
                    <a href="employee_dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
                </li>
                <li>
                    <a href="mi_perfil.php"><i class="bi bi-person-fill"></i> Mi Perfil</a>
                </li>
                <li>
                    <a href="mis_nominas.php"><i class="bi bi-cash-stack"></i> Mis Nóminas</a>
                </li>
                <li>
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
                <button id="sidebar-toggle" class="menu-toggle" style="background: none; border: none; font-size: 20px;">
                    <i class="bi bi-list"></i>
                </button>

                <div class="topbar-right" style="display: flex; align-items: center; gap: 15px;">
                    <div class="user-profile">
                        <img src="<?= htmlspecialchars($foto_perfil_path) ?>" alt="Employee Avatar" style="width: 40px; height: 40px; border-radius: 50%;">
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($empleado_name) ?></span>
                            <span class="user-role">Empleado</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="page-header">
                    <h1>¡Bienvenido/a, <?= htmlspecialchars(obtenerPrimerNombre($empleado_name)) ?>!</h1>
                    <p>Resumen de tu actividad laboral</p>
                </div>

                <?php if (!empty($notificaciones)): ?>
                    <div class="alert-card">
                        <h3><i class="bi bi-bell-fill"></i> Notificaciones</h3>
                        <ul class="alert-list">
                            <?php foreach ($notificaciones as $notif): ?>
                                <li class="alert-item <?= $notif['tipo'] ?>">
                                    <i class="<?= $notif['icono'] ?>"></i>
                                    <?php if ($notif['enlace']): ?>
                                        <a href="<?= $notif['enlace'] ?>" style="color: inherit; text-decoration: none;">
                                            <?= $notif['mensaje'] ?>
                                        </a>
                                    <?php else: ?>
                                        <?= $notif['mensaje'] ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- MÉTRICAS PRINCIPALES -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: <?= $asistencia_hoy ? obtenerEstadoColor($asistencia_hoy['estado']) : '#6B7280' ?>;">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $asistencia_hoy ? obtenerEstadoTexto($asistencia_hoy['estado']) : 'Sin registro' ?></div>
                            <div class="stat-label">Estado Hoy</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #10B981;">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $estadisticas_mes['dias_trabajados'] ?></div>
                            <div class="stat-label">Días Trabajados Este Mes</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #3B82F6;">
                            <i class="bi bi-percent"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= $porcentaje_asistencia ?>%</div>
                            <div class="stat-label">Asistencia Este Mes</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #F59E0B;">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?= formatearMoneda($nominas_data['total_mes']) ?></div>
                            <div class="stat-label">Ingresos Este Mes</div>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div class="analysis-card">
                        <div class="analysis-header">
                            <h3 class="analysis-title">Mi Asistencia - <?= date('F Y') ?></h3>
                            <i class="bi bi-calendar-check" style="color: #10B981; font-size: 24px;"></i>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px;">
                            <?php
                            $estadisticas_cards = [
                                ['dias_presente', 'Días Presente', '#10B981', '#ECFDF5', '#065F46'],
                                ['dias_tardanza', 'Tardanzas', '#F59E0B', '#FEF3C7', '#92400E'],
                                ['dias_ausente', 'Ausencias', '#EF4444', '#FEE2E2', '#991B1B'],
                                ['dias_permiso', 'Permisos', '#3B82F6', '#EBF8FF', '#1E40AF']
                            ];

                            foreach ($estadisticas_cards as $card): ?>
                                <div style="text-align: center; padding: 12px; background: <?= $card[2] ?>; border-radius: 8px; border: 1px solid <?= $card[1] ?>;">
                                    <div style="font-size: 1.3rem; font-weight: 600; color: <?= $card[1] ?>;">
                                        <?= $estadisticas_mes[$card[0]] ?>
                                    </div>
                                    <div style="color: <?= $card[4] ?>; font-size: 0.8rem;"><?= $card[1] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>Porcentaje de Asistencia</span>
                                <span><?= $porcentaje_asistencia ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $porcentaje_asistencia ?>%; background-color: <?= $porcentaje_asistencia >= 90 ? '#10B981' : ($porcentaje_asistencia >= 80 ? '#F59E0B' : '#EF4444') ?>;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- MIS NÓMINAS -->
                    <div class="analysis-card">
                        <div class="analysis-header">
                            <h3 class="analysis-title">Mis Nóminas</h3>
                            <i class="bi bi-cash-stack" style="color: #3B82F6; font-size: 24px;"></i>
                        </div>

                        <?php if ($ultima_nomina): ?>
                            <div style="padding: 15px; background: #F8FAFC; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #3B82F6;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span style="font-weight: 600; color: #1F2937;">Última Nómina</span>
                                    <span style="padding: 4px 8px; background: <?= $ultima_nomina['estado'] == 'pagada' ? '#10B981' : '#F59E0B' ?>; color: white; border-radius: 4px; font-size: 0.75rem;">
                                        <?= ucfirst($ultima_nomina['estado']) ?>
                                    </span>
                                </div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: #10B981; margin-bottom: 5px;">
                                    <?= formatearMoneda($ultima_nomina['total']) ?>
                                </div>
                                <div style="color: #6B7280; font-size: 0.9rem;">
                                    Creada: <?= formatearFecha($ultima_nomina['fecha_creacion']) ?>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div style="text-align: center; padding: 15px; background: #ECFDF5; border-radius: 8px;">
                                    <div style="font-size: 1.2rem; font-weight: 600; color: #10B981;">
                                        <?= $nominas_data['total_nominas'] - $nominas_data['nominas_pendientes'] ?>
                                    </div>
                                    <div style="color: #065F46; font-size: 0.9rem;">Pagadas</div>
                                </div>
                                <div style="text-align: center; padding: 15px; background: #FEF3C7; border-radius: 8px;">
                                    <div style="font-size: 1.2rem; font-weight: 600; color: #F59E0B;">
                                        <?= $nominas_data['nominas_pendientes'] ?>
                                    </div>
                                    <div style="color: #92400E; font-size: 0.9rem;">Pendientes</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: #6B7280;">
                                <i class="bi bi-info-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <div>No tienes nóminas registradas</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    <div class="analysis-card">
                        <div class="analysis-header">
                            <h3 class="analysis-title">Historial de Asistencias (Últimos 7 días)</h3>
                            <i class="bi bi-clock-history" style="color: #6B7280; font-size: 24px;"></i>
                        </div>

                        <?php if (!empty($historial_asistencias)): ?>
                            <div class="attendance-history">
                                <?php foreach ($historial_asistencias as $registro): ?>
                                    <div class="attendance-item">
                                        <div class="attendance-date">
                                            <div style="font-weight: 600; color: #1F2937;">
                                                <?= formatearFecha($registro['fecha']) ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: #6B7280;">
                                                <?= date('l', strtotime($registro['fecha'])) ?>
                                            </div>
                                        </div>
                                        <div class="attendance-status">
                                            <span class="status-badge" style="background-color: <?= obtenerEstadoColor($registro['estado']) ?>;">
                                                <?= obtenerEstadoTexto($registro['estado']) ?>
                                            </span>
                                        </div>
                                        <div class="attendance-times">
                                            <div style="font-size: 0.9rem;">
                                                <span style="color: #059669;">Entrada: <?= formatearHora($registro['hora_entrada']) ?></span>
                                                <?php if ($registro['hora_salida']): ?>
                                                    <span style="color: #DC2626; margin-left: 10px;">Salida: <?= formatearHora($registro['hora_salida']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px; color: #6B7280;">
                                <i class="bi bi-calendar-x" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                <div style="font-size: 1.1rem; margin-bottom: 5px;">No hay registros de asistencia</div>
                                <div style="font-size: 0.9rem;">Los registros aparecerán aquí una vez que marques tu asistencia</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ACCIONES RÁPIDAS -->
                    <div class="analysis-card">
                        <div class="analysis-header">
                            <h3 class="analysis-title">Acciones Rápidas</h3>
                            <i class="bi bi-lightning-fill" style="color: #F59E0B; font-size: 24px;"></i>
                        </div>

                        <div class="quick-actions">
                            <?php
                            $acciones = [
                                ['mis_asistencias.php', 'bi-clock-fill', 'Marcar Asistencia', 'Registrar entrada/salida'],
                                ['mis_nominas.php', 'bi-cash-stack', 'Ver Nóminas', 'Historial de pagos'],
                                ['mi_perfil.php', 'bi-person-fill', 'Mi Perfil', 'Actualizar datos'],
                                ['mis_mensajes.php', 'bi-calendar-event', 'Calendario', 'Ver eventos']
                            ];

                            foreach ($acciones as $accion): ?>
                                <a href="<?= $accion[0] ?>" class="quick-action">
                                    <i class="bi <?= $accion[1] ?>"></i>
                                    <div>
                                        <strong><?= $accion[2] ?></strong>
                                        <div style="font-size: 0.9rem; color: #6B7280;"><?= $accion[3] ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // FUNCIONES SIDEBAR
        function createSidebarOverlay() {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            overlay.id = 'sidebar-overlay';
            document.body.appendChild(overlay);
            return overlay;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.querySelector('.sidebar');
            let overlay = document.getElementById('sidebar-overlay') || createSidebarOverlay();

            if (sidebarToggle && sidebar) {
                function toggleSidebar() {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');

                    const icon = sidebarToggle.querySelector('i');
                    if (icon) {
                        if (sidebar.classList.contains('active')) {
                            icon.className = 'bi bi-x-lg';
                        } else {
                            icon.className = 'bi bi-list';
                        }
                    }
                }

                sidebarToggle.addEventListener('click', toggleSidebar);

                overlay.addEventListener('click', function() {
                    if (sidebar.classList.contains('active')) {
                        toggleSidebar();
                    }
                });

                const menuLinks = sidebar.querySelectorAll('.sidebar-menu a');
                menuLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                            toggleSidebar();
                        }
                    });
                });

                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                        const icon = sidebarToggle.querySelector('i');
                        if (icon) {
                            icon.className = 'bi bi-list';
                        }
                    }
                });
            }
        });

        document.querySelectorAll('.alert-item').forEach(function(alert) {
            if (!alert.querySelector('a')) {
                const closeBtn = document.createElement('button');
                closeBtn.innerHTML = '&times;';
                closeBtn.style.cssText = 'background: none; border: none; font-size: 1.2rem; cursor: pointer; margin-left: auto; padding: 0 5px; opacity: 0.7;';

                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    alert.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => alert.remove(), 300);
                });

                alert.style.cssText += 'display: flex; align-items: center;';
                alert.appendChild(closeBtn);
            }
        });

        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from { opacity: 1; transform: translateX(0); max-height: 50px; }
                to { opacity: 0; transform: translateX(100%); max-height: 0; margin: 0; padding: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>