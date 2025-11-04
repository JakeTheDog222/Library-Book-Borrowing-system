<?php
// Test script to verify book addition validation
require_once 'config.php';
require_once 'classes/Book.php';

$bookObj = new Book($pdo);

// Test 1: Add book with future date (should fail)
echo "Test 1: Adding book with future date (2025-01-01)\n";
$futureDate = date('Y-m-d', strtotime('+1 year'));
$result1 = $bookObj->add([
    'title' => 'Future Book',
    'author' => 'Future Author',
    'genre' => 'Sci-Fi',
    'publication_date' => $futureDate,
    'copies' => 1
]);
echo "Result: " . ($result1 ? "SUCCESS (unexpected)" : "FAILED (expected)") . "\n\n";

// Test 2: Add book with past date (should succeed)
echo "Test 2: Adding book with past date (2020-01-01)\n";
$pastDate = '2020-01-01';
$result2 = $bookObj->add([
    'title' => 'Past Book',
    'author' => 'Past Author',
    'genre' => 'History',
    'publication_date' => $pastDate,
    'copies' => 1
]);
echo "Result: " . ($result2 ? "SUCCESS (expected)" : "FAILED (unexpected)") . "\n\n";

// Test 3: Add book with current date (should succeed)
echo "Test 3: Adding book with current date (" . date('Y-m-d') . ")\n";
$currentDate = date('Y-m-d');
$result3 = $bookObj->add([
    'title' => 'Current Book',
    'author' => 'Current Author',
    'genre' => 'Contemporary',
    'publication_date' => $currentDate,
    'copies' => 1
]);
echo "Result: " . ($result3 ? "SUCCESS (expected)" : "FAILED (unexpected)") . "\n\n";

// Test 4: Check that books were added correctly (only past and current should be in DB)
echo "Test 4: Verifying books in database\n";
$books = $bookObj->all();
$expectedTitles = ['Past Book', 'Current Book'];
$actualTitles = array_map(function($book) { return $book['title']; }, $books);
$futureBookExists = in_array('Future Book', $actualTitles);
$pastBookExists = in_array('Past Book', $actualTitles);
$currentBookExists = in_array('Current Book', $actualTitles);

echo "Future book in DB: " . ($futureBookExists ? "YES (unexpected)" : "NO (expected)") . "\n";
echo "Past book in DB: " . ($pastBookExists ? "YES (expected)" : "NO (unexpected)") . "\n";
echo "Current book in DB: " . ($currentBookExists ? "YES (expected)" : "NO (unexpected)") . "\n\n";

echo "All tests completed.\n";
?>
