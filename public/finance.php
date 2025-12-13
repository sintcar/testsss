<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/finance_service.php';

$pdo = ensure_db_connection();

$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$mode = $_GET['mode'] ?? 'planned';

if ($mode === 'received') {
    $data = received_finance($pdo, $from, $to);
} else {
    $mode = 'planned';
    $data = planned_finance($pdo, $from, $to);
}

render_header('Финансы');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4">Финансы</h1>
</div>
<form class="row g-2 mb-3" method="get">
    <div class="col-auto">
        <select name="mode" class="form-select">
            <option value="planned" <?= $mode === 'planned' ? 'selected' : '' ?>>Планируется</option>
            <option value="received" <?= $mode === 'received' ? 'selected' : '' ?>>Получено</option>
        </select>
    </div>
    <div class="col-auto"><input type="date" name="from" class="form-control" value="<?= h($from) ?>" placeholder="С"></div>
    <div class="col-auto"><input type="date" name="to" class="form-control" value="<?= h($to) ?>" placeholder="По"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Показать</button></div>
</form>
<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>Сумма:</strong> <?= number_format($data['total'], 0, '.', ' ') ?>₽</div>
            <div class="col-md-3"><strong>Квестов:</strong> <?= h($data['count']) ?></div>
            <div class="col-md-3"><strong>Средний чек:</strong> <?= number_format($data['average'], 0, '.', ' ') ?>₽</div>
            <div class="col-md-3"><strong>Чайная:</strong> <?= number_format($data['tea_income'], 0, '.', ' ') ?>₽</div>
        </div>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead>
        <tr>
            <th>Дата</th>
            <th>Квест</th>
            <th>Клиент</th>
            <th>Сумма</th>
            <th>Чайная</th>
            <th>Тип оплаты</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data['items'] as $item): ?>
            <?php $sum = $mode === 'received' ? $item['total_amount'] : calculate_pricing($item, new DateTime($item['start_at']), (int)$item['players'], (bool)$item['tea_room'])['total']; ?>
            <?php $tea = $mode === 'received' ? $item['tea_room_amount'] : calculate_pricing($item, new DateTime($item['start_at']), (int)$item['players'], (bool)$item['tea_room'])['tea']; ?>
            <tr>
                <td><?= h($mode === 'received' ? $item['paid_at'] : $item['start_at']) ?></td>
                <td><?= h($item['quest_name']) ?></td>
                <td><?= h($item['client_name']) ?></td>
                <td><?= number_format($sum, 0, '.', ' ') ?>₽</td>
                <td><?= number_format($tea, 0, '.', ' ') ?>₽</td>
                <td><?= h($item['payment_type'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
render_footer();
