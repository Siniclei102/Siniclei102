<?php
// Configurações de conexão com o banco de dados
define('DB_HOST', 'localhost'); // Host do banco de dados
define('DB_USER', 'sql_postadorface');      // Usuário do banco de dados
define('DB_PASS', '2577fadde1ae4');          // Senha do banco de dados
define('DB_NAME', 'sql_postadorface'); // Nome do banco de dados

// Classe de conexão com o banco
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $this->conn->set_charset("utf8mb4");
            
            if ($this->conn->connect_error) {
                throw new Exception("Falha na conexão: " . $this->conn->connect_error);
            }
        } catch (Exception $e) {
            die("Erro na conexão com o banco de dados: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}
?>