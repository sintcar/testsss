<?php
require_once __DIR__ . '/helpers.php';

function authenticate(string $pin): bool
{
    // Авторизация отключена. Сохраняем маркер гостевого доступа для единообразия.
    start_session();
    $_SESSION['user'] = [
        'id' => null,
        'email' => 'Без авторизации',
        'role' => 'owner',
    ];

    return true;
}

function require_login(): void
{
    // Авторизация отключена.
}

function logout(): void
{
    start_session();
    session_destroy();
}
