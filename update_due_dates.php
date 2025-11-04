<?php
require_once 'config.php';
require_once 'classes/Borrow.php';

// Update due dates for existing borrowed and overdue records to 14 days from borrow date
$stmt = $pdo->prepare("UPDATE borrow_history SET due_date = DATE_ADD(borrow_date, INTERVAL 14 DAY) WHERE status IN ('borrowed', 'overdue')");
$stmt->execute();

echo "Due dates updated for existing borrowed and overdue records.";
?>
