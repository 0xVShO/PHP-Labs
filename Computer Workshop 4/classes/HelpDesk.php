<?php
require_once 'Ticket.php';
require_once 'UrgentTicket.php';

class HelpDesk {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function addTicket(Ticket $ticket): void {
        $isUrgent = $ticket instanceof UrgentTicket ? 1 : 0;
        $sla = $ticket instanceof UrgentTicket ? $ticket->getSlaHours() : null;

        $stmt = $this->pdo->prepare("INSERT INTO tickets (description, subject, priority, status, created_at, is_urgent, sla_hours) VALUES (:desc, :sub, :pri, :stat, :created_at, :is_urg, :sla)");
        $stmt->execute([
            ':desc' => $ticket->getDescription(),
            ':sub' => $ticket->getSubject(),
            ':pri' => $ticket->getPriority(),
            ':stat' => $ticket->getStatus(),
            ':created_at' => $ticket->getCreatedAt()->format('Y-m-d H:i:s'),
            ':is_urg' => $isUrgent,
            ':sla' => $sla
        ]);
    }

    public function findByPriority(string $priority): array {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets WHERE priority = :priority");
        $stmt->execute(['priority' => $priority]);
        $rows = $stmt->fetchAll();
        
        $tickets = [];
        
        foreach ($rows as $row) {
            if ((int)$row['is_urgent'] === 1) {
                $tickets[] = new UrgentTicket($row['subject'], $row['priority'], $row['status'], $row['description'], (int)$row['sla_hours'], $row['created_at'], $row['id']);
            } else {
                $tickets[] = new Ticket($row['subject'], $row['priority'], $row['status'], $row['description'], $row['created_at'], $row['id']);
            }
        }

        return $tickets;
    }

    public function openTickets(): array {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets WHERE status = :status");
        $stmt->execute(['status' => 'open']);
        $rows = $stmt->fetchAll();

        $tickets = [];
        foreach ($rows as $row) {
            if ((int)$row['is_urgent'] === 1) {
                $tickets[] = new UrgentTicket($row['subject'], $row['priority'], $row['status'], $row['description'], (int)$row['sla_hours'], $row['created_at'], $row['id']);
            } else {
                $tickets[] = new Ticket($row['subject'], $row['priority'], $row['status'], $row['description'], $row['created_at'], $row['id']);
            }
        }

        return $tickets;
    }

    public function closedTickets(): array {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets WHERE status = :status");
        $stmt->execute(['status' => 'closed']);
        $rows = $stmt->fetchAll();

        $tickets = [];
        foreach ($rows as $row) {
            if ((int)$row['is_urgent'] === 1) {
                $tickets[] = new UrgentTicket($row['subject'], $row['priority'], $row['status'], $row['description'], (int)$row['sla_hours'], $row['created_at'], $row['id']);
            } else {
                $tickets[] = new Ticket($row['subject'], $row['priority'], $row['status'], $row['description'], $row['created_at'], $row['id']);
            }
        }

        return $tickets;
    }

    public function inProgressTickets(): array {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets WHERE status = :status");
        $stmt->execute(['status' => 'in_progress']);
        $rows = $stmt->fetchAll();

        $tickets = [];
        foreach ($rows as $row) {
            if ((int)$row['is_urgent'] === 1) {
                $tickets[] = new UrgentTicket($row['subject'], $row['priority'], $row['status'], $row['description'], (int)$row['sla_hours'], $row['created_at'], $row['id']);
            } else {
                $tickets[] = new Ticket($row['subject'], $row['priority'], $row['status'], $row['description'], $row['created_at'], $row['id']);
            }
        }

        return $tickets;
    }

    public function updateStatus(int $id, string $status): void {
        $stmt = $this->pdo->prepare("UPDATE tickets SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function deleteTicket(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM tickets WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
?>