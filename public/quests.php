<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/booking_service.php';

require_login();
$pdo = get_db();
$quests = get_quests($pdo);

render_header('Квесты');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4">Квесты</h1>
    <a class="btn btn-primary" href="/public/quest_edit.php">Создать</a>
</div>
<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
        <tr>
            <th>Название</th>
            <th>Длительность</th>
            <th>Цены</th>
            <th>Чайная</th>
            <th>Статус</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($quests as $quest): ?>
            <tr>
                <td><?= h($quest['name']) ?></td>
                <td><?= h($quest['duration']) ?> мин</td>
                <td>
                    09-12: <?= h($quest['price_9_12']) ?>₽<br>
                    13-17: <?= h($quest['price_13_17']) ?>₽<br>
                    18-22: <?= h($quest['price_18_21']) ?>₽
                </td>
                <td>
                    <?= h($quest['tea_room_price']) ?>₽ / <?= h($quest['tea_room_duration']) ?> мин
                </td>
                <td><?= $quest['is_active'] ? 'Активен' : 'Скрыт' ?></td>
                <td><a class="btn btn-sm btn-outline-primary" href="/public/quest_edit.php?id=<?= h($quest['id']) ?>">Редактировать</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
render_footer();
