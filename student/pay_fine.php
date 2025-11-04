<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Fine.php';
if (!is_student()) { header('Location: ../index.php'); exit; }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fine_id'])) {
    $fine_id = (int)$_POST['fine_id'];
    $user_id = $_SESSION['user']['id'];

    error_log("Paying fine $fine_id for user $user_id");

    $fine = new Fine($pdo);
    $result = $fine->payFine($fine_id, $user_id);

    error_log("Pay fine result: " . ($result ? 'success' : 'failed'));

    if ($result) {
        // Log the fine payment in audit logs
        require_once __DIR__ . '/../classes/Audit.php';
        $audit = new Audit($pdo);
        $audit->log($user_id, 'fine_paid', "Paid fine ID: $fine_id");
    }

    echo json_encode(['ok' => $result, 'msg' => $result ? 'Fine paid successfully!' : 'Failed to pay fine']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
?>
