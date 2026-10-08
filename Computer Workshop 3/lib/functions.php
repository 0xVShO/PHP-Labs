<?php
function formatStatus(string $status): string {
    return match($status) {
        'open' => '<span style="color: green; font-weight: bold;">Відкритий</span>',
        'closed' => '<span style="color: gray;">Закритий</span>',
        'in_progress' => '<span style="color: orange; font-weight: bold;">В роботі</span>',
        default => '<span>Невідомо</span>',
    };
}

function isOverdue(Ticket $ticket): bool {
    if ($ticket instanceof UrgentTicket) {
        return $ticket->getSlaHours() <= 24; 
    }
    return false;
}
?>