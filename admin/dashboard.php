<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Book.php';
require_once __DIR__ . '/../classes/Borrow.php';

if (!is_admin()) { header('Location: ../index.php'); exit; }

// handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['borrow_id'])) {
    $borrow = new Borrow($pdo);
    if ($_POST['action'] === 'approve') {
        $borrow->approveRequest($_POST['borrow_id']);
    } elseif ($_POST['action'] === 'reject') {
        $borrow->rejectRequest($_POST['borrow_id']);
    }
    header('Location: dashboard.php');
    exit;
}

// stats
$totalBooks = $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$overdue = $pdo->query("SELECT COUNT(*) FROM borrow_history WHERE status='overdue'")->fetchColumn();
$totalGenres = $pdo->query('SELECT COUNT(DISTINCT genre) FROM books')->fetchColumn();
$overdueItems = $pdo->query("SELECT bh.*, u.username, b.title FROM borrow_history bh JOIN users u ON bh.user_id = u.id JOIN books b ON bh.book_id = b.id WHERE bh.status='overdue' ORDER BY bh.due_date ASC")->fetchAll(PDO::FETCH_ASSOC);
$pendingRequests = $pdo->query("SELECT bh.*, u.username, b.title FROM borrow_history bh JOIN users u ON bh.user_id = u.id JOIN books b ON bh.book_id = b.id WHERE bh.status='pending' ORDER BY bh.id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Analytics data
$totalBorrows = $pdo->query('SELECT COUNT(*) FROM borrow_history')->fetchColumn();
$activeBorrows = $pdo->query('SELECT COUNT(*) FROM borrow_history WHERE status="borrowed"')->fetchColumn();
$overdueBorrows = $pdo->query('SELECT COUNT(*) FROM borrow_history WHERE status="overdue"')->fetchColumn();

// Weekly borrows for chart
$weeklyBorrows = $pdo->query("
    SELECT DATE_FORMAT(borrow_date, '%Y-%U') as week, COUNT(*) as count
    FROM borrow_history
    WHERE borrow_date IS NOT NULL AND borrow_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
    GROUP BY DATE_FORMAT(borrow_date, '%Y-%U')
    ORDER BY week DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

// Popular books
$popularBooks = $pdo->query("
    SELECT b.title, COUNT(bh.id) as borrow_count
    FROM books b
    LEFT JOIN borrow_history bh ON b.id = bh.book_id
    GROUP BY b.id, b.title
    ORDER BY borrow_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// User activity
$userActivity = $pdo->query("
    SELECT u.full_name, COUNT(bh.id) as borrow_count
    FROM users u
    LEFT JOIN borrow_history bh ON u.id = bh.user_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.full_name
    ORDER BY borrow_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Fines data
$totalFines = $pdo->query('SELECT SUM(amount) FROM fines WHERE status="pending"')->fetchColumn() ?: 0;
// Remove paid fines calculation from analytics

// Reviews data
$totalReviews = $pdo->query('SELECT COUNT(*) FROM book_reviews')->fetchColumn();
$avgRating = $pdo->query('SELECT AVG(rating) FROM book_reviews')->fetchColumn() ?: 0;

// Recent reviews
$recentReviews = $pdo->query("
    SELECT br.review, br.rating, br.created_at, u.full_name, b.title
    FROM book_reviews br
    JOIN users u ON br.user_id = u.id
    JOIN books b ON br.book_id = b.id
    ORDER BY br.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/library_system_modern/assets/css/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header class="no-left-radius">
        <h1><img src="../image/wmsulogo.png" alt="WMSU Logo">Library Book Borrowing System</h1>
        <nav>
            <ul>
                <li>Welcome, Admin!</li>
                <li><a href="../logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <aside class="sidebar">
            <h2>Admin Menu</h2>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                <li><a href="books.php"><i class="fas fa-book"></i>Manage Books</a></li>
                <li><a href="borrow_history.php"><i class="fas fa-history"></i>Borrow History</a></li>
                <li><a href="pending_requests.php"><i class="fas fa-clock"></i>Pending Requests</a></li>
                <li><a href="fines.php"><i class="fas fa-money-bill-wave"></i>Fines Management</a></li>
            </ul>
        </aside>
        <div class="content">
        <section class="dashboard-section">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0px;">
                <section id="overview">
                    <h2>Overview</h2>
                    <p>This is the Library Book Borrowing System for WMSU, where admins can view statistics, manage books, and monitor borrow history.</p>
                </section>

                <section>
                    <h2>Recent Reviews</h2>
                    <?php if(empty($recentReviews)): ?>
                        <p>No reviews yet.</p>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach($recentReviews as $review): ?>
                                <div style="background: #f8f9fa; padding: 15px; margin-bottom: 10px; border-radius: 8px; border-left: 4px solid #800000;">
                                    <blockquote style="margin: 0 0 10px 0; font-style: italic; color: #555;">
                                        "<?= htmlspecialchars($review['review']) ?>"
                                    </blockquote>
                                    <div style="font-size: 0.9em; color: #666;">
                                        <strong>By:</strong> <?= htmlspecialchars($review['full_name']) ?><br>
                                        <strong>Book:</strong> <?= htmlspecialchars($review['title']) ?><br>
                                        <strong>Rating:</strong> <?= str_repeat('★', $review['rating']) ?><br>
                                        <strong>Date:</strong> <?= date('M d, Y', strtotime($review['created_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </section>



        <section class="dashboard-section">
            <h2>Analytics Dashboard</h2>

            <!-- Key Metrics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3>Total Books</h3>
                    <p style="font-size: 2em; color: #800000;"><?=$totalBooks?></p>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3>Total Students</h3>
                    <p style="font-size: 2em; color: #800000;"><?=$totalStudents?></p>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3>Total Borrows</h3>
                    <p style="font-size: 2em; color: #800000;"><?=$totalBorrows?></p>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3>Active Borrows</h3>
                    <p style="font-size: 2em; color: #800000;"><?=$activeBorrows?></p>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3>Overdue Items</h3>
                    <p style="font-size: 2em; color: #dc3545;"><?=$overdueBorrows?></p>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3>Pending Fines</h3>
                    <p style="font-size: 2em; color: #ffc107;">₱<?=number_format($totalFines, 2)?></p>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3>Total Genres</h3>
                    <p style="font-size: 2em; color: #800000;"><?=$totalGenres?></p>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                    <h3>Pending Requests</h3>
                    <p style="font-size: 2em; color: #800000;"><?=$pending = $pdo->query("SELECT COUNT(*) FROM borrow_history WHERE status='pending'")->fetchColumn()?></p>
                </div>
            </div>

            <!-- Charts Row -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <!-- Weekly Borrows Chart -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>Weekly Borrows (Last 12 Weeks)</h3>
                    <canvas id="weeklyBorrowsChart"></canvas>
                </div>

                <!-- Popular Books Chart -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>Most Popular Books</h3>
                    <canvas id="popularBooksChart"></canvas>
                </div>
            </div>

            <!-- Tables Row -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- User Activity -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>Most Active Users</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <th style="text-align: left; padding: 8px;">User</th>
                                <th style="text-align: right; padding: 8px;">Borrows</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($userActivity as $user): ?>
                                <tr>
                                    <td style="padding: 8px;"><?=$user['full_name']?></td>
                                    <td style="text-align: right; padding: 8px;"><?=$user['borrow_count']?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- System Stats -->
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>System Statistics</h3>
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">Total Reviews: <?=$totalReviews?></li>
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">Average Rating: <?=number_format($avgRating, 1)?> ★</li>
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">Total Fines Collected: ₱0.00</li>
                        <li style="padding: 8px 0;">Return Rate: <?= $totalBorrows > 0 ? round((($totalBorrows - $activeBorrows) / $totalBorrows) * 100, 1) : 0 ?>%</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="dashboard-section">
            <h2>Pending Borrow Requests</h2>
            <?php if(empty($pendingRequests)): ?>
                <p>No pending requests.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Book Title</th>
                                <th>Copies</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pendingRequests as $req): ?>
                                <tr>
                                    <td><?=$req['username']?></td>
                                    <td><?=$req['title']?></td>
                                    <td><?=$req['copies_borrowed']?></td>
                                    <td><?=$req['id']?></td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="borrow_id" value="<?=$req['id']?>">
                                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                        </form>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="borrow_id" value="<?=$req['id']?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section">
            <h2>Overdue Items</h2>
            <?php if(empty($overdueItems)): ?>
                <p>No overdue items.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Book Title</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($overdueItems as $item): ?>
                                <tr>
                                    <td><?=$item['username']?></td>
                                    <td><?=$item['title']?></td>
                                    <td><?=$item['borrow_date']?></td>
                                    <td><?=$item['due_date']?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section">
            <h2>Quick Actions</h2>
            <div class="borrow-buttons">
                <a href="books.php" class="btn">Manage Books</a>
                <a href="borrow_history.php" class="btn">Borrow History</a>
                <a href="pending_requests.php" class="btn">Pending Requests</a>

            </div>
        </section>
        </div>


    </main>

    <script>
        // Weekly Borrows Chart
        const weeklyCtx = document.getElementById('weeklyBorrowsChart').getContext('2d');
        const weeklyData = <?=json_encode(array_reverse($weeklyBorrows))?>;

        new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: weeklyData.map(item => item.week),
                datasets: [{
                    label: 'Borrows',
                    data: weeklyData.map(item => item.count),
                    borderColor: '#800000',
                    backgroundColor: 'rgba(128, 0, 0, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Popular Books Chart
        const popularCtx = document.getElementById('popularBooksChart').getContext('2d');
        const popularData = <?=json_encode($popularBooks)?>;

        new Chart(popularCtx, {
            type: 'bar',
            data: {
                labels: popularData.map(item => item.title.length > 20 ? item.title.substring(0, 20) + '...' : item.title),
                datasets: [{
                    label: 'Borrow Count',
                    data: popularData.map(item => item.borrow_count),
                    backgroundColor: '#800000',
                    borderColor: '#600000',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>

    <footer>
        © 2025 WMSU Library — All Rights Reserved
    </footer>
</body>
</html>
