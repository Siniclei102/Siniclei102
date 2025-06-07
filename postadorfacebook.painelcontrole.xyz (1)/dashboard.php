<?php
// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Data e hora atual do Brasil (UTC-3)
date_default_timezone_set('America/Sao_Paulo');
$currentDateTime = date('Y-m-d H:i:s');
$currentDateTimeFormatted = date('d/m/Y H:i:s');

// Configurações do usuário (usando os dados já estabelecidos)
$user = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'], // Siniclei102
    'name' => 'Siniclei',
    'email' => 'siniclei102@example.com',
    'avatar' => 'assets/img/avatar.png',
    'account_type' => 'admin',
    'account_status' => 'active',
    'subscription_expires_at' => '2025-12-31',
    'last_login' => $currentDateTime
];

// Calcular dias restantes de assinatura
$today = new DateTime();
$expiryDate = new DateTime($user['subscription_expires_at']);
$daysRemaining = $today->diff($expiryDate)->days;

// Dados reais do sistema (não simulados)
$stats = [
    'total_groups' => 0,
    'active_groups' => 0,
    'total_posts' => 0,
    'posts_today' => 0,
    'completed_posts' => 0,
    'failed_posts' => 0,
    'pending_posts' => 0,
    'total_reach' => 0
];

// Arrays vazios iniciais (sistema novo)
$recent_posts = [];
$top_groups = [];
$notifications = [];
$activities = [];

// Dados para gráfico dos últimos 7 dias (sistema novo - todos zeros)
$chartLabels = [];
$chartData = [];
$currentDate = new DateTime();
$currentDate->modify('-6 days');

for ($i = 0; $i < 7; $i++) {
    $chartLabels[] = $currentDate->format('Y-m-d');
    $chartData[] = 0; // Sistema novo, sem dados ainda
    $currentDate->modify('+1 day');
}

// Função para formatar data
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Função para formatar data e hora
function formatDateTime($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Função para obter classe CSS de status
function getStatusClass($status) {
    switch ($status) {
        case 'active':
        case 'completed':
            return 'success';
        case 'pending':
        case 'processing':
        case 'scheduled':
            return 'warning';
        case 'failed':
        case 'suspended':
            return 'danger';
        default:
            return 'info';
    }
}

// Função para obter ícone de status
function getStatusIcon($status) {
    switch ($status) {
        case 'completed':
            return 'check-circle';
        case 'processing':
            return 'spinner fa-spin';
        case 'scheduled':
            return 'clock';
        case 'failed':
            return 'times-circle';
        case 'pending':
            return 'pause-circle';
        default:
            return 'info-circle';
    }
}

// Função para obter rótulo de status
function getStatusLabel($status) {
    switch ($status) {
        case 'completed':
            return 'Concluído';
        case 'processing':
            return 'Processando';
        case 'scheduled':
            return 'Agendado';
        case 'failed':
            return 'Falha';
        case 'pending':
            return 'Pendente';
        default:
            return ucfirst($status);
    }
}

// Função para formatar número grande
function formatNumber($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    } elseif ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return $num;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PostGrupo Facebook</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            /* Cores primárias */
            --primary: #6c5ce7;
            --primary-dark: #5541d7;
            --primary-light: #a29bfe;
            
            /* Cores secundárias */
            --secondary: #00cec9;
            --secondary-dark: #00a8a3;
            --secondary-light: #81ecec;
            
            /* Cores de status */
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #ff7675;
            --info: #74b9ff;
            
            /* Cores de menu */
            --menu-item-1: #ff7675;
            --menu-item-2: #55efc4;
            --menu-item-3: #ffeaa7;
            --menu-item-4: #74b9ff;
            --menu-item-5: #a29bfe;
            --menu-item-6: #fd79a8;
            
            /* Cores de background */
            --bg-light: #f8f9fd;
            --bg-lighter: #ffffff;
            --bg-dark: #2d3436;
            
            /* Cores de texto */
            --text-primary: #2d3436;
            --text-secondary: #636e72;
            --text-light: #b2bec3;
            --text-white: #ffffff;
            
            /* Sombras */
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 5px 15px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            
            /* Bordas */
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 20px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--bg-lighter);
            box-shadow: var(--shadow-md);
            padding: 20px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            padding: 10px 0;
            margin-bottom: 30px;
        }
        
        .sidebar-logo-text {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-left: 10px;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu-item {
            margin-bottom: 5px;
            border-radius: var(--radius-sm);
        }
        
        .sidebar-menu-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .sidebar-menu-link:hover {
            background-color: var(--bg-light);
        }
        
        .sidebar-menu-link.active {
            background-color: var(--primary);
            color: var(--text-white);
        }
        
        .sidebar-menu-icon {
            width: 24px;
            margin-right: 10px;
            text-align: center;
            font-size: 18px;
        }
        
        .sidebar-menu-text {
            font-size: 15px;
        }
        
        .sidebar-divider {
            height: 1px;
            background-color: var(--bg-light);
            margin: 15px 0;
        }
        
        .sidebar-footer {
            margin-top: auto;
            text-align: center;
            padding: 15px 0;
            font-size: 13px;
            color: var(--text-light);
        }
        
        /* Main content */
        .main {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .header-title h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .header-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .search-box {
            position: relative;
            max-width: 300px;
        }
        
        .search-input {
            width: 100%;
            padding: 8px 15px;
            padding-left: 35px;
            border: 1px solid #eaeaea;
            border-radius: 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 14px;
        }
        
        .notification-icon {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: var(--bg-light);
            position: relative;
            transition: all 0.2s ease;
            color: var(--text-secondary);
            cursor: pointer;
        }
        
        .notification-icon:hover {
            background-color: var(--bg-light);
            color: var(--primary);
        }
        
        .user-menu {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px;
            border-radius: var(--radius-sm);
            cursor: pointer;
        }
        
        .user-menu:hover {
            background-color: var(--bg-light);
        }
        
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .user-info {
            line-height: 1.2;
        }
        
        .user-name {
            font-weight: 500;
            font-size: 14px;
        }
        
        .user-role {
            color: var(--text-secondary);
            font-size: 12px;
        }
        
        .dropdown-icon {
            color: var(--text-secondary);
            font-size: 12px;
            margin-left: 5px;
        }
        
        /* Dashboard content */
        .dashboard-content {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
        }
        
        .card {
            background-color: var(--bg-lighter);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .card-sm {
            grid-column: span 3;
        }
        
        .card-md {
            grid-column: span 6;
        }
        
        .card-lg {
            grid-column: span 12;
        }
        
        .stat-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-card-title {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .stat-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        
        .stat-card-icon.posts {
            background-color: var(--menu-item-1);
        }
        
        .stat-card-icon.groups {
            background-color: var(--menu-item-2);
        }
        
        .stat-card-icon.completed {
            background-color: var(--menu-item-3);
        }
        
        .stat-card-icon.reach {
            background-color: var(--menu-item-4);
        }
        
        .stat-card-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card-subtitle {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #e0e0e0;
            color: var(--text-secondary);
        }
        
        .btn-outline:hover {
            background-color: var(--bg-light);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .subscription-card {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px;
        }
        
        .subscription-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 1;
        }
        
        .subscription-card::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 1;
        }
        
        .subscription-content {
            position: relative;
            z-index: 2;
        }
        
        .subscription-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .subscription-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .subscription-status {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .subscription-date {
            font-size: 15px;
        }
        
        .subscription-message {
            margin-bottom: 20px;
        }
        
        .subscription-actions {
            display: flex;
            gap: 10px;
        }
        
        .subscription-btn {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-align: center;
            flex: 1;
        }
        
        .subscription-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .chart-container {
            position: relative;
            height: 280px;
        }
        
        .system-time {
            text-align: center;
            background-color: var(--primary-light);
            color: var(--text-white);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-bottom: 15px;
            display: inline-block;
            font-weight: 500;
        }
        
        .welcome-card {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, rgba(108, 92, 231, 0.1), rgba(0, 206, 201, 0.1));
            border-radius: var(--radius-md);
            margin-bottom: 20px;
        }
        
        .welcome-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .welcome-subtitle {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        .welcome-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--text-light);
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            margin-bottom: 20px;
        }
        
        @media (max-width: 1200px) {
            .card-sm {
                grid-column: span 6;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                padding: 20px 10px;
            }
            
            .sidebar-logo-text,
            .sidebar-menu-text,
            .sidebar-footer {
                display: none;
            }
            
            .sidebar-menu-link {
                justify-content: center;
                padding: 12px;
            }
            
            .sidebar-menu-icon {
                margin-right: 0;
                font-size: 20px;
            }
            
            .main {
                margin-left: 70px;
            }
            
            .card-sm {
                grid-column: span 6;
            }
            
            .card-md {
                grid-column: span 12;
            }
        }
        
        @media (max-width: 768px) {
            .card-sm {
                grid-column: span 12;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .search-box {
                width: 100%;
                max-width: none;
            }
        }
        
        @media (max-width: 576px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .sidebar-menu {
                display: flex;
                overflow-x: auto;
                padding-bottom: 10px;
            }
            
            .sidebar-menu-item {
                margin-right: 10px;
                margin-bottom: 0;
            }
            
            .sidebar-menu-link {
                padding: 10px;
            }
            
            .main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div style="width:40px;height:40px;background:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:20px;">PG</div>
            <div class="sidebar-logo-text">PostGrupo</div>
        </div>
        
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item">
                <a href="dashboard.php" class="sidebar-menu-link active">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="sidebar-menu-text">Dashboard</div>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="groups.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="sidebar-menu-text">Meus Grupos</div>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="posts.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="sidebar-menu-text">Postagens</div>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="schedule.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="sidebar-menu-text">Agendamentos</div>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="analytics.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="sidebar-menu-text">Analytics</div>
                </a>
            </li>
            
            <div class="sidebar-divider"></div>
            
            <li class="sidebar-menu-item">
                <a href="settings.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="sidebar-menu-text">Configurações</div>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="extension.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-puzzle-piece"></i>
                    </div>
                    <div class="sidebar-menu-text">Extensão</div>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="support.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="sidebar-menu-text">Suporte</div>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="logout.php" class="sidebar-menu-link">
                    <div class="sidebar-menu-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <div class="sidebar-menu-text">Sair</div>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <div>PostGrupo v2.3.1</div>
            <div>&copy; <?php echo date('Y'); ?> Todos os direitos reservados</div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main">
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <div class="system-time">
                    <i class="far fa-clock"></i> <?php echo $currentDateTimeFormatted; ?> (Brasília)
                </div>
                <h1>Dashboard</h1>
                <div class="header-subtitle">
                    Bem-vindo de volta, <?php echo htmlspecialchars($user['name']); ?>! Aqui está uma visão geral das suas atividades.
                </div>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Buscar...">
                </div>
                
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                </div>
                
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="user-role"><?php echo $user['account_type'] === 'admin' ? 'Administrador' : 'Usuário'; ?></div>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Card for New User -->
            <div class="card card-lg">
                <div class="welcome-card">
                    <div class="welcome-title">Bem-vindo ao PostGrupo Facebook!</div>
                    <div class="welcome-subtitle">
                        Olá <strong><?php echo htmlspecialchars($user['username']); ?></strong>, sua conta foi criada com sucesso. 
                        Para começar a usar o sistema, siga os passos abaixo:
                    </div>
                    <div class="welcome-actions">
                        <a href="extension.php" class="btn btn-primary">
                            <i class="fas fa-puzzle-piece"></i> Instalar Extensão
                        </a>
                        <a href="groups.php" class="btn btn-outline">
                            <i class="fas fa-users"></i> Gerenciar Grupos
                        </a>
                        <a href="posts.php" class="btn btn-outline">
                            <i class="fas fa-paper-plane"></i> Criar Postagem
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="card card-sm">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Total de Postagens</div>
                        <div class="stat-card-icon posts">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['total_posts']); ?></div>
                    <div class="stat-card-subtitle">
                        <?php echo number_format($stats['posts_today']); ?> postagens hoje
                    </div>
                </div>
            </div>
            
            <div class="card card-sm">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Grupos Gerenciados</div>
                        <div class="stat-card-icon groups">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['total_groups']); ?></div>
                    <div class="stat-card-subtitle">
                        <?php echo number_format($stats['active_groups']); ?> grupos ativos
                    </div>
                </div>
            </div>
            
            <div class="card card-sm">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Postagens Concluídas</div>
                        <div class="stat-card-icon completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['completed_posts']); ?></div>
                    <div class="stat-card-subtitle">
                        100% de taxa de sucesso
                    </div>
                </div>
            </div>
            
            <div class="card card-sm">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Alcance Estimado</div>
                        <div class="stat-card-icon reach">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo formatNumber($stats['total_reach']); ?></div>
                    <div class="stat-card-subtitle">
                        Baseado em <?php echo number_format($stats['total_groups']); ?> grupos
                    </div>
                </div>
            </div>
            
            <!-- Posts Chart -->
            <div class="card card-md">
                <div class="card-header">
                    <div class="card-title">Atividade de Postagem</div>
                    <div class="card-actions">
                        <button class="btn btn-outline btn-sm" id="lastWeek">Última Semana</button>
                        <button class="btn btn-outline btn-sm" id="lastMonth">Último Mês</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="postsChart"></canvas>
                </div>
            </div>
            
            <!-- Subscription Status -->
            <div class="card card-md">
                <div class="subscription-card">
                    <div class="subscription-content">
                        <div class="subscription-title">Status da Assinatura</div>
                        <div class="subscription-info">
                            <div class="subscription-status">Ativo</div>
                            <div class="subscription-date">Expira em <?php echo formatDate($user['subscription_expires_at']); ?></div>
                        </div>
                        <div class="subscription-message">
                            <p>Olá, <strong><?php echo htmlspecialchars($user['username']); ?></strong>! 
                            Você tem <strong><?php echo $daysRemaining; ?> dias</strong> restantes em sua assinatura. 
                            Aproveite todas as funcionalidades premium!</p>
                        </div>
                        <div class="subscription-actions">
                            <a href="subscription.php" class="subscription-btn">Gerenciar Plano</a>
                            <a href="support.php" class="subscription-btn">Obter Ajuda</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Getting Started -->
            <div class="card card-lg">
                <div class="card-header">
                    <div class="card-title">Primeiros Passos</div>
                </div>
                <div class="empty-state">
                    <i class="fas fa-rocket"></i>
                    <h3>Comece sua jornada no PostGrupo</h3>
                    <p>Siga estes passos para configurar seu sistema de postagens automatizadas:</p>
                    
                    <div style="text-align: left; max-width: 600px; margin: 0 auto;">
                        <div style="margin-bottom: 20px; padding: 15px; background-color: var(--bg-light); border-radius: 8px;">
                            <strong>1. Instale a extensão do Chrome</strong>
                            <p style="margin: 5px 0; color: var(--text-secondary);">
                                A extensão é necessária para sincronizar seus grupos do Facebook com o sistema.
                            </p>
                            <a href="extension.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-puzzle-piece"></i> Instalar Agora
                            </a>
                        </div>
                        
                        <div style="margin-bottom: 20px; padding: 15px; background-color: var(--bg-light); border-radius: 8px;">
                            <strong>2. Sincronize seus grupos</strong>
                            <p style="margin: 5px 0; color: var(--text-secondary);">
                                Use a extensão para importar automaticamente todos os grupos que você administra.
                            </p>
                            <a href="groups.php" class="btn btn-outline btn-sm">
                                <i class="fas fa-users"></i> Ver Grupos
                            </a>
                        </div>
                        
                        <div style="margin-bottom: 20px; padding: 15px; background-color: var(--bg-light); border-radius: 8px;">
                            <strong>3. Crie sua primeira postagem</strong>
                            <p style="margin: 5px 0; color: var(--text-secondary);">
                                Comece a postar em múltiplos grupos simultaneamente com apenas um clique.
                            </p>
                            <a href="posts.php" class="btn btn-outline btn-sm">
                                <i class="fas fa-paper-plane"></i> Criar Postagem
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Atualizar relógio em tempo real (horário de Brasília)
        function updateClock() {
            const now = new Date();
            const timeDisplay = document.querySelector('.system-time');
            if (timeDisplay) {
                // Formatar a data e hora atual
                const options = { 
                    day: '2-digit', 
                    month: '2-digit', 
                    year: 'numeric',
                    hour: '2-digit', 
                    minute: '2-digit', 
                    second: '2-digit',
                    hour12: false,
                    timeZone: 'America/Sao_Paulo'
                };
                
                const formattedTime = now.toLocaleDateString('pt-BR', options).replace(',', '') + ' (Brasília)';
                timeDisplay.innerHTML = '<i class="far fa-clock"></i> ' + formattedTime;
            }
        }
        
        // Chart initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Posts Chart (empty for new system)
            const ctx = document.getElementById('postsChart').getContext('2d');
            const postsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Postagens',
                        data: <?php echo json_encode($chartData); ?>,
                        backgroundColor: 'rgba(108, 92, 231, 0.1)',
                        borderColor: '#6c5ce7',
                        borderWidth: 2,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#6c5ce7',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#2d3436',
                            bodyColor: '#2d3436',
                            bodyFont: {
                                family: "'Poppins', sans-serif",
                                size: 14
                            },
                            titleFont: {
                                family: "'Poppins', sans-serif",
                                size: 14,
                                weight: 'bold'
                            },
                            padding: 12,
                            callbacks: {
                                title: function(context) {
                                    const date = new Date(context[0].label);
                                    return 'Data: ' + date.toLocaleDateString('pt-BR');
                                },
                                label: function(context) {
                                    return 'Postagens: ' + context.raw;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    family: "'Poppins', sans-serif",
                                    size: 12
                                },
                                color: '#636e72',
                                callback: function(value, index, values) {
                                    const date = new Date(this.getLabelForValue(value));
                                    return date.getDate() + '/' + (date.getMonth() + 1);
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f1f1'
                            },
                            ticks: {
                                precision: 0,
                                font: {
                                    family: "'Poppins', sans-serif",
                                    size: 12
                                },
                                color: '#636e72'
                            }
                        }
                    }
                }
            });
            
            // Chart filter buttons
            document.getElementById('lastWeek').addEventListener('click', function() {
                this.classList.add('btn-primary');
                this.classList.remove('btn-outline');
                document.getElementById('lastMonth').classList.add('btn-outline');
                document.getElementById('lastMonth').classList.remove('btn-primary');
            });
            
            document.getElementById('lastMonth').addEventListener('click', function() {
                this.classList.add('btn-primary');
                this.classList.remove('btn-outline');
                document.getElementById('lastWeek').classList.add('btn-outline');
                document.getElementById('lastWeek').classList.remove('btn-primary');
            });
            
            // Set lastWeek as active by default
            document.getElementById('lastWeek').click();
            
            // Atualizar o horário a cada segundo
            setInterval(updateClock, 1000);
            updateClock(); // Atualizar imediatamente
        });
    </script>
</body>
</html>