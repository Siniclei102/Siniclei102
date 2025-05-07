<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Mensagens de Feedback
function showMessage($type, $message) {
    $_SESSION['alert_type'] = $type;
    $_SESSION['message'] = $message;
}

// Processamento de Formulários
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        
        // Ativar ou Suspender Usuário
        if ($_POST['action'] == 'toggle_status') {
            $current_status = $_POST['current_status'];
            $new_status = ($current_status == 'active') ? 'suspended' : 'active';
            
            $updateStmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $updateStmt->bind_param("si", $new_status, $user_id);
            
            if ($updateStmt->execute()) {
                $action = ($new_status == 'active') ? 'ativou' : 'suspendeu';
                logAdminAction($conn, $_SESSION['user_id'], "$action o usuário $username (ID: $user_id)");
                showMessage("success", "Status do usuário alterado com sucesso!");
            } else {
                showMessage("danger", "Erro ao alterar o status do usuário.");
            }
        }
        
        // Renovar Assinatura
        elseif ($_POST['action'] == 'renew') {
            $days = (int)$_POST['days'];
            
            // Obter a data de expiração atual
            $stmt = $conn->prepare("SELECT expiry_date FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $current_date = date('Y-m-d');
                
                // Se já expirou, use a data atual como base
                if (empty($user['expiry_date']) || $user['expiry_date'] < $current_date) {
                    $base_date = $current_date;
                } else {
                    // Caso contrário, use a data de expiração atual
                    $base_date = $user['expiry_date'];
                }
                
                // Calcular nova data de expiração
                $new_expiry_date = date('Y-m-d', strtotime($base_date . " + $days days"));
                
                // Atualizar usuário
                $updateStmt = $conn->prepare("UPDATE users SET expiry_date = ?, status = 'active' WHERE id = ?");
                $updateStmt->bind_param("si", $new_expiry_date, $user_id);
                
                if ($updateStmt->execute()) {
                    logAdminAction($conn, $_SESSION['user_id'], "Renovou assinatura do usuário $username (ID: $user_id) por $days dias");
                    showMessage("success", "Assinatura renovada com sucesso!");
                } else {
                    showMessage("danger", "Erro ao renovar assinatura.");
                }
            } else {
                showMessage("danger", "Usuário não encontrado.");
            }
        }
        
        // Remover usuário (exclusão física)
        elseif ($_POST['action'] == 'delete') {
            // Verificar se existem bots associados
            $bots_count = getCountWhere($conn, 'bots', "created_by = $user_id");
            
            if ($bots_count > 0) {
                showMessage("warning", "Não é possível excluir o usuário pois existem $bots_count bots associados a ele.");
            } else {
                // Exclusão simples do usuário sem verificar tabelas relacionadas
                $deleteUser = $conn->prepare("DELETE FROM users WHERE id = ?");
                $deleteUser->bind_param("i", $user_id);
                
                if ($deleteUser->execute()) {
                    logAdminAction($conn, $_SESSION['user_id'], "Removeu o usuário $username (ID: $user_id) permanentemente");
                    showMessage("success", "Usuário removido com sucesso!");
                } else {
                    showMessage("danger", "Erro ao remover o usuário: " . $conn->error);
                }
            }
        }
    }
}

// Configurações de Paginação
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Filtros e Pesquisa
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Construir consulta base
$where_clause = "WHERE 1=1"; // Começamos com uma condição sempre verdadeira

// Adicionar filtros ao WHERE
if (!empty($role) && $role != 'all') {
    $where_clause .= " AND role = '$role'";
} else if (empty($role)) {
    // Se não há filtro específico, mostramos por padrão os usuários comuns
    $where_clause .= " AND (role = 'user'";
    
    // E se não há filtro específico, incluímos também os masters
    $where_clause .= " OR role = 'master')";
}

if (!empty($status)) {
    if ($status == 'expired') {
        $where_clause .= " AND status = 'active' AND expiry_date < CURDATE()";
    } elseif ($status == 'expiring') {
        $where_clause .= " AND status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($status != 'all') {
        $where_clause .= " AND status = '$status'";
    }
}

// Adicionar pesquisa ao WHERE
if (!empty($search)) {
    $search_term = "%$search%";
    $where_clause .= " AND (username LIKE '$search_term' OR email LIKE '$search_term' OR full_name LIKE '$search_term')";
}

// Adicionar ORDER BY
$order_clause = "ORDER BY $sort $order";

// Consulta para contar total de registros
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$count_result = $conn->query($count_sql);
$count_row = $count_result->fetch_assoc();
$total_records = $count_row['total'];

// Cálculo de paginação
$total_pages = ceil($total_records / $per_page);

// Consulta para buscar usuários
$sql = "SELECT * FROM users $where_clause $order_clause LIMIT $offset, $per_page";
$result = $conn->query($sql);

// Contadores de status para filtros
$active_count = getCountWhere($conn, 'users', "role = 'user' AND status = 'active'");
$suspended_count = getCountWhere($conn, 'users', "role = 'user' AND status = 'suspended'");
$expired_count = getCountWhere($conn, 'users', "role = 'user' AND expiry_date < CURDATE() AND status = 'active'");
$expiring_count = getCountWhere($conn, 'users', "role = 'user' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'active'");
$all_count = getCountWhere($conn, 'users', "role = 'user'");

// Contador para masters
$master_count = getCountWhere($conn, 'users', "role = 'master'");

// Definir título da página
$pageTitle = 'Gerenciar Usuários';

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
        
        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
        }
        
        .btn-rounded {
            border-radius: 50px;
            padding: 0.5rem 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Filtros e Pesquisa */
        .filters-container {
            margin-bottom: 1.5rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 1.25rem;
        }
        
        .filters-nav {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 0.5rem;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .filter-item {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background-color: white;
            border: 1px solid #e3e6f0;
            border-radius: 50px;
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
            transition: all 0.2s;
        }
        
        .filter-item:hover {
            background-color: #f8f9fc;
        }
        
        .filter-item.active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .filter-item i {
            margin-right: 0.4rem;
        }
        
        .filter-item .badge {
            margin-left: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        .search-form {
            position: relative;
        }
        
        .search-form .form-control {
            padding-left: 2.5rem;
            border-radius: 50px;
            height: 46px;
        }
        
        .search-form .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
            border-color: #bac8f3;
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        /* Tabela de Usuários */
        .table-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            border-top: none;
            color: var(--dark-color);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.03em;
            padding: 1.25rem;
            background-color: #f8f9fc;
        }
        
        .table th:first-child {
            border-top-left-radius: 1rem;
        }
        
        .table th:last-child {
            border-top-right-radius: 1rem;
        }
        
        .table td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
            color: var(--dark-color);
            border-bottom: 1px solid #e3e6f0;
            font-size: 0.9rem;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f8f9fc;
        }
        
        .table tr:last-child td:first-child {
            border-bottom-left-radius: 1rem;
        }
        
        .table tr:last-child td:last-child {
            border-bottom-right-radius: 1rem;
        }
        
        /* Badges e Status */
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

/* Remova os estilos antigos de hover que não serão mais necessários */
.btn-action-primary:hover,
.btn-action-success:hover,
.btn-action-warning:hover,
.btn-action-danger:hover {
    /* Vazio para remover os estilos anteriores */
}
        
       
        /* Paginação */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .pagination {
            margin-bottom: 0;
        }
        
        .page-link {
            padding: 0.5rem 0.75rem;
            color: var(--primary-color);
            background-color: white;
            border: 1px solid #e3e6f0;
            margin: 0 0.25rem;
            border-radius: 0.25rem;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .page-link:hover {
            background-color: #eaecf4;
            border-color: #e3e6f0;
            color: var(--secondary-color);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .page-item.disabled .page-link {
            color: #d1d3e2;
        }
        
        /* Modais */
        .modal-confirm .icon-box {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-confirm .icon-box i {
            font-size: 2.5rem;
            color: white;
        }
        
        .modal-confirm .icon-success {
            background-color: var(--success-color);
        }
        
        .modal-confirm .icon-warning {
            background-color: var(--warning-color);
        }
        
        .modal-confirm .icon-danger {
            background-color: var(--danger-color);
        }
        
        .modal-confirm .icon-info {
            background-color: var(--info-color);
        }
        
        /* Responsividade */
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
        
        @media (max-width: 767.98px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .page-header .ms-auto {
                margin-left: 0 !important;
            }
            
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .pagination-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .pagination {
                margin-left: auto;
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
                <div class="page-header">
                    <h1><?php echo $pageTitle; ?></h1>
                    <a href="create.php" class="btn btn-primary btn-rounded ms-auto">
                        <i class="fas fa-plus"></i> Novo Usuário
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
                
                <!-- Filtros e Pesquisa -->
                <div class="filters-container">
                    <div class="filters-nav">
                        <!-- Filtros por Status -->
                        <a href="index.php" class="filter-item <?php echo empty($status) && empty($role) ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i> Todos <span class="badge <?php echo empty($status) && empty($role) ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $all_count; ?></span>
                        </a>
                        <a href="index.php?status=active" class="filter-item <?php echo $status == 'active' ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle"></i> Ativos <span class="badge <?php echo $status == 'active' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $active_count; ?></span>
                        </a>
                        <a href="index.php?status=suspended" class="filter-item <?php echo $status == 'suspended' ? 'active' : ''; ?>">
                            <i class="fas fa-ban"></i> Suspensos <span class="badge <?php echo $status == 'suspended' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $suspended_count; ?></span>
                        </a>
                        <a href="index.php?status=expired" class="filter-item <?php echo $status == 'expired' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-times"></i> Expirados <span class="badge <?php echo $status == 'expired' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $expired_count; ?></span>
                        </a>
                        <a href="index.php?status=expiring" class="filter-item <?php echo $status == 'expiring' ? 'active' : ''; ?>">
                            <i class="fas fa-clock"></i> Expirando <span class="badge <?php echo $status == 'expiring' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $expiring_count; ?></span>
                        </a>
                        
                        <!-- Filtros por Tipo -->
                        <a href="index.php?role=master" class="filter-item <?php echo $role == 'master' ? 'active' : ''; ?>">
                            <i class="fas fa-crown"></i> Masters <span class="badge <?php echo $role == 'master' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $master_count; ?></span>
                        </a>
                    </div>
                    
                    <!-- Campo de Pesquisa -->
                    <form action="index.php" method="get" class="search-form">
                        <?php if (!empty($status)): ?>
                        <input type="hidden" name="status" value="<?php echo $status; ?>">
                        <?php endif; ?>
                        <?php if (!empty($role)): ?>
                        <input type="hidden" name="role" value="<?php echo $role; ?>">
                        <?php endif; ?>
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="form-control" name="search" placeholder="Pesquisar usuários por nome, email..." value="<?php echo htmlspecialchars($search); ?>" aria-label="Pesquisar">
                    </form>
                </div>
                
                <!-- Tabela de Usuários -->
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <a href="index.php?sort=username&order=<?php echo ($sort == 'username' && $order == 'asc') ? 'desc' : 'asc'; ?>&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>" class="text-decoration-none text-dark">
                                            Usuário
                                            <?php if ($sort == 'username'): ?>
                                                <i class="fas fa-sort-<?php echo $order == 'asc' ? 'up' : 'down'; ?> ms-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="d-none d-md-table-cell">Email</th>
                                    <th class="d-none d-lg-table-cell">
                                        <a href="index.php?sort=created_at&order=<?php echo ($sort == 'created_at' && $order == 'asc') ? 'desc' : 'asc'; ?>&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>" class="text-decoration-none text-dark">
                                            Cadastro
                                            <?php if ($sort == 'created_at'): ?>
                                                <i class="fas fa-sort-<?php echo $order == 'asc' ? 'up' : 'down'; ?> ms-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Status</th>
                                    <th class="d-none d-lg-table-cell">
                                        <a href="index.php?sort=expiry_date&order=<?php echo ($sort == 'expiry_date' && $order == 'asc') ? 'desc' : 'asc'; ?>&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>" class="text-decoration-none text-dark">
                                            Expira em
                                            <?php if ($sort == 'expiry_date'): ?>
                                                <i class="fas fa-sort-<?php echo $order == 'asc' ? 'up' : 'down'; ?> ms-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($result && $result->num_rows > 0): 
                                    while($row = $result->fetch_assoc()):
                                        // Determinar classes e texto de status
                                        if ($row['role'] == 'master') {
                                            $status_class = 'badge-status-master';
                                            $status_text = 'Master';
                                        } elseif ($row['status'] == 'suspended') {
                                            $status_class = 'badge-status-suspended';
                                            $status_text = 'Suspenso';
                                        } elseif (function_exists('isExpired') && isExpired($row['expiry_date'])) {
                                            $status_class = 'badge-status-expired';
                                            $status_text = 'Expirado';
                                        } else {
                                            $status_class = 'badge-status-active';
                                            $status_text = 'Ativo';
                                        }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($row['role'] == 'master'): ?>
                                                <span class="me-2" title="Usuário Master">
                                                    <i class="fas fa-crown text-warning"></i>
                                                </span>
                                            <?php else: ?>
                                                <span class="me-2">
                                                    <i class="fas fa-user text-primary"></i>
                                                </span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($row['username']); ?>
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                                    
                                    
                                    
                                    
                                                         <td class="d-none d-lg-table-cell">
                                        <?php
                                        if (function_exists('formatDate')) {
                                            echo formatDate($row['created_at']);
                                        } else {
                                            echo date('d/m/Y', strtotime($row['created_at']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge-status <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php if (!empty($row['expiry_date'])): ?>
                                            <?php
                                            // Calcular dias restantes
                                            if (function_exists('daysRemaining')) {
                                                $days = daysRemaining($row['expiry_date']);
                                            } else {
                                                $expiry = new DateTime($row['expiry_date']);
                                                $now = new DateTime();
                                                $days = $now->diff($expiry)->format("%r%a");
                                            }
                                            
                                            // Exibir data formatada
                                            if (function_exists('formatDate')) {
                                                echo formatDate($row['expiry_date']);
                                            } else {
                                                echo date('d/m/Y', strtotime($row['expiry_date']));
                                            }
                                            
                                            // Mostrar badge de alerta
                                            if ($days <= 0) {
                                                echo ' <span class="badge bg-danger">Expirado</span>';
                                            } elseif ($days <= 7) {
                                                echo ' <span class="badge bg-warning text-dark">' . $days . ' dias</span>';
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span title="Sem data de expiração definida">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    
                                    
                              
                                    
                                    
                                 
<td class="text-end">
    <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-action" style="background-color: #4e73df; color: white;" title="Visualizar">
        <i class="fas fa-eye"></i>
    </a>
    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-action" style="background-color: #36b9cc; color: white;" title="Editar">
        <i class="fas fa-edit"></i>
    </a>
    
    <?php if ($row['status'] == 'active'): ?>
    <button type="button" class="btn btn-action" style="background-color: #f6c23e; color: white;" data-bs-toggle="modal" data-bs-target="#toggleStatusModal" 
        data-user-id="<?php echo $row['id']; ?>" 
        data-username="<?php echo htmlspecialchars($row['username']); ?>"
        data-status="<?php echo $row['status']; ?>"
        data-action="suspender"
        title="Suspender Usuário">
        <i class="fas fa-ban"></i>
    </button>
    <?php else: ?>
    <button type="button" class="btn btn-action" style="background-color: #1cc88a; color: white;" data-bs-toggle="modal" data-bs-target="#toggleStatusModal" 
        data-user-id="<?php echo $row['id']; ?>" 
        data-username="<?php echo htmlspecialchars($row['username']); ?>"
        data-status="<?php echo $row['status']; ?>"
        data-action="ativar"
        title="Ativar Usuário">
        <i class="fas fa-check"></i>
    </button>
    <?php endif; ?>
    
    <!-- Botão de renovação para TODOS os usuários, incluindo masters -->
    <button type="button" class="btn btn-action" style="background-color: #8540f5; color: white;" data-bs-toggle="modal" data-bs-target="#renewModal"
        data-user-id="<?php echo $row['id']; ?>"
        data-username="<?php echo htmlspecialchars($row['username']); ?>"
        data-expiry="<?php echo $row['expiry_date']; ?>"
        title="Renovar Assinatura">
        <i class="fas fa-sync"></i>
    </button>
    
    <button type="button" class="btn btn-action" style="background-color: #e74a3b; color: white;" data-bs-toggle="modal" data-bs-target="#deleteModal"
        data-user-id="<?php echo $row['id']; ?>"
        data-username="<?php echo htmlspecialchars($row['username']); ?>"
        title="Excluir Usuário">
        <i class="fas fa-trash"></i>
    </button>
</td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <?php if (!empty($search)): ?>
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="fas fa-search fa-3x mb-3 text-secondary"></i>
                                                <p class="mb-0 text-secondary">Nenhum usuário encontrado com o termo "<?php echo htmlspecialchars($search); ?>"</p>
                                                <a href="index.php" class="btn btn-outline-primary btn-sm mt-3">Limpar Pesquisa</a>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="fas fa-users fa-3x mb-3 text-secondary"></i>
                                                <p class="mb-0 text-secondary">Nenhum usuário encontrado nesta categoria.</p>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Paginação -->
                <div class="pagination-container">
                    <div class="pagination-info">
                        <?php
                        $start_record = min(($page - 1) * $per_page + 1, $total_records);
                        $end_record = min($page * $per_page, $total_records);
                        
                        echo "Mostrando $start_record a $end_record de $total_records usuários";
                        ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=1&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" aria-label="Primeira">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" aria-label="Anterior">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-double-left"></i></span>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-left"></i></span>
                        </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($start_page + 4, $total_pages);
                        
                        if ($end_page - $start_page < 4 && $start_page > 1) {
                            $start_page = max(1, $end_page - 4);
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="index.php?page=<?php echo $i; ?>&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" aria-label="Próxima">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="index.php?page=<?php echo $total_pages; ?>&status=<?php echo $status; ?>&role=<?php echo $role; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>" aria-label="Última">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-right"></i></span>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-double-right"></i></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Confirmação para Ativar/Suspender -->
    <div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content modal-confirm">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="statusIconBox" class="icon-box icon-warning mb-4">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h5 class="modal-title mb-3" id="toggleStatusTitle">Confirmar Ação</h5>
                    <p id="toggleStatusMessage">Tem certeza que deseja alterar o status deste usuário?</p>
                    <form method="post" action="">
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
    
    <!-- Modal de Confirmação para Remover permanentemente -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content modal-confirm">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="icon-box icon-danger mb-4">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h5 class="modal-title mb-3">Excluir Usuário</h5>
                    <p>Tem certeza que deseja excluir permanentemente o usuário <strong id="deleteUsername"></strong>?</p>
                    <p class="text-danger small">Esta ação não pode ser desfeita!</p>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <input type="hidden" name="username" id="deleteFormUsername">
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Excluir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para Renovação de Assinatura -->
    <div class="modal fade" id="renewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-confirm">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="icon-box icon-info mb-4">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h5 class="modal-title mb-3">Renovar Assinatura</h5>
                    <p>Você está renovando a assinatura do usuário <strong id="renewUsername"></strong>.</p>
                    <p class="mb-3">Data atual de expiração: <span id="currentExpiryDate">-</span></p>
                    
                    <form method="post" action="">
                        <input type="hidden" name="action" value="renew">
                        <input type="hidden" name="user_id" id="renewUserId">
                        <input type="hidden" name="username" id="renewFormUsername">
                        
                        <div class="form-group mb-3">
                            <label for="renewDays" class="form-label">Período de Renovação</label>
                            <select class="form-select" id="renewDays" name="days" required>
                                <option value="7">7 dias</option>
                                <option value="15">15 dias</option>
                                <option value="30" selected>30 dias</option>
                                <option value="60">60 dias</option>
                                <option value="90">90 dias (3 meses)</option>
                                <option value="180">180 dias (6 meses)</option>
                                <option value="365">365 dias (1 ano)</option>
                            </select>
                        </div>
                        
                        <div class="d-flex justify-content-center gap-2 mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Renovar Assinatura</button>
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
                if (sidebar.classList.contains('mobile-visible')) {
                    sidebar.classList.remove('mobile-visible');
                    overlay.classList.remove('active');
                } else {
                    sidebar.classList.add('mobile-visible');
                    overlay.classList.add('active');
                }
            } else {
                if (sidebar.classList.contains('collapsed')) {
                    sidebar.classList.remove('collapsed');
                    contentWrapper.classList.remove('expanded');
                } else {
                    sidebar.classList.add('collapsed');
                    contentWrapper.classList.add('expanded');
                }
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
                    iconBox.className = 'icon-box icon-success mb-4';
                    iconBox.innerHTML = '<i class="fas fa-check-circle"></i>';
                    confirmBtn.className = 'btn btn-success';
                    confirmBtn.textContent = 'Ativar';
                } else {
                    title.textContent = 'Suspender Usuário';
                    message.innerHTML = 'Deseja suspender o usuário <strong>' + username + '</strong>?';
                    iconBox.className = 'icon-box icon-warning mb-4';
                    iconBox.innerHTML = '<i class="fas fa-ban"></i>';
                    confirmBtn.className = 'btn btn-warning';
                    confirmBtn.textContent = 'Suspender';
                }
            });
        }
        
        // Configurações para o modal de exclusão
        var deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var userId = button.getAttribute('data-user-id');
                var username = button.getAttribute('data-username');
                
                document.getElementById('deleteUserId').value = userId;
                document.getElementById('deleteFormUsername').value = username;
                document.getElementById('deleteUsername').textContent = username;
            });
        }
        
        // Configurações para o modal de renovação
        var renewModal = document.getElementById('renewModal');
        if (renewModal) {
            renewModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var userId = button.getAttribute('data-user-id');
                var username = button.getAttribute('data-username');
                var expiryDate = button.getAttribute('data-expiry');
                
                // Formatar data de expiração
                var formattedDate = '-';
                if (expiryDate) {
                    var parts = expiryDate.split('-');
                    if (parts.length === 3) {
                        formattedDate = parts[2] + '/' + parts[1] + '/' + parts[0]; // DD/MM/YYYY
                    }
                }
                
                document.getElementById('renewUserId').value = userId;
                document.getElementById('renewFormUsername').value = username;
                document.getElementById('renewUsername').textContent = username;
                document.getElementById('currentExpiryDate').textContent = formattedDate;
            });
        }
        
        // Inicializar tooltips do Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.forEach(function (element) {
            new bootstrap.Tooltip(element);
        });
        
        // Highlight para linha da tabela ao passar o mouse
        var tableRows = document.querySelectorAll('.table tbody tr');
        tableRows.forEach(function(row) {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fc';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
        
        // Confirmação dupla para exclusão (opcional)
        var deleteForm = document.querySelector('#deleteModal form');
        if (deleteForm) {
            deleteForm.addEventListener('submit', function(e) {
                // Remova o comentário abaixo se quiser uma confirmação dupla
                // var confirmed = confirm("ATENÇÃO: Esta ação excluirá PERMANENTEMENTE o usuário. Confirma a exclusão?");
                // if (!confirmed) {
                //     e.preventDefault();
                //     return false;
                // }
                return true;
            });
        }
    });
    </script>
</body>
</html>