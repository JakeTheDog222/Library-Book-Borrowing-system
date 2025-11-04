<?php
require_once __DIR__ . '/Database.php';

class Fine extends Database {
    public function __construct($pdo = null) {
        parent::__construct($pdo);
    }

    public function calculateOverdueFines() {
        $today = date('Y-m-d');
        $fine_rate = 5.00; // ₱5 per day overdue

        // Get all overdue borrows without fines
        $stmt = $this->pdo->prepare("
            SELECT bh.*, u.id as user_id
            FROM borrow_history bh
            JOIN users u ON bh.user_id = u.id
            WHERE bh.status = 'overdue' AND bh.return_date IS NULL
        ");
        $stmt->execute();
        $overdues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($overdues as $borrow) {
            $due_date = $borrow['due_date'];
            $days_overdue = (strtotime($today) - strtotime($due_date)) / (60 * 60 * 24);
            $days_overdue = max(0, floor($days_overdue));

            if ($days_overdue > 0) {
                $amount = $days_overdue * $fine_rate;

                // Check if fine already exists for this borrow
                $fine_check = $this->pdo->prepare('SELECT id, status FROM fines WHERE borrow_id = ?');
                $fine_check->execute([$borrow['id']]);
                $existing_fine = $fine_check->fetch();

                if (!$existing_fine) {
                    // Create new fine if none exists
                    $this->pdo->prepare('INSERT INTO fines (user_id, borrow_id, amount) VALUES (?, ?, ?)')->execute([$borrow['user_id'], $borrow['id'], $amount]);
                } elseif ($existing_fine['status'] === 'pending') {
                    // Update existing pending fine
                    $this->pdo->prepare('UPDATE fines SET amount = ? WHERE borrow_id = ? AND status = ?')->execute([$amount, $borrow['id'], 'pending']);
                }
                // If fine status is 'paid', don't update it - keep the paid status
            }
        }
    }

    public function getTotalPendingFines($user_id) {
        $stmt = $this->pdo->prepare('SELECT SUM(amount) as total FROM fines WHERE user_id = ? AND status = ?');
        $stmt->execute([$user_id, 'pending']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?: 0;
    }

    public function getUserFines($user_id) {
        $stmt = $this->pdo->prepare('SELECT f.*, b.title FROM fines f JOIN borrow_history bh ON f.borrow_id = bh.id JOIN books b ON bh.book_id = b.id WHERE f.user_id = ? ORDER BY f.created_at DESC');
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function payFine($fine_id, $user_id) {
        $stmt = $this->pdo->prepare('UPDATE fines SET status = ?, paid_at = NOW() WHERE id = ? AND user_id = ? AND status = ?');
        $result = $stmt->execute(['paid', $fine_id, $user_id, 'pending']);

        // After paying fine, check if all fines are paid and clear penalty if no overdues left
        if ($result) {
            $this->checkAndClearPenalty($user_id);
        }

        return $result;
    }

    public function isBlockedByFines($user_id) {
        $total_pending = $this->getTotalPendingFines($user_id);
        return $total_pending > 50.00; // Block if fines exceed ₱50
    }

    private function checkAndClearPenalty($user_id) {
        // Check if user has no more pending fines
        $total_pending = $this->getTotalPendingFines($user_id);
        if ($total_pending == 0) {
            // Check if user has no more overdue books
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM borrow_history WHERE user_id=? AND status='overdue'");
            $stmt->execute([$user_id]);
            $overdue_count = intval($stmt->fetchColumn());

            if ($overdue_count == 0) {
                // Clear penalty status
                $this->pdo->prepare("UPDATE users SET penalty_status='none' WHERE id=?")->execute([$user_id]);
            }
        }
    }
}
?>
