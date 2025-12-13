<?php
require_once __DIR__ . '/helpers.php';

const ACCESS_PIN = '232526';

function authenticate(string $pin): bool
{
    if ($pin === ACCESS_PIN) {
        start_session();
        $_SESSION['user'] = [
            'id' => null,
            'email' => 'PIN-доступ',
            'role' => 'owner',
        ];
        return true;
    }

    return false;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('/login.php');
    }
}

function logout(): void
{
    start_session();
    session_destroy();
}
