<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
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

$empleado_id = $_SESSION['user_id'];
$empleado_email = $_SESSION['user_email'];

function obtenerFotoPerfil($foto_perfil)
{
    if ($foto_perfil && file_exists('../../models/uploads/perfiles/' . $foto_perfil)) {
        return '../../models/uploads/perfiles/' . $foto_perfil;
    }
    return '/Capital_HumanMVC/public/images/logo-banco.png';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'actualizar_perfil') {
        try {
            $stmt = $pdo->prepare("UPDATE empleados SET 
                nombre_completo = ?, 
                fecha_nacimiento = ?, 
                genero = ?, 
                documento_identidad = ?, 
                estado_civil = ?, 
                nacionalidad = ?, 
                correo = ?, 
                telefono = ?, 
                telefono_fijo = ?, 
                direccion = ?, 
                ciudad = ?, 
                codigo_postal = ?
                WHERE id = ?");

            $stmt->execute([
                $_POST['nombre_completo'],
                $_POST['fecha_nacimiento'],
                $_POST['genero'],
                $_POST['documento_identidad'],
                $_POST['estado_civil'],
                $_POST['nacionalidad'],
                $_POST['correo'],
                $_POST['telefono'],
                $_POST['telefono_fijo'],
                $_POST['direccion'],
                $_POST['ciudad'],
                $_POST['codigo_postal'],
                $empleado_id
            ]);

            $_SESSION['user_email'] = $_POST['correo'];
            $mensaje_exito = "Perfil actualizado correctamente";
        } catch (PDOException $e) {
            $mensaje_error = "Error al actualizar el perfil: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'subir_foto' && isset($_FILES['foto_perfil'])) {
        $upload_dir = '../../models/uploads/perfiles/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file = $_FILES['foto_perfil'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; 

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $mensaje_error = "Error al subir el archivo: " . $file['error'];
        } elseif (!in_array($file['type'], $allowed_types)) {
            $mensaje_error = "Formato de imagen no válido. Solo se permiten JPG, PNG y GIF";
        } elseif ($file['size'] > $max_size) {
            $mensaje_error = "El archivo es muy grande. Tamaño máximo: 5MB";
        } else {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'perfil_' . $empleado_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                try {
                    $stmt = $pdo->prepare("SELECT foto_perfil FROM empleados WHERE id = ?");
                    $stmt->execute([$empleado_id]);
                    $old_photo = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (
                        $old_photo && $old_photo['foto_perfil'] &&
                        file_exists($upload_dir . $old_photo['foto_perfil']) &&
                        $old_photo['foto_perfil'] !== 'logo-banco.png'
                    ) {
                        unlink($upload_dir . $old_photo['foto_perfil']);
                    }

                    $stmt = $pdo->prepare("UPDATE empleados SET foto_perfil = ? WHERE id = ?");
                    $stmt->execute([$filename, $empleado_id]);

                    $mensaje_exito = "Foto de perfil actualizada correctamente";

                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=foto_actualizada");
                    exit();
                } catch (PDOException $e) {
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                    $mensaje_error = "Error al actualizar la foto en la base de datos: " . $e->getMessage();
                }
            } else {
                $mensaje_error = "Error al mover el archivo al directorio de destino";
            }
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'foto_actualizada') {
    $mensaje_exito = "Foto de perfil actualizada correctamente";
}

// OBTENER DATOS DEL EMPLEADO
try {
    $stmt = $pdo->prepare("SELECT e.*, d.nombre as departamento_nombre 
                          FROM empleados e 
                          LEFT JOIN departamentos d ON e.departamento_id = d.id 
                          WHERE e.id = ?");
    $stmt->execute([$empleado_id]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empleado) {
        session_destroy();
        header('Location: ../../index.php?error=invalid_session');
        exit();
    }
} catch (PDOException $e) {
    die("Error al obtener datos del empleado: " . $e->getMessage());
}

function formatearFecha($fecha)
{
    if ($fecha) {
        return date('d/m/Y', strtotime($fecha));
    }
    return 'No especificada';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | Capital Human</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Capital_HumanMVC/public/css/client/perfil2.css"">
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
                <li class="active">
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
                        <img src="<?php echo obtenerFotoPerfil($empleado['foto_perfil']); ?>?v=<?php echo time(); ?>"
                            alt="Usuario Avatar"
                            style="cursor: pointer; width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($empleado['nombre_completo']); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($empleado['departamento_nombre'] ?? 'Empleado'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="page-header">
                    <h1>Mi Perfil</h1>
                    <p>Administra tu información personal y profesional</p>
                </div>

                <?php if (isset($mensaje_exito)): ?>
                    <div class="mensaje exito" style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                        <?php echo $mensaje_exito; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($mensaje_error)): ?>
                    <div class="mensaje error" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                        <?php echo $mensaje_error; ?>
                    </div>
                <?php endif; ?>

                <div class="profile-container">
                    <div class="profile-header">
                        <div class="profile-header-left">
                            <div class="profile-avatar">
                                <img src="<?php echo obtenerFotoPerfil($empleado['foto_perfil']); ?>?v=<?php echo time(); ?>"
                                    class="avatar-large" style="object-fit: cover;">
                                <button class="change-photo-btn" onclick="openPhotoModal()">
                                    <i class="bi bi-camera"></i>
                                </button>
                            </div>
                            <div class="profile-details">
                                <h2><?php echo htmlspecialchars($empleado['nombre_completo']); ?></h2>
                                <p class="profile-role"><?php echo htmlspecialchars($empleado['cargo'] ?? 'Sin cargo especificado'); ?></p>
                                <p class="profile-department"><?php echo htmlspecialchars($empleado['departamento_nombre'] ?? 'Sin departamento'); ?></p>
                                <div class="profile-contact">
                                    <span><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($empleado['correo']); ?></span>
                                    <?php if ($empleado['telefono']): ?>
                                        <span><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($empleado['telefono']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="profile-header-right">
                            <div class="employee-status">
                                <span class="status-badge status-success"><?php echo ucfirst($empleado['estado']); ?></span>
                                <?php if ($empleado['fecha_contratacion']): ?>
                                    <p>Empleado desde: <?php echo formatearFecha($empleado['fecha_contratacion']); ?></p>
                                <?php endif; ?>
                            </div>
                            <button class="btn-outline" onclick="openEditModal()">
                                <i class="bi bi-pencil"></i> Editar Perfil
                            </button>
                        </div>
                    </div>

                    <div class="profile-tabs">
                        <button class="tab-btn active" data-tab="info-personal">Información Personal</button>
                    </div>

                    <div class="profile-tab-content">
                        <div class="tab-panel active" id="info-personal">
                            <div class="info-section">
                                <h3>Datos Personales</h3>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Nombre Completo</label>
                                        <p><?php echo htmlspecialchars($empleado['nombre_completo']); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Fecha de Nacimiento</label>
                                        <p><?php echo formatearFecha($empleado['fecha_nacimiento']); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Género</label>
                                        <p><?php echo ucfirst($empleado['genero'] ?? 'No especificado'); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Documento de Identidad</label>
                                        <p><?php echo $empleado['documento_identidad'] ? 'CC ' . number_format($empleado['documento_identidad']) : 'No especificado'; ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Estado Civil</label>
                                        <p><?php echo ucfirst(str_replace('_', ' ', $empleado['estado_civil'] ?? 'No especificado')); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Nacionalidad</label>
                                        <p><?php echo htmlspecialchars($empleado['nacionalidad'] ?? 'No especificada'); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="info-section">
                                <h3>Contacto</h3>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Correo Electrónico</label>
                                        <p><?php echo htmlspecialchars($empleado['correo']); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Teléfono Móvil</label>
                                        <p><?php echo htmlspecialchars($empleado['telefono'] ?? 'No especificado'); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Teléfono Fijo</label>
                                        <p><?php echo htmlspecialchars($empleado['telefono_fijo'] ?? 'No especificado'); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Dirección</label>
                                        <p><?php echo htmlspecialchars($empleado['direccion'] ?? 'No especificada'); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Ciudad</label>
                                        <p><?php echo htmlspecialchars($empleado['ciudad'] ?? 'No especificada'); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Código Postal</label>
                                        <p><?php echo htmlspecialchars($empleado['codigo_postal'] ?? 'No especificado'); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="info-section">
                                <h3>Información Laboral</h3>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Cargo</label>
                                        <p><?php echo htmlspecialchars($empleado['cargo'] ?? 'No especificado'); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Departamento</label>
                                        <p><?php echo htmlspecialchars($empleado['departamento_nombre'] ?? 'Sin departamento'); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Fecha de Contratación</label>
                                        <p><?php echo formatearFecha($empleado['fecha_contratacion']); ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Estado</label>
                                        <p><span class="status-badge status-success"><?php echo ucfirst($empleado['estado']); ?></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Editar Información Personal</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="actualizar_perfil">

                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre_completo">Nombre Completo:</label>
                        <input type="text" id="nombre_completo" name="nombre_completo"
                            value="<?php echo htmlspecialchars($empleado['nombre_completo']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento"
                            value="<?php echo $empleado['fecha_nacimiento']; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="genero">Género:</label>
                        <select id="genero" name="genero">
                            <option value="">Seleccionar...</option>
                            <option value="masculino" <?php echo $empleado['genero'] == 'masculino' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="femenino" <?php echo $empleado['genero'] == 'femenino' ? 'selected' : ''; ?>>Femenino</option>
                            <option value="otro" <?php echo $empleado['genero'] == 'otro' ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="documento_identidad">Documento de Identidad:</label>
                        <input type="text" id="documento_identidad" name="documento_identidad"
                            value="<?php echo htmlspecialchars($empleado['documento_identidad']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="estado_civil">Estado Civil:</label>
                        <select id="estado_civil" name="estado_civil">
                            <option value="">Seleccionar...</option>
                            <option value="soltero" <?php echo $empleado['estado_civil'] == 'soltero' ? 'selected' : ''; ?>>Soltero/a</option>
                            <option value="casado" <?php echo $empleado['estado_civil'] == 'casado' ? 'selected' : ''; ?>>Casado/a</option>
                            <option value="divorciado" <?php echo $empleado['estado_civil'] == 'divorciado' ? 'selected' : ''; ?>>Divorciado/a</option>
                            <option value="viudo" <?php echo $empleado['estado_civil'] == 'viudo' ? 'selected' : ''; ?>>Viudo/a</option>
                            <option value="union_libre" <?php echo $empleado['estado_civil'] == 'union_libre' ? 'selected' : ''; ?>>Unión Libre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nacionalidad">Nacionalidad:</label>
                        <input type="text" id="nacionalidad" name="nacionalidad"
                            value="<?php echo htmlspecialchars($empleado['nacionalidad']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="correo">Correo Electrónico:</label>
                    <input type="email" id="correo" name="correo"
                        value="<?php echo htmlspecialchars($empleado['correo']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="telefono">Teléfono Móvil:</label>
                        <input type="text" id="telefono" name="telefono"
                            value="<?php echo htmlspecialchars($empleado['telefono']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="telefono_fijo">Teléfono Fijo:</label>
                        <input type="text" id="telefono_fijo" name="telefono_fijo"
                            value="<?php echo htmlspecialchars($empleado['telefono_fijo']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="direccion">Dirección:</label>
                    <input type="text" id="direccion" name="direccion"
                        value="<?php echo htmlspecialchars($empleado['direccion']); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="ciudad">Ciudad:</label>
                        <input type="text" id="ciudad" name="ciudad"
                            value="<?php echo htmlspecialchars($empleado['ciudad']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="codigo_postal">Código Postal:</label>
                        <input type="text" id="codigo_postal" name="codigo_postal"
                            value="<?php echo htmlspecialchars($empleado['codigo_postal']); ?>">
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="photoModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePhotoModal()">&times;</span>
            <h2>Cambiar Foto de Perfil</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="subir_foto">

                <div class="form-group">
                    <label for="foto_perfil">Seleccionar nueva foto:</label>
                    <input type="file" id="foto_perfil" name="foto_perfil" accept="image/jpeg,image/png,image/gif" required>
                    <small style="color: #666; font-size: 12px;">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 5MB</small>
                </div>

                <div id="preview-container" style="margin-top: 15px; display: none;">
                    <label>Vista previa:</label>
                    <div style="text-align: center; margin-top: 10px;">
                        <img id="preview-image" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #ddd; object-fit: cover;">
                    </div>
                </div>

                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closePhotoModal()" style="margin-right: 10px;">Cancelar</button>
                    <button type="submit" class="btn btn-success">Subir Foto</button>
                </div>
            </form>
        </div>
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

        function openEditModal() {
            document.getElementById('editModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openPhotoModal() {
            document.getElementById('photoModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closePhotoModal() {
            document.getElementById('photoModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('foto_perfil').value = '';
            document.getElementById('preview-container').style.display = 'none';
        }

        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const photoModal = document.getElementById('photoModal');

            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == photoModal) {
                closePhotoModal();
            }
        }

        document.getElementById('foto_perfil').addEventListener('change', function(event) {
            const file = event.target.files[0];
            const previewContainer = document.getElementById('preview-container');
            const previewImage = document.getElementById('preview-image');

            if (file) {
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Formato de imagen no válido. Solo se permiten JPG, PNG y GIF');
                    event.target.value = '';
                    previewContainer.style.display = 'none';
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    alert('El archivo es muy grande. Tamaño máximo: 5MB');
                    event.target.value = '';
                    previewContainer.style.display = 'none';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewContainer.style.display = 'block'
                };
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabPanels = document.querySelectorAll('.tab-panel');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabPanels.forEach(panel => panel.classList.remove('active'));

                    this.classList.add('active');
                    document.getElementById(targetTab).classList.add('active');
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.mensaje');
            messages.forEach(function(message) {
                setTimeout(function() {
                    message.style.opacity = '0';
                    setTimeout(function() {
                        message.remove();
                    }, 300);
                }, 5000);
            });
        });

        // VALIDACIÓN FORMULARIO
        document.addEventListener('DOMContentLoaded', function() {
            const editForm = document.querySelector('#editModal form');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    const email = document.getElementById('correo').value;
                    const nombre = document.getElementById('nombre_completo').value;

                    if (!email || !nombre) {
                        e.preventDefault();
                        alert('El nombre completo y el correo electrónico son obligatorios.');
                        return false;
                    }

                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Por favor ingresa un correo electrónico válido.');
                        return false;
                    }
                });
            }
        });
    </script>
</body>

</html>