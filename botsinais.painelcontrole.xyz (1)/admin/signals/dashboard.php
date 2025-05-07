<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Inicializar variável $isEditing para evitar warnings
$isEditing = false;
if (isset($_GET['edit_bot'])) {
    $isEditing = true;
    $edit_bot_id = intval($_GET['edit_bot']);
    // Carregar dados do bot para edição
    $bot_query = "SELECT * FROM telegram_bots WHERE id = ?";
    $stmt = $conn->prepare($bot_query);
    $stmt->bind_param("i", $edit_bot_id);
    $stmt->execute();
    $bot_result = $stmt->get_result();
    
    if ($bot_result && $bot_result->num_rows > 0) {
        $bot_data = $bot_result->fetch_assoc();
    } else {
        $_SESSION['message'] = "Bot não encontrado.";
        $_SESSION['alert_type'] = "danger";
        header('Location: dashboard.php');
        exit;
    }
}

// Verificar e criar tabelas necessárias se não existirem
function ensureTablesExist($conn) {
    // Verificar tabela signal_generation_logs
    $check_table = $conn->query("SHOW TABLES LIKE 'signal_generation_logs'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE `signal_generation_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `game_id` int(11) NOT NULL,
            `game_name` varchar(100) NOT NULL,
            `signal_type` varchar(50) NOT NULL,
            `level` enum('vip','comum') NOT NULL,
            `platform` varchar(100) NOT NULL,
            `format` varchar(20) NOT NULL,
            `scheduled_time` varchar(10) NOT NULL,
            `message` text NOT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `signal_type` (`signal_type`),
            KEY `level` (`level`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    
    // Verificar tabela signal_sending_logs
    $check_table = $conn->query("SHOW TABLES LIKE 'signal_sending_logs'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE `signal_sending_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `bot_id` int(11) NOT NULL,
            `destination_id` int(11) NOT NULL,
            `destination_type` enum('group','channel') NOT NULL,
            `signal_type` varchar(50) NOT NULL,
            `message` text NOT NULL,
            `status` enum('success','failed') NOT NULL,
            `error_message` text DEFAULT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `bot_id` (`bot_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    
    // Verificar tabela settings
    $check_table = $conn->query("SHOW TABLES LIKE 'settings'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text NOT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Inserir configurações padrão
        $conn->query("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES 
            ('signal_vip_interval', '15'),
            ('signal_comum_interval', '60'),
            ('signal_platform_pg', 'F12.bet'),
            ('signal_platform_pragmatic', 'F12.bet')
        ");
    }
    
    // Verificar tabela telegram_bots
    $check_table = $conn->query("SHOW TABLES LIKE 'telegram_bots'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE `telegram_bots` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `username` varchar(100) NOT NULL,
            `token` varchar(255) NOT NULL,
            `description` text DEFAULT NULL,
            `type` enum('all','premium','comum') NOT NULL DEFAULT 'all',
            `game_type` enum('all','pg_soft','pragmatic') NOT NULL DEFAULT 'all',
            `status` enum('active','inactive') NOT NULL DEFAULT 'active',
            `created_at` datetime DEFAULT current_timestamp(),
            `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`),
            UNIQUE KEY `username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    
    // Verificar tabela telegram_groups
    $check_table = $conn->query("SHOW TABLES LIKE 'telegram_groups'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE `telegram_groups` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `group_id` varchar(50) NOT NULL,
            `type` enum('pg_soft','pragmatic','all') NOT NULL DEFAULT 'all',
            `level` enum('vip','comum') NOT NULL DEFAULT 'comum',
            `status` enum('active','inactive') NOT NULL DEFAULT 'active',
            `created_at` datetime DEFAULT current_timestamp(),
            `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `group_id` (`group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    
    // Verificar tabela bot_group_mappings
    $check_table = $conn->query("SHOW TABLES LIKE 'bot_group_mappings'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE `bot_group_mappings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `bot_id` int(11) NOT NULL,
            `group_id` int(11) NOT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `bot_group` (`bot_id`, `group_id`),
            KEY `bot_id` (`bot_id`),
            KEY `group_id` (`group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
}

// Criar tabelas necessárias
ensureTablesExist($conn);

// Processar formulário para envio manual de sinal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'send_signal') {
        $signal_id = (int)$_POST['signal_id'];
        $bot_id = (int)$_POST['bot_id'];
        
        // Buscar detalhes do sinal
        $signal_query = "SELECT * FROM signal_generation_logs WHERE id = ?";
        $stmt = $conn->prepare($signal_query);
        $stmt->bind_param("i", $signal_id);
        $stmt->execute();
        $signal_result = $stmt->get_result();
        $signal = $signal_result->fetch_assoc();
        
        // Buscar detalhes do bot
        $bot_query = "SELECT * FROM telegram_bots WHERE id = ?";
        $stmt = $conn->prepare($bot_query);
        $stmt->bind_param("i", $bot_id);
        $stmt->execute();
        $bot_result = $stmt->get_result();
        $bot = $bot_result->fetch_assoc();
        
        if ($signal && $bot) {
            // Enviar sinal via API do Telegram
            $token = $bot['token'];
            $signal_text = $signal['message'];
            $signal_type = $signal['signal_type'];
            $level = $signal['level'];
            
            // Buscar grupos compatíveis
            $groups_query = "SELECT g.* FROM telegram_groups g 
                            JOIN bot_group_mappings m ON g.id = m.group_id 
                            WHERE m.bot_id = ? AND g.type = ? AND g.level = ? AND g.status = 'active'";
            $stmt = $conn->prepare($groups_query);
            $stmt->bind_param("iss", $bot_id, $signal_type, $level);
            $stmt->execute();
            $groups_result = $stmt->get_result();
            
            $success_count = 0;
            $fail_count = 0;
            
            while ($group = $groups_result->fetch_assoc()) {
                // Enviar via API do Telegram
                $url = "https://api.telegram.org/bot$token/sendMessage";
                $data = [
                    'chat_id' => $group['group_id'],
                    'text' => $signal_text,
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
                    $response = json_decode($result, true);
                    
                    if ($response && isset($response['ok']) && $response['ok'] === true) {
                        // Registrar envio no log
                        $stmt = $conn->prepare("INSERT INTO signal_sending_logs (bot_id, destination_id, destination_type, signal_type, message, status) 
                                              VALUES (?, ?, 'group', ?, ?, 'success')");
                        $stmt->bind_param("iiss", $bot_id, $group['id'], $signal_type, $signal_text);
                        $stmt->execute();
                        
                        $success_count++;
                    } else {
                        $error = isset($response['description']) ? $response['description'] : 'Erro desconhecido';
                        // Registrar falha no log
                        $stmt = $conn->prepare("INSERT INTO signal_sending_logs (bot_id, destination_id, destination_type, signal_type, message, status, error_message) 
                                              VALUES (?, ?, 'group', ?, ?, 'failed', ?)");
                        $stmt->bind_param("iisss", $bot_id, $group['id'], $signal_type, $signal_text, $error);
                        $stmt->execute();
                        
                        $fail_count++;
                    }
                } catch (Exception $e) {
                    $fail_count++;
                }
                
                // Esperar um pouco entre envios para evitar throttling da API
                usleep(300000); // 300ms
            }
            
            $_SESSION['message'] = "Sinal enviado para $success_count grupos. Falhas: $fail_count.";
            $_SESSION['alert_type'] = $success_count > 0 ? "success" : "warning";
            
            // Log da ação
            logAdminAction($conn, $_SESSION['user_id'], "Admin enviou manualmente o sinal #$signal_id para {$success_count} grupos");
        } else {
            $_SESSION['message'] = "Sinal ou bot não encontrado.";
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] == 'update_settings') {
        // Atualizar configurações
        $fields = [
            'signal_vip_interval', 'signal_comum_interval', 
            'signal_platform_pg', 'signal_platform_pragmatic'
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = trim($_POST[$field]);
                
                // Verificar se configuração já existe
                $check_query = "SELECT id FROM settings WHERE setting_key = ?";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("s", $field);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Atualizar configuração existente
                    $update_query = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("ss", $value, $field);
                    $stmt->execute();
                } else {
                    // Criar nova configuração
                    $insert_query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param("ss", $field, $value);
                    $stmt->execute();
                }
            }
        }
        
        $_SESSION['message'] = "Configurações atualizadas com sucesso!";
        $_SESSION['alert_type'] = "success";
        
        // Log da ação
        logAdminAction($conn, $_SESSION['user_id'], "Admin atualizou configurações de geração de sinais");
    }
    
    if (isset($_POST['action']) && $_POST['action'] == 'generate_signal') {
        $signal_type = $_POST['signal_type'];
        $level = $_POST['signal_level'];
        
        // Criar um sinal simples sem importar arquivos adicionais
        $signal = generateSimpleSignal($conn, $signal_type, $level);
        
        if ($signal) {
            $_SESSION['message'] = "Sinal {$signal_type} {$level} gerado com sucesso!";
            $_SESSION['alert_type'] = "success";
            
            // Log da ação
            logAdminAction($conn, $_SESSION['user_id'], "Admin gerou manualmente um sinal {$signal_type} {$level}");
        } else {
            $_SESSION['message'] = "Erro ao gerar sinal.";
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Processar adição/edição de bot
    if (isset($_POST['action']) && ($_POST['action'] == 'add_bot' || $_POST['action'] == 'edit_bot')) {
        $name = trim($_POST['bot_name']);
        $username = trim($_POST['bot_username']);
        $token = trim($_POST['bot_token']);
        $description = trim($_POST['bot_description'] ?? '');
        $type = $_POST['bot_type'];
        $game_type = $_POST['game_type'];
        $status = isset($_POST['status']) ? 'active' : 'inactive';
        
        // Remover @ se presente no username
        $username = ltrim($username, '@');
        
        // Validação básica
        if (empty($name) || empty($username) || empty($token)) {
            $_SESSION['message'] = "Todos os campos obrigatórios devem ser preenchidos.";
            $_SESSION['alert_type'] = "danger";
        } else {
            if ($_POST['action'] == 'add_bot') {
                // Verificar se o bot já existe
                $check_query = "SELECT id FROM telegram_bots WHERE username = ? OR token = ?";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("ss", $username, $token);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['message'] = "Um bot com este username ou token já existe.";
                    $_SESSION['alert_type'] = "danger";
                } else {
                    // Inserir novo bot
                    $insert_query = "INSERT INTO telegram_bots (name, username, token, description, type, game_type, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param("sssssss", $name, $username, $token, $description, $type, $game_type, $status);
                    
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Bot adicionado com sucesso!";
                        $_SESSION['alert_type'] = "success";
                        
                        // Log da ação
                        logAdminAction($conn, $_SESSION['user_id'], "Admin adicionou o bot '{$name}'");
                    } else {
                        $_SESSION['message'] = "Erro ao adicionar bot: " . $stmt->error;
                        $_SESSION['alert_type'] = "danger";
                    }
                }
            } else {
                // Editar bot existente
                $bot_id = (int)$_POST['bot_id'];
                
                // Verificar se o username ou token já existem em outro bot
                $check_query = "SELECT id FROM telegram_bots WHERE (username = ? OR token = ?) AND id != ?";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("ssi", $username, $token, $bot_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['message'] = "Um bot com este username ou token já existe.";
                    $_SESSION['alert_type'] = "danger";
                } else {
                    // Atualizar bot
                    $update_query = "UPDATE telegram_bots SET name = ?, username = ?, token = ?, description = ?, type = ?, game_type = ?, status = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("sssssssi", $name, $username, $token, $description, $type, $game_type, $status, $bot_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Bot atualizado com sucesso!";
                        $_SESSION['alert_type'] = "success";
                        
                        // Log da ação
                        logAdminAction($conn, $_SESSION['user_id'], "Admin atualizou o bot '{$name}'");
                    } else {
                        $_SESSION['message'] = "Erro ao atualizar bot: " . $stmt->error;
                        $_SESSION['alert_type'] = "danger";
                    }
                }
            }
        }
    }
    
    // Redirecionar para evitar reenvio do formulário
    header('Location: dashboard.php');
    exit;
}

// Função simples para gerar sinais sem depender de arquivos externos
function generateSimpleSignal($conn, $signal_type, $level) {
    // Buscar jogos do banco de dados
    $provider = ($signal_type == 'pg_soft') ? 'PG' : 'Pragmatic';
    $stmt = $conn->prepare("SELECT id, name FROM games WHERE provider = ? AND status = 'active' ORDER BY RAND() LIMIT 1");
    $stmt->bind_param("s", $provider);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return false; // Nenhum jogo encontrado
    }
    
    $game = $result->fetch_assoc();
    
    // Buscar configurações
    $settings = [
        'signal_platform_pg' => 'F12.bet',
        'signal_platform_pragmatic' => 'F12.bet'
    ];
    
    $settings_query = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('signal_platform_pg', 'signal_platform_pragmatic')";
    $settings_result = $conn->query($settings_query);
    
    if ($settings_result && $settings_result->num_rows > 0) {
        while ($row = $settings_result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    // Configurar valores para o sinal
    $is_turbo = ($level === 'vip') ? (rand(0, 100) > 30) : (rand(0, 100) > 70);
    $formato_jogada = $is_turbo ? "⚡️ 5x Turbo" : "5x Normal";
    
    // Gerar horário para jogar
    $min_wait = ($level === 'vip') ? rand(2, 15) : rand(5, 30);
    $hora_jogar = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    $hora_jogar->modify("+{$min_wait} minutes");
    $horario_formatado = $hora_jogar->format('H:i');
    
    // Determinar plataforma
    $plataforma = ($signal_type === 'pg_soft') ? $settings['signal_platform_pg'] : $settings['signal_platform_pragmatic'];
    
    // Configuração específica para Pragmatic (compra de bônus)
    $compra_bonus = '';
    if ($signal_type === 'pragmatic' && rand(1, 100) <= 40) {
        $compra_bonus = "\n💎 *COMPRAR BÔNUS RECOMENDADO*";
    }
    
    // Emojis e formatação específicos por tipo de jogo
    $emoji_jogo = ($signal_type === 'pg_soft') ? '🐯' : '🎮';
    $emoji_tipo = ($level === 'vip') ? '👑 VIP' : '🔰 PADRÃO';
    $chance_ganho = ($level === 'vip') ? '85-95%' : '75-85%';
    
    // Texto do sinal formatado com Markdown
    $signal_text = "{$emoji_jogo} *SINAL CONFIRMADO {$emoji_tipo}*\n\n";
    $signal_text .= "🎰 *{$game['name']}*\n";
    $signal_text .= "🕒 *HORÁRIO:* {$horario_formatado} (Brasília)\n";
    $signal_text .= "🔄 *RODADAS:* {$formato_jogada}\n";
    $signal_text .= "🎯 *CHANCE:* {$chance_ganho}\n";
    $signal_text .= "🌐 *PLATAFORMA INDICADA:* {$plataforma}\n";
    $signal_text .= $compra_bonus;
    $signal_text .= "\n✅ *Entre no horário e lucre!*";
    
    // Inserir o sinal no banco de dados
    $stmt = $conn->prepare("INSERT INTO signal_generation_logs (game_id, game_name, signal_type, level, platform, format, scheduled_time, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", 
        $game['id'], 
        $game['name'], 
        $signal_type, 
        $level, 
        $plataforma,
        $formato_jogada, 
        $horario_formatado, 
        $signal_text
    );
    
    if (!$stmt->execute()) {
        return false;
    }
    
    $signal_id = $conn->insert_id;
    
    return [
        'id' => $signal_id,
        'game_id' => $game['id'],
        'game_name' => $game['name'],
        'type' => $signal_type,
        'level' => $level,
        'platform' => $plataforma,
        'format' => $formato_jogada,
        'scheduled_time' => $horario_formatado,
        'text' => $signal_text
    ];
}

// Buscar configurações
$settings = [
    'signal_vip_interval' => 15,
    'signal_comum_interval' => 60,
    'signal_platform_pg' => 'F12.bet',
    'signal_platform_pragmatic' => 'F12.bet'
];

$settings_query = "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'signal_%'";
$settings_result = $conn->query($settings_query);

if ($settings_result && $settings_result->num_rows > 0) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Buscar sinais gerados recentemente
$recent_signals = [];
$recent_signals_query = "SELECT * FROM signal_generation_logs ORDER BY created_at DESC LIMIT 50";
$recent_signals_result = $conn->query($recent_signals_query);

if ($recent_signals_result && $recent_signals_result->num_rows > 0) {
    while ($row = $recent_signals_result->fetch_assoc()) {
        $recent_signals[] = $row;
    }
}

// Buscar logs de envio recentes
$sending_logs = [];
$sending_logs_query = "
    SELECT l.*, b.name as bot_name, 
    CASE WHEN l.destination_type = 'group' THEN g.name ELSE c.name END as destination_name
    FROM signal_sending_logs l
    LEFT JOIN telegram_bots b ON l.bot_id = b.id
    LEFT JOIN telegram_groups g ON l.destination_id = g.id AND l.destination_type = 'group'
    LEFT JOIN telegram_channels c ON l.destination_id = c.id AND l.destination_type = 'channel'
    ORDER BY l.created_at DESC LIMIT 50
";

// Verifica se a tabela telegram_channels existe
$check_channels_table = $conn->query("SHOW TABLES LIKE 'telegram_channels'");
if ($check_channels_table->num_rows == 0) {
    $sending_logs_query = "
        SELECT l.*, b.name as bot_name, g.name as destination_name
        FROM signal_sending_logs l
        LEFT JOIN telegram_bots b ON l.bot_id = b.id
        LEFT JOIN telegram_groups g ON l.destination_id = g.id AND l.destination_type = 'group'
        ORDER BY l.created_at DESC LIMIT 50
    ";
}

$sending_logs_result = $conn->query($sending_logs_query);
if ($sending_logs_result && $sending_logs_result->num_rows > 0) {
    while ($row = $sending_logs_result->fetch_assoc()) {
        $sending_logs[] = $row;
    }
}

// Contar status de envios nas últimas 24 horas
$stats = [
    'success' => 0,
    'failed' => 0
];

$stats_query = "
    SELECT status, COUNT(*) as count
    FROM signal_sending_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY status
";
$stats_result = $conn->query($stats_query);
if ($stats_result && $stats_result->num_rows > 0) {
    while ($row = $stats_result->fetch_assoc()) {
        $stats[$row['status']] = (int)$row['count'];
    }
}

// Contar sinais gerados nas últimas 24 horas por tipo
$signal_stats = [
    'pg_soft_vip' => 0,
    'pg_soft_comum' => 0,
    'pragmatic_vip' => 0,
    'pragmatic_comum' => 0
];

$signal_stats_query = "
    SELECT signal_type, level, COUNT(*) as count
    FROM signal_generation_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY signal_type, level
";
$signal_stats_result = $conn->query($signal_stats_query);
if ($signal_stats_result && $signal_stats_result->num_rows > 0) {
    while ($row = $signal_stats_result->fetch_assoc()) {
        $key = $row['signal_type'] . '_' . $row['level'];
        $signal_stats[$key] = (int)$row['count'];
    }
}

// Buscar bots para menu suspenso
$bots = [];
$bots_query = "SELECT id, name, username FROM telegram_bots WHERE status = 'active'";
$bots_result = $conn->query($bots_query);
if ($bots_result && $bots_result->num_rows > 0) {
    while ($row = $bots_result->fetch_assoc()) {
        $bots[] = $row;
    }
}

// Definir título da página
$pageTitle = 'Dashboard de Sinais';

// Obter configurações do site
$siteName = getSetting($conn, 'site_name') ?: 'BotDeSinais';
$siteLogo = getSetting($conn, 'site_logo') ?: 'logo.png';

// Mensagens de Feedback
$message = isset($_SESSION['message']) ? $_SESSION['message'] : null;
$alert_type = isset($_SESSION['alert_type']) ? $_SESSION['alert_type'] : null;

// Limpar as mensagens da sessão
unset($_SESSION['message'], $_SESSION['alert_type']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle . ' | ' . $siteName; ?></title>
    
    <!-- Estilos CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #2e59d9;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --purple-color: #8540f5;
            --pink-color: #e83e8c;
            --orange-color: #fd7e14;
            --teal-color: #20c9a6;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --telegram-color: #0088cc;
            
            --sidebar-width: 250px;
            --topbar-height: 70px;
            --sidebar-collapsed-width: 70px;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
            margin: 0;
            padding: 0;
        }
        
        /* Layout Principal */
        .layout-wrapper {
            display: flex;
            width: 100%;
            overflow: hidden;
        }
        
        /* Sidebar Esquerda */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1030;
            background: linear-gradient(180deg, #222222 10%, #000000 100%);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            color: #fff;
            transition: all 0.3s ease;
            border-radius: 0 15px 15px 0;
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        /* Logo e Branding */
        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 0 15px 0 0;
        }
        
        .sidebar-brand img {
            height: 42px;
            margin-right: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .sidebar-brand h2 {
            font-size: 1.2rem;
            margin: 0;
            color: white;
            font-weight: 700;
            white-space: nowrap;
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-brand h2 {
            opacity: 0;
            width: 0;
        }
        
        /* Admin Badge */
        .admin-badge {
            background-color: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
            margin-left: 0.5rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .sidebar.collapsed .admin-badge,
        .sidebar.mobile-visible .admin-badge {
            display: none;
        }
        
        /* Menu de Navegação */
        .sidebar-menu {
            padding: 1rem 0;
            list-style: none;
            margin: 0;
            overflow-y: auto;
            max-height: calc(100vh - var(--topbar-height));
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.8);
            padding: 0.8rem 1.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
            border-radius: 0 50px 50px 0;
            margin-right: 12px;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu i {
            margin-right: 0.8rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
            transition: margin 0.3s ease;
        }
        
        .sidebar-menu span {
            white-space: nowrap;
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-menu span {
            opacity: 0;
            width: 0;
        }
        
        .sidebar.collapsed .sidebar-menu i {
            margin-right: 0;
            font-size: 1.2rem;
        }
        
        .menu-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin: 1rem 0;
        }
        
        .menu-header {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            padding: 0.8rem 1.5rem;
            margin-top: 0.5rem;
            pointer-events: none;
        }
        
        .sidebar.collapsed .menu-header {
            opacity: 0;
            width: 0;
        }
        
        /* Conteúdo Principal */
        .content-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            width: calc(100% - var(--sidebar-width));
            position: relative;
        }
        
        .content-wrapper.expanded {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }
        
        /* Barra de Topo */
        .topbar {
            height: var(--topbar-height);
            background-color: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        
        .topbar-toggler {
            background: none;
            border: none;
            color: #333;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            margin-right: 1rem;
        }
        
        .topbar-toggler:hover {
            background-color: #f8f9fc;
        }
        
        .topbar-admin-badge {
            display: inline-block;
            background-color: var(--danger-color);
            color: white;
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 0.25rem;
            margin-right: 1rem;
        }
        
        .topbar-user {
            display: flex;
            align-items: center;
            margin-left: auto;
        }
        
        .topbar-user .dropdown-toggle {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #333;
            font-weight: 600;
        }
        
        .topbar-user .dropdown-toggle::after {
            display: none;
        }
        
        .topbar-user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 0.75rem;
            border: 2px solid #eaecf4;
        }
        
        /* Conteúdo da Página */
        .content {
            padding: 1.5rem;
        }
        
        /* Cards de Status */
        .status-card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        
        .status-card:hover {
            transform: translateY(-5px);
        }
        
        .status-card .icon-circle {
            height: 4rem;
            width: 4rem;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .status-card .icon-circle i {
            font-size: 1.75rem;
            color: white;
        }
        
        .status-card .card-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .status-card .card-label {
            font-size: 0.9rem;
            color: #858796;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            font-weight: 700;
        }
        
        .bg-gradient-primary {
            background: linear-gradient(180deg, #4e73df 10%, #2e59d9 100%);
        }
        
        .bg-gradient-success {
            background: linear-gradient(180deg, #1cc88a 10%, #13855c 100%);
        }
        
        .bg-gradient-danger {
            background: linear-gradient(180deg, #e74a3b 10%, #be2617 100%);
        }
        
        .bg-gradient-warning {
            background: linear-gradient(180deg, #f6c23e 10%, #dda20a 100%);
        }
        
        .bg-gradient-info {
            background: linear-gradient(180deg, #36b9cc 10%, #258391 100%);
        }
        
        .bg-gradient-purple {
            background: linear-gradient(180deg, #8540f5 10%, #5a23b9 100%);
        }
        
        .bg-gradient-dark {
            background: linear-gradient(180deg, #5a5c69 10%, #373840 100%);
        }
        
        /* Sinais e Logs */
        .signals-container {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .signals-header {
            border-radius: 0.75rem 0.75rem 0 0;
            padding: 1rem 1.25rem;
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .signals-body {
            padding: 1.25rem;
        }
        
        .signals-table {
            font-size: 0.85rem;
        }
        
        .signals-table th,
        .signals-table td {
            padding: 0.75rem 0.5rem;
            vertical-align: middle;
        }
        
        .signal-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 700;
            display: inline-block;
            text-align: center;
        }
        
        .badge-pg-vip {
            background-color: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
        }
        
        .badge-pg-comum {
            background-color: rgba(156, 39, 176, 0.05);
            color: #9c27b0;
        }
        
        .badge-pragmatic-vip {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .badge-pragmatic-comum {
            background-color: rgba(33, 150, 243, 0.05);
            color: #2196f3;
        }
        
        /* Configurações */
        .config-card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .config-card .card-header {
            border-radius: 0.75rem 0.75rem 0 0;
            padding: 1rem 1.25rem;
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 700;
        }
        
        .config-card .card-body {
            padding: 1.25rem;
        }
        
        /* Botões de ação */
        .btn-action {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 0.25rem;
            padding: 0;
        }
        
        /* Signal Preview */
        .signal-preview {
            background-color: #13151c;
            color: white;
            border-radius: 0.75rem;
            padding: 1.25rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            border: 1px solid #2b2d36;
            white-space: pre-wrap;
        }
        
        /* Estilos para responsividade */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1040;
            }
            
            .sidebar.mobile-visible {
                transform: translateX(0);
            }
            
            .content-wrapper {
                margin-left: 0;
                width: 100%;
            }
            
            .content-wrapper.expanded {
                margin-left: 0;
                width: 100%;
            }
            
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1035;
            }
            
            .overlay.active {
                display: block;
            }
            
            .status-cards {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }
        
        @media (min-width: 992px) {
            .status-cards {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay para menu mobile -->
    <div class="overlay" id="overlay"></div>
    
    <div class="layout-wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-brand">
                <img src="../../assets/img/<?php echo $siteLogo; ?>" alt="<?php echo $siteName; ?>">
                <h2><?php echo $siteName; ?> <span class="admin-badge">Admin</span></h2>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="../dashboard.php">
                        <i class="fas fa-tachometer-alt" style="color: var(--danger-color);"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <div class="menu-header">Gerenciamento</div>
                
                <li>
                    <a href="../users/">
                        <i class="fas fa-users" style="color: var(--primary-color);"></i>
                        <span>Usuários</span>
                    </a>
                </li>
                <li>
                    <a href="../bots/">
                        <i class="fas fa-robot" style="color: var(--success-color);"></i>
                        <span>Bots</span>
                    </a>
                </li>
                <li>
                    <a href="../telegram_channels/">
                        <i class="fab fa-telegram" style="color: #0088cc;"></i>
                        <span>Canais</span>
                    </a>
                </li>
                <li>
                    <a href="../telegram_groups/">
                        <i class="fas fa-users" style="color: #0088cc;"></i>
                        <span>Grupos</span>
                    </a>
                </li>
                <li>
                    <a href="../telegram_users/">
                        <i class="fas fa-user" style="color: #0088cc;"></i>
                        <span>Telegram Usuários</span>
                    </a>
                </li>
                <li>
                    <a href="../signal_sources/">
                        <i class="fas fa-gamepad" style="color: var(--warning-color);"></i>
                        <span>Fontes de Sinais</span>
                    </a>
                </li>

                <div class="menu-header">Sinais</div>
                
                <li>
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-signal" style="color: var(--purple-color);"></i>
                        <span>Dashboard de Sinais</span>
                    </a>
                </li>
                <li>
                    <a href="manage_games.php">
                        <i class="fas fa-dice" style="color: var(--info-color);"></i>
                        <span>Gerenciar Jogos</span>
                    </a>
                </li>
                
                <div class="menu-header">Configurações</div>
                
                <li>
                    <a href="../settings/">
                        <i class="fas fa-cog" style="color: var(--purple-color);"></i>
                        <span>Configurações</span>
                    </a>
                </li>
                <li>
                    <a href="../logs/">
                        <i class="fas fa-clipboard-list" style="color: var(--teal-color);"></i>
                        <span>Logs do Sistema</span>
                    </a>
                </li>
                
                <div class="menu-divider"></div>
                
                <li>
                    <a href="../../user/dashboard.php">
                        <i class="fas fa-user" style="color: var(--orange-color);"></i>
                        <span>Modo Usuário</span>
                    </a>
                </li>
                <li>
                    <a href="../../logout.php">
                        <i class="fas fa-sign-out-alt" style="color: var(--danger-color);"></i>
                        <span>Sair</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Conteúdo Principal -->
        <div class="content-wrapper" id="content-wrapper">
            <!-- Barra Superior -->
            <div class="topbar">
                <button class="topbar-toggler" id="sidebar-toggler" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="topbar-admin-badge">Admin</div>
                
                <div class="topbar-user">
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-md-inline-block me-1"><?php echo $_SESSION['username']; ?></span>
                            <img src="../../assets/img/admin-avatar.png" alt="Admin">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i> Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="../settings/"><i class="fas fa-cog me-2"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Conteúdo da Página -->
            <div class="content">
                <!-- Cabeçalho da página -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Dashboard de Sinais</h1>
                    
                    <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateSignalModal">
                            <i class="fas fa-plus-circle me-1"></i> Gerar Sinal Manual
                        </button>
                        <button type="button" class="btn btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#configModal">
                            <i class="fas fa-cog me-1"></i> Configurações
                        </button>
                        <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#botModal">
                            <i class="fas fa-robot me-1"></i> Adicionar Bot
                        </button>
                    </div>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Cards de Status -->
                <div class="status-cards mb-4">
                    <div class="status-card">
                        <div class="icon-circle bg-gradient-primary mb-3">
                            <i class="fas fa-signal"></i>
                        </div>
                        <div class="card-value"><?php echo count($recent_signals); ?></div>
                        <div class="card-label">Total de Sinais</div>
                    </div>
                    
                    <div class="status-card">
                        <div class="icon-circle bg-gradient-success mb-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-value"><?php echo $stats['success']; ?></div>
                        <div class="card-label">Envios com Sucesso</div>
                    </div>
                    
                    <div class="status-card">
                        <div class="icon-circle bg-gradient-danger mb-3">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="card-value"><?php echo $stats['failed']; ?></div>
                        <div class="card-label">Falhas de Envio</div>
                    </div>
                    
                    <div class="status-card">
                        <div class="icon-circle bg-gradient-info mb-3">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="card-value"><?php echo count($bots); ?></div>
                        <div class="card-label">Bots Ativos</div>
                    </div>
                </div>
                
                <!-- Estatísticas de Sinais -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="signals-container h-100">
                            <div class="signals-header">
                                <span><i class="fas fa-dice me-2" style="color: #9c27b0;"></i> Sinais PG Soft (24h)</span>
                            </div>
                            <div class="signals-body">
                                <div class="d-flex justify-content-around">
                                    <div class="text-center">
                                        <div class="h4 mb-0 fw-bold"><?php echo $signal_stats['pg_soft_vip']; ?></div>
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                                            <span class="badge bg-purple text-white">VIP</span>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="h4 mb-0 fw-bold"><?php echo $signal_stats['pg_soft_comum']; ?></div>
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                                            <span class="badge bg-secondary">COMUM</span>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="h4 mb-0 fw-bold"><?php echo $signal_stats['pg_soft_vip'] + $signal_stats['pg_soft_comum']; ?></div>
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                                            <span class="badge bg-primary">TOTAL</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="signals-container h-100">
                            <div class="signals-header">
                                <span><i class="fas fa-gamepad me-2" style="color: #2196f3;"></i> Sinais Pragmatic (24h)</span>
                            </div>
                            <div class="signals-body">
                                <div class="d-flex justify-content-around">
                                    <div class="text-center">
                                        <div class="h4 mb-0 fw-bold"><?php echo $signal_stats['pragmatic_vip']; ?></div>
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                                            <span class="badge bg-purple text-white">VIP</span>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="h4 mb-0 fw-bold"><?php echo $signal_stats['pragmatic_comum']; ?></div>
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                                            <span class="badge bg-secondary">COMUM</span>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="h4 mb-0 fw-bold"><?php echo $signal_stats['pragmatic_vip'] + $signal_stats['pragmatic_comum']; ?></div>
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                                            <span class="badge bg-primary">TOTAL</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Abas para Sinais e Logs -->
                <ul class="nav nav-tabs mb-4" id="tabsinais" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="sinais-tab" data-bs-toggle="tab" data-bs-target="#sinais-tab-pane" type="button" role="tab" aria-controls="sinais-tab-pane" aria-selected="true">
                            <i class="fas fa-signal me-1"></i> Últimos Sinais Gerados
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs-tab-pane" type="button" role="tab" aria-controls="logs-tab-pane" aria-selected="false">
                            <i class="fas fa-clipboard-list me-1"></i> Logs de Envio
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="tabSinaisContent">
                    <!-- Últimos Sinais Gerados -->
                    <div class="tab-pane fade show active" id="sinais-tab-pane" role="tabpanel" aria-labelledby="sinais-tab" tabindex="0">
                        <div class="signals-container">
                            <div class="signals-header">
                                <span><i class="fas fa-signal me-1"></i> Últimos Sinais Gerados</span>
                            </div>
                            <div class="signals-body">
                                <?php if (count($recent_signals) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover signals-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Jogo</th>
                                                <th>Tipo</th>
                                                <th>Nível</th>
                                                <th>Formato</th>
                                                <th>Hora Agendada</th>
                                                <th>Data Geração</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_signals as $signal): ?>
                                            <tr>
                                                <td><?php echo $signal['id']; ?></td>
                                                <td><?php echo htmlspecialchars($signal['game_name']); ?></td>
                                                <td>
                                                    <?php if ($signal['signal_type'] == 'pg_soft'): ?>
                                                    <span class="signal-badge" style="background-color: rgba(156, 39, 176, 0.1); color: #9c27b0;">
                                                        <i class="fas fa-dice"></i> PG Soft
                                                    </span>
                                                    <?php else: ?>
                                                                                                        <span class="signal-badge" style="background-color: rgba(33, 150, 243, 0.1); color: #2196f3;">
                                                        <i class="fas fa-gamepad"></i> Pragmatic
                                                    </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($signal['level'] == 'vip'): ?>
                                                    <span class="signal-badge" style="background-color: rgba(156, 39, 176, 0.1); color: #9c27b0;">
                                                        <i class="fas fa-crown"></i> VIP
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="signal-badge" style="background-color: rgba(158, 158, 158, 0.1); color: #757575;">
                                                        <i class="fas fa-user"></i> Comum
                                                    </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (strpos($signal['format'], 'Turbo') !== false): ?>
                                                    <span class="signal-badge" style="background-color: rgba(255, 87, 34, 0.1); color: #FF5722;">
                                                        <i class="fas fa-bolt"></i> <?php echo htmlspecialchars($signal['format']); ?>
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="signal-badge" style="background-color: rgba(76, 175, 80, 0.1); color: #4CAF50;">
                                                        <?php echo htmlspecialchars($signal['format']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $signal['scheduled_time']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($signal['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-primary btn-sm btn-action" data-bs-toggle="modal" data-bs-target="#viewSignalModal" 
                                                        data-signal-id="<?php echo $signal['id']; ?>" 
                                                        data-signal-text="<?php echo htmlspecialchars($signal['message']); ?>"
                                                        title="Visualizar Sinal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-success btn-sm btn-action" data-bs-toggle="modal" data-bs-target="#sendSignalModal" 
                                                        data-signal-id="<?php echo $signal['id']; ?>" 
                                                        data-signal-type="<?php echo $signal['signal_type']; ?>" 
                                                        data-signal-level="<?php echo $signal['level']; ?>"
                                                        title="Enviar Sinal">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-signal fa-3x text-secondary mb-3"></i>
                                    <h5>Nenhum sinal gerado até o momento</h5>
                                    <p class="text-muted">Os sinais serão gerados automaticamente pelo sistema</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Logs de Envio -->
                    <div class="tab-pane fade" id="logs-tab-pane" role="tabpanel" aria-labelledby="logs-tab" tabindex="0">
                        <div class="signals-container">
                            <div class="signals-header">
                                <span><i class="fas fa-clipboard-list me-1"></i> Logs de Envio de Sinais</span>
                            </div>
                            <div class="signals-body">
                                <?php if (count($sending_logs) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover signals-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Bot</th>
                                                <th>Destino</th>
                                                <th>Tipo</th>
                                                <th>Status</th>
                                                <th>Data</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sending_logs as $log): ?>
                                            <tr>
                                                <td><?php echo $log['id']; ?></td>
                                                <td><?php echo htmlspecialchars($log['bot_name'] ?? 'Desconhecido'); ?></td>
                                                <td><?php echo htmlspecialchars($log['destination_name'] ?? 'Desconhecido'); ?></td>
                                                <td>
                                                    <?php if ($log['destination_type'] == 'group'): ?>
                                                    <span class="badge bg-primary">Grupo</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-info">Canal</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($log['status'] == 'success'): ?>
                                                    <span class="badge bg-success">Sucesso</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-danger">Falha</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-primary btn-sm btn-action" data-bs-toggle="modal" data-bs-target="#viewLogModal" 
                                                        data-log-id="<?php echo $log['id']; ?>" 
                                                        data-log-message="<?php echo htmlspecialchars($log['message']); ?>"
                                                        data-log-error="<?php echo htmlspecialchars($log['error_message'] ?? ''); ?>"
                                                        data-log-status="<?php echo $log['status']; ?>"
                                                        title="Ver Detalhes">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-clipboard-list fa-3x text-secondary mb-3"></i>
                                    <h5>Nenhum log de envio até o momento</h5>
                                    <p class="text-muted">Os logs aparecerão quando sinais forem enviados</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Visualizar Sinal -->
    <div class="modal fade" id="viewSignalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Visualizar Sinal #<span id="viewSignalId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="signal-preview" id="signalPreview">
                        
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Enviar Sinal -->
    <div class="modal fade" id="sendSignalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar Sinal #<span id="sendSignalId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="dashboard.php" id="sendSignalForm">
                        <input type="hidden" name="action" value="send_signal">
                        <input type="hidden" name="signal_id" id="modalSignalId">
                        
                        <div class="mb-3">
                            <label for="bot_id" class="form-label">Selecione o Bot</label>
                            <select class="form-select" id="bot_id" name="bot_id" required>
                                <option value="">Selecione um bot</option>
                                <?php foreach ($bots as $bot): ?>
                                <option value="<?php echo $bot['id']; ?>"><?php echo htmlspecialchars($bot['name']); ?> (@<?php echo htmlspecialchars($bot['username']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Selecione o bot que enviará este sinal</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            O sinal será enviado para todos os grupos compatíveis com o tipo <strong><span id="signalTypeDisplay"></span></strong> e nível <strong><span id="signalLevelDisplay"></span></strong> que estão mapeados para o bot selecionado.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="sendSignalForm" class="btn btn-success">
                        <i class="fas fa-paper-plane me-1"></i> Enviar Agora
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Visualizar Log -->
    <div class="modal fade" id="viewLogModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Log #<span id="viewLogId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Mensagem Enviada</label>
                        <div class="signal-preview" id="logMessagePreview"></div>
                    </div>
                    
                    <div id="errorSection" class="mb-3 d-none">
                        <label class="form-label">Erro</label>
                        <div class="alert alert-danger" id="logErrorMessage"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Gerar Sinal -->
    <div class="modal fade" id="generateSignalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gerar Novo Sinal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="dashboard.php" id="generateSignalForm">
                        <input type="hidden" name="action" value="generate_signal">
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Sinal</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="signal_type" id="signal_type_pg" value="pg_soft" checked>
                                    <label class="form-check-label" for="signal_type_pg">
                                        <i class="fas fa-dice me-1" style="color: #9c27b0;"></i> PG Soft
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="signal_type" id="signal_type_pragmatic" value="pragmatic">
                                    <label class="form-check-label" for="signal_type_pragmatic">
                                        <i class="fas fa-gamepad me-1" style="color: #2196f3;"></i> Pragmatic
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nível</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="signal_level" id="signal_level_vip" value="vip" checked>
                                    <label class="form-check-label" for="signal_level_vip">
                                        <i class="fas fa-crown me-1" style="color: #f6c23e;"></i> VIP
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="signal_level" id="signal_level_comum" value="comum">
                                    <label class="form-check-label" for="signal_level_comum">
                                        <i class="fas fa-user-friends me-1"></i> Comum
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            O sistema vai gerar um novo sinal com base nos jogos cadastrados no banco de dados
                            e nas configurações definidas.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="generateSignalForm" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-1"></i> Gerar Sinal
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Configurações -->
    <div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Configurações de Geração de Sinais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="dashboard.php" id="settingsForm">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="mb-3">
                            <label for="signal_vip_interval" class="form-label">Intervalo de Geração para VIP (minutos)</label>
                            <input type="number" class="form-control" id="signal_vip_interval" name="signal_vip_interval" value="<?php echo $settings['signal_vip_interval']; ?>" min="5" max="120" required>
                            <div class="form-text">Tempo em minutos entre cada geração de sinais VIP</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="signal_comum_interval" class="form-label">Intervalo de Geração para Comum (minutos)</label>
                            <input type="number" class="form-control" id="signal_comum_interval" name="signal_comum_interval" value="<?php echo $settings['signal_comum_interval']; ?>" min="15" max="240" required>
                            <div class="form-text">Tempo em minutos entre cada geração de sinais Comuns</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="signal_platform_pg" class="form-label">Plataforma para PG Soft</label>
                            <input type="text" class="form-control" id="signal_platform_pg" name="signal_platform_pg" value="<?php echo $settings['signal_platform_pg']; ?>" required>
                            <div class="form-text">Nome da plataforma indicada para jogos PG Soft</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="signal_platform_pragmatic" class="form-label">Plataforma para Pragmatic</label>
                            <input type="text" class="form-control" id="signal_platform_pragmatic" name="signal_platform_pragmatic" value="<?php echo $settings['signal_platform_pragmatic']; ?>" required>
                            <div class="form-text">Nome da plataforma indicada para jogos Pragmatic</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="settingsForm" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Salvar Configurações
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Adicionar/Editar Bot -->
    <div class="modal fade" id="botModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $isEditing ? 'Editar Bot' : 'Adicionar Bot'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="dashboard.php" id="botForm">
                        <input type="hidden" name="action" value="<?php echo $isEditing ? 'edit_bot' : 'add_bot'; ?>">
                        <?php if ($isEditing): ?>
                        <input type="hidden" name="bot_id" value="<?php echo $bot_data['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i> Informações do Bot</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="bot_name" class="form-label">Nome do Bot*</label>
                                    <input type="text" class="form-control" id="bot_name" name="bot_name" required
                                           value="<?php echo $isEditing ? htmlspecialchars($bot_data['name']) : ''; ?>">
                                    <div class="form-text">Nome para identificar o bot no sistema</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bot_username" class="form-label">Username do Bot*</label>
                                    <div class="input-group">
                                        <span class="input-group-text">@</span>
                                        <input type="text" class="form-control" id="bot_username" name="bot_username" required
                                               value="<?php echo $isEditing ? htmlspecialchars($bot_data['username']) : ''; ?>">
                                    </div>
                                    <div class="form-text">Username do bot no Telegram (sem @)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bot_token" class="form-label">Token do Bot*</label>
                                    <input type="text" class="form-control" id="bot_token" name="bot_token" required
                                           value="<?php echo $isEditing ? htmlspecialchars($bot_data['token']) : ''; ?>">
                                    <div class="form-text">Token fornecido pelo BotFather (formato: 123456789:ABCDEFabcdef1234567890)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bot_description" class="form-label">Descrição</label>
                                    <textarea class="form-control" id="bot_description" name="bot_description" rows="3"><?php echo $isEditing ? htmlspecialchars($bot_data['description'] ?? '') : ''; ?></textarea>
                                    <div class="form-text">Informações adicionais sobre este bot (opcional)</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-cog me-2"></i> Configurações de Envio</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label d-block">Tipo de Bot</label>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="bot_type" id="type_all" value="all"
                                               <?php echo (!$isEditing || ($isEditing && $bot_data['type'] == 'all')) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="type_all">
                                            <i class="fas fa-users me-1 text-primary"></i> Todos os Grupos
                                        </label>
                                        <div class="form-text">Bot enviará sinais para grupos VIP e comuns</div>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="bot_type" id="type_premium" value="premium"
                                               <?php echo ($isEditing && $bot_data['type'] == 'premium') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="type_premium">
                                            <i class="fas fa-crown me-1 text-warning"></i> Apenas Grupos VIP
                                        </label>
                                        <div class="form-text">Bot enviará sinais apenas para grupos VIP</div>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bot_type" id="type_comum" value="comum"
                                               <?php echo ($isEditing && $bot_data['type'] == 'comum') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="type_comum">
                                            <i class="fas fa-users me-1 text-success"></i> Apenas Grupos Comuns
                                        </label>
                                        <div class="form-text">Bot enviará sinais apenas para grupos comuns</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label d-block">Tipo de Jogo</label>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="game_type" id="game_all" value="all"
                                               <?php echo (!$isEditing || ($isEditing && $bot_data['game_type'] == 'all')) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="game_all">
                                            <i class="fas fa-gamepad me-1 text-primary"></i> Todos os Jogos
                                        </label>
                                        <div class="form-text">Bot enviará sinais para ambos os tipos de jogos</div>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="game_type" id="game_pgsoft" value="pg_soft"
                                               <?php echo ($isEditing && $bot_data['game_type'] == 'pg_soft') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="game_pgsoft">
                                            <i class="fas fa-dice me-1 text-danger"></i> Apenas PG Soft
                                        </label>
                                        <div class="form-text">Bot enviará apenas sinais de jogos PG Soft</div>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="game_type" id="game_pragmatic" value="pragmatic"
                                               <?php echo ($isEditing && $bot_data['game_type'] == 'pragmatic') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="game_pragmatic">
                                            <i class="fas fa-gamepad me-1 text-info"></i> Apenas Pragmatic
                                        </label>
                                        <div class="form-text">Bot enviará apenas sinais de jogos Pragmatic</div>
                                    </div>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="status" name="status"
                                           <?php echo (!$isEditing || ($isEditing && $bot_data['status'] == 'active')) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status">Bot Ativo</label>
                                    <div class="form-text">Desative para pausar o envio de sinais por este bot</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Instruções:</strong>
                            <ol class="mb-0 ps-3 mt-2">
                                <li>Crie um bot através do @BotFather no Telegram</li>
                                <li>Copie o token fornecido pelo BotFather</li>
                                <li>Adicione o bot como administrador nos grupos e canais desejados</li>
                                <li>Configure os grupos e canais que este bot deve acessar</li>
                            </ol>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="botForm" class="btn btn-primary">
                        <?php echo $isEditing ? 'Salvar Alterações' : 'Adicionar Bot'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Código para o menu lateral (sidebar)
        var sidebar = document.getElementById('sidebar');
        var contentWrapper = document.getElementById('content-wrapper');
        var sidebarToggler = document.getElementById('sidebar-toggler');
        var overlay = document.getElementById('overlay');
        
        // Função para verificar se é mobile
        var isMobileDevice = function() {
            return window.innerWidth < 992;
        };
        
        // Função para alternar o menu
        function toggleSidebar() {
            if (isMobileDevice()) {
                sidebar.classList.toggle('mobile-visible');
                overlay.classList.toggle('active');
            } else {
                sidebar.classList.toggle('collapsed');
                contentWrapper.classList.toggle('expanded');
            }
        }
        
        // Event listeners para o sidebar
        if (sidebarToggler) {
            sidebarToggler.addEventListener('click', toggleSidebar);
        }
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-visible');
                overlay.classList.remove('active');
            });
        }
        
        // Verificar redimensionamento da janela
        window.addEventListener('resize', function() {
            if (isMobileDevice()) {
                sidebar.classList.remove('collapsed');
                contentWrapper.classList.remove('expanded');
                
                // Se o menu estava aberto no mobile, mantê-lo aberto
                if (!sidebar.classList.contains('mobile-visible')) {
                    overlay.classList.remove('active');
                }
            } else {
                sidebar.classList.remove('mobile-visible');
                overlay.classList.remove('active');
            }
        });
        
        // Modal de visualizar sinal
        var viewSignalModal = document.getElementById('viewSignalModal');
        if (viewSignalModal) {
            viewSignalModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var signalId = button.getAttribute('data-signal-id');
                var signalText = button.getAttribute('data-signal-text');
                
                document.getElementById('viewSignalId').textContent = signalId;
                document.getElementById('signalPreview').textContent = signalText;
            });
        }
        
        // Modal de enviar sinal
        var sendSignalModal = document.getElementById('sendSignalModal');
        if (sendSignalModal) {
            sendSignalModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var signalId = button.getAttribute('data-signal-id');
                var signalType = button.getAttribute('data-signal-type');
                var signalLevel = button.getAttribute('data-signal-level');
                
                document.getElementById('sendSignalId').textContent = signalId;
                document.getElementById('modalSignalId').value = signalId;
                
                // Mostrar tipo e nível do sinal
                var signalTypeDisplay = document.getElementById('signalTypeDisplay');
                var signalLevelDisplay = document.getElementById('signalLevelDisplay');
                
                signalTypeDisplay.textContent = signalType === 'pg_soft' ? 'PG Soft' : 'Pragmatic';
                signalLevelDisplay.textContent = signalLevel === 'vip' ? 'VIP' : 'Comum';
            });
        }
        
        // Modal de visualizar log
        var viewLogModal = document.getElementById('viewLogModal');
        if (viewLogModal) {
            viewLogModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var logId = button.getAttribute('data-log-id');
                var logMessage = button.getAttribute('data-log-message');
                var logError = button.getAttribute('data-log-error');
                var logStatus = button.getAttribute('data-log-status');
                
                document.getElementById('viewLogId').textContent = logId;
                document.getElementById('logMessagePreview').textContent = logMessage;
                
                var errorSection = document.getElementById('errorSection');
                var logErrorMessage = document.getElementById('logErrorMessage');
                
                if (logStatus === 'failed' && logError) {
                    errorSection.classList.remove('d-none');
                    logErrorMessage.textContent = logError;
                } else {
                    errorSection.classList.add('d-none');
                }
            });
        }
    });
    </script>
</body>
</html>