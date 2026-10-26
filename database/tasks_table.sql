CREATE TABLE IF NOT EXISTS tasks (
    task_id INT NOT NULL AUTO_INCREMENT,
    student_id INT NULL,
    subject_id INT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    priority ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
    due_date DATE NOT NULL,
    status ENUM('Pending', 'Ongoing', 'Completed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
