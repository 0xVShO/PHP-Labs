<?php
require_once 'lib/functions.php';
require_once 'classes/Ticket.php';
require_once 'classes/UrgentTicket.php';
require_once 'classes/HelpDesk.php';
require_once 'db.php';

$manager = new HelpDesk($pdo);

$allowedPriorities = ['high', 'medium', 'low'];
$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_status') {
            $ticketId = (int)$_POST['ticket_id'];
            $newStatus = $_POST['status'];
            
            if (in_array($newStatus, ['open', 'in_progress', 'closed'])) {
                $manager->updateStatus($ticketId, $newStatus);
            }
            header("Location: index.php");
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            $ticketId = (int)$_POST['ticket_id'];
            $manager->deleteTicket($ticketId);
            header("Location: index.php");
            exit;
        }
    }

    $subject = $_POST['subject'] ?? '';
    $description = $_POST['description'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $slaHours = (int)($_POST['sla_hours'] ?? 24);
    $editId = !empty($_POST['ticket_id']) && !isset($_POST['action']) ? (int)$_POST['ticket_id'] : null;
    
    if(mb_strlen(trim($subject)) < 3) {
        $errors[] = "Назва має містити не менше 3 символів.";
    }

    if(mb_strlen($description) < 15) {
        $errors[] = "Опис має містити не менше 15 символів.";
    }
    
    if (!in_array($priority, $allowedPriorities)) {
        $errors[] = "Недопустимий рівень пріоритету.";
    }

    if (empty($errors) && !isset($_POST['action'])) {
        if ($editId) {
            $isUrgent = ($priority === 'high') ? 1 : 0;
            $sla = ($priority === 'high') ? $slaHours : null;
            $stmt = $pdo->prepare("UPDATE tickets SET subject = :sub, description = :desc, priority = :pri, is_urgent = :is_urg, sla_hours = :sla WHERE id = :id");
            $stmt->execute([
                ':sub' => $subject,
                ':desc' => $description,
                ':pri' => $priority,
                ':is_urg' => $isUrgent,
                ':sla' => $sla,
                ':id' => $editId
            ]);
        } else {
            if ($priority === 'high') {
                $manager->addTicket(new UrgentTicket($subject, $priority, 'open', $description, $slaHours, 'now'));
            } else {
                $manager->addTicket(new Ticket($subject, $priority, 'open', $description, 'now'));
            }
        }
        
        header("Location: index.php");
        exit;
    }
}

$openTickets = $manager->openTickets();
$allTickets = array_merge(
    $manager->findByPriority('high'),
    $manager->findByPriority('medium'),
    $manager->findByPriority('low')
);

$openTicketsCount = count($openTickets);
$closedTicketsCount = count($manager->closedTickets());
$inProgressTicketsCount = count($manager->inProgressTickets());

$editTicketId = null;
if (isset($_GET['edit_id'])) {
    $editTicketId = (int)$_GET['edit_id'];
    foreach ($allTickets as $t) {
        if (method_exists($t, 'getId') && $t->getId() === $editTicketId) {
            $subject = $t->getSubject();
            $description = $t->getDescription();
            $priority = $t->getPriority();
            $slaHours = method_exists($t, 'getSlaHours') ? $t->getSlaHours() : 24;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система тікетів Helpdesk</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Helpdesk: Тікети підтримки</h1>

        <div class="stats-block">
            <h3>Статистика системи:</h3>
            <p>Відкритих тікетів: <strong><?= $openTicketsCount ?></strong></p>
            <p>В роботі: <strong><?= $inProgressTicketsCount ?></strong></p>
            <p>Закритих тікетів: <strong><?= $closedTicketsCount ?></strong></p>
        </div>

        <h2>Список тікетів:</h2>
        <div class="tickets-grid">
            <?php
            if (empty($allTickets)) {
                echo '<p>Тікетів наразі немає</p>';
            } else {
                foreach ($allTickets as $ticket) {
                    $priorityBadge = '';
                    
                    if (method_exists($ticket, 'getPriority')) {
                        if ($ticket->getPriority() === 'high') {
                            $priorityBadge = '<span class="badge urgent">Терміновий</span>';
                        } elseif ($ticket->getPriority() === 'medium') {
                            $priorityBadge = '<span class="badge medium">Середній</span>';
                        } else {
                            $priorityBadge = '<span class="badge low">Низький</span>';
                        }
                    }

                    echo '<div class="ticket-card">';
                    
                    if (method_exists($ticket, 'getId') && $ticket->getId() !== null) {
                        echo '<div class="ticket-actions">';
                        echo '<a href="?edit_id=' . $ticket->getId() . '" class="btn-edit">✎ Ред.</a>';
                        echo '<form method="post" class="form-inline">';
                        echo '<input type="hidden" name="action" value="delete">';
                        echo '<input type="hidden" name="ticket_id" value="' . $ticket->getId() . '">';
                        echo '<button type="submit" class="btn-delete" onclick="return confirm(\'Видалити цей тікет?\')">✖ Видалити</button>';
                        echo '</form>';
                        echo '</div>';
                    }

                    echo '<div class="ticket-status">' . formatStatus($ticket->getStatus()) . '</div>';
                    echo $priorityBadge;
                    echo '<div class="ticket-info">' . nl2br(htmlspecialchars($ticket->getInfo())) . '</div>';
                    
                    if (isOverdue($ticket)) {
                        echo '<div class="sla-warning"><b>Увага:</b> SLA прострочено!</div>';
                    }
                    
                    if (method_exists($ticket, 'getId') && $ticket->getId() !== null) {
                        echo '<form method="post" class="status-update-form">';
                        echo '<input type="hidden" name="action" value="update_status">';
                        echo '<input type="hidden" name="ticket_id" value="' . $ticket->getId() . '">';
                        echo '<select name="status" onchange="this.form.submit()" class="status-select">';
                        echo '<option value="open"' . ($ticket->getStatus() === 'open' ? ' selected' : '') . '>Відкритий</option>';
                        echo '<option value="in_progress"' . ($ticket->getStatus() === 'in_progress' ? ' selected' : '') . '>В роботі</option>';
                        echo '<option value="closed"' . ($ticket->getStatus() === 'closed' ? ' selected' : '') . '>Закритий</option>';
                        echo '</select>';
                        echo '</form>';
                    }

                    echo '</div>';
                }
            }
            ?>
        </div>
        
        <button id="showFormBtn" style="<?= $editTicketId ? 'display:none;' : '' ?>">+</button>
        
        <div class="ticket-form-container" style="<?= (!empty($errors) || $editTicketId) ? 'display: block;' : 'display: none;' ?>">
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
    
            <?php if (!empty($successMessage)): ?>
                <p id="success-msg" class="success-msg"><?= htmlspecialchars($successMessage) ?></p>
            <?php endif; ?>
    
            <p id="js-error-msg" class="error-msg js-error-msg"></p>
    
            <form method="post" action="index.php">
                <input type="hidden" name="ticket_id" value="<?= htmlspecialchars((string)($editTicketId ?? '')) ?>">
                
                <label for="subject">Тема тікета:</label>
                <input type="text" id="subject" name="subject" required minlength="3" placeholder="Введіть тему тікету" value="<?= htmlspecialchars($subject ?? '') ?>">

                <label for="description">Опис проблеми:</label>
                <textarea id="description" name="description" required minlength="15" placeholder="Детально опишіть суть проблеми (мінімум 15 символів)..."><?= htmlspecialchars($description ?? '') ?></textarea>

                <label for="priority">Пріоритет:</label>
                <select id="priority" name="priority">
                    <option value="low" <?= (isset($priority) && $priority === 'low') ? 'selected' : '' ?>>Низький</option>
                    <option value="medium" <?= (empty($priority) || $priority === 'medium') ? 'selected' : '' ?>>Середній</option>
                    <option value="high" <?= (isset($priority) && $priority === 'high') ? 'selected' : '' ?>>Високий</option>
                </select>

                <label for="sla_hours">Час на вирішення (SLA у годинах, для термінових):</label>
                <input type="number" id="sla_hours" name="sla_hours" min="1" max="72" value="<?= htmlspecialchars((string)($slaHours ?? '24')) ?>">

                <div class="form-actions">
                    <button type="submit"><?= $editTicketId ? 'Оновити тікет' : 'Створити тікет' ?></button>
                    <?php if ($editTicketId): ?>
                        <a href="index.php" class="cancel-link"><button type="button">Скасувати</button></a>
                    <?php else: ?>
                        <button type="button" id="closeFormBtn">Скасувати</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>