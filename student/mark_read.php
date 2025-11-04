<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Notification.php';
if (!is_student()) { header('Location: ../index.php'); exit; }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notification_id = (int)$_POST['notification_id'];
    $user_id = $_SESSION['user']['id'];

    error_log("Marking notification $notification_id as read for user $user_id");

    $notification = new Notification($pdo);
    $result = $notification->markAsRead($notification_id, $user_id);

    error_log("Mark as read result: " . ($result ? 'success' : 'failed'));

    if ($result) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Failed to mark notification as read']);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
?>
