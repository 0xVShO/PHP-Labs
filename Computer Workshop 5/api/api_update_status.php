<?php
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не дозволено.']);
    exit;
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

if ($ticketId <= 0 || !in_array($newStatus, ['open', 'in_progress', 'closed'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некоректні дані.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE tickets SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $newStatus, ':id' => $ticketId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}