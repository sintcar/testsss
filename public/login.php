<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

start_session();
if (current_user()) {
    redirect('/index.php');
}
$error = flash('error');
if (is_post()) {
    $pin = trim($_POST['pin'] ?? '');
    if (authenticate($pin)) {
        redirect('/index.php');
    }

    flash('error', 'Неверный пин-код');
    redirect('/login.php');
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width: 420px;">
    <div class="card shadow-sm">
        <div class="card-body">
            <h1 class="h4 mb-3 text-center">Вход</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Пин-код</label>
                    <input type="password" name="pin" class="form-control" inputmode="numeric" pattern="\d*" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Войти</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
