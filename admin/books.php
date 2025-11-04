<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../classes/Book.php';

if (!is_admin()) { header('Location: ../index.php'); exit; }
$bookObj = new Book($pdo);
// actions
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    if ($_POST['action']==='add') {
        $success = $bookObj->add(['title'=>$_POST['title'],'author'=>$_POST['author'],'genre'=>$_POST['genre'],'publication_date'=>$_POST['year'],'copies'=>$_POST['copies']]);
        if (!$success) {
            $error = 'Publication date cannot be in the future.';
        } else {
            header('Location: books.php'); exit;
        }
    } elseif ($_POST['action']==='update') {
        $success = $bookObj->update($_POST['id'], ['title'=>$_POST['title'],'author'=>$_POST['author'],'genre'=>$_POST['genre'],'publication_date'=>$_POST['year'],'copies'=>$_POST['copies']]);
        if (!$success) {
            $error = 'Publication date cannot be in the future.';
        } else {
            header('Location: books.php'); exit;
        }
    }
}
if (isset($_GET['delete'])) { $bookObj->delete(intval($_GET['delete'])); header('Location: books.php'); exit; }
if (isset($_GET['get'])) {
    $id = intval($_GET['get']);
    $book = $bookObj->get($id);
    header('Content-Type: application/json');
    echo json_encode($book);
    exit;
}
$books = $bookObj->all();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books</title>
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
        <section id="add-book" class="dashboard-section">
            <h2>Add New Book</h2>
            <?php if (isset($error)): ?>
                <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method='post' style='display: flex; gap: 10px; flex-wrap: wrap; align-items: center;'>
                <input type='hidden' name='action' value='add'>
                <input name='title' placeholder='Title' required style='padding: 8px; border: 1px solid #ccc; border-radius: 5px; flex: 1; min-width: 150px;'>
                <input name='author' placeholder='Author' style='padding: 8px; border: 1px solid #ccc; border-radius: 5px; flex: 1; min-width: 120px;'>
                <input name='genre' placeholder='Genre' style='padding: 8px; border: 1px solid #ccc; border-radius: 5px; flex: 1; min-width: 100px;'>
                <input name='year' placeholder='Publication Date' type='date' required style='padding: 8px; border: 1px solid #ccc; border-radius: 5px; flex: 1; min-width: 120px;'>
                <input name='copies' value='1' type='number' min='1' style='padding: 8px; border: 1px solid #ccc; border-radius: 5px; width: 80px;'>
                <button type='submit' class='btn'>Add Book</button>
            </form>
        </section>

        <section id="books-list" class="dashboard-section">
            <h2>Books List</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Genre</th>
                            <th>Publication Date</th>
                            <th>Total</th>
                            <th>Available</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($books as $b): ?>
                            <tr>
                                <td><?=htmlspecialchars($b['title'])?></td>
                                <td><?=htmlspecialchars($b['author'])?></td>
                                <td><?=htmlspecialchars($b['genre'])?></td>
                                <td><?=$b['publication_date'] ? date('M d, Y', strtotime($b['publication_date'])) : '-'?></td>
                                <td><?=$b['total_copies']?></td>
                                <td><?=$b['available_copies']?></td>
                                <td>
                                    <button class='btn edit-btn' data-id='<?=$b['id']?>' style='background-color: #28a745; color: white;'>Edit</button>
                                    <button class='btn delete-btn' data-id='<?=$b['id']?>' data-title='<?=htmlspecialchars($b['title'])?>' style='background-color: #dc3545; color: white;'>Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="edit-book" class="dashboard-section">
            <h2>Edit Book</h2>
            <form method='post' style='display: flex; gap: 10px; flex-wrap: wrap; align-items: center;'>
                <input type='hidden' name='action' value='update'>
                <input type='hidden' name='id' id='edit-id'>
                <input name='title' id='edit-title' required style='padding: 8px; border: 1px solid #ccc; border-radius: 5px; flex: 1; min-width: 150px;'>
                <input name='author' id='edit-author' style='padding: 8px; border: 1px solid #ccc; border-radius: 5px; flex: 1; min-width: 120px;'>
                <input name='genre' id='edit-genre' style='padding: 8px; border: 1px solid #ccc; border-radius: 5px; flex: 1; min-width: 100px;'>
                <input name='year' id='edit-year' type='date' required style='padding: 8px; border: 1px solid #ccc; border-radius: 5px; flex: 1; min-width: 120px;'>
                <input name='copies' id='edit-copies' type='number' min='1' style='padding: 8px; border: 1px solid #ccc; border-radius: 5px; width: 80px;'>
                <button type='submit' class='btn'>Save Changes</button>
            </form>
        </section>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3>Confirm Deletion</h3>
                <p>Are you sure you want to delete the book "<span id="bookTitle"></span>"? This action cannot be undone.</p>
                <div class="modal-buttons">
                    <button id="cancelDelete" class="btn">Cancel</button>
                    <button id="confirmDelete" class="btn" style="background-color: #dc3545; color: white;">Delete</button>
                </div>
            </div>
        </div>
        </div>
    </main>

    <script>
        const editSection = document.getElementById('edit-book');
        const editBtns = document.querySelectorAll('.edit-btn');
        const editId = document.getElementById('edit-id');
        const editTitle = document.getElementById('edit-title');
        const editAuthor = document.getElementById('edit-author');
        const editGenre = document.getElementById('edit-genre');
        const editYear = document.getElementById('edit-year');
        const editCopies = document.getElementById('edit-copies');

        editBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                if (editSection.classList.contains('show') && editId.value == id) {
                    // If already open for this book, close it
                    editSection.classList.remove('show');
                } else {
                    // Fetch book data via AJAX or use data attributes
                    fetch('books.php?get=' + id)
                        .then(response => response.json())
                        .then(data => {
                            editId.value = data.id;
                            editTitle.value = data.title;
                            editAuthor.value = data.author;
                            editGenre.value = data.genre;
                            editYear.value = data.publication_date;
                            editCopies.value = data.total_copies;
                            editSection.classList.add('show');
                        });
                }
            });
        });

        document.addEventListener('click', function(e) {
            if (!editSection.contains(e.target) && !e.target.classList.contains('edit-btn')) {
                editSection.classList.remove('show');
            }
        });

        // Modal functionality
        const modal = document.getElementById('deleteModal');
        const closeBtn = document.getElementsByClassName('close')[0];
        const cancelBtn = document.getElementById('cancelDelete');
        const confirmBtn = document.getElementById('confirmDelete');
        const bookTitleSpan = document.getElementById('bookTitle');
        let bookIdToDelete = null;

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                bookIdToDelete = this.getAttribute('data-id');
                const bookTitle = this.getAttribute('data-title');
                bookTitleSpan.textContent = bookTitle;
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
            if (bookIdToDelete) {
                window.location.href = 'books.php?delete=' + bookIdToDelete;
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
