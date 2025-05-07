<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Verificar se o usuário é tipo comum
if (isset($_SESSION['role']) && $_SESSION['role'] != 'user') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Consultar informações do usuário
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Verificar primeiro se a tabela user_bot_access existe
$table_check = $conn->query("SHOW TABLES LIKE 'user_bot_access'");
$has_user_bot_access = $table_check && $table_check->num_rows > 0;

// Verificar estrutura da tabela bots
$column_check = $conn->query("SHOW COLUMNS FROM bots LIKE 'platform_id'");
$has_platform_id = $column_check && $column_check->num_rows > 0;

// Consulta adaptativa baseada na estrutura do banco
if ($has_user_bot_access && $has_platform_id) {
    // Usar a consulta completa com plataformas e tabela de acesso
    $stmt = $conn->prepare("
        SELECT b.*, p.name as platform_name 
        FROM user_bot_access uba 
        JOIN bots b ON uba.bot_id = b.id 
        LEFT JOIN platforms p ON b.platform_id = p.id 
        WHERE uba.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
} elseif ($has_user_bot_access) {
    // Usar consulta sem o JOIN de plataformas
    $stmt = $conn->prepare("
        SELECT b.*
        FROM user_bot_access uba 
        JOIN bots b ON uba.bot_id = b.id 
        WHERE uba.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
} else {
    // Caso não tenha tabela de acesso, tentar buscar bots criados pelo usuário
    $stmt = $conn->prepare("
        SELECT b.*
        FROM bots b
        WHERE b.created_by = ?
    ");
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$bots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obter total de grupos criados pelo usuário
$totalGroups = getCountWhere($conn, 'groups', "created_by = $userId");

// Obter total de canais criados pelo usuário
$totalChannels = getCountWhere($conn, 'channels', "created_by = $userId");

// Obter grupos criados pelo usuário
$stmt = $conn->prepare("
    SELECT g.*
    FROM `groups` g
    WHERE g.created_by = ?
    ORDER BY g.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obter canais criados pelo usuário
$stmt = $conn->prepare("
    SELECT c.*
    FROM channels c
    WHERE c.created_by = ?
    ORDER BY c.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$channels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Verificar dias restantes da assinatura
$daysLeft = !empty($user['expiry_date']) ? daysRemaining($user['expiry_date']) : 0;

// Obter configurações do site
$siteName = getSetting($conn, 'site_name');
if (!$siteName) {
    $siteName = 'BotDeSinais';
}
$siteLogo = getSetting($conn, 'site_logo');
if (!$siteLogo) {
    $siteLogo = 'logo.png';
}

$pageTitle = 'Dashboard do Usuário';
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
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); /* Cor azul para user */
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
        
        /* User Badge */
        .user-badge {
            background-color: var(--primary-color);
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
        
        .sidebar.collapsed .user-badge,
        .sidebar.mobile-visible .user-badge {
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
        
        /* Badge do User na topbar fixa */
        .topbar-user-badge {
            display: inline-block;
            background-color: var(--primary-color);
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
        
        /* Cards e elementos visuais */
        .info-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            padding: 1.5rem;
        }
        
        .stat-card {
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            color: #fff;
            overflow: hidden;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        
        .stat-card-primary {
            background: linear-gradient(45deg, #4e73df, #2e59d9);
        }
        
        .stat-card-success {
            background: linear-gradient(45deg, #1cc88a, #13855c);
        }
        
        .stat-card-info {
            background: linear-gradient(45deg, #36b9cc, #258391);
        }
        
        .stat-card-warning {
            background: linear-gradient(45deg, #f6c23e, #dda20a);
        }
        
        .stat-card-danger {
            background: linear-gradient(45deg, #e74a3b, #be2617);
        }
        
        .stat-card-body {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .stat-card-icon {
            font-size: 2rem;
            opacity: 0.7;
        }
        
        .stat-card-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
            opacity: 0.8;
        }
        
        .stat-card-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.25rem;
        }
        
        .card-title {
            margin-bottom: 0;
            font-weight: 700;
            color: #4e73df;
        }
        
        /* Botões e badges */
        .btn-rounded {
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
        }
        
        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        /* Tabelas */
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
            vertical-align: middle;
            font-weight: 700;
            color: #5a5c69;
        }
        
        .table-action-buttons {
            display: flex;
            gap: 0.5rem;
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
                <img src="../assets/img/<?php echo $siteLogo; ?>" alt="<?php echo $siteName; ?>">
                <h2><?php echo $siteName; ?> <span class="user-badge">Usuário</span></h2>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-tachometer-alt" style="color: var(--light-color);"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <div class="menu-header">Gerenciamento</div>
                
                <li>
                    <a href="bots.php">
                        <i class="fas fa-robot" style="color: var(--success-color);"></i>
                        <span>Meus Bots</span>
                    </a>
                </li>
                <li>
                    <a href="groups.php">
                        <i class="fas fa-users" style="color: var(--warning-color);"></i>
                        <span>Meus Grupos</span>
                    </a>
                </li>
                <li>
                    <a href="channels.php">
                        <i class="fas fa-broadcast-tower" style="color: var(--info-color);"></i>
                        <span>Meus Canais</span>
                    </a>
                </li>
                
                <div class="menu-header">Configurações</div>
                
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user-cog" style="color: var(--purple-color);"></i>
                        <span>Meu Perfil</span>
                    </a>
                </li>
                
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <div class="menu-divider"></div>
                
                <li>
                    <a href="../admin/dashboard.php">
                        <i class="fas fa-user-shield" style="color: var(--danger-color);"></i>
                        <span>Modo Admin</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <div class="menu-divider"></div>
                
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
                
                <div class="topbar-user-badge">Usuário</div>
                
                <div class="topbar-user">
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-md-inline-block me-1"><?php echo htmlspecialchars($username); ?></span>
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="../assets/img/avatars/<?php echo $user['avatar']; ?>" alt="Avatar">
                            <?php else: ?>
                                <img src="../assets/img/user-avatar.png" alt="Avatar">
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i> Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Conteúdo da Página -->
            <div class="content">
                <!-- Cabeçalho da página -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                    <div>
                        <span class="badge bg-<?php echo $daysLeft <= 7 ? 'danger' : 'primary'; ?> p-2">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo $daysLeft; ?> dias restantes
                        </span>
                    </div>
                </div>
                
                <!-- Cards de estatísticas -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-primary">
                            <div class="stat-card-body">
                                <div>
                                    <div class="stat-card-title">Bots Disponíveis</div>
                                    <h5 class="stat-card-value"><?php echo count($bots); ?></h5>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-robot"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-success">
                            <div class="stat-card-body">
                                <div>
                                    <div class="stat-card-title">Meus Grupos</div>
                                    <h5 class="stat-card-value"><?php echo $totalGroups; ?></h5>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-card-info">
                            <div class="stat-card-body">
                                <div>
                                    <div class="stat-card-title">Meus Canais</div>
                                    <h5 class="stat-card-value"><?php echo $totalChannels; ?></h5>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-broadcast-tower"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card <?php echo $daysLeft < 10 ? 'stat-card-danger' : 'stat-card-warning'; ?>">
                            <div class="stat-card-body">
                                <div>
                                    <div class="stat-card-title">Dias Restantes</div>
                                    <h5 class="stat-card-value"><?php echo $daysLeft; ?></h5>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de bots disponíveis -->
                <div class="row">
                    <div class="col-12">
                        <div class="info-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title">
                                    <i class="fas fa-robot me-2" style="color: var(--primary-color);"></i>
                                    Meus Bots
                                </h5>
                                <a href="bots.php" class="btn btn-sm btn-primary btn-rounded">
                                    <i class="fas fa-list me-1"></i> Ver Todos
                                </a>
                            </div>
                            
                            <?php if (count($bots) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Bot</th>
                                                <th>Plataforma</th>
                                                <th>Status</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bots as $bot): ?>
                                                <tr>
                                                    <td class="align-middle"><?php echo htmlspecialchars($bot['name']); ?></td>
                                                    <td class="align-middle">
                                                        <?php 
                                                        if (isset($bot['platform_name']) && !empty($bot['platform_name'])) {
                                                            echo htmlspecialchars($bot['platform_name']);
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="align-middle">
                                                        <?php if ($bot['status'] === 'active'): ?>
                                                            <span class="badge bg-success">Ativo</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Inativo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="bot_details.php?id=<?php echo $bot['id']; ?>" class="btn btn-sm btn-action" style="background-color: #4e73df; color: white;" title="Visualizar Bot">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Você ainda não tem acesso a nenhum bot.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Linha com Cards de Grupos e Canais -->
                <div class="row">
                    <!-- Grupos Recentes -->
                    <div class="col-lg-6">
                        <div class="info-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title">
                                    <i class="fas fa-users me-2" style="color: var(--success-color);"></i>
                                    Grupos Recentes
                                </h5>
                                <a href="groups.php" class="btn btn-sm btn-success btn-rounded">
                                    <i class="fas fa-list me-1"></i> Ver Todos
                                </a>
                            </div>
                            
                            <?php if (count($groups) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($groups as $group): ?>
                                        <a href="group_details.php?id=<?php echo $group['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($group['name']); ?></h6>
                                                <small class="text-muted"><?php echo date('d/m/Y', strtotime($group['created_at'])); ?></small>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-users me-1"></i> <?php echo htmlspecialchars($group['telegram_id']); ?>
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Você ainda não criou nenhum grupo.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Canais Recentes -->
                    <div class="col-lg-6">
                        <div class="info-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title">
                                    <i class="fas fa-broadcast-tower me-2" style="color: var(--info-color);"></i>
                                    Canais Recentes
                                </h5>
                                <a href="channels.php" class="btn btn-sm btn-info btn-rounded text-white">
                                    <i class="fas fa-list me-1"></i> Ver Todos
                                </a>
                            </div>
                            
                            <?php if (count($channels) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($channels as $channel): ?>
                                        <a href="channel_details.php?id=<?php echo $channel['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($channel['name']); ?></h6>
                                                <small class="text-muted"><?php echo date('d/m/Y', strtotime($channel['created_at'])); ?></small>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-broadcast-tower me-1"></i> <?php echo htmlspecialchars($channel['telegram_id']); ?>
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Você ainda não criou nenhum canal.
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
        
        // Inicializar tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.forEach(function (element) {
            new bootstrap.Tooltip(element);
        });
    });
    </script>
</body>
</html>