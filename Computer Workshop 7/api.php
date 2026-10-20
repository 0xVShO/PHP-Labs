<?php
$startTime = microtime(true);
$startMemory = memory_get_peak_usage(true);
$sqlQueryCount = 0;

function executeQuery($pdo, $sql, $params = []) {
    global $sqlQueryCount;
    $sqlQueryCount++;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

require_once './db.php';
require_once './lib/functions.php';
require_once './classes/Ticket.php';
require_once './classes/UrgentTicket.php';
require_once './classes/HelpDesk.php';

header('Content-Type: application/json; charset=utf-8');

function sendDebugHeaders($source) {
    global $startTime, $startMemory, $sqlQueryCount;
    $timeMs = round((microtime(true) - $startTime) * 1000, 2);
    $memKb = round((memory_get_peak_usage(true) - $startMemory) / 1024, 2);
    header("X-Debug-Source: $source");
    header("X-Debug-Time-ms: $timeMs");
    header("X-Debug-Memory-kb: $memKb");
    header("X-Debug-SQL-Queries: $sqlQueryCount");
}

$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;
$id = $_GET['id'] ?? $_POST['ticket_id'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? null;

$cacheFile = __DIR__ . '/cache_tickets.json';
$cacheTTL = 60;

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
            $stmt = executeQuery($pdo, "SELECT id, subject, description, priority, sla_hours, status, created_at FROM tickets WHERE id = :id", [':id' => $id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ticket) {
                echo json_encode($ticket);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Тікет не знайдено.']);
            }
        } else {
            $statusFilter = $_GET['status'] ?? '';
            $cacheKey = $statusFilter ?: 'all';
            
            // Перевірка кешу (Лаба 7)
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
                $cacheData = json_decode(file_get_contents($cacheFile), true);
                if (isset($cacheData[$cacheKey])) {
                    sendDebugHeaders('cache');
                    http_response_code(200);
                    echo json_encode($cacheData[$cacheKey], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            $sql = "SELECT t.*, pc.priority_count 
                    FROM tickets t
                    LEFT JOIN (
                        SELECT priority, COUNT(*) as priority_count 
                        FROM tickets 
                        GROUP BY priority
                    ) pc ON t.priority = pc.priority";
            
            $params = [];
            if ($statusFilter) {
                $sql .= " WHERE t.status = :status";
                $params[':status'] = $statusFilter;
            }
            $sql .= " ORDER BY t.id DESC";

            $stmt = executeQuery($pdo, $sql, $params);
            $ticketsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = array_map(function($row) {
                $isUrgent = ($row['priority'] === 'high');
                $isOverdue = false;
                if ($isUrgent && !empty($row['sla_hours'])) {
                    $createdAt = new DateTime($row['created_at']);
                    $limit = clone $createdAt;
                    $limit->modify("+{$row['sla_hours']} hours");
                    $isOverdue = (new DateTime()) > $limit;
                }
                return [
                    'id' => $row['id'],
                    'subject' => $row['subject'],
                    'priority' => $row['priority'],
                    'status' => $row['status'],
                    'description' => $row['description'],
                    'is_urgent' => $isUrgent,
                    'sla_hours' => $row['sla_hours'],
                    'created_at' => $row['created_at'],
                    'is_overdue' => $isOverdue,
                    'priority_count' => $row['priority_count']
                ];
            }, $ticketsRaw);

            $cacheData = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
            $cacheData[$cacheKey] = $response;
            file_put_contents($cacheFile, json_encode($cacheData));

            sendDebugHeaders('database');
            http_response_code(200);
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        }
    } elseif ($method === 'POST') {
        
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

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