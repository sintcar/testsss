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
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
    <header class="app-header">
        <div class="app-shell">
            <div class="topbar">
                <a class="brand" href="/index.php">Quest Manager</a>
                <button class="nav-toggle" type="button" aria-label="Меню" data-toggle-nav>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <nav class="topnav" data-nav>
                    <a class="nav-link" href="/bookings.php">Бронирования</a>
                    <a class="nav-link" href="/booking_create.php">Новое бронирование</a>
                    <a class="nav-link" href="/quests.php">Квесты</a>
                    <a class="nav-link" href="/finance.php">Финансы</a>
                </nav>
                <div class="user-box">
                    <span class="small"><?= h(current_user()['email']) ?></span>
                    <a class="btn-ghost" href="/logout.php">Выход</a>
                </div>
            </div>
        </div>
    </header>
    <main class="app-main">
        <div class="app-container">
    <?php
}

function render_footer(): void
{
    ?>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.querySelector('[data-toggle-nav]');
            const nav = document.querySelector('[data-nav]');
            if (toggle && nav) {
                toggle.addEventListener('click', () => {
                    nav.classList.toggle('is-open');
                });
            }
            const current = window.location.pathname;
            nav?.querySelectorAll('.nav-link').forEach(link => {
                if (link.getAttribute('href') === current) {
                    link.classList.add('active');
                }
            });
        });
    </script>
    </body>
    </html>
    <?php
}

function require_owner(): void
{
    // Авторизация отключена.
}
