<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/booking_service.php';

require_login();
$pdo = get_db();

if (is_post() && isset($_POST['booking_id'], $_POST['status'])) {
    $status = $_POST['status'];
    $payment_type = $_POST['payment_type'] ?? null;
    update_booking_status((int)$_POST['booking_id'], $status, $payment_type);
    flash('success', 'Статус обновлён');
    redirect('/public/bookings.php');
}

$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$bookings = get_bookings($pdo, $from ?: null, $to ?: null);

render_header('Бронирования');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4">Бронирования</h1>
    <a class="btn btn-primary" href="/public/booking_create.php">Новое бронирование</a>
</div>
<form class="row g-2 mb-3" method="get">
    <div class="col-auto">
        <input type="date" class="form-control" name="from" value="<?= h($from) ?>" placeholder="С">
    </div>
    <div class="col-auto">
        <input type="date" class="form-control" name="to" value="<?= h($to) ?>" placeholder="По">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Фильтр</button>
    </div>
</form>
<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead>
        <tr>
            <th>Дата</th>
            <th>Квест</th>
            <th>Клиент</th>
            <th>Игроки</th>
            <th>Статус</th>
            <th>Чайная</th>
            <th>Комментарий</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $booking): ?>
            <tr>
                <td>
                    <?= h(date('d.m.Y H:i', strtotime($booking['start_at']))) ?> - <?= h(date('H:i', strtotime($booking['end_at']))) ?><br>
                    <small>Создано: <?= h($booking['created_at']) ?></small>
                </td>
                <td><?= h($booking['quest_name']) ?></td>
                <td><?= h($booking['client_name']) ?><br><small><?= h($booking['phone']) ?></small></td>
                <td><?= h($booking['players']) ?> (<?= h($booking['age_info']) ?>)</td>
                <td><span class="badge bg-secondary"><?= h($booking['status']) ?></span></td>
                <td>
                    <?php if ($booking['tea_room']): ?>
                        <?= h(date('H:i', strtotime($booking['tea_start_at']))) ?> - <?= h(date('H:i', strtotime($booking['tea_end_at']))) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?= nl2br(h($booking['comment'])) ?></td>
                <td>
                    <form method="post" class="d-flex flex-column gap-1">
                        <input type="hidden" name="booking_id" value="<?= h($booking['id']) ?>">
                        <select name="status" class="form-select form-select-sm">
                            <?php foreach (['new','confirmed','completed','canceled','no_show'] as $status): ?>
                                <option value="<?= $status ?>" <?= $booking['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="payment_type" class="form-select form-select-sm">
                            <option value="cash">Наличные</option>
                            <option value="card">Карта</option>
                            <option value="transfer">Перевод</option>
                        </select>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Обновить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
render_footer();
