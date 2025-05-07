<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Garantir que a tabela de configurações existe
$check_table = $conn->query("SHOW TABLES LIKE 'settings'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text NOT NULL,
        `category` varchar(50) DEFAULT 'site',
        `type` varchar(20) DEFAULT 'text',
        `description` text DEFAULT NULL,
        `created_at` datetime DEFAULT current_timestamp(),
        `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Inserir configurações padrão
    $default_settings = [
        // Configurações do Site
        ['site_name', 'BotDeSinais', 'site', 'text', 'Nome do site'],
        ['site_description', 'Sistema de gerenciamento de sinais para jogos', 'site', 'textarea', 'Descrição do site'],
        ['site_logo', 'logo.png', 'site', 'image', 'Logo do site'],
        ['site_favicon', 'favicon.ico', 'site', 'image', 'Favicon do site'],
        ['contact_email', 'admin@botsinais.com', 'site', 'email', 'E-mail de contato'],
        ['support_whatsapp', '', 'site', 'text', 'WhatsApp de suporte'],
        
        // Configurações de Sinais
        ['signal_vip_interval', '15', 'signals', 'number', 'Intervalo entre sinais VIP (em minutos)'],
        ['signal_comum_interval', '60', 'signals', 'number', 'Intervalo entre sinais comuns (em minutos)'],
        ['signal_platform_pg', 'F12.bet', 'signals', 'text', 'Plataforma para sinais PG Soft'],
        ['signal_platform_pragmatic', 'F12.bet', 'signals', 'text', 'Plataforma para sinais Pragmatic'],
        
        // Configurações de Telegram
        ['telegram_api_base', 'https://api.telegram.org/bot', 'telegram', 'text', 'URL base da API do Telegram'],
        ['telegram_webhook_enabled', 'false', 'telegram', 'boolean', 'Ativar webhook para receber atualizações'],
        ['telegram_webhook_url', '', 'telegram', 'text', 'URL do webhook (se ativado)'],
        
        // Configurações de Sistema
        ['auto_generate_enabled', 'true', 'system', 'boolean', 'Ativar geração automática de sinais'],
        ['auto_send_enabled', 'true', 'system', 'boolean', 'Ativar envio automático de sinais'],
        ['log_level', 'info', 'system', 'select', 'Nível de log do sistema (debug, info, warning, error)'],
        ['maintenance_mode', 'false', 'system', 'boolean', 'Ativar modo de manutenção'],
        ['maintenance_message', 'Sistema em manutenção. Voltaremos em breve!', 'system', 'textarea', 'Mensagem de manutenção'],
        
        // Configurações de Email
        ['smtp_host', '', 'email', 'text', 'Servidor SMTP'],
        ['smtp_port', '587', 'email', 'number', 'Porta SMTP'],
        ['smtp_username', '', 'email', 'text', 'Usuário SMTP'],
        ['smtp_password', '', 'email', 'password', 'Senha SMTP'],
        ['smtp_from_email', '', 'email', 'email', 'E-mail de envio'],
        ['smtp_from_name', '', 'email', 'text', 'Nome de envio'],
        
        // Configurações de Tema
        ['theme_color', '#4e73df', 'theme', 'color', 'Cor primária do tema'],
        ['theme_secondary_color', '#2e59d9', 'theme', 'color', 'Cor secundária do tema'],
        ['theme_success_color', '#1cc88a', 'theme', 'color', 'Cor de sucesso'],
        ['theme_danger_color', '#e74a3b', 'theme', 'color', 'Cor de erro'],
        ['custom_css', '', 'theme', 'textarea', 'CSS personalizado adicional']
    ];
    
    $insert_stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, category, type, description) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($default_settings as $setting) {
        $insert_stmt->bind_param("sssss", $setting[0], $setting[1], $setting[2], $setting[3], $setting[4]);
        $insert_stmt->execute();
    }
}

// Processar formulário de atualização de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $category = isset($_POST['category']) ? $_POST['category'] : 'site';
    
    // Obter todas as configurações desta categoria
    $query = "SELECT setting_key, type FROM settings WHERE category = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $updated = 0;
    
    while ($row = $result->fetch_assoc()) {
        $key = $row['setting_key'];
        $type = $row['type'];
        
        if (isset($_POST[$key])) {
            $value = $_POST[$key];
            
            // Processar upload de imagem
            if ($type === 'image' && isset($_FILES[$key]) && $_FILES[$key]['error'] === 0) {
                $upload_dir = '../../assets/img/';
                $file_name = basename($_FILES[$key]['name']);
                $target_path = $upload_dir . $file_name;
                
                // Garantir que o diretório existe
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                if (move_uploaded_file($_FILES[$key]['tmp_name'], $target_path)) {
                    $value = $file_name;
                }
            }
            
            // Atualizar configuração
            $update_query = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ss", $value, $key);
            
            if ($update_stmt->execute()) {
                $updated++;
            }
        }
    }
    
    if ($updated > 0) {
        $_SESSION['message'] = "Configurações atualizadas com sucesso!";
        $_SESSION['alert_type'] = "success";
        
        // Log da ação
        logAdminAction($conn, $_SESSION['user_id'], "Admin atualizou {$updated} configurações na categoria '{$category}'");
    } else {
        $_SESSION['message'] = "Nenhuma configuração foi alterada.";
        $_SESSION['alert_type'] = "info";
    }
    
    // Redirecionar para evitar reenvio do formulário
    header("Location: index.php?tab={$category}");
    exit;
}

// Carregar todas as configurações
$settings = [];
$categories = ['site', 'signals', 'telegram', 'system', 'email', 'theme'];
$settings_data = [];

foreach ($categories as $category) {
    $query = "SELECT * FROM settings WHERE category = ? ORDER BY id ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $settings_data[$category] = [];
    
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
        $settings_data[$category][] = $row;
    }
}

// Definir aba ativa
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $categories) ? $_GET['tab'] : 'site';

// Definir título da página
$pageTitle = 'Configurações do Sistema';

// Obter configurações do site
$siteName = isset($settings['site_name']) ? $settings['site_name'] : 'BotDeSinais';
$siteLogo = isset($settings['site_logo']) ? $settings['site_logo'] : 'logo.png';

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
            --primary-color: <?php echo isset($settings['theme_color']) ? $settings['theme_color'] : '#4e73df'; ?>;
            --secondary-color: <?php echo isset($settings['theme_secondary_color']) ? $settings['theme_secondary_color'] : '#2e59d9'; ?>;
            --success-color: <?php echo isset($settings['theme_success_color']) ? $settings['theme_success_color'] : '#1cc88a'; ?>;
            --danger-color: <?php echo isset($settings['theme_danger_color']) ? $settings['theme_danger_color'] : '#e74a3b'; ?>;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
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
        
        /* Estilos para Configurações */
        .settings-container {
            display: flex;
            gap: 1.5rem;
        }
        
        .settings-tabs {
            flex: 0 0 220px;
        }
        
        .settings-content {
            flex: 1;
        }
        
        .settings-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
        }
        
        .settings-nav {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding: 1rem;
        }
        
        .settings-nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.2s;
            font-weight: 600;
        }
        
        .settings-nav-item:hover {
            background-color: #f8f9fc;
        }
        
        .settings-nav-item.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .settings-nav-item i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        
        .settings-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .settings-header {
            padding: 1rem 1.25rem;
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .settings-body {
            padding: 1.5rem;
        }
        
        .settings-form-row {
            margin-bottom: 1.5rem;
        }
        
        .settings-form-row:last-child {
            margin-bottom: 0;
        }
        
        .settings-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .form-hint {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .image-preview {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border: 1px solid #e3e6f0;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
            background-color: #f8f9fc;
        }
        
        .color-preview {
            display: inline-block;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            vertical-align: middle;
            margin-right: 0.5rem;
        }
        
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-top: 0.25em;
        }
        
        .tab-content > .tab-pane {
            display: none;
        }
        
        .tab-content > .active {
            display: block;
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
            
            .settings-container {
                flex-direction: column;
            }
            
            .settings-tabs {
                margin-bottom: 1.5rem;
            }
            
            .settings-nav {
                flex-direction: row;
                overflow-x: auto;
                gap: 0.5rem;
                white-space: nowrap;
            }
            
            .settings-nav-item {
                padding: 0.75rem;
            }
            
            .settings-nav-item i {
                margin-right: 0.5rem;
            }
        }
        
        <?php if (!empty($settings['custom_css'])): ?>
        /* CSS Personalizado */
        <?php echo $settings['custom_css']; ?>
        <?php endif; ?>
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
                    <a href="../settings/" class="active">
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
                    <h1 class="h3 mb-0 text-gray-800">Configurações do Sistema</h1>
                    
                    <div>
                        <button type="button" class="btn btn-primary" id="saveSettingsBtn">
                            <i class="fas fa-save me-1"></i> Salvar Alterações
                        </button>
                    </div>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Conteúdo das Configurações -->
                <div class="settings-container">
                    <!-- Abas Laterais -->
                    <div class="settings-tabs">
                        <div class="settings-card">
                            <div class="settings-nav">
                                <a href="?tab=site" class="settings-nav-item <?php echo $active_tab === 'site' ? 'active' : ''; ?>">
                                    <i class="fas fa-globe"></i>
                                    <span>Site</span>
                                </a>
                                <a href="?tab=signals" class="settings-nav-item <?php echo $active_tab === 'signals' ? 'active' : ''; ?>">
                                    <i class="fas fa-signal"></i>
                                    <span>Sinais</span>
                                </a>
                                <a href="?tab=telegram" class="settings-nav-item <?php echo $active_tab === 'telegram' ? 'active' : ''; ?>">
                                    <i class="fab fa-telegram"></i>
                                    <span>Telegram</span>
                                </a>
                                <a href="?tab=system" class="settings-nav-item <?php echo $active_tab === 'system' ? 'active' : ''; ?>">
                                    <i class="fas fa-server"></i>
                                    <span>Sistema</span>
                                </a>
                                <a href="?tab=email" class="settings-nav-item <?php echo $active_tab === 'email' ? 'active' : ''; ?>">
                                    <i class="fas fa-envelope"></i>
                                    <span>E-mail</span>
                                </a>
                                <a href="?tab=theme" class="settings-nav-item <?php echo $active_tab === 'theme' ? 'active' : ''; ?>">
                                    <i class="fas fa-palette"></i>
                                    <span>Aparência</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conteúdo das Abas -->
                    <div class="settings-content">
                        <div class="tab-content">
                            <?php foreach ($settings_data as $category => $items): ?>
                            <div class="tab-pane <?php echo $active_tab === $category ? 'active' : ''; ?>" id="<?php echo $category; ?>-tab">
                                <form method="post" action="index.php" id="<?php echo $category; ?>Form" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_settings">
                                    <input type="hidden" name="category" value="<?php echo $category; ?>">
                                    
                                    <div class="settings-section">
                                        <div class="settings-header">
                                            <span>
                                                <?php if ($category === 'site'): ?>
                                                <i class="fas fa-globe me-2"></i> Configurações do Site
                                                <?php elseif ($category === 'signals'): ?>
                                                <i class="fas fa-signal me-2"></i> Configurações de Sinais
                                                <?php elseif ($category === 'telegram'): ?>
                                                <i class="fab fa-telegram me-2"></i> Configurações do Telegram
                                                <?php elseif ($category === 'system'): ?>
                                                <i class="fas fa-server me-2"></i> Configurações do Sistema
                                                <?php elseif ($category === 'email'): ?>
                                                <i class="fas fa-envelope me-2"></i> Configurações de E-mail
                                                <?php elseif ($category === 'theme'): ?>
                                                <i class="fas fa-palette me-2"></i> Configurações de Aparência
                                                <?php endif; ?>
                                            </span>
                                            
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fas fa-save me-1"></i> Salvar
                                            </button>
                                        </div>
                                        <div class="settings-body">
                                            <?php foreach ($items as $setting): ?>
                                            <div class="settings-form-row">
                                                <label for="<?php echo $setting['setting_key']; ?>" class="settings-label">
                                                    <?php echo $setting['description']; ?>
                                                </label>
                                                
                                                <?php if ($setting['type'] === 'text'): ?>
                                                <input type="text" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                
                                                <?php elseif ($setting['type'] === 'number'): ?>
                                                <input type="number" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                
                                                <?php elseif ($setting['type'] === 'textarea'): ?>
                                                <textarea class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" rows="3"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                                
                                                <?php elseif ($setting['type'] === 'image'): ?>
                                                <?php if (!empty($setting['setting_value']) && file_exists("../../assets/img/" . $setting['setting_value'])): ?>
                                                <div class="mb-2">
                                                    <img src="../../assets/img/<?php echo $setting['setting_value']; ?>" alt="Preview" class="image-preview">
                                                </div>
                                                <?php endif; ?>
                                                <input type="file" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" accept="image/*">
                                                <input type="hidden" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                <div class="form-hint">Deixe em branco para manter a imagem atual</div>
                                                
                                                <?php elseif ($setting['type'] === 'email'): ?>
                                                <input type="email" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                
                                                <?php elseif ($setting['type'] === 'password'): ?>
                                                <input type="password" class="form-control" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                
                                                <?php elseif ($setting['type'] === 'boolean'): ?>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="true" <?php echo $setting['setting_value'] === 'true' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="<?php echo $setting['setting_key']; ?>">
                                                        <?php echo $setting['setting_value'] === 'true' ? 'Ativado' : 'Desativado'; ?>
                                                    </label>
                                                </div>
                                                
                                                <?php elseif ($setting['type'] === 'select'): ?>
                                                <select class="form-select" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>">
                                                    <?php if ($setting['setting_key'] === 'log_level'): ?>
                                                    <option value="debug" <?php echo $setting['setting_value'] === 'debug' ? 'selected' : ''; ?>>Debug</option>
                                                    <option value="info" <?php echo $setting['setting_value'] === 'info' ? 'selected' : ''; ?>>Info</option>
                                                    <option value="warning" <?php echo $setting['setting_value'] === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                                    <option value="error" <?php echo $setting['setting_value'] === 'error' ? 'selected' : ''; ?>>Error</option>
                                                    <?php endif; ?>
                                                </select>
                                                
                                                <?php elseif ($setting['type'] === 'color'): ?>
                                                <div class="d-flex align-items-center">
                                                    <span class="color-preview" style="background-color: <?php echo htmlspecialchars($setting['setting_value']); ?>"></span>
                                                    <input type="color" class="form-control form-control-color" id="<?php echo $setting['setting_key']; ?>" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($setting['setting_key'] === 'smtp_password'): ?>
                                                <div class="form-hint">A senha SMTP será armazenada com segurança</div>
                                                <?php endif; ?>
                                                
                                                <?php if ($setting['setting_key'] === 'custom_css'): ?>
                                                <div class="form-hint">CSS personalizado para ajustar a aparência do site</div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <?php endforeach; ?>
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
        
        // Botão para salvar configurações
        const saveSettingsBtn = document.getElementById('saveSettingsBtn');
        if (saveSettingsBtn) {
            saveSettingsBtn.addEventListener('click', function() {
                const activeTabId = document.querySelector('.tab-pane.active').id;
                const activeFormId = activeTabId.replace('-tab', '');
                document.getElementById(activeFormId + 'Form').submit();
            });
        }
        
        // Preview de cores
        const colorInputs = document.querySelectorAll('input[type="color"]');
        colorInputs.forEach(input => {
            input.addEventListener('input', function() {
                const preview = this.previousElementSibling;
                preview.style.backgroundColor = this.value;
            });
        });
        
        // Toggle para switches
        const switchInputs = document.querySelectorAll('.form-switch input[type="checkbox"]');
        switchInputs.forEach(input => {
            input.addEventListener('change', function() {
                const label = this.nextElementSibling;
                label.textContent = this.checked ? 'Ativado' : 'Desativado';
            });
        });
        
        // Preview de imagem ao selecionar arquivo
        const imageInputs = document.querySelectorAll('input[type="file"]');
        imageInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const preview = this.previousElementSibling;
                    if (preview && preview.querySelector('img')) {
                        const img = preview.querySelector('img');
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            img.src = e.target.result;
                        };
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                }
            });
        });
    });
    </script>
</body>
</html>