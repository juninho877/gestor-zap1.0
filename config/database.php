<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'streami1_gestorapisafe';
    private $username = 'streami1_gestorapisafe';
    private $password = '5WF)g247(lSvi}-M';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Log de depuração para conexão
            error_log("Database connection successful");
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            echo "Connection error: " . $exception->getMessage();
            die();
        }
        return $this->conn;
    }
}
?>