<?php

function passwordResetMailIsConfigured(): bool
{
    foreach (['TASK_EASE_SMTP_HOST', 'TASK_EASE_SMTP_USERNAME', 'TASK_EASE_SMTP_PASSWORD', 'TASK_EASE_SMTP_FROM'] as $key) {
        if (getenv($key) === false || getenv($key) === '') {
            return false;
        }
    }

    $encryption = strtolower(getenv('TASK_EASE_SMTP_ENCRYPTION') ?: 'tls');
    $port = (int) (getenv('TASK_EASE_SMTP_PORT') ?: 587);
    $baseUrl = getenv('TASK_EASE_BASE_URL');
    $baseUrlParts = is_string($baseUrl) ? parse_url($baseUrl) : false;

    return in_array($encryption, ['tls', 'ssl'], true)
        && $port > 0 && $port <= 65535
        && is_array($baseUrlParts)
        && in_array(strtolower($baseUrlParts['scheme'] ?? ''), ['http', 'https'], true)
        && !empty($baseUrlParts['host'])
        && getenv('TASK_EASE_BASE_URL') !== ''
        && is_file(__DIR__ . '/../vendor/autoload.php');
}

function sendPasswordResetEmail(string $email, string $name, string $token): void
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = getenv('TASK_EASE_SMTP_HOST');
    $mailer->SMTPAuth = true;
    $mailer->Username = getenv('TASK_EASE_SMTP_USERNAME');
    $mailer->Password = getenv('TASK_EASE_SMTP_PASSWORD');
    $mailer->Port = (int) (getenv('TASK_EASE_SMTP_PORT') ?: 587);
    $encryption = strtolower(getenv('TASK_EASE_SMTP_ENCRYPTION') ?: 'tls');

    if ($encryption === 'ssl') {
        $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'tls') {
        $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        throw new RuntimeException('TASK_EASE_SMTP_ENCRYPTION must be tls or ssl.');
    }

    $baseUrl = rtrim((string) getenv('TASK_EASE_BASE_URL'), '/');
    $resetUrl = $baseUrl . '/reset_password.php?token=' . rawurlencode($token);
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom(getenv('TASK_EASE_SMTP_FROM'), getenv('TASK_EASE_SMTP_FROM_NAME') ?: 'TaskEase');
    $mailer->addAddress($email, $name);
    $mailer->isHTML(true);
    $mailer->Subject = 'Reset your TaskEase password';
    $mailer->Body = '<p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>Use the secure link below to set a new TaskEase password. This link expires in 60 minutes.</p>'
        . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Reset password</a></p>'
        . '<p>If you did not request this change, you can ignore this email.</p>';
    $mailer->AltBody = "Reset your TaskEase password using this link (expires in 60 minutes):\n" . $resetUrl;
    $mailer->send();
}
