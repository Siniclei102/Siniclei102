<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuração do banco de dados
$host = 'localhost';
$dbname = 'postador_facebook';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Obter token do header Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Token de autorização necessário']);
    exit;
}

$token = substr($authHeader, 7);

try {
    // Verificar token no banco de dados
    $stmt = $pdo->prepare("
        SELECT es.*, u.username, u.name, u.email, u.account_type, u.account_status 
        FROM extension_sessions es 
        JOIN users u ON es.user_id = u.id 
        WHERE es.token = ? AND es.expires_at > NOW() AND u.account_status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        http_response_code(401);
        echo json_encode([
            'valid' => false,
            'error' => 'Token inválido ou expirado'
        ]);
        exit;
    }
    
    // Atualizar última atividade
    $stmt = $pdo->prepare("UPDATE extension_sessions SET last_activity = NOW() WHERE token = ?");
    $stmt->execute([$token]);
    
    echo json_encode([
        'valid' => true,
        'user' => [
            'id' => intval($session['user_id']),
            'username' => $session['username'],
            'name' => $session['name'],
            'email' => $session['email'],
            'account_type' => $session['account_type']
        ],
        'session' => [
            'created_at' => $session['created_at'],
            'expires_at' => $session['expires_at'],
            'last_activity' => $session['last_activity']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error validating token: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'error' => 'Erro interno do servidor'
    ]);
}
?>