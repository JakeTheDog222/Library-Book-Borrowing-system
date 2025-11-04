<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Borrow.php';
require_once __DIR__ . '/../classes/Book.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../classes/Fine.php';
require_once __DIR__ . '/../classes/Reservation.php';
if (!is_student()) { header('Location: ../index.php'); exit; }
// mark overdues
(new Borrow($pdo))->checkAndMarkOverdue();
$uid = $_SESSION['user']['id'];
$my = $pdo->prepare('SELECT bh.*, b.title FROM borrow_history bh JOIN books b ON b.id=bh.book_id WHERE bh.user_id=? ORDER BY bh.id DESC');
$my->execute([$uid]); $records = $my->fetchAll();
$books = (new Book($pdo))->all();
$stmt = $pdo->prepare('SELECT b.genre, COUNT(*) as count FROM borrow_history bh JOIN books b ON b.id = bh.book_id WHERE bh.user_id = ? GROUP BY b.genre ORDER BY count DESC');
$stmt->execute([$uid]);
$borrowedGenres = $stmt->fetchAll(PDO::FETCH_ASSOC);
$bookTitles = $pdo->query('SELECT title FROM books ORDER BY title')->fetchAll(PDO::FETCH_COLUMN);
$genres = $pdo->query('SELECT DISTINCT genre FROM books ORDER BY genre')->fetchAll(PDO::FETCH_COLUMN);
$blocked = (new User($pdo))->isBlocked($uid);

// Get notifications
$notification = new Notification($pdo);
$notifications = $notification->getUnread($uid);

// Get fines
$fine = new Fine($pdo);
$totalFines = $fine->getTotalPendingFines($uid);
$userFines = $fine->getUserFines($uid);
// Show both pending and paid fines for history

// Get reservations
$reservation = new Reservation($pdo);
$reservations = $reservation->getUserReservations($uid);

// Get most borrowed book
$mostBorrowedBook = $pdo->prepare('SELECT b.title, COUNT(*) as borrow_count FROM borrow_history bh JOIN books b ON bh.book_id = b.id WHERE bh.user_id = ? GROUP BY bh.book_id ORDER BY borrow_count DESC LIMIT 1');
$mostBorrowedBook->execute([$uid]);
$mostBorrowed = $mostBorrowedBook->fetch();

// Get most borrowed genre
$mostBorrowedGenre = $pdo->prepare('SELECT b.genre, COUNT(*) as genre_count FROM borrow_history bh JOIN books b ON bh.book_id = b.id WHERE bh.user_id = ? GROUP BY b.genre ORDER BY genre_count DESC LIMIT 1');
$mostBorrowedGenre->execute([$uid]);
$mostGenre = $mostBorrowedGenre->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="/library_system_modern/assets/css/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="no-left-radius">
        <h1>Library Book Borrowing System</h1>
        <nav>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="borrow.php">Borrow Book</a></li>
                <li><a href="return.php">Return Book</a></li>
                <li>Welcome, <?= htmlspecialchars($_SESSION['user']['full_name']) ?>!</li>
                <li><a href="../logout.php" class="logout">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div class="content">
        <section class="hero">
            <h1>Welcome to Your Library Dashboard</h1>
            <p>Manage your borrowed books, return items, and explore available books to borrow.</p>
        </section>
        <section id="overview" class="zigzag-section">
            <div class="text">
                <h2>Library</h2>
                <p>The purpose of this Library Book Borrowing System is to provide WMSU students with an efficient and user-friendly platform to borrow, return, and manage books. It promotes fair access to educational resources, ensures accountability through fines and penalties for overdue items, and supports the academic community by facilitating easy book reservations and notifications.</p>
                <?php if($blocked): ?>
                    <div style="color: red; font-weight: bold;">Your account is blocked from borrowing due to overdue items.</div>
                <?php endif; ?>
                <?php if($totalFines > 0): ?>
                    <div style="color: orange; font-weight: bold;">You have outstanding fines: ₱<?= number_format($totalFines, 2) ?>. Borrowing is blocked until all fines are paid.</div>
                <?php endif; ?>
                <?php if(!empty($notifications)): ?>
                    <div style="background: #e8f4fd; border: 1px solid #1e88e5; padding: 10px; border-radius: 5px; margin: 10px 0;">
                        <h3>Notifications (<?= count($notifications) ?>)</h3>
                        <?php foreach($notifications as $n): ?>
                            <div id="notification-<?= $n['id'] ?>" style="margin: 5px 0; padding: 5px; background: white; border-radius: 3px; position: relative;">
                                <strong><?= ucfirst($n['type']) ?>:</strong> <?= htmlspecialchars($n['message']) ?>
                                <button onclick="markAsRead(<?= $n['id'] ?>)" style="position: absolute; top: -2px; right: 5px; color: #1e88e5; background: none; border: none; cursor: pointer;">Mark Read</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="image">
                <img src="../image/wmsulogo.png" alt="WMSU Logo">
            </div>
        </section>

        <section id="borrowed" class="zigzag-section">
            <div class="text">
                <h2>Your Borrowed Books</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Borrowed</th>
                                <th>Due</th>
                                <th>Copies</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($records)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #666; font-style: italic;">No borrowed books yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($records as $r): ?>
                                    <tr>
                                        <td><?=htmlspecialchars($r['title'])?></td>
                                        <td><?=$r['request_date'] ?: ($r['borrow_date'] ?: '-')?></td>
                                        <td><?=$r['due_date'] ?: '-'?></td>
                                        <td><?=$r['copies_borrowed']?></td>
                                        <td><?=$r['status']?></td>
                                        <td>
                                            <?php if($r['status']=='borrowed'): ?>
                                                <a class='btn btn-primary' href='return.php?id=<?=$r['id']?>' onclick='return confirm("Return?")'>Return</a>
                                            <?php elseif($r['status']=='pending'): ?>
                                                <a class='btn btn-primary' href='cancel_request.php?id=<?=$r['id']?>' onclick='return confirm("Cancel this request?")' style='background-color: #dc3545; outline: none;'>Cancel</a>
                                            <?php elseif($r['status']=='rejected'): ?>
                                                -
                                            <?php elseif($r['status']=='overdue'): ?>
                                                -
                                            <?php else: echo '-'; endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="image">
                <div class="stats">
                    <div class="stat-card">
                        <h3><?php echo $mostBorrowed ? htmlspecialchars($mostBorrowed['title']) : 'None'; ?></h3>
                        <p>Most Borrowed Book</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $mostGenre ? htmlspecialchars($mostGenre['genre']) : 'None'; ?></h3>
                        <p>Favorite Genre</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="fines" class="zigzag-section" style="display: <?= empty($userFines) ? 'none' : 'block' ?>;">
            <div class="text">
                <h2>Fines History</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($userFines as $f): ?>
                                <tr>
                                    <td><?=htmlspecialchars($f['title'])?></td>
                                    <td>₱<?=number_format($f['amount'], 2)?></td>
                                    <td><?=$f['status']?></td>
                                    <td>
                                        <?php if($f['status'] == 'paid'): ?>
                                            Paid: <?=date('M d, Y', strtotime($f['paid_at']))?>
                                        <?php else: ?>
                                            Created: <?=date('M d, Y', strtotime($f['created_at']))?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($f['status'] == 'pending'): ?>
                                            <button onclick="payFine(<?=$f['id']?>)" class="btn btn-success btn-sm">Pay Fine</button>
                                        <?php else: ?>
                                            <span style="color: #28a745; font-weight: bold;">✓ Paid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="reservations" class="zigzag-section" style="display: <?= empty($reservations) ? 'none' : 'block' ?>;">
            <div class="text">
                <h2>My Reservations</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Reserved Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reservations as $r): ?>
                                <tr>
                                    <td><?=htmlspecialchars($r['title'])?></td>
                                    <td><?=$r['reserved_at']?></td>
                                    <td><button onclick="cancelReservation(<?=$r['id']?>)" class="btn btn-danger btn-sm">Cancel</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="available" class="zigzag-section">
            <div class="text">
                <h2>Available Books</h2>
                <div class="search-bar" style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
                    <input type="text" id="searchInput" placeholder="Search by title or author..." style="padding: 6px; border: 1px solid #ccc; border-radius: 5px; flex: 1; max-width: 300px;">
                    <select id="genreFilter" style="padding: 6px; border: 1px solid #ccc; border-radius: 5px; max-width: 200px;">
                        <option value="">All Genres</option>
                        <?php foreach($genres as $genre): ?>
                            <option value="<?=htmlspecialchars($genre)?>"><?=htmlspecialchars($genre)?></option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="toggleAdvancedSearch()" class="btn btn-secondary">Advanced Search</button>
                </div>
                <div id="advancedSearch" style="display: none; background: #f9f9f9; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select id="availabilityFilter">
                            <option value="">All Availability</option>
                            <option value="available">Available Now</option>
                            <option value="unavailable">Unavailable</option>
                        </select>
                        <button onclick="advancedSearch()" class="btn">Search</button>
                    </div>
                    <div style="margin-top: 10px;">
                        <strong>Recommendations:</strong>
                        <div id="recommendations" style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px;"></div>
                    </div>
                </div>
                <div class="table-container">
                    <table id="booksTable">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Genre</th>
                                <th>Publication Date</th>
                                <th>Available</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($books as $b): ?>
                                <?php
                                // Check if user has pending request or borrowed this book
                                $hasRequest = $pdo->prepare('SELECT id, status FROM borrow_history WHERE user_id=? AND book_id=? AND status IN (?,?)');
                                $hasRequest->execute([$uid, $b['id'], 'pending', 'borrowed']);
                                $existing = $hasRequest->fetch();
                                ?>
                                <tr>
                                    <td>
                                        <?=htmlspecialchars($b['title'])?>
                                        <?php
                                        require_once __DIR__ . '/../classes/Review.php';
                                        $review = new Review($pdo);
                                        $avgRating = $review->getAverageRating($b['id']);
                                        if ($avgRating['total_reviews'] > 0): ?>
                                            <br><small style="color: #666;">★<?=number_format($avgRating['avg_rating'], 1)?> (<?=$avgRating['total_reviews']?> reviews)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?=htmlspecialchars($b['author'])?></td>
                                    <td><?=htmlspecialchars($b['genre'])?></td>
                                    <td><?=$b['publication_date'] ? date('M d, Y', strtotime($b['publication_date'])) : '-'?></td>
                                    <td><?=$b['available_copies']?></td>
                                    <td>
                                        <?php if($existing): ?>
                                            <?php if($existing['status'] == 'borrowed' && $b['available_copies'] > 0): ?>
                                                <form method='post' action='borrow.php' style='display:inline;'>
                                                    <input type='hidden' name='book_id' value='<?=$b['id']?>'>
                                                    <input type='number' name='copies' value='1' min='1' max='<?=$b['available_copies']?>' style='width:70px;' required>
                                                    <button type='submit' class='btn btn-info'>Add Another</button>
                                                </form>
                                            <?php elseif($existing['status'] == 'borrowed'): ?>
                                                <span>Unavailable</span>
                                                <button onclick="reserveBook(<?=$b['id']?>)" class="btn btn-info btn-sm">Reserve</button>
                                            <?php else: ?>
                                                <span>Already requested</span>
                                            <?php endif; ?>
                                        <?php elseif($b['available_copies']>0 && !$blocked && $totalFines == 0): ?>
                                            <form method='post' action='borrow.php' style='display:inline;'>
                                                <input type='hidden' name='book_id' value='<?=$b['id']?>'>
                                                <input type='number' name='copies' value='1' min='1' max='<?=$b['available_copies']?>' style='width:70px;' required>
                                                <button type='submit' class='btn'>Borrow Request</button>
                                            </form>
                                        <?php elseif($blocked): ?>
                                            <span>Borrowing blocked</span>
                                        <?php elseif($totalFines > 0): ?>
                                            <span>Fines outstanding</span>
                                        <?php else: ?>
                                            <span>Unavailable</span>
                                            <button onclick="reserveBook(<?=$b['id']?>)" class="btn btn-info btn-sm">Reserve</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="noResults" style="display: none; text-align: center; padding: 20px; color: #666; font-style: italic;">No books found matching your search.</div>
                </div>
            </div>
            <div class="image">
                <div class="genre-stats">
                    <h3>Most genres borrowed</h3>
                    <?php foreach($borrowedGenres as $genre): ?>
                        <div class="stat-card">
                            <h3><?php echo htmlspecialchars($genre['genre']); ?></h3>
                            <p><?php echo $genre['count']; ?> borrowed</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById('searchInput');
        const genreFilter = document.getElementById('genreFilter');
        const table = document.getElementById('booksTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        // Add event listener to close advanced search when clicking outside
        document.addEventListener('click', function(event) {
            const advancedSearch = document.getElementById('advancedSearch');
            const toggleButton = document.querySelector('button[onclick="toggleAdvancedSearch()"]');
            if (!advancedSearch.contains(event.target) && event.target !== toggleButton && advancedSearch.style.display === 'block') {
                advancedSearch.style.display = 'none';
            }
        });

        // Book titles for recommendations
        const bookTitles = <?php echo json_encode($bookTitles); ?>;
        const recommendationsDiv = document.getElementById('recommendations');

        function updateRecommendations() {
            const searchTerm = searchInput.value.toLowerCase();
            recommendationsDiv.innerHTML = '';
            if (searchTerm.length > 0) {
                const matches = bookTitles.filter(title => title.toLowerCase().includes(searchTerm)).slice(0, 5);
                matches.forEach(title => {
                    const button = document.createElement('button');
                    button.textContent = title;
                    button.className = 'btn btn-small';
                    button.onclick = () => {
                        searchInput.value = title;
                        filterTable();
                    };
                    recommendationsDiv.appendChild(button);
                });
            }
        }

        searchInput.addEventListener('input', updateRecommendations);

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedGenre = genreFilter.value.toLowerCase();
            let visibleRows = 0;

            for (let i = 0; i < rows.length; i++) {
                const title = rows[i].getElementsByTagName('td')[0].textContent.toLowerCase();
                const author = rows[i].getElementsByTagName('td')[1].textContent.toLowerCase();
                const genre = rows[i].getElementsByTagName('td')[2] ? rows[i].getElementsByTagName('td')[2].textContent.toLowerCase() : '';

                const matchesSearch = title.includes(searchTerm) || author.includes(searchTerm);
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

        function toggleAdvancedSearch() {
            const adv = document.getElementById('advancedSearch');
            adv.style.display = adv.style.display === 'none' ? 'block' : 'none';
        }

        function advancedSearch() {
            const availability = document.getElementById('availabilityFilter').value;

            let visibleRows = 0;

            for (let i = 0; i < rows.length; i++) {
                const title = rows[i].getElementsByTagName('td')[0].textContent.toLowerCase();
                const author = rows[i].getElementsByTagName('td')[1].textContent.toLowerCase();
                const genre = rows[i].getElementsByTagName('td')[2] ? rows[i].getElementsByTagName('td')[2].textContent.toLowerCase() : '';
                const available = parseInt(rows[i].getElementsByTagName('td')[4].textContent);

                const searchTerm = searchInput.value.toLowerCase();
                const selectedGenre = genreFilter.value.toLowerCase();

                const matchesSearch = title.includes(searchTerm) || author.includes(searchTerm);
                const matchesGenre = selectedGenre === '' || genre === selectedGenre;
                const matchesAvailability = availability === '' ||
                    (availability === 'available' && available > 0) ||
                    (availability === 'unavailable' && available === 0);

                if (matchesSearch && matchesGenre && matchesAvailability) {
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

        function reserveBook(bookId) {
            if (confirm('Reserve this book? You will be notified when it becomes available.')) {
                fetch('reserve.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'book_id=' + bookId
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.msg);
                    if (data.ok) location.reload();
                });
            }
        }

        function cancelReservation(reservationId) {
            if (confirm('Cancel this reservation?')) {
                fetch('cancel_reservation.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'reservation_id=' + reservationId
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.msg);
                    if (data.ok) location.reload();
                });
            }
        }

        function payFine(fineId) {
            console.log('Paying fine:', fineId);
            // Get fine amount from the table row
            const row = document.querySelector(`button[onclick="payFine(${fineId})"]`).closest('tr');
            const amountCell = row.querySelector('td:nth-child(2)');
            const amount = amountCell.textContent.trim();
            console.log('Fine amount:', amount);

            // Show confirmation modal
            showFineModal(fineId, amount, 'fine_id');
        }



        function showFineModal(id, amount, type) {
            console.log('Showing fine modal for id:', id, 'amount:', amount, 'type:', type);
            // Create modal HTML
            const modal = document.createElement('div');
            modal.id = 'fineModal';
            modal.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.5); z-index: 1000; display: flex;
                align-items: center; justify-content: center;
            `;

            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

            modal.innerHTML = `
                <div style="background: white; padding: 20px; border-radius: 8px; width: 400px; max-width: 90%;">
                    <h3 style="margin-top: 0; color: #800000;">Confirm Fine Payment</h3>
                    <p>Are you sure you want to pay this fine?</p>
                    <p style="font-size: 18px; font-weight: bold; color: #dc3545;">Amount: ${amount}</p>
                    <p>Payment Date: ${dateStr}</p>
                    <p>Status will be: Paid</p>
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button onclick="closeFineModal()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                        <button onclick="confirmPayFine(${id}, '${type}')" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Pay Fine</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            console.log('Modal appended to body');
        }

        function closeFineModal() {
            const modal = document.getElementById('fineModal');
            if (modal) {
                modal.remove();
            }
        }

        function confirmPayFine(id, type) {
            console.log('Confirming payment for id:', id, 'type:', type);
            const body = type + '=' + id;
            console.log('Request body:', body);

            fetch('pay_fine.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                closeFineModal();
                if (data.ok) {
                    alert(data.msg || 'Fine paid successfully!');
                    updateTableAfterPayment(id, type);
                } else {
                    alert(data.msg || 'Failed to pay fine');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                closeFineModal();
                alert('Error processing payment');
            });
        }

        function updateTableAfterPayment(id, type) {
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

            if (type === 'fine_id') {
                // Update Outstanding Fines table
                const button = document.querySelector(`button[onclick="payFine(${id})"]`);
                if (button) {
                    const row = button.closest('tr');
                    const statusCell = row.querySelector('td:nth-child(3)');
                    const actionCell = row.querySelector('td:nth-child(4)');

                    statusCell.textContent = 'paid';
                    actionCell.textContent = `Paid on ${dateStr}`;

                    // Disable the button to prevent double payment
                    button.disabled = true;
                    button.textContent = 'Paid';
                    button.style.backgroundColor = '#28a745';

                    // Check if all fines are now paid and update borrowing status
                    checkAndUpdateBorrowingStatus();
                }
            }
        }

        function checkAndUpdateBorrowingStatus() {
            // Check if there are any remaining pending fines
            const pendingButtons = document.querySelectorAll('button[onclick^="payFine("]:not([disabled])');
            if (pendingButtons.length === 0) {
                // All fines are paid - update the warning message and enable borrowing
                const warningDiv = document.querySelector('div[style*="color: orange"]');
                if (warningDiv) {
                    warningDiv.style.display = 'none';
                }

                // Re-enable borrow buttons for available books
                const borrowButtons = document.querySelectorAll('button[type="submit"]');
                borrowButtons.forEach(button => {
                    const form = button.closest('form');
                    if (form && form.action.includes('borrow.php')) {
                        const unavailableSpan = form.parentElement.querySelector('span');
                        if (unavailableSpan && unavailableSpan.textContent === 'Fines outstanding') {
                            unavailableSpan.style.display = 'none';
                            form.style.display = 'inline';
                        }
                    }
                });
            }
        }

        function updateBothTablesAfterPayment() {
            // Reload the page to refresh all data including fines totals and admin stats
            location.reload();
        }

        function markAsRead(notificationId) {
            console.log('Marking notification as read:', notificationId);
            fetch('mark_read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'notification_id=' + notificationId
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.ok) {
                    const notificationDiv = document.getElementById('notification-' + notificationId);
                    if (notificationDiv) {
                        notificationDiv.style.display = 'none';
                        console.log('Notification hidden');
                    } else {
                        console.log('Notification div not found');
                    }

                    // Update notification count if needed
                    const notificationSection = document.querySelector('h3');
                    if (notificationSection) {
                        console.log('Notification section text:', notificationSection.textContent);
                        const countMatch = notificationSection.textContent.match(/(\d+)/);
                        if (countMatch) {
                            const currentCount = parseInt(countMatch[1]);
                            console.log('Current count:', currentCount);
                            if (currentCount > 1) {
                                notificationSection.textContent = notificationSection.textContent.replace(currentCount, currentCount - 1);
                                console.log('Updated count to:', currentCount - 1);
                            } else {
                                // Hide the entire notification section
                                const notificationContainer = notificationSection.closest('div');
                                if (notificationContainer) {
                                    notificationContainer.style.display = 'none';
                                    console.log('Notification section hidden');
                                }
                            }
                        } else {
                            console.log('No count match found');
                        }
                    } else {
                        console.log('Notification section h3 not found');
                    }
                } else {
                    alert('Failed to mark as read: ' + data.msg);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while marking the notification as read.');
            });
        }
    </script>

    <footer>
        © 2025 WMSU Library — All Rights Reserved
    </footer>
</body>
</html>
