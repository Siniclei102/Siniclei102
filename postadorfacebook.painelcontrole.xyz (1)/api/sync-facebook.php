

<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// DEBUG: Ver o que está chegando
$rawData = file_get_contents('php://input');
file_put_contents('/tmp/sync_debug.txt', date('Y-m-d H:i:s') . " - " . $rawData . PHP_EOL, FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$data = json_decode($rawData, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Dados não recebidos']);
    exit;
}

// Aqui você precisa SALVAR os grupos no banco
$user = $data['user'] ?? null;
$groups = $data['groups'] ?? [];

// IMPORTANTE: Adicione aqui o código para salvar no banco!
// Exemplo:
foreach ($groups as $group) {
    // INSERT INTO grupos (user_id, group_id, group_name, synced_at) VALUES (...)
}

echo json_encode(['success' => true]);
?>
