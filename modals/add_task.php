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

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireValidCsrfToken();
    $subject_id = null;
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $priority = (string) ($_POST['priority'] ?? '');
    $due_date = (string) ($_POST['due_date'] ?? '');
    $status = (string) ($_POST['status'] ?? '');

    $date = DateTime::createFromFormat('!Y-m-d', $due_date);
    if ($title === '' || mb_strlen($title) > 150 || !$date || $date->format('Y-m-d') !== $due_date
        || !in_array($priority, ['Low', 'Medium', 'High'], true)
        || !in_array($status, ['Pending', 'Ongoing', 'Completed'], true)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $task->addTask(
                (int) $currentUser['id'],
                isset($currentUser['student_id']) ? (int) $currentUser['student_id'] : null,
                $subject_id,
                $title,
                $description,
                $priority,
                $due_date,
                $status
            );
            $_SESSION['success'] = 'Task created successfully.';
            header("Location: ../index.php");
            exit;
        } catch (Throwable $exception) {
            error_log('TaskEase task creation failed: ' . $exception->getMessage());
            $error = 'Unable to save the task right now. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Task - TaskEase</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <div class="logo">TaskEase</div>
            <p class="subtitle">Add New Task</p>
        </div>

        <a href="../index.php" class="btn btn-secondary">
            ← Dashboard
        </a>
    </div>

    <div class="form-card">

        <h2>Add New Task</h2>

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
                    value="<?= e($_POST['title'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description"><?= e($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">

                <div class="form-group">
                    <label>Priority *</label>

                    <select name="priority">
                        <option value="Low" <?= ($_POST['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= ($_POST['priority'] ?? 'Medium') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= ($_POST['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Due Date *</label>

                    <input
                        type="date"
                        name="due_date"
                        value="<?= e($_POST['due_date'] ?? '') ?>"
                        required
                    >
                </div>

            </div>

            <div class="form-group">

                <label>Status *</label>

                <select name="status">

                    <option value="Pending" <?= ($_POST['status'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Ongoing" <?= ($_POST['status'] ?? '') === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                    <option value="Completed" <?= ($_POST['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>

                </select>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Save Task
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
