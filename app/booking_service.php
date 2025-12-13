<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

const BOOKING_ACTIVE_STATUSES = ['new', 'confirmed', 'completed'];

function auto_complete_bookings(PDO $pdo): void
{
    $now = (new DateTime())->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare('SELECT id FROM bookings WHERE end_at < :now AND status IN ("new","confirmed")');
    $stmt->execute([':now' => $now]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $bookingId) {
        update_booking_status((int)$bookingId, 'completed');
    }
}

function get_quests(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM quests ORDER BY name');
    return $stmt->fetchAll();
}

function get_active_quests(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM quests WHERE is_active = 1 ORDER BY name');
    return $stmt->fetchAll();
}

function find_quest(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM quests WHERE id = ?');
    $stmt->execute([$id]);
    $quest = $stmt->fetch();
    return $quest ?: null;
}

function time_price(array $quest, DateTime $start): int
{
    $hour = (int)$start->format('H');
    if ($hour >= 9 && $hour <= 12) {
        return (int)$quest['price_9_12'];
    }
    if ($hour >= 13 && $hour <= 17) {
        return (int)$quest['price_13_17'];
    }
    if ($hour >= 18 && $hour <= 21) {
        return (int)$quest['price_18_21'];
    }
    return (int)$quest['price_9_12'];
}

function calculate_pricing(array $quest, DateTime $start, int $players, bool $tea_room): array
{
    $base = time_price($quest, $start);
    $extra_players = $players > 5 ? ($players - 5) * 1000 : 0;
    $tea_amount = $tea_room ? (int)$quest['tea_room_price'] : 0;
    $total = $base + $extra_players + $tea_amount;
    return [
        'base' => $base,
        'extra' => $extra_players,
        'tea' => $tea_amount,
        'total' => $total,
    ];
}

function compute_times(array $quest, DateTime $start, bool $tea_room): array
{
    $end = clone $start;
    $end->modify('+' . (int)$quest['duration'] . ' minutes');
    $end->modify('+15 minutes');
    $teaStart = null;
    $teaEnd = null;
    if ($tea_room) {
        $teaStart = clone $end;
        $teaEnd = clone $teaStart;
        $teaEnd->modify('+' . max(60, (int)$quest['tea_room_duration']) . ' minutes');
    }
    return [$end, $teaStart, $teaEnd];
}

function getAvailableTimeSlots(PDO $pdo, int $questId, string $date): array
{
    $quest = find_quest($pdo, $questId);
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);

    if (!$quest || !$quest['is_active'] || !$dateObj) {
        return [];
    }

    $durationMinutes = (int)$quest['duration'] + 15; // includes technical interval

    $stmt = $pdo->prepare('SELECT start_at, end_at FROM bookings WHERE quest_id = :quest_id AND DATE(start_at) = :date AND status IN ("new", "confirmed", "completed")');
    $stmt->execute([
        ':quest_id' => $questId,
        ':date' => $date,
    ]);

    $bookings = array_map(function ($row) {
        return [
            'start' => new DateTime($row['start_at']),
            'end' => new DateTime($row['end_at']),
        ];
    }, $stmt->fetchAll());

    $slots = [];
    $current = (clone $dateObj)->setTime(9, 0);
    $endOfDay = (clone $dateObj)->setTime(21, 0);

    while ($current <= $endOfDay) {
        $slotStart = clone $current;
        $slotEnd = (clone $slotStart)->modify('+' . $durationMinutes . ' minutes');

        $hasConflict = false;
        foreach ($bookings as $booking) {
            if ($slotStart < $booking['end'] && $slotEnd > $booking['start']) {
                $hasConflict = true;
                break;
            }
        }

        if (!$hasConflict) {
            $slots[] = $slotStart->format('H:i');
        }

        $current->modify('+15 minutes');
    }

    return $slots;
}

function has_booking_conflict(PDO $pdo, int $quest_id, DateTime $start, DateTime $end, ?int $exclude_id = null): bool
{
    $sql = 'SELECT COUNT(*) FROM bookings WHERE quest_id = :quest_id AND status NOT IN ("canceled","no_show") AND NOT (end_at <= :start OR start_at >= :end)';
    $params = [
        ':quest_id' => $quest_id,
        ':start' => $start->format('Y-m-d H:i:s'),
        ':end' => $end->format('Y-m-d H:i:s'),
    ];
    if ($exclude_id) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $exclude_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function has_tea_conflict(PDO $pdo, DateTime $tea_start, DateTime $tea_end, ?int $exclude_id = null): bool
{
    $sql = 'SELECT COUNT(*) FROM bookings WHERE tea_room = 1 AND status NOT IN ("canceled","no_show") AND NOT (tea_end_at <= :start OR tea_start_at >= :end)';
    $params = [
        ':start' => $tea_start->format('Y-m-d H:i:s'),
        ':end' => $tea_end->format('Y-m-d H:i:s'),
    ];
    if ($exclude_id) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $exclude_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function create_booking(array $data): array
{
    $pdo = get_db();
    $quest = find_quest($pdo, (int)$data['quest_id']);
    if (!$quest || !$quest['is_active']) {
        return ['success' => false, 'message' => 'Квест недоступен'];
    }
    $start = new DateTime($data['start_at']);
    [$end, $teaStart, $teaEnd] = compute_times($quest, $start, (bool)($data['tea_room'] ?? false));

    if (has_booking_conflict($pdo, (int)$quest['id'], $start, $end, null)) {
        return ['success' => false, 'message' => 'Пересечение по времени квеста'];
    }
    if ($data['tea_room'] && $teaStart && has_tea_conflict($pdo, $teaStart, $teaEnd, null)) {
        return ['success' => false, 'message' => 'Пересечение чайной комнаты'];
    }
    $pricing = calculate_pricing($quest, $start, (int)$data['players'], (bool)$data['tea_room']);
    $stmt = $pdo->prepare('INSERT INTO bookings (quest_id, start_at, end_at, players, age_info, client_name, phone, tea_room, tea_start_at, tea_end_at, status, comment, created_by, created_at, updated_at) VALUES (:quest_id, :start_at, :end_at, :players, :age_info, :client_name, :phone, :tea_room, :tea_start_at, :tea_end_at, :status, :comment, :created_by, NOW(), NOW())');
    $stmt->execute([
        ':quest_id' => $quest['id'],
        ':start_at' => $start->format('Y-m-d H:i:s'),
        ':end_at' => $end->format('Y-m-d H:i:s'),
        ':players' => (int)$data['players'],
        ':age_info' => $data['age_info'],
        ':client_name' => $data['client_name'],
        ':phone' => $data['phone'],
        ':tea_room' => $data['tea_room'] ? 1 : 0,
        ':tea_start_at' => $teaStart ? $teaStart->format('Y-m-d H:i:s') : null,
        ':tea_end_at' => $teaEnd ? $teaEnd->format('Y-m-d H:i:s') : null,
        ':status' => $data['status'] ?? 'new',
        ':comment' => $data['comment'] ?? '',
        ':created_by' => current_user()['id'] ?? null,
    ]);

    return ['success' => true, 'pricing' => $pricing];
}

function get_bookings(PDO $pdo, ?string $from = null, ?string $to = null, string $sort = 'desc', string $period = 'all'): array
{
    auto_complete_bookings($pdo);

    $allowedSorts = ['asc', 'desc'];
    $sortDirection = in_array(strtolower($sort), $allowedSorts, true) ? strtoupper($sort) : 'DESC';

    $sql = 'SELECT b.*, COALESCE(q.name, "Неизвестный квест") as quest_name, q.price_9_12, q.price_13_17, q.price_18_21, q.tea_room_price, q.tea_room_duration, q.duration FROM bookings b LEFT JOIN quests q ON b.quest_id = q.id';
    $params = [];
    $conditions = [];

    if ($from) {
        $conditions[] = 'b.start_at >= :from';
        $params[':from'] = $from . ' 00:00:00';
    }
    if ($to) {
        $conditions[] = 'b.start_at <= :to';
        $params[':to'] = $to . ' 23:59:59';
    }

    $now = (new DateTime())->format('Y-m-d H:i:s');
    if ($period === 'upcoming') {
        $conditions[] = 'b.start_at >= :now';
        $params[':now'] = $now;
    } elseif ($period === 'past') {
        $conditions[] = 'b.start_at < :now';
        $params[':now'] = $now;
    }

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY b.start_at ' . $sortDirection;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_bookings_by_date(PDO $pdo, string $date): array
{
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj) {
        return [];
    }
    auto_complete_bookings($pdo);
    $stmt = $pdo->prepare('SELECT b.*, q.name as quest_name FROM bookings b LEFT JOIN quests q ON b.quest_id = q.id WHERE DATE(b.start_at) = :date ORDER BY b.start_at ASC');
    $stmt->execute([':date' => $dateObj->format('Y-m-d')]);
    return $stmt->fetchAll();
}

function get_booking(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT b.*, q.duration, q.price_9_12, q.price_13_17, q.price_18_21, q.tea_room_price, q.tea_room_duration, COALESCE(q.name, "Неизвестный квест") as quest_name FROM bookings b LEFT JOIN quests q ON b.quest_id = q.id WHERE b.id = ?');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    return $booking ?: null;
}

function update_booking_status(int $booking_id, string $status, ?string $payment_type = null, ?int $prepayment_amount = null, ?string $prepayment_paid_at = null): void
{
    $pdo = get_db();
    $booking = get_booking($pdo, $booking_id);
    if (!$booking) {
        return;
    }
    $allowed_statuses = ['new', 'confirmed', 'completed', 'canceled', 'no_show'];
    if (!in_array($status, $allowed_statuses, true)) {
        return;
    }

    $prepayment_value = $prepayment_amount !== null ? max(0, $prepayment_amount) : null;
    if ($status === 'confirmed' && $prepayment_value === null) {
        $prepayment_value = 1000;
    }

    $fields = ['status = :status', 'updated_at = NOW()'];
    $params = [
        ':status' => $status,
        ':id' => $booking_id,
    ];

    if ($prepayment_value !== null) {
        $fields[] = 'prepayment_amount = :prepayment_amount';
        $fields[] = 'prepayment_paid_at = :prepayment_paid_at';
        $params[':prepayment_amount'] = $prepayment_value;
        $params[':prepayment_paid_at'] = $prepayment_value > 0 ? ($prepayment_paid_at ?: (new DateTime())->format('Y-m-d H:i:s')) : null;
    }

    $stmt = $pdo->prepare('UPDATE bookings SET ' . implode(', ', $fields) . ' WHERE id = :id');
    $stmt->execute($params);
    $prepayment_used = $prepayment_value ?? (int)$booking['prepayment_amount'];
    if ($status === 'completed') {
        $pricing = calculate_pricing($booking, new DateTime($booking['start_at']), (int)$booking['players'], (bool)$booking['tea_room']);
        $final_amount = max(0, $pricing['total'] - $prepayment_used);
        $pdo->prepare('INSERT INTO payments (booking_id, base_amount, extra_players_amount, tea_room_amount, total_amount, payment_type, paid_at, created_at) VALUES (:booking_id, :base, :extra, :tea, :total, :payment_type, NOW(), NOW()) ON DUPLICATE KEY UPDATE base_amount = VALUES(base_amount), extra_players_amount = VALUES(extra_players_amount), tea_room_amount = VALUES(tea_room_amount), total_amount = VALUES(total_amount), payment_type = VALUES(payment_type), paid_at = VALUES(paid_at)')
            ->execute([
                ':booking_id' => $booking_id,
                ':base' => $pricing['base'],
                ':extra' => $pricing['extra'],
                ':tea' => $pricing['tea'],
                ':total' => $final_amount,
                ':payment_type' => $payment_type ?: 'cash',
            ]);
    }
}
