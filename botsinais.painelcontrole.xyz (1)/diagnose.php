<?php
// Configurar relatório de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definir cabeçalhos para saída de texto
header('Content-Type: text/plain');

echo "DIAGNÓSTICO DO GERADOR DE SINAIS\n";
echo "===============================\n\n";
echo "Data e hora atual: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Verificar existência dos arquivos
echo "1. Verificando arquivos necessários:\n";
$file = __DIR__ . '/includes/signal-generator.php';
echo "   - Signal Generator: " . (file_exists($file) ? "EXISTE" : "NÃO ENCONTRADO!") . "\n";
if (file_exists($file)) {
    echo "     Permissões: " . substr(sprintf('%o', fileperms($file)), -4) . "\n";
}

// 2. Verificar banco de dados
require_once __DIR__ . '/config/database.php';
echo "\n2. Verificando banco de dados:\n";
echo "   - Conexão: " . ($conn ? "OK" : "FALHOU!") . "\n";

// 3. Verificar tabelas
$tables = [
    'signal_generator_settings',
    'signal_queue',
    'games',
    'platforms'
];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    echo "   - Tabela '$table': " . ($result->num_rows > 0 ? "EXISTE" : "NÃO ENCONTRADA!") . "\n";
}

// 4. Verificar jogos e plataformas ativos
$result = $conn->query("SELECT COUNT(*) as count FROM games WHERE status = 'active'");
$active_games = $result->fetch_assoc()['count'];
echo "\n3. Verificando dados necessários:\n";
echo "   - Jogos ativos: $active_games\n";

$result = $conn->query("SELECT COUNT(*) as count FROM platforms WHERE status = 'active'");
$active_platforms = $result->fetch_assoc()['count'];
echo "   - Plataformas ativas: $active_platforms\n";

// 5. Verificar configurações do gerador
$result = $conn->query("SELECT * FROM signal_generator_settings");
echo "\n4. Configurações do gerador:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   - {$row['setting_key']}: {$row['setting_value']}\n";
    }
} else {
    echo "   ERRO: Nenhuma configuração encontrada!\n";
}

// 6. Verificar sinais recentes
$result = $conn->query("SELECT id, signal_type, status, created_at FROM signal_queue ORDER BY id DESC LIMIT 5");
echo "\n5. Sinais recentes:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   - ID {$row['id']}: [{$row['signal_type']}] {$row['status']} em {$row['created_at']}\n";
    }
} else {
    echo "   Nenhum sinal gerado ainda.\n";
}

// 7. Testar execução do gerador
echo "\n6. Tentando executar o gerador:\n";
try {
    require_once __DIR__ . '/includes/signal-generator.php';
    echo "   Gerador executado com sucesso!\n";
} catch (Exception $e) {
    echo "   ERRO ao executar o gerador: " . $e->getMessage() . "\n";
}

echo "\nDiagnóstico concluído em " . date('Y-m-d H:i:s');