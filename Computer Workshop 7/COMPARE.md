# Порівняння роботи ДО і ПІЛСЯ оптимізації

<details>
<summary>Код початкової версії <code>api.php</code> :</summary>

```php
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

if ($method === 'GET') {
    if ($id) {
        $id = (int)$id;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Некоректний ID.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT id, subject, description, priority, sla_hours FROM tickets WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ticket) {
                echo json_encode($ticket);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Тікет не знайдено.']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Помилка БД: ' . $e->getMessage()]);
        }
    } else {
        try {
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
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Помилка сервера: ' . $e->getMessage()]);
        }
    }
} elseif ($method === 'POST') {
    if ($action === 'close') {
        $ticketId = (int)$id;
        if ($ticketId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Некоректні дані.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'closed' WHERE id = :id");
            $stmt->execute([':id' => $ticketId]);
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'update_status') {
        $ticketId = (int)$id;
        $newStatus = $_POST['status'] ?? '';

        if ($ticketId <= 0 || !in_array($newStatus, ['open', 'in_progress', 'closed'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Некоректні дані.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE tickets SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $ticketId]);
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'update') {
        $ticketId = (int)$id;
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? '';
        $slaHours = (int)($_POST['sla_hours'] ?? 24);
        $isUrgent = ($priority === 'high') ? 1 : 0;
        $sla = ($priority === 'high') ? $slaHours : null;

        $errors = [];
        if ($ticketId <= 0) $errors[] = "Помилка ідентифікації тікета.";
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
                ':id' => $ticketId
            ]);
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'delete') {
         $ticketId = (int)$id;

        if ($ticketId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Некоректний ID тікета.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = :id");
            $stmt->execute([':id' => $ticketId]);
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        $manager = new HelpDesk($pdo);
        $subject = $_POST['subject'] ?? '';
        $description = $_POST['description'] ?? '';
        $priority = $_POST['priority'] ?? '';
        $slaHours = (int)($_POST['sla_hours'] ?? 24);

        $errors = [];

        if (mb_strlen(trim($subject)) < 3) {
            $errors[] = "Назва має містити не менше 3 символів.";
        }
        if (mb_strlen($description) < 15) {
            $errors[] = "Опис має містити не менше 15 символів.";
        }
        if (!in_array($priority, ['high', 'medium', 'low'])) {
            $errors[] = "Недопустимий рівень пріоритету.";
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
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не дозволено.']);
}
```
</details>

---

Команда для тестування:
```bash
docker exec -it phpprojects-php-server-1 ab -n 200 -c 20 "http://localhost/Computer%20Workshop%207/api.php?resource=tickets"
```

Результати виконання команди тестування для початкової версії `api.php`:

```bash
This is ApacheBench, Version 2.3 <$Revision: 1934973 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking localhost (be patient)
Completed 100 requests
Completed 200 requests
Finished 200 requests


Server Software:        Apache/2.4.68
Server Hostname:        localhost
Server Port:            80

Document Path:          /Computer%20Workshop%207/api.php?resource=tickets
Document Length:        1027 bytes

Concurrency Level:      20
Time taken for tests:   0.138 seconds
Complete requests:      200
Failed requests:        0
Total transferred:      245800 bytes
HTML transferred:       205400 bytes
Requests per second:    1450.40 [#/sec] (mean)
Time per request:       13.789 [ms] (mean)
Time per request:       0.689 [ms] (mean, across all concurrent requests)
Transfer rate:          1740.76 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    0   0.1      0       1
Processing:     5   12   3.4     12      23
Waiting:        5   12   3.4     12      23
Total:          5   13   3.4     12      24

Percentage of the requests served within a certain time (ms)
  50%     12
  66%     14
  75%     15
  80%     15
  90%     16
  95%     19
  98%     22
  99%     23
 100%     24 (longest request)
```

---

<details>
<summary>Код оптимізованої версії <code>api.php</code> :</summary>

```php
<?php
$startTime = microtime(true);
$startMemory = memory_get_peak_usage(true);
$sqlQueryCount = 0;

// Функція-обгортка для підрахунку SQL-запитів
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

// Функція для відправки метрик у заголовках відповіді (щоб не ламати фронтенд)
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

if ($method === 'GET') {
    if ($id) {
        $id = (int)$id;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Некоректний ID.']);
            exit;
        }
        try {
            $stmt = executeQuery($pdo, "SELECT id, subject, description, priority, sla_hours, status FROM tickets WHERE id = :id", [':id' => $id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ticket) {
                echo json_encode($ticket);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Тікет не знайдено.']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Помилка БД: ' . $e->getMessage()]);
        }
    } else {
        try {
            $statusFilter = $_GET['status'] ?? '';
            $cacheKey = $statusFilter ?: 'all';
            
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
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Помилка сервера: ' . $e->getMessage()]);
        }
    }
} elseif ($method === 'POST') {
    
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }

    if ($action === 'close') {
        $ticketId = (int)$id;
        if ($ticketId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Некоректні дані.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'closed' WHERE id = :id");
            $stmt->execute([':id' => $ticketId]);
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'update_status') {
        $ticketId = (int)$id;
        $newStatus = $_POST['status'] ?? '';

        if ($ticketId <= 0 || !in_array($newStatus, ['open', 'in_progress', 'closed'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Некоректні дані.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE tickets SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $ticketId]);
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'update') {
        $ticketId = (int)$id;
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? '';
        $slaHours = (int)($_POST['sla_hours'] ?? 24);
        $isUrgent = ($priority === 'high') ? 1 : 0;
        $sla = ($priority === 'high') ? $slaHours : null;

        $errors = [];
        if ($ticketId <= 0) $errors[] = "Помилка ідентифікації тікета.";
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
                ':sub' => $subject, ':desc' => $description, ':pri' => $priority, ':is_urg' => $isUrgent, ':sla' => $sla, ':id' => $ticketId
            ]);
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'delete') {
         $ticketId = (int)$id;

        if ($ticketId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Некоректний ID тікета.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = :id");
            $stmt->execute([':id' => $ticketId]);
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        $manager = new HelpDesk($pdo);
        $subject = $_POST['subject'] ?? '';
        $description = $_POST['description'] ?? '';
        $priority = $_POST['priority'] ?? '';
        $slaHours = (int)($_POST['sla_hours'] ?? 24);

        $errors = [];
        if (mb_strlen(trim($subject)) < 3) $errors[] = "Назва має містити не менше 3 символів.";
        if (mb_strlen($description) < 15) $errors[] = "Опис має містити не менше 15 символів.";
        if (!in_array($priority, ['high', 'medium', 'low'])) $errors[] = "Недопустимий рівень пріоритету.";

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
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не дозволено.']);
}
```
</details>

---

Результати виконання команди тестування для нової версії `api.php`:
```bash
This is ApacheBench, Version 2.3 <$Revision: 1934973 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking localhost (be patient)
Completed 100 requests
Completed 200 requests
Finished 200 requests


Server Software:        Apache/2.4.68
Server Hostname:        localhost
Server Port:            80

Document Path:          /Computer%20Workshop%207/api.php?resource=tickets
Document Length:        1122 bytes

Concurrency Level:      20
Time taken for tests:   0.192 seconds
Complete requests:      200
Failed requests:        0
Total transferred:      283160 bytes
HTML transferred:       224400 bytes
Requests per second:    1043.43 [#/sec] (mean)
Time per request:       19.168 [ms] (mean)
Time per request:       0.958 [ms] (mean, across all concurrent requests)
Transfer rate:          1442.67 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    0   0.2      0       1
Processing:    10   17   3.6     17      27
Waiting:       10   17   3.6     17      27
Total:         11   17   3.6     17      28

Percentage of the requests served within a certain time (ms)
  50%     17
  66%     20
  75%     20
  80%     21
  90%     22
  95%     23
  98%     27
  99%     28
 100%     28 (longest request)
```

- Час / RPS до: 1450 req/s, ~13.7 ms
- Час / RPS після: 1043 req/s, ~19.1 ms

### Висновок: 
Оптимізація виявила ефект I/O вузького місця: на мікро-об'ємах даних файлове кешування (через специфіку змонтованих томів Docker на Windows) працює повільніше, ніж прямі звернення до MySQL в пам'яті. Також витрати на json_decode/json_encode файлу перекрили вигоду від усунення N+1 (JOIN), оскільки вибірка дуже мала.