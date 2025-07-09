<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'empleado') {
    header('Location: ../../index.php');
    exit();
}

date_default_timezone_set('America/Bogota');

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $config = [
            'host' => 'localhost',
            'dbname' => 'capital_human',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4'
        ];

        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            $this->pdo->exec("SET time_zone = '-05:00'");
        } catch (PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            die("Error de conexión a la base de datos");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }
}

class EmpleadoNominaManager
{
    private $pdo;
    private $empleado_id;

    public function __construct($empleado_id)
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->empleado_id = $empleado_id;
    }

    public function marcarComoRecibido($nomina_id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT id, estado FROM nominas WHERE id = ? AND empleado_id = ?");
            $stmt->execute([$nomina_id, $this->empleado_id]);
            $nomina = $stmt->fetch();

            if (!$nomina) {
                throw new Exception("La nómina no existe o no te pertenece.");
            }

            $estado_actual = strtolower(trim($nomina['estado']));

            if ($estado_actual !== 'pendiente') {
                throw new Exception("La nómina no está en estado pendiente (Estado actual: $estado_actual).");
            }

            $stmt = $this->pdo->prepare("UPDATE nominas SET estado = 'recibido', fecha_actualizacion = NOW() WHERE id = ? AND empleado_id = ?");

            if (!$stmt->execute([$nomina_id, $this->empleado_id])) {
                throw new Exception("Error al actualizar el estado de la nómina.");
            }

            return "Nómina marcada como recibida correctamente.";
        } catch (PDOException $e) {
            error_log("Error en marcarComoRecibido: " . $e->getMessage());
            throw new Exception("Error en la base de datos: " . $e->getMessage());
        }
    }

    public function obtenerNominas($filtros = [], $page = 1, $per_page = 10)
    {
        $offset = ($page - 1) * $per_page;

        $where_conditions = ["n.empleado_id = ?"];
        $params = [$this->empleado_id];

        if (!empty($filtros['year'])) {
            $where_conditions[] = "YEAR(n.periodo_inicio) = ?";
            $params[] = $filtros['year'];
        }

        if (!empty($filtros['status'])) {
            $where_conditions[] = "LOWER(TRIM(n.estado)) = LOWER(?)";
            $params[] = $filtros['status'];
        }

        $where_clause = implode(" AND ", $where_conditions);

        $count_stmt = $this->pdo->prepare("SELECT COUNT(*) FROM nominas n WHERE $where_clause");
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $per_page);

        $stmt = $this->pdo->prepare("
            SELECT n.id, n.empleado_id, n.salario_base, n.horas_extra, n.bonificaciones, 
                   n.deducciones, n.total, n.periodo_inicio, n.periodo_fin, n.fecha_creacion,
                   n.estado
            FROM nominas n 
            WHERE $where_clause
            ORDER BY n.fecha_creacion DESC 
            LIMIT $per_page OFFSET $offset
        ");
        $stmt->execute($params);
        $nominas = $stmt->fetchAll();

        return [
            'nominas' => $nominas,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'current_page' => $page
        ];
    }

    public function obtenerTotalesAnuales($year)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_nominas,
                COALESCE(SUM(salario_base), 0) as total_salario_base,
                COALESCE(SUM(horas_extra + bonificaciones), 0) as total_extras,
                COALESCE(SUM(deducciones), 0) as total_deducciones,
                COALESCE(SUM(total), 0) as total_neto
            FROM nominas 
            WHERE empleado_id = ? AND YEAR(periodo_inicio) = ?
        ");
        $stmt->execute([$this->empleado_id, $year]);
        return $stmt->fetch();
    }

    public function obtenerAniosDisponibles()
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT YEAR(periodo_inicio) as year FROM nominas WHERE empleado_id = ? ORDER BY year DESC");
        $stmt->execute([$this->empleado_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function obtenerNominaPorId($nomina_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT n.*, e.nombre_completo, e.documento_identidad, e.cargo, d.nombre as departamento
            FROM nominas n 
            JOIN empleados e ON n.empleado_id = e.id
            LEFT JOIN departamentos d ON e.departamento_id = d.id
            WHERE n.id = ? AND n.empleado_id = ?
        ");
        $stmt->execute([$nomina_id, $this->empleado_id]);
        return $stmt->fetch();
    }
}

class EmpleadoManager
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function obtenerEmpleadoPorId($empleado_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, d.nombre as departamento 
            FROM empleados e 
            LEFT JOIN departamentos d ON e.departamento_id = d.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$empleado_id]);
        return $stmt->fetch();
    }

    public function obtenerFotoPerfil($foto_perfil)
    {
        if ($foto_perfil && file_exists('../../models/uploads/perfiles/' . $foto_perfil)) {
            return '../../models/uploads/perfiles/' . $foto_perfil;
        }
        return '/Capital_HumanMVC/public/images/logo-banco.png';
    }
}

class PDFExporter
{
    private $nomina;

    public function __construct($nomina)
    {
        $this->nomina = $nomina;
    }

    public function exportar()
    {
        if (!$this->nomina) {
            throw new Exception("No se encontró la nómina para exportar");
        }

        header('Content-Type: text/html; charset=UTF-8');
        $filename = "nomina_" . $this->nomina['id'] . "_" . date('Y-m-d') . ".html";
        header("Content-Disposition: attachment; filename=\"$filename\"");

        echo $this->generarHTML();
        exit;
    }

    private function generarHTML()
    {
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <title>Nómina - " . htmlspecialchars($this->nomina['nombre_completo']) . "</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .info { margin: 20px 0; }
                .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .table th { background-color: #f2f2f2; }
                .total { background-color: #e8f5e8; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>CAPITAL HUMAN</h1>
                <h2>Comprobante de Nómina</h2>
            </div>
            
            <div class='info'>
                <p><strong>Empleado:</strong> " . htmlspecialchars($this->nomina['nombre_completo']) . "</p>
                <p><strong>Documento:</strong> " . htmlspecialchars($this->nomina['documento_identidad']) . "</p>
                <p><strong>Cargo:</strong> " . htmlspecialchars($this->nomina['cargo']) . "</p>
                <p><strong>Departamento:</strong> " . htmlspecialchars($this->nomina['departamento']) . "</p>
                <p><strong>Período:</strong> " . date('d/m/Y', strtotime($this->nomina['periodo_inicio'])) . " - " . date('d/m/Y', strtotime($this->nomina['periodo_fin'])) . "</p>
            </div>
            
            <table class='table'>
                <tr><th>Concepto</th><th>Valor</th></tr>
                <tr><td>Salario Base</td><td>" . formatearMoneda($this->nomina['salario_base']) . "</td></tr>
                <tr><td>Horas Extra</td><td>" . formatearMoneda($this->nomina['horas_extra']) . "</td></tr>
                <tr><td>Bonificaciones</td><td>" . formatearMoneda($this->nomina['bonificaciones']) . "</td></tr>
                <tr><td><strong>Total Ingresos</strong></td><td><strong>" . formatearMoneda($this->nomina['salario_base'] + $this->nomina['horas_extra'] + $this->nomina['bonificaciones']) . "</strong></td></tr>
                <tr><td>Deducciones (Salud + Pensión)</td><td>" . formatearMoneda($this->nomina['deducciones']) . "</td></tr>
                <tr class='total'><td><strong>TOTAL NETO</strong></td><td><strong>" . formatearMoneda($this->nomina['total']) . "</strong></td></tr>
            </table>
            
            <p><small>Generado el " . date('d/m/Y H:i') . "</small></p>
        </body>
        </html>";
    }
}

function formatearMoneda($numero)
{
    return '$' . number_format($numero, 0, ',', '.');
}

function getEstadoBadge($estado)
{
    $estado_limpio = strtolower(trim($estado));

    switch ($estado_limpio) {
        case 'pendiente':
            return '<span class="badge badge-warning"><i class="bi bi-clock"></i> Pendiente</span>';
        case 'pagado':
            return '<span class="badge badge-info"><i class="bi bi-credit-card"></i> Pagado</span>';
        case 'recibido':
            return '<span class="badge badge-success"><i class="bi bi-check-circle"></i> Recibido</span>';
        default:
            return '<span class="badge badge-secondary"><i class="bi bi-question-circle"></i> ' .
                htmlspecialchars(ucfirst($estado)) . '</span>';
    }
}

$empleado_id = $_SESSION['usuario_id'];
$nominaManager = new EmpleadoNominaManager($empleado_id);
$empleadoManager = new EmpleadoManager();

$mensaje_exito = '';
$mensaje_error = '';

try {
    if (isset($_GET['marcar_recibido']) && $_GET['nomina_id']) {
        $nomina_id = (int)$_GET['nomina_id'];
        $mensaje_exito = $nominaManager->marcarComoRecibido($nomina_id);

        $redirect_url = "?year=" . ($_GET['year'] ?? date('Y')) . "&status=" . ($_GET['status'] ?? '') . "&success=1";
        header("Location: " . $redirect_url);
        exit();
    }

    if (isset($_GET['export_pdf']) && $_GET['nomina_id']) {
        $nomina_id = (int)$_GET['nomina_id'];
        $nomina = $nominaManager->obtenerNominaPorId($nomina_id);

        if ($nomina) {
            $exporter = new PDFExporter($nomina);
            $exporter->exportar();
        } else {
            throw new Exception("No se encontró la nómina para exportar");
        }
    }
} catch (Exception $e) {
    $mensaje_error = $e->getMessage();
    error_log("Error en mis_nominas.php: " . $e->getMessage());
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $mensaje_exito = "Nómina marcada como recibida correctamente.";
}

if (isset($_GET['error']) && !empty($_GET['error'])) {
    $mensaje_error = urldecode($_GET['error']);
}

$empleado = $empleadoManager->obtenerEmpleadoPorId($empleado_id);

if (!$empleado) {
    die("Empleado no encontrado");
}

$filtros = [
    'year' => $_GET['year'] ?? date('Y'),
    'status' => $_GET['status'] ?? ''
];

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$resultado_nominas = $nominaManager->obtenerNominas($filtros, $page);
$nominas = $resultado_nominas['nominas'];
$total_records = $resultado_nominas['total_records'];
$total_pages = $resultado_nominas['total_pages'];

$totals = $nominaManager->obtenerTotalesAnuales($filtros['year']);
$available_years = $nominaManager->obtenerAniosDisponibles();

$empleado_name = $empleado['nombre_completo'];
$foto_perfil_path = $empleadoManager->obtenerFotoPerfil($empleado['foto_perfil']);

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Nóminas | Capital Human</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Capital_HumanMVC/public/css/client/nomina11.css">
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
                <li class="active">
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
                <?php if ($mensaje_exito): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i>
                        <?php echo htmlspecialchars($mensaje_exito); ?>
                    </div>
                <?php endif; ?>

                <?php if ($mensaje_error): ?>
                    <div class="alert alert-error">
                        <i class="bi bi-exclamation-circle"></i>
                        <?php echo htmlspecialchars($mensaje_error); ?>
                    </div>
                <?php endif; ?>

                <div class="page-header">
                    <h1>Mis Nóminas</h1>
                    <p>Consulta y descarga tus comprobantes de nómina</p>
                </div>

                <div class="header">
                    <div class="employee-info">
                        <div class="employee-avatar">
                            <?php echo strtoupper(substr($empleado['nombre_completo'], 0, 2)); ?>
                        </div>
                        <div class="employee-details">
                            <h1><?php echo htmlspecialchars($empleado['nombre_completo']); ?></h1>
                            <p><i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($empleado['cargo'] ?? 'Sin cargo'); ?></p>
                            <p><i class="bi bi-building"></i> <?php echo htmlspecialchars($empleado['departamento'] ?? 'Sin departamento'); ?></p>
                            <p><i class="bi bi-card-text"></i> <?php echo htmlspecialchars($empleado['documento_identidad']); ?></p>
                        </div>
                        <div class="year-selector">
                            <select onchange="changeYear(this.value)">
                                <?php foreach ($available_years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $year == $filtros['year'] ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="bi bi-file-earmark-text"></i>
                        <div class="stat-value"><?php echo $totals['total_nominas'] ?? 0; ?></div>
                        <div class="stat-label">Nóminas del Año</div>
                    </div>
                    <div class="stat-card">
                        <i class="bi bi-cash-stack"></i>
                        <div class="stat-value"><?php echo formatearMoneda($totals['total_salario_base'] ?? 0); ?></div>
                        <div class="stat-label">Total Salarios Base</div>
                    </div>
                    <div class="stat-card">
                        <i class="bi bi-plus-circle"></i>
                        <div class="stat-value"><?php echo formatearMoneda($totals['total_extras'] ?? 0); ?></div>
                        <div class="stat-label">Extras y Bonificaciones</div>
                    </div>
                    <div class="stat-card">
                        <i class="bi bi-arrow-down-circle"></i>
                        <div class="stat-value"><?php echo formatearMoneda($totals['total_deducciones'] ?? 0); ?></div>
                        <div class="stat-label">Total Deducciones</div>
                    </div>
                    <div class="stat-card">
                        <i class="bi bi-wallet2"></i>
                        <div class="stat-value"><?php echo formatearMoneda($totals['total_neto'] ?? 0); ?></div>
                        <div class="stat-label">Total Neto Recibido</div>
                    </div>
                </div>

                <div class="filters">
                    <label>Filtrar por estado:</label>
                    <select onchange="changeStatus(this.value)">
                        <option value="">Todos los estados</option>
                        <option value="pendiente" <?php echo $filtros['status'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="pagado" <?php echo $filtros['status'] === 'pagado' ? 'selected' : ''; ?>>Pagado</option>
                        <option value="recibido" <?php echo $filtros['status'] === 'recibido' ? 'selected' : ''; ?>>Recibido</option>
                    </select>
                </div>

                <div class="nominas-table">
                    <div class="table-header">
                        <h2><i class="bi bi-list-ul"></i> Mis Nóminas - <?php echo $filtros['year']; ?></h2>
                        <span><?php echo $total_records; ?> registros</span>
                    </div>

                    <?php if (!empty($nominas)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Período</th>
                                        <th>Salario Base</th>
                                        <th>Extras</th>
                                        <th>Deducciones</th>
                                        <th>Total Neto</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nominas as $nomina): ?>
                                        <?php $estado_limpio = strtolower(trim($nomina['estado'])); ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('M Y', strtotime($nomina['periodo_inicio'])); ?></strong><br>
                                                <small><?php echo date('d/m', strtotime($nomina['periodo_inicio'])); ?> - <?php echo date('d/m/Y', strtotime($nomina['periodo_fin'])); ?></small>
                                            </td>
                                            <td><?php echo formatearMoneda($nomina['salario_base']); ?></td>
                                            <td><?php echo formatearMoneda($nomina['horas_extra'] + $nomina['bonificaciones']); ?></td>
                                            <td><?php echo formatearMoneda($nomina['deducciones']); ?></td>
                                            <td><strong><?php echo formatearMoneda($nomina['total']); ?></strong></td>
                                            <td>
                                                <?php echo getEstadoBadge($nomina['estado']); ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($estado_limpio === 'pendiente'): ?>
                                                    <?php endif; ?>
                                                    <a href="?export_pdf=1&nomina_id=<?php echo $nomina['id']; ?>"
                                                        class="btn btn-primary btn-sm"
                                                        title="Descargar comprobante">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&year=<?php echo $filtros['year']; ?>&status=<?php echo $filtros['status']; ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?php echo $i; ?>&year=<?php echo $filtros['year']; ?>&status=<?php echo $filtros['status']; ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&year=<?php echo $filtros['year']; ?>&status=<?php echo $filtros['status']; ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <h3>No hay nóminas registradas</h3>
                            <p>No se encontraron nóminas para los filtros seleccionados</p>
                        </div>
                    <?php endif; ?>
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

        function changeYear(year) {
            window.location.href = `?year=${year}&status=<?php echo $filtros['status']; ?>`;
        }

        function changeStatus(status) {
            window.location.href = `?year=<?php echo $filtros['year']; ?>&status=${status}`;
        }

        document.querySelectorAll('a[href*="export_pdf"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('¿Descargar comprobante de nómina?')) {
                    e.preventDefault();
                }
            });
        });

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', smoothTransition);
        });

        document.querySelectorAll('a[href*="marcar_recibido"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                const confirmModal = document.createElement('div');
                confirmModal.className = 'modal-overlay';
                confirmModal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3><i class="bi bi-check-circle"></i> Confirmar Recepción</h3>
                        </div>
                        <div class="modal-body">
                            <p>¿Confirmas que has recibido esta nómina?</p>
                            <p><small>Esta acción cambiará el estado a "Recibido" y no se puede deshacer.</small></p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">
                                Cancelar
                            </button>
                            <button class="btn btn-success" onclick="window.location.href='${this.href}'">
                                <i class="bi bi-check"></i> Confirmar
                            </button>
                        </div>
                    </div>
                `;

                document.body.appendChild(confirmModal);

                confirmModal.addEventListener('click', function(e) {
                    if (e.target === confirmModal) {
                        confirmModal.remove();
                    }
                });
            });
        });

        document.querySelectorAll('a[href*="export_pdf"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (confirm('¿Descargar comprobante de nómina?')) {
                    const originalContent = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                    this.style.pointerEvents = 'none';

                    setTimeout(() => {
                        this.innerHTML = originalContent;
                        this.style.pointerEvents = 'auto';
                    }, 3000);
                } else {
                    e.preventDefault();
                }
            });
        });

        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'slideInUp 0.6s ease forwards';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.animationDelay = `${index * 0.1}s`;
            observer.observe(card);
        });


        function addQuickSearch() {
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Buscar por período...';
            searchInput.className = 'quick-search';

            const filtersDiv = document.querySelector('.filters');
            if (filtersDiv) {
                filtersDiv.appendChild(searchInput);

                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('.table tbody tr');

                    rows.forEach(row => {
                        const period = row.querySelector('td:first-child').textContent.toLowerCase();
                        row.style.display = period.includes(searchTerm) ? '' : 'none';
                    });
                });
            }
        }


        if (document.querySelector('.table tbody tr')) {
            addQuickSearch();
        }


        if (window.location.search.includes('success=1')) {

            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }
    </script>
</body>

</html>