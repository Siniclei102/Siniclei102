<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

// Definir título da página
$pageTitle = 'Dashboard do Administrador';

// Obter estatísticas
$stats = [
    'total_users' => getCount($conn, 'users WHERE role = "user"'),
    'active_users' => getCountWhere($conn, 'users', "role = 'user' AND status = 'active'"),
    'total_bots' => getCount($conn, 'bots'),
    'active_bots' => getCountWhere($conn, 'bots', "status = 'active'"),
    'total_signals_today' => getCountWhere($conn, 'signals', "DATE(schedule_time) = CURDATE()"),
    'games_count' => getCount($conn, 'games'),
    'platforms_count' => getCount($conn, 'platforms')
];

// Usuários recentes
$recentUsersStmt = $conn->prepare("
    SELECT id, username, role, status, created_at, last_login, expiry_date 
    FROM users 
    WHERE role = 'user' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recentUsersStmt->execute();
$recentUsers = $recentUsersStmt->get_result();

// Usuários expirando em breve
$expiringUsersStmt = $conn->prepare("
    SELECT id, username, role, status, created_at, last_login, expiry_date 
    FROM users 
    WHERE role = 'user' 
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
    AND status = 'active'
    ORDER BY expiry_date
    LIMIT 5
");
$expiringUsersStmt->execute();
$expiringUsers = $expiringUsersStmt->get_result();

// Sinais recentes
$recentSignalsStmt = $conn->prepare("
    SELECT s.*, g.name as game_name, p.name as platform_name, b.name as bot_name, u.username
    FROM signals s
    JOIN games g ON s.game_id = g.id
    JOIN platforms p ON s.platform_id = p.id
    JOIN bots b ON s.bot_id = b.id
    JOIN users u ON b.created_by = u.id
    ORDER BY s.schedule_time DESC
    LIMIT 5
");
$recentSignalsStmt->execute();
$recentSignals = $recentSignalsStmt->get_result();

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
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
        }
        
        /* Layout Principal - Design de Painéis Laterais */
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
            padding: 1.5rem 0;
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
            border-radius: 0 0 15px 15px; /* Bordas arredondadas na parte inferior */
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
        
        /* Badge do Admin na topbar fixa e não no menu móvel */
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
        
        /* Cards estilizados */
        .stat-card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 0;
            border-radius: 1rem; /* Bordas mais arredondadas */
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease-in-out;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .card-body {
            display: flex;
            padding: 1.25rem;
        }
        
        .stat-card .icon-container {
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 1rem; /* Bordas mais arredondadas */
            margin-right: 1rem;
        }
        
        .stat-card .icon-container i {
            font-size: 2rem;
            color: white;
        }
        
        .stat-card .card-content {
            flex: 1;
        }
        
        .stat-card .card-title {
            text-transform: uppercase;
            font-size: 0.7rem;
            font-weight: 700;
            color: #6e7d91;
            margin-bottom: 0.25rem;
            letter-spacing: 0.05rem;
        }
        
        .stat-card .card-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 0;
        }
        
        .stat-card .card-footer {
            background: transparent;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding: 0;
        }
        
        .stat-card .card-footer a {
            display: block;
            padding: 0.75rem;
            text-align: center;
            text-decoration: none;
            color: #6e7d91;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
            border-radius: 0 0 1rem 1rem; /* Bordas arredondadas no footer */
        }
        
        .stat-card .card-footer a:hover {
            background-color: #f8f9fc;
            color: var(--primary-color);
        }
        
        /* Cores para diferentes tipos de cards */
        .bg-primary-gradient {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        }
        
        .bg-success-gradient {
            background: linear-gradient(45deg, var(--success-color), #0db473);
        }
        
        .bg-info-gradient {
            background: linear-gradient(45deg, var(--info-color), #2aa5b8);
        }
        
        .bg-purple-gradient {
            background: linear-gradient(45deg, var(--purple-color), #6b33c5);
        }
        
        .bg-danger-gradient {
            background: linear-gradient(45deg, var(--danger-color), #c92e1e);
        }
        
        .bg-warning-gradient {
            background: linear-gradient(45deg, var(--warning-color), #e0ae20);
        }
        
        .bg-dark-gradient {
            background: linear-gradient(45deg, var(--dark-color), #3a3b47);
        }
        
        .bg-teal-gradient {
            background: linear-gradient(45deg, var(--teal-color), #169b80);
        }
        
        /* Botões de Ação Rápida - Versão Admin */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        
        .action-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background-color: white;
            border-radius: 0.75rem;
            text-decoration: none;
            color: #333;
            border: none;
            font-weight: 700;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: all 0.3s;
        }
        
        .action-btn i {
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
        }
        
        .btn-primary-soft {
            background-color: rgba(78, 115, 223, 0.1);
            color: var(--primary-color);
        }
        
        .btn-success-soft {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        .btn-info-soft {
            background-color: rgba(54, 185, 204, 0.1);
            color: var(--info-color);
        }
        
        .btn-danger-soft {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }
        
        .btn-warning-soft {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
        }
        
        /* Widgets personalizados para o admin */
        .overview-widget {
            background-color: #fff;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            padding: 1.5rem;
        }
        
        .overview-widget h5 {
            margin-top: 0;
            margin-bottom: 1rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: #333;
        }
        
        /* Estilos de Tabela */
        .table-card {
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            background-color: #fff;
        }
        
        .table-card .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .table-card .card-header h5 {
            margin: 0;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .table-card .card-header h5 i {
            margin-right: 0.5rem;
        }
        
        .table-card .table {
            margin-bottom: 0;
        }
        
        .table-card .table th {
            font-weight: 600;
            border-top: none;
            background-color: #f8f9fc;
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
            
            /* Quando o menu mobile está ativo, escurece o resto da tela */
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
        
        @media (max-width: 767.98px) {
            .stat-card .card-body {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .stat-card .icon-container {
                margin-right: 0;
                margin-bottom: 0.75rem;
            }
            
            .action-btn {
                padding: 0.75rem;
                font-size: 0.9rem;
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
                <img src="../assets/img/<?php echo $siteLogo; ?>" alt="<?php echo $siteName; ?>">
                <h2><?php echo $siteName; ?> <span class="admin-badge">Admin</span></h2>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-tachometer-alt" style="color: var(--danger-color);"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <div class="menu-header">Gerenciamento</div>
                
                <li>
                    <a href="users/">
                        <i class="fas fa-users" style="color: var(--primary-color);"></i>
                        <span>Usuários</span>
                    </a>
                </li>
                <li>
                    <a href="bots/">
                        <i class="fas fa-robot" style="color: var(--success-color);"></i>
                        <span>Bots</span>
                    </a>
                </li>
                <li>
                    <a href="games/">
                        <i class="fas fa-gamepad" style="color: var(--warning-color);"></i>
                        <span>Jogos</span>
                    </a>
                </li>
                <li>
                    <a href="platforms/">
                        <i class="fas fa-desktop" style="color: var(--info-color);"></i>
                        <span>Plataformas</span>
                    </a>
                </li>
                
                <div class="menu-header">Configurações</div>
                
                <li>
                    <a href="settings/">
                        <i class="fas fa-cog" style="color: var(--purple-color);"></i>
                        <span>Configurações</span>
                    </a>
                </li>
                <li>
                    <a href="logs/">
                        <i class="fas fa-clipboard-list" style="color: var(--teal-color);"></i>
                        <span>Logs do Sistema</span>
                    </a>
                </li>
                
                <div class="menu-divider"></div>
                
              
                </li>
                <li>
                    <a href="../logout.php">
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
                

                
                <div class="topbar-user">
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-md-inline-block me-1"><?php echo $_SESSION['username']; ?></span>
                            <img src="../assets/img/admin-avatar.png" alt="Admin">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="settings/"><i class="fas fa-cog me-2"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Conteúdo da Página -->
            <div class="content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 m-0">Dashboard do Administrador</h1>
                    <div class="d-flex align-items-center">
                        <span class="me-2">Data atual:</span>
                        <span class="badge bg-dark"><?php echo date('d/m/Y'); ?></span>
                    </div>
                </div>
                
                <!-- Estatísticas Principais -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body">
                                <div class="icon-container bg-primary-gradient">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="card-content">
                                    <h5 class="card-title">Usuários</h5>
                                    <p class="card-value">
                                        <?php echo $stats['active_users']; ?> / <?php echo $stats['total_users']; ?>
                                        <small class="text-muted fs-6 fw-normal">ativos</small>
                                    </p>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="users/">
                                    Gerenciar Usuários <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body">
                                <div class="icon-container bg-success-gradient">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <div class="card-content">
                                    <h5 class="card-title">Bots</h5>
                                    <p class="card-value">
                                        <?php echo $stats['active_bots']; ?> / <?php echo $stats['total_bots']; ?>
                                        <small class="text-muted fs-6 fw-normal">ativos</small>
                                    </p>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="bots/">
                                    Gerenciar Bots <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body">
                                <div class="icon-container bg-warning-gradient">
                                    <i class="fas fa-gamepad"></i>
                                </div>
                                <div class="card-content">
                                    <h5 class="card-title">Jogos</h5>
                                    <p class="card-value">
                                        <?php echo $stats['games_count']; ?>
                                        <small class="text-muted fs-6 fw-normal">cadastrados</small>
                                    </p>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="games/">
                                    Gerenciar Jogos <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body">
                                <div class="icon-container bg-info-gradient">
                                    <i class="fas fa-desktop"></i>
                                </div>
                                <div class="card-content">
                                    <h5 class="card-title">Plataformas</h5>
                                    <p class="card-value">
                                        <?php echo $stats['platforms_count']; ?>
                                        <small class="text-muted fs-6 fw-normal">cadastradas</small>
                                    </p>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="platforms/">
                                    Gerenciar Plataformas <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botões de Ação Rápida -->
                <div class="action-buttons">
                    <a href="users/create.php" class="action-btn btn-primary-soft">
                        <i class="fas fa-user-plus"></i> Novo Usuário
                    </a>
                    <a href="games/create.php" class="action-btn btn-warning-soft">
                        <i class="fas fa-plus"></i> Novo Jogo
                    </a>
                    <a href="platforms/create.php" class="action-btn btn-info-soft">
                        <i class="fas fa-plus"></i> Nova Plataforma
                    </a>
                    <a href="settings/" class="action-btn btn-danger-soft">
                        <i class="fas fa-cog"></i> Configurações
                    </a>
                </div>
                
                <!-- Blocos de informações -->
                <div class="row">
                    <!-- Usuários expirando em breve -->
                    <div class="col-lg-6">
                        <div class="table-card">
                            <div class="card-header">
                                <h5><i class="fas fa-clock text-warning"></i> Usuários Expirando em Breve</h5>
                                <a href="users/" class="btn btn-sm btn-warning">Ver todos</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Criado em</th>
                                            <th>Expira em</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($expiringUsers->num_rows > 0): ?>
                                            <?php while ($user = $expiringUsers->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <a href="users/edit.php?id=<?php echo $user['id']; ?>">
                                                            <?php echo htmlspecialchars($user['username']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                                    <td>
                                                        <?php 
                                                            $days_left = daysRemaining($user['expiry_date']);
                                                            echo formatDate($user['expiry_date']);
                                                            echo " <span class='badge bg-warning text-dark'>{$days_left} dias</span>";
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['status'] == 'active'): ?>
                                                            <span class="badge bg-success">Ativo</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Suspenso</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="fas fa-check-circle me-1"></i>
                                                        Nenhum usuário expirando nos próximos 7 dias
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Usuários Recentes -->
                    <div class="col-lg-6">
                        <div class="table-card">
                            <div class="card-header">
                                <h5><i class="fas fa-users text-primary"></i> Usuários Recentes</h5>
                                <a href="users/" class="btn btn-sm btn-primary">Ver todos</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Criado em</th>
                                            <th>Último Login</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recentUsers->num_rows > 0): ?>
                                            <?php while ($user = $recentUsers->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <a href="users/edit.php?id=<?php echo $user['id']; ?>">
                                                            <?php echo htmlspecialchars($user['username']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                                    <td>
                                                        <?php 
                                                            echo $user['last_login'] ? formatDate($user['last_login']) : 'Nunca';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['status'] == 'active'): ?>
                                                            <span class="badge bg-success">Ativo</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Suspenso</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4">
                                                    <div class="text-muted">
                                                        Nenhum usuário encontrado
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Sinais Recentes -->
                    <div class="col-lg-6">
                        <div class="table-card">
                            <div class="card-header">
                                <h5><i class="fas fa-signal text-info"></i> Sinais Recentes</h5>
                                <a href="signals/" class="btn btn-sm btn-info text-white">Ver todos</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Jogo</th>
                                            <th>Bot</th>
                                            <th>Usuário</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recentSignals->num_rows > 0): ?>
                                            <?php while ($signal = $recentSignals->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($signal['game_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($signal['bot_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($signal['username']); ?></td>
                                                    <td>
                                                        <?php if ($signal['status'] == 'sent'): ?>
                                                            <span class="badge bg-success">Enviado</span>
                                                        <?php elseif ($signal['status'] == 'pending'): ?>
                                                            <span class="badge bg-warning text-dark">Pendente</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Falha</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4">
                                                    <div class="text-muted">Nenhum sinal recente</div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resumo do Sistema -->
                    <div class="col-lg-6">
                        <div class="overview-widget">
                            <h5><i class="fas fa-chart-pie text-primary me-2"></i> Resumo do Sistema</h5>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <i class="fas fa-signal text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="small text-muted">Sinais Hoje</div>
                                            <div class="fw-bold"><?php echo $stats['total_signals_today']; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <i class="fas fa-server text-success"></i>
                                        </div>
                                        <div>
                                            <div class="small text-muted">Status do Servidor</div>
                                            <div class="fw-bold text-success">Online</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <i class="fas fa-memory text-info"></i>
                                        </div>
                                        <div>
                                            <div class="small text-muted">Uso de Memória</div>
                                            <div class="fw-bold">
                                                <?php echo function_exists('memory_get_usage') ? round(memory_get_usage() / 1048576, 2) . ' MB' : 'N/A'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <i class="fas fa-clock text-warning"></i>
                                        </div>
                                        <div>
                                            <div class="small text-muted">Hora do Servidor</div>
                                            <div class="fw-bold"><?php echo date('H:i:s'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <a href="settings/" class="btn btn-sm btn-primary">
                                    <i class="fas fa-cog me-1"></i> Configurações do Sistema
                                </a>
                                <a href="logs/" class="btn btn-sm btn-outline-secondary ms-2">
                                    <i class="fas fa-clipboard-list me-1"></i> Ver Logs
                                </a>
                            </div>
                        </div>
                        
                        <!-- Versão do Sistema -->
                        <div class="card shadow-sm mt-3 rounded-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="d-block h5 mb-0"><?php echo $siteName; ?></span>
                                        <span class="text-muted">Versão 1.0.0</span>
                                    </div>
                                    <div>
                                        <span class="badge bg-dark">Admin</span>
                                    </div>
                                </div>
                                <div class="mt-2 small text-muted">
                                    Último login: <?php echo date('d/m/Y H:i'); ?> 
                                    <span class="ms-2">Usuário: <?php echo $_SESSION['username']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const contentWrapper = document.getElementById('content-wrapper');
        const sidebarToggler = document.getElementById('sidebar-toggler');
        const overlay = document.getElementById('overlay');
        
        // Função para verificar se é mobile
        const isMobileDevice = function() {
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
        
        // Event listeners
        sidebarToggler.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-visible');
            overlay.classList.remove('active');
        });
        
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