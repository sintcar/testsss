<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/booking_service.php';

require_owner();
$pdo = ensure_db_connection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$quest = $id ? find_quest($pdo, $id) : null;
$error = null;

if (is_post()) {
    $data = [
        ':name' => $_POST['name'],
        ':duration' => (int)$_POST['duration'],
        ':price_9_12' => (int)$_POST['price_9_12'],
        ':price_13_17' => (int)$_POST['price_13_17'],
        ':price_18_21' => (int)$_POST['price_18_21'],
        ':tea_room_price' => (int)$_POST['tea_room_price'],
        ':tea_room_duration' => max(60, (int)$_POST['tea_room_duration']),
        ':is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($id) {
        $data[':id'] = $id;
        $stmt = $pdo->prepare('UPDATE quests SET name=:name, duration=:duration, price_9_12=:price_9_12, price_13_17=:price_13_17, price_18_21=:price_18_21, tea_room_price=:tea_room_price, tea_room_duration=:tea_room_duration, is_active=:is_active, updated_at=NOW() WHERE id=:id');
        $stmt->execute($data);
    } else {
        $stmt = $pdo->prepare('INSERT INTO quests (name, duration, price_9_12, price_13_17, price_18_21, tea_room_price, tea_room_duration, is_active, created_at, updated_at) VALUES (:name, :duration, :price_9_12, :price_13_17, :price_18_21, :tea_room_price, :tea_room_duration, :is_active, NOW(), NOW())');
        $stmt->execute($data);
        $id = (int)$pdo->lastInsertId();
    }
    redirect('/quests.php');
}

render_header($id ? 'Редактирование квеста' : 'Новый квест');
?>
<div class="row">
    <div class="col-lg-8">
        <h1 class="h4 mb-3"><?= $id ? 'Редактирование квеста' : 'Новый квест' ?></h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <form method="post" class="card card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Название</label>
                    <input type="text" name="name" class="form-control" value="<?= h($quest['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Длительность (мин)</label>
                    <input type="number" name="duration" min="30" class="form-control" value="<?= h($quest['duration'] ?? 60) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Цена 09-12</label>
                    <input type="number" name="price_9_12" class="form-control" value="<?= h($quest['price_9_12'] ?? 0) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Цена 13-17</label>
                    <input type="number" name="price_13_17" class="form-control" value="<?= h($quest['price_13_17'] ?? 0) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Цена 18-22</label>
                    <input type="number" name="price_18_21" class="form-control" value="<?= h($quest['price_18_21'] ?? 0) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Чайная комната (цена)</label>
                    <input type="number" name="tea_room_price" class="form-control" value="<?= h($quest['tea_room_price'] ?? 0) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Чайная (длительность, мин)</label>
                    <input type="number" name="tea_room_duration" step="60" min="60" class="form-control" value="<?= h($quest['tea_room_duration'] ?? 60) ?>" required>
                </div>
                <div class="col-12 form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= ($quest['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Активен</label>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-success" type="submit">Сохранить</button>
            </div>
        </form>
    </div>
</div>
<?php
render_footer();
