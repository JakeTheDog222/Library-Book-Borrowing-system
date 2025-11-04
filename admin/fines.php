<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Fine.php';
require_once __DIR__ . '/../classes/User.php';

if (!is_admin()) { header('Location: ../index.php'); exit; }



// Get all fines with user information
$fines = $pdo->query("
    SELECT f.*, u.username, u.full_name, u.penalty_status,
           b.title as book_title, bh.borrow_date, bh.due_date
    FROM fines f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN borrow_history bh ON f.borrow_id = bh.id
    LEFT JOIN books b ON bh.book_id = b.id
    ORDER BY f.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Separate pending and paid fines
$pendingFines = array_filter($fines, function($f) { return $f['status'] === 'pending'; });
$paidFines = array_filter($fines, function($f) { return $f['status'] === 'paid'; });

// Get users with outstanding fines
$usersWithFines = $pdo->query("
    SELECT u.id, u.username, u.full_name,
           SUM(f.amount) as total_pending_fines,
           COUNT(f.id) as fine_count
    FROM users u
    JOIN fines f ON u.id = f.user_id
    WHERE f.status = 'pending'
    GROUP BY u.id, u.username, u.full_name
    ORDER BY total_pending_fines DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fines Management - Admin</title>
    <link rel="stylesheet" href="/library_system_modern/assets/css/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                <li><a href="fines.php" class="active"><i class="fas fa-money-bill-wave"></i>Fines Management</a></li>
            </ul>
        </aside>

        <div class="content">
            <section class="dashboard-section">
                <h2>Fines Management</h2>
                <p>Manage outstanding fines and user blocking status.</p>
            </section>

            <section class="dashboard-section">
                <h2>Users with Outstanding Fines</h2>
                <?php if(empty($usersWithFines)): ?>
                    <p>No users with outstanding fines.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Username</th>
                                    <th>Total Pending Fines</th>
                                    <th>Number of Fines</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($usersWithFines as $user): ?>
                                    <tr>
                                        <td><?=htmlspecialchars($user['full_name'])?></td>
                                        <td><?=htmlspecialchars($user['username'])?></td>
                                        <td>₱<?=number_format($user['total_pending_fines'], 2)?></td>
                                        <td><?=$user['fine_count']?></td>
                                        <td>
                                            <?php
                                            $userObj = new User($pdo);
                                            if($userObj->isBlocked($user['id'])): ?>
                                                <span style="color: #dc3545; font-weight: bold;">Blocked</span>
                                            <?php else: ?>
                                                <span style="color: #28a745;">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            No actions available
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dashboard-section">
                <h2>Pending Fines History</h2>
                <?php if(empty($pendingFines)): ?>
                    <p>No pending fines.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Book</th>
                                    <th>Amount</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Created</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pendingFines as $fine): ?>
                                    <tr>
                                        <td><?=htmlspecialchars($fine['full_name'])?> (<?=htmlspecialchars($fine['username'])?>)</td>
                                        <td><?=htmlspecialchars($fine['book_title'] ?: 'N/A')?></td>
                                        <td>₱<?=number_format($fine['amount'], 2)?></td>
                                        <td><?=$fine['borrow_date'] ?: 'N/A'?></td>
                                        <td><?=$fine['due_date'] ?: 'N/A'?></td>
                                        <td><?=date('M d, Y H:i', strtotime($fine['created_at']))?></td>
                                        <td><span style="color: #ffc107; font-weight: bold;"><?=$fine['status']?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dashboard-section">
                <h2>Paid Fines History</h2>
                <?php if(empty($paidFines)): ?>
                    <p>No paid fines.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Book</th>
                                    <th>Amount</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Paid Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($paidFines as $fine): ?>
                                    <tr>
                                        <td><?=htmlspecialchars($fine['full_name'])?> (<?=htmlspecialchars($fine['username'])?>)</td>
                                        <td><?=htmlspecialchars($fine['book_title'] ?: 'N/A')?></td>
                                        <td>₱<?=number_format($fine['amount'], 2)?></td>
                                        <td><?=$fine['borrow_date'] ?: 'N/A'?></td>
                                        <td><?=$fine['due_date'] ?: 'N/A'?></td>
                                        <td><?=date('M d, Y H:i', strtotime($fine['paid_at']))?></td>
                                        <td><span style="color: #28a745; font-weight: bold;">Paid</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>


        </div>
    </main>

    <footer>
        © 2025 WMSU Library — All Rights Reserved
    </footer>
</body>
</html>
