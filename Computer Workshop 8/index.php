<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <title>Система тікетів Helpdesk</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Helpdesk: Тікети підтримки</h1>

        <div class="stats-block">
            <h3>Статистика системи:</h3>
            <p>Відкритих тікетів: <strong id="stat-open">0</strong></p>
            <p>В роботі: <strong id="stat-progress">0</strong></p>
            <p>Закритих тікетів: <strong id="stat-closed">0</strong></p>
        </div>

        <h2>Список тікетів:</h2>
        
        <input type="text" id="status-search" placeholder="Пошук за статусом (наприклад: open, in_progress, closed)..." style="width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">

        <div id="results" class="tickets-grid"></div>
        
        <button id="showFormBtn">+</button>
        
        <div class="ticket-form-container" style="display: none;">
            <p id="success-msg" class="success-msg" style="display: none;"></p>
            <p id="js-error-msg" class="error-msg js-error-msg"></p>
    
            <form id="addTicketForm">
                <input type="hidden" name="ticket_id" id="ticket_id" value="">
                <!-- CSRF Токен для форми -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                
                <label for="subject">Тема тікета:</label>
                <input type="text" id="subject" name="subject" required minlength="3" placeholder="Введіть тему тікету">

                <label for="description">Опис проблеми:</label>
                <textarea id="description" name="description" required minlength="15" placeholder="Детально опишіть суть проблеми (мінімум 15 символів)..."></textarea>

                <label for="priority">Пріоритет:</label>
                <select id="priority" name="priority">
                    <option value="low">Низький</option>
                    <option value="medium" selected>Середній</option>
                    <option value="high">Високий</option>
                </select>

                <label for="sla_hours">Час на вирішення (SLA у годинах, для термінових):</label>
                <input type="number" id="sla_hours" name="sla_hours" min="1" max="72" value="24">

                <div class="form-actions">
                    <button type="submit">Створити тікет</button>
                    <button type="button" id="closeFormBtn">Скасувати</button>
                </div>
            </form>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>