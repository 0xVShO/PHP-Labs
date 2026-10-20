<?php
/* 
 * API Контракт: Система тікетів служби підтримки (Варіант 19)
 * Базовий URL: api.php
 * Ресурс: tickets (поля: id, subject, priority, status)
 * 
 * === GET-запити (Отримання даних) ===
 * 
 * 1. Отримати список тікетів
 *    URL: api.php?resource=tickets
 *    Додаткові параметри: &status=open|closed|in_progress
 * 
 * 2. Отримати один тікет за ID
 *    URL: api.php?resource=tickets&id={N}
 * 
 * === POST-запити (Модифікація даних) ===
 * 
 * 3. Створити новий тікет
 *    URL: api.php?resource=tickets
 *    Параметри: subject, description, priority, sla_hours (без параметра action)
 * 
 * 4. Закрити тікет
 *    URL: api.php?resource=tickets
 *    Параметри: action=close, ticket_id={N}
 * 
 * 5. Оновити статус тікета
 *    URL: api.php?resource=tickets
 *    Параметри: action=update_status, ticket_id={N}, status=open|in_progress|closed
 * 
 * 6. Редагувати існуючий тікет
 *    URL: api.php?resource=tickets
 *    Параметри: action=update, ticket_id, subject, description, priority, sla_hours
 * 
 * 7. Видалити тікет
 *    URL: api.php?resource=tickets
 *    Параметри: action=delete, ticket_id={N}
 */

require_once './db.php';
require_once './lib/functions.php';
require_once './classes/Ticket.php';
require_once './classes/UrgentTicket.php';
require_once './classes/HelpDesk.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;
$id = $_GET['id'] ?? $_POST['ticket_id'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($resource !== 'tickets') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Ресурс не знайдено']);
    exit;
}

try {
    if ($method === 'GET') {
        
        if ($id) {
            $id = (int)$id;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Некоректний ID.']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT id, subject, description, priority, sla_hours FROM tickets WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ticket) {
                echo json_encode($ticket);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Тікет не знайдено.']);
            }
            
        } else {
            $manager = new HelpDesk($pdo);
            $statusFilter = $_GET['status'] ?? '';
            
            if ($statusFilter === 'open') {
                $tickets = $manager->openTickets();
            } elseif ($statusFilter === 'closed') {
                $tickets = $manager->closedTickets();
            } elseif ($statusFilter === 'in_progress') {
                $tickets = $manager->inProgressTickets();
            } else {
                $tickets = array_merge(
                    $manager->findByPriority('high'),
                    $manager->findByPriority('medium'),
                    $manager->findByPriority('low')
                );
            }

            $response = array_map(function($t) {
                return [
                    'id' => $t->getId(),
                    'subject' => $t->getSubject(),
                    'priority' => $t->getPriority(),
                    'status' => $t->getStatus(),
                    'description' => $t->getDescription(),
                    'is_urgent' => method_exists($t, 'getSlaHours'),
                    'sla_hours' => method_exists($t, 'getSlaHours') ? $t->getSlaHours() : null,
                    'created_at' => $t->getCreatedAt()->format('Y-m-d H:i:s'),
                    'is_overdue' => function_exists('isOverdue') ? isOverdue($t) : false
                ];
            }, $tickets);

            http_response_code(200);
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        }
        
    } elseif ($method === 'POST') {
        
        if ($action === 'close') {
            $ticketId = (int)$id;
            if ($ticketId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Некоректний ID.']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'closed' WHERE id = :id");
            $stmt->execute([':id' => $ticketId]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Тікет не знайдено.']);
                exit;
            }
            
            http_response_code(200);
            echo json_encode(['success' => true]);
            
        } elseif ($action === 'update_status') {
            $ticketId = (int)$id;
            $newStatus = $_POST['status'] ?? '';

            if ($ticketId <= 0 || !is_string($newStatus) || !in_array($newStatus, ['open', 'in_progress', 'closed'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Некоректні дані.']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE tickets SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $ticketId]);
            
            http_response_code(200);
            echo json_encode(['success' => true]);
            
        } elseif ($action === 'update') {
            $ticketId = (int)$id;
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
            if ($ticketId <= 0) $errors[] = "Помилка ідентифікації тікета.";
            if (mb_strlen($subject) < 3) $errors[] = "Назва має містити не менше 3 символів.";
            if (mb_strlen($description) < 15 || mb_strlen($description) > 500) $errors[] = "Опис має бути від 15 до 500 символів.";
            if (!in_array($priority, ['high', 'medium', 'low'])) $errors[] = "Недопустимий рівень пріоритету.";
            if ($priority === 'high' && ($slaHours < 1 || $slaHours > 72)) $errors[] = "SLA має бути в межах від 1 до 72 годин.";

            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE tickets SET subject = :sub, description = :desc, priority = :pri, is_urgent = :is_urg, sla_hours = :sla WHERE id = :id");
            $stmt->execute([
                ':sub' => $subject,
                ':desc' => $description,
                ':pri' => $priority,
                ':is_urg' => $isUrgent,
                ':sla' => $sla,
                ':id' => $ticketId
            ]);
            
            http_response_code(200);
            echo json_encode(['success' => true]);
            
        } elseif ($action === 'delete') {
            $ticketId = (int)$id;

            if ($ticketId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Некоректний ID тікета.']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = :id");
            $stmt->execute([':id' => $ticketId]);
            
            http_response_code(200);
            echo json_encode(['success' => true]);
            
        } else {
            // Додавання (створення) тікета
            $manager = new HelpDesk($pdo);
            
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

            $errors = [];
            if (mb_strlen($subject) < 3) $errors[] = "Назва має містити не менше 3 символів.";
            if (mb_strlen($description) < 15 || mb_strlen($description) > 500) $errors[] = "Опис має бути від 15 до 500 символів.";
            if (!in_array($priority, ['high', 'medium', 'low'])) $errors[] = "Недопустимий рівень пріоритету.";
            if ($priority === 'high' && ($slaHours < 1 || $slaHours > 72)) $errors[] = "SLA має бути в межах від 1 до 72 годин.";

            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
            
            if ($priority === 'high') {
                $ticket = new UrgentTicket($subject, $priority, 'open', $description, $slaHours, 'now');
            } else {
                $ticket = new Ticket($subject, $priority, 'open', $description, 'now');
            }
            
            $manager->addTicket($ticket);
            
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Тікет успішно створено!']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Метод не дозволено.']);
    }
} catch (Throwable $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Внутрішня помилка сервера. Спробуйте пізніше.']);
}