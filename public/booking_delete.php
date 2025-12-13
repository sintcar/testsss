<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/booking_service.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод']);
    exit;
}

$user = current_user();
if (!in_array($user['role'] ?? 'guest', ['owner', 'admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Нет прав для удаления брони']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    $payload = $_POST;
}

$bookingId = isset($payload['booking_id']) ? (int)$payload['booking_id'] : 0;
if ($bookingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Некорректный идентификатор брони']);
    exit;
}

$booking = get_booking(get_db(), $bookingId);
if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Бронь не найдена']);
    exit;
}

echo json_encode(['success' => delete_booking($bookingId)]);
