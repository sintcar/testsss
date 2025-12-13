<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/booking_service.php';

header('Content-Type: application/json');

$questId = isset($_GET['quest_id']) ? (int)$_GET['quest_id'] : null;
$date = $_GET['date'] ?? null;
$dateValid = $date ? DateTime::createFromFormat('Y-m-d', $date) : null;

if (!$questId || !$dateValid) {
    echo json_encode(['success' => false, 'message' => 'Некорректные параметры']);
    exit;
}

$pdo = ensure_db_connection();
$slots = getAvailableTimeSlots($pdo, $questId, $date);

echo json_encode([
    'success' => true,
    'slots' => $slots,
]);
