<?php
require_once __DIR__ . '/Database.php';

class Audit extends Database {
    public function __construct($pdo = null) {
        parent::__construct($pdo);
    }

    public function log($user_id, $action, $details = null, $ip_address = null) {
        if (!$ip_address) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }

        $stmt = $this->pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
        return $stmt->execute([$user_id, $action, $details, $ip_address]);
    }

    public function getLogs($limit = 100, $user_id = null) {
        $sql = 'SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id';
        $params = [];

        if ($user_id) {
            $sql .= ' WHERE a.user_id = ?';
            $params[] = $user_id;
        }

        $sql .= ' ORDER BY a.created_at DESC LIMIT ?';
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
