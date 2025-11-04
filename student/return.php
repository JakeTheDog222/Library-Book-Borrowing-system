<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Borrow.php';
require_once __DIR__ . '/../classes/Book.php';

if (!is_student()) { header('Location: ../index.php'); exit; }
$uid = $_SESSION['user']['id'];
$id = intval($_GET['id'] ?? 0);
$book_id = 0;
if ($id) {
    $borrow = new Borrow($pdo);
    $ok = $borrow->makeReturn($id, $uid);
    if ($ok) {
        // Get book details for review modal
        $stmt = $pdo->prepare('SELECT book_id FROM borrow_history WHERE id = ?');
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        $book_id = $record['book_id'] ?? 0;
        $book = (new Book($pdo))->get($book_id);
    } else {
        flash_set('Return failed.');
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book</title>
    <link rel="stylesheet" href="/library_system_modern/assets/css/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="no-left-radius">
        <h1><img src="../image/wmsulogo.png" alt="WMSU Logo">Library Book Borrowing System</h1>
        <nav>
            <ul>
                <li>Welcome, <?= htmlspecialchars($_SESSION['user']['full_name']) ?>!</li>
                <li><a href="../logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="content">
            <h2>Book Returned Successfully</h2>
            <p>Your book has been returned. Would you like to leave a review?</p>

            <?php if ($book_id && $book): ?>
            <div id="reviewModal" style="display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; width: 400px; max-width: 90%;">
                    <h3>Review: <?= htmlspecialchars($book['title']) ?></h3>
                    <form id="reviewForm">
                        <input type="hidden" name="book_id" value="<?= $book_id ?>">
                        <div style="margin-bottom: 15px;">
                            <label>Rating:</label><br>
                            <div style="position: relative; width: 100%; margin: 20px 0;">
                                <input type="range" name="rating" id="ratingSlider" min="1" max="5" value="3" style="width: 100%;" required>
                                <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                                    <span>1</span>
                                    <span>2</span>
                                    <span>3</span>
                                    <span>4</span>
                                    <span>5</span>
                                </div>
                                <div style="text-align: center; margin-top: 10px; font-weight: bold;">
                                    <span id="ratingValue">3</span> - <span id="ratingText">Neutral</span>
                                </div>
                            </div>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label for="review">Review (optional):</label><br>
                            <textarea name="review" id="review" rows="4" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
                        </div>
                        <div style="text-align: right;">
                            <button type="button" onclick="closeModal()" class="btn btn-secondary">Skip</button>
                            <button type="submit" class="btn">Submit Review</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <a href="dashboard.php" class="btn btn-primary" style="background-color: #dc3545;">Back to Dashboard</a>
        </div>
    </main>

    <footer>
        © 2025 WMSU Library — All Rights Reserved
    </footer>

    <script>
        function closeModal() {
            document.getElementById('reviewModal').style.display = 'none';
        }

        // Update rating display
        const ratingSlider = document.getElementById('ratingSlider');
        const ratingValue = document.getElementById('ratingValue');
        const ratingText = document.getElementById('ratingText');

        const ratingTexts = {
            1: 'Very Dissatisfied',
            2: 'Dissatisfied',
            3: 'Neutral',
            4: 'Satisfied',
            5: 'Very Satisfied'
        };

        ratingSlider.addEventListener('input', function() {
            ratingValue.textContent = this.value;
            ratingText.textContent = ratingTexts[this.value];
        });

        document.getElementById('reviewForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('submit_review.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.msg);
                if (data.ok) {
                    closeModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the review.');
            });
        });
    </script>
</body>
</html>
<?php exit; ?>
