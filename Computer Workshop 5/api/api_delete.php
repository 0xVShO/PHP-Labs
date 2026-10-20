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

if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некоректний ID тікета.']);
    exit;
}

try {
    $manager = new HelpDesk($pdo);
    $manager->deleteTicket($ticketId);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log("Delete Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Помилка сервера під час видалення.']);
}