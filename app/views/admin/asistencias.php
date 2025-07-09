<?php
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
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}


// FUNCIONES FOTO PERFIL //
function obtenerFotoPerfil($foto_perfil)
{
    if ($foto_perfil && file_exists('../../models/uploads/perfiles/' . $foto_perfil)) {
        return '../../models/uploads/perfiles/' . $foto_perfil;
    }
    return '/Capital_HumanMVC/public/images/admin-avatar.png';
}

$usuario_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['usuario_id'];
$usuario_rol = isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'admin';

$admin_name = 'Administrador';
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
    $admin_name = 'Administrador';
    $foto_perfil_path = '/Capital_HumanMVC/public/images/admin-avatar.png';
}
$usuario_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['usuario_id'];
$usuario_rol = isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'admin';

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
    $foto_perfil_path = '../../images/admin-avatar.png';
}

if (!isset($foto_perfil_path)) {
    $foto_perfil_path = '../../images/admin-avatar.png';
}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS asistencias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empleado_id INT NOT NULL,
        fecha DATE NOT NULL,
        hora_entrada TIME,
        hora_salida TIME,
        estado ENUM('presente', 'ausente', 'tardanza', 'permiso', 'pendiente') DEFAULT 'pendiente',
        observacion TEXT,
        notas TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        UNIQUE KEY unique_empleado_fecha (empleado_id, fecha)
    )");
} catch (PDOException $e) {
}

$mensaje = '';
$tipo_mensaje = '';

// PROCESO FORMULARIOS //
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['registrar'])) {
        try {
            if (!isset($_POST['empleado_id']) || !isset($_POST['fecha']) || !isset($_POST['estado'])) {
                throw new Exception("Faltan campos requeridos");
            }

            $empleado_id = '';
            if (!empty($_POST['empleado_id'])) {
                $empleado_id = trim($_POST['empleado_id']);
            } elseif (!empty($_POST['empleado_id_hidden'])) {
                $empleado_id = trim($_POST['empleado_id_hidden']);
            }

            $fecha = trim($_POST['fecha']);
            $estado = trim($_POST['estado']);
            $hora_entrada = isset($_POST['hora_entrada']) ? trim($_POST['hora_entrada']) : null;
            $hora_salida = isset($_POST['hora_salida']) ? trim($_POST['hora_salida']) : null;
            $notas = isset($_POST['notas']) ? trim($_POST['notas']) : '';

            if (empty($empleado_id) || empty($fecha) || empty($estado)) {
                throw new Exception("Los campos empleado, fecha y estado son obligatorios");
            }

            $check_stmt = $pdo->prepare("SELECT id FROM asistencias WHERE empleado_id = ? AND fecha = ?");
            $check_stmt->execute([$empleado_id, $fecha]);
            $existing_record = $check_stmt->fetch();

            if ($existing_record) {
                $stmt = $pdo->prepare("UPDATE asistencias SET 
                                      hora_entrada = ?, 
                                      hora_salida = ?, 
                                      estado = ?, 
                                      notas = ? 
                                      WHERE empleado_id = ? AND fecha = ?");

                $stmt->execute([
                    $hora_entrada ?: null,
                    $hora_salida ?: null,
                    $estado,
                    $notas,
                    $empleado_id,
                    $fecha
                ]);

                $mensaje = "Asistencia actualizada correctamente";
            } else {
                $stmt = $pdo->prepare("INSERT INTO asistencias (empleado_id, fecha, hora_entrada, hora_salida, estado, notas) 
                                      VALUES (?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $empleado_id,
                    $fecha,
                    $hora_entrada ?: null,
                    $hora_salida ?: null,
                    $estado,
                    $notas
                ]);

                $mensaje = "Asistencia registrada correctamente";
            }

            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $mensaje = "Error al procesar asistencia: " . $e->getMessage();
            $tipo_mensaje = "error";
        } catch (PDOException $e) {
            $mensaje = "Error de base de datos: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

try {
    $stmt = $pdo->query("SELECT id, nombre_completo FROM empleados WHERE estado = 'activo' ORDER BY nombre_completo");
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $empleados = [];
}

// ASISTENCIAS //
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id as asistencia_id,
            a.empleado_id,
            e.nombre_completo,
            a.fecha,
            a.hora_entrada,
            a.hora_salida,
            a.estado,
            COALESCE(a.notas, a.observacion, '') as notas
        FROM asistencias a
        INNER JOIN empleados e ON a.empleado_id = e.id
        WHERE a.fecha = ? AND e.estado = 'activo'
        ORDER BY e.nombre_completo
    ");
    $stmt->execute([$fecha_filtro]);
    $asistencias_registradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT id, nombre_completo FROM empleados WHERE estado = 'activo' ORDER BY nombre_completo");
    $todos_empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $asistencias = [];
    foreach ($todos_empleados as $empleado) {
        $asistencia_encontrada = false;

        foreach ($asistencias_registradas as $asistencia_reg) {
            if ($asistencia_reg['empleado_id'] == $empleado['id']) {
                $asistencias[] = $asistencia_reg;
                $asistencia_encontrada = true;
                break;
            }
        }

        if (!$asistencia_encontrada) {
            $asistencias[] = [
                'asistencia_id' => null,
                'empleado_id' => $empleado['id'],
                'nombre_completo' => $empleado['nombre_completo'],
                'fecha' => $fecha_filtro,
                'hora_entrada' => null,
                'hora_salida' => null,
                'estado' => 'pendiente',
                'notas' => ''
            ];
        }
    }
} catch (PDOException $e) {
    $asistencias = [];
    $mensaje = "Error al obtener asistencias: " . $e->getMessage();
    $tipo_mensaje = "error";
}

$total_empleados = count($asistencias);
$presentes = count(array_filter($asistencias, function ($a) {
    return $a['estado'] === 'presente';
}));
$ausentes = count(array_filter($asistencias, function ($a) {
    return $a['estado'] === 'ausente';
}));
$tardanzas = count(array_filter($asistencias, function ($a) {
    return $a['estado'] === 'tardanza';
}));
$permisos = count(array_filter($asistencias, function ($a) {
    return $a['estado'] === 'permiso';
}));
$pendientes = count(array_filter($asistencias, function ($a) {
    return $a['estado'] === 'pendiente';
}));

// FUNCIONES AUXILIARES
function formatearHora($hora)
{
    return $hora ? date('H:i', strtotime($hora)) : '-';
}

function obtenerClaseEstado($estado)
{
    switch ($estado) {
        case 'presente':
            return 'status-success';
        case 'tardanza':
            return 'status-pending';
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
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistencias | Capital Human</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Capital_HumanMVC/public/css/admin/Asistencia.css">
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
                <li>
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
                <li class="active">
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



                <div class="topbar-right">
                    <div class="user-profile">
                        <img src="<?php echo htmlspecialchars($foto_perfil_path); ?>"
                            alt="Admin Avatar"
                            onerror="this.src='../../images/admin-avatar.png'">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                            <span class="user-role">Administrador</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ASISTENCIAS -->
            <div class="dashboard-content">
                <div class="page-header">
                    <h1>Gestión de Asistencias</h1>
                    <p>Controla y registra las asistencias del personal de tu personal</p>
                </div>

                <div class="date-controls">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label class="form-label">Fecha de consulta:</label>
                            <input type="date" class="form-control" id="fechaFiltro" value="<?php echo $fecha_filtro; ?>"
                                onchange="location.href='?fecha='+this.value">
                        </div>
                        <div class="col-md-6" style="text-align: right;">
                            <button class="btn btn-primary" onclick="openModal()">
                                <i class="bi bi-plus-circle"></i> Registrar Asistencia
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?> alert-dismissible">
                        <i class="bi bi-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close-alert" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card success">
                        <i class="bi bi-check-circle" style="font-size: 2rem; color: #28a745;"></i>
                        <div class="stat-number" style="color: #28a745;"><?php echo $presentes; ?></div>
                        <div class="stat-label">Presentes</div>
                    </div>
                    <div class="stat-card danger">
                        <i class="bi bi-x-circle" style="font-size: 2rem; color: #dc3545;"></i>
                        <div class="stat-number" style="color: #dc3545;"><?php echo $ausentes; ?></div>
                        <div class="stat-label">Ausentes</div>
                    </div>
                    <div class="stat-card warning">
                        <i class="bi bi-clock" style="font-size: 2rem; color: #ffc107;"></i>
                        <div class="stat-number" style="color: #ffc107;"><?php echo $tardanzas; ?></div>
                        <div class="stat-label">Tardanzas</div>
                    </div>
                    <div class="stat-card info">
                        <i class="bi bi-calendar-event" style="font-size: 2rem; color: #17a2b8;"></i>
                        <div class="stat-number" style="color: #17a2b8;"><?php echo $permisos; ?></div>
                        <div class="stat-label">Permisos</div>
                    </div>
                    <div class="stat-card secondary">
                        <i class="bi bi-hourglass-split" style="font-size: 2rem; color: #6c757d;"></i>
                        <div class="stat-number" style="color: #6c757d;"><?php echo $pendientes; ?></div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                </div>

                <div class="table-card full-width">
                    <div class="card-header-attendance">
                        <h5>
                            <i class="bi bi-calendar-date"></i>
                            Asistencias del <?php echo date('d/m/Y', strtotime($fecha_filtro)); ?>
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Empleado</th>
                                    <th>Entrada</th>
                                    <th>Salida</th>
                                    <th>Estado</th>
                                    <th>Notas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($asistencias)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px;">
                                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                            <p style="color: #666; margin-top: 10px;">No hay empleados registrados</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($asistencias as $asistencia): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <i class="bi <?php echo obtenerIconoEstado($asistencia['estado']); ?>" style="color: <?php echo $asistencia['estado'] === 'presente' ? '#28a745' : ($asistencia['estado'] === 'tardanza' ? '#ffc107' : ($asistencia['estado'] === 'permiso' ? '#17a2b8' : ($asistencia['estado'] === 'pendiente' ? '#6c757d' : '#dc3545'))); ?>;"></i>
                                                    <strong><?php echo htmlspecialchars($asistencia['nombre_completo']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo formatearHora($asistencia['hora_entrada']); ?></td>
                                            <td><?php echo formatearHora($asistencia['hora_salida']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo obtenerClaseEstado($asistencia['estado']); ?>">
                                                    <?php echo ucfirst($asistencia['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small style="color: #666;">
                                                    <?php echo $asistencia['notas'] ? htmlspecialchars($asistencia['notas']) : '-'; ?>
                                                </small>
                                            </td>
                                            <td class="actions">
                                                <button class="btn-icon"
                                                    onclick="editarAsistencia(<?php echo $asistencia['empleado_id']; ?>, '<?php echo addslashes($asistencia['nombre_completo']); ?>', '<?php echo $asistencia['hora_entrada']; ?>', '<?php echo $asistencia['hora_salida']; ?>', '<?php echo $asistencia['estado']; ?>', '<?php echo addslashes($asistencia['notas']); ?>')"
                                                    title="Editar asistencia">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            </td>
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

    <div class="modal" id="modalRegistrar">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Asistencia</h5>
                    <button type="button" class="btn-close" onclick="closeModal()">&times;</button>
                </div>
                <form method="POST" id="formAsistencia">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Empleado *</label>
                                    <select name="empleado_id" class="form-select" required>
                                        <option value="">Seleccionar empleado...</option>
                                        <?php foreach ($empleados as $empleado): ?>
                                            <option value="<?php echo $empleado['id']; ?>">
                                                <?php echo htmlspecialchars($empleado['nombre_completo']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Fecha *</label>
                                    <input type="date" name="fecha" class="form-control" value="<?php echo $fecha_filtro; ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Hora Entrada</label>
                                    <input type="time" name="hora_entrada" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Hora Salida</label>
                                    <input type="time" name="hora_salida" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Estado *</label>
                                    <select name="estado" class="form-select" required>
                                        <option value="pendiente">Pendiente</option>
                                        <option value="presente">Presente</option>
                                        <option value="tardanza">Tardanza</option>
                                        <option value="ausente">Ausente</option>
                                        <option value="permiso">Permiso</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Notas</label>
                                    <textarea name="notas" class="form-control" rows="3" placeholder="Observaciones adicionales..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            <i class="bi bi-x"></i> Cancelar
                        </button>
                        <button type="submit" name="registrar" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
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
                if (!sidebarToggle.innerHTML.trim()) {}

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
        // FUNCIONES ESTADO
        function openModal() {
            document.getElementById('modalRegistrar').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('modalRegistrar');
            const form = document.getElementById('formAsistencia');

            modal.style.display = 'none';
            document.body.style.overflow = 'auto';

            form.reset();

            modal.querySelector('.modal-title').textContent = 'Registrar Asistencia';

            const empleadoSelect = form.querySelector('select[name="empleado_id"]');
            empleadoSelect.style.pointerEvents = 'auto';
            empleadoSelect.style.backgroundColor = '';
            empleadoSelect.style.opacity = '';

            const hiddenEmpleadoId = form.querySelector('input[name="empleado_id_hidden"]');
            if (hiddenEmpleadoId) {
                hiddenEmpleadoId.remove();
            }

            form.querySelector('input[name="fecha"]').value = '<?php echo $fecha_filtro; ?>';
        }

        function editarAsistencia(empleadoId, nombreEmpleado, horaEntrada, horaSalida, estado, notas) {
            const modal = document.getElementById('modalRegistrar');
            const form = document.getElementById('formAsistencia');

            modal.querySelector('.modal-title').textContent = 'Editar Asistencia - ' + nombreEmpleado;

            form.querySelector('select[name="empleado_id"]').value = empleadoId;
            form.querySelector('input[name="hora_entrada"]').value = horaEntrada || '';
            form.querySelector('input[name="hora_salida"]').value = horaSalida || '';
            form.querySelector('select[name="estado"]').value = estado || 'pendiente';
            form.querySelector('textarea[name="notas"]').value = notas || '';

            const empleadoSelect = form.querySelector('select[name="empleado_id"]');
            empleadoSelect.style.pointerEvents = 'none';
            empleadoSelect.style.backgroundColor = '#f8f9fa';
            empleadoSelect.style.opacity = '0.7';

            let hiddenEmpleadoId = form.querySelector('input[name="empleado_id_hidden"]');
            if (!hiddenEmpleadoId) {
                hiddenEmpleadoId = document.createElement('input');
                hiddenEmpleadoId.type = 'hidden';
                hiddenEmpleadoId.name = 'empleado_id_hidden';
                form.appendChild(hiddenEmpleadoId);
            }
            hiddenEmpleadoId.value = empleadoId;

            openModal();
        }


        document.addEventListener('DOMContentLoaded', function() {
            const estadoSelect = document.querySelector('select[name="estado"]');
            if (estadoSelect) {
                estadoSelect.addEventListener('change', function() {
                    const horaEntrada = document.querySelector('input[name="hora_entrada"]');
                    const horaSalida = document.querySelector('input[name="hora_salida"]');

                    if (this.value === 'presente' || this.value === 'tardanza') {
                        if (!horaEntrada.value) {
                            const now = new Date();
                            const timeString = now.getHours().toString().padStart(2, '0') + ':' +
                                now.getMinutes().toString().padStart(2, '0');
                            horaEntrada.value = timeString;
                        }
                    }

                    if (this.value === 'ausente' || this.value === 'permiso') {
                        horaEntrada.value = '';
                        horaSalida.value = '';
                    }
                });
            }
        });

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('modalRegistrar');
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'ArrowLeft') {
                const fechaActual = new Date(document.getElementById('fechaFiltro').value);
                fechaActual.setDate(fechaActual.getDate() - 1);
                location.href = '?fecha=' + fechaActual.toISOString().split('T')[0];
            } else if (e.ctrlKey && e.key === 'ArrowRight') {
                const fechaActual = new Date(document.getElementById('fechaFiltro').value);
                fechaActual.setDate(fechaActual.getDate() + 1);
                location.href = '?fecha=' + fechaActual.toISOString().split('T')[0];
            }
        });
    </script>

</body>

</html>