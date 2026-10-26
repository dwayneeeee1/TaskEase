<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/User.php';

$database = new Database();
$db = $database->connect();
$userModel = new User($db);
$submitted = false;
$smtpConfigured = passwordResetMailIsConfigured();
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 150) {
        $user = $userModel->findByEmail($email);

        if ($user && $user['account_status'] === 'active' && $smtpConfigured) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
            $delete = $db->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
            $delete->execute([':user_id' => (int) $user['id']]);
            $insert = $db->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (:user_id, :token_hash, :expires_at)'
            );
            $insert->execute([
                ':user_id' => (int) $user['id'],
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
            ]);

            try {
                sendPasswordResetEmail($user['email'], $user['name'], $token);
            } catch (PHPMailer\PHPMailer\Exception $error) {
                error_log('TaskEase password reset email failed: ' . $error->getMessage());
                $remove = $db->prepare('DELETE FROM password_resets WHERE token_hash = :token_hash');
                $remove->execute([':token_hash' => $tokenHash]);
            }
        }
    }

    $submitted = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password - TaskEase</title>
    <link rel="stylesheet" href="css/auth.css?v=2">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="forgot-title">
            <a class="auth-brand" href="index.php" aria-label="TaskEase home">
                <span class="brand-mark" aria-hidden="true">T</span>
                <span class="auth-brand-text">TaskEase</span>
            </a>
            <h1 id="forgot-title">Reset your password</h1>
            <p class="auth-intro">Enter the email address associated with your account.</p>
            <?php if ($submitted): ?>
                <div class="message success" role="status">
                    If an active account uses that address, reset instructions will be sent.
                </div>
            <?php endif; ?>
            <?php if (!$smtpConfigured): ?>
                <div class="message setup-note" role="note">
                    Password reset email is not configured yet. Ask the site administrator to
                    install the Composer dependency and configure the SMTP settings listed in
                    <code>.env.example</code>.
                </div>
            <?php endif; ?>
            <form class="auth-form" method="POST" action="forgot_password.php" data-auth-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input class="auth-input" id="email" name="email" type="email"
                           value="<?= e($email) ?>" autocomplete="email" maxlength="150"
                           placeholder="you@example.com" required>
                </div>
                <button type="submit" class="auth-button">
                    <span class="button-label">Send reset link</span>
                    <span class="button-loading" aria-hidden="true">Sending…</span>
                </button>
                <p class="auth-foot"><a href="login.php">Return to sign in</a></p>
            </form>
        </section>
    </main>
    <script src="js/auth.js" defer></script>
</body>
</html>
