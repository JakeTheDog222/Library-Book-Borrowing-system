<?php
// Run once to create sample admin and student accounts and a few books.
require 'config.php';

// Check DB empty
$c = $pdo->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
if ($c > 0) {
    // Delete existing data to reseed
    $pdo->exec("DELETE FROM audit_logs");
    $pdo->exec("DELETE FROM reservations");
    $pdo->exec("DELETE FROM book_reviews");
    $pdo->exec("DELETE FROM fines");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM borrow_history");
    $pdo->exec("DELETE FROM books");
    $pdo->exec("DELETE FROM users");
    // Reset auto increment
    $pdo->exec("ALTER TABLE users AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE books AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE borrow_history AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE fines AUTO_INCREMENT = 1");
}

$admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
$stud_pass = password_hash('student12345', PASSWORD_DEFAULT);
$stud2_pass = password_hash('student22222', PASSWORD_DEFAULT);

$pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'admin')")
    ->execute(['Admin@wmsu.admin.com', $admin_pass, 'Administrator']);
$pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'student')")
    ->execute(['Student@wmsu.com', $stud_pass, 'Student']);
$pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'student')")
    ->execute(['Student1@wmsu.com', $stud2_pass, 'Student1']);

$books = [
    ['Clean Code','Robert C. Martin','Programming','2008-08-01',2],
    ['PHP & MySQL Web Development','Luke Welling','Programming','2016-05-15',1],
    ['To Kill a Mockingbird','Harper Lee','Fiction','1960-07-11',3],
    ['Dune','Frank Herbert','Science Fiction','1965-08-01',2],
    ['The Great Gatsby','F. Scott Fitzgerald','Classic','1925-04-10',4],
    ['Sherlock Holmes: A Study in Scarlet','Arthur Conan Doyle','Mystery','1887-11-01',2],
    ['Pride and Prejudice','Jane Austen','Romance','1813-01-28',3],
    ['Sapiens: A Brief History of Humankind','Yuval Noah Harari','History','2011-09-04',1],
    ['The Autobiography of Malcolm X','Malcolm X','Biography','1965-10-29',2],
    ['The Hobbit','J.R.R. Tolkien','Fantasy','1937-09-21',3],
];
$stmt = $pdo->prepare("INSERT INTO books (title, author, genre, publication_date, total_copies, available_copies)
VALUES (?, ?, ?, ?, ?, ?)");
foreach ($books as $book) {
    $stmt->execute([$book[0], $book[1], $book[2], $book[3], $book[4], $book[4]]);
}

// Add overdue borrows for Student1@wmsu.com using the first three books
$student2_id = $pdo->query("SELECT id FROM users WHERE username = 'Student1@wmsu.com'")->fetch()['id'];

// Overdue book 1: Clean Code (ID 1)
$book_id = 1; // Clean Code
$pdo->prepare("INSERT INTO borrow_history (user_id, book_id, status, borrow_date, due_date) VALUES (?, ?, 'overdue', DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY))")
    ->execute([$student2_id, $book_id]);
$borrow_id1 = $pdo->lastInsertId();
// Add a fine for the overdue book
$pdo->prepare("INSERT INTO fines (user_id, borrow_id, amount, status) VALUES (?, ?, 5.00, 'pending')")
    ->execute([$student2_id, $borrow_id1]);

// Overdue book 2: To Kill a Mockingbird (ID 3)
$book_id = 3; // To Kill a Mockingbird
$pdo->prepare("INSERT INTO borrow_history (user_id, book_id, status, borrow_date, due_date) VALUES (?, ?, 'overdue', DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY))")
    ->execute([$student2_id, $book_id]);
$borrow_id2 = $pdo->lastInsertId();
// Add a fine for the overdue book
$pdo->prepare("INSERT INTO fines (user_id, borrow_id, amount, status) VALUES (?, ?, 5.00, 'pending')")
    ->execute([$student2_id, $borrow_id2]);

// Overdue book 3: Dune (ID 4)
$book_id = 4; // Dune
$pdo->prepare("INSERT INTO borrow_history (user_id, book_id, status, borrow_date, due_date) VALUES (?, ?, 'overdue', DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY))")
    ->execute([$student2_id, $book_id]);
$borrow_id3 = $pdo->lastInsertId();
// Add a fine for the overdue book
$pdo->prepare("INSERT INTO fines (user_id, borrow_id, amount, status) VALUES (?, ?, 5.00, 'pending')")
    ->execute([$student2_id, $borrow_id3]);




echo "Seed complete. Admin: Admin@wmsu.admin.com/admin123  Student: Student@wmsu.com/student12345  Student1: Student1@wmsu.com/student22222\n";
?>
