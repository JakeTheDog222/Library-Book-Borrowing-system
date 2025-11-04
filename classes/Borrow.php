<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';
class Borrow extends Database {
    public function __construct($pdo=null){ parent::__construct($pdo); }
    public function borrow($user_id,$book_id,$copies) {
        // check blocked
        $u = new User($this->pdo);
        if ($u->isBlocked($user_id)) return ['ok'=>false,'msg'=>'You are blocked from borrowing.'];

        // Check fines
        require_once __DIR__ . '/Fine.php';
        $fine = new Fine($this->pdo);
        if ($fine->isBlockedByFines($user_id)) return ['ok'=>false,'msg'=>'You have outstanding fines exceeding ₱50. Please pay your fines before borrowing.'];

        // check if already requested this book
        $stmt = $this->pdo->prepare('SELECT id, status, copies_borrowed FROM borrow_history WHERE user_id=? AND book_id=? AND status IN (?,?)');
        $stmt->execute([$user_id,$book_id,'pending','borrowed']);
        $existing = $stmt->fetch();
        if ($existing) {
            if ($existing['status'] == 'pending') {
                return ['ok'=>false,'msg'=>'You have already requested this book.'];
            } elseif ($existing['status'] == 'borrowed') {
                // Allow adding more copies to borrowed book
                $stmt = $this->pdo->prepare('SELECT available_copies FROM books WHERE id=?');
                $stmt->execute([$book_id]); $avail = intval($stmt->fetchColumn());
                if ($avail < $copies) return ['ok'=>false,'msg'=>'Not enough copies available.'];
                // Update existing borrowed record
                $new_copies = $existing['copies_borrowed'] + $copies;
                $this->pdo->prepare('UPDATE borrow_history SET copies_borrowed=? WHERE id=?')->execute([$new_copies, $existing['id']]);
                $this->pdo->prepare('UPDATE books SET available_copies = available_copies - ? WHERE id=?')->execute([$copies,$book_id]);

                // Send notification
                require_once __DIR__ . '/Notification.php';
                $notification = new Notification($this->pdo);
                $notification->create($user_id, 'approval', "Additional copies ({$copies}) added to your borrowed book ID {$book_id}. Total copies: {$new_copies}");

                // Log audit
                require_once __DIR__ . '/Audit.php';
                $audit = new Audit($this->pdo);
                $audit->log($user_id, 'add_copies', "Added {$copies} copies to borrowed book ID {$book_id}, total: {$new_copies}");

                return ['ok'=>true,'msg'=>'Additional copies added to your borrowed book.'];
            }
        }
        // check available (for approval later, but allow request)
        $stmt = $this->pdo->prepare('SELECT available_copies FROM books WHERE id=?');
        $stmt->execute([$book_id]); $avail = intval($stmt->fetchColumn());
        if ($avail < $copies) return ['ok'=>false,'msg'=>'Not enough copies available.'];
        // block all borrowing if user has overdue (re-check)
        if ($u->isBlocked($user_id)) return ['ok'=>false,'msg'=>'You have overdue books; borrowing is blocked.'];
        // insert as pending request with request_date
        $request_date = date('Y-m-d');
        $stmt = $this->pdo->prepare('INSERT INTO borrow_history (user_id,book_id,copies_borrowed,status,request_date) VALUES (?,?,?,?,?)');
        if (!$stmt->execute([$user_id,$book_id,$copies,'pending',$request_date])) {
            return ['ok'=>false,'msg'=>'Failed to submit borrow request. Please try again.'];
        }

        // Log audit
        require_once __DIR__ . '/Audit.php';
        $audit = new Audit($this->pdo);
        $audit->log($user_id, 'borrow_request', "Submitted borrow request for book ID {$book_id}, copies: {$copies}");

        return ['ok'=>true,'msg'=>'Borrow request submitted. Waiting for admin approval.'];
    }

    public function makeReturn($borrow_id,$user_id) {
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare('SELECT * FROM borrow_history WHERE id=? FOR UPDATE');
        $stmt->execute([$borrow_id]); $r = $stmt->fetch();
        if (!$r || $r['user_id'] != $user_id) { $this->pdo->rollBack(); return false; }
        if ($r['status'] !== 'borrowed') { $this->pdo->rollBack(); return false; }
        $return_date = date('Y-m-d');
        $status = ($return_date > $r['due_date']) ? 'overdue' : 'returned';
        $this->pdo->prepare('UPDATE borrow_history SET return_date=?, status=? WHERE id=?')->execute([$return_date,$status,$borrow_id]);
        $this->pdo->prepare('UPDATE books SET available_copies = available_copies + ? WHERE id=?')->execute([$r['copies_borrowed'],$r['book_id']]);

        // Send return notification
        require_once __DIR__ . '/Notification.php';
        $notification = new Notification($this->pdo);
        $notification->create($user_id, 'return', "You have successfully returned book ID {$r['book_id']}. Status: {$status}");

        // Check and notify reservations
        require_once __DIR__ . '/Reservation.php';
        $reservation = new Reservation($this->pdo);
        $reservation->checkAndNotifyAvailableBooks();

        // if returned and no more overdue, clear penalty
        $u = new User($this->pdo);
        $u->clearPenaltyIfNone($user_id);

        // Log audit
        require_once __DIR__ . '/Audit.php';
        $audit = new Audit($this->pdo);
        $audit->log($user_id, 'return_book', "Returned book ID {$r['book_id']}, borrow ID {$borrow_id}");

        $this->pdo->commit();
        return true;
    }

    public function checkAndMarkOverdue() {
        $today = date('Y-m-d');
        $this->pdo->prepare("UPDATE borrow_history SET status='overdue' WHERE status='borrowed' AND return_date IS NULL AND due_date < ?")->execute([$today]);

        // Send overdue notifications
        $stmt = $this->pdo->prepare("SELECT * FROM borrow_history WHERE status='overdue' AND return_date IS NULL");
        $stmt->execute();
        $overdues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require_once __DIR__ . '/Notification.php';
        $notification = new Notification($this->pdo);

        foreach ($overdues as $borrow) {
            // Check if notification already sent
            $check = $this->pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND type = ? AND message LIKE ?");
            $check->execute([$borrow['user_id'], 'overdue', "%{$borrow['id']}%"]);
            if (!$check->fetch()) {
                $notification->create($borrow['user_id'], 'overdue', "Book ID {$borrow['book_id']} is overdue. Please return it immediately to avoid fines.");
            }
        }

        // block users
        $stmt = $this->pdo->prepare("SELECT DISTINCT user_id FROM borrow_history WHERE status='overdue'");
        $stmt->execute();
        foreach ($stmt->fetchAll() as $u) {
            $this->pdo->prepare("UPDATE users SET penalty_status='blocked' WHERE id=?")->execute([$u['user_id']]);
        }

        // Calculate fines
        require_once __DIR__ . '/Fine.php';
        $fine = new Fine($this->pdo);
        $fine->calculateOverdueFines();
    }

    public function approveRequest($borrow_id) {
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare('SELECT * FROM borrow_history WHERE id=? FOR UPDATE');
        $stmt->execute([$borrow_id]); $r = $stmt->fetch();
        if (!$r || $r['status'] !== 'pending') { $this->pdo->rollBack(); return false; }
        // check available copies
        $stmt2 = $this->pdo->prepare('SELECT available_copies FROM books WHERE id=?');
        $stmt2->execute([$r['book_id']]); $avail = intval($stmt2->fetchColumn());
        if ($avail < $r['copies_borrowed']) { $this->pdo->rollBack(); return false; }
        // approve: set borrow_date, due_date, status, decrement copies
        $borrow_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+14 days'));
        $this->pdo->prepare('UPDATE borrow_history SET borrow_date=?, due_date=?, status=? WHERE id=?')->execute([$borrow_date,$due_date,'borrowed',$borrow_id]);
        $this->pdo->prepare('UPDATE books SET available_copies = available_copies - ? WHERE id=?')->execute([$r['copies_borrowed'],$r['book_id']]);

        // Send notification
        require_once __DIR__ . '/Notification.php';
        $notification = new Notification($this->pdo);
        $notification->create($r['user_id'], 'approval', "Your borrow request for book ID {$r['book_id']} has been approved. Due date: {$due_date}");

        // Log audit
        require_once __DIR__ . '/Audit.php';
        $audit = new Audit($this->pdo);
        $audit->log($_SESSION['user']['id'] ?? null, 'approve_request', "Approved borrow request ID {$borrow_id} for user {$r['user_id']}");

        $this->pdo->commit();
        return true;
    }

    public function rejectRequest($borrow_id) {
        $stmt = $this->pdo->prepare('SELECT * FROM borrow_history WHERE id=? AND status=?');
        $stmt->execute([$borrow_id, 'pending']); $r = $stmt->fetch();
        if (!$r) return false;

        $this->pdo->prepare("UPDATE borrow_history SET status='rejected' WHERE id=? AND status='pending'")->execute([$borrow_id]);

        // Send notification
        require_once __DIR__ . '/Notification.php';
        $notification = new Notification($this->pdo);
        $notification->create($r['user_id'], 'rejection', "Your borrow request for book ID {$r['book_id']} has been rejected.");

        // Log audit
        require_once __DIR__ . '/Audit.php';
        $audit = new Audit($this->pdo);
        $audit->log($_SESSION['user']['id'] ?? null, 'reject_request', "Rejected borrow request ID {$borrow_id} for user {$r['user_id']}");

        return true;
    }

    public function cancelRequest($borrow_id, $user_id) {
        $stmt = $this->pdo->prepare('SELECT * FROM borrow_history WHERE id=? AND user_id=? AND status=?');
        $stmt->execute([$borrow_id, $user_id, 'pending']); $r = $stmt->fetch();
        if (!$r) return false;

        $this->pdo->prepare("UPDATE borrow_history SET status='cancelled' WHERE id=? AND status='pending'")->execute([$borrow_id]);

        // Send notification
        require_once __DIR__ . '/Notification.php';
        $notification = new Notification($this->pdo);
        $notification->create($user_id, 'cancellation', "Your borrow request for book ID {$r['book_id']} has been cancelled.");

        // Log audit
        require_once __DIR__ . '/Audit.php';
        $audit = new Audit($this->pdo);
        $audit->log($user_id, 'cancel_request', "Cancelled borrow request ID {$borrow_id} for book ID {$r['book_id']}");

        return true;
    }
}
?>