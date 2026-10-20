<?php
require_once '../db.php';
require_once '../lib/functions.php';
require_once '../classes/Ticket.php';
require_once '../classes/UrgentTicket.php';
require_once '../classes/HelpDesk.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $manager = new HelpDesk($pdo);
    $statusFilter = $_GET['status'] ?? '';
    
    if (is_string($statusFilter) && $statusFilter !== '') {
        $tickets = $manager->searchByStatus($statusFilter);
    } else {
        $tickets = array_merge(
            $manager->findByPriority('high'),
            $manager->findByPriority('medium'),
            $manager->findByPriority('low')
        );
    }

    $response = array_map(function($t) {
        return [
            'id' => $t->getId(),
            'subject' => $t->getSubject(),
            'priority' => $t->getPriority(),
            'status' => $t->getStatus(),
            'description' => $t->getDescription(),
            'is_urgent' => method_exists($t, 'getSlaHours'),
            'sla_hours' => method_exists($t, 'getSlaHours') ? $t->getSlaHours() : null,
            'created_at' => $t->getCreatedAt()->format('Y-m-d H:i:s'),
            'is_overdue' => isOverdue($t)
        ];
    }, $tickets);

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("API List Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Помилка сервера при завантаженні списку.']);
}