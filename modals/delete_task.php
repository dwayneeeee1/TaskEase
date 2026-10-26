<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../classes/Task.php";
require_once __DIR__ . "/../includes/csrf.php";

requireLogin('../login.php');

$database = new Database();
$db = $database->connect();

$task = new Task($db);
$currentUser = currentUser();

$id = $_GET['id'] ?? '';

if (!ctype_digit((string) $id) || (int) $id < 1) {
    http_response_code(404);
    exit("Task not found.");
}

$studentId = isset($currentUser['student_id']) ? (int) $currentUser['student_id'] : null;
$currentTask = $task->getTaskById((int) $id, (int) $currentUser['id'], $studentId);

if (!$currentTask) {
    http_response_code(404);
    exit("Task not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireValidCsrfToken();
    try {
        $result = $task->deleteTask((int) $id, (int) $currentUser['id'], $studentId);

        if ($result) {
            $_SESSION['success'] = 'Task deleted successfully.';
            header("Location: ../index.php");
            exit;
        }

        http_response_code(404);
        $error = 'Task not found or you do not have permission to delete it.';
    } catch (Throwable $exception) {
        error_log('TaskEase task deletion failed: ' . $exception->getMessage());
        $error = 'Unable to delete the task right now. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Delete Task - TaskEase</title>

    <link rel="stylesheet" href="../css/style.css">

</head>

<body>

<div class="container">

    <div class="confirm-card">

        <div class="logo">TaskEase</div>

        <h2>Delete Task?</h2>

        <p>
            Are you sure you want to delete this task?
        </p>

        <p>
            <strong>
                <?= e($currentTask['title']) ?>
            </strong>
        </p>

        <p>
            Due Date:
            <?= e($currentTask['due_date']) ?>
        </p>

        <p>
            Status:
            <?= e($currentTask['status']) ?>
        </p>

        <?php if (isset($error)): ?>

            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <div class="confirm-actions">

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <button type="submit" class="btn btn-danger">
                    Yes, Delete
                </button>

            </form>

            <a href="../index.php" class="btn btn-secondary">
                Cancel
            </a>

        </div>

    </div>

</div>

</body>
</html>