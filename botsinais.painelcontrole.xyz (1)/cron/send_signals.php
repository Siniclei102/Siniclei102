<?php
// Arquivo para ser executado via CRON para enviar sinais
define('BASE_PATH', dirname(dirname(__DIR__)) . '/');
require_once BASE_PATH . 'config/database.php';
require_once BASE_PATH . 'includes/functions.php';

// Função para log
function logSignalSending($message, $is_error = false) {
    $log_file = BASE_PATH . 'logs/signal_sending_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] " . ($is_error ? "[ERRO] " : "[INFO] ") . $message . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    // Se for erro, também mostrar no console quando executado manualmente
    if ($is_error) {
        echo $log_message;
    }
}

logSignalSending("Iniciando processo de envio de sinais...");

// Verificar se há bots ativos
$bots_query = "SELECT * FROM telegram_bots WHERE status = 'active'";
$bots_result = $conn->query($bots_query);

if (!$bots_result || $bots_result->num_rows === 0) {
    logSignalSending("Nenhum bot ativo encontrado.", true);
    exit;
}

// Gerar sinais para PG Soft e Pragmatic
function generateSignal($type) {
    $games = [];
    
    if ($type === 'pg_soft') {
        $games = [
            "Fortune Tiger", "Fortune Ox", "Fortune Mouse", 
            "Ganesha Fortune", "Dragon Hatch", "Lucky Neko",
            "Mahjong Ways", "Mahjong Ways 2", "Wild Bandito",
            "Queen of Bounty"
        ];
    } else if ($type === 'pragmatic') {
        $games = [
            "Sweet Bonanza", "Gates of Olympus", "Starlight Princess", 
            "Wild West Gold", "Big Bass Bonanza", "Aztec Gems",
            "Wolf Gold", "The Dog House", "Great Rhino",
            "Fruit Party"
        ];
    }
    
    $game = $games[array_rand($games)];
    $bet = rand(1, 5) * 5; // Valores entre R$5 e R$25
    $multiplier = number_format(rand(20, 200) / 10, 1); // Multiplicadores entre 2.0x e 20.0x
    $minutes = rand(2, 10); // Tempo de duração entre 2 e 10 minutos
    
    // Formato do sinal
    $signal = "🎮 *SINAL CONFIRMADO*\n\n";
    $signal .= "🎯 Jogo: *$game*\n";
    $signal .= "💰 Aposta: R$ $bet,00\n";
    $signal .= "🔥 Multiplicador: {$multiplier}x\n";
    $signal .= "⏱️ Tempo: $minutes minutos\n\n";
    $signal .= "✅ Entre agora e lucre!";
    
    return [
        'game' => $game,
        'bet' => $bet,
        'multiplier' => $multiplier,
        'minutes' => $minutes,
        'text' => $signal,
        'type' => $type
    ];
}

// Função para enviar mensagem para grupos e canais
function sendTelegramMessage($token, $chat_id, $message) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    
    try {
        $result = file_get_contents($url, false, $context);
        return json_decode($result, true);
    } catch (Exception $e) {
        return ['ok' => false, 'description' => $e->getMessage()];
    }
}

// Função para registrar log de envio no banco de dados
function logSendingToDB($conn, $bot_id, $destination_id, $destination_type, $signal_type, $message, $status, $error_message = null) {
    $stmt = $conn->prepare("INSERT INTO signal_sending_logs (bot_id, destination_id, destination_type, signal_type, message, status, error_message) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $bot_id, $destination_id, $destination_type, $signal_type, $message, $status, $error_message);
    $stmt->execute();
}

// Processar cada bot
while ($bot = $bots_result->fetch_assoc()) {
    logSignalSending("Processando bot: " . $bot['name'] . " (ID: " . $bot['id'] . ")");
    
    $bot_id = $bot['id'];
    $token = $bot['token'];
    $bot_type = $bot['type']; // premium, comum, all
    $game_type = $bot['game_type']; // pg_soft, pragmatic, all
    
    // Gerar sinais PG Soft
    if ($game_type == 'pg_soft' || $game_type == 'all') {
        $pg_soft_signal = generateSignal('pg_soft');
        
        // Buscar grupos PG Soft compatíveis com esse bot
        $where_clauses = ["m.bot_id = $bot_id", "g.type = 'pg_soft'"];
        
        if ($bot_type == 'premium') {
            $where_clauses[] = "g.level = 'vip'";
        } else if ($bot_type == 'comum') {
            $where_clauses[] = "g.level = 'comum'";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        $groups_query = "SELECT g.* FROM telegram_groups g 
                        JOIN bot_group_mappings m ON g.id = m.group_id 
                        WHERE $where_sql AND g.status = 'active'";
        
        $groups_result = $conn->query($groups_query);
        
        if ($groups_result && $groups_result->num_rows > 0) {
            logSignalSending("Enviando sinal PG Soft para " . $groups_result->num_rows . " grupos");
            
            while ($group = $groups_result->fetch_assoc()) {
                $response = sendTelegramMessage($token, $group['group_id'], $pg_soft_signal['text']);
                
                if ($response && isset($response['ok']) && $response['ok'] === true) {
                    logSignalSending("Sinal PG Soft enviado com sucesso para o grupo: " . $group['name']);
                    logSendingToDB($conn, $bot_id, $group['id'], 'group', 'pg_soft', $pg_soft_signal['text'], 'success');
                } else {
                    $error = isset($response['description']) ? $response['description'] : 'Erro desconhecido';
                    logSignalSending("Falha ao enviar sinal PG Soft para o grupo: " . $group['name'] . ". Erro: " . $error, true);
                    logSendingToDB($conn, $bot_id, $group['id'], 'group', 'pg_soft', $pg_soft_signal['text'], 'failed', $error);
                }
                
                // Esperar um pouco entre envios para evitar throttling da API
                sleep(1);
            }
        } else {
            logSignalSending("Nenhum grupo PG Soft compatível encontrado para este bot.", true);
        }
    }
    
    // Gerar sinais Pragmatic
    if ($game_type == 'pragmatic' || $game_type == 'all') {
        $pragmatic_signal = generateSignal('pragmatic');
        
        // Buscar grupos Pragmatic compatíveis com esse bot
        $where_clauses = ["m.bot_id = $bot_id", "g.type = 'pragmatic'"];
        
        if ($bot_type == 'premium') {
            $where_clauses[] = "g.level = 'vip'";
        } else if ($bot_type == 'comum') {
            $where_clauses[] = "g.level = 'comum'";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        $groups_query = "SELECT g.* FROM telegram_groups g 
                        JOIN bot_group_mappings m ON g.id = m.group_id 
                        WHERE $where_sql AND g.status = 'active'";
        
        $groups_result = $conn->query($groups_query);
        
        if ($groups_result && $groups_result->num_rows > 0) {
            logSignalSending("Enviando sinal Pragmatic para " . $groups_result->num_rows . " grupos");
            
            while ($group = $groups_result->fetch_assoc()) {
                $response = sendTelegramMessage($token, $group['group_id'], $pragmatic_signal['text']);
                
                if ($response && isset($response['ok']) && $response['ok'] === true) {
                    logSignalSending("Sinal Pragmatic enviado com sucesso para o grupo: " . $group['name']);
                    logSendingToDB($conn, $bot_id, $group['id'], 'group', 'pragmatic', $pragmatic_signal['text'], 'success');
                } else {
                    $error = isset($response['description']) ? $response['description'] : 'Erro desconhecido';
                    logSignalSending("Falha ao enviar sinal Pragmatic para o grupo: " . $group['name'] . ". Erro: " . $error, true);
                    logSendingToDB($conn, $bot_id, $group['id'], 'group', 'pragmatic', $pragmatic_signal['text'], 'failed', $error);
                }
                
                // Esperar um pouco entre envios para evitar throttling da API
                sleep(1);
            }
        } else {
            logSignalSending("Nenhum grupo Pragmatic compatível encontrado para este bot.", true);
        }
    }
    
    // Atualizar última atividade do bot
    $update_bot = $conn->prepare("UPDATE telegram_bots SET last_activity = NOW() WHERE id = ?");
    $update_bot->bind_param("i", $bot_id);
    $update_bot->execute();
    
    logSignalSending("Processamento do bot " . $bot['name'] . " concluído.");
}

logSignalSending("Processo de envio de sinais concluído com sucesso!");