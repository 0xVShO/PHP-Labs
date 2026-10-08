<?php
require_once 'lib/functions.php';
require_once 'classes/Ticket.php';
require_once 'classes/UrgentTicket.php';
require_once 'classes/HelpDesk.php';

$manager = new HelpDesk();

$allowedPriorities = ['urgent', 'medium', 'low'];
$errors = [];
$successMessage = '';

$manager->addTicket(new UrgentTicket(
    'Не працює інтернет на робочому місці', 
    'urgent',
    'open', 
    'Після ранкового оновлення системи зник доступ до мережі. Перезавантаження роутера не допомогло.', 
    2
));

$manager->addTicket(new Ticket(
    'Оновити антивірус на бухгалтерському ПК', 
    'low', 
    'closed', 
    'Потрібно встановити останню версію антивірусного ПЗ для відповідності новим політикам безпеки корпоративної мережі.'
));

$manager->addTicket(new Ticket(
    'Замінити зламану мишку', 
    'medium', 
    'open', 
    'У користувача в кабінеті 302 перестало працювати коліщатко на мишці. Прохання видати нову бездротову мишу.'
));

$manager->addTicket(new UrgentTicket(
    'Сервер бази даних не відповідає', 
    'urgent', 
    'closed', 
    'Головний сервер БД ліг о 14:00. Помилка підключення timeout. Вже підняли з бекапу, але треба перевірити логи.',
    1
));

$manager->addTicket(new Ticket(
    'Надати доступ до спільної папки', 
    'medium', 
    'open', 
    'Новий співробітник відділу маркетингу потребує доступу до папки "Promo_2026" на файловому сервері.'
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $_POST['subject'] ?? '';
    $description = $_POST['description'] ?? '';
    $priority = $_POST['priority'] ?? '';
    
    if(mb_strlen(trim($subject)) < 3) {
        $errors[] = "Назва має містити не менше 3 символів.";
    }

    if(mb_strlen($description) < 15) {
        $errors[] = "Опис має містити не менше 15 символів.";
    }
    
    if (!in_array($priority, $allowedPriorities)) {
        $errors[] = "Недопустимий рівень пріоритету.";
    }

    if (empty($errors)) {
        $successMessage = "Тікет успішно створено!";
        
        if ($priority === 'urgent') {
            $manager->addTicket(new UrgentTicket($subject, $priority, 'open', $description, 24));
        } else {
            $manager->addTicket(new Ticket($subject, $priority, 'open', $description));
        }
        
        $subject = '';
        $description = '';
        $priority = '';
    }
}

$openTickets = $manager->openTickets();
$closedTickets = $manager->closedTickets();
$openTicketsCount = count($openTickets);
$closedTicketsCount = count($closedTickets);
$allTickets = array_merge($openTickets, $closedTickets);

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
                        if ($ticket->getPriority() === 'urgent') {
                            $priorityBadge = '<span class="badge urgent">Терміновий</span>';
                        } elseif ($ticket->getPriority() === 'medium') {
                            $priorityBadge = '<span class="badge medium">Середній</span>';
                        } else {
                            $priorityBadge = '<span class="badge low">Низький</span>';
                        }
                    }

                    echo '<div class="ticket-card">';
                    echo $priorityBadge;
                    echo '<div class="ticket-info">' . nl2br(htmlspecialchars($ticket->getInfo())) . '</div>';
                    
                    if (isOverdue($ticket)) {
                        echo '<div style="color:red; font-size:0.9em; margin-top:10px;"><b>Увага:</b> SLA прострочено!</div>';
                    }
                    
                    echo '</div>';
                }
            }
            ?>
        </div>
        
        <button id="showFormBtn">+</button>
        
        <div class="ticket-form-container" style="<?= (!empty($errors)) ? 'display: block;' : 'display: none;' ?>">
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <p style="color: red;"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
    
            <?php if (!empty($successMessage)): ?>
                <p id="success-msg" style="color: green;"><?= htmlspecialchars($successMessage) ?></p>
            <?php endif; ?>
    
            <p id="js-error-msg" style="color: red; display: none;"></p>
    
            <form method="post">
                <input type="text" name="subject" required value="<?= htmlspecialchars($subject ?? '') ?>">
                <textarea name="description"><?= htmlspecialchars($description ?? '') ?></textarea>
                <select name="priority">
                    <option value="urgent">urgent</option>
                    <option value="medium">medium</option>
                    <option value="low">low</option>
                </select>
                <button type="submit">Створити тікет</button>
                <button type="button" id="closeFormBtn">Скасувати</button>
            </form>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>