<?php
class Ticket {
    protected string $subject;
    protected string $priority;
    protected string $status;
    protected string $description;

    public function __construct(string $subject, string $priority, string $status, string $description) {
        $this->subject = $subject;
        $this->priority = $priority;
        $this->status = $status;
        $this->description = $description;
    }

    public function getInfo(): string {
        return "Тема: {$this->subject}\nПріорітет: {$this->priority}\nСтатус: {$this->status}\nОпис: {$this->description}";
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function getPriority(): string {
        return $this->priority;
    }
}
?>