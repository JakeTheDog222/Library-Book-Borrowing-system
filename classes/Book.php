<?php
require_once __DIR__ . '/Database.php';
class Book extends Database {
    public function __construct($pdo=null){ parent::__construct($pdo); }
    public function all() {
        $stmt = $this->pdo->query('SELECT * FROM books WHERE deleted = 0 ORDER BY title');
        return $stmt->fetchAll();
    }
    public function get($id) {
        $stmt = $this->pdo->prepare('SELECT * FROM books WHERE id=? AND deleted = 0');
        $stmt->execute([$id]); return $stmt->fetch();
    }
    private function validatePublicationDate($date) {
        $currentDate = date('Y-m-d');
        return $date <= $currentDate;
    }

    public function add($data) {
        if (!$this->validatePublicationDate($data['publication_date'])) {
            return false; // Invalid date
        }
        $stmt = $this->pdo->prepare('INSERT INTO books (title,author,genre,publication_date,total_copies,available_copies) VALUES (?,?,?,?,?,?)');
        return $stmt->execute([$data['title'],$data['author'],$data['genre'],$data['publication_date'],intval($data['copies']),intval($data['copies'])]);
    }
    public function update($id,$data) {
        if (!$this->validatePublicationDate($data['publication_date'])) {
            return false; // Invalid date
        }
        // adjust available when total changes
        $book = $this->get($id);
        $delta = intval($data['copies']) - intval($book['total_copies']);
        $avail = max(0, intval($book['available_copies']) + $delta);
        $stmt = $this->pdo->prepare('UPDATE books SET title=?,author=?,genre=?,publication_date=?,total_copies=?,available_copies=? WHERE id=?');
        return $stmt->execute([$data['title'],$data['author'],$data['genre'],$data['publication_date'],intval($data['copies']),$avail,$id]);
    }
    public function delete($id) {
        $stmt = $this->pdo->prepare('UPDATE books SET deleted = 1 WHERE id=?'); return $stmt->execute([$id]);
    }
}
?>