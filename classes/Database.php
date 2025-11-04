<?php
class Database {
    private $host = '127.0.0.1';
    private $db = 'library_system';
    private $user = 'root';
    private $pass = '';
    protected $pdo;

    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
            return;
        }
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->db};charset=utf8mb4", $this->user, $this->pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Exception $e) {
            die('DB Error: ' . $e->getMessage());
        }
    }
}
?>