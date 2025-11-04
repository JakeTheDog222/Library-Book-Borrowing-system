<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Borrow.php';
require_once __DIR__ . '/../classes/Audit.php';
if (!is_admin()) { header('Location: ../index.php'); exit; }
// handle mark returned
if (isset($_GET['mark_returned'])) {
    $id = intval($_GET['mark_returned']);
    $stmt = $pdo->prepare('SELECT * FROM borrow_history WHERE id=?'); $stmt->execute([$id]); $r = $stmt->fetch();
    if ($r && $r['status'] === 'borrowed') {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE borrow_history SET return_date=?, status=? WHERE id=?')->execute([date('Y-m-d'), 'returned', $id]);
        $pdo->prepare('UPDATE books SET available_copies = available_copies + ? WHERE id=?')->execute([$r['copies_borrowed'],$r['book_id']]);

        // Send return notification
        require_once __DIR__ . '/../classes/Notification.php';
        $notification = new Notification($pdo);
        $notification->create($r['user_id'], 'return', "Your book '{$r['title']}' has been marked as returned by admin.");

        // Log audit
        $audit = new Audit($pdo);
        $audit->log($_SESSION['user']['id'], 'mark_returned', "Marked borrow ID {$id} as returned");

        $pdo->commit();
    }
    header('Location: borrow_history.php'); exit;
}

// handle delete
if (isset($_POST['delete_record'])) {
    $id = intval($_POST['delete_record']);
    $stmt = $pdo->prepare('SELECT * FROM borrow_history WHERE id=?'); $stmt->execute([$id]); $r = $stmt->fetch();
    if ($r) {
        $pdo->beginTransaction();
        try {
            if ($r['status'] === 'borrowed') {
                // Restore available copies if still borrowed
                $pdo->prepare('UPDATE books SET available_copies = available_copies + ? WHERE id=?')->execute([$r['copies_borrowed'], $r['book_id']]);
            }
            $pdo->prepare('DELETE FROM borrow_history WHERE id=?')->execute([$id]);

            // Log audit
            $audit = new Audit($pdo);
            $audit->log($_SESSION['user']['id'], 'delete_borrow_record', "Deleted borrow record ID {$id} for user {$r['user_id']}, book {$r['book_id']}");

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
    header('Location: borrow_history.php'); exit;
}

$rows = $pdo->query('SELECT bh.*, u.full_name, b.title FROM borrow_history bh JOIN users u ON u.id=bh.user_id JOIN books b ON b.id=bh.book_id ORDER BY bh.borrow_date DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow History</title>
    <link rel="stylesheet" href="/library_system_modern/assets/css/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="no-left-radius">
        <h1><img src="../image/wmsulogo.png" alt="WMSU Logo">Library Book Borrowing System</h1>
        <nav>
            <ul>

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
            <h2>Borrow History</h2>
            <h5 class='mt-3'>All Records</h5>
            <div class="search-bar" style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
                <input type="text" id="searchInput" placeholder="Search by user or book title..." style="padding: 6px; border: 1px solid #ccc; border-radius: 5px; flex: 1; max-width: 300px;">
                <select id="statusFilter" style="padding: 6px; border: 1px solid #ccc; border-radius: 5px; max-width: 200px;">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="borrowed">Borrowed</option>
                    <option value="returned">Returned</option>
                    <option value="overdue">Overdue</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="table-container">
                <table class='table table-striped' id="historyTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Book</th>
                            <th>Borrowed</th>
                            <th>Due</th>
                            <th>Returned</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $r): ?>
                            <tr>
                                <td><?=$r['id']?></td>
                                <td><?=htmlspecialchars($r['full_name'])?></td>
                                <td><?=htmlspecialchars($r['title'])?></td>
                                <td><?=$r['borrow_date'] ?: $r['request_date'] ?: '-'?></td>
                                <td><?=$r['due_date']?></td>
                                <td><?=$r['return_date']?: '-'?></td>
                                <td><?=$r['status']?></td>
                                <td>
                                    <button class='btn btn-sm btn-danger delete-btn' data-id='<?=$r['id']?>' data-title='<?=htmlspecialchars($r['full_name'])?> - <?=htmlspecialchars($r['title'])?>'>Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="noResults" style="display: none; text-align: center; padding: 20px; color: #666; font-style: italic;">No records found matching your search.</div>
            </div>
        </section>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>Confirm Deletion</h3>
                <p>Are you sure you want to delete the borrow record for "<span id="recordTitle"></span>"? This action cannot be undone.</p>
                <div class="modal-buttons">
                    <button id="cancelDelete" class="btn">Cancel</button>
                    <button id="confirmDelete" class="btn" style="background-color: #dc3545; color: white;">Delete</button>
                </div>
            </div>
        </div>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const table = document.getElementById('historyTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedStatus = statusFilter.value.toLowerCase();
            let visibleRows = 0;

            for (let i = 0; i < rows.length; i++) {
                const user = rows[i].getElementsByTagName('td')[1].textContent.toLowerCase();
                const book = rows[i].getElementsByTagName('td')[2].textContent.toLowerCase();
                const status = rows[i].getElementsByTagName('td')[6].textContent.toLowerCase();

                const matchesSearch = user.includes(searchTerm) || book.includes(searchTerm);
                const matchesStatus = selectedStatus === '' || status === selectedStatus;

                if (matchesSearch && matchesStatus) {
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
        statusFilter.addEventListener('change', filterTable);

        // Modal functionality
        const modal = document.getElementById('deleteModal');
        const closeBtn = document.getElementsByClassName('close')[0];
        const cancelBtn = document.getElementById('cancelDelete');
        const confirmBtn = document.getElementById('confirmDelete');
        const recordTitleSpan = document.getElementById('recordTitle');
        let recordIdToDelete = null;

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                recordIdToDelete = this.getAttribute('data-id');
                const recordTitle = this.getAttribute('data-title');
                recordTitleSpan.textContent = recordTitle;
                modal.style.display = 'block';
            });
        });

        closeBtn.onclick = function() {
            modal.style.display = 'none';
        };

        cancelBtn.onclick = function() {
            modal.style.display = 'none';
        };

        confirmBtn.onclick = function() {
            if (recordIdToDelete) {
                const form = document.createElement('form');
                form.method = 'post';
                form.action = 'borrow_history.php';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_record';
                input.value = recordIdToDelete;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        };

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        };
    </script>

    <footer>
        © 2025 WMSU Library — All Rights Reserved
    </footer>
</body>
</html>
