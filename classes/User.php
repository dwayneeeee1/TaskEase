<?php

class User
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, name, email, password, role, account_status, auth_version, student_id
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, name, email, password, role, account_status, auth_version, student_id
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function studentLinkMatchesEmail(int $studentId, string $email): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT 1 FROM students
             WHERE student_id = :student_id AND LOWER(TRIM(student_email)) = :email
             LIMIT 1'
        );
        $stmt->execute([':student_id' => $studentId, ':email' => $email]);

        return (bool) $stmt->fetchColumn();
    }

    public function studentAlreadyLinked(int $studentId): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT 1 FROM users WHERE student_id = :student_id LIMIT 1'
        );
        $stmt->execute([':student_id' => $studentId]);

        return (bool) $stmt->fetchColumn();
    }

    public function create(string $name, string $email, string $password, ?int $studentId): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO users (name, email, password, student_id, role, account_status)
             VALUES (:name, :email, :password, :student_id, 'student', 'active')"
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':student_id' => $studentId,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function verifyLogin(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);

        if (!$user || $user['account_status'] !== 'active'
            || !password_verify($password, $user['password'])) {
            return null;
        }

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $stmt = $this->conn->prepare('UPDATE users SET password = :password WHERE id = :id');
            $stmt->execute([
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => (int) $user['id'],
            ]);
        }

        return $user;
    }

    public function countRecentFailedAttempts(string $identifierHash): int
    {
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE identifier_hash = :identifier_hash
               AND attempted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)'
        );
        $stmt->execute([':identifier_hash' => $identifierHash]);

        return (int) $stmt->fetchColumn();
    }

    public function recordFailedAttempt(string $identifierHash): void
    {
        $this->conn->exec(
            'DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)'
        );
        $stmt = $this->conn->prepare(
            'INSERT INTO login_attempts (identifier_hash, attempted_at)
             VALUES (:identifier_hash, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([':identifier_hash' => $identifierHash]);
    }

    public function clearFailedAttempts(string $identifierHash): void
    {
        $stmt = $this->conn->prepare(
            'DELETE FROM login_attempts WHERE identifier_hash = :identifier_hash'
        );
        $stmt->execute([':identifier_hash' => $identifierHash]);
    }

    public function resetPassword(int $userId, string $password): void
    {
        $stmt = $this->conn->prepare(
            'UPDATE users SET password = :password, auth_version = auth_version + 1 WHERE id = :id'
        );
        $stmt->execute([
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':id' => $userId,
        ]);
    }
}
