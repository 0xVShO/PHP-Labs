<?php
// Крок 2: Оголошення масиву даних (Система тікетів)
$tickets = [
    [
        'subject' => 'Не працює інтернет на робочому місці',
        'priority' => 'urgent',
        'status' => 'open'
    ],
    [
        'subject' => 'Оновити антивірус на бухгалтерському ПК',
        'priority' => 'low',
        'status' => 'closed'
    ],
    [
        'subject' => 'Замінити зламану мишку',
        'priority' => 'medium',
        'status' => 'open'
    ],
    [
        'subject' => 'Сервер бази даних не відповідає',
        'priority' => 'urgent',
        'status' => 'closed'
    ],
    [
        'subject' => 'Надати доступ до спільної папки',
        'priority' => 'medium',
        'status' => 'open'
    ]
];

// Крок 3: Функція форматування (з типізацією)
function formatTicketDescription(array $ticket): string {
    $safeSubject = htmlspecialchars($ticket['subject']);
    $statusText = $ticket['status'] === 'open' ? 'Відкритий' : 'Закритий';
    return "<strong>Тема:</strong> {$safeSubject} <br> <em>Статус:</em> {$statusText}";
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
            ?>
        </div>
    </div>
</body>
</html>