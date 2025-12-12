<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

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

function get_bookings(PDO $pdo, ?string $from = null, ?string $to = null): array
{
    $sql = 'SELECT b.*, q.name as quest_name, q.price_9_12, q.price_13_17, q.price_18_21, q.tea_room_price, q.tea_room_duration, q.duration FROM bookings b JOIN quests q ON b.quest_id = q.id';
    $params = [];
    if ($from || $to) {
        $sql .= ' WHERE 1=1';
        if ($from) {
            $sql .= ' AND b.start_at >= :from';
            $params[':from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $sql .= ' AND b.start_at <= :to';
            $params[':to'] = $to . ' 23:59:59';
        }
    }
    $sql .= ' ORDER BY b.start_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_booking(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT b.*, q.duration, q.price_9_12, q.price_13_17, q.price_18_21, q.tea_room_price, q.tea_room_duration, q.name as quest_name FROM bookings b JOIN quests q ON b.quest_id = q.id WHERE b.id = ?');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    return $booking ?: null;
}

function update_booking_status(int $booking_id, string $status, ?string $payment_type = null): void
{
    $pdo = get_db();
    $booking = get_booking($pdo, $booking_id);
    if (!$booking) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE bookings SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $booking_id]);
    if ($status === 'completed') {
        $pricing = calculate_pricing($booking, new DateTime($booking['start_at']), (int)$booking['players'], (bool)$booking['tea_room']);
        $pdo->prepare('INSERT INTO payments (booking_id, base_amount, extra_players_amount, tea_room_amount, total_amount, payment_type, paid_at, created_at) VALUES (:booking_id, :base, :extra, :tea, :total, :payment_type, NOW(), NOW()) ON DUPLICATE KEY UPDATE base_amount = VALUES(base_amount), extra_players_amount = VALUES(extra_players_amount), tea_room_amount = VALUES(tea_room_amount), total_amount = VALUES(total_amount), payment_type = VALUES(payment_type), paid_at = VALUES(paid_at)')
            ->execute([
                ':booking_id' => $booking_id,
                ':base' => $pricing['base'],
                ':extra' => $pricing['extra'],
                ':tea' => $pricing['tea'],
                ':total' => $pricing['total'],
                ':payment_type' => $payment_type ?: 'cash',
            ]);
    }
}
