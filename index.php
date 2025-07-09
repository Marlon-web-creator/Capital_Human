<?php
session_start();

require_once 'config/db.php';

$mensaje = '';
$tipo_mensaje = '';

// PROCESAMIENTO DEL FORMULARIO DE LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password']) && !isset($_POST['name'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT id, nombre, password, 'admin' as rol FROM administradores WHERE correo = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        $stmt = $db->prepare("SELECT id, nombre_completo as nombre, password, 'empleado' as rol FROM empleados WHERE correo = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($usuario && password_verify($password, $usuario['password'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_rol'] = $usuario['rol'];

        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['user_email'] = $email;

        if ($usuario['rol'] === 'admin') {
            header('Location: app/views/admin/admin_dashboard.php');
        } else {
            header('Location: app/views/client/employee_dashboard.php');
        }
        exit;
    } else {
        $mensaje = 'Correo electrónico o contraseña incorrectos';
        $tipo_mensaje = 'error';
    }
}

// PROCESAMIENTO DEL FORMULARIO DE REGISTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro_email'], $_POST['registro_password'])) {
    $nombre = trim($_POST['name']);
    $email = trim($_POST['registro_email']);
    $password = $_POST['registro_password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if ($password !== $confirm_password) {
        $mensaje = 'Las contraseñas no coinciden';
        $tipo_mensaje = 'error';
    } else {
        $stmt = $db->prepare("SELECT correo FROM administradores WHERE correo = ?");
        $stmt->execute([$email]);
        $admin_exists = $stmt->fetch();

        $stmt = $db->prepare("SELECT correo FROM empleados WHERE correo = ?");
        $stmt->execute([$email]);
        $empleado_exists = $stmt->fetch();

        if ($admin_exists || $empleado_exists) {
            $mensaje = 'Este correo electrónico ya está registrado';
            $tipo_mensaje = 'error';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            try {
                if ($role === 'admin') {
                    $stmt = $db->prepare("INSERT INTO administradores (nombre, correo, password) VALUES (?, ?, ?)");
                    $stmt->execute([$nombre, $email, $hashed_password]);
                } else {
                    $stmt = $db->prepare("INSERT INTO empleados (nombre_completo, correo, password, estado, fecha_contratacion) VALUES (?, ?, ?, 'activo', NOW())");
                    $stmt->execute([$nombre, $email, $hashed_password]);
                }

                $mensaje = 'Registro exitoso. Ahora puedes iniciar sesión.';
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = 'Error al registrar: ' . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capital Human | Gestión de Recursos Humanos</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="public/css/main.css">
</head>

<body>
    <!-- HEADER -->
    <header>
        <div class="container">
            <div class="logo">
                <img id="logo-img" src="" alt="Capital Human Logo" class="logo-img">
                <h1>Capital Human</h1>
            </div>

            <nav>
                <button class="mobile-menu-btn" id="mobile-menu-btn">
                    <i class="bi bi-list"></i>
                </button>
                <ul id="nav-menu">
                    <li><a href="#functionalities">Funcionalidades</a></li>
                    <li><a href="#about-us">Acerca De</a></li>
                    <li><a href="#contact">Contacto</a></li>
                    <li><button id="show-login-btn" class="nav-btn">Iniciar Sesión</button></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h2>Simplifica la gestión de tus recursos humanos</h2>
                <p>La solución integral para optimizar el talento empresarial. Gestiona empleados, nóminas y reportes en un solo lugar.</p>
                <div class="button-container">
                    <a href="#" id="show-register" class="btn btn-primary">Registrarse</a>
                    <a href="#" id="show-login" class="btn btn-secondary">Iniciar Sesión</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="public/images/test.png" alt="Gestión de recursos humanos">
            </div>
        </div>
    </section>

    <div id="auth-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="close-modal">&times;</span>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <!-- FORMULARIO DE LOGIN -->
            <div id="login-form" class="auth-form">
                <h3>Iniciar Sesión</h3>
                <form action="index.php" method="POST" class="login-form">
                    <div class="form-group">
                        <label for="login-email">Correo Electrónico</label>
                        <div class="input-icon">
                            <i class="bi bi-envelope"></i>
                            <input type="email" id="login-email" name="email" required placeholder="nombre@correo.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="login-password">Contraseña</label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="login-password" name="password" required placeholder="Tu contraseña">
                        </div>
                    </div>
                    <div class="form-group">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
                    <div class="auth-separator">
                        <span>¿No tienes una cuenta?</span>
                    </div>
                    <button type="button" id="switch-to-register" class="btn btn-outline btn-block">Registrarse</button>
                </form>
            </div>

            <!-- FORMULARIO DE REGISTRO -->
            <div id="register-form" class="auth-form" style="display: none;">
                <h3>Crear Cuenta</h3>
                <form action="index.php" method="POST">
                    <div class="form-group">
                        <label for="register-name">Nombre Completo</label>
                        <div class="input-icon">
                            <i class="bi bi-person"></i>
                            <input type="text" id="register-name" name="name" required placeholder="Tu nombre completo">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="register-email">Correo Electrónico</label>
                        <div class="input-icon">
                            <i class="bi bi-envelope"></i>
                            <input type="email" id="register-email" name="registro_email" required placeholder="nombre@correo.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="register-password">Contraseña</label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="register-password" name="registro_password" required placeholder="Crea una contraseña segura">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Confirmar Contraseña</label>
                        <div class="input-icon">
                            <i class="bi bi-lock-fill"></i>
                            <input type="password" id="confirm-password" name="confirm_password" required placeholder="Confirma tu contraseña">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="user-role">
                            <label>Tipo de usuario:</label>
                            <div class="role-options">
                                <input type="radio" id="role-admin" name="role" value="admin">
                                <label for="role-admin">Administrador</label>

                                <input type="radio" id="role-empleado" name="role" value="empleado" checked>
                                <label for="role-empleado">Empleado</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Crear Cuenta</button>
                </form>
            </div>
        </div>
    </div>

    <!-- FUNCIONALIDADES -->
    <section id="functionalities" class="section-padding">
        <div class="container">
            <div class="section-header">
                <h2>Funcionalidades</h2>
                <p>Todo lo que necesitas para gestionar tus recursos humanos en un solo lugar</p>
            </div>
            <div class="cards-container">
                <div class="card">
                    <div class="card-icon">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <h3>Gestión de Empleados</h3>
                    <p>Administra perfiles completos, contratos, documentos y más en una plataforma centralizada y segura.</p>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <h3>Nóminas Automáticas</h3>
                    <p>Calcula automáticamente salarios, deducciones, bonificaciones y genera recibos de pago en segundos.</p>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h3>Reportes Detallados</h3>
                    <p>Analiza el rendimiento de tu equipo con informes personalizados que te ayudan a tomar mejores decisiones.</p>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h3>Seguridad Avanzada</h3>
                    <p>Protección de datos personales con los más altos estándares de seguridad y conformidad normativa.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ACERCA DE NOSOTROS -->
    <section id="about-us" class="section-padding bg-light">
        <div class="container">
            <div class="about-container">
                <div class="about-image">
                    <img src="public/images/logo-sena.png" alt="Proyecto Capital Human" class="rounded-image">
                </div>
                <div class="about-content">
                    <h2>Acerca del Proyecto</h2>
                    <p class="subtitle">Un sistema de gestión de recursos humanos formativo</p>
                    <p>Capital Human es un proyecto educativo desarrollado para demostrar las capacidades de un sistema de gestión de recursos humanos y nóminas. Diseñado como herramienta de aprendizaje.</p>
                    <p>Este proyecto integra bases de datos y administración de recursos humanos, como proyecto formativo.</p>
                    <div class="about-stats">
                        <div class="stat">
                            <h3>Formativo</h3>
                            <p>Enfoque educativo</p>
                        </div>
                        <div class="stat">
                            <h3>Práctico</h3>
                            <p>Aplicación real</p>
                        </div>
                        <div class="stat">
                            <h3>Completo</h3>
                            <p>Sistema integral</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CONTACTO -->
    <section id="contact" class="section-padding bg-light">
        <div class="container">
            <div class="section-header">
                <h2>Contacto</h2>
                <p>¿Tienes preguntas sobre el proyecto?</p>
            </div>
            <div class="contact-container">
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="bi bi-compass"></i>
                        <div>
                            <h3>Teléfono</h3>
                            <p>3232381543</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="bi bi-telephone"></i>
                        <div>
                            <h3>Teléfono</h3>
                            <p>3232381543</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="bi bi-envelope"></i>
                        <div>
                            <h3>Email</h3>
                            <p>proyecto@capitalhuman.edu</p>
                        </div>
                    </div>
                    <div class="contact-social">
                        <h3>Síguenos</h3>
                        <div class="social-icons">
                            <a href="#"><i class="bi bi-facebook"></i></a>
                            <a href="#"><i class="bi bi-twitter"></i></a>
                            <a href="#"><i class="bi bi-github"></i></a>
                            <a href="#"><i class="bi bi-linkedin"></i></a>
                        </div>
                    </div>
                </div>

                <div class="contact-form-container">
                    <?php
                    if (isset($_SESSION['mensaje']) && isset($_SESSION['tipo_mensaje'])) {
                        echo '<div class="alert alert-' . $_SESSION['tipo_mensaje'] . '">' . $_SESSION['mensaje'] . '</div>';
                        unset($_SESSION['mensaje']);
                        unset($_SESSION['tipo_mensaje']);
                    }
                    ?>

                    <form action="app/controllers/contact_process.php" method="POST" class="contact-form">
                        <div class="form-group">
                            <label for="name">Nombre completo</label>
                            <input type="text" id="name" name="name" required placeholder="Introduce tu nombre">
                        </div>
                        <div class="form-group">
                            <label for="email">Correo electrónico</label>
                            <input type="email" id="email" name="email" required placeholder="nombre@correo.com">
                        </div>
                        <div class="form-group">
                            <label for="subject">Asunto</label>
                            <input type="text" id="subject" name="subject" required placeholder="¿En qué podemos ayudarte?">
                        </div>
                        <div class="form-group">
                            <label for="message">Mensaje</label>
                            <textarea id="message" name="message" rows="5" required placeholder="Escribe tu mensaje"></textarea>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-container">
                                <input type="checkbox" id="privacy" name="privacy" required>
                                <label for="privacy">Acepto la política de privacidad</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Enviar Mensaje</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>¿Listo para probar nuestro sistema de gestión de RRHH?</h2>
                <p>Registra una cuenta y explora todas las funcionalidades de este proyecto formativo</p>
                <a href="#" id="cta-register" class="btn btn-light">Registrarme Ahora</a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <div class="footer-logo">
                        <img id="footer-logo" src="/Capital_HumanMVC/public/images/logo-blanco.png" alt="Capital Human Logo">
                        <h3 class="footer-title">Capital Human</h3>
                    </div>
                    <p>Proyecto formativo de gestión de recursos humanos con enfoque educativo.</p>
                </div>
                <div class="footer-links">
                    <h3>Enlaces Rápidos</h3>
                    <ul>
                        <li><a href="#functionalities">Funcionalidades</a></li>
                        <li><a href="#about-us">Acerca del Proyecto</a></li>
                        <li><a href="#contact">Contacto</a></li>
                    </ul>
                </div>
                <div class="footer-newsletter" id="newsletter">
                    <h3>Newsletter</h3>
                    <p>Recibe actualizaciones sobre este proyecto</p>
                    <form action="app/controllers/subscribe.php" method="POST" class="newsletter-form">
                        <input type="email" name="subscribe_email" placeholder="Tu correo electrónico" required>
                        <button type="submit" class="btn btn-primary">Suscribirse</button>
                    </form>

                    <?php if (isset($_GET['newsletter'])): ?>
                        <div class="newsletter-message <?php echo $_GET['newsletter'] === 'success' ? 'success' : 'error'; ?>">
                            <?php
                            switch ($_GET['newsletter']) {
                                case 'success':
                                    echo '<span>¡Gracias por suscribirte a nuestro newsletter!</span>';
                                    break;
                                case 'invalid_email':
                                    echo '<span>Por favor, introduce un correo electrónico válido.</span>';
                                    break;
                                case 'already_subscribed':
                                    echo '<span>Este correo ya está suscrito a nuestro newsletter.</span>';
                                    break;
                                case 'error':
                                    echo '<span>Ha ocurrido un error al procesar tu solicitud.</span>';
                                    break;
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Capital Human. Proyecto educativo sin fines comerciales.</p>
                <div class="social-icons">
                    <a href="#"><i class="bi bi-facebook"></i></a>
                    <a href="#"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-github"></i></a>
                    <a href="#"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- MODO NOCHE/DIA -->
    <div id="theme-toggle" class="theme-toggle">
        <i id="theme-icon" class="bi bi-moon-fill"></i>
    </div>

    <script src="public/js/main.js"></script>
    <script src="public/js/toggle.js"></script>

</body>

</html>