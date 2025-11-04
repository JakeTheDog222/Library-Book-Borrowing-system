<?php
require_once __DIR__ . '/Database.php';

class Reservation extends Database {
    public function __construct($pdo = null) {
        parent::__construct($pdo);
    }

    public function reserveBook($user_id, $book_id) {
        // Check if book is available
        $stmt = $this->pdo->prepare('SELECT available_copies FROM books WHERE id = ?');
        $stmt->execute([$book_id]);
        $available = $stmt->fetchColumn();

        if ($available > 0) {
            return ['ok' => false, 'msg' => 'Book is currently available. No need to reserve.'];
        }

        // Check if already reserved
        $stmt = $this->pdo->prepare('SELECT id FROM reservations WHERE user_id = ? AND book_id = ? AND status = ?');
        $stmt->execute([$user_id, $book_id, 'active']);
        if ($stmt->fetch()) {
            return ['ok' => false, 'msg' => 'You have already reserved this book.'];
        }

        // Create reservation
        $stmt = $this->pdo->prepare('INSERT INTO reservations (user_id, book_id) VALUES (?, ?)');
        if ($stmt->execute([$user_id, $book_id])) {
            return ['ok' => true, 'msg' => 'Book reserved successfully. You will be notified when it becomes available.'];
        }
        return ['ok' => false, 'msg' => 'Failed to reserve book.'];
    }

    public function cancelReservation($reservation_id, $user_id) {
        $stmt = $this->pdo->prepare('UPDATE reservations SET status = ? WHERE id = ? AND user_id = ? AND status = ?');
        return $stmt->execute(['cancelled', $reservation_id, $user_id, 'active']);
    }

    public function checkAndNotifyAvailableBooks() {
        // Find active reservations where book is now available
        $stmt = $this->pdo->prepare("
            SELECT r.*, b.title, u.username
            FROM reservations r
            JOIN books b ON r.book_id = b.id
            JOIN users u ON r.user_id = u.id
            WHERE r.status = 'active' AND r.notified = FALSE AND b.available_copies > 0
        ");
        $stmt->execute();
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservations as $reservation) {
            // Create notification
            require_once __DIR__ . '/Notification.php';
            $notification = new Notification($this->pdo);
            $notification->create(
                $reservation['user_id'],
                'reservation_available',
                "The book '{$reservation['title']}' you reserved is now available for borrowing."
            );

            // Mark as notified
            $this->pdo->prepare('UPDATE reservations SET notified = TRUE WHERE id = ?')->execute([$reservation['id']]);
        }
    }

    public function getUserReservations($user_id) {
        $stmt = $this->pdo->prepare('SELECT r.*, b.title FROM reservations r JOIN books b ON r.book_id = b.id WHERE r.user_id = ? AND r.status = ? ORDER BY r.reserved_at DESC');
        $stmt->execute([$user_id, 'active']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
