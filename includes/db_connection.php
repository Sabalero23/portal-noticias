<?php
// Prevenir acceso directo
if (!defined('BASE_PATH')) {
    exit('No se permite el acceso directo al script');
}

// Incluir configuración si no está incluida
if (!defined('DB_HOST')) {
    require_once 'config.php';
}

class Database {
    private $conn;
    private static $instance;
    
    // Constructor privado (patrón Singleton)
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    // Obtener instancia de conexión (Singleton)
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Obtener conexión
    public function getConnection() {
        return $this->conn;
    }
    
    // Ejecutar consulta
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            die("Error en la consulta: " . $e->getMessage());
        }
    }
    
    // Obtener un solo registro
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    // Obtener todos los registros
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Obtener el ID del último registro insertado
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
}

// Clase para transacciones
class Transaction {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function begin() {
        $this->db->beginTransaction();
    }
    
    public function commit() {
        $this->db->commit();
    }
    
    public function rollback() {
        $this->db->rollBack();
    }
}
?>