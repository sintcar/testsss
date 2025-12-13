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
    $sql = 'SELECT b.*, q.name as quest_name, q.price_9_12, q.price_13_17, q.price_18_21, q.tea_room_price, q.tea_room_duration, p.base_amount, p.extra_players_amount, p.tea_room_amount, p.total_amount, p.payment_type, p.paid_at FROM bookings b JOIN quests q ON b.quest_id = q.id LEFT JOIN payments p ON p.booking_id = b.id WHERE (b.status = "completed" OR b.prepayment_paid_at IS NOT NULL)';
    $params = [];
    if ($from) {
        $sql .= ' AND COALESCE(p.paid_at, b.updated_at, b.start_at) >= :from';
        $params[':from'] = $from . ' 00:00:00';
    }
    if ($to) {
        $sql .= ' AND COALESCE(p.paid_at, b.updated_at, b.start_at) <= :to';
        $params[':to'] = $to . ' 23:59:59';
    }
    $sql .= ' ORDER BY COALESCE(p.paid_at, b.updated_at, b.start_at) DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $enriched = [];
    $total = 0;
    $tea_income = 0;
    $prepayment_income = 0;
    $balance_income = 0;
    foreach ($rows as $row) {
        if ($row['total_amount'] === null) {
            $pricing = calculate_pricing($row, new DateTime($row['start_at']), (int)$row['players'], (bool)$row['tea_room']);
            $row['base_amount'] = $pricing['base'];
            $row['extra_players_amount'] = $pricing['extra'];
            $row['tea_room_amount'] = $pricing['tea'];
            $row['total_amount'] = max(0, $pricing['total'] - (int)$row['prepayment_amount']);
        }
        $row['payment_type'] = $row['payment_type'] ?? '-';
        $row['paid_at'] = $row['paid_at'] ?? $row['updated_at'] ?? $row['start_at'];
        $row['balance_amount'] = max(0, (int)$row['total_amount']);
        $row['prepayment_amount'] = (int)$row['prepayment_amount'];
        $row['total_received'] = $row['balance_amount'] + $row['prepayment_amount'];
        $total += $row['total_received'];
        $prepayment_income += $row['prepayment_amount'];
        $balance_income += $row['balance_amount'];
        $tea_income += $row['tea_room_amount'];
        $enriched[] = $row;
    }

    $count = count($enriched);
    $quests_income = $total - $tea_income;
    return [
        'total' => $total,
        'count' => $count,
        'average' => $count ? $total / $count : 0,
        'quests_income' => $quests_income,
        'tea_income' => $tea_income,
        'prepayment_income' => $prepayment_income,
        'balance_income' => $balance_income,
        'items' => $enriched,
    ];
}
