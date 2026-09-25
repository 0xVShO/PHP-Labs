<?php
// Крок 2: Оголошення масиву даних (Система тікетів)
$allowedPriorities = ['urgent', 'medium', 'low'];
$tickets = [];
$errors = [];
$successMessage = '';

$tickets = [
    [
        'subject' => 'Не працює інтернет на робочому місці',
        'priority' => 'urgent',
        'status' => 'open',
        'description' => 'Після ранкового оновлення системи зник доступ до мережі. Перезавантаження роутера не допомогло.'
    ],
    [
        'subject' => 'Оновити антивірус на бухгалтерському ПК',
        'priority' => 'low',
        'status' => 'closed',
        'description' => 'Потрібно встановити останню версію антивірусного ПЗ для відповідності новим політикам безпеки корпоративної мережі.'
    ],
    [
        'subject' => 'Замінити зламану мишку',
        'priority' => 'medium',
        'status' => 'open',
        'description' => 'У користувача в кабінеті 302 перестало працювати коліщатко на мишці. Прохання видати нову бездротову мишу.'
    ],
    [
        'subject' => 'Сервер бази даних не відповідає',
        'priority' => 'urgent',
        'status' => 'closed',
        'description' => 'Головний сервер БД ліг о 14:00. Помилка підключення timeout. Вже підняли з бекапу, але треба перевірити логи.'
    ],
    [
        'subject' => 'Надати доступ до спільної папки',
        'priority' => 'medium',
        'status' => 'open',
        'description' => 'Новий співробітник відділу маркетингу потребує доступу до папки "Promo_2026" на файловому сервері.'
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $_POST['subject'] ?? '';
    $description = $_POST['description'] ?? '';
    $priority = $_POST['priority'] ?? '';
    
    if(mb_strlen($description) < 15) {
        $errors[] = "Опис має містити не менше 15 символів.";
    }
    
    if (!in_array($priority, $allowedPriorities)) {
        $errors[] = "Недопустимий рівень пріоритету.";
    }

    if (empty($errors)) {
        $successMessage = "Тікет успішно створено!";
        
        $tickets[] = [
            'subject' => $subject,
            'priority' => $priority,
            'status' => 'open',
            'description' => $description
        ];
        
        $subject = '';
        $description = '';
        $priority = '';
    }
}

// Крок 3: Функція форматування (з типізацією)
function formatTicketDescription(array $ticket): string {
    $safeSubject = htmlspecialchars($ticket['subject']);
    $statusText = $ticket['status'] === 'open' ? 'Відкритий' : 'Закритий';

    $safeDesc = htmlspecialchars($ticket['description'] ?? 'Опис відсутній');

    return "<strong>Тема:</strong> {$safeSubject} <br> 
            <em>Статус:</em> {$statusText} <hr> 
            <div style='margin-top: 10px; font-size: 0.9em; color: #555;'>{$safeDesc}</div>";
}

// Крок 6: Обчислення агрегатних показників
$openTicketsCount = count(array_filter($tickets, function($ticket) {
    return $ticket['status'] === 'open';
}));

$closedTicketsCount = count(array_filter($tickets, function($ticket) {
    return $ticket['status'] === 'closed';
}));

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

        <!-- Виведення агрегатних показників -->
        <div class="stats-block">
            <h3>Статистика системи:</h3>
            <p>Відкритих тікетів: <strong><?= $openTicketsCount ?></strong></p>
            <p>Закритих тікетів: <strong><?= $closedTicketsCount ?></strong></p>
        </div>

        <h2>Список тікетів:</h2>
        <div class="tickets-grid">
            <?php
            if (empty($tickets)) {
                echo '<p>Тікетів наразі немає</p>';
            } else {
                // Крок 5: Виведення даних через цикл foreach
                foreach ($tickets as $ticket) {
                    // Крок 4: Умовна логіка для мітки
                    $priorityBadge = '';
                    if ($ticket['priority'] === 'urgent') {
                        $priorityBadge = '<span class="badge urgent">Терміновий</span>';
                    } elseif ($ticket['priority'] === 'medium') {
                        $priorityBadge = '<span class="badge medium">Середній</span>';
                    } else {
                        $priorityBadge = '<span class="badge low">Низький</span>';
                    }

                    // Виведення картки тікета
                    echo '<div class="ticket-card">';
                    echo $priorityBadge;
                    // Використання функції форматування
                    echo '<p class="ticket-info">' . formatTicketDescription($ticket) . '</p>';
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