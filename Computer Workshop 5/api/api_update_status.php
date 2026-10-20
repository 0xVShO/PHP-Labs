<?php
require_once '../db.php';
require_once '../classes/HelpDesk.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не дозволено.']);
    exit;
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

if ($ticketId <= 0 || !is_string($newStatus) || !in_array($newStatus, ['open', 'in_progress', 'closed'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некоректні дані.']);
    exit;
}

try {
    $manager = new HelpDesk($pdo);
    $manager->updateStatus($ticketId, $newStatus);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log("Update Status Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Помилка сервера.']);
}