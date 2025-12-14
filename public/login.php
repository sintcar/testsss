<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

start_session();

if (is_authenticated()) {
    redirect('/bookings.php');
}

$error = null;
if (is_post()) {
    $password = $_POST['password'] ?? '';
    if (authenticate($password)) {
        redirect('/bookings.php');
    } else {
        $error = 'Неверный пароль. Попробуйте снова.';
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Quest Manager</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-base);
            padding: 28px;
            box-shadow: var(--shadow);
            width: min(420px, 100%);
        }
        .login-card h1 {
            margin: 0 0 12px;
            font-size: 22px;
        }
        .login-card p { color: var(--text-muted); margin: 0 0 18px; }
        .error-box {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fecdd3;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <h1>Вход в систему</h1>
        <p>Введите пароль, чтобы продолжить работу.</p>

        <?php if ($error): ?>
            <div class="error-box"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" for="password">Пароль</label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Войти</button>
        </form>
    </div>
</div>
</body>
</html>
