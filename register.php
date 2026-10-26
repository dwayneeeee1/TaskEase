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
$formData = ['name' => '', 'email' => '', 'student_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $formData['name'] = trim((string) ($_POST['name'] ?? ''));
    $formData['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $formData['student_id'] = trim((string) ($_POST['student_id'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $studentId = null;

    if ($formData['name'] === '' || $formData['email'] === '' || $password === '' || $confirmPassword === '') {
        $errors[] = 'Complete all required fields.';
    }

    if ($formData['name'] !== '' && (mb_strlen($formData['name']) < 2 || mb_strlen($formData['name']) > 100)) {
        $errors[] = 'Your name must be between 2 and 100 characters.';
    }

    if ($formData['email'] !== '' && (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)
        || strlen($formData['email']) > 150)) {
        $errors[] = 'Enter a valid email address (up to 150 characters).';
    }

    if ($formData['student_id'] !== '') {
        $parsedStudentId = filter_var($formData['student_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsedStudentId === false) {
            $errors[] = 'Enter a valid student ID.';
        } else {
            $studentId = (int) $parsedStudentId;
            if ($formData['email'] !== '' && filter_var($formData['email'], FILTER_VALIDATE_EMAIL)
                && !$userModel->studentLinkMatchesEmail($studentId, $formData['email'])) {
                $errors[] = 'That student ID does not match the email on the student record.';
            }
            if ($userModel->studentAlreadyLinked($studentId)) {
                $errors[] = 'That student ID is already linked to an account.';
            }
        }
    }

    if ($password !== '' && (mb_strlen($password) < 10 || mb_strlen($password) > 128 || strlen($password) > 512)) {
        $errors[] = 'Use a password between 10 and 128 characters.';
    }

    if ($password !== '' && $confirmPassword !== '' && !hash_equals($password, $confirmPassword)) {
        $errors[] = 'The passwords do not match.';
    }

    if ($formData['email'] !== '' && filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        if ($userModel->findByEmail($formData['email'])) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (!$errors) {
        try {
            $userModel->create($formData['name'], $formData['email'], $password, $studentId);
            $_SESSION['success'] = 'Your account is ready. Sign in to continue.';
            header('Location: login.php');
            exit;
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                $errors[] = 'An account already exists for this email or student ID.';
            } else {
                error_log('TaskEase registration failed: ' . $error->getMessage());
                $errors[] = 'Unable to create your account right now. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account - TaskEase</title>
    <link rel="stylesheet" href="css/auth.css?v=2">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="register-title">
            <a class="auth-brand" href="index.php" aria-label="TaskEase home">
                <span class="brand-mark" aria-hidden="true">T</span>
                <span class="auth-brand-text">TaskEase</span>
            </a>
            <h1 id="register-title">Create your account</h1>
            <p class="auth-intro">Start organizing your school tasks with TaskEase.</p>
            <?php foreach ($errors as $error): ?>
                <div class="message error" role="alert"><?= e($error) ?></div>
            <?php endforeach; ?>
            <form class="auth-form" method="POST" action="register.php" data-auth-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div>
                    <label class="form-label" for="name">Full name</label>
                    <input class="auth-input" id="name" name="name" type="text"
                           value="<?= e($formData['name']) ?>" autocomplete="name" maxlength="100"
                           required>
                </div>
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input class="auth-input" id="email" name="email" type="email"
                           value="<?= e($formData['email']) ?>" autocomplete="email" maxlength="150"
                           placeholder="you@example.com" required>
                </div>
                <div>
                    <label class="form-label" for="student_id">Student ID <span>(optional)</span></label>
                    <input class="auth-input" id="student_id" name="student_id" type="number"
                           value="<?= e($formData['student_id']) ?>" min="1" step="1"
                           inputmode="numeric" autocomplete="off">
                    <small class="field-help">Link existing student tasks using the email on your student record.</small>
                </div>
                <div>
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <input class="auth-input" id="password" name="password" type="password"
                               autocomplete="new-password" minlength="10" maxlength="128" required>
                        <button type="button" class="password-toggle" data-target="password"
                                aria-label="Show password">Show</button>
                    </div>
                    <small class="field-help">Use at least 10 characters.</small>
                </div>
                <div>
                    <label class="form-label" for="confirm_password">Confirm password</label>
                    <div class="input-wrap">
                        <input class="auth-input" id="confirm_password" name="confirm_password"
                               type="password" autocomplete="new-password" minlength="10"
                               maxlength="128" required>
                        <button type="button" class="password-toggle" data-target="confirm_password"
                                aria-label="Show password">Show</button>
                    </div>
                </div>
                <button type="submit" class="auth-button">
                    <span class="button-label">Create account</span>
                    <span class="button-loading" aria-hidden="true">Creating account…</span>
                </button>
                <p class="auth-foot">Already registered? <a href="login.php">Sign in</a></p>
            </form>
        </section>
    </main>
    <script src="js/auth.js" defer></script>
</body>
</html>
