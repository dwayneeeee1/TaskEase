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

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireValidCsrfToken();
    $subject_id = $currentTask['subject_id'];
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $priority = (string) ($_POST['priority'] ?? '');
    $due_date = (string) ($_POST['due_date'] ?? '');
    $status = (string) ($_POST['status'] ?? '');
    $date = DateTime::createFromFormat('!Y-m-d', $due_date);

    if (
        $title === '' || mb_strlen($title) > 150 || !$date || $date->format('Y-m-d') !== $due_date
        || !in_array($priority, ['Low', 'Medium', 'High'], true)
        || !in_array($status, ['Pending', 'Ongoing', 'Completed'], true)
    ) {

        $error = "Please fill in all required fields.";

    } else {

        try {
            $result = $task->updateTask(
                (int) $id,
                (int) $currentUser['id'],
                $studentId,
                $subject_id,
                $title,
                $description,
                $priority,
                $due_date,
                $status
            );

            if ($result) {
                $_SESSION['success'] = 'Task updated successfully.';
                header("Location: ../index.php");
                exit;
            }

            http_response_code(404);
            $error = 'Task not found or you do not have permission to update it.';
        } catch (Throwable $exception) {
            error_log('TaskEase task update failed: ' . $exception->getMessage());
            $error = 'Unable to update the task right now. Please try again.';
        }
    }
}

$data = $_SERVER["REQUEST_METHOD"] === "POST"
    ? $_POST
    : $currentTask;
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Task - TaskEase</title>

    <link rel="stylesheet" href="../css/style.css">

</head>

<body>

<div class="container">

    <div class="header">

        <div>
            <div class="logo">TaskEase</div>
            <p class="subtitle">Edit Task</p>
        </div>

        <a href="../index.php" class="btn btn-secondary">
            ← Dashboard
        </a>

    </div>

    <div class="form-card">

        <h2>Edit Task</h2>

        <?php if ($error): ?>

            <div class="message error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="form-group">

                <label>Task Title *</label>

                <input
                    type="text"
                    name="title"
                    maxlength="150"
                    value="<?= e($data['title'] ?? '') ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Description</label>

                <textarea name="description"><?= e($data['description'] ?? '') ?></textarea>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>Priority *</label>

                    <select name="priority">

                        <option value="Low" <?= ($data['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>
                            Low
                        </option>

                        <option value="Medium" <?= ($data['priority'] ?? '') === 'Medium' ? 'selected' : '' ?>>
                            Medium
                        </option>

                        <option value="High" <?= ($data['priority'] ?? '') === 'High' ? 'selected' : '' ?>>
                            High
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label>Due Date *</label>

                    <input
                        type="date"
                        name="due_date"
                        value="<?= e($data['due_date'] ?? '') ?>"
                        required
                    >

                </div>

            </div>

            <div class="form-group">

                <label>Status *</label>

                <select name="status">

                    <option value="Pending" <?= ($data['status'] ?? '') === 'Pending' ? 'selected' : '' ?>>
                        Pending
                    </option>

                    <option value="Ongoing" <?= ($data['status'] ?? '') === 'Ongoing' ? 'selected' : '' ?>>
                        Ongoing
                    </option>

                    <option value="Completed" <?= ($data['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>
                        Completed
                    </option>

                </select>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Update Task
                </button>

                <a href="../index.php" class="btn btn-secondary">
                    Cancel
                </a>
            </div>

        </form>

    </div>

</div>

</body>
</html>