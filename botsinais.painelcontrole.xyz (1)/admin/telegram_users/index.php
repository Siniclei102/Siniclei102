<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Verificar se tabela existe e criar se necessário
$table_check = $conn->query("SHOW TABLES LIKE 'telegram_users'");
if ($table_check->num_rows == 0) {
    // Tabela não existe, vamos criá-la com todas as colunas necessárias
    $create_table = "CREATE TABLE `telegram_users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` varchar(50) NOT NULL,
        `username` varchar(100) DEFAULT NULL,
        `first_name` varchar(100) DEFAULT NULL,
        `last_name` varchar(100) DEFAULT NULL,
        `status` enum('active','blocked','inactive') NOT NULL DEFAULT 'active',
        `premium` tinyint(1) NOT NULL DEFAULT 0,
        `premium_expires_at` datetime DEFAULT NULL,
        `last_activity` datetime DEFAULT NULL,
        `created_at` datetime DEFAULT current_timestamp(),
        `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->query($create_table);
}

// Verificar estrutura da tabela e adicionar colunas faltantes
$columns_to_check = [
    'premium' => "ALTER TABLE telegram_users ADD COLUMN premium TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
    'premium_expires_at' => "ALTER TABLE telegram_users ADD COLUMN premium_expires_at DATETIME NULL AFTER premium",
    'last_activity' => "ALTER TABLE telegram_users ADD COLUMN last_activity DATETIME NULL AFTER premium_expires_at"
];

foreach ($columns_to_check as $column => $alter_query) {
    $column_check = $conn->query("SHOW COLUMNS FROM telegram_users LIKE '$column'");
    if ($column_check->num_rows == 0) {
        // Coluna não existe, adicionar
        $conn->query($alter_query);
    }
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Toggle Premium Status
        if ($_POST['action'] == 'toggle_premium') {
            $user_id = (int)$_POST['telegram_user_id'];
            $current_status = $_POST['current_premium'] == '1' ? 1 : 0;
            $new_status = $current_status ? 0 : 1;
            
            // Se estiver dando acesso premium, verificar se há data de expiração
            if ($new_status == 1 && isset($_POST['expires_at']) && !empty($_POST['expires_at'])) {
                $expires_at = $_POST['expires_at'] . ' 23:59:59'; // Final do dia
                $stmt = $conn->prepare("UPDATE telegram_users SET premium = ?, premium_expires_at = ? WHERE id = ?");
                $stmt->bind_param("isi", $new_status, $expires_at, $user_id);
            } else if ($new_status == 0) {
                // Removendo acesso premium - limpar data de expiração
                $stmt = $conn->prepare("UPDATE telegram_users SET premium = 0, premium_expires_at = NULL WHERE id = ?");
                $stmt->bind_param("i", $user_id);
            } else {
                // Dando acesso premium sem data de expiração
                $stmt = $conn->prepare("UPDATE telegram_users SET premium = 1, premium_expires_at = NULL WHERE id = ?");
                $stmt->bind_param("i", $user_id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Status premium do usuário atualizado com sucesso!";
                $_SESSION['alert_type'] = "success";
                
                // Log da ação
                $username = $_POST['username'] ?: 'Usuário #' . $user_id;
                $action_text = $new_status ? "concedeu acesso premium" : "removeu acesso premium";
                if ($new_status && isset($_POST['expires_at']) && !empty($_POST['expires_at'])) {
                    $action_text .= " até " . date('d/m/Y', strtotime($_POST['expires_at']));
                }
                logAdminAction($conn, $_SESSION['user_id'], "Admin $action_text para o usuário do Telegram '$username'");
            } else {
                $_SESSION['message'] = "Erro ao atualizar status premium do usuário.";
                $_SESSION['alert_type'] = "danger";
            }
            
            header('Location: index.php');
            exit;
        }
        
        // Adicionar novo usuário
        if ($_POST['action'] == 'add_user') {
            $telegram_id = trim($_POST['telegram_id']);
            $username = trim($_POST['username']);
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $is_premium = isset($_POST['is_premium']) ? 1 : 0;
            
            // Verificar se usuário já existe
            $check = $conn->prepare("SELECT id FROM telegram_users WHERE user_id = ?");
            $check->bind_param("s", $telegram_id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['message'] = "Este usuário do Telegram já está cadastrado.";
                $_SESSION['alert_type'] = "warning";
            } else {
                // Se premium, verificar data de expiração
                if ($is_premium && isset($_POST['expires_at']) && !empty($_POST['expires_at'])) {
                    $expires_at = $_POST['expires_at'] . ' 23:59:59'; // Final do dia
                    $stmt = $conn->prepare("INSERT INTO telegram_users (user_id, username, first_name, last_name, premium, premium_expires_at, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                    $stmt->bind_param("ssssis", $telegram_id, $username, $first_name, $last_name, $is_premium, $expires_at);
                } else {
                    $stmt = $conn->prepare("INSERT INTO telegram_users (user_id, username, first_name, last_name, premium, status) VALUES (?, ?, ?, ?, ?, 'active')");
                    $stmt->bind_param("ssssi", $telegram_id, $username, $first_name, $last_name, $is_premium);
                }
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Usuário do Telegram adicionado com sucesso!";
                    $_SESSION['alert_type'] = "success";
                    
                    // Log da ação
                    $display_name = $username ?: ($first_name . ($last_name ? " " . $last_name : ""));
                    $action_text = "adicionou o usuário do Telegram '$display_name'";
                    if ($is_premium && isset($_POST['expires_at']) && !empty($_POST['expires_at'])) {
                        $action_text .= " com acesso premium até " . date('d/m/Y', strtotime($_POST['expires_at']));
                    }
                    logAdminAction($conn, $_SESSION['user_id'], "Admin $action_text");
                    
                    // Se for premium, tentar adicionar ao grupo VIP
                    if ($is_premium) {
                        // Lógica para adicionar ao grupo VIP ou enviar convite será implementada aqui
                    }
                } else {
                    $_SESSION['message'] = "Erro ao adicionar usuário do Telegram.";
                    $_SESSION['alert_type'] = "danger";
                }
            }
            
            header('Location: index.php');
            exit;
        }
        
        // Atualizar data de expiração
        if ($_POST['action'] == 'update_expiry') {
            $user_id = (int)$_POST['telegram_user_id'];
            $expires_at = $_POST['expires_at'] . ' 23:59:59';
            
            $stmt = $conn->prepare("UPDATE telegram_users SET premium_expires_at = ? WHERE id = ?");
            $stmt->bind_param("si", $expires_at, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Data de expiração atualizada com sucesso!";
                $_SESSION['alert_type'] = "success";
                
                $username = $_POST['username'] ?: 'Usuário #' . $user_id;
                logAdminAction($conn, $_SESSION['user_id'], "Admin atualizou a data de expiração do acesso premium para o usuário '$username' para " . date('d/m/Y', strtotime($_POST['expires_at'])));
            } else {
                $_SESSION['message'] = "Erro ao atualizar data de expiração.";
                $_SESSION['alert_type'] = "danger";
            }
            
            header('Location: index.php');
            exit;
        }
    }
}

// Verificar assinaturas expiradas
$check_expired = $conn->query("UPDATE telegram_users SET premium = 0 WHERE premium = 1 AND premium_expires_at IS NOT NULL AND premium_expires_at < NOW()");

// Paginação e filtros
$per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$premium_filter = isset($_GET['premium']) ? $_GET['premium'] : '';

// Construir query
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(user_id LIKE ? OR username LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= "ssss";
}

if ($status !== '') {
    $where_clauses[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($premium_filter !== '') {
    $where_clauses[] = "premium = ?";
    $params[] = $premium_filter;
    $types .= "i";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Contagem total para paginação
$count_sql = "SELECT COUNT(*) as total FROM telegram_users $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_users = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// Buscar usuários - Corrigindo a ordenação
$sql = "SELECT * FROM telegram_users $where_sql ORDER BY premium DESC, created_at DESC LIMIT $offset, $per_page";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Contagens para filtros
$total_count = getCountWhere($conn, 'telegram_users', "1");
$premium_count = getCountWhere($conn, 'telegram_users', "premium = 1");
$active_count = getCountWhere($conn, 'telegram_users', "status = 'active'");
$blocked_count = getCountWhere($conn, 'telegram_users', "status = 'blocked'");

// Verificar se existem grupos VIP para exibir opção de convite
$groups_result = $conn->query("SELECT * FROM telegram_groups WHERE type = 'premium' LIMIT 1");
$has_vip_groups = $groups_result->num_rows > 0;
if ($has_vip_groups) {
    $vip_group = $groups_result->fetch_assoc();
}

// Mensagens de Feedback
$message = isset($_SESSION['message']) ? $_SESSION['message'] : null;
$alert_type = isset($_SESSION['alert_type']) ? $_SESSION['alert_type'] : null;

// Limpar as mensagens da sessão
unset($_SESSION['message']);
unset($_SESSION['alert_type']);

// Definir título da página
$pageTitle = 'Gerenciar Usuários do Telegram';

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
        
        /* Status Badges */
        .badge-premium {
            background-color: rgba(133, 64, 245, 0.1);
            color: var(--purple-color);
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        .badge-regular {
            background-color: rgba(54, 185, 204, 0.1);
            color: var(--info-color);
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        .badge-active {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        .badge-blocked {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        .badge-expiring {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        /* Botões */
        .btn-telegram {
            background-color: var(--telegram-color);
            color: white;
        }
        
        .btn-telegram:hover {
            background-color: #0077b5;
            color: white;
        }
        
        .btn-purple {
            background-color: var(--purple-color);
            color: white;
        }
        
        .btn-purple:hover {
            background-color: #7030e3;
            color: white;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .premium-icon {
            color: var(--purple-color);
            margin-left: 0.25rem;
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
                        <h1 class="h3 mb-0 text-gray-800">Gerenciar Usuários do Telegram</h1>
                        
                       <a href="add_vip.php" class="btn btn-primary">
    <i class="fas fa-plus"></i> Adicionar Usuário VIP
</a>
                            <i class="fas fa-plus"></i> Adicionar Usuário
                        </button>
                    </div>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Filtros -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Filtrar Usuários</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6 mb-2">
                                    <a href="index.php" class="btn <?php echo empty($premium_filter) && empty($status) ? 'btn-primary' : 'btn-outline-secondary'; ?> me-2">
                                        Todos (<?php echo $total_count; ?>)
                                    </a>
                                    <a href="index.php?premium=1" class="btn <?php echo $premium_filter === '1' ? 'btn-primary' : 'btn-outline-secondary'; ?> me-2">
                                        <i class="fas fa-crown"></i> Premium (<?php echo $premium_count; ?>)
                                    </a>
                                    <a href="index.php?status=active" class="btn <?php echo $status === 'active' ? 'btn-primary' : 'btn-outline-secondary'; ?> me-2">
                                        <i class="fas fa-check-circle"></i> Ativos (<?php echo $active_count; ?>)
                                    </a>
                                    <a href="index.php?status=blocked" class="btn <?php echo $status === 'blocked' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                        <i class="fas fa-ban"></i> Bloqueados (<?php echo $blocked_count; ?>)
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <form action="index.php" method="get">
                                        <?php if (!empty($premium_filter)): ?>
                                        <input type="hidden" name="premium" value="<?php echo $premium_filter; ?>">
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($status)): ?>
                                        <input type="hidden" name="status" value="<?php echo $status; ?>">
                                        <?php endif; ?>
                                        
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="search" placeholder="Buscar por ID, nome ou username..." value="<?php echo htmlspecialchars($search); ?>">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fas fa-search fa-sm"></i>
                                            </button>
                                            <?php if (!empty($search)): ?>
                                            <a href="<?php echo 'index.php' . (!empty($premium_filter) ? "?premium=$premium_filter" : '') . (!empty($status) ? (!empty($premium_filter) ? "&" : "?") . "status=$status" : ''); ?>" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista de Usuários -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Usuários do Telegram</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID Telegram</th>
                                            <th>Username</th>
                                            <th>Nome</th>
                                            <th>Status</th>
                                            <th>Premium</th>
                                            <th>Validade</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($users->num_rows > 0): ?>
                                            <?php while ($user = $users->fetch_assoc()): 
                                                $is_expiring = false;
                                                $days_left = 0;
                                                
                                                if ($user['premium'] && isset($user['premium_expires_at']) && $user['premium_expires_at']) {
                                                    $expiry_date = new DateTime($user['premium_expires_at']);
                                                    $today = new DateTime();
                                                    $interval = $today->diff($expiry_date);
                                                    $days_left = $interval->days;
                                                    $is_expiring = $days_left <= 7 && $expiry_date > $today; 
                                                }
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                                    <td>
                                                        <?php if ($user['username']): ?>
                                                            <a href="https://t.me/<?php echo htmlspecialchars($user['username']); ?>" target="_blank">
                                                                @<?php echo htmlspecialchars($user['username']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sem username</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
                                                            echo $full_name ? htmlspecialchars($full_name) : '<span class="text-muted">Nome não informado</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['status'] == 'active'): ?>
                                                            <span class="badge badge-active">Ativo</span>
                                                        <?php elseif ($user['status'] == 'blocked'): ?>
                                                            <span class="badge badge-blocked">Bloqueado</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary text-white"><?php echo ucfirst($user['status']); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['premium']): ?>
                                                            <span class="badge badge-premium">
                                                                <i class="fas fa-crown"></i> Premium
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-regular">Regular</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['premium'] && isset($user['premium_expires_at']) && $user['premium_expires_at']): ?>
                                                            <?php if ($is_expiring): ?>
                                                                <span class="badge badge-expiring">
                                                                    <i class="fas fa-clock"></i>
                                                                    <?php echo date('d/m/Y', strtotime($user['premium_expires_at'])); ?>
                                                                    (<?php echo $days_left; ?> dias)
                                                                </span>
                                                            <?php else: ?>
                                                                <?php echo date('d/m/Y', strtotime($user['premium_expires_at'])); ?>
                                                            <?php endif; ?>
                                                        <?php elseif ($user['premium']): ?>
                                                            <span class="text-muted">Sem data de expiração</span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="fas fa-cog"></i>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <?php if ($user['premium']): ?>
                                                                    <!-- Renovar Premium -->
                                                                    <li>
                                                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#updateExpiryModal<?php echo $user['id']; ?>">
                                                                            <i class="fas fa-calendar-plus me-2 text-primary"></i> Atualizar Validade
                                                                        </button>
                                                                    </li>
                                                                    <!-- Remover Premium -->
                                                                    <li>
                                                                        <form method="post" class="d-inline">
                                                                            <input type="hidden" name="action" value="toggle_premium">
                                                                            <input type="hidden" name="telegram_user_id" value="<?php echo $user['id']; ?>">
                                                                            <input type="hidden" name="current_premium" value="1">
                                                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username'] ?: $full_name); ?>">
                                                                            <button type="submit" class="dropdown-item">
                                                                                <i class="fas fa-times-circle me-2 text-danger"></i> Remover VIP
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php else: ?>
                                                                    <!-- Dar acesso Premium -->
                                                                    <li>
                                                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#grantPremiumModal<?php echo $user['id']; ?>">
                                                                            <i class="fas fa-crown me-2 text-warning"></i> Dar Acesso VIP
                                                                        </button>
                                                                    </li>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Outras ações -->
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewUserModal<?php echo $user['id']; ?>">
                                                                        <i class="fas fa-eye me-2 text-info"></i> Ver Detalhes
                                                                    </button>
                                                                </li>
                                                                <?php if ($user['username']): ?>
                                                                <li>
                                                                    <a href="https://t.me/<?php echo htmlspecialchars($user['username']); ?>" target="_blank" class="dropdown-item">
                                                                        <i class="fab fa-telegram me-2 text-primary"></i> Abrir no Telegram
                                                                    </a>
                                                                </li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <i class="fas fa-search fa-2x mb-3 text-gray-300"></i>
                                                    <p class="mb-0">Nenhum usuário encontrado</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginação -->
                            <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    Mostrando <?php echo min(($page - 1) * $per_page + 1, $total_users); ?> a <?php echo min($page * $per_page, $total_users); ?> de <?php echo $total_users; ?> usuários
                                </div>
                                
                                <nav aria-label="Paginação">
                                    <ul class="pagination">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($premium_filter) ? '&premium=' . $premium_filter : ''; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?>">
                                                <i class="fas fa-angle-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($start_page + 4, $total_pages);
                                        
                                        if ($end_page - $start_page < 4 && $start_page > 1) {
                                            $start_page = max(1, $end_page - 4);
                                        }
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($premium_filter) ? '&premium=' . $premium_filter : ''; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($premium_filter) ? '&premium=' . $premium_filter : ''; ?><?php echo !empty($status) ? '&status=' . $status : ''; ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Adicionar Usuário -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Usuário do Telegram</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        
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
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="is_premium" name="is_premium">
                            <label class="form-check-label" for="is_premium">Conceder Acesso VIP</label>
                        </div>
                        
                        <div id="premium-options" class="mb-3 ps-4 border-start" style="display: none;">
                            <div class="mb-3">
                                <label for="expires_at" class="form-label">Data de Validade</label>
                                <input type="text" class="form-control datepicker" id="expires_at" name="expires_at" placeholder="Selecione uma data">
                                <div class="form-text">Data de expiração do acesso VIP. Deixe em branco para acesso sem prazo.</div>
                            </div>
                            
                            <!-- Opções para adicionar automaticamente ao grupo -->
                            <?php if ($has_vip_groups): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="add_to_group" name="add_to_group" value="1">
                                <label class="form-check-label" for="add_to_group">
                                    Adicionar ao grupo VIP automaticamente
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Como obter o ID do Telegram: Envie uma mensagem para @userinfobot e ele retornará o ID do usuário.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modais para cada usuário -->
    <?php 
    if ($users->num_rows > 0) {
        $users->data_seek(0);
        while ($user = $users->fetch_assoc()): 
            $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
            $display_name = $full_name ?: ($user['username'] ? '@' . $user['username'] : 'Usuário #' . $user['user_id']);
    ?>
    <!-- Modal para visualizar detalhes do usuário -->
    <div class="modal fade" id="viewUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $user['premium'] ? '<i class="fas fa-crown premium-icon"></i> ' : ''; ?>
                        <?php echo htmlspecialchars($display_name); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center mb-4">
                        <div class="bg-light rounded-circle p-3 me-3">
                            <i class="fas fa-user fa-2x text-secondary"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">
                                <?php echo htmlspecialchars($full_name ?: 'Nome não informado'); ?>
                            </h6>
                            <?php if ($user['username']): ?>
                            <a href="https://t.me/<?php echo htmlspecialchars($user['username']); ?>" target="_blank" class="text-decoration-none">
                                @<?php echo htmlspecialchars($user['username']); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="list-group mb-3">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>ID do Telegram</span>
                            <span class="fw-bold"><?php echo htmlspecialchars($user['user_id']); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Status</span>
                            <span>
                                <?php if ($user['status'] == 'active'): ?>
                                    <span class="badge badge-active">Ativo</span>
                                <?php elseif ($user['status'] == 'blocked'): ?>
                                    <span class="badge badge-blocked">Bloqueado</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-white"><?php echo ucfirst($user['status']); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Acesso VIP</span>
                            <span>
                                <?php if ($user['premium']): ?>
                                    <span class="badge badge-premium"><i class="fas fa-crown"></i> Premium</span>
                                <?php else: ?>
                                    <span class="badge badge-regular">Regular</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($user['premium'] && isset($user['premium_expires_at']) && $user['premium_expires_at']): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Data de Expiração</span>
                            <span>
                                <?php 
                                    $expiry_date = new DateTime($user['premium_expires_at']);
                                    $today = new DateTime();
                                    $interval = $today->diff($expiry_date);
                                    $days_left = $interval->days;
                                    $is_expired = $today > $expiry_date;
                                    
                                    if ($is_expired) {
                                        echo '<span class="badge badge-blocked">Expirado</span>';
                                    } else if ($days_left <= 7) {
                                        echo '<span class="badge badge-expiring">Expira em ' . $days_left . ' dias</span>';
                                    } else {
                                        echo date('d/m/Y', strtotime($user['premium_expires_at']));
                                    }
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Cadastrado em</span>
                            <span><?php echo isset($user['created_at']) ? date('d/m/Y H:i', strtotime($user['created_at'])) : 'N/A'; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Última atividade</span>
                            <span>
                                <?php echo isset($user['last_activity']) && $user['last_activity'] ? date('d/m/Y H:i', strtotime($user['last_activity'])) : 'Nunca'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="https://t.me/<?php echo htmlspecialchars($user['username'] ?: ''); ?>" target="_blank" class="btn btn-telegram" <?php echo !$user['username'] ? 'disabled' : ''; ?>>
                        <i class="fab fa-telegram"></i> Abrir no Telegram
                    </a>
                    
                    <?php if ($user['premium']): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateExpiryModal<?php echo $user['id']; ?>" data-bs-dismiss="modal">
                            <i class="fas fa-calendar-plus"></i> Atualizar Validade
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#grantPremiumModal<?php echo $user['id']; ?>" data-bs-dismiss="modal">
                            <i class="fas fa-crown"></i> Dar Acesso VIP
                        </button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para dar acesso VIP -->
    <div class="modal fade" id="grantPremiumModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dar Acesso VIP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="toggle_premium">
                        <input type="hidden" name="telegram_user_id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="current_premium" value="0">
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username'] ?: $display_name); ?>">
                        
                        <p>Conceder acesso VIP para <strong><?php echo htmlspecialchars($display_name); ?></strong>?</p>
                        
                        <div class="mb-3">
                                                       <label for="expires_at_<?php echo $user['id']; ?>" class="form-label">Data de Validade</label>
                            <input type="text" class="form-control datepicker" id="expires_at_<?php echo $user['id']; ?>" name="expires_at" placeholder="Selecione uma data">
                            <div class="form-text">Deixe em branco para acesso sem prazo de expiração.</div>
                        </div>
                        
                        <!-- Opções para adicionar automaticamente ao grupo -->
                        <?php if ($has_vip_groups): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="add_to_group_<?php echo $user['id']; ?>" name="add_to_group" value="1">
                            <label class="form-check-label" for="add_to_group_<?php echo $user['id']; ?>">
                                Adicionar ao grupo VIP automaticamente
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-purple">Conceder Acesso</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para atualizar validade do acesso premium -->
    <div class="modal fade" id="updateExpiryModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Atualizar Validade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_expiry">
                        <input type="hidden" name="telegram_user_id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username'] ?: $display_name); ?>">
                        
                        <p>Atualizar data de validade do acesso VIP para <strong><?php echo htmlspecialchars($display_name); ?></strong>.</p>
                        
                        <div class="mb-3">
                            <label for="new_expires_at_<?php echo $user['id']; ?>" class="form-label">Nova Data de Validade</label>
                            <input type="text" class="form-control datepicker" id="new_expires_at_<?php echo $user['id']; ?>" name="expires_at" 
                                value="<?php echo isset($user['premium_expires_at']) ? date('Y-m-d', strtotime($user['premium_expires_at'])) : ''; ?>" required>
                            <div class="form-text">A nova data de expiração do acesso VIP.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endwhile; } ?>
    
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
        
        // Mostrar/esconder opções premium no modal de adicionar usuário
        var isPremiumCheckbox = document.getElementById('is_premium');
        var premiumOptions = document.getElementById('premium-options');
        
        if (isPremiumCheckbox && premiumOptions) {
            isPremiumCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    premiumOptions.style.display = 'block';
                } else {
                    premiumOptions.style.display = 'none';
                }
            });
        }
    });
    </script>
</body>
</html>