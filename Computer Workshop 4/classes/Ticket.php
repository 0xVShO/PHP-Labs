<?php
class Ticket {
    protected ?int $id = null;
    protected string $subject;
    protected string $priority;
    protected string $status;
    protected string $description;
    protected DateTimeImmutable $createdAt;

    public function __construct(string $subject, string $priority, string $status, string $description, string $createdAt = 'now', ?int $id = null) {
        $this->id = $id;
        $this->subject = $subject;
        $this->priority = $priority;
        $this->status = $status;
        $this->description = $description;
        $this->createdAt = new DateTimeImmutable($createdAt);
    }

    public function getInfo(): string {
        return "Тема: {$this->subject}\nПріорітет: {$this->priority}\nСтатус: {$this->status}\nОпис: {$this->description}\nДата створення: {$this->createdAt->format('Y-m-d H:i:s')}";
    }

    public function getId(): ?int { 
        return $this->id; 
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getSubject(): string { 
        return $this->subject; 
    }

    public function getPriority(): string {
        return $this->priority;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable {
        return $this->createdAt;
    }
}
?>