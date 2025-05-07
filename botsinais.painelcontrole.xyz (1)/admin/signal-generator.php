<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Variáveis para alertas e mensagens
$alert = "";
$alert_type = "";

// Verificar e criar tabelas necessárias se não existirem
$tables_check = [
    'signal_generator_settings' => "CREATE TABLE IF NOT EXISTS signal_generator_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'signal_queue' => "CREATE TABLE IF NOT EXISTS signal_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        game_id INT NOT NULL,
        platform_id INT NOT NULL,
        signal_type ENUM('premium', 'regular') NOT NULL,
        strategy VARCHAR(100) NOT NULL,
        entry_value VARCHAR(100) NOT NULL,
        entry_type VARCHAR(50) NOT NULL,
        multiplier DECIMAL(10,2) NOT NULL,
        scheduled_at TIMESTAMP NOT NULL,
        status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL,
        error_message TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'signal_history' => "CREATE TABLE IF NOT EXISTS signal_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        queue_id INT NOT NULL,
        bot_id INT NOT NULL,
        channel_id INT NULL,
        signal_type ENUM('premium', 'regular') NOT NULL,
        status ENUM('sent', 'failed') NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        error_message TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($tables_check as $table => $create_sql) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows == 0) {
        $conn->query($create_sql);
    }
}

// Verificar e inserir configurações padrão se não existirem
$default_settings = [
    'premium_min_interval' => ['15', 'Intervalo mínimo em minutos entre sinais premium (15-30)'],
    'premium_max_interval' => ['30', 'Intervalo máximo em minutos entre sinais premium (15-30)'],
    'regular_min_interval' => ['60', 'Intervalo mínimo em minutos entre sinais regulares (60-120)'],
    'regular_max_interval' => ['120', 'Intervalo máximo em minutos entre sinais regulares (60-120)'],
    'win_rate_percentage' => ['80', 'Taxa de acerto simulada para os sinais gerados (%)'],
    'active' => ['true', 'Se o gerador de sinais está ativo ou não'],
    'last_premium_signal' => ['0', 'Timestamp do último sinal premium gerado'],
    'last_regular_signal' => ['0', 'Timestamp do último sinal regular gerado'],
    'premium_delay' => ['45', 'Atraso em minutos para enviar sinais premium após geração'],
    'regular_delay' => ['5', 'Atraso em minutos para enviar sinais regulares após geração']
];

foreach ($default_settings as $key => $data) {
    $check = $conn->prepare("SELECT COUNT(*) as count FROM signal_generator_settings WHERE setting_key = ?");
    $check->bind_param("s", $key);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->fetch_assoc()['count'] == 0) {
        $stmt = $conn->prepare("INSERT INTO signal_generator_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $key, $data[0], $data[1]);
        $stmt->execute();
    }
}

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Atualizar configurações
    if (isset($_POST['action']) && $_POST['action'] == 'update_settings') {
        $settings = [
            'premium_min_interval' => (int)$_POST['premium_min_interval'],
            'premium_max_interval' => (int)$_POST['premium_max_interval'],
            'regular_min_interval' => (int)$_POST['regular_min_interval'],
            'regular_max_interval' => (int)$_POST['regular_max_interval'],
            'win_rate_percentage' => (int)$_POST['win_rate_percentage'],
            'active' => isset($_POST['active']) ? 'true' : 'false',
            // Novos campos de atraso adicionados
            'premium_delay' => (int)$_POST['premium_delay'],
            'regular_delay' => (int)$_POST['regular_delay']
        ];
        
        // Validar valores
        if ($settings['premium_min_interval'] > $settings['premium_max_interval']) {
            $alert = "O intervalo mínimo premium não pode ser maior que o máximo";
            $alert_type = "danger";
        } elseif ($settings['regular_min_interval'] > $settings['regular_max_interval']) {
            $alert = "O intervalo mínimo regular não pode ser maior que o máximo";
            $alert_type = "danger";
        } elseif ($settings['win_rate_percentage'] < 0 || $settings['win_rate_percentage'] > 100) {
            $alert = "A taxa de acerto deve estar entre 0 e 100%";
            $alert_type = "danger";
        } else {
            // Atualizar configurações
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("UPDATE signal_generator_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
            }
            
            $alert = "Configurações atualizadas com sucesso!";
            $alert_type = "success";
        }
    }
    
    // Gerar sinais manualmente
    if (isset($_POST['action']) && $_POST['action'] == 'generate_now') {
        $type = $_POST['signal_type'];
        
        require_once '../includes/signal-generator.php';
        
        if (class_exists('SignalGenerator')) {
            $generator = new SignalGenerator($conn);
            
            if ($generator->generateSignal($type)) {
                $alert = "Sinal $type gerado com sucesso!";
                $alert_type = "success";
            } else {
                $alert = "Erro ao gerar sinal $type. Verifique se existem jogos e plataformas ativos.";
                $alert_type = "danger";
            }
        } else {
            $alert = "Classe SignalGenerator não encontrada. Verifique o arquivo signal-generator.php.";
            $alert_type = "danger";
        }
    }
    
    // Resetar contadores de tempo
    if (isset($_POST['action']) && $_POST['action'] == 'reset_counters') {
        $resetStmt = $conn->prepare("UPDATE signal_generator_settings SET setting_value = '0' 
                                    WHERE setting_key IN ('last_premium_signal', 'last_regular_signal')");
        if ($resetStmt->execute()) {
            $alert = "Contadores de tempo resetados com sucesso!";
            $alert_type = "success";
        } else {
            $alert = "Erro ao resetar contadores de tempo.";
            $alert_type = "danger";
        }
    }
}

// Obter configurações atuais
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value, description FROM signal_generator_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'description' => $row['description']
        ];
    }
}

// Obter estatísticas de sinais
$stats = [
    'total_signals' => 0,
    'pending_signals' => 0,
    'sent_signals' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM signal_queue");
if ($result) {
    $stats['total_signals'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM signal_queue WHERE status = 'pending'");
if ($result) {
    $stats['pending_signals'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM signal_queue WHERE status = 'sent'");
if ($result) {
    $stats['sent_signals'] = $result->fetch_assoc()['count'];
}

// Últimos sinais gerados
$last_signals = [];
$sql = "SELECT q.*, g.name as game_name, p.name as platform_name 
        FROM signal_queue q 
        JOIN games g ON q.game_id = g.id 
        JOIN platforms p ON q.platform_id = p.id 
        ORDER BY q.created_at DESC LIMIT 10";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $last_signals[] = $row;
    }
}

// Próximos sinais previstos
function calculateNextSignal($lastSignalTime, $minInterval, $maxInterval) {
    if ($lastSignalTime == 0) {
        return "Imediato";
    }
    
    $now = time();
    $minTime = $lastSignalTime + ($minInterval * 60);
    $maxTime = $lastSignalTime + ($maxInterval * 60);
    
    if ($now >= $maxTime) {
        return "Imediato";
    } elseif ($now >= $minTime) {
        return "Entre agora e " . date('H:i', $maxTime);
    } else {
        return "Entre " . date('H:i', $minTime) . " e " . date('H:i', $maxTime);
    }
}

$next_premium = calculateNextSignal(
    (int)$settings['last_premium_signal']['value'], 
    (int)$settings['premium_min_interval']['value'], 
    (int)$settings['premium_max_interval']['value']
);

$next_regular = calculateNextSignal(
    (int)$settings['last_regular_signal']['value'], 
    (int)$settings['regular_min_interval']['value'], 
    (int)$settings['regular_max_interval']['value']
);

// Título da página
$pageTitle = "Gerador de Sinais Automáticos";

// Obter configurações do site
$siteName = getSetting($conn, 'site_name');
if (!$siteName) {
    $siteName = 'BotDeSinais';
}
$siteLogo = getSetting($conn, 'site_logo');
if (!$siteLogo) {
    $siteLogo = 'logo.png';
}
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
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
        
        .sidebar.collapsed .admin-badge {
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
        
        .card {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header h6 {
            margin: 0;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        /* Form controls */
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
        }
        
        .form-check-input:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        /* Tables */
        .table-card {
            border-radius: 0.75rem;
            overflow: hidden;
        }
        
        .table th {
            background-color: #f8f9fc;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.03rem;
        }
        
        /* Responsive overlay */
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
                <img src="../assets/img/<?php echo $siteLogo; ?>" alt="<?php echo $siteName; ?>">
                <h2><?php echo $siteName; ?> <span class="admin-badge">Admin</span></h2>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="index.php">
                        <i class="fas fa-tachometer-alt" style="color: var(--danger-color);"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <div class="menu-header">Gerenciamento</div>
                
                <li>
                    <a href="users.php">
                        <i class="fas fa-users" style="color: var(--primary-color);"></i>
                        <span>Usuários</span>
                    </a>
                </li>
                <li>
                    <a href="bots.php">
                        <i class="fas fa-robot" style="color: var(--success-color);"></i>
                        <span>Bots</span>
                    </a>
                </li>
                <li>
                                        <a href="games.php">
                        <i class="fas fa-gamepad" style="color: var(--warning-color);"></i>
                        <span>Jogos</span>
                    </a>
                </li>
                <li>
                    <a href="platforms.php">
                        <i class="fas fa-desktop" style="color: var(--info-color);"></i>
                        <span>Plataformas</span>
                    </a>
                </li>
                <li>
                    <a href="signal-generator.php" class="active">
                        <i class="fas fa-signal" style="color: var(--pink-color);"></i>
                        <span>Gerador de Sinais</span>
                    </a>
                </li>
                
                <div class="menu-header">Configurações</div>
                
                <li>
                    <a href="settings.php">
                        <i class="fas fa-cog" style="color: var(--purple-color);"></i>
                        <span>Configurações</span>
                    </a>
                </li>
                <li>
                    <a href="logs.php">
                        <i class="fas fa-clipboard-list" style="color: var(--teal-color);"></i>
                        <span>Logs do Sistema</span>
                    </a>
                </li>
                
                <div class="menu-divider"></div>
                
                <li>
                    <a href="../user/dashboard.php">
                        <i class="fas fa-user" style="color: var(--orange-color);"></i>
                        <span>Modo Usuário</span>
                    </a>
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
                
                <div class="topbar-admin-badge">Admin</div>
                
                <div class="topbar-user">
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-md-inline-block me-1"><?php echo $_SESSION['username']; ?></span>
                            <img src="../assets/img/admin-avatar.png" alt="Admin">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Conteúdo da Página -->
            <div class="content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Gerador de Sinais</li>
                        </ol>
                    </nav>
                </div>
                
                <?php if (!empty($alert)): ?>
                <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $alert; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Configurações do Gerador -->
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Configurações do Gerador</h6>
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" id="toggleGenerator" 
                                        <?php echo isset($settings['active']) && $settings['active']['value'] === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="toggleGenerator">
                                        <?php echo isset($settings['active']) && $settings['active']['value'] === 'true' ? 'Ativo' : 'Inativo'; ?>
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="post" action="signal-generator.php">
                                    <input type="hidden" name="action" value="update_settings">
                                    
                                    <div class="form-group mb-3">
                                        <label>Status do Gerador</label>
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="form-check-input" name="active" id="activeStatus" 
                                                <?php echo isset($settings['active']) && $settings['active']['value'] === 'true' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="activeStatus">
                                                Gerador ativo
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">Liga/desliga o gerador automático de sinais</small>
                                    </div>
                                    
                                    <h5 class="text-primary">Sinais Premium (15-30min)</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="premium_min_interval">Intervalo mínimo (minutos)</label>
                                                <input type="number" class="form-control" id="premium_min_interval" name="premium_min_interval" 
                                                    value="<?php echo isset($settings['premium_min_interval']) ? $settings['premium_min_interval']['value'] : '15'; ?>" min="1" max="120" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="premium_max_interval">Intervalo máximo (minutos)</label>
                                                <input type="number" class="form-control" id="premium_max_interval" name="premium_max_interval" 
                                                    value="<?php echo isset($settings['premium_max_interval']) ? $settings['premium_max_interval']['value'] : '30'; ?>" min="1" max="120" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <h5 class="text-primary mt-4">Sinais Regulares (1-2h)</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="regular_min_interval">Intervalo mínimo (minutos)</label>
                                                <input type="number" class="form-control" id="regular_min_interval" name="regular_min_interval" 
                                                    value="<?php echo isset($settings['regular_min_interval']) ? $settings['regular_min_interval']['value'] : '60'; ?>" min="1" max="480" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="regular_max_interval">Intervalo máximo (minutos)</label>
                                                <input type="number" class="form-control" id="regular_max_interval" name="regular_max_interval" 
                                                    value="<?php echo isset($settings['regular_max_interval']) ? $settings['regular_max_interval']['value'] : '120'; ?>" min="1" max="480" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Novos campos de atraso adicionados aqui -->
                                    <h5 class="text-primary mt-4">Atrasos de Envio</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="premium_delay">Atraso para sinais premium (minutos)</label>
                                                <input type="number" class="form-control" id="premium_delay" name="premium_delay" 
                                                    value="<?php echo isset($settings['premium_delay']) ? $settings['premium_delay']['value'] : '45'; ?>" min="0" max="180" required>
                                                <small class="form-text text-muted">Tempo de espera entre geração e envio do sinal premium</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="regular_delay">Atraso para sinais regulares (minutos)</label>
                                                <input type="number" class="form-control" id="regular_delay" name="regular_delay" 
                                                    value="<?php echo isset($settings['regular_delay']) ? $settings['regular_delay']['value'] : '5'; ?>" min="0" max="180" required>
                                                <small class="form-text text-muted">Tempo de espera entre geração e envio do sinal regular</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="win_rate_percentage">Taxa de acerto simulada (%)</label>
                                        <input type="number" class="form-control" id="win_rate_percentage" name="win_rate_percentage" 
                                            value="<?php echo isset($settings['win_rate_percentage']) ? $settings['win_rate_percentage']['value'] : '80'; ?>" min="0" max="100" required>
                                        <small class="form-text text-muted">Define o percentual de sinais que serão marcados como "acertados"</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Estatísticas e Controles -->
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Status e Estatísticas</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card bg-primary text-white mb-4">
                                            <div class="card-body">
                                                <div class="text-xs font-weight-bold text-uppercase mb-1">Total de Sinais</div>
                                                <div class="h5 mb-0 font-weight-bold"><?php echo isset($stats['total_signals']) ? $stats['total_signals'] : 0; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-success text-white mb-4">
                                            <div class="card-body">
                                                <div class="text-xs font-weight-bold text-uppercase mb-1">Sinais Enviados</div>
                                                <div class="h5 mb-0 font-weight-bold"><?php echo isset($stats['sent_signals']) ? $stats['sent_signals'] : 0; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <h5 class="mt-4">Próximos Sinais Previstos</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Tipo</th>
                                                <th>Próximo Sinal</th>
                                                <th>Última Geração</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Premium</td>
                                                <td><?php echo isset($next_premium) ? $next_premium : 'Imediato'; ?></td>
                                                <td>
                                                    <?php 
                                                    if (isset($settings['last_premium_signal']) && (int)$settings['last_premium_signal']['value'] > 0) {
                                                        echo date('d/m/Y H:i:s', (int)$settings['last_premium_signal']['value']);
                                                    } else {
                                                        echo "Nunca";
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Regular</td>
                                                <td><?php echo isset($next_regular) ? $next_regular : 'Imediato'; ?></td>
                                                <td>
                                                    <?php 
                                                    if (isset($settings['last_regular_signal']) && (int)$settings['last_regular_signal']['value'] > 0) {
                                                        echo date('d/m/Y H:i:s', (int)$settings['last_regular_signal']['value']);
                                                    } else {
                                                        echo "Nunca";
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <h5 class="mt-4">Controles Manuais</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <form method="post" action="signal-generator.php" class="mb-3">
                                            <input type="hidden" name="action" value="generate_now">
                                            <input type="hidden" name="signal_type" value="premium">
                                            <button type="submit" id="generate-premium" class="btn btn-success btn-block w-100">
                                                <i class="fas fa-plus-circle"></i> Gerar Sinal Premium Agora
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <form method="post" action="signal-generator.php" class="mb-3">
                                            <input type="hidden" name="action" value="generate_now">
                                            <input type="hidden" name="signal_type" value="regular">
                                            <button type="submit" id="generate-regular" class="btn btn-info btn-block w-100 text-white">
                                                <i class="fas fa-plus-circle"></i> Gerar Sinal Regular Agora
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <form method="post" action="signal-generator.php" class="mt-3">
                                    <input type="hidden" name="action" value="reset_counters">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-redo"></i> Resetar Contadores de Tempo
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Últimos Sinais Gerados -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Últimos Sinais Gerados</h6>
                        <a href="platforms.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-desktop"></i> Gerenciar Plataformas
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="signalsTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tipo</th>
                                        <th>Jogo</th>
                                        <th>Plataforma</th>
                                        <th>Status</th>
                                        <th>Criado em</th>
                                        <th>Será enviado em</th>
                                        <th>Atraso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($last_signals) && !empty($last_signals)): ?>
                                        <?php foreach ($last_signals as $signal): ?>
                                        <tr>
                                            <td><?php echo $signal['id']; ?></td>
                                            <td>
                                                <?php if ($signal['signal_type'] == 'premium'): ?>
                                                <span class="badge bg-primary">Premium</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">Regular</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($signal['game_name']); ?></td>
                                            <td><?php echo htmlspecialchars($signal['platform_name']); ?></td>
                                            <td>
                                                <?php if ($signal['status'] == 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pendente</span>
                                                <?php elseif ($signal['status'] == 'sent'): ?>
                                                <span class="badge bg-success">Enviado</span>
                                                <?php else: ?>
                                                <span class="badge bg-danger">Falha</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($signal['created_at'])); ?></td>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($signal['scheduled_at'])); ?></td>
                                            <td>
                                                <?php
                                                $created = new DateTime($signal['created_at']);
                                                $scheduled = new DateTime($signal['scheduled_at']);
                                                $diff = $created->diff($scheduled);
                                                $minutes = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;
                                                echo "$minutes minutos";
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-3">
                                                <div class="text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Nenhum sinal gerado ainda. Use os controles acima para gerar sinais manualmente.
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Dicas e Informações -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Requisitos para o Gerador</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h5><i class="fas fa-gamepad text-warning"></i> Jogos</h5>
                                    <p>O gerador precisa de jogos <strong>ativos</strong> para criar sinais.</p>
                                    <a href="games.php" class="btn btn-sm btn-warning">
                                        <i class="fas fa-gamepad"></i> Gerenciar Jogos
                                    </a>
                                </div>
                                
                                <div class="mb-3">
                                    <h5><i class="fas fa-desktop text-info"></i> Plataformas</h5>
                                    <p>É necessário ter plataformas <strong>ativas</strong> para que o gerador funcione.</p>
                                    <a href="platforms.php" class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-desktop"></i> Gerenciar Plataformas
                                    </a>
                                </div>
                                
                                <div>
                                    <h5><i class="fas fa-robot text-success"></i> Bots</h5>
                                    <p>Configure bots para receber e distribuir os sinais gerados.</p>
                                    <a href="bots.php" class="btn btn-sm btn-success">
                                        <i class="fas fa-robot"></i> Gerenciar Bots
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Configuração do Cronjob</h6>
                            </div>
                            <div class="card-body">
                                <p>Para o funcionamento automático do gerador de sinais, é necessário configurar um cronjob no servidor:</p>
                                
                                <div class="alert alert-info">
                                    <strong>Comando para o cronjob:</strong>
                                    <div class="mt-2 bg-dark text-white p-2 rounded">
                                        <code>* * * * * php /caminho/para/seu/site/includes/signal-generator.php >> /caminho/para/seu/site/logs/cronjob.log 2>&1</code>
                                    </div>
                                </div>
                                
                                <p>Este comando executa o script gerador a cada minuto, permitindo que os sinais sejam criados nos intervalos configurados.</p>
                                
                                <p class="mb-0">
                                    <i class="fas fa-info-circle text-primary"></i> 
                                    <small>Consulte a documentação do seu servidor ou entre em contato com seu provedor de hospedagem para obter ajuda na configuração do cronjob.</small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
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
        
        // Event listeners
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
        
        // Toggle para ligar/desligar gerador rapidamente
        const toggleGenerator = document.getElementById('toggleGenerator');
        if (toggleGenerator) {
            toggleGenerator.addEventListener('change', function() {
                // Atualiza o checkbox do formulário para sincronizar
                document.getElementById('activeStatus').checked = this.checked;
                
                // Exibe mensagem de feedback
                const status = this.checked ? 'ativado' : 'desativado';
                document.querySelector('label[for="toggleGenerator"]').textContent = this.checked ? 'Ativo' : 'Inativo';
                
                // Opcionalmente: fazer submit automático do formulário
                if (confirm(`O gerador será ${status}. Confirmar esta alteração?`)) {
                    document.querySelector('form[action="signal-generator.php"]').submit();
                } else {
                    // Reverter se o usuário cancela
                    this.checked = !this.checked;
                    document.getElementById('activeStatus').checked = this.checked;
                    document.querySelector('label[for="toggleGenerator"]').textContent = this.checked ? 'Ativo' : 'Inativo';
                }
            });
        }
        
        // DataTable para a tabela de sinais
        if (document.getElementById('signalsTable')) {
            $('#signalsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json"
                },
                "order": [[0, "desc"]],
                "pageLength": 10
            });
        }
    });
    </script>
</body>
</html>