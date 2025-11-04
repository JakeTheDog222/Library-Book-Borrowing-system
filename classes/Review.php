<?php
require_once __DIR__ . '/Database.php';

class Review extends Database {
    public function __construct($pdo = null) {
        parent::__construct($pdo);
    }

    public function addReview($user_id, $book_id, $rating, $review_text = null) {
        // Check if user has returned this book
        $stmt = $this->pdo->prepare("SELECT id FROM borrow_history WHERE user_id = ? AND book_id = ? AND status IN ('returned', 'overdue')");
        $stmt->execute([$user_id, $book_id]);
        if (!$stmt->fetch()) {
            return ['ok' => false, 'msg' => 'You can only review books you have returned.'];
        }

        // Insert or update review
        $stmt = $this->pdo->prepare('INSERT INTO book_reviews (user_id, book_id, rating, review) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)');
        if ($stmt->execute([$user_id, $book_id, $rating, $review_text])) {
            return ['ok' => true, 'msg' => 'Review submitted successfully.'];
        }
        return ['ok' => false, 'msg' => 'Failed to submit review.'];
    }

    public function getBookReviews($book_id) {
        $stmt = $this->pdo->prepare('SELECT r.*, u.full_name FROM book_reviews r JOIN users u ON r.user_id = u.id WHERE r.book_id = ? ORDER BY r.created_at DESC');
        $stmt->execute([$book_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAverageRating($book_id) {
        $stmt = $this->pdo->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM book_reviews WHERE book_id = ?');
        $stmt->execute([$book_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserReviews($user_id) {
        $stmt = $this->pdo->prepare('SELECT r.*, b.title FROM book_reviews r JOIN books b ON r.book_id = b.id WHERE r.user_id = ? ORDER BY r.created_at DESC');
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
