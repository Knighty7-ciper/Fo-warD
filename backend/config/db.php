<?php

class Database {
    private static $instance = null;
    private $conn;

    private $supabase_url = 'https://dcqcwzxioeqvreqlsakl.supabase.co';
    private $supabase_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImRjcWN3enhpb2VxdnJlcWxzYWtsIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTk3MjEyMTYsImV4cCI6MjA3NTI5NzIxNn0.IfBKdZMYHA7wBebBKqy8k3Cz3jnp0HK_dxo6Ub7ILkk';

    private $host = 'db.dcqcwzxioeqvreqlsakl.supabase.co';
    private $port = '5432';
    private $dbname = 'postgres';
    private $username = 'postgres';
    private $password = '';

    private function __construct() {
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            throw new Exception("Query execution failed");
        }
    }

    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->conn->lastInsertId();
    }

    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollback() {
        return $this->conn->rollBack();
    }

    public function getSupabaseUrl() {
        return $this->supabase_url;
    }

    public function getSupabaseKey() {
        return $this->supabase_key;
    }

    public function setUserContext($user_id) {
        if (!empty($user_id)) {
            $sql = "SET app.user_id = :user_id";
            $this->query($sql, [':user_id' => $user_id]);
        }
    }
}

?>
