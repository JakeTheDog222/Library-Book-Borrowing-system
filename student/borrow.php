<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Borrow.php';

if (!is_student()) { header('Location: ../index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') header('Location: dashboard.php');
$uid = $_SESSION['user']['id'];
$book_id = intval($_POST['book_id'] ?? 0);
$copies = max(1,intval($_POST['copies'] ?? 1));
$edit_id = intval($_POST['edit_id'] ?? 0);

if ($edit_id) {
    // Edit existing rejected request
    $stmt = $pdo->prepare('UPDATE borrow_history SET copies_borrowed=?, status=? WHERE id=? AND user_id=? AND status=?');
    $stmt->execute([$copies, 'pending', $edit_id, $uid, 'rejected']);
    flash_set('Borrow request updated.');
} else {
    $res = (new Borrow($pdo))->borrow($uid,$book_id,$copies);
    if ($res['ok']) {
        flash_set($res['msg']);
    } else {
        flash_set('Borrow request failed: ' . $res['msg']);
    }
}
header('Location: dashboard.php');
exit;
?>