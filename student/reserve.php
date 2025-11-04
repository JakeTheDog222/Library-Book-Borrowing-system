<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Reservation.php';
if (!is_student()) { header('Location: ../index.php'); exit; }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $book_id = (int)$_POST['book_id'];
    $user_id = $_SESSION['user']['id'];

    $reservation = new Reservation($pdo);
    $result = $reservation->reserveBook($user_id, $book_id);

    echo json_encode($result);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
?>
