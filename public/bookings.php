<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/booking_service.php';

$pdo = ensure_db_connection();

if (is_post() && isset($_POST['booking_id'], $_POST['status'])) {
    $status = $_POST['status'];
    $payment_type = $_POST['payment_type'] ?? null;
    $prepayment = isset($_POST['prepayment_amount']) ? (int)$_POST['prepayment_amount'] : null;
    update_booking_status((int)$_POST['booking_id'], $status, $payment_type, $prepayment);
    flash('success', 'Данные бронирования обновлены');
    redirect('/bookings.php');
}

$today = (new DateTime())->format('Y-m-d');
$monthParam = $_GET['month'] ?? date('Y-m');
$monthDate = DateTime::createFromFormat('Y-m', $monthParam) ?: new DateTime('first day of this month');
$monthStart = (clone $monthDate)->modify('first day of this month');
$monthEnd = (clone $monthStart)->modify('last day of this month');
$activeDate = $_GET['date'] ?? $today;

$monthBookings = get_bookings($pdo, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'), 'asc', 'all');
$bookingsByDay = [];
foreach ($monthBookings as $booking) {
    $dayKey = (new DateTime($booking['start_at']))->format('Y-m-d');
    $bookingsByDay[$dayKey][] = $booking;
}

$dayBookings = get_bookings_by_date($pdo, $activeDate);

render_header('Бронирования');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Бронирования</h1>
    <a class="btn btn-primary" href="/booking_create.php">Новое бронирование</a>
</div>
<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>

<?php
$prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');
$daysInMonth = (int)$monthStart->format('t');
$weekdayOffset = ((int)$monthStart->format('N')) - 1;
?>
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="btn-group" role="group">
                <a class="btn btn-outline-secondary" href="?month=<?= h($prevMonth) ?>">&lt; <?= h($prevMonth) ?></a>
                <span class="btn btn-outline-secondary disabled"><?= h($monthStart->format('F Y')) ?></span>
                <a class="btn btn-outline-secondary" href="?month=<?= h($nextMonth) ?>"><?= h($nextMonth) ?> &gt;</a>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="badge-legend"><span class="status-dot status-new"></span><small>Новый</small></span>
                <span class="badge-legend"><span class="status-dot status-confirmed"></span><small>Подтверждён</small></span>
                <span class="badge-legend"><span class="status-dot status-completed"></span><small>Проведён</small></span>
                <span class="badge-legend"><span class="status-dot status-canceled"></span><small>Отменён</small></span>
                <span class="badge-legend"><span class="status-dot status-no_show"></span><small>Неявка</small></span>
            </div>
        </div>
        <div class="calendar-grid" data-active-date="<?= h($activeDate) ?>">
            <?php for ($i = 0; $i < $weekdayOffset; $i++): ?>
                <div class="day-tile empty"></div>
            <?php endfor; ?>
            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                $dateValue = $monthStart->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                $dayItems = $bookingsByDay[$dateValue] ?? [];
                $statuses = array_map(fn($b) => $b['status'], $dayItems);
                $statusCounts = array_count_values($statuses);
                arsort($statusCounts);
                $dominantStatus = count($statusCounts) === 1 ? array_key_first($statusCounts) : null;
                $isMixed = count($statusCounts) > 1;
                $activeClass = $dateValue === $activeDate ? 'active-day' : '';
                ?>
                <button type="button"
                        class="day-tile <?= $dominantStatus ? 'status-' . h($dominantStatus) : '' ?> <?= $isMixed ? 'mixed' : '' ?> <?= $activeClass ?>"
                        data-date="<?= h($dateValue) ?>">
                    <div class="day-number"><?= $day ?></div>
                    <?php if ($dayItems): ?>
                        <span class="day-count"><?= count($dayItems) ?></span>
                    <?php endif; ?>
                    <?php if ($isMixed): ?>
                        <div class="status-markers">
                            <?php foreach (array_keys($statusCounts) as $status): ?>
                                <span class="status-dot status-<?= h($status) ?>"></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </button>
            <?php endfor; ?>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Список квестов за день</h2>
                    <span class="badge bg-light text-dark" id="selectedDateLabel"></span>
                </div>
                <div id="dayBookings" class="day-bookings-list">
                    <?php if (empty($dayBookings)): ?>
                        <div class="text-muted">На выбранную дату бронирований нет</div>
                    <?php else: ?>
                        <?php foreach ($dayBookings as $booking): ?>
                            <div class="day-booking-row status-<?= h($booking['status']) ?>">
                                <div class="fw-bold"><?= h(date('H:i', strtotime($booking['start_at']))) ?> - <?= h(date('H:i', strtotime($booking['end_at']))) ?></div>
                                <div><?= h($booking['quest_name'] ?? 'Квест') ?></div>
                                <div class="small"><?= h($booking['client_name']) ?> — <?= h($booking['status']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Быстрое изменение брони</h2>
                <p class="text-muted small">Выберите нужную строку в таблице ниже, чтобы обновить статус и предоплату.</p>
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Клиент</th>
                            <th>Статус</th>
                            <th>Предоплата</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($monthBookings)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Брони пока нет</td></tr>
                        <?php else: ?>
                            <?php foreach ($monthBookings as $booking): ?>
                                <?php $prepaymentValue = ($booking['status'] === 'confirmed' && (int)($booking['prepayment_amount'] ?? 0) === 0) ? 1000 : ($booking['prepayment_amount'] ?? 0); ?>
                                <tr class="status-row status-<?= h($booking['status']) ?>">
                                    <td><?= h(date('d.m H:i', strtotime($booking['start_at']))) ?></td>
                                    <td><?= h($booking['client_name']) ?><br><small><?= h($booking['quest_name']) ?></small></td>
                                    <td><span class="badge status-badge status-<?= h($booking['status']) ?>"><?= h($booking['status']) ?></span></td>
                                    <td>
                                        <form method="post" class="row g-1 align-items-center">
                                            <input type="hidden" name="booking_id" value="<?= h($booking['id']) ?>">
                                            <div class="col-12">
                                                <input type="number" name="prepayment_amount" class="form-control form-control-sm" value="<?= h($prepaymentValue) ?>" min="0">
                                            </div>
                                            <div class="col-12">
                                                <select name="status" class="form-select form-select-sm">
                                                    <?php foreach (['new','confirmed','completed'] as $status): ?>
                                                        <option value="<?= $status ?>" <?= $booking['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <select name="payment_type" class="form-select form-select-sm">
                                                    <option value="cash">Наличные</option>
                                                    <option value="card">Карта</option>
                                                    <option value="transfer">Перевод</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <button class="btn btn-sm btn-outline-primary w-100" type="submit">Сохранить</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const calendar = document.querySelector('.calendar-grid');
        const dateLabel = document.getElementById('selectedDateLabel');
        const listContainer = document.getElementById('dayBookings');

        const formatDate = (value) => {
            const date = new Date(value);
            return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
        };

        const renderItems = (items) => {
            if (!items.length) {
                listContainer.innerHTML = '<div class="text-muted">На выбранную дату бронирований нет</div>';
                return;
            }
            listContainer.innerHTML = '';
            items.forEach((item) => {
                const row = document.createElement('div');
                row.className = `day-booking-row status-${item.status}`;
                const start = new Date(item.start_at);
                const end = new Date(item.end_at);
                row.innerHTML = `<div class="fw-bold">${start.toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'})} - ${end.toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'})}</div>
                    <div>${item.quest_name}</div>
                    <div class="small">${item.client_name} — ${item.status}</div>`;
                listContainer.appendChild(row);
            });
        };

        const setActiveDate = (date) => {
            calendar.dataset.activeDate = date;
            dateLabel.textContent = formatDate(date);
            document.querySelectorAll('.day-tile').forEach(btn => btn.classList.remove('active-day'));
            const activeBtn = document.querySelector(`.day-tile[data-date="${date}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active-day');
            }
        };

        const loadDay = async (date) => {
            setActiveDate(date);
            try {
                const response = await fetch(`/day_bookings.php?date=${encodeURIComponent(date)}`);
                const data = await response.json();
                if (!data.success) {
                    return;
                }
                renderItems(data.items);
            } catch (e) {
                console.error(e);
            }
        };

        const initialDate = calendar.dataset.activeDate;
        setActiveDate(initialDate);

        document.querySelectorAll('.day-tile').forEach((btn) => {
            if (btn.classList.contains('empty')) {
                return;
            }
            btn.addEventListener('click', () => loadDay(btn.dataset.date));
        });
    });
</script>
<?php
render_footer();
