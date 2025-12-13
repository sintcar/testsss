<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function current_user(): ?array
{
    start_session();

    return $_SESSION['user'] ?? [
        'id' => null,
        'email' => 'Без авторизации',
        'role' => 'owner',
    ];
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function h($value): string
{
    if ($value === null) {
        return '';
    }

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function render_error_page(string $title, string $message): void
{
    render_header($title);
    ?>
    <div class="alert alert-danger"><?= h($message) ?></div>
    <?php
    render_footer();
    exit;
}

function ensure_db_connection(): PDO
{
    try {
        return get_db();
    } catch (Throwable $e) {
        render_error_page('Ошибка базы данных', 'Не удалось подключиться к базе данных. Проверьте настройки подключения и попробуйте снова.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    start_session();
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function render_header(string $title = 'Quest Manager'): void
{
    start_session();
    ?>
    <!doctype html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="/index.php">Quest Manager</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/bookings.php">Бронирования</a></li>
                    <li class="nav-item"><a class="nav-link" href="/booking_create.php">Новое бронирование</a></li>
                    <li class="nav-item"><a class="nav-link" href="/quests.php">Квесты</a></li>
                    <li class="nav-item"><a class="nav-link" href="/finance.php">Финансы</a></li>
                </ul>
                <span class="navbar-text text-white">Доступ без авторизации</span>
            </div>
        </div>
    </nav>
    <div class="container">
    <?php
}

function render_footer(): void
{
    ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

function require_owner(): void
{
    // Авторизация отключена.
}
