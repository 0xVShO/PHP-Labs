<?php
require_once 'Ticket.php';

class HelpDesk {
    private array $tickets = [];

    public function addTicket(Ticket $ticket): void {
        $this->tickets[] = $ticket;
    }

    public function findByPriority(string $priority): array {
        return array_filter($this->tickets, function($ticket) use ($priority) {
            return $ticket->getPriority() === $priority;
        });
    }

    public function openTickets(): array {
        return array_filter($this->tickets, function($ticket) {
            return $ticket->getStatus() === 'open';
        });
    }

    public function closedTickets(): array {
        return array_filter($this->tickets, function($ticket) {
            return $ticket->getStatus() === 'closed';
        });
    }
    
}
?>