<?php
require_once 'config.php';
require_once 'classes/Book.php';

$bookObj = new Book($pdo);

// Test 1: Get all books before deletion
echo "Books before deletion:\n";
$books = $bookObj->all();
foreach ($books as $book) {
    echo "- {$book['title']} (ID: {$book['id']})\n";
}

// Test 2: Delete a book (soft delete)
if (count($books) > 0) {
    $bookToDelete = $books[0];
    echo "\nDeleting book: {$bookToDelete['title']} (ID: {$bookToDelete['id']})\n";
    $bookObj->delete($bookToDelete['id']);
    echo "Soft delete executed.\n";
}

// Test 3: Get all books after deletion (should not include deleted)
echo "\nBooks after deletion:\n";
$booksAfter = $bookObj->all();
foreach ($booksAfter as $book) {
    echo "- {$book['title']} (ID: {$book['id']})\n";
}

// Test 4: Check if deleted book is still in DB but marked deleted
echo "\nChecking raw DB for deleted book:\n";
$stmt = $pdo->prepare('SELECT * FROM books WHERE id = ?');
$stmt->execute([$bookToDelete['id']]);
$rawBook = $stmt->fetch();
if ($rawBook) {
    echo "Book still in DB: {$rawBook['title']} (deleted: {$rawBook['deleted']})\n";
} else {
    echo "Book not found in DB.\n";
}

// Test 5: Try to get deleted book via get() method (should return null)
echo "\nTrying to get deleted book via get() method:\n";
$retrieved = $bookObj->get($bookToDelete['id']);
if ($retrieved) {
    echo "Retrieved: {$retrieved['title']}\n";
} else {
    echo "Book not retrieved (as expected).\n";
}

echo "\nTest completed.\n";
?>
