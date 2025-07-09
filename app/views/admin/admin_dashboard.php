<?php
date_default_timezone_set('America/Bogota');

session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: ../../index.php');
    exit();
}

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
    return '/Capital_HumanMVC/public/images/admin-avatar.png';
}

$usuario_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['usuario_id'];
$usuario_rol = isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'admin';

$admin_name = "Administrador Principal";
$foto_perfil_path = '/Capital_HumanMVC/public/images/admin-avatar.png';

try {
    if ($usuario_rol === 'admin') {
        $stmt = $pdo->prepare("SELECT nombre, foto_perfil FROM administradores WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario_actual = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario_actual) {
            $admin_name = $usuario_actual['nombre'];
            $foto_perfil_path = obtenerFotoPerfil($usuario_actual['foto_perfil']);
        }
    }
} catch (PDOException $e) {
    error_log("Error al obtener datos del usuario: " . $e->getMessage());
}

// MÉTRICAS DASHBOARD
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_empleados,
        COUNT(CASE WHEN estado = 'activo' OR estado IS NULL THEN 1 END) as empleados_activos,
        COUNT(CASE WHEN estado = 'inactivo' THEN 1 END) as empleados_inactivos,
        COUNT(CASE WHEN MONTH(fecha_contratacion) = MONTH(NOW()) AND YEAR(fecha_contratacion) = YEAR(NOW()) THEN 1 END) as nuevos_este_mes
    FROM empleados");
    $metricas_empleados = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_nominas,
        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as nominas_pendientes,
        COUNT(CASE WHEN estado = 'pagada' THEN 1 END) as nominas_pagadas,
        COALESCE(SUM(CASE WHEN MONTH(fecha_creacion) = MONTH(NOW()) AND YEAR(fecha_creacion) = YEAR(NOW()) THEN total ELSE 0 END), 0) as total_mes_actual,
        COALESCE(AVG(CASE WHEN total IS NOT NULL AND total > 0 THEN total END), 0) as promedio_nomina
    FROM nominas");
    $metricas_nominas = $stmt->fetch(PDO::FETCH_ASSOC);

    $fecha_hoy = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT 
        COUNT(CASE WHEN DATE(fecha) = ? AND estado = 'presente' THEN 1 END) as presentes_hoy,
        COUNT(CASE WHEN DATE(fecha) = ? AND estado = 'ausente' THEN 1 END) as ausentes_hoy,
        COUNT(CASE WHEN DATE(fecha) = ? AND estado = 'tardanza' THEN 1 END) as tardanzas_hoy,
        COUNT(CASE WHEN DATE(fecha) = ? AND estado = 'permiso' THEN 1 END) as permisos_hoy,
        (SELECT COUNT(*) FROM empleados WHERE estado = 'activo' OR estado IS NULL) as total_empleados_activos
    FROM asistencias WHERE DATE(fecha) = ?");
    $stmt->execute([$fecha_hoy, $fecha_hoy, $fecha_hoy, $fecha_hoy, $fecha_hoy]);
    $metricas_asistencias = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$metricas_asistencias['presentes_hoy']) {
        $metricas_asistencias['presentes_hoy'] = 0;
    }
    if (!$metricas_asistencias['ausentes_hoy']) {
        $metricas_asistencias['ausentes_hoy'] = 0;
    }
    if (!$metricas_asistencias['tardanzas_hoy']) {
        $metricas_asistencias['tardanzas_hoy'] = 0;
    }
    if (!$metricas_asistencias['permisos_hoy']) {
        $metricas_asistencias['permisos_hoy'] = 0;
    }

    $asistencias_efectivas = $metricas_asistencias['presentes_hoy'] + $metricas_asistencias['tardanzas_hoy'];
    $porcentaje_asistencia = $metricas_asistencias['total_empleados_activos'] > 0
        ? round(($asistencias_efectivas / $metricas_asistencias['total_empleados_activos']) * 100, 1)
        : 0;
} catch (PDOException $e) {
    $metricas_empleados = ['total_empleados' => 0, 'empleados_activos' => 0, 'empleados_inactivos' => 0, 'nuevos_este_mes' => 0];
    $metricas_nominas = ['total_nominas' => 0, 'nominas_pendientes' => 0, 'nominas_pagadas' => 0, 'total_mes_actual' => 0, 'promedio_nomina' => 0];
    $metricas_asistencias = ['presentes_hoy' => 0, 'ausentes_hoy' => 0, 'tardanzas_hoy' => 0, 'permisos_hoy' => 0, 'total_empleados_activos' => 0];
    $porcentaje_asistencia = 0;
    error_log("Error al obtener métricas: " . $e->getMessage());
}

// SISTEMA DE ALERTAS
$alertas = [];

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as nominas_vencidas 
        FROM nominas 
        WHERE estado = 'pendiente' 
        AND fecha_creacion <= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $nominas_vencidas = $stmt->fetch(PDO::FETCH_ASSOC)['nominas_vencidas'];

    if ($nominas_vencidas > 0) {
        $alertas[] = [
            'tipo' => 'warning',
            'mensaje' => "Tienes {$nominas_vencidas} nóminas pendientes de hace más de 7 días",
            'icono' => 'bi-exclamation-triangle-fill',
            'enlace' => 'nominas.php?filtro=pendiente'
        ];
    }
} catch (PDOException $e) {
    error_log("Error al verificar nóminas vencidas: " . $e->getMessage());
}

$empleados_sin_asistencia = $metricas_asistencias['total_empleados_activos'] -
    $metricas_asistencias['presentes_hoy'] -
    $metricas_asistencias['ausentes_hoy'] -
    $metricas_asistencias['tardanzas_hoy'] -
    $metricas_asistencias['permisos_hoy'];

if ($empleados_sin_asistencia > 0) {
    $alertas[] = [
        'tipo' => 'info',
        'mensaje' => "{$empleados_sin_asistencia} empleados aún no han registrado asistencia hoy",
        'icono' => 'bi-info-circle-fill',
        'enlace' => 'asistencias.php?fecha=' . $fecha_hoy
    ];
}

$dia_actual = date('d');
if ($dia_actual >= 28) {
    $alertas[] = [
        'tipo' => 'success',
        'mensaje' => 'Considera generar las nóminas del mes si aún no lo has hecho',
        'icono' => 'bi-calendar-check',
        'enlace' => 'nominas.php?accion=generar'
    ];
}

function formatearMoneda($numero)
{
    return '$' . number_format($numero, 0, ',', '.');
}

function formatearFecha($fecha)
{
    if (empty($fecha)) return 'N/A';
    return date('d/m/Y', strtotime($fecha));
}

function formatearFechaHora($fecha)
{
    if (empty($fecha)) return 'N/A';
    return date('d/m/Y H:i', strtotime($fecha));
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo | Capital Human</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Capital_HumanMVC/public/css/admin/Admin.css">
</head>

<body>
    <div class="admin-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="/Capital_HumanMVC/public/images/logo-blanco.png" alt="Capital Human Logo" class="sidebar-logo">
                <h2>Capital Human</h2>
            </div>

            <ul class="sidebar-menu">
                <li class="active">
                    <a href="admin_dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
                </li>
                <li>
                    <a href="mi_perfil.php"><i class="bi bi-person-fill"></i> Mi perfil</a>
                </li>
                <li>
                    <a href="empleados.php"><i class="bi bi-people-fill"></i> Empleados</a>
                </li>
                <li>
                    <a href="nominas.php"><i class="bi bi-cash-stack"></i> Nóminas</a>
                </li>
                <li>
                    <a href="asistencias.php"><i class="bi bi-calendar-check"></i> Asistencias</a>
                </li>
                <li>
                    <a href="calendario.php"><i class="bi bi-calendar-event"></i> Calendario</a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="/Capital_HumanMVC/index.php"><i class="bi bi-box-arrow-left"></i> Cerrar Sesión</a>
            </div>
        </aside>

        <main class="main-content">
            <!-- TOP BAR -->
            <div class="topbar">
                <button id="sidebar-toggle" class="menu-toggle" style="background: none; border: none; font-size: 20px;">
                </button>

                <div class="topbar-right" style="display: flex; align-items: center; gap: 15px;">

                    <div class="user-profile">
                        <img src="<?php echo htmlspecialchars($foto_perfil_path); ?>" alt="Admin Avatar" style="width: 40px; height: 40px; border-radius: 50%;" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNjY2MiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iI2ZmZiIvPgo8cGF0aCBkPSJNMTAgMzJjMC02IDQgLTEwIDEwIC0xMHMxMCA0IDEwIDEwIiBmaWxsPSIjZmZmIi8+Cjwvc3ZnPgo='">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                            <span class="user-role">Administrador</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DASHBOARD CONTENT -->
            <div class="dashboard-content">
                <div class="page-header">
                    <h1>Resumen Ejecutivo</h1>
                    <p>Panel de control principal del sistema</p>
                </div>

                <?php if (!empty($alertas)): ?>
                    <div class="alert-card">
                        <h3><i class="bi bi-bell-fill"></i> Notificaciones Importantes</h3>
                        <ul class="alert-list">
                            <?php foreach ($alertas as $alerta): ?>
                                <li class="alert-item <?php echo $alerta['tipo']; ?>">
                                    <i class="<?php echo $alerta['icono']; ?>"></i>
                                    <?php if (isset($alerta['enlace'])): ?>
                                        <a href="<?php echo $alerta['enlace']; ?>" style="color: inherit; text-decoration: none;">
                                            <?php echo $alerta['mensaje']; ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo $alerta['mensaje']; ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- MÉTRICAS PRINCIPALES -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $metricas_empleados['total_empleados']; ?></div>
                        <div class="stat-label">Total Empleados</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number"><?php echo $metricas_empleados['empleados_activos']; ?></div>
                        <div class="stat-label">Empleados Activos</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number"><?php echo $metricas_empleados['nuevos_este_mes']; ?></div>
                        <div class="stat-label">Nuevos Este Mes</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number"><?php echo $porcentaje_asistencia; ?>%</div>
                        <div class="stat-label">Asistencia Hoy</div>
                    </div>
                </div>

                <!-- ANÁLISIS DETALLADO -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div class="analysis-card">
                        <div class="analysis-header">
                            <h3 class="analysis-title">Estado de Nóminas</h3>
                            <i class="bi bi-cash-stack" style="color: #3B82F6; font-size: 24px;"></i>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>Nóminas Pagadas</span>
                                <span><?php echo $metricas_nominas['nominas_pagadas']; ?> de <?php echo $metricas_nominas['total_nominas']; ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 
                                <?php
                                echo $metricas_nominas['total_nominas'] > 0
                                    ? round(($metricas_nominas['nominas_pagadas'] / $metricas_nominas['total_nominas']) * 100)
                                    : 0;

                                ?>%; background-color: #10B981;"></div>
                            </div>
                        </div>

                        <?php if ($metricas_nominas['nominas_pendientes'] > 0): ?>
                            <div style="margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span style="color: #F59E0B;">Nóminas Pendientes</span>
                                    <span style="color: #F59E0B; font-weight: 600;"><?php echo $metricas_nominas['nominas_pendientes']; ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
                            <div style="text-align: center; padding: 15px; background: #F3F4F6; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 600; color: #10B981;">
                                    <?php echo formatearMoneda($metricas_nominas['total_mes_actual']); ?>
                                </div>
                                <div style="color: #6B7280; font-size: 0.9rem;">Total Este Mes</div>
                            </div>
                            <div style="text-align: center; padding: 15px; background: #F3F4F6; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 600; color: #3B82F6;">
                                    <?php echo formatearMoneda($metricas_nominas['promedio_nomina']); ?>
                                </div>
                                <div style="color: #6B7280; font-size: 0.9rem;">Promedio</div>
                            </div>
                        </div>
                    </div>

                    <!-- ANÁLISIS DE ASISTENCIAS -->
                    <div class="analysis-card">
                        <div class="analysis-header">
                            <h3 class="analysis-title">Asistencias Hoy (<?php echo date('d/m/Y'); ?>)</h3>
                            <i class="bi bi-calendar-check" style="color: #10B981; font-size: 24px;"></i>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px;">
                            <div style="text-align: center; padding: 12px; background: #ECFDF5; border-radius: 8px; border: 1px solid #10B981;">
                                <div style="font-size: 1.3rem; font-weight: 600; color: #10B981;">
                                    <?php echo $metricas_asistencias['presentes_hoy']; ?>
                                </div>
                                <div style="color: #065F46; font-size: 0.8rem;">Presentes</div>
                            </div>
                            <div style="text-align: center; padding: 12px; background: #FEF3C7; border-radius: 8px; border: 1px solid #F59E0B;">
                                <div style="font-size: 1.3rem; font-weight: 600; color: #F59E0B;">
                                    <?php echo $metricas_asistencias['tardanzas_hoy']; ?>
                                </div>
                                <div style="color: #92400E; font-size: 0.8rem;">Tardanzas</div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px;">
                            <div style="text-align: center; padding: 12px; background: #FEE2E2; border-radius: 8px; border: 1px solid #EF4444;">
                                <div style="font-size: 1.3rem; font-weight: 600; color: #EF4444;">
                                    <?php echo $metricas_asistencias['ausentes_hoy']; ?>
                                </div>
                                <div style="color: #991B1B; font-size: 0.8rem;">Ausentes</div>
                            </div>
                            <div style="text-align: center; padding: 12px; background: #EBF8FF; border-radius: 8px; border: 1px solid #3B82F6;">
                                <div style="font-size: 1.3rem; font-weight: 600; color: #3B82F6;">
                                    <?php echo $metricas_asistencias['permisos_hoy']; ?>
                                </div>
                                <div style="color: #1E40AF; font-size: 0.8rem;">Permisos</div>
                            </div>
                        </div>

                        <?php if ($empleados_sin_asistencia > 0): ?>
                            <div style="text-align: center; padding: 8px; background: #F9FAFB; border-radius: 6px; margin-bottom: 15px;">
                                <div style="font-size: 1rem; font-weight: 500; color: #6B7280;">
                                    <?php echo $empleados_sin_asistencia; ?> empleados sin registro
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>Porcentaje de Asistencia Efectiva</span>
                                <span><?php echo $porcentaje_asistencia; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $porcentaje_asistencia; ?>%; background-color: <?php echo $porcentaje_asistencia >= 80 ? '#10B981' : ($porcentaje_asistencia >= 60 ? '#F59E0B' : '#EF4444'); ?>;"></div>
                            </div>
                            <div style="font-size: 0.8rem; color: #6B7280; margin-top: 5px;">
                                <?php echo ($metricas_asistencias['presentes_hoy'] + $metricas_asistencias['tardanzas_hoy']); ?> de <?php echo $metricas_asistencias['total_empleados_activos']; ?> empleados activos
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACCIONES RÁPIDAS -->
                <div class="analysis-card">
                    <div class="analysis-header">
                        <h3 class="analysis-title">Acciones Rápidas</h3>
                        <i class="bi bi-lightning-fill" style="color: #F59E0B; font-size: 24px;"></i>
                    </div>

                    <div class="quick-actions">
                        <a href="empleados.php" class="quick-action">
                            <i class="bi bi-people-fill"></i>
                            <div>
                                <strong>Gestionar Empleados</strong>
                                <div style="font-size: 0.9rem; color: #6B7280;">Ver y administrar empleados</div>
                            </div>
                        </a>

                        <a href="nominas.php" class="quick-action">
                            <i class="bi bi-cash-coin"></i>
                            <div>
                                <strong>Gestionar Nóminas</strong>
                                <div style="font-size: 0.9rem; color: #6B7280;">Administrar pagos y nóminas</div>
                            </div>
                        </a>

                        <a href="asistencias.php" class="quick-action">
                            <i class="bi bi-calendar-day"></i>
                            <div>
                                <strong>Ver Asistencias</strong>
                                <div style="font-size: 0.9rem; color: #6B7280;">Control de asistencias</div>
                            </div>
                        </a>

                        <a href="calendario.php" class="quick-action">
                            <i class="bi bi-calendar-event"></i>
                            <div>
                                <strong>Calendario</strong>
                                <div style="font-size: 0.9rem; color: #6B7280;">Eventos y programación</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function createSidebarOverlay() {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            overlay.id = 'sidebar-overlay';
            document.body.appendChild(overlay);
            return overlay;
        }

        function actualizarFechaHora() {
            const ahora = new Date();
            const opciones = {
                timeZone: 'America/Bogota',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            };

            const fechaHoraFormateada = ahora.toLocaleString('es-CO', opciones);
            const elemento = document.getElementById('fecha-hora-actual');
            if (elemento) {
                elemento.textContent = fechaHoraFormateada;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.querySelector('.sidebar');
            let overlay = document.getElementById('sidebar-overlay') || createSidebarOverlay();

            actualizarFechaHora();
            setInterval(actualizarFechaHora, 60000);

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
    </script>
</body>

</html>