<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Borrow.php';

if (!is_admin()) { header('Location: ../index.php'); exit; }

// handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['borrow_id'])) {
    $borrow = new Borrow($pdo);
    if ($_POST['action'] === 'approve') {
        $borrow->approveRequest($_POST['borrow_id']);
        flash_set('Request approved.');
    } elseif ($_POST['action'] === 'reject') {
        $borrow->rejectRequest($_POST['borrow_id']);
        flash_set('Request rejected.');
    }
    header('Location: pending_requests.php');
    exit;
}

// get pending requests
$pendingRequests = $pdo->query("SELECT bh.*, u.username, u.full_name, b.title, b.author, b.genre FROM borrow_history bh JOIN users u ON bh.user_id = u.id JOIN books b ON bh.book_id = b.id WHERE bh.status='pending' ORDER BY bh.id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Borrow Requests</title>
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
        <aside class="sidebar">
            <h2>Admin Menu</h2>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                <li><a href="books.php"><i class="fas fa-book"></i>Manage Books</a></li>
                <li><a href="borrow_history.php"><i class="fas fa-history"></i>Borrow History</a></li>
                <li><a href="pending_requests.php"><i class="fas fa-clock"></i>Pending Requests</a></li>

            </ul>
        </aside>
        <div class="content">
            <section class="dashboard-section">
                <h2>Pending Borrow Requests</h2>
                <div class="search-bar" style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
                    <input type="text" id="searchInput" placeholder="Search by user, book title, or author..." style="padding: 6px; border: 1px solid #ccc; border-radius: 5px; flex: 1; max-width: 300px;">
                    <select id="genreFilter" style="padding: 6px; border: 1px solid #ccc; border-radius: 5px; max-width: 200px;">
                        <option value="">All Genres</option>
                        <?php
                        $genres = $pdo->query('SELECT DISTINCT genre FROM books ORDER BY genre')->fetchAll(PDO::FETCH_COLUMN);
                        foreach($genres as $genre): ?>
                            <option value="<?=htmlspecialchars($genre)?>"><?=htmlspecialchars($genre)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="table-container">
                    <table id="pendingTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Full Name</th>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Genre</th>
                                <th>Copies</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingRequests)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #666; font-style: italic;">No pending requests at this time.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($pendingRequests as $req): ?>
                                    <tr>
                                        <td><?=$req['username']?></td>
                                        <td><?=$req['full_name']?></td>
                                        <td><?=$req['title']?></td>
                                        <td><?=$req['author']?></td>
                                        <td><?=$req['genre']?></td>
                                        <td><?=$req['copies_borrowed']?></td>
                                        <td><?=$req['request_date']?></td>
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
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div id="noResults" style="display: none; text-align: center; padding: 20px; color: #666; font-style: italic;">No pending requests found matching your search.</div>
                </div>
            </section>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById('searchInput');
        const genreFilter = document.getElementById('genreFilter');
        const table = document.getElementById('pendingTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedGenre = genreFilter.value.toLowerCase();
            let visibleRows = 0;

            for (let i = 0; i < rows.length; i++) {
                const user = rows[i].getElementsByTagName('td')[0].textContent.toLowerCase();
                const fullName = rows[i].getElementsByTagName('td')[1].textContent.toLowerCase();
                const title = rows[i].getElementsByTagName('td')[2].textContent.toLowerCase();
                const author = rows[i].getElementsByTagName('td')[3].textContent.toLowerCase();
                const genre = rows[i].getElementsByTagName('td')[4].textContent.toLowerCase();

                const matchesSearch = user.includes(searchTerm) || fullName.includes(searchTerm) || title.includes(searchTerm) || author.includes(searchTerm);
                const matchesGenre = selectedGenre === '' || genre === selectedGenre;

                if (matchesSearch && matchesGenre) {
                    rows[i].style.display = '';
                    visibleRows++;
                } else {
                    rows[i].style.display = 'none';
                }
            }

            const noResults = document.getElementById('noResults');
            if (visibleRows === 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        searchInput.addEventListener('keyup', filterTable);
        genreFilter.addEventListener('change', filterTable);
    </script>

    <footer>
        © 2025 WMSU Library — All Rights Reserved
    </footer>
</body>
</html>
