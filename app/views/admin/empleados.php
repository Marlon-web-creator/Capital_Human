<?php
session_start();

if ((!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) &&
    (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']))
) {
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

$empleado_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['usuario_id'];
$empleado_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
$usuario_rol = isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'empleado';

if (empty($empleado_email)) {
    if ($usuario_rol === 'admin') {
        $stmt = $pdo->prepare("SELECT correo FROM administradores WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT correo FROM empleados WHERE id = ?");
    }
    $stmt->execute([$empleado_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $empleado_email = $result['correo'];
        $_SESSION['user_email'] = $empleado_email;
    }
}

$mensaje = '';
$tipo_mensaje = '';
$empleado = null;
$empleado_id = null;
$empleados_lista = [];

$usuario_logueado_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['usuario_id'];
$usuario_logueado_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
$usuario_rol = isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'empleado';

if (empty($usuario_logueado_email)) {
    try {
        if ($usuario_rol === 'admin') {
            $stmt = $pdo->prepare("SELECT correo FROM administradores WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("SELECT correo FROM empleados WHERE id = ?");
        }
        $stmt->execute([$usuario_logueado_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $usuario_logueado_email = $result['correo'];
            $_SESSION['user_email'] = $usuario_logueado_email; 
        }
    } catch (PDOException $e) {
        error_log("Error obteniendo email del usuario: " . $e->getMessage());
    }
}

$usuario_logueado = null;
try {
    if ($usuario_rol === 'admin') {
        $stmt = $pdo->prepare("SELECT 
            id, nombre as nombre_completo, correo, 
            NULL as fecha_nacimiento, NULL as genero, NULL as documento_identidad,
            NULL as estado_civil, NULL as nacionalidad, NULL as telefono,
            NULL as telefono_fijo, NULL as direccion, NULL as ciudad,
            NULL as codigo_postal, foto_perfil, NULL as cargo,
            NULL as fecha_contratacion, 'activo' as estado,
            NULL as departamento_nombre
            FROM administradores WHERE id = ?");
        $stmt->execute([$usuario_logueado_id]);
    } else {
        $stmt = $pdo->prepare("SELECT e.*, d.nombre as departamento_nombre 
                              FROM empleados e 
                              LEFT JOIN departamentos d ON e.departamento_id = d.id 
                              WHERE e.id = ?");
        $stmt->execute([$usuario_logueado_id]);
    }
    
    $usuario_logueado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario_logueado) {
        session_destroy();
        header('Location: ../../index.php?error=invalid_session');
        exit();
    }
} catch (PDOException $e) {
    $mensaje = "Error al cargar datos del usuario: " . $e->getMessage();
    $tipo_mensaje = "error";
    error_log("Error cargando datos del usuario: " . $e->getMessage());
}

if (isset($_GET['empleado_id']) && !empty($_GET['empleado_id'])) {
    $empleado_id = (int)$_GET['empleado_id'];
} elseif (isset($_GET['empleado_id_select']) && !empty($_GET['empleado_id_select'])) {
    $empleado_id = (int)$_GET['empleado_id_select'];
} elseif (isset($_GET['empleado_id_manual']) && !empty($_GET['empleado_id_manual'])) {
    $empleado_id = (int)$_GET['empleado_id_manual'];
} elseif (isset($_POST['empleado_id']) && !empty($_POST['empleado_id'])) {
    $empleado_id = (int)$_POST['empleado_id'];
}
try {
    $stmt = $pdo->prepare("SELECT id, nombre_completo, correo, estado FROM empleados ORDER BY nombre_completo");
    $stmt->execute();
    $empleados_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar la lista de empleados: " . $e->getMessage();
    $tipo_mensaje = "error";
    error_log("Error cargando lista de empleados: " . $e->getMessage());
}

// DATOS DEL USUARIO //
if ($empleado_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, d.nombre as departamento_nombre 
            FROM empleados e 
            LEFT JOIN departamentos d ON e.departamento_id = d.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$empleado_id]);
        $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$empleado) {
            $mensaje = "No se encontró el empleado con ID: $empleado_id.";
            $tipo_mensaje = "error";
            $empleado_id = null; 
        }
    } catch (PDOException $e) {
        $mensaje = "Error al cargar los datos del empleado: " . $e->getMessage();
        $tipo_mensaje = "error";
        $empleado_id = null;
        error_log("Error cargando datos del empleado: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil']) && $empleado_id) {
    try {
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        
        if (empty($nombre_completo)) {
            throw new Exception("El nombre completo es obligatorio.");
        }
        
        if (empty($correo)) {
            throw new Exception("El correo electrónico es obligatorio.");
        }
        
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del correo electrónico no es válido.");
        }
        
        $stmt_check = $pdo->prepare("SELECT id FROM empleados WHERE correo = ? AND id != ?");
        $stmt_check->execute([$correo, $empleado_id]);
        if ($stmt_check->fetch()) {
            throw new Exception("El correo electrónico ya está en uso por otro empleado.");
        }
        
        $stmt = $pdo->prepare("
            UPDATE empleados SET 
                nombre_completo = ?,
                fecha_nacimiento = ?,
                telefono = ?,
                direccion = ?,
                correo = ?,
                ultima_actualizacion = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nombre_completo,
            !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null,
            !empty($_POST['telefono']) ? trim($_POST['telefono']) : null,
            !empty($_POST['direccion']) ? trim($_POST['direccion']) : null,
            $correo,
            $empleado_id
        ]);
        
        $mensaje = "Perfil actualizado correctamente.";
        $tipo_mensaje = "success";
        
        $stmt = $pdo->prepare("
            SELECT e.*, d.nombre as departamento_nombre 
            FROM empleados e 
            LEFT JOIN departamentos d ON e.departamento_id = d.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$empleado_id]);
        $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "error";
    } catch (PDOException $e) {
        $mensaje = "Error al actualizar el perfil. Por favor, inténtalo de nuevo.";
        $tipo_mensaje = "error";
        error_log("Error actualizando perfil: " . $e->getMessage());
    }
}

// SECCION FOTO DE PERFIL 
function obtenerFotoPerfil($foto_perfil) {
    if ($foto_perfil && file_exists('../../models/uploads/perfiles/' . $foto_perfil)) {
        return '../../models/uploads/perfiles/' . $foto_perfil;
    }
    return '/Capital_HumanMVC/public/images/logo-banco.png';
}

function tieneFotoPerfil($foto_perfil) {
    return !empty($foto_perfil) && file_exists('../../models/uploads/perfiles/' . $foto_perfil);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados | Capital Human</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Capital_HumanMVC/public/css/admin/Empleado.css">
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
            <li class="active">
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
                <i class="bi bi-list"></i>
            </button>
            
            <div class="topbar-right" style="display: flex; align-items: center; gap: 15px;">
                <div class="user-profile">
                    <?php if ($usuario_logueado): ?>
                        <img src="<?php echo obtenerFotoPerfil($usuario_logueado['foto_perfil']); ?>?v=<?php echo time(); ?>" 
                             alt="Usuario Avatar" 
                             style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($usuario_logueado['nombre_completo']); ?></span>
                            <span class="user-role"><?php echo $usuario_rol === 'admin' ? 'Administrador' : 'Empleado'; ?></span>
                        </div>
                    <?php else: ?>
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #666;">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="user-info">
                            <span class="user-name">Usuario</span>
                            <span class="user-role">Sistema</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'error'; ?>" id="alertMessage">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <?php if (!$empleado_id || !$empleado): ?>
                <div class="employee-selector">
                    <h2>Seleccionar Empleado</h2>
                    <p>Elige un empleado para ver y editar su perfil</p>
                    
                    <?php if (empty($empleados_lista)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            No hay empleados registrados en el sistema.
                        </div>
                    <?php else: ?>
                        <form method="GET" class="selector-form">
    <div class="form-group-inline">
        <label for="empleado_select">Seleccionar de la lista:</label>
        <select name="empleado_id_select" id="empleado_select" class="form-control">
            <option value="">-- Selecciona un empleado --</option>
            <?php foreach ($empleados_lista as $emp): ?>
                <option value="<?php echo $emp['id']; ?>">
                    <?php echo htmlspecialchars($emp['nombre_completo'] . ' (' . $emp['correo'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div style="text-align: center; margin: 20px 0;">
        <strong>O</strong>
    </div>
    
    <div class="form-group-inline">
        <label for="empleado_id_manual">Ingresar ID manualmente:</label>
        <input type="number" name="empleado_id_manual" id="empleado_id_manual" 
               placeholder="ID del empleado" min="1" class="form-control">
    </div>
    
    <button type="submit" class="btn-select">
        <i class="bi bi-person-check"></i> Ver Perfil
    </button>
</form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="current-user-info">
                    <div class="user-details">
                        <i class="bi bi-person-circle" style="font-size: 24px;"></i>
                        <div>
                            <strong>Viendo perfil de:</strong> <?php echo htmlspecialchars($empleado['nombre_completo']); ?>
                            <br><small>ID: <?php echo $empleado_id; ?> | <?php echo htmlspecialchars($empleado['correo']); ?></small>
                        </div>
                    </div>
                    <a href="empleados.php" class="btn-change-user">
                        <i class="bi bi-arrow-left"></i> Cambiar Usuario
                    </a>
                </div>

                <div class="profile-container">
                    <div class="profile-header">
                        <div class="profile-header-left">
                            <div class="profile-avatar">
                                <?php if (tieneFotoPerfil($empleado['foto_perfil'])): ?>
                                    <img src="<?php echo obtenerFotoPerfil($empleado['foto_perfil']); ?>" class="avatar-large">
                                <?php else: ?>
                                    <div class="avatar-large avatar-placeholder">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="profile-details">
                                <h2><?php echo htmlspecialchars($empleado['nombre_completo'] ?? 'Sin nombre'); ?></h2>
                                <p class="profile-role"><?php echo htmlspecialchars($empleado['departamento_nombre'] ?? 'Sin departamento asignado'); ?></p>
                                <div class="profile-contact">
                                    <span><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($empleado['correo'] ?? 'Sin correo'); ?></span>
                                    <span><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($empleado['telefono'] ?? 'No especificado'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="profile-header-right">
                            <div class="employee-status">
                                <span class="status-badge status-<?php echo ($empleado['estado'] ?? 'inactivo') === 'activo' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($empleado['estado'] ?? 'Inactivo'); ?>
                                </span>
                                <?php if (!empty($empleado['fecha_contratacion'])): ?>
                                    <p>Empleado desde: <?php echo date('d/m/Y', strtotime($empleado['fecha_contratacion'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <button class="btn-outline" id="editProfileBtn">
                                <i class="bi bi-pencil"></i> Editar Perfil
                            </button>
                        </div>
                    </div>

                    <div class="profile-tab-content">
                        <div class="tab-panel active" id="info-personal">
                            <div class="info-view" id="infoView">
                                <div class="info-section">
                                    <h3>Datos Personales</h3>
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <label>Nombre Completo</label>
                                            <p><?php echo htmlspecialchars($empleado['nombre_completo'] ?? 'No especificado'); ?></p>
                                        </div>
                                        <div class="info-item">
                                            <label>Fecha de Nacimiento</label>
                                            <p><?php echo !empty($empleado['fecha_nacimiento']) ? date('d/m/Y', strtotime($empleado['fecha_nacimiento'])) : 'No especificada'; ?></p>
                                        </div>
                                        <div class="info-item">
                                            <label>Correo Electrónico</label>
                                            <p><?php echo htmlspecialchars($empleado['correo'] ?? 'No especificado'); ?></p>
                                        </div>
                                        <div class="info-item">
                                            <label>Teléfono</label>
                                            <p><?php echo htmlspecialchars($empleado['telefono'] ?? 'No especificado'); ?></p>
                                        </div>
                                        <div class="info-item">
                                            <label>Dirección</label>
                                            <p><?php echo htmlspecialchars($empleado['direccion'] ?? 'No especificada'); ?></p>
                                        </div>
                                        <div class="info-item">
                                            <label>Estado</label>
                                            <p><?php echo ucfirst($empleado['estado'] ?? 'Inactivo'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="edit-form" id="editForm" style="display: none;">
                                <form method="POST" id="profileForm">
                                    <input type="hidden" name="actualizar_perfil" value="1">
                                    <input type="hidden" name="empleado_id" value="<?php echo $empleado_id; ?>">
                                    
                                    <div class="info-section">
                                        <h3>Editar Datos Personales</h3>
                                        <div class="form-row">
                                            <div class="form-group required">
                                                <label for="nombre_completo">Nombre Completo</label>
                                                <input type="text" 
                                                       id="nombre_completo" 
                                                       name="nombre_completo" 
                                                       value="<?php echo htmlspecialchars($empleado['nombre_completo'] ?? ''); ?>" 
                                                       required>
                                            </div>
                                            <div class="form-group">
                                                <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                                                <input type="date" 
                                                       id="fecha_nacimiento" 
                                                       name="fecha_nacimiento" 
                                                       value="<?php echo $empleado['fecha_nacimiento'] ?? ''; ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group required">
                                                <label for="correo">Correo Electrónico</label>
                                                <input type="email" 
                                                       id="correo" 
                                                       name="correo" 
                                                       value="<?php echo htmlspecialchars($empleado['correo'] ?? ''); ?>" 
                                                       required>
                                            </div>
                                            <div class="form-group">
                                                <label for="telefono">Teléfono</label>
                                                <input type="tel" 
                                                       id="telefono" 
                                                       name="telefono" 
                                                       value="<?php echo htmlspecialchars($empleado['telefono'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="direccion">Dirección</label>
                                            <textarea id="direccion" 
                                                      name="direccion" 
                                                      rows="3" 
                                                      placeholder="Ingrese su dirección completa"><?php echo htmlspecialchars($empleado['direccion'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="btn-group">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg"></i> Guardar Cambios
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="cancelEditBtn">
                                            <i class="bi bi-x-lg"></i> Cancelar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    const editBtn = document.getElementById('editProfileBtn');
    const cancelBtn = document.getElementById('cancelEditBtn');
    const infoView = document.getElementById('infoView');
    const editForm = document.getElementById('editForm');
    const profileForm = document.getElementById('profileForm');

    if (editBtn) {
        editBtn.addEventListener('click', function() {
            if (infoView && editForm) {
                infoView.classList.add('hidden');
                editForm.classList.add('active');
                editForm.style.display = 'block';
                editBtn.style.display = 'none';
            }
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            if (infoView && editForm) {
                editForm.classList.remove('active');
                editForm.style.display = 'none';
                infoView.classList.remove('hidden');
                if (editBtn) {
                    editBtn.style.display = 'inline-flex';
                }
                
                if (profileForm) {
                    profileForm.reset();
                    const inputs = profileForm.querySelectorAll('input, textarea');
                    inputs.forEach(input => {
                        input.classList.remove('is-invalid');
                        const errorMsg = input.parentNode.querySelector('.error-message');
                        if (errorMsg) {
                            errorMsg.remove();
                        }
                    });
                }
            }
        });
    }

    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            const inputs = profileForm.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.classList.remove('is-invalid');
                const errorMsg = input.parentNode.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
            
            const nombreCompleto = document.getElementById('nombre_completo');
            if (!nombreCompleto.value.trim()) {
                showFieldError(nombreCompleto, 'El nombre completo es obligatorio');
                isValid = false;
            }
            
            const correo = document.getElementById('correo');
            if (!correo.value.trim()) {
                showFieldError(correo, 'El correo electrónico es obligatorio');
                isValid = false;
            } else if (!isValidEmail(correo.value)) {
                showFieldError(correo, 'El formato del correo electrónico no es válido');
                isValid = false;
            }
            
            const telefono = document.getElementById('telefono');
            if (telefono.value.trim() && !isValidPhone(telefono.value)) {
                showFieldError(telefono, 'El formato del teléfono no es válido');
                isValid =false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
        });
    }

    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        
        const existingError = field.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        errorDiv.style.color = '#dc3545';
        errorDiv.style.fontSize = '0.875rem';
        errorDiv.style.marginTop = '0.25rem';
        
        field.parentNode.appendChild(errorDiv);
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function isValidPhone(phone) {
        const phoneRegex = /^[\d\s\-\(\)\+]+$/;
        return phoneRegex.test(phone) && phone.replace(/\D/g, '').length >= 7;
    }

const empleadoSelect = document.getElementById('empleado_select');
const empleadoIdManual = document.getElementById('empleado_id_manual');
const selectorForm = document.querySelector('.selector-form');

if (empleadoSelect && empleadoIdManual && selectorForm) {
    empleadoSelect.addEventListener('change', function() {
        if (this.value) {
            empleadoIdManual.value = '';
        }
    });
    
    empleadoIdManual.addEventListener('input', function() {
        if (this.value) {
            empleadoSelect.value = '';
        }
    });

    selectorForm.addEventListener('submit', function(e) {
        const selectValue = empleadoSelect.value;
        const manualValue = empleadoIdManual.value;
        
        if (!selectValue && !manualValue) {
            e.preventDefault();
            alert('Por favor selecciona un empleado de la lista o ingresa un ID manualmente.');
            return false;
        }
        
        if (selectValue) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'empleado_id';
            hiddenInput.value = selectValue;
            this.appendChild(hiddenInput);
            
            empleadoIdManual.value = '';
            empleadoIdManual.name = '';
        }
    });
}
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    }

    const alertMessage = document.getElementById('alertMessage');
    if (alertMessage) {
        setTimeout(function() {
            alertMessage.style.opacity = '0';
            setTimeout(function() {
                alertMessage.style.display = 'none';
            }, 300);
        }, 5000);
    }

    let formSubmitting = false;
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            if (formSubmitting) {
                e.preventDefault();
                return false;
            }
            formSubmitting = true;
            
            setTimeout(function() {
                formSubmitting = false;
            }, 3000);
        });
    }
});
</script>

</body>
</html>