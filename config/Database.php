<?php

class Database
{
    public function connect(): PDO
    {
        $conn = $this->tryConnect();
        if ($conn === null) {
            http_response_code(500);
            exit('TaskEase could not connect to its database. Check the application configuration.');
        }

        return $conn;
    }

    public function tryConnect(): ?PDO
    {
        try {
            $conn = new PDO(
                'mysql:host=' . (getenv('TASK_EASE_DB_HOST') ?: 'localhost')
                    . ';port=' . (getenv('TASK_EASE_DB_PORT') ?: '3306')
                    . ';dbname=' . (getenv('TASK_EASE_DB_NAME') ?: 'taskease')
                    . ';charset=utf8mb4',
                getenv('TASK_EASE_DB_USER') !== false ? getenv('TASK_EASE_DB_USER') : 'root',
                getenv('TASK_EASE_DB_PASSWORD') !== false ? getenv('TASK_EASE_DB_PASSWORD') : ''
            );

            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            return $conn;
        } catch (PDOException $error) {
            error_log('TaskEase database connection failed: ' . $error->getMessage());
            return null;
        }
    }
}
