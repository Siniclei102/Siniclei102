<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'save_bot') {
        $bot_id = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;
        $bot_name = trim($_POST['bot_name']);
        $bot_username = trim($_POST['bot_username']);
        $bot_token = trim($_POST['bot_token']);
        $description = trim($_POST['description'] ?? '');
        $type = $_POST['bot_type']; // 'premium', 'comum', 'all'
        $game_type = $_POST['game_type']; // 'pg_soft', 'pragmatic', 'all'
        $status = isset($_POST['status']) && $_POST['status'] == '1' ? 'active' : 'inactive';
        
        // Validação
        if (empty($bot_name) || empty($bot_username) || empty($bot_token)) {
            $_SESSION['message'] = "Todos os campos obrigatórios devem ser preenchidos.";
            $_SESSION['alert_type'] = "danger";
        } else {
            // Remover @ do username se existir
            $bot_username = ltrim($bot_username, '@');
            
            if ($bot_id > 0) {
                // Atualizar bot existente
                $stmt = $conn->prepare("UPDATE telegram_bots SET 
                    name = ?, username = ?, token = ?, description = ?, 
                    type = ?, game_type = ?, status = ?, 
                    updated_at = NOW() 
                    WHERE id = ?");
                $stmt->bind_param("sssssssi", $bot_name, $bot_username, $bot_token, $description, 
                                $type, $game_type, $status, $bot_id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Bot atualizado com sucesso!";
                    $_SESSION['alert_type'] = "success";
                    
                    // Log da ação
                    logAdminAction($conn, $_SESSION['user_id'], "Admin atualizou o bot '{$bot_name}'");
                } else {
                    $_SESSION['message'] = "Erro ao atualizar bot: " . $stmt->error;
                    $_SESSION['alert_type'] = "danger";
                }
            } else {
                // Inserir novo bot
                $stmt = $conn->prepare("INSERT INTO telegram_bots 
                    (name, username, token, description, type, game_type, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $bot_name, $bot_username, $bot_token, $description, 
                                $type, $game_type, $status);
                
                if ($stmt->execute()) {
                    $bot_id = $conn->insert_id;
                    $_SESSION['message'] = "Bot adicionado com sucesso!";
                    $_SESSION['alert_type'] = "success";
                    
                    // Log da ação
                    logAdminAction($conn, $_SESSION['user_id'], "Admin adicionou o bot '{$bot_name}'");
                } else {
                    $_SESSION['message'] = "Erro ao adicionar bot: " . $stmt->error;
                    $_SESSION['alert_type'] = "danger";
                }
            }
        }
    }
    
    // Atualizar mapeamentos de grupos
    if (isset($_POST['action']) && $_POST['action'] == 'update_mappings') {
        $bot_id = (int)$_POST['bot_id'];
        
        // Primeiro, remover todos os mapeamentos existentes
        $conn->query("DELETE FROM bot_group_mappings WHERE bot_id = $bot_id");
        
        // Adicionar novos mapeamentos
        if (isset($_POST['selected_groups']) && is_array($_POST['selected_groups'])) {
            foreach ($_POST['selected_groups'] as $group_id) {
                $conn->query("INSERT INTO bot_group_mappings (bot_id, group_id) VALUES ($bot_id, $group_id)");
            }
            
            $_SESSION['message'] = "Mapeamentos de grupos atualizados com sucesso!";
            $_SESSION['alert_type'] = "success";
            
            // Log da ação
            $bot_name = getBotNameById($conn, $bot_id);
            logAdminAction($conn, $_SESSION['user_id'], "Admin atualizou os mapeamentos de grupos do bot '{$bot_name}'");
        } else {
            $_SESSION['message'] = "Nenhum grupo selecionado para este bot.";
            $_SESSION['alert_type'] = "warning";
        }
    }
    
    // Atualizar mapeamentos de canais
    if (isset($_POST['action']) && $_POST['action'] == 'update_channel_mappings') {
        $bot_id = (int)$_POST['bot_id'];
        
        // Primeiro, remover todos os mapeamentos existentes
        $conn->query("DELETE FROM bot_channel_mappings WHERE bot_id = $bot_id");
        
        // Adicionar novos mapeamentos
        if (isset($_POST['selected_channels']) && is_array($_POST['selected_channels'])) {
            foreach ($_POST['selected_channels'] as $channel_id) {
                $conn->query("INSERT INTO bot_channel_mappings (bot_id, channel_id) VALUES ($bot_id, $channel_id)");
            }
            
            $_SESSION['message'] = "Mapeamentos de canais atualizados com sucesso!";
            $_SESSION['alert_type'] = "success";
            
            // Log da ação
            $bot_name = getBotNameById($conn, $bot_id);
            logAdminAction($conn, $_SESSION['user_id'], "Admin atualizou os mapeamentos de canais do bot '{$bot_name}'");
        } else {
            $_SESSION['message'] = "Nenhum canal selecionado para este bot.";
            $_SESSION['alert_type'] = "warning";
        }
    }
    
    // Teste de envio de mensagem
    if (isset($_POST['action']) && $_POST['action'] == 'test_bot') {
        $bot_id = (int)$_POST['bot_id'];
        $chat_id = trim($_POST['chat_id']);
        $test_message = trim($_POST['test_message']);
        
        // Buscar token do bot
        $stmt = $conn->prepare("SELECT token FROM telegram_bots WHERE id = ?");
        $stmt->bind_param("i", $bot_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $bot = $result->fetch_assoc();
            $token = $bot['token'];
            
            // Testar envio via API do Telegram
            $response = sendTelegramMessage($token, $chat_id, $test_message);
            
            if ($response && isset($response['ok']) && $response['ok'] === true) {
                $_SESSION['message'] = "Mensagem de teste enviada com sucesso!";
                $_SESSION['alert_type'] = "success";
                
                // Log da ação
                $bot_name = getBotNameById($conn, $bot_id);
                logAdminAction($conn, $_SESSION['user_id'], "Admin enviou uma mensagem de teste usando o bot '{$bot_name}'");
            } else {
                $error = isset($response['description']) ? $response['description'] : 'Erro desconhecido';
                $_SESSION['message'] = "Erro ao enviar mensagem de teste: $error";
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Bot não encontrado.";
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Redirecionar para evitar reenvio do formulário
    header('Location: manage_bots.php' . (isset($_POST['bot_id']) && $_POST['bot_id'] > 0 ? '?id=' . $_POST['bot_id'] : ''));
    exit;
}

// Verificar tabela de bots
$check_table = $conn->query("SHOW TABLES LIKE 'telegram_bots'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE `telegram_bots` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `username` varchar(100) NOT NULL,
        `token` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `type` enum('premium','comum','all') NOT NULL DEFAULT 'all',
        `game_type` enum('pg_soft','pragmatic','all') NOT NULL DEFAULT 'all',
        `webhook_url` varchar(255) DEFAULT NULL,
        `last_activity` datetime DEFAULT NULL,
        `status` enum('active','inactive') NOT NULL DEFAULT 'active',
        `created_at` datetime DEFAULT current_timestamp(),
        `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Verificar tabela de mapeamentos bot-grupo
$check_group_mappings = $conn->query("SHOW TABLES LIKE 'bot_group_mappings'");
if ($check_group_mappings->num_rows == 0) {
    $conn->query("CREATE TABLE `bot_group_mappings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `bot_id` int(11) NOT NULL,
        `group_id` int(11) NOT NULL,
        `created_at` datetime DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `bot_group_unique` (`bot_id`, `group_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Verificar tabela de mapeamentos bot-canal
$check_channel_mappings = $conn->query("SHOW TABLES LIKE 'bot_channel_mappings'");
if ($check_channel_mappings->num_rows == 0) {
    $conn->query("CREATE TABLE `bot_channel_mappings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `bot_id` int(11) NOT NULL,
        `channel_id` int(11) NOT NULL,
        `created_at` datetime DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `bot_channel_unique` (`bot_id`, `channel_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Verificar tabela de logs de envio
$check_signals_log = $conn->query("SHOW TABLES LIKE 'signal_sending_logs'");
if ($check_signals_log->num_rows == 0) {
    $conn->query("CREATE TABLE `signal_sending_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `bot_id` int(11) NOT NULL,
        `destination_id` int(11) NOT NULL,
        `destination_type` enum('group','channel') NOT NULL,
        `signal_type` varchar(50) DEFAULT NULL,
        `message` text NOT NULL,
        `status` enum('success','failed') NOT NULL,
        `error_message` text DEFAULT NULL,
        `created_at` datetime DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `bot_id` (`bot_id`),
        KEY `destination_id` (`destination_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Função para obter nome do bot pelo ID
function getBotNameById($conn, $bot_id) {
    $stmt = $conn->prepare("SELECT name FROM telegram_bots WHERE id = ?");
    $stmt->bind_param("i", $bot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['name'];
    }
    return 'Bot #' . $bot_id;
}

// Função para enviar mensagem via API do Telegram
function sendTelegramMessage($token, $chat_id, $message) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return json_decode($result, true);
}

// Carregando bot específico se ID fornecido
$bot = null;
$isEditing = false;
$bot_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bot_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM telegram_bots WHERE id = ?");
    $stmt->bind_param("i", $bot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $bot = $result->fetch_assoc();
        $isEditing = true;
    }
}

// Carregar grupos e canais mapeados para este bot
$mapped_groups = [];
$mapped_channels = [];

if ($isEditing) {
    // Buscar grupos mapeados
    $group_stmt = $conn->prepare("SELECT group_id FROM bot_group_mappings WHERE bot_id = ?");
    $group_stmt->bind_param("i", $bot_id);
    $group_stmt->execute();
    $group_result = $group_stmt->get_result();
    
    while ($row = $group_result->fetch_assoc()) {
        $mapped_groups[] = $row['group_id'];
    }
    
    // Buscar canais mapeados
    $channel_stmt = $conn->prepare("SELECT channel_id FROM bot_channel_mappings WHERE bot_id = ?");
    $channel_stmt->bind_param("i", $bot_id);
    $channel_stmt->execute();
    $channel_result = $channel_stmt->get_result();
    
    while ($row = $channel_result->fetch_assoc()) {
        $mapped_channels[] = $row['channel_id'];
    }
}

// Listar todos os grupos disponíveis separados por tipo
$pg_vip_groups = [];
$pg_comum_groups = [];
$pragmatic_vip_groups = [];
$pragmatic_comum_groups = [];

$groups_query = "SELECT * FROM telegram_groups ORDER BY name";
$groups_result = $conn->query($groups_query);

if ($groups_result && $groups_result->num_rows > 0) {
    while ($group = $groups_result->fetch_assoc()) {
        $type = isset($group['type']) ? $group['type'] : 'unknown';
        $level = isset($group['level']) ? $group['level'] : 'comum';
        
        if ($type == 'pg_soft' && $level == 'vip') {
            $pg_vip_groups[] = $group;
        } elseif ($type == 'pg_soft' && $level == 'comum') {
            $pg_comum_groups[] = $group;
        } elseif ($type == 'pragmatic' && $level == 'vip') {
            $pragmatic_vip_groups[] = $group;
        } elseif ($type == 'pragmatic' && $level == 'comum') {
            $pragmatic_comum_groups[] = $group;
        }
    }
}

// Listar todos os canais disponíveis
$channels = [];
$channels_query = "SELECT * FROM telegram_channels ORDER BY name";
$channels_result = $conn->query($channels_query);

if ($channels_result && $channels_result->num_rows > 0) {
    while ($channel = $channels_result->fetch_assoc()) {
        $channels[] = $channel;
    }
}

// Listar logs recentes
$logs = [];
$log_query = "SELECT l.*, b.name as bot_name, 
              CASE WHEN l.destination_type = 'group' THEN g.name ELSE c.name END AS destination_name
              FROM signal_sending_logs l 
              LEFT JOIN telegram_bots b ON l.bot_id = b.id
              LEFT JOIN telegram_groups g ON l.destination_id = g.id AND l.destination_type = 'group'
              LEFT JOIN telegram_channels c ON l.destination_id = c.id AND l.destination_type = 'channel'
              ORDER BY l.created_at DESC LIMIT 50";
$log_result = $conn->query($log_query);

if ($log_result && $log_result->num_rows > 0) {
    while ($log = $log_result->fetch_assoc()) {
        $logs[] = $log;
    }
}

// Obter configurações do site
$siteName = getSetting($conn, 'site_name') ?: 'BotDeSinais';
$siteLogo = getSetting($conn, 'site_logo') ?: 'logo.png';

// Título da página
$pageTitle = $isEditing ? 'Editar Bot: ' . $bot['name'] : 'Adicionar Novo Bot';
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
        
        /* Cards */
        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.25rem;
            border-top-left-radius: 0.75rem !important;
            border-top-right-radius: 0.75rem !important;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Formulários */
        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #d1d3e2;
            font-size: 0.9rem;
            padding: 0.6rem 0.75rem;
        }
        
        .form-control:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #5a5c69;
        }
        
        .form-text {
            color: #858796;
            font-size: 0.8rem;
        }
        
        .form-check-label {
            font-weight: 600;
        }
        
        /* Separador de Seções */
        .section-divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
        }
        
        .section-divider hr {
            flex-grow: 1;
            border-top: 1px solid #e3e6f0;
            margin: 0 1rem;
        }
        
        .section-divider .section-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary-color);
        }
        
        /* Fitas de tipo */
        .ribbon {
            position: absolute;
            top: 1rem;
            right: -0.5rem;
            padding: 0.25rem 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 0.25rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .ribbon-premium {
            background-color: var(--purple-color);
            color: white;
        }
        
        .ribbon-comum {
            background-color: var(--info-color);
            color: white;
        }
        
        .ribbon-pg {
            background-color: #9c27b0;
            color: white;
        }
        
        .ribbon-pragmatic {
            background-color: #2196f3;
            color: white;
        }
        
        /* Botões */
        .btn {
            font-weight: 700;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
        }
        
        .btn-telegram {
            background-color: var(--telegram-color);
            color: white;
        }
        
        .btn-telegram:hover {
            background-color: #0077b5;
            color: white;
        }
        
        /* Grupos e Canais List */
        .groups-selector {
            max-height: 400px;
            overflow-y: auto;
            border-radius: 0.5rem;
            border: 1px solid #e3e6f0;
            padding: 0.5rem;
        }
        
        .groups-section {
            margin-bottom: 1rem;
            border-bottom: 1px solid #e3e6f0;
            padding-bottom: 0.5rem;
        }
        
        .groups-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        
        .group-section-header {
            font-weight: 700;
            padding: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: rgba(0,0,0,0.02);
            border-radius: 0.35rem;
            margin-bottom: 0.5rem;
        }
        
        .group-section-header.pg-vip {
            color: #9c27b0;
            background-color: rgba(156, 39, 176, 0.05);
        }
        
        .group-section-header.pg-comum {
            color: #9c27b0;
            background-color: rgba(156, 39, 176, 0.02);
        }
        
        .group-section-header.pragmatic-vip {
            color: #2196f3;
            background-color: rgba(33, 150, 243, 0.05);
        }
        
        .group-section-header.pragmatic-comum {
            color: #2196f3;
            background-color: rgba(33, 150, 243, 0.02);
        }
        
        .group-item {
            display: flex;
            align-items: center;
            border-radius: 0.35rem;
            margin-bottom: 0.25rem;
            padding: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .group-item:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .group-item .form-check {
            margin-right: 0.5rem;
        }
        
        .group-item .group-name {
            flex-grow: 1;
        }
        
        .group-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            border-radius: 50px;
            margin-left: 0.5rem;
        }
        
        .group-badge.pg-vip {
            background-color: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
        }
        
        .group-badge.pg-comum {
            background-color: rgba(156, 39, 176, 0.05);
            color: #9c27b0;
        }
        
        .group-badge.pragmatic-vip {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .group-badge.pragmatic-comum {
            background-color: rgba(33, 150, 243, 0.05);
            color: #2196f3;
        }
        
        /* Logs */
        .log-table {
            font-size: 0.85rem;
        }
        
        .log-status-success {
            color: var(--success-color);
        }
        
        .log-status-failed {
            color: var(--danger-color);
        }
        
        /* Nav Tabs */
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #5a5c69;
            font-weight: 700;
            padding: 0.75rem 1.5rem;
            border-radius: 0;
        }
        
        .nav-tabs .nav-link.active {
            border-bottom: 3px solid var(--primary-color);
            color: var(--primary-color);
            background-color: transparent;
        }
        
        .nav-tabs .nav-link:hover {
            border-bottom: 3px solid #e3e6f0;
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
                    <a href="index.php" class="active">
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
                <!-- Breadcrumb e Título -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Bots</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo $isEditing ? 'Editar Bot' : 'Adicionar Bot'; ?></li>
                        </ol>
                    </nav>
                    
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['alert_type']); endif; ?>
                
                <!-- Abas da Página -->
                <ul class="nav nav-tabs mb-4" id="botTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info-tab-pane" type="button" role="tab" aria-controls="info-tab-pane" aria-selected="true">
                            <i class="fas fa-info-circle me-1"></i> Informações do Bot
                        </button>
                    </li>
                    <?php if ($isEditing): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="groups-tab" data-bs-toggle="tab" data-bs-target="#groups-tab-pane" type="button" role="tab" aria-controls="groups-tab-pane" aria-selected="false">
                            <i class="fas fa-users me-1"></i> Gerenciar Grupos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="channels-tab" data-bs-toggle="tab" data-bs-target="#channels-tab-pane" type="button" role="tab" aria-controls="channels-tab-pane" aria-selected="false">
                            <i class="fas fa-broadcast-tower me-1"></i> Gerenciar Canais
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="test-tab" data-bs-toggle="tab" data-bs-target="#test-tab-pane" type="button" role="tab" aria-controls="test-tab-pane" aria-selected="false">
                            <i class="fas fa-vial me-1"></i> Testar Bot
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Conteúdo das Abas -->
                <div class="tab-content" id="botTabsContent">
                    <!-- Aba de Informações -->
                    <div class="tab-pane fade show active" id="info-tab-pane" role="tabpanel" aria-labelledby="info-tab" tabindex="0">
                        <div class="card">
                            <div class="card-header d-flex align-items-center">
                                <i class="fas fa-robot me-2" style="color: var(--success-color);"></i>
                                <h6 class="mb-0 fw-bold"><?php echo $isEditing ? 'Editar Bot' : 'Adicionar Novo Bot'; ?></h6>
                            </div>
                            <div class="card-body">
                                <form method="post" action="manage_bots.php">
                                    <input type="hidden" name="action" value="save_bot">
                                    <input type="hidden" name="bot_id" value="<?php echo $isEditing ? $bot['id'] : '0'; ?>">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="bot_name" class="form-label">Nome do Bot*</label>
                                                <input type="text" class="form-control" id="bot_name" name="bot_name" value="<?php echo $isEditing ? htmlspecialchars($bot['name']) : ''; ?>" required>
                                                <div class="form-text">Nome para identificar o bot no sistema</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="bot_username" class="form-label">Username do Bot*</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">@</span>
                                                    <input type="text" class="form-control" id="bot_username" name="bot_username" value="<?php echo $isEditing ? htmlspecialchars($bot['username']) : ''; ?>" required>
                                                </div>
                                                <div class="form-text">Username do bot no Telegram (sem @)</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="bot_token" class="form-label">Token do Bot*</label>
                                        <input type="text" class="form-control" id="bot_token" name="bot_token" value="<?php echo $isEditing ? htmlspecialchars($bot['token']) : ''; ?>" required>
                                        <div class="form-text">Token fornecido pelo BotFather (formato: 123456789:ABCDEFabcdef1234567890)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Descrição</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $isEditing ? htmlspecialchars($bot['description']) : ''; ?></textarea>
                                        <div class="form-text">Informações adicionais sobre este bot (opcional)</div>
                                    </div>
                                    
                                    <div class="section-divider">
                                        <span class="section-title">Configurações de Envio</span>
                                        <hr>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Tipo de Bot</label>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" name="bot_type" id="bot_type_all" value="all" <?php echo (!$isEditing || ($isEditing && $bot['type'] == 'all')) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="bot_type_all">
                                                        Todos os Grupos
                                                    </label>
                                                    <div class="form-text ms-4">Bot enviará sinais para grupos VIP e comuns</div>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" name="bot_type" id="bot_type_premium" value="premium" <?php echo ($isEditing && $bot['type'] == 'premium') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="bot_type_premium">
                                                        <i class="fas fa-crown me-1 text-warning"></i> Apenas Grupos VIP
                                                    </label>
                                                    <div class="form-text ms-4">Bot enviará sinais apenas para grupos VIP</div>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="bot_type" id="bot_type_comum" value="comum" <?php echo ($isEditing && $bot['type'] == 'comum') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="bot_type_comum">
                                                        <i class="fas fa-user-friends me-1 text-info"></i> Apenas Grupos Comuns
                                                    </label>
                                                    <div class="form-text ms-4">Bot enviará sinais apenas para grupos comuns</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Tipo de Jogo</label>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" name="game_type" id="game_type_all" value="all" <?php echo (!$isEditing || ($isEditing && $bot['game_type'] == 'all')) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="game_type_all">
                                                        Todos os Jogos
                                                    </label>
                                                    <div class="form-text ms-4">Bot enviará sinais para ambos os tipos de jogos</div>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" name="game_type" id="game_type_pg_soft" value="pg_soft" <?php echo ($isEditing && $bot['game_type'] == 'pg_soft') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="game_type_pg_soft">
                                                        <i class="fas fa-dice me-1" style="color: #9c27b0;"></i> Apenas PG Soft
                                                    </label>
                                                    <div class="form-text ms-4">Bot enviará apenas sinais de jogos PG Soft</div>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="game_type" id="game_type_pragmatic" value="pragmatic" <?php echo ($isEditing && $bot['game_type'] == 'pragmatic') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="game_type_pragmatic">
                                                        <i class="fas fa-gamepad me-1" style="color: #2196f3;"></i> Apenas Pragmatic
                                                    </label>
                                                    <div class="form-text ms-4">Bot enviará apenas sinais de jogos Pragmatic</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" <?php echo (!$isEditing || ($isEditing && $bot['status'] == 'active')) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="status">Bot Ativo</label>
                                        <div class="form-text">Desative para pausar o envio de sinais por este bot</div>
                                    </div>
                                    
                                    <div class="section-divider">
                                        <hr>
                                    </div>
                                    
                                    <div class="alert alert-info mb-4">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Instruções:</strong>
                                        <ol class="mb-0 mt-2">
                                            <li>Crie um bot através do <a href="https://t.me/BotFather" target="_blank">@BotFather</a> no Telegram</li>
                                            <li>Copie o token fornecido pelo BotFather</li>
                                            <li>Adicione o bot como administrador nos grupos e canais desejados</li>
                                            <li>Configure os grupos e canais que este bot deve acessar</li>
                                        </ol>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> <?php echo $isEditing ? 'Atualizar Bot' : 'Adicionar Bot'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isEditing): ?>
                    <!-- Aba de Gerenciamento de Grupos -->
                    <div class="tab-pane fade" id="groups-tab-pane" role="tabpanel" aria-labelledby="groups-tab" tabindex="0">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-users me-2" style="color: var(--telegram-color);"></i>
                                    <h6 class="mb-0 fw-bold">Gerenciar Grupos</h6>
                                </div>
                                
                                <div>
                                    <span class="badge bg-light text-dark">
                                        <strong><?php echo count($mapped_groups); ?></strong> grupos selecionados
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="post" action="manage_bots.php">
                                    <input type="hidden" name="action" value="update_mappings">
                                    <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                                    
                                    <div class="alert alert-info mb-4">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Importante:</strong> Selecione os grupos para os quais este bot enviará sinais.
                                        <div class="mt-2">
                                            <div class="d-flex">
                                                <div style="width: 20px; height: 20px;" class="bg-success rounded-circle me-2"></div>
                                                <div>Grupo já configurado - O bot já tem acesso</div>
                                            </div>
                                            <div class="d-flex mt-1">
                                                <div style="width: 20px; height: 20px;" class="bg-secondary rounded-circle me-2"></div>
                                                <div>Grupo não configurado - O bot ainda não tem acesso</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="groups-selector">
                                        <!-- PG Soft VIP -->
                                        <?php if (count($pg_vip_groups) > 0): ?>
                                        <div class="groups-section">
                                            <div class="group-section-header pg-vip">
                                                <span><i class="fas fa-crown me-1"></i> PG Soft - Grupos VIP</span>
                                                <span class="badge bg-light text-dark"><?php echo count($pg_vip_groups); ?> grupos</span>
                                            </div>
                                            
                                            <?php foreach ($pg_vip_groups as $group): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="<?php echo $group['id']; ?>" id="group_<?php echo $group['id']; ?>" name="selected_groups[]" <?php echo in_array($group['id'], $mapped_groups) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="group-badge pg-vip">
                                                    <i class="fas fa-dice"></i> PG VIP
                                                </span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- PG Soft Comum -->
                                        <?php if (count($pg_comum_groups) > 0): ?>
                                        <div class="groups-section">
                                            <div class="group-section-header pg-comum">
                                                <span><i class="fas fa-user-friends me-1"></i> PG Soft - Grupos Comuns</span>
                                                <span class="badge bg-light text-dark"><?php echo count($pg_comum_groups); ?> grupos</span>
                                            </div>
                                            
                                            <?php foreach ($pg_comum_groups as $group): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="<?php echo $group['id']; ?>" id="group_<?php echo $group['id']; ?>" name="selected_groups[]" <?php echo in_array($group['id'], $mapped_groups) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="group-badge pg-comum">
                                                    <i class="fas fa-dice"></i> PG Comum
                                                </span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Pragmatic VIP -->
                                        <?php if (count($pragmatic_vip_groups) > 0): ?>
                                        <div class="groups-section">
                                            <div class="group-section-header pragmatic-vip">
                                                <span><i class="fas fa-crown me-1"></i> Pragmatic - Grupos VIP</span>
                                                <span class="badge bg-light text-dark"><?php echo count($pragmatic_vip_groups); ?> grupos</span>
                                            </div>
                                            
                                            <?php foreach ($pragmatic_vip_groups as $group): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="<?php echo $group['id']; ?>" id="group_<?php echo $group['id']; ?>" name="selected_groups[]" <?php echo in_array($group['id'], $mapped_groups) ? 'checked' : ''; ?>>
                                                                                                        <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="group-badge pragmatic-vip">
                                                    <i class="fas fa-gamepad"></i> Prag VIP
                                                </span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Pragmatic Comum -->
                                        <?php if (count($pragmatic_comum_groups) > 0): ?>
                                        <div class="groups-section">
                                            <div class="group-section-header pragmatic-comum">
                                                <span><i class="fas fa-user-friends me-1"></i> Pragmatic - Grupos Comuns</span>
                                                <span class="badge bg-light text-dark"><?php echo count($pragmatic_comum_groups); ?> grupos</span>
                                            </div>
                                            
                                            <?php foreach ($pragmatic_comum_groups as $group): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="<?php echo $group['id']; ?>" id="group_<?php echo $group['id']; ?>" name="selected_groups[]" <?php echo in_array($group['id'], $mapped_groups) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="group-badge pragmatic-comum">
                                                    <i class="fas fa-gamepad"></i> Prag Comum
                                                </span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (count($pg_vip_groups) == 0 && count($pg_comum_groups) == 0 && 
                                                count($pragmatic_vip_groups) == 0 && count($pragmatic_comum_groups) == 0): ?>
                                        <div class="text-center py-4">
                                            <div class="mb-3">
                                                <i class="fas fa-users fa-3x text-secondary"></i>
                                            </div>
                                            <h6>Nenhum grupo cadastrado</h6>
                                            <p class="text-muted">Você precisa adicionar grupos antes de poder mapeá-los ao bot</p>
                                            <a href="../telegram_groups/add.php" class="btn btn-primary mt-2">
                                                <i class="fas fa-plus"></i> Adicionar Grupo
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Salvar Alterações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aba de Gerenciamento de Canais -->
                    <div class="tab-pane fade" id="channels-tab-pane" role="tabpanel" aria-labelledby="channels-tab" tabindex="0">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-broadcast-tower me-2" style="color: var(--telegram-color);"></i>
                                    <h6 class="mb-0 fw-bold">Gerenciar Canais</h6>
                                </div>
                                
                                <div>
                                    <span class="badge bg-light text-dark">
                                        <strong><?php echo count($mapped_channels); ?></strong> canais selecionados
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="post" action="manage_bots.php">
                                    <input type="hidden" name="action" value="update_channel_mappings">
                                    <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                                    
                                    <div class="alert alert-info mb-4">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Importante:</strong> Selecione os canais para os quais este bot enviará sinais.
                                        Certifique-se de que o bot foi adicionado como administrador em cada canal.
                                    </div>
                                    
                                    <div class="groups-selector">
                                        <?php if (count($channels) > 0): ?>
                                            <div class="group-section-header mb-3">
                                                <span><i class="fas fa-broadcast-tower me-1"></i> Canais</span>
                                                <span class="badge bg-light text-dark"><?php echo count($channels); ?> canais</span>
                                            </div>
                                            
                                            <?php foreach ($channels as $channel): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="<?php echo $channel['id']; ?>" id="channel_<?php echo $channel['id']; ?>" name="selected_channels[]" <?php echo in_array($channel['id'], $mapped_channels) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="channel_<?php echo $channel['id']; ?>">
                                                        <?php echo htmlspecialchars($channel['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="badge bg-info text-white">
                                                    <i class="fas fa-broadcast-tower"></i> Canal
                                                </span>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <div class="mb-3">
                                                    <i class="fas fa-broadcast-tower fa-3x text-secondary"></i>
                                                </div>
                                                <h6>Nenhum canal cadastrado</h6>
                                                <p class="text-muted">Você precisa adicionar canais antes de poder mapeá-los ao bot</p>
                                                <a href="../telegram_channels/add.php" class="btn btn-primary mt-2">
                                                    <i class="fas fa-plus"></i> Adicionar Canal
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Salvar Alterações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aba de Testes -->
                    <div class="tab-pane fade" id="test-tab-pane" role="tabpanel" aria-labelledby="test-tab" tabindex="0">
                        <div class="card">
                            <div class="card-header d-flex align-items-center">
                                <i class="fas fa-vial me-2" style="color: var(--purple-color);"></i>
                                <h6 class="mb-0 fw-bold">Testar Envio de Mensagem</h6>
                            </div>
                            <div class="card-body">
                                <form method="post" action="manage_bots.php">
                                    <input type="hidden" name="action" value="test_bot">
                                    <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                                    
                                    <div class="alert alert-warning mb-4">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Atenção:</strong> Esta funcionalidade envia uma mensagem de teste para confirmar que o bot está funcionando corretamente.
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="chat_id" class="form-label">ID do Chat*</label>
                                        <input type="text" class="form-control" id="chat_id" name="chat_id" required>
                                        <div class="form-text">
                                            ID do grupo, canal ou usuário para enviar a mensagem. <br>
                                            Formato para grupos: -1001234567890<br>
                                            Formato para canais: -1001234567890<br>
                                            Formato para usuários: 1234567890
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="test_message" class="form-label">Mensagem de Teste*</label>
                                        <textarea class="form-control" id="test_message" name="test_message" rows="4" required>🤖 *MENSAGEM DE TESTE*

Este é um teste de envio do bot <?php echo htmlspecialchars($bot['name']); ?>.

✅ Se você recebeu esta mensagem, significa que o bot está configurado corretamente!</textarea>
                                        <div class="form-text">Você pode usar formatação HTML básica</div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i> Enviar Mensagem de Teste
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="section-divider mt-4">
                                    <span class="section-title">Histórico de Envios</span>
                                    <hr>
                                </div>
                                
                                <?php if (count($logs) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm log-table">
                                        <thead>
                                            <tr>
                                                <th>Data/Hora</th>
                                                <th>Destino</th>
                                                <th>Tipo</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($logs as $log): 
                                                if ($log['bot_id'] != $bot['id']) continue; // Mostrar apenas logs deste bot
                                            ?>
                                            <tr>
                                                <td>
                                                    <small><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($log['destination_name'] ?? 'Desconhecido'); ?></small>
                                                </td>
                                                <td>
                                                    <small>
                                                    <?php if($log['destination_type'] == 'group'): ?>
                                                        <span class="badge bg-primary">Grupo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">Canal</span>
                                                    <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small>
                                                    <?php if($log['status'] == 'success'): ?>
                                                        <span class="log-status-success"><i class="fas fa-check-circle"></i> Sucesso</span>
                                                    <?php else: ?>
                                                        <span class="log-status-failed"><i class="fas fa-exclamation-circle"></i> Falha</span>
                                                    <?php endif; ?>
                                                    </small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-clipboard-list fa-3x mb-3 text-secondary"></i>
                                    <p class="text-muted">Nenhum registro de envio para este bot</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
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
        
        // Seleção de todos os grupos de um tipo
        var selectAllPGVIP = document.getElementById('select_all_pg_vip');
        var selectAllPGComum = document.getElementById('select_all_pg_comum');
        var selectAllPragmaticVIP = document.getElementById('select_all_pragmatic_vip');
        var selectAllPragmaticComum = document.getElementById('select_all_pragmatic_comum');
        
        function toggleGroupSelection(checkboxes, checked) {
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = checked;
            });
        }
        
        if (selectAllPGVIP) {
            selectAllPGVIP.addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('input[name="selected_groups[]"][data-type="pg_soft"][data-level="vip"]');
                toggleGroupSelection(checkboxes, this.checked);
            });
        }
        
        if (selectAllPGComum) {
            selectAllPGComum.addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('input[name="selected_groups[]"][data-type="pg_soft"][data-level="comum"]');
                toggleGroupSelection(checkboxes, this.checked);
            });
        }
        
        if (selectAllPragmaticVIP) {
            selectAllPragmaticVIP.addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('input[name="selected_groups[]"][data-type="pragmatic"][data-level="vip"]');
                toggleGroupSelection(checkboxes, this.checked);
            });
        }
        
        if (selectAllPragmaticComum) {
            selectAllPragmaticComum.addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('input[name="selected_groups[]"][data-type="pragmatic"][data-level="comum"]');
                toggleGroupSelection(checkboxes, this.checked);
            });
        }
        
        // Manter aba selecionada após postback
        const hash = window.location.hash;
        if (hash) {
            const tab = document.querySelector(`button[data-bs-target="${hash}"]`);
            if (tab) {
                const bsTab = new bootstrap.Tab(tab);
                bsTab.show();
            }
        }
    });
    </script>
</body>
</html>