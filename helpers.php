<?php
require_once 'config.php';

function is_logged_in() {
    return isset($_SESSION['user']);
}
function is_admin() {
    return is_logged_in() && $_SESSION['user']['role'] === 'admin';
}
function is_student() {
    return is_logged_in() && $_SESSION['user']['role'] === 'student';
}
function flash_set($msg) {
    $_SESSION['_flash'] = $msg;
}
function flash_get() {
    $m = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $m;
}

function count_overdue($pdo, $user_id) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrow_history WHERE user_id = ? AND status = 'borrowed' AND due_date < ?");
    $stmt->execute([$user_id, $today]);
    return intval($stmt->fetchColumn());
}

function mark_overdues($pdo) {
    $today = date('Y-m-d');
    $pdo->prepare("UPDATE borrow_history SET status='overdue' WHERE return_date IS NULL AND due_date < ?")->execute([$today]);
}
?>