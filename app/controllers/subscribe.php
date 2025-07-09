<?php
session_start();

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_email'])) {
    $email = trim($_POST['subscribe_email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../../index.php?newsletter=invalid_email#newsletter');
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT email FROM newsletter WHERE email = ?");
        $stmt->execute([$email]);
        $existe = $stmt->fetch();

        if ($existe) {
            header('Location: ../../index.php?newsletter=already_subscribed#newsletter');
        } else {
            $stmt = $db->prepare("INSERT INTO newsletter (email) VALUES (?)");
            $stmt->execute([$email]);

            header('Location: ../../index.php?newsletter=success#newsletter');
        }
    } catch (PDOException $e) {
        header('Location: ../../index.php?newsletter=error#newsletter');
    }

    exit;
} else {
    header('Location: index.php');
    exit;
}
