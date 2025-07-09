<?php
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

$pdo->exec("CREATE TABLE IF NOT EXISTS eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    tipo ENUM('reunion', 'evento', 'importante') DEFAULT 'evento',
    empleado_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_empleado (empleado_id),
    INDEX idx_fecha (fecha_inicio)
)");

session_start();
$usuario_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 3);
$usuario_rol = isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'empleado';

// FUNCIONES FOTO PERFIL - ACTUALIZADA
function obtenerFotoPerfil($foto_perfil) {
    if ($foto_perfil && file_exists('../../models/uploads/perfiles/' . $foto_perfil)) {
        return '../../models/uploads/perfiles/' . $foto_perfil;
    }
    return '/Capital_HumanMVC/public/images/logo-banco.png';
}

try {
    if ($usuario_rol === 'admin') {
        $stmt = $pdo->prepare("SELECT nombre, foto_perfil FROM administradores WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario_actual) {
            $usuario_nombre = $usuario_actual['nombre'];
            $usuario_departamento = 'Administrador';
            $foto_perfil_path = obtenerFotoPerfil($usuario_actual['foto_perfil']);
        }
    } else {
        $stmt = $pdo->prepare("SELECT e.*, d.nombre as departamento_nombre 
                              FROM empleados e 
                              LEFT JOIN departamentos d ON e.departamento_id = d.id 
                              WHERE e.id = ?");
        $stmt->execute([$usuario_id]);
        $usuario_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario_actual) {
            $usuario_nombre = $usuario_actual['nombre_completo'];
            $usuario_departamento = $usuario_actual['departamento_nombre'] ?: 'Sin departamento';
            $foto_perfil_path = obtenerFotoPerfil($usuario_actual['foto_perfil']);
        }
    }
} catch(PDOException $e) {
    $usuario_nombre = 'Usuario';
    $usuario_departamento = 'Sin departamento';
    $foto_perfil_path = '/Capital_HumanMVC/public/images/logo-banco.png';
}

if (!isset($foto_perfil_path)) {
    $usuario_nombre = 'Usuario';
    $usuario_departamento = 'Sin departamento';
    $foto_perfil_path = '/Capital_HumanMVC/public/images/logo-banco.png';
}

$empleado_id = $usuario_id;

// FUNCIONES ACCIONES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'crear_evento') {
        try {
            $stmt = $pdo->prepare("INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, tipo, empleado_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['titulo'],
                $_POST['descripcion'],
                $_POST['fecha_inicio'],
                $_POST['fecha_fin'],
                $_POST['tipo'],
                $empleado_id
            ]);
            $mensaje = "Evento creado correctamente";
        } catch (PDOException $e) {
            $error = "Error al crear evento: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'eliminar_evento') {
        try {
            $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = ? AND empleado_id = ?");
            $stmt->execute([$_POST['evento_id'], $empleado_id]);
            $mensaje = "Evento eliminado correctamente";
        } catch (PDOException $e) {
            $error = "Error al eliminar evento: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'obtener_evento') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ? AND empleado_id = ?");
            $stmt->execute([$_POST['evento_id'], $empleado_id]);
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($evento);
            exit;
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
}

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');

if ($mes < 1 || $mes > 12) $mes = date('n');
if ($ano < 2000 || $ano > 2100) $ano = date('Y');

try {
    $stmt = $pdo->prepare("SELECT * FROM eventos 
                          WHERE MONTH(fecha_inicio) = ? AND YEAR(fecha_inicio) = ? AND empleado_id = ?
                          ORDER BY fecha_inicio");
    $stmt->execute([$mes, $ano, $empleado_id]);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $eventos = [];
    $error = "Error al obtener eventos: " . $e->getMessage();
}

$eventos_por_dia = [];
foreach ($eventos as $evento) {
    $dia = date('j', strtotime($evento['fecha_inicio']));
    $eventos_por_dia[$dia][] = $evento;
}

$primer_dia = mktime(0, 0, 0, $mes, 1, $ano);
$nombre_mes = formatearFechaEspanol($mes, $ano);
$dias_mes = date('t', $primer_dia);
$dia_semana_inicio = date('w', $primer_dia);

$mes_anterior = $mes == 1 ? 12 : $mes - 1;
$ano_anterior = $mes == 1 ? $ano - 1 : $ano;
$mes_siguiente = $mes == 12 ? 1 : $mes + 1;
$ano_siguiente = $mes == 12 ? $ano + 1 : $ano;

function formatearFechaEspanol($mes, $ano)
{
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[$mes] . ' ' . $ano;
}

function formatearFechaCompleta($fecha)
{
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return 'Fecha inválida';
    }

    $dia = date('j', $timestamp);
    $mes = date('n', $timestamp);
    $ano = date('Y', $timestamp);

    return $dia . ' de ' . $meses[$mes] . ' de ' . $ano;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario | Capital Human</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Capital_HumanMVC/public/css/admin/Calendario.css">
</head>

<body>

    <div class="admin-container">
        <!-- SIDEBAR -->
        <aside class="sidebar" id="sidebar">
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
                <li>
                    <a href="asistencias.php"><i class="bi bi-calendar-check"></i> Asistencias</a>
                </li>
                <li class="active">
                    <a href="calendario.php"><i class="bi bi-calendar-event"></i> Calendario</a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="/Capital_HumanMVC/index.php"><i class="bi bi-box-arrow-left"></i> Cerrar Sesión</a>
            </div>
        </aside>

        <main class="main-content">
            <!-- TOPBAR -->
            <div class="topbar">
                <button id="sidebar-toggle" class="menu-toggle">
                </button>

                
                <div class="topbar-right">
                    <div class="user-profile">
                        <img src="<?php echo htmlspecialchars($foto_perfil_path); ?>" 
                             alt="Usuario Avatar" 
                             onerror="this.src='../../images/admin-avatar.png'"
                             class="user-avatar">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($usuario_nombre); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($usuario_departamento); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="page-header">
                    <h1><i class="bi bi-calendar-event"></i> Calendario de Eventos</h1>
                    <p>Gestiona y visualiza todos tus eventos, reuniones y fechas importantes</p>
                </div>

                <?php if (isset($mensaje)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?php echo $mensaje; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="calendar-header-section">
                    <div class="calendar-nav">
                        <a href="?mes=<?php echo $mes_anterior; ?>&ano=<?php echo $ano_anterior; ?>" class="btn btn-primary">
                            <i class="bi bi-chevron-left"></i> Anterior
                        </a>
                        <h2><?php echo $nombre_mes; ?></h2>
                        <a href="?mes=<?php echo $mes_siguiente; ?>&ano=<?php echo $ano_siguiente; ?>" class="btn btn-primary">
                            Siguiente <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                    <button class="btn btn-success" onclick="openModal()">
                        <i class="bi bi-plus-lg"></i> Nuevo Evento
                    </button>
                </div>

                <div class="calendar">
                    <div class="calendar-grid-header">
                        <div class="day-header">Domingo</div>
                        <div class="day-header">Lunes</div>
                        <div class="day-header">Martes</div>
                        <div class="day-header">Miércoles</div>
                        <div class="day-header">Jueves</div>
                        <div class="day-header">Viernes</div>
                        <div class="day-header">Sábado</div>
                    </div>

                    <div class="calendar-body">
                        <?php
                        for ($i = 0; $i < $dia_semana_inicio; $i++) {
                            echo '<div class="day-cell empty"></div>';
                        }

                        for ($dia = 1; $dia <= $dias_mes; $dia++) {
                            date_default_timezone_set('America/Bogota');
                            $es_hoy = ($dia == date('j') && $mes == date('n') && $ano == date('Y'));
                            $clase_hoy = $es_hoy ? ' today' : '';

                            echo '<div class="day-cell' . $clase_hoy . '" onclick="seleccionarDia(' . $dia . ')" data-dia="' . $dia . '">';
                            echo '<div class="day-number">' . $dia . '</div>';

                            if (isset($eventos_por_dia[$dia])) {
                                foreach ($eventos_por_dia[$dia] as $evento) {
                                    $clase_tipo = 'evento-' . $evento['tipo'];
                                    $hora = date('H:i', strtotime($evento['fecha_inicio']));
                                    echo '<div class="evento ' . $clase_tipo . '" onclick="event.stopPropagation(); verEvento(' . $evento['id'] . ')" title="' . htmlspecialchars($evento['titulo'] . ' - ' . $hora) . '">';
                                    echo '<small>' . $hora . '</small><br>';
                                    echo htmlspecialchars(substr($evento['titulo'], 0, 15));
                                    if (strlen($evento['titulo']) > 15) echo '...';
                                    echo '</div>';
                                }
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="eventos-lista">
                    <h3><i class="bi bi-list-ul"></i> Eventos de <?php echo $nombre_mes; ?></h3>
                    <?php if (empty($eventos)): ?>
                        <div class="no-events">
                            <i class="bi bi-calendar-x"></i>
                            <p>No hay eventos programados para este mes</p>
                            <small>Haz clic en "Nuevo Evento" para agregar uno</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($eventos as $evento): ?>
                            <div class="evento-item evento-<?php echo $evento['tipo']; ?>">
                                <div class="evento-content">
                                    <div class="evento-info">
                                        <h4><?php echo htmlspecialchars($evento['titulo']); ?></h4>
                                        <p><?php echo htmlspecialchars($evento['descripcion']); ?></p>
                                        <div class="evento-meta">
                                            <span class="evento-fecha">
                                                <i class="bi bi-calendar"></i>
                                                <?php echo date('d/m/Y', strtotime($evento['fecha_inicio'])); ?>
                                            </span>
                                            <span class="evento-hora">
                                                <i class="bi bi-clock"></i>
                                                <?php echo date('H:i', strtotime($evento['fecha_inicio'])); ?>
                                                - <?php echo date('H:i', strtotime($evento['fecha_fin'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="evento-actions">
                                        <form method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este evento?')">
                                            <input type="hidden" name="action" value="eliminar_evento">
                                            <input type="hidden" name="evento_id" value="<?php echo $evento['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar evento">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="eventoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="bi bi-calendar-plus"></i> Nuevo Evento</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" id="eventoForm">
                <input type="hidden" name="action" value="crear_evento">

                <div class="form-group">
                    <label for="titulo">Título del evento *</label>
                    <input type="text" id="titulo" name="titulo" required placeholder="Ej: Reunión de equipo" maxlength="255">
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="3" placeholder="Describe los detalles del evento..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha y hora de inicio *</label>
                        <input type="datetime-local" id="fecha_inicio" name="fecha_inicio" required>
                    </div>

                    <div class="form-group">
                        <label for="fecha_fin">Fecha y hora de fin *</label>
                        <input type="datetime-local" id="fecha_fin" name="fecha_fin" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo de evento</label>
                    <select id="tipo" name="tipo">
                        <option value="evento">Evento General</option>
                        <option value="reunion">Reunión</option>
                        <option value="importante">Fecha Importante</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg"></i> Crear Evento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="eventoDetalleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="bi bi-calendar-event"></i> Detalles del Evento</h2>
                <span class="close" onclick="closeDetalleModal()">&times;</span>
            </div>
            <div id="eventoDetalleContent">
            </div>
            <div class="modal-footer">

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
        if (!sidebarToggle.innerHTML.trim()) {
        }
        
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
        let diaSeleccionado = null;

        // FUNCIONES CALENDARIO //
        function openModal(diaPreseleccionado = null) {
            document.getElementById('eventoModal').style.display = 'flex';

            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');

            if (diaPreseleccionado) {
                const mesActual = <?php echo $mes; ?>;
                const anoActual = <?php echo $ano; ?>;
                const diaFormateado = String(diaPreseleccionado).padStart(2, '0');
                const mesFormateado = String(mesActual).padStart(2, '0');

                const fechaPreseleccionada = `${anoActual}-${mesFormateado}-${diaFormateado}T09:00`;
                document.getElementById('fecha_inicio').value = fechaPreseleccionada;

                const fechaFin = new Date(fechaPreseleccionada);
                fechaFin.setHours(fechaFin.getHours() + 1);
                document.getElementById('fecha_fin').value = fechaFin.toISOString().slice(0, 16);
            } else {
                const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
                document.getElementById('fecha_inicio').value = minDateTime;

                const fechaFin = new Date(minDateTime);
                fechaFin.setHours(fechaFin.getHours() + 1);
                document.getElementById('fecha_fin').value = fechaFin.toISOString().slice(0, 16);
            }

            const minDateTime = `${year}-${month}-${day}T00:00`;
            document.getElementById('fecha_inicio').min = minDateTime;
            document.getElementById('fecha_fin').min = minDateTime;
        }

        function closeModal() {
            document.getElementById('eventoModal').style.display = 'none';
            document.getElementById('eventoForm').reset();
            document.querySelectorAll('.day-cell').forEach(cell => {
                cell.classList.remove('selected');
            });
            diaSeleccionado = null;
        }

        function closeDetalleModal() {
            document.getElementById('eventoDetalleModal').style.display = 'none';
        }

        function seleccionarDia(dia) {
            document.querySelectorAll('.day-cell').forEach(cell => {
                cell.classList.remove('selected');
            });

            const diaCell = document.querySelector(`[data-dia="${dia}"]`);
            if (diaCell) {
                diaCell.classList.add('selected');
                diaSeleccionado = dia;

                openModal(dia);
            }
        }
function verEvento(id) {
    const formData = new FormData();
    formData.append('action', 'obtener_evento');
    formData.append('evento_id', id);

    fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(evento => {
            if (evento.error) {
                alert('Error al cargar el evento: ' + evento.error);
                return;
            }

            function formatearFechaEspanol(fechaString) {
                const fecha = new Date(fechaString);
                const meses = [
                    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                ];
                const dias = [
                    'Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'
                ];
                
                const diaSemana = dias[fecha.getDay()];
                const dia = fecha.getDate();
                const mes = meses[fecha.getMonth()];
                const año = fecha.getFullYear();
                
                return `${diaSemana}, ${dia} de ${mes} de ${año}`;
            }

            function formatearHora(fechaString) {
                const fecha = new Date(fechaString);
                let horas = fecha.getHours();
                const minutos = fecha.getMinutes().toString().padStart(2, '0');
                
                const ampm = horas >= 12 ? 'PM' : 'AM';
                
                horas = horas % 12;
                horas = horas ? horas : 12; 
                
                return `${horas}:${minutos} ${ampm}`;
            }

            const fechaFormateada = formatearFechaEspanol(evento.fecha_inicio);
            const horaInicio = formatearHora(evento.fecha_inicio);
            const horaFin = formatearHora(evento.fecha_fin);

            const contenido = `
                <div class="evento-detalle-info">
                    <strong><i class="bi bi-card-heading"></i> Título:</strong> 
                    <span>${evento.titulo}</span>
                </div>
                
                <div class="evento-detalle-info">
                    <strong><i class="bi bi-file-text"></i> Descripción:</strong> 
                    <span>${evento.descripcion || 'Sin descripción'}</span>
                </div>
                
                <div class="evento-detalle-info">
                    <strong><i class="bi bi-calendar-date"></i> Fecha:</strong> 
                    <span>${fechaFormateada}</span>
                </div>
                
                <div class="evento-detalle-info">
                    <strong><i class="bi bi-clock"></i> Hora de inicio:</strong> 
                    <span>${horaInicio}</span>
                </div>
                
                <div class="evento-detalle-info">
                    <strong><i class="bi bi-clock-history"></i> Hora de fin:</strong> 
                    <span>${horaFin}</span>
                </div>
            `;

            document.getElementById('eventoDetalleContent').innerHTML = contenido;
            document.getElementById('eventoDetalleModal').style.display = 'flex';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los detalles del evento');
        });
}
        document.getElementById('fecha_inicio').addEventListener('change', function() {
            const fechaInicio = this.value;
            document.getElementById('fecha_fin').min = fechaInicio;

            if (document.getElementById('fecha_fin').value && document.getElementById('fecha_fin').value <= fechaInicio) {
                const fecha = new Date(fechaInicio);
                fecha.setHours(fecha.getHours() + 1);
                document.getElementById('fecha_fin').value = fecha.toISOString().slice(0, 16);
            }
        });

        document.getElementById('searchEvents').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const eventos = document.querySelectorAll('.evento-item');

            eventos.forEach(evento => {
                const titulo = evento.querySelector('h4').textContent.toLowerCase();
                const descripcion = evento.querySelector('p').textContent.toLowerCase();

                if (titulo.includes(searchTerm) || descripcion.includes(searchTerm)) {
                    evento.style.display = 'block';
                } else {
                    evento.style.display = 'none';
                }
            });
        });

        window.onclick = function(event) {
            const modal = document.getElementById('eventoModal');
            const detalleModal = document.getElementById('eventoDetalleModal');

            if (event.target == modal) {
                closeModal();
            }
            if (event.target == detalleModal) {
                closeDetalleModal();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const fechaInputs = document.querySelectorAll('input[type="datetime-local"]');
            fechaInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const currentYear = new Date().getFullYear();


                    if (selectedDate.getFullYear() < currentYear) {
                        alert('No puedes seleccionar fechas de años anteriores');
                        this.value = '';
                        return;
                    }
                });
            });
        });

        function resaltarDiaActual() {
            const diaActual = new Date().getDate();
            const mesActual = new Date().getMonth() + 1;
            const anoActual = new Date().getFullYear();
            
            const mesCalendario = <?php echo $mes; ?>;
            const anoCalendario = <?php echo $ano; ?>;
            
            if (mesActual === mesCalendario && anoActual === anoCalendario) {
                const celda = document.querySelector(`[data-dia="${diaActual}"]`);
                if (celda) {
                    celda.classList.add('today');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            resaltarDiaActual();
        });

        function handleResize() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
            }
        }

        window.addEventListener('resize', handleResize);
        window.addEventListener('load', handleResize);
    </script>

    <div id="loading" class="loading">
        <div class="spinner"></div>
        <p>Cargando...</p>
    </div>

</body>
</html>