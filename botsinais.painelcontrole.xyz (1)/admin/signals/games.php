<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Criar tabela se não existir
$check_table = $conn->query("SHOW TABLES LIKE 'games'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE `games` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `type` enum('pg_soft','pragmatic') NOT NULL,
        `image` varchar(255) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `status` enum('active','inactive') NOT NULL DEFAULT 'active',
        `created_at` datetime DEFAULT current_timestamp(),
        `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `type` (`type`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Inserir jogos padrão
    $default_pg_games = [
        "Fortune Tiger", "Fortune Ox", "Fortune Mouse", 
        "Ganesha Fortune", "Dragon Hatch", "Lucky Neko",
        "Mahjong Ways", "Mahjong Ways 2", "Wild Bandito",
        "Queen of Bounty"
    ];
    
    $default_pragmatic_games = [
        "Sweet Bonanza", "Gates of Olympus", "Starlight Princess", 
        "Wild West Gold", "Big Bass Bonanza", "Aztec Gems",
        "Wolf Gold", "The Dog House", "Great Rhino",
        "Fruit Party"
    ];
    
    $stmt = $conn->prepare("INSERT INTO games (name, type, status) VALUES (?, ?, 'active')");
    $stmt->bind_param("ss", $name, $type);
    
    $type = 'pg_soft';
    foreach ($default_pg_games as $name) {
        $stmt->execute();
    }
    
    $type = 'pragmatic';
    foreach ($default_pragmatic_games as $name) {
        $stmt->execute();
    }
    
    $_SESSION['message'] = "Tabela de jogos criada e jogos padrão adicionados com sucesso!";
    $_SESSION['alert_type'] = "success";
}

// Processar ações de formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Adicionar novo jogo
    if (isset($_POST['action']) && $_POST['action'] == 'add_game') {
        $name = trim($_POST['game_name']);
        $type = $_POST['game_type'];
        $description = trim($_POST['description'] ?? '');
        $status = isset($_POST['status']) ? 'active' : 'inactive';
        
        // Validar entrada
        if (empty($name)) {
            $_SESSION['message'] = "O nome do jogo é obrigatório!";
            $_SESSION['alert_type'] = "danger";
        } else {
            $stmt = $conn->prepare("INSERT INTO games (name, type, description, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $type, $description, $status);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Jogo adicionado com sucesso!";
                $_SESSION['alert_type'] = "success";
                
                // Log da ação
                logAdminAction($conn, $_SESSION['user_id'], "Admin adicionou o jogo '{$name}'");
            } else {
                $_SESSION['message'] = "Erro ao adicionar jogo: " . $stmt->error;
                $_SESSION['alert_type'] = "danger";
            }
        }
    }
    
    // Atualizar jogo
    if (isset($_POST['action']) && $_POST['action'] == 'edit_game') {
        $game_id = (int)$_POST['game_id'];
        $name = trim($_POST['game_name']);
        $type = $_POST['game_type'];
        $description = trim($_POST['description'] ?? '');
        $status = isset($_POST['status']) ? 'active' : 'inactive';
        
        // Validar entrada
        if (empty($name)) {
            $_SESSION['message'] = "O nome do jogo é obrigatório!";
            $_SESSION['alert_type'] = "danger";
        } else {
            // Garantir que updated_at é atualizado automaticamente
            $stmt = $conn->prepare("UPDATE games SET name = ?, type = ?, description = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $type, $description, $status, $game_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Jogo atualizado com sucesso!";
                $_SESSION['alert_type'] = "success";
                
                // Log da ação
                logAdminAction($conn, $_SESSION['user_id'], "Admin atualizou o jogo '{$name}'");
            } else {
                $_SESSION['message'] = "Erro ao atualizar jogo: " . $stmt->error;
                $_SESSION['alert_type'] = "danger";
            }
        }
    }
    
    // Excluir jogo
    if (isset($_POST['action']) && $_POST['action'] == 'delete_game') {
        $game_id = (int)$_POST['game_id'];
        
        // Verificar se o jogo está sendo usado em sinais
        $check_query = "SELECT COUNT(*) as count FROM signal_generation_logs WHERE game_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            $_SESSION['message'] = "Este jogo não pode ser excluído pois está sendo usado em {$count} sinais gerados.";
            $_SESSION['alert_type'] = "warning";
        } else {
            $game_name = '';
            $get_name = $conn->prepare("SELECT name FROM games WHERE id = ?");
            $get_name->bind_param("i", $game_id);
            $get_name->execute();
            $name_result = $get_name->get_result();
            if ($name_result->num_rows > 0) {
                $game_name = $name_result->fetch_assoc()['name'];
            }
            
            $stmt = $conn->prepare("DELETE FROM games WHERE id = ?");
            $stmt->bind_param("i", $game_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Jogo excluído com sucesso!";
                $_SESSION['alert_type'] = "success";
                
                // Log da ação
                logAdminAction($conn, $_SESSION['user_id'], "Admin excluiu o jogo '{$game_name}'");
            } else {
                $_SESSION['message'] = "Erro ao excluir jogo: " . $stmt->error;
                $_SESSION['alert_type'] = "danger";
            }
        }
    }
    
    // Alternar status
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_status') {
        $game_id = (int)$_POST['game_id'];
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';
        
        $game_name = '';
        $get_name = $conn->prepare("SELECT name FROM games WHERE id = ?");
        $get_name->bind_param("i", $game_id);
        $get_name->execute();
        $name_result = $get_name->get_result();
        if ($name_result->num_rows > 0) {
            $game_name = $name_result->fetch_assoc()['name'];
        }
        
        $stmt = $conn->prepare("UPDATE games SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $game_id);
        
        if ($stmt->execute()) {
            $action_text = ($new_status == 'active') ? "ativou" : "desativou";
            $_SESSION['message'] = "Status do jogo alterado com sucesso!";
            $_SESSION['alert_type'] = "success";
            
            // Log da ação
            logAdminAction($conn, $_SESSION['user_id'], "Admin {$action_text} o jogo '{$game_name}'");
        } else {
            $_SESSION['message'] = "Erro ao alterar status do jogo: " . $stmt->error;
            $_SESSION['alert_type'] = "danger";
        }
    }

    // Atualizar todos os jogos de um tipo
    if (isset($_POST['action']) && $_POST['action'] == 'update_type') {
        $old_type = $_POST['old_type'];
        $new_type = $_POST['new_type'];
        
        $stmt = $conn->prepare("UPDATE games SET type = ? WHERE type = ?");
        $stmt->bind_param("ss", $new_type, $old_type);
        
        if ($stmt->execute()) {
            $affected = $conn->affected_rows;
            $_SESSION['message'] = "{$affected} jogos atualizados de '{$old_type}' para '{$new_type}' com sucesso!";
            $_SESSION['alert_type'] = "success";
            
            // Log da ação
            logAdminAction($conn, $_SESSION['user_id'], "Admin alterou o tipo de {$affected} jogos de '{$old_type}' para '{$new_type}'");
        } else {
            $_SESSION['message'] = "Erro ao atualizar tipo dos jogos: " . $stmt->error;
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    // Redirecionar para evitar reenvio do formulário
    header('Location: games.php');
    exit;
}

// Busca de jogos
$search_query = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// Filtro de tipo
$type_filter = '';
if (isset($_GET['type']) && in_array($_GET['type'], ['pg_soft', 'pragmatic'])) {
    $type_filter = $_GET['type'];
}

// Buscar todos os jogos com filtros
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_conditions[] = "name LIKE ?";
    $params[] = "%{$search_query}%";
    $param_types .= 's';
}

if (!empty($type_filter)) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$games_query = "SELECT * FROM games {$where_clause} ORDER BY type ASC, name ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($games_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $games_result = $stmt->get_result();
} else {
    $games_result = $conn->query($games_query);
}

$games = [];

if ($games_result && $games_result->num_rows > 0) {
    while ($row = $games_result->fetch_assoc()) {
        $games[] = $row;
    }
}

// Contar jogos por tipo
$pg_count = 0;
$pragmatic_count = 0;
$active_count = 0;
$inactive_count = 0;

foreach ($games as $game) {
    if ($game['type'] == 'pg_soft') $pg_count++;
    if ($game['type'] == 'pragmatic') $pragmatic_count++;
    if ($game['status'] == 'active') $active_count++;
    if ($game['status'] == 'inactive') $inactive_count++;
}

// Definir título da página
$pageTitle = 'Gerenciar Jogos';

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
        
        /* Games Grid */
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .game-card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.2s;
            position: relative;
        }
        
        .game-card:hover {
            transform: translateY(-5px);
        }
        
        .game-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .game-name {
            font-weight: 700;
            margin: 0;
            font-size: 1.1rem;
        }
        
        .game-body {
            padding: 1rem;
            flex-grow: 1;
        }
        
        .game-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .game-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background-color: #f8f9fc;
            border-top: 1px solid #e3e6f0;
        }
        
        .game-type-badge {
            position: absolute;
            top: 0;
            right: 0;
            padding: 0.35rem 0.75rem;
            font-weight: 700;
            font-size: 0.75rem;
            border-bottom-left-radius: 0.75rem;
        }
        
        .game-type-badge.pg {
            background-color: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
        }
        
        .game-type-badge.pragmatic {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .game-inactive {
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
            
            .games-grid {
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
                    <a href="dashboard.php">
                        <i class="fas fa-signal" style="color: var(--purple-color);"></i>
                        <span>Dashboard de Sinais</span>
                    </a>
                </li>
                <li>
                    <a href="games.php" class="active">
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
                    <h1 class="h3 mb-0 text-gray-800">Gerenciar Jogos</h1>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#fixGameTypesModal">
                            <i class="fas fa-wrench me-1"></i> Corrigir Tipos
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGameModal">
                            <i class="fas fa-plus-circle me-1"></i> Adicionar Jogo
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
                    <form action="games.php" method="GET" class="search-form">
                        <input type="text" name="search" class="form-control search-input" placeholder="Pesquisar jogos..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn btn-primary search-btn"><i class="fas fa-search"></i> Buscar</button>
                    </form>
                </div>
                
                <!-- Filtros -->
                <div class="filters-container mb-4">
                    <div class="filters-nav">
                        <a href="games.php" class="filter-item <?php echo (!isset($_GET['type'])) ? 'active' : ''; ?>">
                            <i class="fas fa-layer-group"></i> Todos
                            <span class="badge bg-secondary"><?php echo count($games); ?></span>
                        </a>
                        <a href="games.php?type=pg_soft" class="filter-item <?php echo (isset($_GET['type']) && $_GET['type'] == 'pg_soft') ? 'active' : ''; ?>">
                            <i class="fas fa-dice" style="color: <?php echo (isset($_GET['type']) && $_GET['type'] == 'pg_soft') ? 'white' : '#9c27b0'; ?>"></i> PG Soft
                            <span class="badge bg-secondary"><?php echo $pg_count; ?></span>
                        </a>
                        <a href="games.php?type=pragmatic" class="filter-item <?php echo (isset($_GET['type']) && $_GET['type'] == 'pragmatic') ? 'active' : ''; ?>">
                            <i class="fas fa-gamepad" style="color: <?php echo (isset($_GET['type']) && $_GET['type'] == 'pragmatic') ? 'white' : '#2196f3'; ?>"></i> Pragmatic
                            <span class="badge bg-secondary"><?php echo $pragmatic_count; ?></span>
                        </a>
                        <a href="#" class="filter-item filter-status" data-status="active">
                            <i class="fas fa-check-circle" style="color: #1cc88a;"></i> Ativos
                            <span class="badge bg-secondary"><?php echo $active_count; ?></span>
                        </a>
                        <a href="#" class="filter-item filter-status" data-status="inactive">
                            <i class="fas fa-times-circle" style="color: #e74a3b;"></i> Inativos
                            <span class="badge bg-secondary"><?php echo $inactive_count; ?></span>
                        </a>
                    </div>
                </div>
                
                <!-- Grid de Jogos -->
                <div class="games-grid">
                    <?php foreach ($games as $game): ?>
                    <div class="game-card" data-type="<?php echo $game['type']; ?>" data-status="<?php echo $game['status']; ?>">
                        <?php if ($game['status'] == 'inactive'): ?>
                        <div class="game-inactive">
                            <span>INATIVO</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="game-header">
                            <h5 class="game-name"><?php echo htmlspecialchars($game['name']); ?></h5>
                        </div>
                        
                        <div class="game-type-badge <?php echo $game['type'] == 'pg_soft' ? 'pg' : 'pragmatic'; ?>">
                            <i class="<?php echo $game['type'] == 'pg_soft' ? 'fas fa-dice' : 'fas fa-gamepad'; ?>"></i>
                            <?php echo $game['type'] == 'pg_soft' ? 'PG Soft' : 'Pragmatic'; ?>
                        </div>
                        
                        <div class="game-body">
                            <div class="game-meta">
                                <div class="d-flex align-items-center">
                                    <span class="text-secondary"><i class="fas fa-calendar-alt me-2"></i> Adicionado em:</span>
                                    <span class="ms-auto"><?php echo date('d/m/Y', strtotime($game['created_at'])); ?></span>
                                </div>
                                
                                <div class="d-flex align-items-center">
                                    <span class="text-secondary"><i class="fas fa-clock me-2"></i> Atualizado em:</span>
                                    <span class="ms-auto">
                                        <?php echo isset($game['updated_at']) && $game['updated_at'] ? date('d/m/Y', strtotime($game['updated_at'])) : 'Nunca'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!empty($game['description'])): ?>
                            <div class="mt-3">
                                <p class="mb-0"><small><?php echo nl2br(htmlspecialchars($game['description'])); ?></small></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="game-footer">
                            <button type="button" class="btn btn-info btn-action" data-bs-toggle="modal" data-bs-target="#editGameModal"
                                data-game-id="<?php echo $game['id']; ?>"
                                data-game-name="<?php echo htmlspecialchars($game['name']); ?>"
                                data-game-type="<?php echo $game['type']; ?>"
                                data-game-desc="<?php echo htmlspecialchars($game['description'] ?? ''); ?>"
                                data-game-status="<?php echo $game['status']; ?>"
                                title="Editar Jogo">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <form method="post" action="games.php" class="d-inline" onsubmit="return confirm('Tem certeza que deseja alterar o status deste jogo?')">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $game['status']; ?>">
                                <button type="submit" class="btn <?php echo $game['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?> btn-action" title="<?php echo $game['status'] == 'active' ? 'Desativar' : 'Ativar'; ?>">
                                    <i class="fas <?php echo $game['status'] == 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                </button>
                            </form>
                            
                            <form method="post" action="games.php" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este jogo? Esta ação não pode ser desfeita.')">
                                <input type="hidden" name="action" value="delete_game">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-action" title="Excluir Jogo">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($games) === 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-dice fa-3x mb-3 text-secondary"></i>
                    <h4>Nenhum jogo encontrado</h4>
                    <p class="text-muted">
                        <?php if (!empty($search_query)): ?>
                            Nenhum jogo encontrado para a pesquisa "<?php echo htmlspecialchars($search_query); ?>".
                        <?php else: ?>
                            Clique em "Adicionar Novo Jogo" para começar.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal para Adicionar Jogo -->
    <div class="modal fade" id="addGameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Novo Jogo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="games.php" id="addGameForm">
                        <input type="hidden" name="action" value="add_game">
                        
                        <div class="mb-3">
                            <label for="game_name" class="form-label">Nome do Jogo*</label>
                            <input type="text" class="form-control" id="game_name" name="game_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Jogo*</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="game_type" id="type_pg_soft" value="pg_soft" checked>
                                    <label class="form-check-label" for="type_pg_soft">
                                        <i class="fas fa-dice me-1" style="color: #9c27b0;"></i> PG Soft
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="game_type" id="type_pragmatic" value="pragmatic">
                                    <label class="form-check-label" for="type_pragmatic">
                                        <i class="fas fa-gamepad me-1" style="color: #2196f3;"></i> Pragmatic
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição (opcional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="status" name="status" checked>
                            <label class="form-check-label" for="status">
                                Ativo
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="addGameForm" class="btn btn-primary">Adicionar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para Editar Jogo -->
    <div class="modal fade" id="editGameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Jogo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="games.php" id="editGameForm">
                        <input type="hidden" name="action" value="edit_game">
                        <input type="hidden" name="game_id" id="edit_game_id">
                        
                        <div class="mb-3">
                            <label for="edit_game_name" class="form-label">Nome do Jogo*</label>
                            <input type="text" class="form-control" id="edit_game_name" name="game_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Jogo*</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="game_type" id="edit_type_pg_soft" value="pg_soft">
                                    <label class="form-check-label" for="edit_type_pg_soft">
                                        <i class="fas fa-dice me-1" style="color: #9c27b0;"></i> PG Soft
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="game_type" id="edit_type_pragmatic" value="pragmatic">
                                    <label class="form-check-label" for="edit_type_pragmatic">
                                        <i class="fas fa-gamepad me-1" style="color: #2196f3;"></i> Pragmatic
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Descrição (opcional)</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="edit_status" name="status">
                            <label class="form-check-label" for="edit_status">
                                Ativo
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="editGameForm" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Corrigir Tipos de Jogos -->
    <div class="modal fade" id="fixGameTypesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Corrigir Tipos de Jogos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Esta função permite corrigir os tipos de jogos em massa.</p>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Dica:</strong> Use esta função quando houver jogos com tipos incorretos. Por exemplo, jogos PG Soft classificados como Pragmatic ou vice-versa.
                    </div>
                    
                    <form method="post" action="games.php" id="updateTypeForm">
                        <input type="hidden" name="action" value="update_type">
                        
                        <div class="mb-3">
                            <label for="old_type" class="form-label">De (tipo atual)</label>
                            <select class="form-select" id="old_type" name="old_type" required>
                                <option value="pg_soft">PG Soft</option>
                                <option value="pragmatic">Pragmatic</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_type" class="form-label">Para (novo tipo)</label>
                            <select class="form-select" id="new_type" name="new_type" required>
                                <option value="pg_soft">PG Soft</option>
                                <option value="pragmatic" selected>Pragmatic</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Atenção:</strong> Esta ação atualizará TODOS os jogos do tipo selecionado para o novo tipo. Confirme antes de prosseguir.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="updateTypeForm" class="btn btn-warning">
                        <i class="fas fa-sync-alt me-1"></i> Atualizar Tipos
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
        
        // Filtros de status (active/inactive)
        const statusFilters = document.querySelectorAll('.filter-status');
        const gameCards = document.querySelectorAll('.game-card');
        
        statusFilters.forEach(filter => {
            filter.addEventListener('click', function(e) {
                e.preventDefault();
                
                const status = this.getAttribute('data-status');
                
                // Remove active class from all filters
                statusFilters.forEach(f => f.classList.remove('active'));
                
                // Add active class to clicked filter
                this.classList.add('active');
                
                // Filter game cards
                gameCards.forEach(card => {
                    const cardStatus = card.getAttribute('data-status');
                    
                    if (status === 'all' || cardStatus === status) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
        
        // Modal de edição
        const editGameModal = document.getElementById('editGameModal');
        if (editGameModal) {
            editGameModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const gameId = button.getAttribute('data-game-id');
                const gameName = button.getAttribute('data-game-name');
                const gameType = button.getAttribute('data-game-type');
                const gameDesc = button.getAttribute('data-game-desc');
                const gameStatus = button.getAttribute('data-game-status');
                
                // Preencher o formulário
                document.getElementById('edit_game_id').value = gameId;
                document.getElementById('edit_game_name').value = gameName;
                
                if (gameType === 'pg_soft') {
                    document.getElementById('edit_type_pg_soft').checked = true;
                } else {
                    document.getElementById('edit_type_pragmatic').checked = true;
                }
                
                document.getElementById('edit_description').value = gameDesc;
                document.getElementById('edit_status').checked = (gameStatus === 'active');
            });
        }

        // Config para modal de corrigir tipos
        const fixTypesModal = document.getElementById('fixGameTypesModal');
        if (fixTypesModal) {
            const oldTypeSelect = document.getElementById('old_type');
            const newTypeSelect = document.getElementById('new_type');
            
            oldTypeSelect.addEventListener('change', function() {
                if (this.value === 'pg_soft') {
                    newTypeSelect.value = 'pragmatic';
                } else {
                    newTypeSelect.value = 'pg_soft';
                }
            });
        }
    });
    </script>
</body>
</html>