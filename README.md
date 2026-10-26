# TaskEase

## Local XAMPP setup and verification

1. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. Open `http://localhost/phpmyadmin/`.
3. For an existing TaskEase database, select `taskease`, take a backup using
   phpMyAdmin's **Export** tab, then import `database/auth_migration.sql` only if
   the authentication migration has not already been applied. It adds the
   authentication tables/columns and task ownership without deleting existing
   user or task records. This migration contains `ALTER TABLE` statements and
   must not be run a second time.
4. For a brand-new installation only, import `database/users_table.sql` to
   create the `taskease` database and base `users`/`tasks` tables; then select
   `taskease` and import `database/auth_migration.sql` once.
5. If Composer is not installed, install it with the official Composer for Windows
   installer and select `C:\xampp\php\php.exe` as the PHP executable. Then run
   from the TaskEase directory:

   ```powershell
   composer install
   ```

   This installs PHPMailer, used to send password-reset emails. Social login is
   intentionally omitted because no OAuth providers or credentials are configured.
6. Open `http://localhost/TaskEase/` and choose **Sign up**. The project is
   already located under `C:\xampp\htdocs\TaskEase`.
7. Test by registering an account, logging in, creating/editing/deleting tasks,
   searching by title/description/priority/status, and logging out. Open
   `http://localhost/TaskEase/index.php` in a private browser window to confirm
   the dashboard redirects to login when no authenticated session exists.

The PHP CLI used for the current checks is `C:\xampp\php\php.exe`. To syntax
check every PHP file from PowerShell in the project directory:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
    & 'C:\xampp\php\php.exe' -l $_.FullName
}
```

The default local database settings are `localhost:3306`, database `taskease`,
user `root`, and an empty password. To override them, set the
`TASK_EASE_DB_HOST`, `TASK_EASE_DB_PORT`, `TASK_EASE_DB_NAME`,
`TASK_EASE_DB_USER`, and `TASK_EASE_DB_PASSWORD` environment variables for the
Apache process. `.env.example` documents the names and SMTP settings; PHP does
not automatically load that example file.

## Password reset email configuration

Password reset links are only generated and sent after SMTP is configured. Set
these environment variables for Apache (for example in the relevant Apache
virtual-host configuration using `SetEnv`, or as Windows system variables and
then restart Apache):

```apache
SetEnv TASK_EASE_BASE_URL "http://localhost/TaskEase"
SetEnv TASK_EASE_SMTP_HOST "smtp.example.com"
SetEnv TASK_EASE_SMTP_PORT "587"
SetEnv TASK_EASE_SMTP_ENCRYPTION "tls"
SetEnv TASK_EASE_SMTP_USERNAME "your-smtp-user"
SetEnv TASK_EASE_SMTP_PASSWORD "your-smtp-password"
SetEnv TASK_EASE_SMTP_FROM "taskease@example.com"
SetEnv TASK_EASE_SMTP_FROM_NAME "TaskEase"
```

Use your provider's real SMTP endpoint and credentials. Port 587 with STARTTLS
(`tls`) and port 465 with implicit TLS (`ssl`) are supported. Set
`TASK_EASE_BASE_URL` to the public HTTPS URL in production. Do not commit actual
credentials. When SMTP or PHPMailer is missing, the forgot-password page states
that setup is incomplete and does not claim an email was sent.

## Task ownership and existing records

New tasks are assigned to the authenticated user's `users.id` in the new
`tasks.owner_user_id` column. If a user supplies a student ID during registration,
the ID is linked only when that student's existing `student_email` matches the
account email; student profile links are unique. Unclaimed legacy tasks tied to
that student's row remain visible to the linked student. Task listings, search,
edit, and delete scope queries to that authenticated owner. Older tasks with no
student association or owner remain in the database but are not shown to any
account; assign them only after verifying their rightful owner. The migration
does not reinterpret legacy `tasks.student_id` values.

For any legacy unassigned task you have positively matched to an account, use
phpMyAdmin to set its owner explicitly, replacing the example values with the
verified user and task IDs:

```sql
UPDATE tasks
SET owner_user_id = 123
WHERE task_id IN (10, 11)
  AND owner_user_id IS NULL;
```

## Security and deployment

The application starts a hardened PHP session from `includes/auth.php`. It
regenerates the session ID after login, checks the active account and auth
version on protected pages, and clears the session cookie and server-side
session on POST logout. The dashboard and task/search pages require login.

Task creation, updates, and deletion are transactional methods in
`classes/Task.php` (`addTask`, `updateTask`, and `deleteTask`). Each uses the
same PDO connection for all statements, commits on success, and rolls back and
rethrows database errors. Update and delete lock the task row and enforce the
authenticated user's ownership, while preserving access to that user's
unclaimed legacy student tasks. The current schema stores task data in one
`tasks` row and defines no separate dependent task records; if child tables are
added later, their writes/deletes must be included in these transactions.

The application also uses CSRF tokens for POST forms, hashed
remember-me validators and password-reset tokens, account-version invalidation
on password reset, and a 15-minute login-attempt window. Production must use
HTTPS so session and remember cookies receive the `Secure` attribute. Keep PHP
and MySQL error display disabled in production and review PHP's error log for
internal diagnostics.

## Production deployment checklist

- Use a PHP 8+ host with PDO MySQL and a MySQL/MariaDB database; configure
  `TASK_EASE_DB_HOST`, `TASK_EASE_DB_PORT`, `TASK_EASE_DB_NAME`,
  `TASK_EASE_DB_USER`, and `TASK_EASE_DB_PASSWORD` privately in the host.
- Back up the database before applying the one-time authentication migration.
- Upload the application files over an authorized deployment channel. Do not
  upload `.env`, credentials, or production configuration files.
- Install Composer dependencies on the host and configure SMTP only if password
  reset emails are required.
- Configure HTTPS and set `TASK_EASE_BASE_URL` to the actual HTTPS site URL.
- Verify registration/login, session cookies, task ownership, task CRUD, search,
  logout, and unauthenticated dashboard redirects on the deployed site.
