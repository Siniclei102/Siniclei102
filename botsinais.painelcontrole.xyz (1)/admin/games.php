<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Processar exclusão de jogo
if (isset($_POST['action']) && $_POST['action'] == 'delete') {
    $game_id = (int)$_POST['game_id'];
    
    // Verificar se o jogo está sendo usado em sinais
    $check_signals = $conn->prepare("SELECT COUNT(*) as count FROM signals WHERE game_id = ?");
    $check_signals->bind_param("i", $game_id);
    $check_signals->execute();
    $result = $check_signals->get_result();
    $signals_count = $result->fetch_assoc()['count'];
    
    if ($signals_count > 0) {
        $_SESSION['message'] = "Este jogo não pode ser excluído pois está sendo usado em $signals_count sinais.";
        $_SESSION['alert_type'] = "warning";
    } else {
        // Excluir jogo
        $stmt = $conn->prepare("DELETE FROM games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Jogo excluído com sucesso!";
            $_SESSION['alert_type'] = "success";
            
            // Registrar ação no log
            logAdminAction($conn, $_SESSION['user_id'], "Excluiu o jogo ID: $game_id");
        } else {
            $_SESSION['message'] = "Erro ao excluir jogo: " . $conn->error;
            $_SESSION['alert_type'] = "danger";
        }
    }
    
    header('Location: index.php');
    exit;
}

// Filtro de provider (plataforma)
$provider_filter = isset($_GET['provider']) ? $_GET['provider'] : '';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Construir a consulta base
$query = "SELECT * FROM games WHERE 1=1";

// Adicionar filtros
if (!empty($provider_filter)) {
    $query .= " AND provider = ?";
}

if (!empty($search_term)) {
    $query .= " AND (name LIKE ? OR description LIKE ?)";
}

// Ordenação
$query .= " ORDER BY provider, name";

// Preparar e executar a consulta
$stmt = $conn->prepare($query);

// Bind dos parâmetros conforme os filtros
if (!empty($provider_filter) && !empty($search_term)) {
    $search_param = "%$search_term%";
    $stmt->bind_param("sss", $provider_filter, $search_param, $search_param);
} elseif (!empty($provider_filter)) {
    $stmt->bind_param("s", $provider_filter);
} elseif (!empty($search_term)) {
    $search_param = "%$search_term%";
    $stmt->bind_param("ss", $search_param, $search_param);
}

$stmt->execute();
$result = $stmt->get_result();

// Definir título da página
$pageTitle = 'Gerenciamento de Jogos';

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
        
        /* Card principal */
        .main-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e3e6f0;
            background-color: #f8f9fc;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
        }
        
        .card-header h5 {
            color: var(--dark-color);
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .card-header h5 i {
            margin-right: 0.5rem;
            color: var(--warning-color);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Tabela de dados */
        .data-table {
            width: 100%;
        }
        
        .data-table thead th {
            background-color: #f8f9fc;
            color: var(--dark-color);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05rem;
            vertical-align: middle;
        }
        
        .data-table td {
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        /* Filtros e pesquisa */
        .filters-row {
            margin-bottom: 1.5rem;
        }
        
        .provider-filter {
            display: flex;
            gap: 0.5rem;
        }
        
        .provider-filter .btn {
            border-radius: 50px;
            font-weight: 600;
            padding: 0.5rem 1rem;
        }
        
        .search-form {
            max-width: 300px;
        }
        
        .search-form .input-group {
            border-radius: 50px;
            overflow: hidden;
        }
        
        /* Game preview */
        .game-preview {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
            background-color: #f2f2f2;
            margin-right: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .game-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Provider badges */
        .badge-provider {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        .badge-pragmatic {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        .badge-pg {
            background-color: rgba(78, 115, 223, 0.1);
            color: var(--primary-color);
        }
        
        .badge-other {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-view {
            background-color: var(--info-color);
        }
        
        .btn-edit {
            background-color: var(--warning-color);
        }
        
        .btn-delete {
            background-color: var(--danger-color);
        }
        
        /* Overlay e responsividade */
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
                    <a href="index.php" class="active">
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
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Jogos</li>
                        </ol>
                    </nav>
                    
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Adicionar Jogo
                    </a>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                    unset($_SESSION['message']);
                    unset($_SESSION['alert_type']);
                endif; 
                ?>
                
                <!-- Card Principal -->
                <div class="main-card">
                    <div class="card-header">
                        <h5><i class="fas fa-gamepad"></i> Lista de Jogos</h5>
                    </div>
                    
                    <div class="card-body">
                        <!-- Filtros e pesquisa -->
                        <div class="row filters-row">
                            <div class="col-md-6">
                                <div class="provider-filter">
                                    <a href="index.php" class="btn <?php echo empty($provider_filter) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        Todos
                                    </a>
                                    <a href="index.php?provider=pragmatic" class="btn <?php echo $provider_filter == 'pragmatic' ? 'btn-success' : 'btn-outline-success'; ?>">
                                        Pragmatic Play
                                    </a>
                                    <a href="index.php?provider=pg" class="btn <?php echo $provider_filter == 'pg' ? 'btn-info' : 'btn-outline-info'; ?>">
                                        PG Soft
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <form method="get" class="search-form ms-auto">
                                    <?php if (!empty($provider_filter)): ?>
                                    <input type="hidden" name="provider" value="<?php echo $provider_filter; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Buscar jogo..." name="search" value="<?php echo $search_term; ?>">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Tabela de jogos -->
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th width="80">Preview</th>
                                        <th>Nome do Jogo</th>
                                        <th>Provider</th>
                                        <th>Tipo</th>
                                        <th>Status</th>
                                        <th width="120">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($game = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="game-preview">
                                                        <?php if (!empty($game['image'])): ?>
                                                            <img src="../../assets/img/games/<?php echo $game['image']; ?>" alt="<?php echo $game['name']; ?>">
                                                        <?php else: ?>
                                                            <i class="fas fa-gamepad fa-2x text-muted"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($game['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($game['provider'] == 'pragmatic'): ?>
                                                        <span class="badge-provider badge-pragmatic">Pragmatic Play</span>
                                                    <?php elseif ($game['provider'] == 'pg'): ?>
                                                        <span class="badge-provider badge-pg">PG Soft</span>
                                                    <?php else: ?>
                                                        <span class="badge-provider badge-other"><?php echo htmlspecialchars(ucfirst($game['provider'])); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($game['type'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($game['status'] == 'active'): ?>
                                                        <span class="badge bg-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                   
                                                        </a>
                                                        <a href="edit.php?id=<?php echo $game['id']; ?>" class="btn-action btn-edit" title="Editar">
                                                            <i class="fas fa-pencil-alt"></i>
                                                        </a>
                                                        <button type="button" class="btn-action btn-delete" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteModal"
                                                                data-game-id="<?php echo $game['id']; ?>"
                                                                data-game-name="<?php echo htmlspecialchars($game['name']); ?>"
                                                                title="Excluir">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <?php if (!empty($provider_filter) || !empty($search_term)): ?>
                                                    <div class="text-muted">
                                                        <i class="fas fa-search me-2"></i>Nenhum jogo encontrado com os critérios de pesquisa.
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-muted">
                                                        <i class="fas fa-info-circle me-2"></i>Nenhum jogo cadastrado ainda.
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmação de exclusão -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center pb-4">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-triangle text-warning fa-4x"></i>
                    </div>
                    <h5 class="mb-4">Tem certeza que deseja excluir o jogo <span id="gameName"></span>?</h5>
                    <p class="text-muted mb-4">Esta ação não poderá ser desfeita e todos os dados relacionados a este jogo serão perdidos.</p>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="game_id" id="gameId">
                        
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash-alt me-2"></i>Excluir
                            </button>
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
        
        // Modal de exclusão
        var deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var gameId = button.getAttribute('data-game-id');
                var gameName = button.getAttribute('data-game-name');
                
                deleteModal.querySelector('#gameId').value = gameId;
                deleteModal.querySelector('#gameName').textContent = gameName;
            });
        }
    });
    </script>
</body>
</html>