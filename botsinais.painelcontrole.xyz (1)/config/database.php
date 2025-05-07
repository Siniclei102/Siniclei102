

<?php
// Informações de conexão com o banco de dados
$db_host = 'localhost';
$db_name = 'sql_botsinais_pa'; // Use o nome correto do banco de dados
$db_user = 'sql_botsinais_pa';             // Seu usuário MySQL
$db_pass = 'c84ac8f71dba5';                    // Sua senha MySQL

// Conexão com o banco de dados
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verificar se houve erro na conexão
if ($conn->connect_error) {
    // Registrar erro em log
    $log_dir = __DIR__ . '/../logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/db_error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] Falha na conexão com o banco de dados: " . $conn->connect_error . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    // Em ambiente de produção, não exibir detalhes do erro
    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
        die("Erro na conexão com o banco de dados: " . $conn->connect_error);
    } else {
        die("Erro na conexão com o banco de dados. Por favor, tente novamente mais tarde.");
    }
}

// Definir conjunto de caracteres para UTF-8
$conn->set_charset("utf8mb4");

/**
 * Função para executar uma query SQL com segurança
 * 
 * @param string $sql Query SQL com placeholders
 * @param array $params Parâmetros para substituir os placeholders
 * @param string $types Tipos dos parâmetros (i = inteiro, s = string, d = double, b = blob)
 * @return mysqli_result|bool Resultado da query
 */
function executeQuery($sql, $params = [], $types = "") {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        if (empty($types)) {
            // Tentar determinar os tipos automaticamente
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}