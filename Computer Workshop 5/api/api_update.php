<?php
require_once '../db.php';
require_once '../classes/HelpDesk.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$id = (int)($_POST['ticket_id'] ?? 0);
$subjectRaw = $_POST['subject'] ?? '';
$descriptionRaw = $_POST['description'] ?? '';
$priorityRaw = $_POST['priority'] ?? '';

if (!is_string($subjectRaw) || !is_string($descriptionRaw) || !is_string($priorityRaw)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Некоректний формат даних.']]);
    exit;
}

$subject = trim($subjectRaw);
$description = trim($descriptionRaw);
$priority = $priorityRaw;
$slaHours = isset($_POST['sla_hours']) ? (int)$_POST['sla_hours'] : 24;
$isUrgent = ($priority === 'high') ? 1 : 0;
$sla = ($priority === 'high') ? $slaHours : null;

$errors = [];
if ($id <= 0) $errors[] = "Помилка ідентифікації тікета.";
if (mb_strlen($subject) < 3) $errors[] = "Назва має містити не менше 3 символів.";
if (mb_strlen($description) < 15 || mb_strlen($description) > 500) $errors[] = "Опис має бути від 15 до 500 символів.";
if (!in_array($priority, ['high', 'medium', 'low'])) $errors[] = "Недопустимий рівень пріоритету.";
if ($priority === 'high' && ($slaHours < 1 || $slaHours > 72)) $errors[] = "SLA має бути в межах від 1 до 72 годин.";

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $manager = new HelpDesk($pdo);
    $manager->updateTicket($id, $subject, $description, $priority, $isUrgent, $sla);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log("Update Ticket Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Помилка сервера під час оновлення.']);
}