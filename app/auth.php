<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function authenticate(string $email, string $password): bool
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        start_session();
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        return true;
    }
    return false;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('/public/login.php');
    }
}

function logout(): void
{
    start_session();
    session_destroy();
}
