<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

start_session();
if (current_user()) {
    redirect('/index.php');
}
$error = null;
if (is_post()) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (authenticate($email, $password)) {
        redirect('/index.php');
    } else {
        $error = 'Неверный email или пароль';
    }
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
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Пароль</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Войти</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
