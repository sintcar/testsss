<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
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
                            <div class="day-booking-row status-<?= h($booking['status']) ?>"
                                 data-booking-id="<?= h($booking['id']) ?>"
                                 data-start="<?= h($booking['start_at']) ?>"
                                 data-end="<?= h($booking['end_at']) ?>"
                                 data-players="<?= h($booking['players']) ?>"
                                 data-status="<?= h($booking['status']) ?>"
                                 data-prepayment="<?= h($booking['prepayment_amount'] ?? 0) ?>"
                                 data-comment="<?= h($booking['comment'] ?? '') ?>"
                                 data-client="<?= h($booking['client_name']) ?>"
                                 data-quest="<?= h($booking['quest_name'] ?? 'Квест') ?>">
                                <div class="day-booking-main">
                                    <div class="fw-bold booking-time"><?= h(date('H:i', strtotime($booking['start_at']))) ?> - <?= h(date('H:i', strtotime($booking['end_at']))) ?></div>
                                    <div class="booking-quest"><?= h($booking['quest_name'] ?? 'Квест') ?></div>
                                    <div class="small booking-client"><span class="booking-client-name"><?= h($booking['client_name']) ?></span> — <span class="booking-status-label"><?= h($booking['status']) ?></span></div>
                                </div>
                                <div class="day-booking-actions">
                                    <button type="button" class="btn-icon" data-action="edit" title="Изменить">✏️</button>
                                    <button type="button" class="btn-icon text-danger" data-action="delete" title="Удалить">🗑</button>
                                </div>
                                <div class="day-booking-edit" hidden></div>
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
        let activeEditRow = null;

        const formatDate = (value) => {
            const date = new Date(value);
            return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
        };

        const parseDateTime = (value) => new Date((value || '').replace(' ', 'T'));
        const formatTimeRange = (start, end) => {
            const startDate = parseDateTime(start);
            const endDate = parseDateTime(end);
            return `${startDate.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })} - ${endDate.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}`;
        };

        const statuses = ['new', 'confirmed', 'completed', 'canceled', 'no_show'];

        const closeActiveEdit = () => {
            if (!activeEditRow) return;
            const editBlock = activeEditRow.querySelector('.day-booking-edit');
            if (editBlock) {
                editBlock.hidden = true;
                editBlock.innerHTML = '';
                activeEditRow.classList.remove('editing');
            }
            activeEditRow = null;
        };

        const updateStatusClass = (row, newStatus) => {
            statuses.forEach((st) => row.classList.remove(`status-${st}`));
            row.classList.add(`status-${newStatus}`);
        };

        const buildEditForm = (row) => {
            const editBlock = row.querySelector('.day-booking-edit');
            const startValue = row.dataset.start;
            const startTime = parseDateTime(startValue).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
            const players = row.dataset.players || '2';
            const prepayment = row.dataset.prepayment || '0';
            const comment = row.dataset.comment || '';
            const status = row.dataset.status || 'new';

            const form = document.createElement('form');
            form.className = 'quick-edit-form';
            form.innerHTML = `
                <div class="form-grid">
                    <label>
                        <span>Время начала</span>
                        <input type="time" name="start_time" required value="${startTime}" />
                    </label>
                    <label>
                        <span>Игроки</span>
                        <input type="number" name="players" min="1" value="${players}" />
                    </label>
                    <label>
                        <span>Статус</span>
                        <select name="status">
                            ${statuses.map((st) => `<option value="${st}" ${st === status ? 'selected' : ''}>${st}</option>`).join('')}
                        </select>
                    </label>
                    <label>
                        <span>Предоплата</span>
                        <input type="number" name="prepayment_amount" min="0" value="${prepayment}" />
                    </label>
                </div>
                <label class="mt-2">
                    <span>Комментарий</span>
                    <textarea name="comment" rows="2" placeholder="Краткая заметка">${comment}</textarea>
                </label>
                <div class="edit-actions">
                    <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cancel>Отмена</button>
                    <span class="form-error" aria-live="polite"></span>
                </div>
            `;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const errorBox = form.querySelector('.form-error');
                errorBox.textContent = '';
                const payload = {
                    booking_id: row.dataset.bookingId,
                    start_time: form.start_time.value,
                    players: form.players.value,
                    status: form.status.value,
                    prepayment_amount: form.prepayment_amount.value,
                    comment: form.comment.value.trim(),
                };
                try {
                    const response = await fetch('/booking_quick_update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json();
                    if (!data.success) {
                        errorBox.textContent = data.message || 'Ошибка сохранения';
                        return;
                    }
                    const updated = data.booking;
                    row.dataset.start = updated.start_at;
                    row.dataset.end = updated.end_at;
                    row.dataset.players = updated.players;
                    row.dataset.status = updated.status;
                    row.dataset.prepayment = updated.prepayment_amount;
                    row.dataset.comment = updated.comment || '';
                    row.querySelector('.booking-time').textContent = formatTimeRange(updated.start_at, updated.end_at);
                    row.querySelector('.booking-status-label').textContent = updated.status;
                    updateStatusClass(row, updated.status);
                    closeActiveEdit();
                } catch (err) {
                    errorBox.textContent = 'Ошибка сети';
                }
            });

            form.querySelector('[data-cancel]')?.addEventListener('click', () => {
                closeActiveEdit();
            });

            editBlock.innerHTML = '';
            editBlock.appendChild(form);
            editBlock.hidden = false;
            row.classList.add('editing');
            activeEditRow = row;
        };

        const attachActions = (row) => {
            row.querySelector('[data-action="edit"]')?.addEventListener('click', () => {
                if (activeEditRow && activeEditRow !== row) {
                    closeActiveEdit();
                }
                buildEditForm(row);
            });

            row.querySelector('[data-action="delete"]')?.addEventListener('click', async () => {
                if (!confirm('Удалить бронирование?')) return;
                try {
                    const response = await fetch('/booking_delete.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ booking_id: row.dataset.bookingId }),
                    });
                    const data = await response.json();
                    if (data.success) {
                        row.remove();
                        if (!listContainer.querySelector('.day-booking-row')) {
                            listContainer.innerHTML = '<div class="text-muted">На выбранную дату бронирований нет</div>';
                        }
                    }
                } catch (err) {
                    // silent
                }
            });
        };

        const createRow = (item) => {
            const row = document.createElement('div');
            row.className = `day-booking-row status-${item.status}`;
            row.dataset.bookingId = item.id;
            row.dataset.start = item.start_at;
            row.dataset.end = item.end_at;
            row.dataset.players = item.players ?? 0;
            row.dataset.status = item.status;
            row.dataset.prepayment = item.prepayment_amount ?? 0;
            row.dataset.comment = item.comment ?? '';
            row.dataset.client = item.client_name ?? '';
            row.dataset.quest = item.quest_name ?? '';

            const main = document.createElement('div');
            main.className = 'day-booking-main';

            const time = document.createElement('div');
            time.className = 'fw-bold booking-time';
            time.textContent = formatTimeRange(item.start_at, item.end_at);

            const quest = document.createElement('div');
            quest.className = 'booking-quest';
            quest.textContent = item.quest_name;

            const client = document.createElement('div');
            client.className = 'small booking-client';
            const clientName = document.createElement('span');
            clientName.className = 'booking-client-name';
            clientName.textContent = item.client_name;
            const status = document.createElement('span');
            status.className = 'booking-status-label';
            status.textContent = item.status;
            client.append(clientName, document.createTextNode(' — '), status);

            main.append(time, quest, client);

            const actions = document.createElement('div');
            actions.className = 'day-booking-actions';
            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn-icon';
            editBtn.dataset.action = 'edit';
            editBtn.title = 'Изменить';
            editBtn.textContent = '✏️';
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn-icon text-danger';
            deleteBtn.dataset.action = 'delete';
            deleteBtn.title = 'Удалить';
            deleteBtn.textContent = '🗑';
            actions.append(editBtn, deleteBtn);

            const edit = document.createElement('div');
            edit.className = 'day-booking-edit';
            edit.hidden = true;

            row.append(main, actions, edit);
            attachActions(row);
            return row;
        };

        const renderItems = (items) => {
            closeActiveEdit();
            if (!items.length) {
                listContainer.innerHTML = '<div class="text-muted">На выбранную дату бронирований нет</div>';
                return;
            }
            listContainer.innerHTML = '';
            items.forEach((item) => {
                const row = createRow(item);
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

        document.querySelectorAll('.day-booking-row').forEach((row) => attachActions(row));
    });
</script>
<?php
render_footer();
