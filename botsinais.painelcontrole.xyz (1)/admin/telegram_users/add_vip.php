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
    $telegram_id = trim($_POST['telegram_id']);
    $username = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    
    // Verificar se usuário já existe
    $check = $conn->prepare("SELECT id FROM telegram_users WHERE user_id = ?");
    $check->bind_param("s", $telegram_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Usuário já existe, pegar o ID
        $user_id = $result->fetch_assoc()['id'];
        
        // Atualizar informações básicas se fornecidas
        if (!empty($username) || !empty($first_name) || !empty($last_name)) {
            $update_fields = [];
            $update_params = [];
            $update_types = "";
            
            if (!empty($username)) {
                $update_fields[] = "username = ?";
                $update_params[] = $username;
                $update_types .= "s";
            }
            
            if (!empty($first_name)) {
                $update_fields[] = "first_name = ?";
                $update_params[] = $first_name;
                $update_types .= "s";
            }
            
            if (!empty($last_name)) {
                $update_fields[] = "last_name = ?";
                $update_params[] = $last_name;
                $update_types .= "s";
            }
            
            if (!empty($update_fields)) {
                $update_params[] = $user_id;
                $update_types .= "i";
                
                $update_sql = "UPDATE telegram_users SET " . implode(", ", $update_fields) . " WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param($update_types, ...$update_params);
                $update_stmt->execute();
            }
        }
    } else {
        // Criar novo usuário
        $stmt = $conn->prepare("INSERT INTO telegram_users (user_id, username, first_name, last_name, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssss", $telegram_id, $username, $first_name, $last_name);
        
        if (!$stmt->execute()) {
            $_SESSION['message'] = "Erro ao adicionar usuário do Telegram.";
            $_SESSION['alert_type'] = "danger";
            header('Location: index.php');
            exit;
        }
        
        $user_id = $stmt->insert_id;
    }
    
    // Processar inscrições em grupos
    $access_types = [];
    $active_groups = [];
    
    // Processar acesso PG Soft
    if (isset($_POST['pg_soft_access']) && $_POST['pg_soft_access'] == 1) {
        $access_types[] = "pg_soft";
        $pg_expires_at = !empty($_POST['pg_expires_at']) ? $_POST['pg_expires_at'] . ' 23:59:59' : NULL;
        $pg_groups = isset($_POST['pg_groups']) ? $_POST['pg_groups'] : [];
        
        foreach ($pg_groups as $group_id) {
            $active_groups[] = [
                'group_id' => $group_id,
                'type' => 'pg_soft',
                'expires_at' => $pg_expires_at
            ];
        }
    }
    
    // Processar acesso Pragmatic
    if (isset($_POST['pragmatic_access']) && $_POST['pragmatic_access'] == 1) {
        $access_types[] = "pragmatic";
        $pragmatic_expires_at = !empty($_POST['pragmatic_expires_at']) ? $_POST['pragmatic_expires_at'] . ' 23:59:59' : NULL;
        $pragmatic_groups = isset($_POST['pragmatic_groups']) ? $_POST['pragmatic_groups'] : [];
        
        foreach ($pragmatic_groups as $group_id) {
            $active_groups[] = [
                'group_id' => $group_id,
                'type' => 'pragmatic',
                'expires_at' => $pragmatic_expires_at
            ];
        }
    }
    
    // Tornar usuário premium se tiver qualquer tipo de acesso
    if (!empty($access_types)) {
        $update_premium = $conn->prepare("UPDATE telegram_users SET premium = 1 WHERE id = ?");
        $update_premium->bind_param("i", $user_id);
        $update_premium->execute();
    }
    
    // Verificar se a tabela user_group_access existe
    $table_check = $conn->query("SHOW TABLES LIKE 'user_group_access'");
    if ($table_check->num_rows == 0) {
        // Criar tabela se não existir
        $conn->query("CREATE TABLE `user_group_access` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `group_id` int(11) NOT NULL,
            `group_type` varchar(50) NOT NULL,
            `expires_at` datetime DEFAULT NULL,
            `status` enum('active','expired','revoked') NOT NULL DEFAULT 'active',
            `created_at` datetime DEFAULT current_timestamp(),
            `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `group_id` (`group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    
    // Registrar acesso aos grupos
    foreach ($active_groups as $group) {
        // Verificar se já existe um registro para este usuário e grupo
        $check_access = $conn->prepare("SELECT id FROM user_group_access WHERE user_id = ? AND group_id = ?");
        $check_access->bind_param("ii", $user_id, $group['group_id']);
        $check_access->execute();
        $access_result = $check_access->get_result();
        
        if ($access_result->num_rows > 0) {
            // Atualizar registro existente
            $access_id = $access_result->fetch_assoc()['id'];
            $update_access = $conn->prepare("UPDATE user_group_access SET expires_at = ?, status = 'active', updated_at = NOW() WHERE id = ?");
            $update_access->bind_param("si", $group['expires_at'], $access_id);
            $update_access->execute();
        } else {
            // Criar novo registro
            $insert_access = $conn->prepare("INSERT INTO user_group_access (user_id, group_id, group_type, expires_at, status) VALUES (?, ?, ?, ?, 'active')");
            $insert_access->bind_param("iiss", $user_id, $group['group_id'], $group['type'], $group['expires_at']);
            $insert_access->execute();
        }
        
        // Lógica para adicionar o usuário ao grupo do Telegram
        // (Implementar integração com a API do Telegram para adicionar usuário ao grupo)
    }
    
    $_SESSION['message'] = "Usuário adicionado com sucesso aos grupos VIP!";
    $_SESSION['alert_type'] = "success";
    
    // Log da ação
    $display_name = $username ?: ($first_name . ($last_name ? " " . $last_name : ""));
    $log_message = "Admin adicionou o usuário '$display_name' aos grupos VIP: " . implode(", ", $access_types);
    logAdminAction($conn, $_SESSION['user_id'], $log_message);
    
    header('Location: index.php');
    exit;
}

// Verificar a estrutura da tabela telegram_groups
$check_type_column = $conn->query("SHOW COLUMNS FROM telegram_groups LIKE 'type'");
$has_type_column = $check_type_column->num_rows > 0;

// Se não tiver a coluna type, adicioná-la
if (!$has_type_column) {
    $conn->query("ALTER TABLE telegram_groups ADD COLUMN `type` VARCHAR(50) DEFAULT NULL AFTER `name`");
}

// Buscar grupos PG Soft - corrigido para não usar a coluna tags
$pg_groups = [];
// Usando apenas a coluna type para filtrar
if ($has_type_column) {
    $pg_result = $conn->query("SELECT * FROM telegram_groups WHERE type = 'pg_soft' OR type = 'pg' ORDER BY name");
} else {
    // Se não tiver coluna type, pegar todos os grupos
    $pg_result = $conn->query("SELECT * FROM telegram_groups ORDER BY name");
}

if ($pg_result && $pg_result->num_rows > 0) {
    while ($group = $pg_result->fetch_assoc()) {
        $pg_groups[] = $group;
    }
}

// Buscar grupos Pragmatic - corrigido para não usar a coluna tags
$pragmatic_groups = [];
// Usando apenas a coluna type para filtrar
if ($has_type_column) {
    $pragmatic_result = $conn->query("SELECT * FROM telegram_groups WHERE type = 'pragmatic' ORDER BY name");
} else {
    // Se não tiver coluna type, pegar todos os grupos
    // Aqui vamos deixar vazio pois já pegamos todos acima
    $pragmatic_result = $conn->query("SELECT * FROM telegram_groups WHERE id NOT IN (SELECT id FROM telegram_groups WHERE type = 'pg_soft' OR type = 'pg') ORDER BY name");
    
    if ($pragmatic_result && $pragmatic_result->num_rows > 0) {
        while ($group = $pragmatic_result->fetch_assoc()) {
            $pragmatic_groups[] = $group;
        }
    }
}

// Definir título da página
$pageTitle = 'Adicionar Usuário VIP';
// Obter configurações do site
$siteName = getSetting($conn, 'site_name') ?: 'BotDeSinais';
$siteLogo = getSetting($conn, 'site_logo') ?: 'logo.png';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
            background: linear-gradient(180deg, #222222 10%, #000000 100%); /* Cor escura para admin */
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            color: #fff;
            transition: all 0.3s ease;
            border-radius: 0 15px 15px 0; /* Bordas arredondadas do lado direito */
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
            border-radius: 0 15px 0 0; /* Borda superior direita arredondada */
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
            border-radius: 0 50px 50px 0; /* Bordas arredondadas nos itens do menu */
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
        
        /* Estilos específicos para esta página */
        .group-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e3e6f0;
            border-radius: 0.35rem;
            padding: 0.5rem;
        }
        
        .group-list .form-check {
            padding: 0.5rem;
            border-bottom: 1px solid #f8f9fc;
        }
        
        .group-list .form-check:last-child {
            border-bottom: none;
        }
        
        .access-section {
            background-color: rgba(0,0,0,0.02);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .pg-soft-color {
            color: #9c27b0;
        }
        
        .pragmatic-color {
            color: #2196f3;
        }
        
        /* Cards */
        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            border-radius: 0.75rem;
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
                    <a href="index.php" class="active">
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
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Adicionar Usuário VIP</h1>
                        
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                    
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Detalhes do Usuário VIP</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Informações do Usuário</h5>
                                        
                                        <div class="mb-3">
                                            <label for="telegram_id" class="form-label">ID do Telegram*</label>
                                            <input type="text" class="form-control" id="telegram_id" name="telegram_id" required>
                                            <div class="form-text">ID numérico do usuário (ex: 123456789)</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Username</label>
                                            <div class="input-group">
                                                <span class="input-group-text">@</span>
                                                <input type="text" class="form-control" id="username" name="username">
                                            </div>
                                            <div class="form-text">Username sem o "@" (opcional)</div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col">
                                                <div class="mb-3">
                                                    <label for="first_name" class="form-label">Nome</label>
                                                    <input type="text" class="form-control" id="first_name" name="first_name">
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="mb-3">
                                                    <label for="last_name" class="form-label">Sobrenome</label>
                                                    <input type="text" class="form-control" id="last_name" name="last_name">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> 
                                            Como obter o ID do Telegram: Envie uma mensagem para @userinfobot e ele retornará o ID do usuário.
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Instruções</h5>
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <p><strong>Como adicionar um usuário aos grupos VIP:</strong></p>
                                                <ol>
                                                    <li>Preencha as informações do usuário</li>
                                                    <li>Selecione os tipos de acesso (PG Soft e/ou Pragmatic)</li>
                                                    <li>Para cada tipo de acesso:
                                                        <ul>
                                                            <li>Selecione os grupos específicos</li>
                                                            <li>Defina uma data de validade (opcional)</li>
                                                        </ul>
                                                    </li>
                                                    <li>Clique em "Adicionar Usuário VIP"</li>
                                                </ol>
                                                <p class="mb-0 text-primary"><i class="fas fa-lightbulb"></i> <strong>Dica:</strong> Você pode adicionar o usuário a múltiplos grupos de cada tipo.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <h5 class="mb-3">Configurar Acesso VIP</h5>
                                
                                <!-- Seção PG Soft -->
                                <div class="access-section mb-4">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="pg_soft_access" name="pg_soft_access" value="1">
                                        <label class="form-check-label" for="pg_soft_access">
                                            <i class="fas fa-dice pg-soft-color"></i> Acesso aos Sinais PG Soft
                                        </label>
                                    </div>
                                    
                                    <div id="pg_soft_options" class="ms-4" style="display: none;">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="pg_expires_at" class="form-label">Data de Validade</label>
                                                <input type="text" class="form-control datepicker" id="pg_expires_at" name="pg_expires_at" placeholder="Selecione uma data">
                                                <div class="form-text">Deixe em branco para acesso sem prazo de expiração.</div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Selecione os grupos PG Soft</label>
                                            
                                            <?php if (count($pg_groups) > 0): ?>
                                                <div class="group-list">
                                                    <?php foreach ($pg_groups as $group): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" value="<?php echo $group['id']; ?>" id="pg_group_<?php echo $group['id']; ?>" name="pg_groups[]">
                                                            <label class="form-check-label" for="pg_group_<?php echo $group['id']; ?>">
                                                                <?php echo htmlspecialchars($group['name']); ?>
                                                                <?php if (isset($group['premium']) && $group['premium'] == 1): ?>
                                                                    <span class="badge bg-warning text-dark">Premium</span>
                                                                <?php endif; ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning mb-0">
                                                    Não há grupos PG Soft cadastrados. <a href="../telegram_groups/add.php">Cadastrar novo grupo</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Seção Pragmatic -->
                                <div class="access-section mb-4">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="pragmatic_access" name="pragmatic_access" value="1">
                                        <label class="form-check-label" for="pragmatic_access">
                                            <i class="fas fa-gamepad pragmatic-color"></i> Acesso aos Sinais Pragmatic
                                        </label>
                                    </div>
                                    
                                    <div id="pragmatic_options" class="ms-4" style="display: none;">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="pragmatic_expires_at" class="form-label">Data de Validade</label>
                                                <input type="text" class="form-control datepicker" id="pragmatic_expires_at" name="pragmatic_expires_at" placeholder="Selecione uma data">
                                                <div class="form-text">Deixe em branco para acesso sem prazo de expiração.</div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Selecione os grupos Pragmatic</label>
                                            
                                            <?php if (count($pragmatic_groups) > 0): ?>
                                                <div class="group-list">
                                                    <?php foreach ($pragmatic_groups as $group): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" value="<?php echo $group['id']; ?>" id="pragmatic_group_<?php echo $group['id']; ?>" name="pragmatic_groups[]">
                                                            <label class="form-check-label" for="pragmatic_group_<?php echo $group['id']; ?>">
                                                                <?php echo htmlspecialchars($group['name']); ?>
                                                                <?php if (isset($group['premium']) && $group['premium'] == 1): ?>
                                                                    <span class="badge bg-warning text-dark">Premium</span>
                                                                <?php endif; ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning mb-0">
                                                    Não há grupos Pragmatic cadastrados. <a href="../telegram_groups/add.php">Cadastrar novo grupo</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-plus-circle"></i> Adicionar Usuário VIP
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
        
        // Inicializar datepicker para todas as datas
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d",
            minDate: "today",
            defaultDate: "2025-05-07", // Data atual em UTC
            locale: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
                    longhand: ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']
                },
                months: {
                    shorthand: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                    longhand: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']
                }
            }
        });
        
        // Toggle seções de PG Soft e Pragmatic
        const pgSoftAccess = document.getElementById('pg_soft_access');
        const pgSoftOptions = document.getElementById('pg_soft_options');
        
        if (pgSoftAccess && pgSoftOptions) {
            pgSoftAccess.addEventListener('change', function() {
                pgSoftOptions.style.display = this.checked ? 'block' : 'none';
            });
        }
        
        const pragmaticAccess = document.getElementById('pragmatic_access');
        const pragmaticOptions = document.getElementById('pragmatic_options');
        
        if (pragmaticAccess && pragmaticOptions) {
            pragmaticAccess.addEventListener('change', function() {
                pragmaticOptions.style.display = this.checked ? 'block' : 'none';
            });
        }
    });
    </script>
</body>
</html>