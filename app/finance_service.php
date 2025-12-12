<?php
require_once __DIR__ . '/booking_service.php';

function planned_finance(PDO $pdo, ?string $from, ?string $to): array
{
    $bookings = get_bookings($pdo, $from, $to);
    $filtered = array_filter($bookings, fn($b) => in_array($b['status'], ['new', 'confirmed'], true));
    $total = 0;
    $count = 0;
    $tea_income = 0;
    $quests_income = 0;
    foreach ($filtered as $booking) {
        $pricing = calculate_pricing($booking, new DateTime($booking['start_at']), (int)$booking['players'], (bool)$booking['tea_room']);
        $total += $pricing['total'];
        $count++;
        $quests_income += $pricing['base'] + $pricing['extra'];
        $tea_income += $pricing['tea'];
    }
    return [
        'total' => $total,
        'count' => $count,
        'average' => $count ? $total / $count : 0,
        'quests_income' => $quests_income,
        'tea_income' => $tea_income,
        'items' => $filtered,
    ];
}

function received_finance(PDO $pdo, ?string $from, ?string $to): array
{
    $sql = 'SELECT b.*, q.name as quest_name, p.base_amount, p.extra_players_amount, p.tea_room_amount, p.total_amount, p.payment_type, p.paid_at FROM bookings b JOIN quests q ON b.quest_id = q.id JOIN payments p ON p.booking_id = b.id WHERE b.status = "completed"';
    $params = [];
    if ($from) {
        $sql .= ' AND p.paid_at >= :from';
        $params[':from'] = $from . ' 00:00:00';
    }
    if ($to) {
        $sql .= ' AND p.paid_at <= :to';
        $params[':to'] = $to . ' 23:59:59';
    }
    $sql .= ' ORDER BY p.paid_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $total = array_sum(array_column($rows, 'total_amount'));
    $count = count($rows);
    $tea_income = array_sum(array_column($rows, 'tea_room_amount'));
    $quests_income = $total - $tea_income;
    return [
        'total' => $total,
        'count' => $count,
        'average' => $count ? $total / $count : 0,
        'quests_income' => $quests_income,
        'tea_income' => $tea_income,
        'items' => $rows,
    ];
}
