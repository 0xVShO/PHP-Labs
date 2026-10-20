<?php
$dsn = 'mysql:host=db;dbname=php_workshop_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', 'root_password', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
?>
