<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/User.php';

function isHttpsRequest(): bool
{
    return (
        (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('TaskEaseSession');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function isLoggedIn(): bool
{
    return isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id']);
}

function currentUser(): ?array
{
    return isLoggedIn() ? $_SESSION['user'] : null;
}

function setAuthenticatedUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'] ?? 'student',
        'auth_version' => (int) ($user['auth_version'] ?? 0),
        'student_id' => isset($user['student_id']) ? (int) $user['student_id'] : null,
    ];
}

function rememberCookieOptions(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function clearRememberCookie(): void
{
    setcookie('taskease_remember', '', rememberCookieOptions(time() - 3600));
    unset($_COOKIE['taskease_remember']);
}

function issueRememberToken(PDO $db, int $userId): void
{
    $db->exec('DELETE FROM remember_tokens WHERE expires_at <= CURRENT_TIMESTAMP');
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

    $stmt = $db->prepare(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
         VALUES (:user_id, :selector, :token_hash, :expires_at)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':selector' => $selector,
        ':token_hash' => hash('sha256', $validator),
        ':expires_at' => $expiresAt,
    ]);

    setcookie(
        'taskease_remember',
        $selector . ':' . $validator,
        rememberCookieOptions(time() + 60 * 60 * 24 * 30)
    );
}

function revokeRememberToken(PDO $db): void
{
    $cookie = $_COOKIE['taskease_remember'] ?? '';
    if (is_string($cookie) && preg_match('/^([a-f0-9]{32}):[a-f0-9]{64}$/', $cookie, $matches)) {
        $stmt = $db->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $stmt->execute([':selector' => $matches[1]]);
    }

    clearRememberCookie();
}

function restoreRememberedUser(): bool
{
    $cookie = $_COOKIE['taskease_remember'] ?? '';
    if (!is_string($cookie) || !preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/', $cookie, $matches)) {
        return false;
    }

    try {
        $database = new Database();
        $db = $database->connect();
        $stmt = $db->prepare(
            'SELECT rt.user_id, rt.token_hash, u.id, u.name, u.email, u.role,
                    u.account_status, u.auth_version, u.student_id
             FROM remember_tokens rt
             INNER JOIN users u ON u.id = rt.user_id
             WHERE rt.selector = :selector AND rt.expires_at > CURRENT_TIMESTAMP
             LIMIT 1'
        );
        $stmt->execute([':selector' => $matches[1]]);
        $row = $stmt->fetch();

        if (!$row || $row['account_status'] !== 'active'
            || !hash_equals($row['token_hash'], hash('sha256', $matches[2]))) {
            if ($row) {
                $delete = $db->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
                $delete->execute([':selector' => $matches[1]]);
            }
            clearRememberCookie();
            return false;
        }

        $delete = $db->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $delete->execute([':selector' => $matches[1]]);
        setAuthenticatedUser($row);
        issueRememberToken($db, (int) $row['id']);
        return true;
    } catch (PDOException $error) {
        error_log('TaskEase remember-me authentication failed: ' . $error->getMessage());
        return false;
    }
}

function requireLogin(string $redirect = 'login.php'): void
{
    if (isLoggedIn()) {
        $database = new Database();
        $user = (new User($database->connect()))->findById((int) $_SESSION['user']['id']);

        if ($user && $user['account_status'] === 'active'
            && (int) $user['auth_version'] === (int) ($_SESSION['user']['auth_version'] ?? 0)) {
            $_SESSION['user']['name'] = $user['name'];
            $_SESSION['user']['email'] = $user['email'];
            $_SESSION['user']['role'] = $user['role'];
            $_SESSION['user']['student_id'] = isset($user['student_id']) ? (int) $user['student_id'] : null;
        } else {
            unset($_SESSION['user']);
            session_regenerate_id(true);
        }
    }

    if (!isLoggedIn() && !restoreRememberedUser()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: ' . $redirect);
        exit;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function requireGuest(string $redirect = 'index.php'): void
{
    if (isLoggedIn()) {
        $database = new Database();
        $user = (new User($database->connect()))->findById((int) $_SESSION['user']['id']);
        if (!$user || $user['account_status'] !== 'active'
            || (int) $user['auth_version'] !== (int) ($_SESSION['user']['auth_version'] ?? 0)) {
            unset($_SESSION['user']);
            session_regenerate_id(true);
        }
    }

    if (isLoggedIn() || restoreRememberedUser()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
