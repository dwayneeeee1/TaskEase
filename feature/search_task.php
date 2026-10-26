<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../classes/Task.php";

requireLogin('../login.php');

$database = new Database();
$db = $database->connect();

$task = new Task($db);
$currentUser = currentUser();

$keyword = trim($_GET['keyword'] ?? '');

$tasks = [];

if ($keyword !== '') {
    $tasks = $task->searchTasks(
        $keyword,
        (int) $currentUser['id'],
        isset($currentUser['student_id']) ? (int) $currentUser['student_id'] : null
    );
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

    <title>Search Tasks - TaskEase</title>

    <link rel="stylesheet" href="../css/style.css">

</head>

<body>

<div class="container">

    <div class="header">

        <div>

            <div class="logo">TaskEase</div>

            <p class="subtitle">
                Search Results
            </p>

        </div>

        <a href="../index.php" class="btn btn-secondary">
            ← Dashboard
        </a>

    </div>

    <div class="search-box">

        <form action="search_task.php" method="GET">

            <input
                type="text"
                name="keyword"
                value="<?= e($keyword) ?>"
                placeholder="Search tasks..."
                required
            >

            <button type="submit" class="btn btn-primary">
                Search tasks
            </button>

            <a href="search_task.php" class="btn btn-secondary">
                Clear
            </a>

        </form>

    </div>

    <div class="table-card">

        <h2 class="page-title">
            Search Results
        </h2>

        <?php if ($keyword === ''): ?>

            <div class="empty">
                Enter a keyword to search for tasks.
            </div>

        <?php elseif (empty($tasks)): ?>

            <div class="empty">
                <strong>No matching tasks</strong>
                <p>Try a different keyword or search by task status.</p>
                <a href="../index.php" class="btn btn-secondary">Back to dashboard</a>
            </div>

        <?php else: ?>

            <p class="subtitle" style="margin-bottom:20px;">
                Results for:
                <strong><?= e($keyword) ?></strong>
            </p>

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
                                    href="../modals/edit_task.php?id=<?= $item['task_id'] ?>"
                                    class="btn btn-warning"
                                >
                                    Edit
                                </a>

                                <a
                                    href="../modals/delete_task.php?id=<?= $item['task_id'] ?>"
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
