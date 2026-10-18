<?php
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$id = (int)($_POST['ticket_id'] ?? 0);
$subject = trim($_POST['subject'] ?? '');
$description = trim($_POST['description'] ?? '');
$priority = $_POST['priority'] ?? '';
$slaHours = (int)($_POST['sla_hours'] ?? 24);
$isUrgent = ($priority === 'high') ? 1 : 0;
$sla = ($priority === 'high') ? $slaHours : null;

$errors = [];
if ($id <= 0) $errors[] = "Помилка ідентифікації тікета.";
if (mb_strlen($subject) < 3) $errors[] = "Назва має містити не менше 3 символів.";
if (mb_strlen($description) < 15) $errors[] = "Опис має містити не менше 15 символів.";

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE tickets SET subject = :sub, description = :desc, priority = :pri, is_urgent = :is_urg, sla_hours = :sla WHERE id = :id");
    $stmt->execute([
        ':sub' => $subject,
        ':desc' => $description,
        ':pri' => $priority,
        ':is_urg' => $isUrgent,
        ':sla' => $sla,
        ':id' => $id
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}