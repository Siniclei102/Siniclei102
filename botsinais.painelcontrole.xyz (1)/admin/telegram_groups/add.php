<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Verificar se é edição
$isEditing = false;
$group_id = null;
$group = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $group_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM telegram_groups WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $group = $result->fetch_assoc();
        $isEditing = true;
    } else {
        $_SESSION['message'] = "Grupo não encontrado.";
        $_SESSION['alert_type'] = "danger";
        header('Location: index.php');
        exit;
    }
}

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $group_id_telegram = trim($_POST['group_id_telegram']);
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type']; // pg_soft, pragmatic
    $level = $_POST['level']; // vip, comum
    $signal_frequency = (int)$_POST['signal_frequency'];
    $min_minutes = (int)$_POST['min_minutes'];
    $max_minutes = (int)$_POST['max_minutes'];
    $status = isset($_POST['status']) ? 'active' : 'inactive';
    
    // Validação básica
    if (empty($name) || empty($group_id_telegram) || empty($type) || empty($level)) {
        $_SESSION['message'] = "Todos os campos obrigatórios devem ser preenchidos.";
        $_SESSION['alert_type'] = "danger";
    } else {
        // Se é premium/VIP
        $premium = ($level == 'vip') ? 1 : 0;
        
        if ($isEditing) {
            // Atualizar grupo existente
            $stmt = $conn->prepare("UPDATE telegram_groups SET name = ?, group_id = ?, description = ?, type = ?, 
                                    level = ?, premium = ?, signal_frequency = ?, min_minutes = ?, max_minutes = ?, 
                                    status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssssiiissi", $name, $group_id_telegram, $description, $type, $level, $premium, 
                             $signal_frequency, $min_minutes, $max_minutes, $status, $group_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Grupo atualizado com sucesso!";
                $_SESSION['alert_type'] = "success";
                
                // Log da ação
                logAdminAction($conn, $_SESSION['user_id'], "Admin atualizou o grupo do Telegram '$name' (ID: $group_id)");
                
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['message'] = "Erro ao atualizar grupo: " . $conn->error;
                $_SESSION['alert_type'] = "danger";
            }
        } else {
            // Inserir novo grupo
            $stmt = $conn->prepare("INSERT INTO telegram_groups (name, group_id, description, type, level, premium, 
                                   signal_frequency, min_minutes, max_minutes, status, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssiiissi", $name, $group_id_telegram, $description, $type, $level, $premium, 
                             $signal_frequency, $min_minutes, $max_minutes, $status, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Grupo adicionado com sucesso!";
                $_SESSION['alert_type'] = "success";
                
                // Log da ação
                logAdminAction($conn, $_SESSION['user_id'], "Admin adicionou o grupo do Telegram '$name'");
                
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['message'] = "Erro ao adicionar grupo: " . $conn->error;
                $_SESSION['alert_type'] = "danger";
            }
        }
    }
}

// Verificar se existe a coluna 'level' na tabela telegram_groups
$check_level = $conn->query("SHOW COLUMNS FROM telegram_groups LIKE 'level'");
if ($check_level->num_rows == 0) {
    // Adicionar a coluna level se não existir
    $conn->query("ALTER TABLE telegram_groups ADD COLUMN level ENUM('vip', 'comum') DEFAULT 'comum' AFTER type");
}

// Verificar se existe a coluna 'signal_frequency'
$check_frequency = $conn->query("SHOW COLUMNS FROM telegram_groups LIKE 'signal_frequency'");
if ($check_frequency->num_rows == 0) {
    // Adicionar a coluna signal_frequency se não existir
    $conn->query("ALTER TABLE telegram_groups ADD COLUMN signal_frequency INT DEFAULT 30 AFTER level");
}

// Verificar se existem as colunas min_minutes e max_minutes
$check_min = $conn->query("SHOW COLUMNS FROM telegram_groups LIKE 'min_minutes'");
if ($check_min->num_rows == 0) {
    // Adicionar a coluna min_minutes se não existir
    $conn->query("ALTER TABLE telegram_groups ADD COLUMN min_minutes INT DEFAULT 3 AFTER signal_frequency");
}

$check_max = $conn->query("SHOW COLUMNS FROM telegram_groups LIKE 'max_minutes'");
if ($check_max->num_rows == 0) {
    // Adicionar a coluna max_minutes se não existir
    $conn->query("ALTER TABLE telegram_groups ADD COLUMN max_minutes INT DEFAULT 10 AFTER min_minutes");
}

// Definir título da página
$pageTitle = $isEditing ? 'Editar Grupo do Telegram' : 'Adicionar Grupo do Telegram';

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
        
        /* Formulário */
        .form-section {
            padding: 1rem;
            border-left: 4px solid var(--primary-color);
            background-color: rgba(78, 115, 223, 0.05);
            border-radius: 0.35rem;
            margin-bottom: 1.5rem;
        }
        
        .pg-soft-section {
            border-color: var(--purple-color);
            background-color: rgba(133, 64, 245, 0.05);
        }
        
        .pragmatic-section {
            border-color: var(--info-color);
            background-color: rgba(54, 185, 204, 0.05);
        }
        
        .vip-section {
            border-color: var(--warning-color);
            background-color: rgba(246, 194, 62, 0.05);
        }
        
        .comum-section {
            border-color: var(--success-color);
            background-color: rgba(28, 200, 138, 0.05);
        }
        
        .form-label {
            font-weight: 600;
        }
        
        .form-check-label {
            font-weight: 600;
        }
        
        .form-text {
            color: #858796;
        }
        
        .form-range::-webkit-slider-thumb {
            background-color: var(--primary-color);
        }
        
        .range-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
        }
        
        .range-value {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        /* Tipo e Nível */
        .type-level-selector {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .type-card {
            border: 2px solid transparent;
            border-radius: 0.75rem;
            padding: 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.05);
        }
        
        .pg-soft-card {
            border-color: rgba(133, 64, 245, 0.1);
        }
        
        .pg-soft-card.selected {
            border-color: var(--purple-color);
            background-color: rgba(133, 64, 245, 0.1);
        }
        
        .pragmatic-card {
            border-color: rgba(54, 185, 204, 0.1);
        }
        
        .pragmatic-card.selected {
            border-color: var(--info-color);
            background-color: rgba(54, 185, 204, 0.1);
        }
        
        .vip-card {
            border-color: rgba(246, 194, 62, 0.1);
        }
        
        .vip-card.selected {
            border-color: var(--warning-color);
            background-color: rgba(246, 194, 62, 0.1);
        }
        
        .comum-card {
            border-color: rgba(28, 200, 138, 0.1);
        }
        
        .comum-card.selected {
            border-color: var(--success-color);
            background-color: rgba(28, 200, 138, 0.1);
        }
        
        .type-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .pg-soft-icon {
            color: var(--purple-color);
        }
        
        .pragmatic-icon {
            color: var(--info-color);
        }
        
        .vip-icon {
            color: var(--warning-color);
        }
        
        .comum-icon {
            color: var(--success-color);
        }
        
        .type-title {
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .type-description {
            color: #858796;
            font-size: 0.85rem;
            margin-top: 0.5rem;
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
            
            .type-level-selector {
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
                    <a href="index.php" class="active">
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
                    <!-- Cabeçalho da página -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Grupos</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo $isEditing ? 'Editar Grupo' : 'Adicionar Grupo'; ?></li>
                            </ol>
                        </nav>
                        
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                    
                    <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['alert_type']); endif; ?>
                    
                    <div class="card mb-4">
                        <div class="card-header d-flex align-items-center">
                            <i class="fas fa-users me-2" style="color: #0088cc;"></i>
                            <h6 class="m-0 font-weight-bold"><?php echo $isEditing ? 'Editar Grupo do Telegram' : 'Adicionar Grupo do Telegram'; ?></h6>
                        </div>
                        <div class="card-body">
                            <form method="post" id="groupForm">
                                <!-- Informações básicas do grupo -->
                                <div class="form-section mb-4">
                                    <h5 class="mb-3">Informações Básicas</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Nome do Grupo*</label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?php echo isset($group) ? htmlspecialchars($group['name']) : ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="group_id_telegram" class="form-label">ID do Grupo no Telegram*</label>
                                                <input type="text" class="form-control" id="group_id_telegram" name="group_id_telegram" 
                                                       value="<?php echo isset($group) ? htmlspecialchars($group['group_id']) : ''; ?>" required>
                                                <div class="form-text">Formato: -1001234567890</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Descrição</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($group) ? htmlspecialchars($group['description']) : ''; ?></textarea>
                                        <div class="form-text">Uma breve descrição sobre este grupo (opcional)</div>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="status" name="status" 
                                               <?php echo (!isset($group) || (isset($group) && $group['status'] == 'active')) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="status">Grupo Ativo</label>
                                        <div class="form-text">Desative para pausar o envio de sinais para este grupo</div>
                                    </div>
                                </div>
                                
                                <!-- Seleção de Tipo (PG Soft ou Pragmatic) -->
                                <div class="form-section mb-4">
                                    <h5 class="mb-3">Tipo de Sinais</h5>
                                    <div class="mb-3">
                                        <p>Selecione o tipo de sinais que este grupo deve receber:</p>
                                    </div>
                                    
                                    <div class="type-level-selector">
                                        <!-- PG Soft -->
                                        <div class="type-card pg-soft-card <?php echo isset($group) && $group['type'] == 'pg_soft' ? 'selected' : ''; ?>" id="pg_soft_card">
                                            <div class="type-icon pg-soft-icon">
                                                <i class="fas fa-dice"></i>
                                            </div>
                                            <div class="type-title">PG Soft</div>
                                            <div class="type-description">Sinais exclusivos de jogos PG Soft</div>
                                        </div>
                                        
                                        <!-- Pragmatic -->
                                        <div class="type-card pragmatic-card <?php echo isset($group) && $group['type'] == 'pragmatic' ? 'selected' : ''; ?>" id="pragmatic_card">
                                            <div class="type-icon pragmatic-icon">
                                                <i class="fas fa-gamepad"></i>
                                            </div>
                                            <div class="type-title">Pragmatic</div>
                                            <div class="type-description">Sinais exclusivos de jogos Pragmatic</div>
                                        </div>
                                    </div>
                                    
                                    <input type="hidden" name="type" id="type_input" value="<?php echo isset($group) ? $group['type'] : 'pg_soft'; ?>" required>
                                </div>
                                
                                <!-- Seleção de Nível (VIP ou Comum) -->
                                <div class="form-section mb-4">
                                    <h5 class="mb-3">Nível do Grupo</h5>
                                    <div class="mb-3">
                                        <p>Selecione o nível deste grupo:</p>
                                    </div>
                                    
                                    <div class="type-level-selector">
                                        <!-- VIP -->
                                        <div class="type-card vip-card <?php echo isset($group) && $group['level'] == 'vip' ? 'selected' : ''; ?>" id="vip_card">
                                            <div class="type-icon vip-icon">
                                                <i class="fas fa-crown"></i>
                                            </div>
                                            <div class="type-title">VIP</div>
                                            <div class="type-description">Sinais exclusivos para membros premium</div>
                                        </div>
                                        
                                        <!-- Comum -->
                                        <div class="type-card comum-card <?php echo isset($group) && $group['level'] == 'comum' ? 'selected' : ''; ?>" id="comum_card">
                                            <div class="type-icon comum-icon">
                                                <i class="fas fa-user-friends"></i>
                                            </div>
                                            <div class="type-title">Comum</div>
                                            <div class="type-description">Sinais para todos os membros</div>
                                        </div>
                                    </div>
                                    
                                    <input type="hidden" name="level" id="level_input" value="<?php echo isset($group) ? $group['level'] : 'comum'; ?>" required>
                                </div>
                                
                                <!-- Configuração de Frequência de Sinais -->
                                <div class="form-section mb-4">
                                    <h5 class="mb-3">Configuração de Sinais</h5>
                                    
                                    <div class="mb-3">
                                        <label for="signal_frequency" class="form-label">Frequência de Envio (minutos)</label>
                                        <input type="range" class="form-range" id="signal_frequency" name="signal_frequency" 
                                               min="5" max="120" step="5" 
                                               value="<?php echo isset($group) ? $group['signal_frequency'] : '30'; ?>">
                                        <div class="range-labels">
                                            <span>5 min</span>
                                            <span>30 min</span>
                                            <span>60 min</span>
                                            <span>120 min</span>
                                        </div>
                                        <div class="form-text">
                                            Sinais serão enviados a cada <span id="signal_frequency_value" class="range-value">
                                                <?php echo isset($group) ? $group['signal_frequency'] : '30'; ?>
                                            </span> minutos.
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="min_minutes" class="form-label">Duração Mínima (minutos)</label>
                                                <input type="number" class="form-control" id="min_minutes" name="min_minutes" 
                                                       value="<?php echo isset($group) ? $group['min_minutes'] : '3'; ?>" 
                                                       min="1" max="15">
                                                <div class="form-text">Tempo mínimo para execução do sinal</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="max_minutes" class="form-label">Duração Máxima (minutos)</label>
                                                <input type="number" class="form-control" id="max_minutes" name="max_minutes" 
                                                       value="<?php echo isset($group) ? $group['max_minutes'] : '10'; ?>" 
                                                       min="5" max="30">
                                                <div class="form-text">Tempo máximo para execução do sinal</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <span id="frequency_explanation">
                                            Grupos VIP recebem sinais mais frequentes e com tempos de execução mais curtos e precisos.
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save"></i> <?php echo $isEditing ? 'Atualizar Grupo' : 'Adicionar Grupo'; ?>
                                    </button>
                                </div>
                            </form>
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
        
        // Seleção de Tipo
        var pgSoftCard = document.getElementById('pg_soft_card');
        var pragmaticCard = document.getElementById('pragmatic_card');
        var typeInput = document.getElementById('type_input');
        
        if (pgSoftCard && pragmaticCard && typeInput) {
            pgSoftCard.addEventListener('click', function() {
                pgSoftCard.classList.add('selected');
                pragmaticCard.classList.remove('selected');
                typeInput.value = 'pg_soft';
            });
            
            pragmaticCard.addEventListener('click', function() {
                pragmaticCard.classList.add('selected');
                pgSoftCard.classList.remove('selected');
                typeInput.value = 'pragmatic';
            });
        }
        
        // Seleção de Nível
        var vipCard = document.getElementById('vip_card');
        var comumCard = document.getElementById('comum_card');
        var levelInput = document.getElementById('level_input');
        
        if (vipCard && comumCard && levelInput) {
            vipCard.addEventListener('click', function() {
                vipCard.classList.add('selected');
                comumCard.classList.remove('selected');
                levelInput.value = 'vip';
                updateFrequencyExplanation();
            });
            
            comumCard.addEventListener('click', function() {
                comumCard.classList.add('selected');
                vipCard.classList.remove('selected');
                levelInput.value = 'comum';
                updateFrequencyExplanation();
            });
        }
        
        // Atualizar valor do range
        var signalFrequency = document.getElementById('signal_frequency');
        var signalFrequencyValue = document.getElementById('signal_frequency_value');
        
        if (signalFrequency && signalFrequencyValue) {
            signalFrequency.addEventListener('input', function() {
                signalFrequencyValue.textContent = this.value;
            });
        }
        
        // Atualizar explicação da frequência
        function updateFrequencyExplanation() {
            var explanation = document.getElementById('frequency_explanation');
            var level = levelInput.value;
            
            if (explanation) {
                if (level === 'vip') {
                    explanation.innerHTML = '<strong>Configuração VIP:</strong> Sinais mais frequentes e com tempos de execução mais curtos e precisos.';
                } else {
                    explanation.innerHTML = '<strong>Configuração Comum:</strong> Sinais em intervalos maiores e com tempos de execução mais flexíveis.';
                }
            }
        }
        
        // Inicializar a explicação
        updateFrequencyExplanation();
        
        // Validação do formulário antes de enviar
        var groupForm = document.getElementById('groupForm');
        
        if (groupForm) {
            groupForm.addEventListener('submit', function(e) {
                var minMinutes = parseInt(document.getElementById('min_minutes').value);
                var maxMinutes = parseInt(document.getElementById('max_minutes').value);
                
                if (minMinutes >= maxMinutes) {
                    e.preventDefault();
                    alert('A duração mínima deve ser menor que a duração máxima.');
                    return false;
                }
                
                if (minMinutes < 1) {
                    e.preventDefault();
                    alert('A duração mínima deve ser de pelo menos 1 minuto.');
                    return false;
                }
                
                if (maxMinutes > 30) {
                    e.preventDefault();
                    alert('A duração máxima não pode exceder 30 minutos.');
                    return false;
                }
                
                return true;
            });
        }
    });
    </script>
</body>
</html>