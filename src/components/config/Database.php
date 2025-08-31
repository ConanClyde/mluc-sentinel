<?php
class Database {
    private $host = "localhost";   // DB server
    private $db_name = "mluc-sentinel"; 
    private $username = "root";    // change if you have a custom user
    private $password = "";        // change if password is set
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            echo "Database connection failed: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
