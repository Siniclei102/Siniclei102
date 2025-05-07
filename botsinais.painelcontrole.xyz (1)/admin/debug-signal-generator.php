<?php
/**
 * Script de Depuração do Gerador de Sinais
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicializar ambiente
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/database.php';

echo "<h1>Diagnóstico do Gerador de Sinais</h1>";
echo "<pre>";

// 1. Verificar conexão com o banco de dados
echo "=== VERIFICANDO CONEXÃO COM O BANCO ===\n";
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
} else {
    echo "Conexão com o banco de dados: OK\n";
}

// 2. Verificar tabelas
echo "\n=== VERIFICANDO TABELAS ===\n";
$tables = ['signal_generator_settings', 'signal_queue', 'signal_history', 'games', 'platforms', 'bots'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "Tabela $table: EXISTE\n";
    } else {
        echo "ERRO: Tabela $table não existe!\n";
    }
}

// 3. Verificar dados nas tabelas
echo "\n=== VERIFICANDO DADOS NAS TABELAS ===\n";

// Verificar configurações
echo "Configurações do gerador:\n";
$result = $conn->query("SELECT * FROM signal_generator_settings");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['setting_key']}: {$row['setting_value']}\n";
    }
} else {
    echo "ERRO: Nenhuma configuração encontrada!\n";
}

// Verificar jogos
echo "\nJogos disponíveis:\n";
$result = $conn->query("SELECT COUNT(*) as count FROM games WHERE status = 'active'");
$row = $result->fetch_assoc();
echo "  - Jogos ativos: {$row['count']}\n";

// Verificar plataformas
echo "\nPlataformas disponíveis:\n";
$result = $conn->query("SELECT COUNT(*) as count FROM platforms WHERE status = 'active'");
$row = $result->fetch_assoc();
echo "  - Plataformas ativas: {$row['count']}\n";

// Verificar bots
echo "\nBots disponíveis:\n";
$result = $conn->query("SELECT COUNT(*) as count FROM bots WHERE status = 'active'");
$row = $result->fetch_assoc();
echo "  - Bots ativos: {$row['count']}\n";

// 4. Testar geração de sinais
echo "\n=== TESTE DE GERAÇÃO DE SINAIS ===\n";

// Incluir classe do gerador
if (file_exists(BASE_PATH . '/includes/signal-generator.php')) {
    echo "Arquivo signal-generator.php: ENCONTRADO\n";
    require_once BASE_PATH . '/includes/signal-generator.php';
    
    try {
        echo "\nTentando gerar um sinal premium manualmente...\n";
        $generator = new SignalGenerator($conn);
        $result = $generator->generateSignal('premium');
        
        if ($result) {
            echo "SUCESSO: Sinal premium gerado com sucesso!\n";
        } else {
            echo "ERRO: Falha ao gerar sinal premium.\n";
        }
    } catch (Exception $e) {
        echo "ERRO: Exceção ao gerar sinal: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "ERRO: Arquivo signal-generator.php não encontrado!\n";
}

// 5. Verificar cronjob (se possível)
echo "\n=== VERIFICANDO CRONJOB ===\n";
if (function_exists('exec')) {
    echo "Executando 'crontab -l'...\n";
    exec('crontab -l 2>&1', $output, $return_var);
    if ($return_var === 0) {
        $found = false;
        foreach ($output as $line) {
            if (strpos($line, 'signal-generator.php') !== false) {
                echo "Cronjob para signal-generator.php: ENCONTRADO\n";
                echo "Linha: $line\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "ERRO: Nenhum cronjob para signal-generator.php encontrado!\n";
        }
    } else {
        echo "Impossível verificar cronjob: " . implode("\n", $output) . "\n";
    }
} else {
    echo "Função exec() desativada. Impossível verificar cronjob automaticamente.\n";
    echo "Verifique manualmente se o cronjob está configurado corretamente.\n";
}

echo "</pre>";

echo "<div style='margin-top: 20px; padding: 10px; background-color: #f0f0f0; border: 1px solid #ccc;'>";
echo "<h2>Como resolver problemas encontrados:</h2>";
echo "<ul>";
echo "<li><strong>Se tabelas não existem:</strong> Execute o script SQL de criação de tabelas</li>";
echo "<li><strong>Se não há dados nas tabelas:</strong> Verifique se as inserções iniciais foram feitas</li>";
echo "<li><strong>Se o arquivo signal-generator.php não foi encontrado:</strong> Verifique o caminho e as permissões</li>";
echo "<li><strong>Se o cronjob não está configurado:</strong> Configure-o com <code>* * * * * php /caminho/para/includes/signal-generator.php</code></li>";
echo "<li><strong>Se o teste de geração manual falhou:</strong> Verifique os logs em /logs/ para identificar o erro específico</li>";
echo "</ul>";
echo "</div>";
?>