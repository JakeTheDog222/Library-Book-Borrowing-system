<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Borrow.php';

if (!is_student()) { header('Location: ../index.php'); exit; }
$uid = $_SESSION['user']['id'];
$id = intval($_GET['id'] ?? 0);
if ($id) {
    $ok = (new Borrow($pdo))->cancelRequest($id, $uid);
    if ($ok) flash_set('Request cancelled.');
    else flash_set('Cancellation failed.');
}
header('Location: dashboard.php');
exit;
?>
