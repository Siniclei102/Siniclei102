<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// INCLUIR O ARQUIVO DE CONFIGURAÇÃO EXISTENTE
// Ajustar o caminho conforme necessário
if (file_exists('..app/config/config.php')) {
    require_once '..app/config/config.php';

} else {

}

try {
    // Usar as variáveis de configuração que já existem no seu sistema
    // Adaptar os nomes das variáveis conforme seu arquivo de config
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro de conexão com o banco de dados',
        'debug' => $e->getMessage()
    ]);
    exit;
}

// Ler dados do POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Usuário e senha são obrigatórios']);
    exit;
}

$inputUsername = trim($input['username']);
$inputPassword = trim($input['password']);

try {
    // Buscar usuário no banco de dados
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$inputUsername]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Usuário não encontrado'
        ]);
        exit;
    }
    
    // Verificar senha
    $passwordMatches = false;
    
    if (password_verify($inputPassword, $user['password'])) {
        $passwordMatches = true;
    } elseif ($inputPassword === $user['password']) {
        $passwordMatches = true;
    }
    
    if (!$passwordMatches) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Senha incorreta'
        ]);
        exit;
    }
    
    // Autenticação bem-sucedida
    $token = bin2hex(random_bytes(32));
    
    $response = [
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => intval($user['id']),
            'username' => $user['username'],
            'name' => $user['name'] ?? $user['username'],
            'email' => $user['email'] ?? '',
            'account_type' => $user['account_type'] ?? 'user'
        ],
        'server_time' => date('Y-m-d H:i:s'),
        'message' => 'Autenticação realizada com sucesso'
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor'
    ]);
}
?>