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

session_start();
$usuario_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1);

function obtenerFotoPerfil($foto_perfil)
{
    if ($foto_perfil && file_exists('../../models/uploads/perfiles/' . $foto_perfil)) {
        return '../../models/uploads/perfiles/' . $foto_perfil;
    }
    return '/Capital_HumanMVC/public/images/logo-banco.png';
}

try {
    $stmt = $pdo->prepare("SELECT e.*, d.nombre as departamento_nombre 
                          FROM empleados e 
                          LEFT JOIN departamentos d ON e.departamento_id = d.id 
                          WHERE e.id = ?");
    $stmt->execute([$usuario_id]);
    $usuario_actual = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario_actual) {
        $usuario_nombre = $usuario_actual['nombre_completo'];
        $usuario_departamento = $usuario_actual['departamento_nombre'] ?: 'Empleado';
        $foto_perfil_path = obtenerFotoPerfil($usuario_actual['foto_perfil']);
    }
} catch (PDOException $e) {
    $usuario_nombre = 'Empleado';
    $usuario_departamento = 'Empleado';
    $foto_perfil_path = '/Capital_HumanMVC/public/images/logo-banco.png';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'obtener_evento') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id = ?");
        $stmt->execute([$_POST['evento_id']]);
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

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');

if ($mes < 1 || $mes > 12) $mes = date('n');
if ($ano < 2000 || $ano > 2100) $ano = date('Y');

try {
    $stmt = $pdo->prepare("SELECT e.*, emp.nombre_completo as creador 
                          FROM eventos e
                          LEFT JOIN empleados emp ON e.empleado_id = emp.id
                          WHERE MONTH(e.fecha_inicio) = ? AND YEAR(e.fecha_inicio) = ?
                          ORDER BY e.fecha_inicio");
    $stmt->execute([$mes, $ano]);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $eventos = [];
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
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre'
    ];
    return $meses[$mes] . ' ' . $ano;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario | Capital Human</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Capital_HumanMVC/public/css/client/mensajes6.css">
</head>

<body>
    <div class="empleado-container">
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
                <li>
                    <a href="mis_asistencias.php"><i class="bi bi-calendar-check"></i> Mis Asistencias</a>
                </li>
                <li class="active">
                    <a href="mis_mensajes.php"><i class="bi bi-calendar-event"></i> Mis Mensajes</a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="/Capital_HumanMVC/index.php?action=logout"><i class="bi bi-box-arrow-left"></i> Cerrar Sesión</a>
            </div>
        </aside>

        <main class="main-content">
            <!-- TOPBAR -->
            <div class="topbar">
                <button id="sidebar-toggle" class="menu-toggle">
                    <i class="bi bi-list"></i>
                </button>

                <div class="topbar-right">
                    <div class="user-profile">
                        <img src="<?php echo htmlspecialchars($foto_perfil_path); ?>"
                            alt="Usuario Avatar"
                            onerror="this.src='../../images/empleado-avatar.png'"
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
                    <p>Visualiza eventos, reuniones y fechas importantes de la empresa</p>
                </div>

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

                            echo '<div class="day-cell' . $clase_hoy . '">';
                            echo '<div class="day-number">' . $dia . '</div>';

                            if (isset($eventos_por_dia[$dia])) {
                                foreach ($eventos_por_dia[$dia] as $evento) {
                                    $clase_tipo = 'evento-' . $evento['tipo'];
                                    $hora = date('H:i', strtotime($evento['fecha_inicio']));
                                    echo '<div class="evento ' . $clase_tipo . '" onclick="verEvento(' . $evento['id'] . ')" title="' . htmlspecialchars($evento['titulo'] . ' - ' . $hora) . '">';
                                    echo '<small>' . $hora . '</small><br>';
                                    echo htmlspecialchars(substr($evento['titulo'], 0, 12));
                                    if (strlen($evento['titulo']) > 12) echo '...';
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
                        </div>
                    <?php else: ?>
                        <?php foreach ($eventos as $evento): ?>
                            <div class="evento-item evento-<?php echo $evento['tipo']; ?>" onclick="verEvento(<?php echo $evento['id']; ?>)">
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
                                            <?php if ($evento['creador']): ?>
                                                <span class="evento-creador">
                                                    <i class="bi bi-person"></i>
                                                    <?php echo htmlspecialchars($evento['creador']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="eventoDetalleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="bi bi-calendar-event"></i> Detalles del Evento</h2>
                <span class="close" onclick="closeDetalleModal()">&times;</span>
            </div>
            <div id="eventoDetalleContent"></div>
        </div>
    </div>

    <script>
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

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
                        <strong><i class="bi bi-clock"></i> Horario:</strong> 
                        <span>${horaInicio} - ${horaFin}</span>
                    </div>
                    
                    <div class="evento-detalle-info">
                        <strong><i class="bi bi-tag"></i> Tipo:</strong> 
                        <span class="tipo-${evento.tipo}">${evento.tipo.charAt(0).toUpperCase() + evento.tipo.slice(1)}</span>
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

        function closeDetalleModal() {
            document.getElementById('eventoDetalleModal').style.display = 'none';
        }

        function formatearFechaEspanol(fechaString) {
            const fecha = new Date(fechaString);
            const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
            ];
            const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

            return `${dias[fecha.getDay()]}, ${fecha.getDate()} de ${meses[fecha.getMonth()]} de ${fecha.getFullYear()}`;
        }

        function formatearHora(fechaString) {
            const fecha = new Date(fechaString);
            let horas = fecha.getHours();
            const minutos = fecha.getMinutes().toString().padStart(2, '0');
            const ampm = horas >= 12 ? 'PM' : 'AM';
            horas = horas % 12 || 12;
            return `${horas}:${minutos} ${ampm}`;
        }

        window.onclick = function(event) {
            const modal = document.getElementById('eventoDetalleModal');
            if (event.target == modal) {
                closeDetalleModal();
            }
        }
    </script>
</body>

</html>