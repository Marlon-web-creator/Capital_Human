<?php
session_start();

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

class NominaManager
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function crearNomina($datos)
    {
        $this->validarDatos($datos);

        if ($this->existeNomina($datos['empleado_id'], $datos['periodo_inicio'], $datos['periodo_fin'])) {
            throw new Exception("Ya existe una nómina para este empleado en el período seleccionado");
        }

        $calculos = $this->calcularNomina($datos);

        $sql = "INSERT INTO nominas (empleado_id, periodo_inicio, periodo_fin, salario_base, 
                horas_extra, bonificaciones, deducciones, total, estado, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $datos['empleado_id'],
            $datos['periodo_inicio'],
            $datos['periodo_fin'],
            $datos['salario_base'],
            $datos['horas_extra'],
            $calculos['bonificaciones_total'],
            $calculos['deducciones_total'],
            $calculos['total_neto']
        ]);

        return $calculos['total_neto'];
    }

    private function validarDatos($datos)
    {
        $required = ['empleado_id', 'periodo_inicio', 'periodo_fin', 'salario_base'];
        foreach ($required as $field) {
            if (empty($datos[$field])) {
                throw new Exception("El campo {$field} es obligatorio");
            }
        }

        if ($datos['salario_base'] <= 0) {
            throw new Exception("El salario base debe ser mayor a 0");
        }

        if (strtotime($datos['periodo_inicio']) >= strtotime($datos['periodo_fin'])) {
            throw new Exception("La fecha de inicio debe ser anterior a la fecha de fin");
        }
    }

    private function existeNomina($empleado_id, $periodo_inicio, $periodo_fin)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM nominas WHERE empleado_id = ? AND periodo_inicio = ? AND periodo_fin = ?");
        $stmt->execute([$empleado_id, $periodo_inicio, $periodo_fin]);
        return $stmt->fetch() !== false;
    }

    private function calcularNomina($datos)
    {
        $salario_base = floatval($datos['salario_base']);
        $horas_extra = max(0, floatval($datos['horas_extra'] ?? 0));
        $bonificaciones = max(0, floatval($datos['bonificaciones'] ?? 0));
        $auxilio_transporte = max(0, floatval($datos['auxilio_transporte'] ?? 0));

        $deduccion_salud = $salario_base * 0.04;
        $deduccion_pension = $salario_base * 0.04;
        $deducciones_total = $deduccion_salud + $deduccion_pension;

        $bonificaciones_total = $bonificaciones + $auxilio_transporte;
        $total_ingresos = $salario_base + $horas_extra + $bonificaciones_total;
        $total_neto = $total_ingresos - $deducciones_total;

        return [
            'bonificaciones_total' => $bonificaciones_total,
            'deducciones_total' => $deducciones_total,
            'total_neto' => $total_neto
        ];
    }

    public function cambiarEstado($nomina_id, $nuevo_estado)
    {
        $estados_permitidos = ['pendiente', 'recibido', 'anulado'];
        if (!in_array($nuevo_estado, $estados_permitidos)) {
            throw new Exception("Estado no válido");
        }

        $stmt = $this->pdo->prepare("UPDATE nominas SET estado = ?, fecha_actualizacion = NOW() WHERE id = ?");
        $stmt->execute([$nuevo_estado, $nomina_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("No se pudo actualizar el estado de la nómina");
        }

        return "Estado actualizado a: " . ucfirst($nuevo_estado);
    }

    public function obtenerNominasRecientes($limit = 15)
    {
        $sql = "SELECT n.*, e.nombre_completo, e.documento_identidad, e.cargo, d.nombre as departamento
                FROM nominas n 
                LEFT JOIN empleados e ON n.empleado_id = e.id 
                LEFT JOIN departamentos d ON e.departamento_id = d.id
                ORDER BY n.fecha_creacion DESC 
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function obtenerTodasNominas()
    {
        $sql = "SELECT 
                    n.id,
                    COALESCE(e.nombre_completo, 'N/A') as nombre_completo, 
                    COALESCE(e.documento_identidad, 'N/A') as documento_identidad, 
                    COALESCE(e.cargo, 'N/A') as cargo,
                    COALESCE(d.nombre, 'Sin departamento') as departamento,
                    e.fecha_contratacion,
                    n.periodo_inicio,
                    n.periodo_fin,
                    COALESCE(n.salario_base, 0) as salario_base,
                    COALESCE(n.horas_extra, 0) as horas_extra,
                    COALESCE(n.bonificaciones, 0) as bonificaciones,
                    COALESCE(n.deducciones, 0) as deducciones,
                    COALESCE(n.total, 0) as total,
                    COALESCE(n.estado, 'pendiente') as estado,
                    n.fecha_creacion
                FROM nominas n 
                LEFT JOIN empleados e ON n.empleado_id = e.id 
                LEFT JOIN departamentos d ON e.departamento_id = d.id 
                ORDER BY n.fecha_creacion DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

class EmpleadoManager
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function obtenerEmpleadosActivos()
    {
        $sql = "SELECT e.*, d.nombre as departamento_nombre 
                FROM empleados e 
                LEFT JOIN departamentos d ON e.departamento_id = d.id 
                WHERE e.estado = 'activo' OR e.estado IS NULL
                ORDER BY e.nombre_completo";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
class UsuarioManager
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function obtenerDatosUsuario($usuario_id, $usuario_rol)
    {
        $datos = [
            'nombre' => 'Administrador Principal',
            'foto_perfil' => '/Capital_HumanMVC/public/images/admin-avatar.png'
        ];

        if ($usuario_rol === 'admin') {
            try {
                $stmt = $this->pdo->prepare("SELECT nombre, foto_perfil FROM administradores WHERE id = ?");
                $stmt->execute([$usuario_id]);
                $usuario = $stmt->fetch();

                if ($usuario) {
                    $datos['nombre'] = $usuario['nombre'];
                    $datos['foto_perfil'] = $this->obtenerRutaFotoPerfil($usuario['foto_perfil']);
                }
            } catch (PDOException $e) {
                error_log("Error al obtener datos del usuario: " . $e->getMessage());
            }
        }

        return $datos;
    }

    private function obtenerRutaFotoPerfil($foto_perfil)
    {
        $rutas_posibles = [
            '/Capital_HumanMVC/public/uploads/perfiles/' . $foto_perfil,
            '/Capital_HumanMVC/models/uploads/perfiles/' . $foto_perfil,
            '../../uploads/perfiles/' . $foto_perfil,
            '../../models/uploads/perfiles/' . $foto_perfil,
            '../uploads/perfiles/' . $foto_perfil
        ];

        if ($foto_perfil) {
            foreach ($rutas_posibles as $ruta) {
                $ruta_sistema = $_SERVER['DOCUMENT_ROOT'] . $ruta;
                if (file_exists($ruta_sistema)) {
                    return $ruta;
                }
            }

            $rutas_relativas = [
                '../../uploads/perfiles/' . $foto_perfil,
                '../../models/uploads/perfiles/' . $foto_perfil,
                '../uploads/perfiles/' . $foto_perfil
            ];

            foreach ($rutas_relativas as $ruta) {
                if (file_exists($ruta)) {
                    return $ruta;
                }
            }
        }

        return '/Capital_HumanMVC/public/images/admin-avatar.png';
    }
}
class CSVExporter
{
    private $nominas;

    public function __construct($nominas)
    {
        $this->nominas = $nominas;
    }

    public function exportar()
    {
        $filename = "nomina_" . date('Y-m-d_H-i-s') . ".csv";

        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        $headers = [
            'ID',
            'Empleado',
            'Documento',
            'Cargo',
            'Departamento',
            'Fecha Ingreso',
            'Periodo Inicio',
            'Periodo Fin',
            'Salario Base',
            'Horas Extra',
            'Bonificaciones',
            'Total Ingresos',
            'Deducciones',
            'Total Neto',
            'Estado',
            'Fecha Creacion'
        ];
        fputcsv($output, $headers, ';', '"');

        foreach ($this->nominas as $nomina) {
            $row = $this->formatearFilaNomina($nomina);
            fputcsv($output, $row, ';', '"');
        }

        if (!empty($this->nominas)) {
            $this->agregarTotales($output, count($headers));
        }

        fclose($output);
    }

    private function formatearFilaNomina($nomina)
    {
        $totalIngresos = $nomina['salario_base'] + $nomina['horas_extra'] + $nomina['bonificaciones'];

        return [
            $nomina['id'],
            $nomina['nombre_completo'],
            $nomina['documento_identidad'],
            $nomina['cargo'],
            $nomina['departamento'],
            $this->formatearFecha($nomina['fecha_contratacion']),
            $this->formatearFecha($nomina['periodo_inicio']),
            $this->formatearFecha($nomina['periodo_fin']),
            number_format($nomina['salario_base'], 0, ',', '.'),
            number_format($nomina['horas_extra'], 0, ',', '.'),
            number_format($nomina['bonificaciones'], 0, ',', '.'),
            number_format($totalIngresos, 0, ',', '.'),
            number_format($nomina['deducciones'], 0, ',', '.'),
            number_format($nomina['total'], 0, ',', '.'),
            ucfirst($nomina['estado']),
            !empty($nomina['fecha_creacion']) ? date('d/m/Y H:i', strtotime($nomina['fecha_creacion'])) : 'N/A'
        ];
    }

    private function formatearFecha($fecha)
    {
        if (empty($fecha) || $fecha === '0000-00-00') return 'N/A';
        try {
            return date('d/m/Y', strtotime($fecha));
        } catch (Exception $e) {
            return 'N/A';
        }
    }

    private function agregarTotales($output, $numColumns)
    {
        fputcsv($output, array_fill(0, $numColumns, ''), ';', '"');

        $totales = [
            '',
            'TOTALES GENERALES',
            '',
            '',
            '',
            '',
            '',
            '',
            number_format(array_sum(array_column($this->nominas, 'salario_base')), 0, ',', '.'),
            number_format(array_sum(array_column($this->nominas, 'horas_extra')), 0, ',', '.'),
            number_format(array_sum(array_column($this->nominas, 'bonificaciones')), 0, ',', '.'),
            number_format(
                array_sum(array_column($this->nominas, 'salario_base')) +
                    array_sum(array_column($this->nominas, 'horas_extra')) +
                    array_sum(array_column($this->nominas, 'bonificaciones')),
                0,
                ',',
                '.'
            ),
            number_format(array_sum(array_column($this->nominas, 'deducciones')), 0, ',', '.'),
            number_format(array_sum(array_column($this->nominas, 'total')), 0, ',', '.'),
            count($this->nominas) . ' nominas',
            'Exportado: ' . date('d/m/Y H:i')
        ];

        fputcsv($output, $totales, ';', '"');
    }
}

function formatearMoneda($numero)
{
    return '$' . number_format($numero, 0, ',', '.');
}

$nominaManager = new NominaManager();
$empleadoManager = new EmpleadoManager();
$usuarioManager = new UsuarioManager();

$usuario_id = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;
$usuario_rol = $_SESSION['usuario_rol'] ?? 'admin';

$usuario_datos = $usuarioManager->obtenerDatosUsuario($usuario_id, $usuario_rol);

$mensaje = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
        $nomina_id = (int)$_POST['nomina_id'];
        $nuevo_estado = $_POST['nuevo_estado'];
        $mensaje = $nominaManager->cambiarEstado($nomina_id, $nuevo_estado);
    }

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $nominas = $nominaManager->obtenerTodasNominas();
        $exporter = new CSVExporter($nominas);
        $exporter->exportar();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_nomina'])) {
        $datos_nomina = [
            'empleado_id' => (int)$_POST['empleado_id'],
            'periodo_inicio' => $_POST['periodo_inicio'],
            'periodo_fin' => $_POST['periodo_fin'],
            'salario_base' => max(0, floatval($_POST['salario_base'])),
            'horas_extra' => max(0, floatval($_POST['horas_extra'] ?? 0)),
            'bonificaciones' => max(0, floatval($_POST['bonificaciones'] ?? 0)),
            'auxilio_transporte' => max(0, floatval($_POST['auxilio_transporte'] ?? 0))
        ];

        $total_neto = $nominaManager->crearNomina($datos_nomina);
        $mensaje = "Nómina creada exitosamente. Total neto: " . formatearMoneda($total_neto);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

$empleados = $empleadoManager->obtenerEmpleadosActivos();
$nominas_recientes = $nominaManager->obtenerNominasRecientes();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nóminas | Capital Human</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Capital_HumanMVC/public/css/admin/Nomina.css">
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
                <li><a href="admin_dashboard.php"><i class="bi bi-grid-1x2-fill"></i> <span>Dashboard</span></a></li>
                <li><a href="mi_perfil.php"><i class="bi bi-person-fill"></i> <span>Mi perfil</span></a></li>
                <li><a href="empleados.php"><i class="bi bi-people-fill"></i> <span>Empleados</span></a></li>
                <li class="active"><a href="nominas.php"><i class="bi bi-cash-stack"></i> <span>Nóminas</span></a></li>
                <li><a href="asistencias.php"><i class="bi bi-calendar-check"></i> <span>Asistencias</span></a></li>
                <li><a href="calendario.php"><i class="bi bi-calendar-event"></i> <span>Calendario</span></a></li>
            </ul>

            <div class="sidebar-footer">
                <a href="/Capital_HumanMVC/index.php"><i class="bi bi-box-arrow-left"></i> <span>Cerrar Sesión</span></a>
            </div>
        </aside>

        <main class="main-content">
            <!-- TOP BAR -->
            <div class="topbar">
                <button id="sidebar-toggle" class="menu-toggle"></button>
                <div class="topbar-right">
                    <div class="user-profile">
                        <img src="<?php echo htmlspecialchars($usuario_datos['foto_perfil']); ?>?v=<?php echo time(); ?>"
                            alt="Admin Avatar"
                            class="user-avatar"
                            onerror="this.src='/Capital_HumanMVC/public/images/admin-avatar.png'">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($usuario_datos['nombre']); ?></span>
                            <span class="user-role"><?php echo $usuario_rol === 'admin' ? 'Administrador' : 'Usuario'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENT -->
            <div class="dashboard-content">
                <div class="page-header">
                    <h1>Gestión de Nóminas</h1>
                    <p>Sistema de cálculo y gestión de nóminas para empleados</p>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="bi bi-cash-stack"></i> Nueva Nómina</h3>
                        <div class="export-buttons">
                            <button type="button" class="btn btn-success" onclick="exportarNominas()" id="btnExportar">
                                <i class="bi bi-file-earmark-excel"></i> Exportar CSV
                            </button>
                        </div>
                    </div>

                    <div class="export-info" id="exportInfo" style="display: none;">
                        <h5><i class="bi bi-info-circle"></i> Información de Exportación</h5>
                        <ul>
                            <li>Incluye todas las nóminas registradas</li>
                            <li>Compatible con Excel y LibreOffice</li>
                            <li>Formato colombiano de moneda</li>
                            <li>Incluye totales generales</li>
                        </ul>
                    </div>

                    <form method="POST" id="nominaForm">
                        <input type="hidden" name="crear_nomina" value="1">

                        <div class="form-section">
                            <h4><i class="bi bi-person"></i> Información del Empleado</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="empleado_id">Empleado *</label>
                                    <select name="empleado_id" id="empleado_id" class="form-control" required>
                                        <option value="">Seleccionar empleado...</option>
                                        <?php foreach ($empleados as $empleado): ?>
                                            <option value="<?php echo $empleado['id']; ?>"
                                                data-salario="<?php echo $empleado['salario'] ?? 0; ?>">
                                                <?php echo htmlspecialchars($empleado['nombre_completo'] . ' - ' . ($empleado['departamento_nombre'] ?? 'Sin departamento')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="salario_base">Salario Base *</label>
                                    <input type="number" name="salario_base" id="salario_base"
                                        class="form-control" step="1000" min="0" required>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="periodo_inicio">Periodo Inicio *</label>
                                    <input type="date" name="periodo_inicio" id="periodo_inicio"
                                        class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="periodo_fin">Periodo Fin *</label>
                                    <input type="date" name="periodo_fin" id="periodo_fin"
                                        class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4><i class="bi bi-plus-circle"></i> Ingresos Adicionales</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="horas_extra">Horas Extra</label>
                                    <input type="number" name="horas_extra" id="horas_extra"
                                        class="form-control" step="1000" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="bonificaciones">Bonificaciones</label>
                                    <input type="number" name="bonificaciones" id="bonificaciones"
                                        class="form-control" step="1000" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="auxilio_transporte">Auxilio de Transporte</label>
                                    <input type="number" name="auxilio_transporte" id="auxilio_transporte"
                                        class="form-control" step="1000" min="0" value="0">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4><i class="bi bi-calculator"></i> Resumen de Cálculos</h4>
                            <div class="calculation-summary">
                                <div class="calc-row">
                                    <span>Salario Base:</span>
                                    <span id="calc-salario-base">$0</span>
                                </div>
                                <div class="calc-row">
                                    <span>Horas Extra:</span>
                                    <span id="calc-horas-extra">$0</span>
                                </div>
                                <div class="calc-row">
                                    <span>Bonificaciones:</span>
                                    <span id="calc-bonificaciones">$0</span>
                                </div>
                                <div class="calc-row">
                                    <span>Auxilio de Transporte:</span>
                                    <span id="calc-auxilio">$0</span>
                                </div>
                                <div class="calc-row">
                                    <span><strong>Total Ingresos:</strong></span>
                                    <span id="calc-total-ingresos"><strong>$0</strong></span>
                                </div>
                                <div class="calc-row">
                                    <span>Salud (4%):</span>
                                    <span id="calc-salud">$0</span>
                                </div>
                                <div class="calc-row">
                                    <span>Pensión (4%):</span>
                                    <span id="calc-pension">$0</span>
                                </div>
                                <div class="calc-row">
                                    <span><strong>Total Deducciones:</strong></span>
                                    <span id="calc-total-deducciones"><strong>$0</strong></span>
                                </div>
                                <div class="calc-row total-row">
                                    <span><strong>TOTAL NETO A PAGAR:</strong></span>
                                    <span id="calc-total-neto"><strong>$0</strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="reset" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Crear Nómina
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="bi bi-clock-history"></i> Nóminas Recientes</h3>
                        <span class="badge"><?php echo count($nominas_recientes); ?> registros</span>
                    </div>

                    <?php if (!empty($nominas_recientes)): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Empleado</th>
                                        <th>Cargo</th>
                                        <th>Departamento</th>
                                        <th>Período</th>
                                        <th>Salario Base</th>
                                        <th>Ingresos Extra</th>
                                        <th>Deducciones</th>
                                        <th>Total Neto</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nominas_recientes as $nomina): ?>
                                        <tr>
                                            <td><?php echo $nomina['id']; ?></td>
                                            <td> <strong><?php echo htmlspecialchars($nomina['nombre_completo']); ?></strong>
                                                <br><small><?php echo htmlspecialchars($nomina['documento_identidad']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($nomina['cargo']); ?></td>
                                            <td><?php echo htmlspecialchars($nomina['departamento']); ?></td>
                                            <td>
                                                <small>
                                                    <?php echo date('d/m/Y', strtotime($nomina['periodo_inicio'])); ?> -<br>
                                                    <?php echo date('d/m/Y', strtotime($nomina['periodo_fin'])); ?>
                                                </small>
                                            </td>
                                            <td><?php echo formatearMoneda($nomina['salario_base']); ?></td>
                                            <td><?php echo formatearMoneda($nomina['horas_extra'] + $nomina['bonificaciones']); ?></td>
                                            <td><?php echo formatearMoneda($nomina['deducciones']); ?></td>
                                            <td><strong><?php echo formatearMoneda($nomina['total']); ?></strong></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $nomina['estado']; ?>">
                                                    <?php echo ucfirst($nomina['estado']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($nomina['fecha_creacion'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($nomina['estado'] === 'pendiente'): ?>
                                                        <form method="POST" style="display: inline-block;">
                                                            <input type="hidden" name="cambiar_estado" value="1">
                                                            <input type="hidden" name="nomina_id" value="<?php echo $nomina['id']; ?>">
                                                            <input type="hidden" name="nuevo_estado" value="recibido">
                                                            <button type="submit" class="btn btn-success btn-sm"
                                                                onclick="return confirm('¿Marcar como recibido?')">
                                                                <i class="bi bi-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline-block;">
                                                            <input type="hidden" name="cambiar_estado" value="1">
                                                            <input type="hidden" name="nomina_id" value="<?php echo $nomina['id']; ?>">
                                                            <input type="hidden" name="nuevo_estado" value="anulado">
                                                            <button type="submit" class="btn btn-danger btn-sm"
                                                                onclick="return confirm('¿Anular esta nómina?')">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <h4>No hay nóminas registradas</h4>
                            <p>Crea la primera nómina usando el formulario anterior</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        document.getElementById('empleado_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const salario = selectedOption.getAttribute('data-salario') || 0;
            document.getElementById('salario_base').value = salario;
            calcularNomina();
        });

        function calcularNomina() {
            const salarioBase = parseFloat(document.getElementById('salario_base').value) || 0;
            const horasExtra = parseFloat(document.getElementById('horas_extra').value) || 0;
            const bonificaciones = parseFloat(document.getElementById('bonificaciones').value) || 0;
            const auxilioTransporte = parseFloat(document.getElementById('auxilio_transporte').value) || 0;

            const deduccionSalud = salarioBase * 0.04;
            const deduccionPension = salarioBase * 0.04;
            const totalDeducciones = deduccionSalud + deduccionPension;

            const totalIngresos = salarioBase + horasExtra + bonificaciones + auxilioTransporte;
            const totalNeto = totalIngresos - totalDeducciones;

            document.getElementById('calc-salario-base').textContent = formatearMoneda(salarioBase);
            document.getElementById('calc-horas-extra').textContent = formatearMoneda(horasExtra);
            document.getElementById('calc-bonificaciones').textContent = formatearMoneda(bonificaciones);
            document.getElementById('calc-auxilio').textContent = formatearMoneda(auxilioTransporte);
            document.getElementById('calc-total-ingresos').textContent = formatearMoneda(totalIngresos);
            document.getElementById('calc-salud').textContent = formatearMoneda(deduccionSalud);
            document.getElementById('calc-pension').textContent = formatearMoneda(deduccionPension);
            document.getElementById('calc-total-deducciones').textContent = formatearMoneda(totalDeducciones);
            document.getElementById('calc-total-neto').textContent = formatearMoneda(totalNeto);
        }

        function formatearMoneda(numero) {
            return '$' + new Intl.NumberFormat('es-CO').format(numero);
        }

        document.getElementById('salario_base').addEventListener('input', calcularNomina);
        document.getElementById('horas_extra').addEventListener('input', calcularNomina);
        document.getElementById('bonificaciones').addEventListener('input', calcularNomina);
        document.getElementById('auxilio_transporte').addEventListener('input', calcularNomina);

        function exportarNominas() {
            const btnExportar = document.getElementById('btnExportar');
            const exportInfo = document.getElementById('exportInfo');

            exportInfo.style.display = 'block';

            btnExportar.innerHTML = '<i class="bi bi-hourglass-split"></i> Preparando...';
            btnExportar.disabled = true;

            setTimeout(function() {
                window.location.href = '?export=csv';

                setTimeout(function() {
                    btnExportar.innerHTML = '<i class="bi bi-file-earmark-excel"></i> Exportar CSV';
                    btnExportar.disabled = false;
                    exportInfo.style.display = 'none';
                }, 3000);
            }, 1000);
        }

        document.getElementById('periodo_inicio').addEventListener('change', function() {
            const fechaInicio = new Date(this.value);
            const fechaFinInput = document.getElementById('periodo_fin');

            const minDate = new Date(fechaInicio);
            minDate.setDate(minDate.getDate() + 1);
            fechaFinInput.min = minDate.toISOString().split('T')[0];

            if (fechaFinInput.value && new Date(fechaFinInput.value) <= fechaInicio) {
                fechaFinInput.value = minDate.toISOString().split('T')[0];
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const hoy = new Date();
            const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            const ultimoDia = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);

            document.getElementById('periodo_inicio').value = primerDia.toISOString().split('T')[0];
            document.getElementById('periodo_fin').value = ultimoDia.toISOString().split('T')[0];
        });

        document.getElementById('nominaForm').addEventListener('submit', function(e) {
            const empleadoId = document.getElementById('empleado_id').value;
            const salarioBase = parseFloat(document.getElementById('salario_base').value);
            const periodoInicio = document.getElementById('periodo_inicio').value;
            const periodoFin = document.getElementById('periodo_fin').value;

            let errores = [];

            if (!empleadoId) errores.push('Debe seleccionar un empleado');
            if (!salarioBase || salarioBase <= 0) errores.push('El salario base debe ser mayor a 0');
            if (!periodoInicio) errores.push('Debe seleccionar la fecha de inicio del período');
            if (!periodoFin) errores.push('Debe seleccionar la fecha de fin del período');
            if (periodoInicio && periodoFin && new Date(periodoInicio) >= new Date(periodoFin)) {
                errores.push('La fecha de inicio debe ser anterior a la fecha de fin');
            }

            if (errores.length > 0) {
                e.preventDefault();
                alert('Errores en el formulario:\n\n' + errores.join('\n'));
                return false;
            }

            const totalNeto = document.getElementById('calc-total-neto').textContent;
            if (!confirm(`¿Crear nómina por ${totalNeto}?`)) {
                e.preventDefault();
                return false;
            }
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