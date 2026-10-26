<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Task.php';

requireLogin('login.php');

$database = new Database();
$db = $database->connect();

$currentUser = currentUser();
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
$task = new Task($db);
$tasks = $task->getTasks(
    (int) $currentUser['id'],
    isset($currentUser['student_id']) ? (int) $currentUser['student_id'] : null
);

$total = count($tasks);
$pending = 0;
$ongoing = 0;
$completed = 0;

foreach ($tasks as $item) {
    if ($item['status'] === 'Pending') {
        $pending++;
    }

    if ($item['status'] === 'Ongoing') {
        $ongoing++;
    }

    if ($item['status'] === 'Completed') {
        $completed++;
    }
}

function priorityClass($priority)
{
    return strtolower("priority-" . $priority);
}

function statusClass($status)
{
    return strtolower("status-" . $status);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskEase - Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <div class="logo">TaskEase</div>
            <p class="subtitle">Student Task Management System</p>
        </div>

        <div class="top-actions">
            <div class="user-pill">
                <strong><?= e($currentUser['name']) ?></strong>
                <span><?= e($currentUser['email']) ?></span>
            </div>

            <form action="logout.php" method="POST" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <button type="submit" class="btn btn-secondary">Log out</button>
            </form>
            <a href="modals/add_task.php" class="btn btn-primary">
                + Add New Task
            </a>
        </div>
    </div>

    <?php if ($successMessage !== ''): ?>
        <div class="message" role="status"><?= e($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
        <div class="message error" role="alert"><?= e($errorMessage) ?></div>
    <?php endif; ?>

    <div class="search-box">
        <form action="feature/search_task.php" method="GET">
            <input
                type="text"
                name="keyword"
                placeholder="Search tasks by title, description, priority, or status..."
                required
            >
            <button type="submit" class="btn btn-primary">
                Search tasks
            </button>
        </form>
    </div>

    <div class="stats">

        <div class="stat-card">
            <h3>Total Tasks</h3>
            <div class="stat-number">
                <?= $total ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Pending</h3>
            <div class="stat-number">
                <?= $pending ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Ongoing</h3>
            <div class="stat-number">
                <?= $ongoing ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Completed</h3>
            <div class="stat-number">
                <?= $completed ?>
            </div>
        </div>

    </div>

    <div class="table-card">

        <h2 class="page-title">My Tasks</h2>

        <?php if (empty($tasks)): ?>

            <div class="empty">
                <strong>No tasks yet</strong>
                <p>Start organizing your school tasks today.</p>
                <a href="modals/add_task.php" class="btn btn-primary">+ Add New Task</a>
            </div>

        <?php else: ?>

            <table>

                <thead>
                    <tr>
                        <th>Task Title</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($tasks as $item): ?>

                    <tr>

                        <td>
                            <strong><?= e($item['title']) ?></strong>
                        </td>

                        <td>
                            <span class="badge <?= priorityClass($item['priority']) ?>">
                                <?= e($item['priority']) ?>
                            </span>
                        </td>

                        <td>
                            <?= e($item['due_date']) ?>
                        </td>

                        <td>
                            <span class="badge <?= statusClass($item['status']) ?>">
                                <?= e($item['status']) ?>
                            </span>
                        </td>

                        <td>

                            <div class="actions">

                                <a
                                    href="modals/edit_task.php?id=<?= $item['task_id'] ?>"
                                    class="btn btn-warning"
                                >
                                    Edit
                                </a>

                                <a
                                    href="modals/delete_task.php?id=<?= $item['task_id'] ?>"
                                    class="btn btn-danger"
                                >
                                    Delete
                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

</body>
</html>