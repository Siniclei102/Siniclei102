<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de usuário master
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'master') {
    header('Location: ../../index.php');
    exit;
}

// Obter ID do usuário master
$masterId = $_SESSION['user_id'];

// Função para verificar seguramente se uma coluna existe na tabela
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($result && $result->num_rows > 0);
}

// Função para contar com segurança
function safeCountTable($conn, $table, $masterIdColumn, $masterId) {
    if (columnExists($conn, $table, $masterIdColumn)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM `$table` WHERE `$masterIdColumn` = ?");
        $stmt->bind_param("i", $masterId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['total'];
    }
    return 0; // Se a coluna não existir, retorna 0
}

// Contadores com verificação segura
$bots_count = safeCountTable($conn, 'bots', 'owner_id', $masterId);
$users_count = safeCountTable($conn, 'users', 'created_by', $masterId);
$platforms_count = safeCountTable($conn, 'platforms', 'owner_id', $masterId);
$signals_count = safeCountTable($conn, 'signals', 'master_id', $masterId); // Alterado para master_id

// Obter configurações do site
$siteName = getSetting($conn, 'site_name') ?: 'BotDeSinais';
$siteLogo = getSetting($conn, 'site_logo') ?: 'logo.png';

// Obter usuário atual
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $masterId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Calcular dias restantes
$daysRemaining = daysRemaining($user['expiry_date']);

// Data de expiração formatada
$expiryDate = !empty($user['expiry_date']) ? date('d/m/Y', strtotime($user['expiry_date'])) : 'Sem validade';

// Consultas seguras para dados recentes
// Usuários recentes
if (columnExists($conn, 'users', 'created_by')) {
    $recentUsers = $conn->prepare("SELECT * FROM users WHERE created_by = ? ORDER BY created_at DESC LIMIT 5");
    $recentUsers->bind_param("i", $masterId);
    $recentUsers->execute();
    $recentUsers = $recentUsers->get_result();
} else {
    // Fallback: mostrar todos os usuários
    $recentUsers = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
}

// Bots recentes
if (columnExists($conn, 'bots', 'owner_id')) {
    $recentBots = $conn->prepare("SELECT * FROM bots WHERE owner_id = ? ORDER BY created_at DESC LIMIT 5");
    $recentBots->bind_param("i", $masterId);
    $recentBots->execute();
    $recentBots = $recentBots->get_result();
} else if (columnExists($conn, 'bots', 'created_by')) {
    $recentBots = $conn->prepare("SELECT * FROM bots WHERE created_by = ? ORDER BY created_at DESC LIMIT 5");
    $recentBots->bind_param("i", $masterId);
    $recentBots->execute();
    $recentBots = $recentBots->get_result();
} else {
    // Fallback: mostrar todos os bots
    $recentBots = $conn->query("SELECT * FROM bots ORDER BY created_at DESC LIMIT 5");
}

// Sinais recentes - Agora com ênfase em que são automáticos
$signalsQuery = "SELECT s.*, b.name as bot_name, g.name as game_name 
                FROM signals s
                LEFT JOIN bots b ON s.bot_id = b.id
                LEFT JOIN games g ON s.game_id = g.id";

if (columnExists($conn, 'signals', 'master_id')) {
    $signalsQuery .= " WHERE s.master_id = ?";
    $signalsQuery .= " ORDER BY s.created_at DESC LIMIT 5";
    $recentSignals = $conn->prepare($signalsQuery);
    $recentSignals->bind_param("i", $masterId);
    $recentSignals->execute();
    $recentSignals = $recentSignals->get_result();
} else if (columnExists($conn, 'signals', 'created_by')) {
    $signalsQuery .= " WHERE s.created_by = ?";
    $signalsQuery .= " ORDER BY s.created_at DESC LIMIT 5";
    $recentSignals = $conn->prepare($signalsQuery);
    $recentSignals->bind_param("i", $masterId);
    $recentSignals->execute();
    $recentSignals = $recentSignals->get_result();
} else {
    // Fallback: mostrar todos os sinais
    $signalsQuery .= " ORDER BY s.created_at DESC LIMIT 5";
    $recentSignals = $conn->query($signalsQuery);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Master | <?php echo $siteName; ?></title>
    
    <!-- Estilos CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #7E57C2;  /* Cor roxa para Usuário Master */
            --secondary-color: #5C3A9E;
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
            background: linear-gradient(180deg, var(--primary-color) 10%, var(--secondary-color) 100%);
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
        
        /* Master Badge */
        .master-badge {
            background-color: var(--warning-color);
            color: #333;
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
            margin-left: 0.5rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .sidebar.collapsed .master-badge {
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
            height: 0;
            padding: 0;
            margin: 0;
            overflow: hidden;
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
            border-radius: 0 0 15px 15px;
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
        
        /* Card de validade do usuário */
        .expiry-card {
            background: linear-gradient(135deg, var(--info-color) 0%, #2980b9 100%);
            color: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.25rem 1.25rem rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .expiry-card h5 {
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .expiry-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .expiry-date {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .days-remaining {
            font-size: 2.5rem;
            font-weight: 800;
            text-align: center;
            line-height: 1;
        }
        
        .days-remaining .label {
            font-size: 0.85rem;
            opacity: 0.8;
        }
        
        /* Cards do Dashboard */
        .stat-card {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .stat-card .card-body {
            display: flex;
            align-items: center;
            padding: 1.25rem;
        }
        
        .stat-card .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 1rem;
        }
        
        .stat-card .stat-icon i {
            font-size: 1.75rem;
            color: #fff;
        }
        
        .stat-card .stat-info {
            flex: 1;
        }
        
        .stat-card .stat-info .stat-title {
            color: #5a5c69;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
        }
        
        .stat-card .stat-info .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0;
            color: #333;
        }
        
        /* Tabelas do Dashboard */
        .table-card {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .table-card .card-header {
            background-color: #fff;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #eaecf4;
        }
        
        .table-card .card-header h5 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .table-card .card-header h5 i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .table-card .card-body {
            padding: 0;
        }
        
        .table-card .table {
            margin-bottom: 0;
        }
        
        .table-card .table th {
            background-color: #f8f9fc;
            color: #2d3748;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
        }
        
        .table-card .table td {
            vertical-align: middle;
        }
        
        .table-card .empty-table {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .table-card .table-footer {
            background-color: #f8f9fc;
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: center;
        }
        
        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        .badge-status-active {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        .badge-status-pending {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
        }
        
        .badge-status-failed {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }
        
        /* Botões de Ação */
        .btn-action {
            border-radius: 50px;
            font-weight: 600;
            padding: 0.5rem 1.25rem;
        }
        
        .welcome-card {
            margin-bottom: 1.5rem;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }
        
        .welcome-card .card-body {
            padding: 2rem;
            color: white;
        }
        
        .welcome-card h4 {
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .welcome-card p {
            opacity: 0.8;
        }
        
        /* Auto-signal info badge */
        .auto-signal-badge {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            margin-left: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        .auto-signal-badge i {
            margin-right: 0.25rem;
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
                <h2><?php echo $siteName; ?> <span class="master-badge">Master</span></h2>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-tachometer-alt" style="color: var(--info-color);"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <div class="menu-header">Gerenciamento</div>
                
                <li>
                    <a href="users/">
                        <i class="fas fa-users" style="color: var(--warning-color);"></i>
                        <span>Meus Usuários</span>
                    </a>
                </li>
                <li>
                    <a href="bots/">
                        <i class="fas fa-robot" style="color: var(--success-color);"></i>
                        <span>Meus Bots</span>
                    </a>
                </li>
                <li>
                    <a href="platforms/">
                        <i class="fas fa-desktop" style="color: var(--info-color);"></i>
                        <span>Plataformas</span>
                    </a>
                </li>
                <li>
                    <a href="games/">
                        <i class="fas fa-gamepad" style="color: var(--danger-color);"></i>
                        <span>Jogos</span>
                    </a>
                </li>
                <li>
                    <a href="signals/">
                        <i class="fas fa-signal" style="color: var(--teal-color);"></i>
                        <span>Sinais</span>
                    </a>
                </li>
                
                <div class="menu-header">Configurações</div>
                
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user-circle" style="color: var(--purple-color);"></i>
                        <span>Meu Perfil</span>
                    </a>
                </li>
                
                <div class="menu-divider"></div>
                
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
                
                <div class="topbar-user">
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-md-inline-block me-1"><?php echo $_SESSION['username']; ?></span>
                            <img src="../../assets/img/master-avatar.png" alt="Master User">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i> Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Conteúdo da Página -->
            <div class="content">
                <!-- Card de Boas-vindas -->
                <div class="card welcome-card">
                    <div class="card-body">
                        <h4>Bem-vindo, <?php echo htmlspecialchars($user['full_name'] ?: $_SESSION['username']); ?>!</h4>
                        <p>Este é seu painel de controle como usuário Master. Aqui você pode gerenciar seus usuários, bots e monitorar os sinais automáticos.</p>
                    </div>
                </div>
                
                <!-- Card de Validade -->
                <div class="expiry-card">
                    <h5><i class="fas fa-calendar-check me-2"></i> Validade da sua conta</h5>
                    <div class="expiry-info">
                        <div>
                            <div class="text-white-50 mb-1">Data de expiração</div>
                            <div class="expiry-date"><?php echo $expiryDate; ?></div>
                        </div>
                        <div>
                            <div class="days-remaining">
                                <?php echo $daysRemaining; ?>
                                <div class="label">dias restantes</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon" style="background-color: var(--primary-color);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-title">Usuários</div>
                                    <div class="stat-value"><?php echo $users_count; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon" style="background-color: var(--success-color);">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-title">Bots</div>
                                    <div class="stat-value"><?php echo $bots_count; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon" style="background-color: var(--info-color);">
                                    <i class="fas fa-desktop"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-title">Plataformas</div>
                                    <div class="stat-value"><?php echo $platforms_count; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-icon" style="background-color: var(--warning-color);">
                                    <i class="fas fa-signal"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-title">Sinais Automáticos</div>
                                    <div class="stat-value"><?php echo $signals_count; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botões de Acesso Rápido -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="users/create.php" class="btn btn-primary btn-action">
                                        <i class="fas fa-user-plus me-2"></i> Novo Usuário
                                    </a>
                                    <a href="bots/create.php" class="btn btn-success btn-action">
                                        <i class="fas fa-robot me-2"></i> Novo Bot
                                    </a>
                                    <a href="platforms/create.php" class="btn btn-info text-white btn-action">
                                        <i class="fas fa-desktop me-2"></i> Nova Plataforma
                                    </a>
                                    <a href="signals/" class="btn btn-warning btn-action">
                                        <i class="fas fa-signal me-2"></i> Ver Sinais Automáticos
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Últimos Usuários -->
                    <div class="col-lg-6">
                        <div class="card table-card">
                            <div class="card-header">
                                <h5><i class="fas fa-users"></i> Últimos Usuários Cadastrados</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Usuário</th>
                                                <th>Data</th>
                                                <th>Validade</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($recentUsers && $recentUsers->num_rows > 0): ?>
                                                <?php while ($user_row = $recentUsers->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($user_row['username']); ?></strong>
                                                            <?php if (!empty($user_row['full_name'])): ?>
                                                                <div class="small text-muted"><?php echo htmlspecialchars($user_row['full_name']); ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('d/m/Y', strtotime($user_row['created_at'])); ?></td>
                                                        <td>
                                                            <?php 
                                                            if (!empty($user_row['expiry_date'])) {
                                                                echo date('d/m/Y', strtotime($user_row['expiry_date']));
                                                                
                                                                // Dias restantes
                                                                $user_days = daysRemaining($user_row['expiry_date']);
                                                                $days_class = $user_days <= 7 ? 'text-danger' : 'text-success';
                                                                echo " <span class='$days_class'>($user_days dias)</span>";
                                                            } else {
                                                                echo "Sem validade";
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($user_row['status'] == 'active'): ?>
                                                                <span class="badge-status badge-status-active">Ativo</span>
                                                            <?php else: ?>
                                                                <span class="badge-status badge-status-failed">Suspenso</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="empty-table">
                                                        <i class="fas fa-info-circle me-2"></i> Nenhum usuário cadastrado ainda
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="table-footer">
                                    <a href="users/" class="btn btn-sm btn-primary">Ver Todos os Usuários</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Últimos Bots -->
                    <div class="col-lg-6">
                        <div class="card table-card">
                            <div class="card-header">
                                <h5><i class="fas fa-robot"></i> Últimos Bots Criados</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Bot</th>
                                                <th>Token</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($recentBots && $recentBots->num_rows > 0): ?>
                                                <?php while ($bot = $recentBots->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($bot['name']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <span class="small text-muted"><?php echo isset($bot['token']) ? substr($bot['token'], 0, 10) . '...' : 'N/A'; ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if (isset($bot['status']) && $bot['status'] == 'active'): ?>
                                                                <span class="badge-status badge-status-active">Ativo</span>
                                                            <?php else: ?>
                                                                <span class="badge-status badge-status-failed">Inativo</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="empty-table">
                                                        <i class="fas fa-info-circle me-2"></i> Nenhum bot criado ainda
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="table-footer">
                                    <a href="bots/" class="btn btn-sm btn-success">Ver Todos os Bots</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Últimos Sinais Automáticos -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card table-card">
                            <div class="card-header">
                                <h5>
                                    <i class="fas fa-signal"></i> Últimos Sinais Automáticos
                                    <span class="auto-signal-badge"><i class="fas fa-robot"></i> Gerados pelo Sistema</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Bot</th>
                                                <th>Jogo</th>
                                                <th>Data/Hora</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($recentSignals && $recentSignals->num_rows > 0): ?>
                                                <?php while ($signal = $recentSignals->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($signal['bot_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($signal['game_name'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <?php 
                                                            // Verificar se scheduled_at existe, caso contrário usar created_at
                                                            $dateField = isset($signal['scheduled_at']) ? 'scheduled_at' : 'created_at';
                                                            echo isset($signal[$dateField]) ? date('d/m/Y H:i', strtotime($signal[$dateField])) : 'N/A'; 
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php if (isset($signal['status'])): ?>
                                                                <?php if ($signal['status'] == 'sent'): ?>
                                                                    <span class="badge-status badge-status-active">Enviado</span>
                                                                <?php elseif ($signal['status'] == 'pending'): ?>
                                                                    <span class="badge-status badge-status-pending">Pendente</span>
                                                                <?php else: ?>
                                                                    <span class="badge-status badge-status-failed">Falha</span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge-status badge-status-pending">Pendente</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="empty-table">
                                                        <i class="fas fa-info-circle me-2"></i> Nenhum sinal automático enviado ainda
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="table-footer">
                                    <a href="signals/" class="btn btn-sm btn-warning">Ver Todos os Sinais</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Rodapé da página -->
                <div class="text-center text-muted mt-4">
                    <p class="small">
                        Data e hora atual do servidor: <?php echo date('d/m/Y H:i:s'); ?>
                    </p>
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