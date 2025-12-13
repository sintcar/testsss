<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/booking_service.php';

header('Content-Type: application/json');
$date = $_GET['date'] ?? null;
$dateObj = $date ? DateTime::createFromFormat('Y-m-d', $date) : null;
if (!$dateObj) {
    echo json_encode(['success' => false, 'message' => 'Некорректная дата']);
    exit;
}
$pdo = ensure_db_connection();
$bookings = get_bookings_by_date($pdo, $dateObj->format('Y-m-d'));
$items = array_map(function (array $booking) {
    return [
        'id' => (int)$booking['id'],
        'start_at' => $booking['start_at'],
        'end_at' => $booking['end_at'],
        'quest_name' => $booking['quest_name'] ?? 'Неизвестный квест',
        'client_name' => $booking['client_name'],
        'status' => $booking['status'],
        'players' => (int)$booking['players'],
        'prepayment_amount' => (int)($booking['prepayment_amount'] ?? 0),
        'comment' => $booking['comment'] ?? '',
    ];
}, $bookings);

echo json_encode([
    'success' => true,
    'date' => $dateObj->format('Y-m-d'),
    'items' => $items,
]);
