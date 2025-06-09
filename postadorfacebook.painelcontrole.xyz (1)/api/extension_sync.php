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

// Verificar token de autorização
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Token de autorização necessário']);
    exit;
}

$token = substr($authHeader, 7);

// Em produção, validar o token no banco de dados
// Por enquanto, aceitar qualquer token não vazio
if (empty($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

// Ler dados do POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['groups']) || !is_array($input['groups'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Lista de grupos é obrigatória']);
    exit;
}

$groups = $input['groups'];
$userId = $input['user_id'] ?? 1;

// Simular salvamento dos grupos
$savedGroups = 0;

foreach ($groups as $group) {
    if (isset($group['facebook_id']) && isset($group['name'])) {
        // Em produção, salvar no banco de dados
        // INSERT INTO groups (user_id, facebook_id, name, url, members, status, created_at)
        // VALUES (?, ?, ?, ?, ?, ?, NOW())
        
        $savedGroups++;
    }
}

// Log da sincronização
error_log("Groups sync for user $userId: $savedGroups groups saved");

echo json_encode([
    'success' => true,
    'message' => "$savedGroups grupos sincronizados com sucesso",
    'groups_saved' => $savedGroups,
    'sync_time' => date('Y-m-d H:i:s')
]);
?>