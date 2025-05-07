<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Verificar se foi fornecido um ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_GET['id'];

// Obter dados do usuário
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Usuário não encontrado ou excluído
    $_SESSION['message'] = "Usuário não encontrado ou foi removido.";
    $_SESSION['alert_type'] = "warning";
    header('Location: index.php');
    exit;
}

$user = $result->fetch_assoc();

// Obter bots associados ao usuário
$has_user_bot_access = false;
$table_check = $conn->query("SHOW TABLES LIKE 'user_bot_access'");
if ($table_check && $table_check->num_rows > 0) {
    $has_user_bot_access = true;
}

if ($has_user_bot_access) {
    $bots_query = "SELECT b.* FROM bots b 
                   LEFT JOIN user_bot_access uba ON uba.bot_id = b.id 
                   WHERE b.created_by = ? OR uba.user_id = ?
                   ORDER BY b.name";
} else {
    $bots_query = "SELECT * FROM bots WHERE created_by = ? ORDER BY name";
}

$bots_stmt = $conn->prepare($bots_query);
if ($has_user_bot_access) {
    $bots_stmt->bind_param("ii", $user_id, $user_id);
} else {
    $bots_stmt->bind_param("i", $user_id);
}
$bots_stmt->execute();
$bots_result = $bots_stmt->get_result();

// Verificar histórico de login
$has_login_history = false;
$login_data = array();

// Verificar se a tabela login_history existe
$table_check = $conn->query("SHOW TABLES LIKE 'login_history'");
if ($table_check && $table_check->num_rows > 0) {
    // A tabela existe, então podemos buscar os dados
    $has_login_history = true;
    $login_query = "SELECT * FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT 10";
    $login_stmt = $conn->prepare($login_query);
    $login_stmt->bind_param("i", $user_id);
    $login_stmt->execute();
    $login_result = $login_stmt->get_result();
    
    if ($login_result && $login_result->num_rows > 0) {
        while ($row = $login_result->fetch_assoc()) {
            $login_data[] = $row;
        }
    }
}

// Definir título da página
$pageTitle = 'Visualizar Usuário';

// Obter configurações do site
$siteName = getSetting($conn, 'site_name');
if (!$siteName) {
    $siteName = 'BotDeSinais';
}
$siteLogo = getSetting($conn, 'site_logo');
if (!$siteLogo) {
    $siteLogo = 'logo.png';
}

// Função para determinar o status do usuário
function getUserStatus($user) {
    if ($user['role'] == 'master') {
        return ['badge-status-master', 'Master'];
    }
    
    if ($user['status'] == 'suspended') {
        return ['badge-status-suspended', 'Suspenso'];
    }
    
    // Verificar se a função daysRemaining existe
    if (function_exists('daysRemaining')) {
        $days_remaining = daysRemaining($user['expiry_date']);
        if ($days_remaining <= 0) {
            return ['badge-status-expired', 'Expirado'];
        }
    } else {
        // Implementação simplificada se a função não existir
        if (!empty($user['expiry_date']) && strtotime($user['expiry_date']) < time()) {
            return ['badge-status-expired', 'Expirado'];
        }
    }
    
    return ['badge-status-active', 'Ativo'];
}

// Obter status
list($status_class, $status_text) = getUserStatus($user);

// Obter dados do criador (se houver)
$creator_info = '-';
if (!empty($user['created_by'])) {
    $creator_stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
    $creator_stmt->bind_param("i", $user['created_by']);
    $creator_stmt->execute();
    $creator_result = $creator_stmt->get_result();
    if ($creator_result->num_rows > 0) {
        $creator = $creator_result->fetch_assoc();
        $creator_info = $creator['username'] . ' (' . ucfirst($creator['role']) . ')';
    }
}

// Obter número de bots acessados
$bot_count = $bots_result ? $bots_result->num_rows : 0;
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
        
        /* Cards de informações */
        .info-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            padding: 1.5rem;
        }
        
        .user-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1.5rem;
        }
        
        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #5a5c69;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }
        
        .user-info .meta {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 0.8rem;
        }
        
        /* Badges de Status */
        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        .badge-status-active {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        .badge-status-suspended {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }
        
        .badge-status-expired {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
        }
        
        .badge-status-master {
            background-color: rgba(133, 64, 245, 0.1);
            color: var(--purple-color);
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .info-group {
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            border-bottom: 1px solid #eaecf4;
            padding: 0.8rem 0;
            display: flex;
            flex-wrap: wrap;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            flex: 0 0 35%;
            max-width: 35%;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .info-value {
            flex: 0 0 65%;
            max-width: 65%;
            color: #6c757d;
        }
        
        .bot-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .bot-list-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #eaecf4;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .bot-list-item:last-child {
            border-bottom: none;
        }
        
        .bot-name {
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .bot-name i {
            margin-right: 0.5rem;
            color: var(--success-color);
        }
        
        /* Tabelas de Login History */
        .login-table th {
            background-color: #f8f9fc;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .action-buttons .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
        }
        
        /* Estilos para botões de ação coloridos */
        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: none;
            color: white;
            margin-right: 0.25rem;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            filter: brightness(1.1);
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
            
            .info-label,
            .info-value {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .info-label {
                margin-bottom: 0.25rem;
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
                    <a href="index.php" class="active">
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
                    <a href="../games/">
                        <i class="fas fa-gamepad" style="color: var(--warning-color);"></i>
                        <span>Jogos</span>
                    </a>
                </li>
                <li>
                    <a href="../platforms/">
                        <i class="fas fa-desktop" style="color: var(--info-color);"></i>
                        <span>Plataformas</span>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Usuários</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Visualizar Usuário</li>
                        </ol>
                    </nav>
                    
                    <a href="index.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Voltar
                    </a>
                </div>
                
                <!-- Card Principal -->
                <div class="info-card">
                    <div class="user-header">
                        <div class="user-avatar">
                            <?php if (empty($user['avatar'])): ?>
                                <i class="fas fa-user"></i>
                            <?php else: ?>
                                <img src="../../assets/img/avatars/<?php echo $user['avatar']; ?>" alt="Avatar">
                            <?php endif; ?>
                        </div>
                        
                        <div class="user-info">
                            <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                            <div class="meta">
                                <?php if (!empty($user['full_name'])): ?>
                                    <i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($user['full_name']); ?> &bull; 
                                <?php endif; ?>
                                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user['email'] ?: '-'); ?>
                            </div>
                            
                            <div>
                                <span class="badge-status <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                                <span class="badge bg-light text-dark ms-2">
                                    ID: <?php echo $user['id']; ?>
                                </span>
                                <span class="badge bg-light text-dark ms-2">
                                    <i class="fas fa-user-tag me-1"></i> <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Botões de Ação -->
                        <div class="ms-auto action-buttons">
                            <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-1"></i> Editar
                            </a>
                            <?php if ($user['status'] == 'active'): ?>
                                <button type="button" class="btn btn-warning" 
                                    data-bs-toggle="modal" data-bs-target="#toggleStatusModal"
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-status="<?php echo $user['status']; ?>"
                                    data-action="suspender">
                                    <i class="fas fa-ban me-1"></i> Suspender
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-success" 
                                    data-bs-toggle="modal" data-bs-target="#toggleStatusModal"
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-status="<?php echo $user['status']; ?>"
                                    data-action="ativar">
                                    <i class="fas fa-check me-1"></i> Ativar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Coluna da Esquerda - Informações Básicas -->
                        <div class="col-lg-6">
                            <h5 class="section-title">
                                <i class="fas fa-id-card"></i> Informações do Usuário
                            </h5>
                            
                            <div class="info-group">
                                <div class="info-item">
                                    <div class="info-label">Nome Completo</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['full_name'] ?: '-'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Nome de Usuário</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['email'] ?: '-'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Telefone</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Tipo de Conta</div>
                                    <div class="info-value"><?php echo ucfirst($user['role']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Status</div>
                                    <div class="info-value">
                                        <span class="badge-status <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="section-title">
                                <i class="fas fa-calendar-alt"></i> Datas Importantes
                            </h5>
                            
                            <div class="info-group">
                                <div class="info-item">
                                    <div class="info-label">Cadastrado em</div>
                                    <div class="info-value">
                                        <?php 
                                        if (function_exists('formatDate')) {
                                            echo formatDate($user['created_at'], true); 
                                        } else {
                                            echo date('d/m/Y H:i', strtotime($user['created_at']));
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Cadastrado por</div>
                                    <div class="info-value"><?php echo $creator_info; ?></div>
                                </div>
                                <!-- MODIFICAÇÃO AQUI: Agora exibe a data de validade para TODOS os usuários -->
                                <div class="info-item">
                                    <div class="info-label">Expira em</div>
                                    <div class="info-value">
                                        <?php 
                                        if (!empty($user['expiry_date'])) {
                                            if (function_exists('formatDate')) {
                                                echo formatDate($user['expiry_date']);
                                            } else {
                                                echo date('d/m/Y', strtotime($user['expiry_date']));
                                            }
                                            
                                            if (function_exists('daysRemaining')) {
                                                $days_remaining = daysRemaining($user['expiry_date']);
                                                if ($days_remaining > 0 && $user['status'] == 'active') {
                                                    echo ' <span class="badge ' . ($days_remaining <= 7 ? 'bg-warning text-dark' : 'bg-success') . '">' . $days_remaining . ' dias</span>';
                                                } elseif ($days_remaining <= 0) {
                                                    echo ' <span class="badge bg-danger">Expirado</span>';
                                                }
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Último acesso</div>
                                    <div class="info-value">
                                        <?php
                                        if (!empty($user['last_login'])) {
                                            if (function_exists('formatDate')) {
                                                echo formatDate($user['last_login'], true);
                                            } else {
                                                echo date('d/m/Y H:i', strtotime($user['last_login']));
                                            } 
                                        } else {
                                            echo 'Nunca acessou';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Coluna da Direita - Bots e Histórico -->
                        <div class="col-lg-6">
                            <h5 class="section-title">
                                <i class="fas fa-robot"></i> Bots Acessíveis (<?php echo $bot_count; ?>)
                            </h5>
                            
                            <?php if ($bots_result && $bots_result->num_rows > 0): ?>
                                <div class="info-group">
                                    <ul class="bot-list">
                                        <?php while ($bot = $bots_result->fetch_assoc()): ?>
                                            <li class="bot-list-item">
                                                <span class="bot-name">
                                                    <i class="fas fa-robot"></i>
                                                    <?php echo htmlspecialchars($bot['name']); ?>
                                                </span>
                                                <a href="../bots/view.php?id=<?php echo $bot['id']; ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> Este usuário não tem acesso a nenhum bot.
                                </div>
                            <?php endif; ?>
                            
                            <!-- Histórico de Acessos -->
                            <h5 class="section-title mt-4">
                                <i class="fas fa-clock"></i> Histórico de Acessos
                            </h5>
                            
                            <?php if ($has_login_history && count($login_data) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Data/Hora</th>
                                                <th>Endereço IP</th>
                                                <th>Dispositivo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($login_data as $login): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y H:i:s', strtotime($login['login_time'])); ?></td>
                                                <td><?php echo $login['ip_address']; ?></td>
                                                <td><?php echo $login['user_agent']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Nenhum histórico de acesso disponível para este usuário.
                                </div>
                            <?php endif; ?>
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
    
    <!-- Modal de Confirmação para Ativar/Suspender -->
    <div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="statusIconBox" class="icon-box icon-warning mb-4 mx-auto" style="width: 80px; height: 80px; background-color: var(--warning-color); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-question-circle" style="font-size: 2.5rem; color: white;"></i>
                    </div>
                    <h5 class="modal-title mb-3" id="toggleStatusTitle">Confirmar Ação</h5>
                    <p id="toggleStatusMessage">Tem certeza que deseja alterar o status deste usuário?</p>
                    <form method="post" action="index.php">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" id="toggleStatusUserId">
                        <input type="hidden" name="username" id="toggleStatusUsername">
                        <input type="hidden" name="current_status" id="toggleStatusCurrentStatus">
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-warning" id="toggleStatusBtn">Confirmar</button>
                        </div>
                    </form>
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
        
        // Configurações para o modal de alteração de status
        var toggleStatusModal = document.getElementById('toggleStatusModal');
        if (toggleStatusModal) {
            toggleStatusModal.addEventListener('show.bs.modal', function (event) {
                // Botão que disparou o modal
                var button = event.relatedTarget;
                var userId = button.getAttribute('data-user-id');
                var username = button.getAttribute('data-username');
                var currentStatus = button.getAttribute('data-status');
                var action = button.getAttribute('data-action');
                
                // Atualiza o modal
                var title = document.getElementById('toggleStatusTitle');
                var message = document.getElementById('toggleStatusMessage');
                var iconBox = document.getElementById('statusIconBox');
                var confirmBtn = document.getElementById('toggleStatusBtn');
                
                // Preenche os campos do formulário
                document.getElementById('toggleStatusUserId').value = userId;
                document.getElementById('toggleStatusUsername').value = username;
                document.getElementById('toggleStatusCurrentStatus').value = currentStatus;
                
                // Personaliza com base na ação
                if (action === 'ativar') {
                    title.textContent = 'Ativar Usuário';
                    message.innerHTML = 'Deseja ativar o usuário <strong>' + username + '</strong>?';
                    iconBox.style.backgroundColor = 'var(--success-color)';
                    iconBox.innerHTML = '<i class="fas fa-check-circle" style="font-size: 2.5rem; color: white;"></i>';
                    confirmBtn.className = 'btn btn-success';
                    confirmBtn.textContent = 'Ativar';
                } else {
                    title.textContent = 'Suspender Usuário';
                    message.innerHTML = 'Deseja suspender o usuário <strong>' + username + '</strong>?';
                    iconBox.style.backgroundColor = 'var(--warning-color)';
                    iconBox.innerHTML = '<i class="fas fa-ban" style="font-size: 2.5rem; color: white;"></i>';
                    confirmBtn.className = 'btn btn-warning';
                    confirmBtn.textContent = 'Suspender';
                }
            });
        }
    });
    </script>
</body>
</html>