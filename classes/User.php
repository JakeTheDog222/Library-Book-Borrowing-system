<?php
require_once __DIR__ . '/Database.php';

class User extends Database {
    public function __construct($pdo=null){ parent::__construct($pdo); }
    public function getById($id) {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id=?');
        $stmt->execute([$id]); return $stmt->fetch();
    }
    public function isBlocked($user_id) {
        $stmt = $this->pdo->prepare('SELECT penalty_status FROM users WHERE id=?');
        $stmt->execute([$user_id]); $r = $stmt->fetch();
        if (!$r) return true;
        if ($r['penalty_status'] === 'blocked') return true;
        // check overdue
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM borrow_history WHERE user_id=? AND status='overdue'");
        $stmt->execute([$user_id]);
        $c = intval($stmt->fetchColumn());
        if ($c > 0) {
            $this->pdo->prepare("UPDATE users SET penalty_status='blocked' WHERE id=?")->execute([$user_id]);
            return true;
        }
        return false;
    }
    public function clearPenaltyIfNone($user_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM borrow_history WHERE user_id=? AND status='overdue'");
        $stmt->execute([$user_id]);
        if (intval($stmt->fetchColumn()) === 0) {
            $this->pdo->prepare("UPDATE users SET penalty_status='none' WHERE id=?")->execute([$user_id]);
        }
    }

    public function unblockUser($user_id) {
        $this->pdo->prepare("UPDATE users SET penalty_status='none' WHERE id=?")->execute([$user_id]);
    }
}
?>