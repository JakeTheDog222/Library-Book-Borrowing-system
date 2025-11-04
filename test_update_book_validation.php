<?php
// Test script to verify book update validation
require_once 'config.php';
require_once 'classes/Book.php';

$bookObj = new Book($pdo);

// First, add a test book with a past date
$bookObj->add([
    'title' => 'Test Update Book',
    'author' => 'Test Author',
    'genre' => 'Test Genre',
    'publication_date' => '2020-01-01',
    'copies' => 1
]);

// Get the ID of the newly added book
$books = $bookObj->all();
$testBook = array_filter($books, function($book) {
    return $book['title'] === 'Test Update Book';
});
$testBook = reset($testBook);
$bookId = $testBook['id'];

echo "Testing update validation for book ID: $bookId\n\n";

// Test 1: Update book with future date (should fail)
echo "Test 1: Updating book with future date (2025-01-01)\n";
$futureDate = date('Y-m-d', strtotime('+1 year'));
$result1 = $bookObj->update($bookId, [
    'title' => 'Test Update Book',
    'author' => 'Test Author',
    'genre' => 'Test Genre',
    'publication_date' => $futureDate,
    'copies' => 1
]);
echo "Result: " . ($result1 ? "SUCCESS (unexpected)" : "FAILED (expected)") . "\n\n";

// Test 2: Update book with past date (should succeed)
echo "Test 2: Updating book with past date (2019-01-01)\n";
$pastDate = '2019-01-01';
$result2 = $bookObj->update($bookId, [
    'title' => 'Test Update Book',
    'author' => 'Test Author',
    'genre' => 'Test Genre',
    'publication_date' => $pastDate,
    'copies' => 1
]);
echo "Result: " . ($result2 ? "SUCCESS (expected)" : "FAILED (unexpected)") . "\n\n";

// Test 3: Update book with current date (should succeed)
echo "Test 3: Updating book with current date (" . date('Y-m-d') . ")\n";
$currentDate = date('Y-m-d');
$result3 = $bookObj->update($bookId, [
    'title' => 'Test Update Book',
    'author' => 'Test Author',
    'genre' => 'Test Genre',
    'publication_date' => $currentDate,
    'copies' => 1
]);
echo "Result: " . ($result3 ? "SUCCESS (expected)" : "FAILED (unexpected)") . "\n\n";

// Test 4: Verify the final publication date in DB
echo "Test 4: Verifying final publication date in database\n";
$updatedBook = $bookObj->get($bookId);
$finalDate = $updatedBook['publication_date'];
echo "Final publication date: $finalDate\n";
echo "Expected: $currentDate\n";
echo "Match: " . ($finalDate === $currentDate ? "YES (expected)" : "NO (unexpected)") . "\n\n";

echo "All update tests completed.\n";
?>
