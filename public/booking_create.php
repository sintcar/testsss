<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/booking_service.php';

$pdo = ensure_db_connection();
$quests = get_active_quests($pdo);
$message = null;
$error = null;

if (is_post()) {
    $date = $_POST['date'] ?? '';
    $timeSlot = $_POST['time_slot'] ?? '';
    $start = $date && $timeSlot ? DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $timeSlot) : null;
    if (!$start) {
        $error = 'Укажите корректные дату и время';
    }

    $selectedQuestId = (int)($_POST['quest_id'] ?? 0);
    $availableSlots = $date ? getAvailableTimeSlots($pdo, $selectedQuestId, $date) : [];
    if ($date && !$availableSlots) {
        $error = 'На выбранную дату свободного времени нет';
    } elseif (isset($start) && $availableSlots && !in_array($timeSlot, $availableSlots, true)) {
        $error = 'Выбранное время недоступно';
    }

    if (!$error) {
        $data = [
            'quest_id' => $selectedQuestId,
            'start_at' => $start->format('Y-m-d H:i:s'),
            'players' => (int)$_POST['players'],
            'age_info' => $_POST['age_info'],
            'client_name' => $_POST['client_name'],
            'phone' => $_POST['phone'],
            'tea_room' => isset($_POST['tea_room']),
            'status' => $_POST['status'],
            'comment' => $_POST['comment'],
        ];
        $result = create_booking($data);
        if ($result['success']) {
            $pricing = $result['pricing'];
            $message = 'Бронирование создано. Стоимость: ' . $pricing['total'] . '₽';
        } else {
            $error = $result['message'];
        }
    }
}

render_header('Новое бронирование');
?>
<div class="row">
    <div class="col-lg-8">
        <h1 class="h4 mb-3">Новое бронирование</h1>
        <?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <form method="post" class="card card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Квест</label>
                    <select name="quest_id" class="form-select" required>
                        <?php foreach ($quests as $quest): ?>
                            <option value="<?= h($quest['id']) ?>"><?= h($quest['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Статус</label>
                    <select name="status" class="form-select">
                        <option value="new">new</option>
                        <option value="confirmed">confirmed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Дата</label>
                    <input type="date" name="date" id="dateSelect" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Время</label>
                    <select name="time_slot" id="timeSelect" class="form-select" disabled required>
                        <option value="">Выберите время</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Игроки</label>
                    <input type="number" name="players" min="1" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Возраст</label>
                    <input type="text" name="age_info" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Имя клиента</label>
                    <input type="text" name="client_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Телефон</label>
                    <input type="text" name="phone" class="form-control" required>
                </div>
                <div class="col-md-12 form-check">
                    <input class="form-check-input" type="checkbox" id="tea_room" name="tea_room">
                    <label class="form-check-label" for="tea_room">Использовать чайную комнату</label>
                </div>
                <div class="col-12">
                    <label class="form-label">Комментарий</label>
                    <textarea name="comment" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="mt-3">
                <div class="alert alert-warning d-none" id="noSlotsMessage">На выбранную дату свободного времени нет</div>
            </div>
            <div class="mt-3">
                <button class="btn btn-success" type="submit" id="submitButton" disabled>Создать</button>
            </div>
        </form>
    </div>
</div>
<?php
?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const dateInput = document.getElementById('dateSelect');
        const questSelect = document.querySelector('select[name="quest_id"]');
        const timeSelect = document.getElementById('timeSelect');
        const noSlotsMessage = document.getElementById('noSlotsMessage');
        const submitButton = document.getElementById('submitButton');

        const resetTime = () => {
            timeSelect.innerHTML = '<option value="">Выберите время</option>';
            timeSelect.disabled = true;
            submitButton.disabled = true;
        };

        const showNoSlots = (show) => {
            if (show) {
                noSlotsMessage.classList.remove('d-none');
                submitButton.disabled = true;
                timeSelect.disabled = true;
            } else {
                noSlotsMessage.classList.add('d-none');
            }
        };

        const loadSlots = async () => {
            const date = dateInput.value;
            const questId = questSelect.value;

            if (!date) {
                resetTime();
                showNoSlots(false);
                return;
            }

            resetTime();
            showNoSlots(false);

            try {
                const response = await fetch(`/available_slots.php?quest_id=${encodeURIComponent(questId)}&date=${encodeURIComponent(date)}`);
                const data = await response.json();

                if (!data.success) {
                    showNoSlots(true);
                    return;
                }

                if (!data.slots.length) {
                    showNoSlots(true);
                    return;
                }

                data.slots.forEach((slot) => {
                    const option = document.createElement('option');
                    option.value = slot;
                    option.textContent = slot;
                    timeSelect.appendChild(option);
                });

                timeSelect.disabled = false;
                submitButton.disabled = false;
            } catch (e) {
                showNoSlots(true);
            }
        };

        dateInput.addEventListener('change', loadSlots);
        questSelect.addEventListener('change', () => {
            if (dateInput.value) {
                loadSlots();
            }
        });
    });
</script>
<?php
render_footer();
