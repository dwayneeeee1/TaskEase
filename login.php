<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/User.php';

requireGuest('index.php');

$database = new Database();
$db = $database->connect();
$userModel = new User($db);
$errors = [];
$email = '';
$successMessage = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Enter your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $identifierHash = hash(
            'sha256',
            $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );
        $attemptCount = $userModel->countRecentFailedAttempts($identifierHash);

        if ($attemptCount >= 5) {
            $errors[] = 'Too many unsuccessful attempts. Please wait 15 minutes and try again.';
        } else {
            $user = $userModel->verifyLogin($email, $password);

            if ($user) {
                $userModel->clearFailedAttempts($identifierHash);
                revokeRememberToken($db);
                setAuthenticatedUser($user);

                if (!empty($_POST['remember_me'])) {
                    issueRememberToken($db, (int) $user['id']);
                }

                $destination = $_SESSION['redirect_after_login'] ?? 'index.php';
                unset($_SESSION['redirect_after_login']);
                $parsedDestination = parse_url($destination);
                $path = $parsedDestination['path'] ?? '';

                if (is_string($path) && $path !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//')) {
                    $destination = $path . (isset($parsedDestination['query']) ? '?' . $parsedDestination['query'] : '');
                } else {
                    $destination = 'index.php';
                }

                header('Location: ' . $destination);
                exit;
            }

            $userModel->recordFailedAttempt($identifierHash);
            $errors[] = 'The email or password was not recognized.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="same-origin">
    <title>Sign in - TaskEase</title>
    <link rel="stylesheet" href="css/auth.css?v=2">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="login-title">
            <a class="auth-brand" href="index.php" aria-label="TaskEase home">
                <span class="brand-mark" aria-hidden="true">T</span>
                <span class="auth-brand-text">TaskEase</span>
            </a>
            <h1 id="login-title">Welcome back</h1>
            <p class="auth-intro">Sign in to stay on top of your school tasks.</p>

            <?php if ($successMessage !== ''): ?>
                <div class="message success" role="status"><?= e($successMessage) ?></div>
            <?php endif; ?>
            <?php foreach ($errors as $error): ?>
                <div class="message error" role="alert"><?= e($error) ?></div>
            <?php endforeach; ?>

            <form class="auth-form" method="POST" action="login.php" data-auth-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input class="auth-input" id="email" name="email" type="email"
                           value="<?= e($email) ?>" autocomplete="email" maxlength="150"
                           placeholder="you@example.com" required>
                </div>
                <div>
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <input class="auth-input" id="password" name="password" type="password"
                               autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-target="password"
                                aria-label="Show password">Show</button>
                    </div>
                </div>
                <div class="auth-options">
                    <label class="remember-option">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember me</span>
                    </label>
                    <a class="auth-link" href="forgot_password.php">Forgot password?</a>
                </div>
                <button type="submit" class="auth-button">
                    <span class="button-label">Login</span>
                    <span class="button-loading" aria-hidden="true">Signing in…</span>
                </button>
                <p class="auth-foot">Need an account? <a href="register.php">Sign up</a></p>
            </form>
        </section>
    </main>
    <script src="js/auth.js" defer></script>
</body>
</html>
