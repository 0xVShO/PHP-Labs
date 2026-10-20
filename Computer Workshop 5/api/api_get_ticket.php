<?php
require_once '../db.php';
require_once '../classes/HelpDesk.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Некоректний ID.']);
    exit;
}

try {
    $manager = new HelpDesk($pdo);
    $ticket = $manager->getTicketById($id);

    if ($ticket) {
        echo json_encode($ticket);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Тікет не знайдено.']);
    }
} catch (Throwable $e) {
    error_log("Get Ticket Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Помилка сервера при отриманні тікета.']);
}