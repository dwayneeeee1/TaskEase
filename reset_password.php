<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/User.php';

$database = new Database();
$db = $database->connect();
$userModel = new User($db);
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$errors = [];
$resetComplete = false;
$validToken = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$tokenHash = $validToken ? hash('sha256', $token) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (mb_strlen($password) < 10 || mb_strlen($password) > 128 || strlen($password) > 512) {
        $errors[] = 'Use a password between 10 and 128 characters.';
    }
    if (!hash_equals($password, $confirmPassword)) {
        $errors[] = 'The passwords do not match.';
    }

    if (!$errors && $validToken) {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare(
                'SELECT user_id FROM password_resets
                 WHERE token_hash = :token_hash AND expires_at > CURRENT_TIMESTAMP
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':token_hash' => $tokenHash]);
            $reset = $stmt->fetch();

            if (!$reset) {
                $db->rollBack();
                $validToken = false;
            } else {
                $userId = (int) $reset['user_id'];
                $userModel->resetPassword($userId, $password);
                $deleteResets = $db->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
                $deleteResets->execute([':user_id' => $userId]);
                $deleteRememberTokens = $db->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
                $deleteRememberTokens->execute([':user_id' => $userId]);
                $db->commit();
                $resetComplete = true;
                if (isLoggedIn() && (int) $_SESSION['user']['id'] === $userId) {
                    unset($_SESSION['user']);
                }
            }
        } catch (PDOException $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('TaskEase password reset failed: ' . $error->getMessage());
            $errors[] = 'Unable to reset your password right now. Please try again.';
        }
    }
}

if ($validToken && !$resetComplete) {
    $check = $db->prepare(
        'SELECT 1 FROM password_resets
         WHERE token_hash = :token_hash AND expires_at > CURRENT_TIMESTAMP LIMIT 1'
    );
    $check->execute([':token_hash' => $tokenHash]);
    $validToken = (bool) $check->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Choose a new password - TaskEase</title>
    <link rel="stylesheet" href="css/auth.css?v=2">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="reset-title">
            <a class="auth-brand" href="index.php" aria-label="TaskEase home">
                <span class="brand-mark" aria-hidden="true">T</span>
                <span class="auth-brand-text">TaskEase</span>
            </a>
            <h1 id="reset-title">Choose a new password</h1>
            <?php if ($resetComplete): ?>
                <div class="message success" role="status">Your password has been changed. Sign in with your new password.</div>
                <p class="auth-foot"><a href="login.php">Go to sign in</a></p>
            <?php elseif (!$validToken): ?>
                <div class="message error" role="alert">This reset link is invalid, expired, or has already been used.</div>
                <p class="auth-foot"><a href="forgot_password.php">Request another reset link</a></p>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                    <div class="message error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>
                <p class="auth-intro">Choose a new password for your TaskEase account.</p>
                <form class="auth-form" method="POST" action="reset_password.php" data-auth-form>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div>
                        <label class="form-label" for="password">New password</label>
                        <div class="input-wrap">
                            <input class="auth-input" id="password" name="password" type="password"
                                   autocomplete="new-password" minlength="10" maxlength="128" required>
                            <button type="button" class="password-toggle" data-target="password"
                                    aria-label="Show password">Show</button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="confirm_password">Confirm new password</label>
                        <div class="input-wrap">
                            <input class="auth-input" id="confirm_password" name="confirm_password"
                                   type="password" autocomplete="new-password" minlength="10"
                                   maxlength="128" required>
                            <button type="button" class="password-toggle" data-target="confirm_password"
                                    aria-label="Show password">Show</button>
                        </div>
                    </div>
                    <button type="submit" class="auth-button">
                        <span class="button-label">Save new password</span>
                        <span class="button-loading" aria-hidden="true">Saving…</span>
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <script src="js/auth.js" defer></script>
</body>
</html>
