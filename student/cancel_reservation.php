<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Reservation.php';
if (!is_student()) { header('Location: ../index.php'); exit; }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    $user_id = $_SESSION['user']['id'];

    $reservation = new Reservation($pdo);
    $result = $reservation->cancelReservation($reservation_id, $user_id);

    if ($result) {
        echo json_encode(['ok' => true, 'msg' => 'Reservation cancelled successfully']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Failed to cancel reservation']);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
?>
