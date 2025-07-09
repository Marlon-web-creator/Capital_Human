<?php
session_start();

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $privacy = isset($_POST['privacy']);

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $_SESSION['mensaje'] = 'Todos los campos son obligatorios.';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ../../index.php#contact');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['mensaje'] = 'El formato del correo electrónico no es válido.';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ../../index.php#contact');
        exit;
    }

    if (strlen($name) > 100) {
        $_SESSION['mensaje'] = 'El nombre es demasiado largo (máximo 100 caracteres).';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ../../index.php#contact');
        exit;
    }

    if (strlen($email) > 100) {
        $_SESSION['mensaje'] = 'El email es demasiado largo (máximo 100 caracteres).';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ../../index.php#contact');
        exit;
    }

    if (strlen($subject) > 200) {
        $_SESSION['mensaje'] = 'El asunto es demasiado largo (máximo 200 caracteres).';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ../../index.php#contact');
        exit;
    }

    if (strlen($message) > 2000) {
        $_SESSION['mensaje'] = 'El mensaje es demasiado largo (máximo 2000 caracteres).';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ../../index.php#contact');
        exit;
    }

    if (!$privacy) {
        $_SESSION['mensaje'] = 'Debes aceptar la política de privacidad para enviar el mensaje.';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ../../index.php#contact');
        exit;
    }

    try {
        $createTable = "CREATE TABLE IF NOT EXISTS contactos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            asunto VARCHAR(200) NOT NULL,
            mensaje TEXT NOT NULL,
            fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            leido BOOLEAN DEFAULT FALSE,
            respondido BOOLEAN DEFAULT FALSE
        )";
        $db->exec($createTable);

        $stmt = $db->prepare("INSERT INTO contactos (nombre, email, asunto, mensaje, fecha_envio) VALUES (?, ?, ?, ?, NOW())");
        $result = $stmt->execute([$name, $email, $subject, $message]);

        if ($result) {
            $_SESSION['mensaje'] = 'Tu mensaje ha sido enviado correctamente. Te responderemos pronto.';
            $_SESSION['tipo_mensaje'] = 'success';
        } else {
            $_SESSION['mensaje'] = 'Error al enviar el mensaje. Inténtalo de nuevo más tarde.';
            $_SESSION['tipo_mensaje'] = 'error';
        }

    } catch (PDOException $e) {
        $_SESSION['mensaje'] = 'Error al procesar tu solicitud. Inténtalo de nuevo más tarde.';
        $_SESSION['tipo_mensaje'] = 'error';
        
        error_log("Error en contact_process.php: " . $e->getMessage());
    }

    header('Location: ../../index.php#contact');
    exit;

} else {
    $_SESSION['mensaje'] = 'Acceso no autorizado.';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: ../../index.php');
    exit;
}
?>