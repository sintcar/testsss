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
    echo json_encode(['success' => false, 'message' => 'Нет прав для изменения брони']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    $payload = $_POST;
}

$result = quick_update_booking($payload ?? []);

echo json_encode($result);
