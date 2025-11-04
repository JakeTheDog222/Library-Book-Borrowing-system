<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Review.php';
require_once __DIR__ . '/../classes/Notification.php';

if (!is_student()) {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user']['id'];
    $book_id = intval($_POST['book_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review'] ?? '');

    if ($rating < 1 || $rating > 5) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid rating']);
        exit;
    }

    $review = new Review($pdo);
    $result = $review->addReview($user_id, $book_id, $rating, $review_text);

    if ($result['ok']) {
        // Notify admin about the new review
        $notification = new Notification($pdo);
        $book_title = $pdo->prepare('SELECT title FROM books WHERE id = ?');
        $book_title->execute([$book_id]);
        $book = $book_title->fetch();
        $title = $book['title'] ?? 'Unknown Book';

        $message = "New review submitted for '{$title}' by {$_SESSION['user']['full_name']}. Rating: {$rating}/5";
        if (!empty($review_text)) {
            $message .= ". Review: " . substr($review_text, 0, 100) . (strlen($review_text) > 100 ? '...' : '');
        }

        // Get admin user ID (assuming admin role)
        $admin_stmt = $pdo->prepare('SELECT id FROM users WHERE role = ? LIMIT 1');
        $admin_stmt->execute(['admin']);
        $admin = $admin_stmt->fetch();
        if ($admin) {
            $notification->create($admin['id'], 'review', $message);
        }
    }

    echo json_encode($result);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
?>
