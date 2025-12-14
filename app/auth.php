<?php
require_once __DIR__ . '/helpers.php';

function authenticate(string $pin): bool
{
    start_session();
    global $config;

    $hash = $config['auth']['password_hash'] ?? null;
    if (!$hash || !password_verify($pin, $hash)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['auth'] = true;
    $_SESSION['user'] = [
        'id' => null,
        'email' => 'Администратор',
        'role' => 'owner',
    ];

    return true;
}

function is_authenticated(): bool
{
    start_session();

    return ($_SESSION['auth'] ?? false) === true;
}

function require_login(): void
{
    if (!is_authenticated()) {
        redirect('/login.php');
    }
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
    session_regenerate_id(true);
}
