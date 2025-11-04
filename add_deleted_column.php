<?php
require_once 'config.php';

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM books LIKE 'deleted'");
    $exists = $stmt->fetch();

    if (!$exists) {
        $pdo->exec('ALTER TABLE books ADD COLUMN deleted BOOLEAN DEFAULT FALSE');
        echo "Column 'deleted' added successfully to books table.\n";
    } else {
        echo "Column 'deleted' already exists in books table.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
