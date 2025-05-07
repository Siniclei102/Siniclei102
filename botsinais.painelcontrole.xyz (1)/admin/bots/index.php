<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Verificar e criar a tabela telegram_bots se não existir
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

// Processar exclusão de bot
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $bot_id = (int)$_GET['delete'];
    
    // Verificar se o bot existe
    $check_query = "SELECT name FROM telegram_bots WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $bot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $bot_name = $result->fetch_assoc()['name'];
        
        // Verificar se o bot está sendo usado em mapeamentos
        $check_mappings = "SELECT COUNT(*) as count FROM bot_group_mappings WHERE bot_id = ?";
        $stmt = $conn->prepare($check_mappings);
        $stmt->bind_param("i", $bot_id);
        $stmt->execute();
        $mapping_count = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($mapping_count > 0) {
            $_SESSION['message'] = "Não é possível excluir o bot '{$bot_name}' pois ele está associado a {$mapping_count} grupos.";
            $_SESSION['alert_type'] = "warning";
        } else {
            // Excluir bot
            $delete_query = "DELETE FROM telegram_bots WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $bot_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Bot '{$bot_name}' excluído com sucesso!";
                $_SESSION['alert_type'] = "success";
                
                // Log da ação
                logAdminAction($conn, $_SESSION['user_id'], "Admin excluiu o bot '{$bot_name}'");
            } else {
                $_SESSION['message'] = "Erro ao excluir bot: " . $stmt->error;
                $_SESSION['alert_type'] = "danger";
            }
        }
    } else {
        $_SESSION['message'] = "Bot não encontrado.";
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirecionar para evitar reenvio do formulário
    header('Location: index.php');
    exit;
}

// Processar alteração de status
if (isset($_GET['toggle_status']) && $_GET['toggle_status'] > 0) {
    $bot_id = (int)$_GET['toggle_status'];
    
    // Verificar status atual
    $check_query = "SELECT name, status FROM telegram_bots WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $bot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $bot = $result->fetch_assoc();
        $new_status = ($bot['status'] == 'active') ? 'inactive' : 'active';
        
        // Atualizar status
        $update_query = "UPDATE telegram_bots SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $bot_id);
        
        if ($stmt->execute()) {
            $status_text = ($new_status == 'active') ? 'ativado' : 'desativado';
            $_SESSION['message'] = "Bot '{$bot['name']}' {$status_text} com sucesso!";
            $_SESSION['alert_type'] = "success";
            
            // Log da ação
            logAdminAction($conn, $_SESSION['user_id'], "Admin {$status_text} o bot '{$bot['name']}'");
        } else {
            $_SESSION['message'] = "Erro ao alterar status do bot: " . $stmt->error;
            $_SESSION['alert_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Bot não encontrado.";
        $_SESSION['alert_type'] = "danger";
    }
    
    // Redirecionar para evitar reenvio do formulário
    header('Location: index.php');
    exit;
}

// Processar adição/edição de bot
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_bot' || $_POST['action'] == 'edit_bot') {
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
        
        // Redirecionar para evitar reenvio do formulário
        header('Location: index.php');
        exit;
    }
}

// Buscar todos os bots
$bots = [];
$search_query = '';

// Processar busca
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $sql = "SELECT * FROM telegram_bots WHERE name LIKE ? OR username LIKE ? ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    $search_param = "%$search_query%";
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM telegram_bots ORDER BY name ASC";
    $result = $conn->query($sql);
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bots[] = $row;
    }
}

// Contar bots por tipo e status
$stats = [
    'total' => count($bots),
    'active' => 0,
    'inactive' => 0,
    'all' => 0,
    'premium' => 0,
    'comum' => 0,
    'pg_soft' => 0,
    'pragmatic' => 0,
    'game_all' => 0
];

foreach ($bots as $bot) {
    if ($bot['status'] == 'active') {
        $stats['active']++;
    } else {
        $stats['inactive']++;
    }
    
    $stats[$bot['type']]++;
    
    if ($bot['game_type'] == 'all') {
        $stats['game_all']++;
    } else {
        $stats[$bot['game_type']]++;
    }
}

// Inicializar variável para edição
$isEditing = false;
$bot_data = null;

// Verificar se estamos editando
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM telegram_bots WHERE id = ?";
    $stmt = $conn->prepare($edit_query);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $isEditing = true;
        $bot_data = $result->fetch_assoc();
    }
}

// Definir título da página
$pageTitle = 'Gerenciar Bots';

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
        
        /* Filtros e Contagem */
        .filters-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .filters-nav {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .filter-item {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background-color: white;
            border: 1px solid #e3e6f0;
            border-radius: 50px;
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
            transition: all 0.2s;
        }
        
        .filter-item:hover {
            background-color: #f8f9fc;
            text-decoration: none;
        }
        
        .filter-item.active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .filter-item i {
            margin-right: 0.4rem;
        }
        
        .filter-item .badge {
            margin-left: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        /* Search Bar */
        .search-container {
            margin-bottom: 1.5rem;
        }
        
        .search-form {
            display: flex;
        }
        
        .search-input {
            flex-grow: 1;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .search-btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        /* Bots Grid */
        .bots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .bot-card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.2s;
            position: relative;
        }
        
        .bot-card:hover {
            transform: translateY(-5px);
        }
        
        .bot-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .bot-name {
            font-weight: 700;
            margin: 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .bot-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--telegram-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .bot-body {
            padding: 1rem;
            flex-grow: 1;
        }
        
        .bot-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .bot-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background-color: #f8f9fc;
            border-top: 1px solid #e3e6f0;
        }
        
        .bot-type-badge {
            position: absolute;
            top: 0;
            right: 0;
            padding: 0.35rem 0.75rem;
            font-weight: 700;
            font-size: 0.75rem;
            border-bottom-left-radius: 0.75rem;
        }
        
        .bot-type-badge.all {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .bot-type-badge.premium {
            background-color: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }
        
        .bot-type-badge.comum {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        .bot-game-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }
        
        .bot-game-badge.all {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .bot-game-badge.pg_soft {
            background-color: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
        }
        
        .bot-game-badge.pragmatic {
            background-color: rgba(255, 87, 34, 0.1);
            color: #ff5722;
        }
        
        .bot-inactive {
            position: absolute;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
            z-index: 5;
        }
        
        /* Botões de ação */
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
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
            
            .bots-grid {
                grid-template-columns: 1fr;
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
                    <a href="../bots/" class="active">
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
                    <a href="../signals/dashboard.php">
                        <i class="fas fa-signal" style="color: var(--purple-color);"></i>
                        <span>Dashboard de Sinais</span>
                    </a>
                </li>
                <li>
                    <a href="../signals/manage_games.php">
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
                    <h1 class="h3 mb-0 text-gray-800">Gerenciar Bots</h1>
                    
                    <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#botModal">
                            <i class="fas fa-plus-circle me-1"></i> Adicionar Bot
                        </button>
                    </div>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Barra de pesquisa -->
                <div class="search-container">
                    <form action="index.php" method="GET" class="search-form">
                        <input type="text" name="search" class="form-control search-input" placeholder="Pesquisar bots..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn btn-primary search-btn"><i class="fas fa-search"></i> Buscar</button>
                    </form>
                </div>
                
                <!-- Filtros -->
                <div class="filters-container mb-4">
                    <div class="filters-nav">
                        <a href="#" class="filter-item filter-all active">
                            <i class="fas fa-layer-group"></i> Todos
                            <span class="badge bg-secondary"><?php echo $stats['total']; ?></span>
                        </a>
                        <a href="#" class="filter-item filter-status" data-status="active">
                            <i class="fas fa-check-circle" style="color: #1cc88a;"></i> Ativos
                            <span class="badge bg-secondary"><?php echo $stats['active']; ?></span>
                        </a>
                        <a href="#" class="filter-item filter-status" data-status="inactive">
                            <i class="fas fa-times-circle" style="color: #e74a3b;"></i> Inativos
                            <span class="badge bg-secondary"><?php echo $stats['inactive']; ?></span>
                        </a>
                        <a href="#" class="filter-item filter-type" data-type="all">
                            <i class="fas fa-users" style="color: #2196f3;"></i> Todos Grupos
                            <span class="badge bg-secondary"><?php echo $stats['all']; ?></span>
                        </a>
                        <a href="#" class="filter-item filter-type" data-type="premium">
                            <i class="fas fa-crown" style="color: #f1c40f;"></i> VIP
                            <span class="badge bg-secondary"><?php echo $stats['premium']; ?></span>
                        </a>
                        <a href="#" class="filter-item filter-type" data-type="comum">
                            <i class="fas fa-user-friends" style="color: #2ecc71;"></i> Comuns
                            <span class="badge bg-secondary"><?php echo $stats['comum']; ?></span>
                        </a>
                    </div>
                </div>
                
                <!-- Grid de Bots -->
                <div class="bots-grid">
                    <?php foreach ($bots as $bot): ?>
                    <div class="bot-card" data-status="<?php echo $bot['status']; ?>" data-type="<?php echo $bot['type']; ?>" data-game-type="<?php echo $bot['game_type']; ?>">
                        <?php if ($bot['status'] == 'inactive'): ?>
                        <div class="bot-inactive">
                            <span>INATIVO</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="bot-header">
                            <h5 class="bot-name">
                                <div class="bot-avatar">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <?php echo htmlspecialchars($bot['name']); ?>
                            </h5>
                        </div>
                        
                        <div class="bot-type-badge <?php echo $bot['type']; ?>">
                            <?php if ($bot['type'] == 'all'): ?>
                                <i class="fas fa-users"></i> Todos Grupos
                            <?php elseif ($bot['type'] == 'premium'): ?>
                                <i class="fas fa-crown"></i> VIP
                            <?php else: ?>
                                <i class="fas fa-user-friends"></i> Comuns
                            <?php endif; ?>
                        </div>
                        
                        <div class="bot-body">
                            <div class="bot-meta">
                                <div class="d-flex align-items-center">
                                    <span class="text-secondary"><i class="fab fa-telegram me-2" style="color: var(--telegram-color);"></i> Username:</span>
                                    <span class="ms-auto">@<?php echo htmlspecialchars($bot['username']); ?></span>
                                </div>
                                
                                <div class="d-flex align-items-center">
                                    <span class="text-secondary"><i class="fas fa-calendar-alt me-2"></i> Adicionado em:</span>
                                    <span class="ms-auto"><?php echo date('d/m/Y', strtotime($bot['created_at'])); ?></span>
                                </div>
                                
                                <div class="d-flex align-items-center">
                                    <span class="text-secondary"><i class="fas fa-gamepad me-2"></i> Tipos de Jogo:</span>
                                    <span class="ms-auto">
                                        <?php if ($bot['game_type'] == 'all'): ?>
                                            <span class="bot-game-badge all"><i class="fas fa-gamepad me-1"></i> Todos</span>
                                        <?php elseif ($bot['game_type'] == 'pg_soft'): ?>
                                            <span class="bot-game-badge pg_soft"><i class="fas fa-dice me-1"></i> PG Soft</span>
                                        <?php else: ?>
                                            <span class="bot-game-badge pragmatic"><i class="fas fa-gamepad me-1"></i> Pragmatic</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($bot['description'])): ?>
                                <div class="mt-2">
                                    <small class="text-muted"><?php echo htmlspecialchars($bot['description']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="bot-footer">
                            <a href="map.php?bot_id=<?php echo $bot['id']; ?>" class="btn btn-outline-primary btn-sm" title="Mapear Grupos">
                                <i class="fas fa-link me-1"></i> Mapear Grupos
                            </a>
                            
                            <a href="index.php?edit=<?php echo $bot['id']; ?>" class="btn btn-info btn-action" title="Editar Bot">
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <a href="index.php?toggle_status=<?php echo $bot['id']; ?>" class="btn <?php echo $bot['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?> btn-action" 
                               title="<?php echo $bot['status'] == 'active' ? 'Desativar' : 'Ativar'; ?>" 
                               onclick="return confirm('Tem certeza que deseja <?php echo $bot['status'] == 'active' ? 'desativar' : 'ativar'; ?> este bot?')">
                                <i class="fas <?php echo $bot['status'] == 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                            </a>
                            
                            <a href="index.php?delete=<?php echo $bot['id']; ?>" class="btn btn-danger btn-action" 
                               title="Excluir Bot" 
                               onclick="return confirm('Tem certeza que deseja excluir este bot? Esta ação não pode ser desfeita.')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($bots) === 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-robot fa-3x mb-3 text-secondary"></i>
                    <h4>Nenhum bot encontrado</h4>
                    <p class="text-muted">
                        <?php if (!empty($search_query)): ?>
                            Nenhum bot encontrado para a pesquisa "<?php echo htmlspecialchars($search_query); ?>".
                        <?php else: ?>
                            Clique em "Adicionar Bot" para começar.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
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
                    <form method="post" action="index.php" id="botForm">
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
                                <li>Mapeie os grupos que este bot deve acessar após salvá-lo</li>
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
        
        // Filtros para os bots
        const filterAllItem = document.querySelector('.filter-all');
        const filterStatusItems = document.querySelectorAll('.filter-status');
        const filterTypeItems = document.querySelectorAll('.filter-type');
        const botCards = document.querySelectorAll('.bot-card');
        
        // Função para filtrar os bots
        function filterBots(filterType, filterValue) {
            botCards.forEach(card => {
                if (filterType === 'all') {
                    card.style.display = 'flex';
                } else if (filterType === 'status') {
                    if (card.dataset.status === filterValue) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                } else if (filterType === 'type') {
                    if (card.dataset.type === filterValue) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        }
        
        // Event listeners para os filtros
        if (filterAllItem) {
            filterAllItem.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remover classe ativa de todos os filtros
                document.querySelectorAll('.filter-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Adicionar classe ativa ao filtro "Todos"
                this.classList.add('active');
                
                // Mostrar todos os bots
                filterBots('all');
            });
        }
        
        filterStatusItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remover classe ativa de todos os filtros
                document.querySelectorAll('.filter-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Adicionar classe ativa ao filtro clicado
                this.classList.add('active');
                
                // Filtrar bots por status
                filterBots('status', this.dataset.status);
            });
        });
        
        filterTypeItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remover classe ativa de todos os filtros
                document.querySelectorAll('.filter-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Adicionar classe ativa ao filtro clicado
                this.classList.add('active');
                
                // Filtrar bots por tipo
                filterBots('type', this.dataset.type);
            });
        });
        
        // Abrir modal se estamos editando
        const botModal = new bootstrap.Modal(document.getElementById('botModal'));
        <?php if ($isEditing): ?>
        botModal.show();
        <?php endif; ?>
    });
    </script>
</body>
</html>