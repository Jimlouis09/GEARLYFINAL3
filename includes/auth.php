<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /GEARLY/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();

    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v) {
    return '₱' . number_format((float)$v, 2);
}

function flash($message, $type = 'success') {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

function showFlash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];

        echo '<div class="alert alert-' . h($f['type']) . '">' . h($f['message']) . '</div>';

        unset($_SESSION['flash']);
    }
}

function csrf() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function checkCsrf() {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')
    ) {
        http_response_code(419);
        exit('Invalid form token. Refresh and try again.');
    }
}


function sendCodeEmail($email, $name, $code, $purpose) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = GEARLY_SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = GEARLY_SMTP_USERNAME;
        $mail->Password = GEARLY_SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = GEARLY_SMTP_PORT;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            GEARLY_MAIL_FROM,
            GEARLY_MAIL_NAME
        );

        $mail->addAddress($email, $name);

        $mail->isHTML(false);

        $mail->Subject = 'GEARLY ' . ucfirst($purpose)
            . ' verification code';

        $mail->Body = "Hello $name,\n\n"
            . "Your GEARLY $purpose verification code is: $code\n"
            . "This code expires in 30 minutes.\n\n"
            . "If you did not request this, contact GEARLY support.";

        $mail->send();

        return true;

    } catch (Exception $e) {
        error_log('GEARLY email error: ' . $mail->ErrorInfo);
        return false;
    }
}
