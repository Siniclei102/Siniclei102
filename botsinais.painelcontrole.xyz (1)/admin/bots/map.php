<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Verificar se foi fornecido um bot_id
if (!isset($_GET['bot_id']) || empty($_GET['bot_id'])) {
    $_SESSION['message'] = "ID do bot não fornecido.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

$bot_id = (int)$_GET['bot_id'];

// Verificar se o bot existe
$bot_query = "SELECT * FROM telegram_bots WHERE id = ?";
$stmt = $conn->prepare($bot_query);
$stmt->bind_param("i", $bot_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['message'] = "Bot não encontrado.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

$bot = $result->fetch_assoc();

// Verificar e criar tabela bot_group_mappings se não existir
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

// Verificar se a tabela telegram_groups existe
$check_groups_table = $conn->query("SHOW TABLES LIKE 'telegram_groups'");
if ($check_groups_table->num_rows == 0) {
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

// Processar ações do formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Adicionar mapeamento
    if (isset($_POST['action']) && $_POST['action'] == 'map_groups') {
        $selected_groups = $_POST['selected_groups'] ?? [];
        
        // Excluir mapeamentos atuais
        $delete_query = "DELETE FROM bot_group_mappings WHERE bot_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $bot_id);
        $stmt->execute();
        
        // Inserir novos mapeamentos
        if (!empty($selected_groups)) {
            $insert_query = "INSERT INTO bot_group_mappings (bot_id, group_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_query);
            
            foreach ($selected_groups as $group_id) {
                $stmt->bind_param("ii", $bot_id, $group_id);
                $stmt->execute();
            }
        }
        
        $_SESSION['message'] = "Mapeamentos atualizados com sucesso!";
        $_SESSION['alert_type'] = "success";
        
        // Log da ação
        logAdminAction($conn, $_SESSION['user_id'], "Admin atualizou mapeamentos do bot '{$bot['name']}'");
        
        // Redirecionar para evitar reenvio do formulário
        header("Location: map.php?bot_id=$bot_id");
        exit;
    }
}

// Buscar todos os grupos
$groups_by_type = [
    'all' => [
        'vip' => [],
        'comum' => []
    ],
    'pg_soft' => [
        'vip' => [],
        'comum' => []
    ],
    'pragmatic' => [
        'vip' => [],
        'comum' => []
    ]
];

// Buscar e classificar grupos por tipo e nível
$groups_query = "SELECT * FROM telegram_groups WHERE status = 'active' ORDER BY type, level, name";
$groups_result = $conn->query($groups_query);

if ($groups_result && $groups_result->num_rows > 0) {
    while ($group = $groups_result->fetch_assoc()) {
        $groups_by_type[$group['type']][$group['level']][] = $group;
    }
}

// Buscar mapeamentos atuais
$mapped_groups = [];
$mapping_query = "SELECT group_id FROM bot_group_mappings WHERE bot_id = ?";
$stmt = $conn->prepare($mapping_query);
$stmt->bind_param("i", $bot_id);
$stmt->execute();
$mapping_result = $stmt->get_result();

if ($mapping_result && $mapping_result->num_rows > 0) {
    while ($mapping = $mapping_result->fetch_assoc()) {
        $mapped_groups[] = $mapping['group_id'];
    }
}

// Definir título da página
$pageTitle = 'Mapear Grupos para Bot: ' . $bot['name'];

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
        
        /* Bot Info Card */
        .bot-info-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            padding: 1.25rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .bot-avatar {
            width: 70px;
            height: 70px;
            background-color: var(--telegram-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .bot-details {
            flex-grow: 1;
        }
        
        .bot-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .bot-username {
            color: var(--telegram-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .bot-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .bot-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .bot-badge.type {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .bot-badge.game-type {
            background-color: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
        }
        
        .bot-badge.status {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        .bot-badge.status.inactive {
            background-color: rgba(231, 74, 59, 0.1);
            color: #e74a3b;
        }
        
        .bot-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Mapeamento de Grupos */
        .mapping-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .mapping-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f8f9fc;
            border-radius: 0.75rem 0.75rem 0 0;
        }
        
        .mapping-body {
            padding: 1.5rem;
        }
        
        .mapping-section {
            margin-bottom: 2rem;
        }
        
        .mapping-section-title {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .mapping-section-title i {
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
        }
        
        .mapping-section-title i.pg {
            background-color: #9c27b0;
        }
        
        .mapping-section-title i.pragmatic {
            background-color: #2196f3;
        }
        
        .mapping-section-title i.all {
            background-color: #f1c40f;
        }
        
        .group-list {
            border: 1px solid #e3e6f0;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .group-list-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1rem;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .group-list-header.vip {
            color: #9c27b0;
            background-color: rgba(156, 39, 176, 0.05);
        }
        
        .group-list-header.comum {
            color: #2ecc71;
            background-color: rgba(46, 204, 113, 0.05);
        }
        
        .group-list-body {
            padding: 0.5rem;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .group-item {
            padding: 0.75rem;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
            background-color: #f8f9fc;
            display: flex;
            align-items: center;
        }
        
        .group-item:last-child {
            margin-bottom: 0;
        }
        
        .group-item-checkbox {
            margin-right: 1rem;
        }
        
        .group-item-name {
            font-weight: 600;
            flex-grow: 1;
        }
        
        .group-item-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .no-groups {
            padding: 2rem;
            text-align: center;
            color: #6c757d;
        }
        
        .no-groups i {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
            
            .bot-info-card {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }
            
            .bot-meta {
                justify-content: center;
            }
            
            .bot-actions {
                margin-top: 1rem;
                justify-content: center;
            }
        }
        
        /* Custom Form Control */
        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
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
                <div class="d-flex align-items-center mb-4">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary me-3">
                        <i class="fas fa-arrow-left me-1"></i> Voltar
                    </a>
                    <h1 class="h3 mb-0 text-gray-800">Mapear Grupos para Bot</h1>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Informações do Bot -->
                <div class="bot-info-card">
                    <div class="bot-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="bot-details">
                        <div class="bot-name"><?php echo htmlspecialchars($bot['name']); ?></div>
                        <div class="bot-username">@<?php echo htmlspecialchars($bot['username']); ?></div>
                        <?php if (!empty($bot['description'])): ?>
                        <div class="text-muted"><?php echo htmlspecialchars($bot['description']); ?></div>
                        <?php endif; ?>
                        <div class="bot-meta">
                            <div class="bot-badge type">
                                <?php if ($bot['type'] == 'all'): ?>
                                    <i class="fas fa-users me-1"></i> Todos os Grupos
                                <?php elseif ($bot['type'] == 'premium'): ?>
                                    <i class="fas fa-crown me-1"></i> Apenas VIP
                                <?php else: ?>
                                    <i class="fas fa-user-friends me-1"></i> Apenas Comuns
                                <?php endif; ?>
                            </div>
                            <div class="bot-badge game-type">
                                <?php if ($bot['game_type'] == 'all'): ?>
                                    <i class="fas fa-gamepad me-1"></i> Todos os Jogos
                                <?php elseif ($bot['game_type'] == 'pg_soft'): ?>
                                    <i class="fas fa-dice me-1"></i> PG Soft
                                <?php else: ?>
                                    <i class="fas fa-gamepad me-1"></i> Pragmatic
                                <?php endif; ?>
                            </div>
                            <div class="bot-badge status <?php echo $bot['status'] == 'active' ? '' : 'inactive'; ?>">
                                <i class="fas <?php echo $bot['status'] == 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i> 
                                <?php echo $bot['status'] == 'active' ? 'Ativo' : 'Inativo'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="bot-actions">
                        <a href="index.php?edit=<?php echo $bot['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit me-1"></i> Editar Bot
                        </a>
                    </div>
                </div>
                
                <!-- Formulário de Mapeamento -->
                <form method="post" action="map.php?bot_id=<?php echo $bot_id; ?>">
                    <input type="hidden" name="action" value="map_groups">
                    
                    <div class="mapping-container">
                        <div class="mapping-header">
                            <span><i class="fas fa-link me-2"></i> Mapear Grupos para este Bot</span>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i> Salvar Mapeamentos
                            </button>
                        </div>
                        <div class="mapping-body">
                            <?php if (empty($groups_by_type['all']['vip']) && empty($groups_by_type['all']['comum']) && 
                                       empty($groups_by_type['pg_soft']['vip']) && empty($groups_by_type['pg_soft']['comum']) && 
                                       empty($groups_by_type['pragmatic']['vip']) && empty($groups_by_type['pragmatic']['comum'])): ?>
                            <div class="no-groups">
                                <i class="fas fa-users-slash"></i>
                                <h5>Nenhum grupo cadastrado</h5>
                                <p>Cadastre grupos no sistema para mapeá-los a este bot</p>
                                <a href="../telegram_groups/" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i> Cadastrar Grupos
                                </a>
                            </div>
                            <?php else: ?>
                                <!-- Grupos Todos (ALL) -->
                                <div class="mapping-section">
                                    <h5 class="mapping-section-title">
                                        <i class="fas fa-users all"></i> Grupos para Todos os Jogos
                                    </h5>
                                    
                                    <!-- Grupos VIP -->
                                    <?php if (!empty($groups_by_type['all']['vip'])): ?>
                                    <div class="group-list mb-3">
                                        <div class="group-list-header vip">
                                            <i class="fas fa-crown me-1"></i> Grupos VIP
                                        </div>
                                        <div class="group-list-body">
                                            <?php foreach ($groups_by_type['all']['vip'] as $group): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input group-item-checkbox" type="checkbox" 
                                                           name="selected_groups[]" value="<?php echo $group['id']; ?>" 
                                                           id="group_<?php echo $group['id']; ?>"
                                                           <?php echo in_array($group['id'], $mapped_groups) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="group-item-meta ms-auto">ID: <?php echo $group['group_id']; ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Grupos Comuns -->
                                    <?php if (!empty($groups_by_type['all']['comum'])): ?>
                                    <div class="group-list">
                                        <div class="group-list-header comum">
                                            <i class="fas fa-user-friends me-1"></i> Grupos Comuns
                                        </div>
                                        <div class="group-list-body">
                                            <?php foreach ($groups_by_type['all']['comum'] as $group): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input group-item-checkbox" type="checkbox" 
                                                           name="selected_groups[]" value="<?php echo $group['id']; ?>" 
                                                           id="group_<?php echo $group['id']; ?>"
                                                           <?php echo in_array($group['id'], $mapped_groups) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="group-item-meta ms-auto">ID: <?php echo $group['group_id']; ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (empty($groups_by_type['all']['vip']) && empty($groups_by_type['all']['comum'])): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhum grupo cadastrado para todos os jogos.
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Grupos PG Soft -->
                                <div class="mapping-section">
                                    <h5 class="mapping-section-title">
                                        <i class="fas fa-dice pg"></i> Grupos PG Soft
                                    </h5>
                                    
                                    <!-- Grupos VIP -->
                                    <?php if (!empty($groups_by_type['pg_soft']['vip'])): ?>
                                    <div class="group-list mb-3">
                                        <div class="group-list-header vip">
                                            <i class="fas fa-crown me-1"></i> Grupos VIP
                                        </div>
                                        <div class="group-list-body">
                                            <?php foreach ($groups_by_type['pg_soft']['vip'] as $group): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input group-item-checkbox" type="checkbox" 
                                                           name="selected_groups[]" value="<?php echo $group['id']; ?>" 
                                                           id="group_<?php echo $group['id']; ?>"
                                                           <?php echo in_array($group['id'], $mapped_groups) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="group-item-meta ms-auto">ID: <?php echo $group['group_id']; ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Grupos Comuns -->
                                    <?php if (!empty($groups_by_type['pg_soft']['comum'])): ?>
                                    <div class="group-list">
                                        <div class="group-list-header comum">
                                            <i class="fas fa-user-friends me-1"></i> Grupos Comuns
                                        </div>
                                        <div class="group-list-body">
                                            <?php foreach ($groups_by_type['pg_soft']['comum'] as $group): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input group-item-checkbox" type="checkbox" 
                                                           name="selected_groups[]" value="<?php echo $group['id']; ?>" 
                                                           id="group_<?php echo $group['id']; ?>"
                                                           <?php echo in_array($group['id'], $mapped_groups) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="group-item-meta ms-auto">ID: <?php echo $group['group_id']; ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (empty($groups_by_type['pg_soft']['vip']) && empty($groups_by_type['pg_soft']['comum'])): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhum grupo cadastrado para PG Soft.
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Grupos Pragmatic -->
                                <div class="mapping-section">
                                    <h5 class="mapping-section-title">
                                        <i class="fas fa-gamepad pragmatic"></i> Grupos Pragmatic
                                    </h5>
                                    
                                    <!-- Grupos VIP -->
                                    <?php if (!empty($groups_by_type['pragmatic']['vip'])): ?>
                                    <div class="group-list mb-3">
                                        <div class="group-list-header vip">
                                            <i class="fas fa-crown me-1"></i> Grupos VIP
                                        </div>
                                        <div class="group-list-body">
                                            <?php foreach ($groups_by_type['pragmatic']['vip'] as $group): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input group-item-checkbox" type="checkbox" 
                                                           name="selected_groups[]" value="<?php echo $group['id']; ?>" 
                                                           id="group_<?php echo $group['id']; ?>"
                                                           <?php echo in_array($group['id'], $mapped_groups) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="group-item-meta ms-auto">ID: <?php echo $group['group_id']; ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Grupos Comuns -->
                                    <?php if (!empty($groups_by_type['pragmatic']['comum'])): ?>
                                    <div class="group-list">
                                        <div class="group-list-header comum">
                                            <i class="fas fa-user-friends me-1"></i> Grupos Comuns
                                        </div>
                                        <div class="group-list-body">
                                            <?php foreach ($groups_by_type['pragmatic']['comum'] as $group): ?>
                                            <div class="group-item">
                                                <div class="form-check">
                                                    <input class="form-check-input group-item-checkbox" type="checkbox" 
                                                           name="selected_groups[]" value="<?php echo $group['id']; ?>" 
                                                           id="group_<?php echo $group['id']; ?>"
                                                           <?php echo in_array($group['id'], $mapped_groups) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="group_<?php echo $group['id']; ?>">
                                                        <?php echo htmlspecialchars($group['name']); ?>
                                                    </label>
                                                </div>
                                                <span class="group-item-meta ms-auto">ID: <?php echo $group['group_id']; ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (empty($groups_by_type['pragmatic']['vip']) && empty($groups_by_type['pragmatic']['comum'])): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhum grupo cadastrado para Pragmatic.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Salvar Mapeamentos
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
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
    });
    </script>
</body>
</html>