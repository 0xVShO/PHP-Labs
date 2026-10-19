<?php
require_once 'Ticket.php';

class UrgentTicket extends Ticket {
    protected int $slaHours;

    public function __construct(string $subject, string $priority, string $status, string $description, int $slaHours, string $createdAt = 'now', ?int $id = null) {
        parent::__construct($subject, $priority, $status, $description, $createdAt, $id);
        $this->slaHours = $slaHours;
    }

    public function getInfo(): string {
        return parent::getInfo() . " | SLA: {$this->slaHours} год.";
    }

    public function getSlaHours(): int {
        return $this->slaHours;
    }
}
?>