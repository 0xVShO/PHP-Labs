<?php
require_once '../db.php'; 
require_once '../classes/Ticket.php';
require_once '../classes/UrgentTicket.php';
require_once '../classes/HelpDesk.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не дозволено.']);
    exit;
}

$manager = new HelpDesk($pdo);

$subject = $_POST['subject'] ?? '';
$description = $_POST['description'] ?? '';
$priority = $_POST['priority'] ?? '';
$slaHours = isset($_POST['sla_hours']) ? (int)$_POST['sla_hours'] : 24;

if (!is_string($subject) || !is_string($description) || !is_string($priority)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Некоректний формат даних.']]);
    exit;
}

$subject = trim($subject);
$description = trim($description);

$errors = [];

if (mb_strlen($subject) < 3) {
    $errors[] = "Назва має містити не менше 3 символів.";
}

if (mb_strlen($description) < 15 || mb_strlen($description) > 500) {
    $errors[] = "Опис має бути від 15 до 500 символів.";
}

if (!in_array($priority, ['high', 'medium', 'low'])) {
    $errors[] = "Недопустимий рівень пріоритету.";
}

if ($priority === 'high' && ($slaHours < 1 || $slaHours > 72)) {
    $errors[] = "SLA має бути в межах від 1 до 72 годин.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    if ($priority === 'high') {
        $ticket = new UrgentTicket($subject, $priority, 'open', $description, $slaHours, 'now');
    } else {
        $ticket = new Ticket($subject, $priority, 'open', $description, 'now');
    }

    $manager->addTicket($ticket);

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Тікет успішно створено!']);
} catch (Throwable $e) {
    error_log("DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Помилка сервера. Спробуйте пізніше.']);
}