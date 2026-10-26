<?php

class Task
{
    private PDO $conn;
    private string $table = 'tasks';

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Transaction 1 — create a task and commit only after the insert succeeds.
    public function addTask(
        int $ownerUserId,
        ?int $studentId,
        ?int $subjectId,
        string $title,
        string $description,
        string $priority,
        string $dueDate,
        string $status
    ): bool {
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table}
                    (student_id, owner_user_id, subject_id, title, description, priority, due_date, status)
                 VALUES
                    (:student_id, :owner_user_id, :subject_id, :title, :description, :priority, :due_date, :status)"
            );
            $stmt->execute([
                ':student_id' => $studentId,
                ':owner_user_id' => $ownerUserId,
                ':subject_id' => $subjectId,
                ':title' => $title,
                ':description' => $description,
                ':priority' => $priority,
                ':due_date' => $dueDate,
                ':status' => $status,
            ]);
            $this->conn->commit();

            return true;
        } catch (Throwable $error) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $error;
        }
    }

    public function getTasks(int $ownerUserId, ?int $studentId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE owner_user_id = :owner_user_id
                OR (student_id = :student_id AND owner_user_id IS NULL)
             ORDER BY due_date ASC"
        );
        $stmt->execute([':owner_user_id' => $ownerUserId, ':student_id' => $studentId]);

        return $stmt->fetchAll();
    }

    public function getTaskById(int $id, int $ownerUserId, ?int $studentId): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE task_id = :task_id AND (
                 owner_user_id = :owner_user_id
                 OR (student_id = :student_id AND owner_user_id IS NULL)
             ) LIMIT 1"
        );
        $stmt->execute([
            ':task_id' => $id,
            ':owner_user_id' => $ownerUserId,
            ':student_id' => $studentId,
        ]);
        $task = $stmt->fetch();

        return $task ?: null;
    }

    // Transaction 2 — lock and verify ownership before changing a task.
    public function updateTask(
        int $id,
        int $ownerUserId,
        ?int $studentId,
        ?int $subjectId,
        string $title,
        string $description,
        string $priority,
        string $dueDate,
        string $status
    ): bool {
        try {
            $this->conn->beginTransaction();
            $currentTask = $this->getTaskForUpdate($id, $ownerUserId, $studentId);
            if (!$currentTask) {
                $this->conn->rollBack();
                return false;
            }

            $stmt = $this->conn->prepare(
                "UPDATE {$this->table}
                 SET owner_user_id = :owner_user_id,
                     subject_id = :subject_id,
                     title = :title,
                     description = :description,
                     priority = :priority,
                     due_date = :due_date,
                     status = :status
                 WHERE task_id = :task_id"
            );
            $stmt->execute([
                ':owner_user_id' => $ownerUserId,
                ':subject_id' => $subjectId,
                ':title' => $title,
                ':description' => $description,
                ':priority' => $priority,
                ':due_date' => $dueDate,
                ':status' => $status,
                ':task_id' => $id,
            ]);
            $this->conn->commit();

            return true;
        } catch (Throwable $error) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $error;
        }
    }

    // Transaction 3 — lock and verify ownership before deleting a task.
    public function deleteTask(int $id, int $ownerUserId, ?int $studentId): bool
    {
        try {
            $this->conn->beginTransaction();
            if (!$this->getTaskForUpdate($id, $ownerUserId, $studentId)) {
                $this->conn->rollBack();
                return false;
            }

            $stmt = $this->conn->prepare(
                "DELETE FROM {$this->table}
                 WHERE task_id = :task_id
                   AND (
                       owner_user_id = :owner_user_id
                       OR (student_id = :student_id AND owner_user_id IS NULL)
                   )"
            );
            $stmt->execute([
                ':task_id' => $id,
                ':owner_user_id' => $ownerUserId,
                ':student_id' => $studentId,
            ]);

            if ($stmt->rowCount() !== 1) {
                $this->conn->rollBack();
                return false;
            }

            $this->conn->commit();

            return true;
        } catch (Throwable $error) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $error;
        }
    }

    private function getTaskForUpdate(int $id, int $ownerUserId, ?int $studentId): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT task_id FROM {$this->table}
             WHERE task_id = :task_id
               AND (
                   owner_user_id = :owner_user_id
                   OR (student_id = :student_id AND owner_user_id IS NULL)
               )
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([
            ':task_id' => $id,
            ':owner_user_id' => $ownerUserId,
            ':student_id' => $studentId,
        ]);
        $task = $stmt->fetch();

        return $task ?: null;
    }

    public function searchTasks(string $keyword, int $ownerUserId, ?int $studentId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table}
             WHERE (owner_user_id = :owner_user_id
                    OR (student_id = :student_id AND owner_user_id IS NULL))
               AND (title LIKE :title_keyword OR description LIKE :description_keyword
                    OR priority LIKE :priority_keyword OR status LIKE :status_keyword)
             ORDER BY due_date ASC"
        );
        $search = '%' . $keyword . '%';
        $stmt->execute([
            ':owner_user_id' => $ownerUserId,
            ':student_id' => $studentId,
            ':title_keyword' => $search,
            ':description_keyword' => $search,
            ':priority_keyword' => $search,
            ':status_keyword' => $search,
        ]);

        return $stmt->fetchAll();
    }
}
