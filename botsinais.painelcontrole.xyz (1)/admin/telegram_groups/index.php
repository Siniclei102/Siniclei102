<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Verificar e criar tabela se não existir
$check_table = $conn->query("SHOW TABLES LIKE 'telegram_groups'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE `telegram_groups` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `group_id` varchar(100) NOT NULL,
        `description` text DEFAULT NULL,
        `type` varchar(50) DEFAULT NULL,
        `level` enum('vip','comum') DEFAULT 'comum',
        `premium` tinyint(1) NOT NULL DEFAULT 0,
        `signal_frequency` int(11) DEFAULT 30,
        `min_minutes` int(11) DEFAULT 3,
        `max_minutes` int(11) DEFAULT 10,
        `status` enum('active','inactive') NOT NULL DEFAULT 'active',
        `created_at` datetime DEFAULT current_timestamp(),
        `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Verificar estrutura da tabela e adicionar colunas faltantes
$columns_to_check = [
    'type' => "ALTER TABLE telegram_groups ADD COLUMN type VARCHAR(50) DEFAULT NULL AFTER description",
    'level' => "ALTER TABLE telegram_groups ADD COLUMN level ENUM('vip', 'comum') DEFAULT 'comum' AFTER type",
    'signal_frequency' => "ALTER TABLE telegram_groups ADD COLUMN signal_frequency INT DEFAULT 30 AFTER level",
    'min_minutes' => "ALTER TABLE telegram_groups ADD COLUMN min_minutes INT DEFAULT 3 AFTER signal_frequency",
    'max_minutes' => "ALTER TABLE telegram_groups ADD COLUMN max_minutes INT DEFAULT 10 AFTER min_minutes"
];

foreach ($columns_to_check as $column => $alter_query) {
    $column_check = $conn->query("SHOW COLUMNS FROM telegram_groups LIKE '$column'");
    if ($column_check->num_rows == 0) {
        // Coluna não existe, adicionar
        $conn->query($alter_query);
    }
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
    $group_name = isset($_POST['group_name']) ? $_POST['group_name'] : '';
    
    if ($_POST['action'] == 'toggle_status') {
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';
        
        $stmt = $conn->prepare("UPDATE telegram_groups SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $group_id);
        
        if ($stmt->execute()) {
            $action_text = ($new_status == 'active') ? "ativou" : "desativou";
            logAdminAction($conn, $_SESSION['user_id'], "Admin $action_text o grupo '$group_name' (ID: $group_id)");
            
            $_SESSION['message'] = "Status do grupo alterado com sucesso!";
            $_SESSION['alert_type'] = "success";
        } else {
            $_SESSION['message'] = "Erro ao alterar status do grupo.";
            $_SESSION['alert_type'] = "danger";
        }
        
        header('Location: index.php');
        exit;
    }
    
    if ($_POST['action'] == 'delete') {
        // Verificar se existem mapeamentos relacionados
        $check_mappings = $conn->query("SELECT COUNT(*) as count FROM bot_group_mappings WHERE group_id = $group_id");
        if ($check_mappings && $check_mappings->num_rows > 0) {
            $mappings_count = $check_mappings->fetch_assoc()['count'];
            
            if ($mappings_count > 0) {
                // Remover mapeamentos primeiro
                $conn->query("DELETE FROM bot_group_mappings WHERE group_id = $group_id");
            }
        }
        
        // Verificar se existem acessos de usuários
        $check_access = $conn->query("SELECT COUNT(*) as count FROM user_group_access WHERE group_id = $group_id");
        if ($check_access && $check_access->num_rows > 0) {
            $access_count = $check_access->fetch_assoc()['count'];
            if ($access_count > 0) {
                // Remover acessos de usuários
                $conn->query("DELETE FROM user_group_access WHERE group_id = $group_id");
            }
        }
        
        // Agora excluir o grupo
        $delete_stmt = $conn->prepare("DELETE FROM telegram_groups WHERE id = ?");
        $delete_stmt->bind_param("i", $group_id);
        
        if ($delete_stmt->execute()) {
            logAdminAction($conn, $_SESSION['user_id'], "Admin excluiu o grupo '$group_name' (ID: $group_id)");
            $_SESSION['message'] = "Grupo excluído com sucesso!";
            $_SESSION['alert_type'] = "success";
        } else {
            $_SESSION['message'] = "Erro ao excluir o grupo.";
            $_SESSION['alert_type'] = "danger";
        }
        
        header('Location: index.php');
        exit;
    }
}

// Paginação e filtros
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$level_filter = isset($_GET['level']) ? $_GET['level'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Construir query
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(name LIKE ? OR group_id LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

if (!empty($type_filter)) {
    $where_clauses[] = "type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if (!empty($level_filter)) {
    $where_clauses[] = "level = ?";
    $params[] = $level_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Contagem total para paginação
$count_sql = "SELECT COUNT(*) as total FROM telegram_groups $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_groups = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_groups / $per_page);

// Buscar grupos - CORRIGIDO para remover JOIN com users
$sql = "SELECT * FROM telegram_groups $where_sql ORDER BY created_at DESC LIMIT $offset, $per_page";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$groups = $stmt->get_result();

// Contagens para filtros
$total_count = getCountWhere($conn, 'telegram_groups', "1");
$active_count = getCountWhere($conn, 'telegram_groups', "status = 'active'");
$inactive_count = getCountWhere($conn, 'telegram_groups', "status = 'inactive'");
$vip_count = getCountWhere($conn, 'telegram_groups', "level = 'vip'");
$comum_count = getCountWhere($conn, 'telegram_groups', "level = 'comum'");
$pg_soft_count = getCountWhere($conn, 'telegram_groups', "type = 'pg_soft'");
$pragmatic_count = getCountWhere($conn, 'telegram_groups', "type = 'pragmatic'");

// Mensagens de Feedback
$message = isset($_SESSION['message']) ? $_SESSION['message'] : null;
$alert_type = isset($_SESSION['alert_type']) ? $_SESSION['alert_type'] : null;

// Limpar as mensagens da sessão
unset($_SESSION['message'], $_SESSION['alert_type']);

// Definir título da página
$pageTitle = 'Gerenciar Grupos do Telegram';

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
        
        /* Dashboard Cards Summary */
        .dashboard-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
        }
        
        .summary-card .card-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .summary-card .summary-icon {
            background: linear-gradient(45deg, rgba(78,115,223,0.1) 0%, rgba(78,115,223,0.2) 100%);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .summary-card.blue .summary-icon {
            background: linear-gradient(45deg, rgba(78,115,223,0.1) 0%, rgba(78,115,223,0.2) 100%);
            color: var(--primary-color);
        }
        
        .summary-card.green .summary-icon {
            background: linear-gradient(45deg, rgba(28,200,138,0.1) 0%, rgba(28,200,138,0.2) 100%);
            color: var(--success-color);
        }
        
        .summary-card.red .summary-icon {
            background: linear-gradient(45deg, rgba(231,74,59,0.1) 0%, rgba(231,74,59,0.2) 100%);
            color: var(--danger-color);
        }
        
        .summary-card.yellow .summary-icon {
            background: linear-gradient(45deg, rgba(246,194,62,0.1) 0%, rgba(246,194,62,0.2) 100%);
            color: var(--warning-color);
        }
        
        .summary-card.purple .summary-icon {
            background: linear-gradient(45deg, rgba(133,64,245,0.1) 0%, rgba(133,64,245,0.2) 100%);
            color: var(--purple-color);
        }
        
        .summary-card.teal .summary-icon {
            background: linear-gradient(45deg, rgba(32,201,166,0.1) 0%, rgba(32,201,166,0.2) 100%);
            color: var(--teal-color);
        }
        
        .summary-card.info .summary-icon {
            background: linear-gradient(45deg, rgba(54,185,204,0.1) 0%, rgba(54,185,204,0.2) 100%);
            color: var(--info-color);
        }
        
        .summary-card .card-info {
            text-align: right;
        }
        
        .summary-card .card-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            color: #858796;
            margin-bottom: 0.2rem;
        }
        
        .summary-card .card-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            color: #5a5c69;
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
        
        /* Grupo Cards */
        .group-card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        
        .group-card:hover {
            transform: translateY(-5px);
        }
        
        .group-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            background-color: #f8f9fc;
        }
        
        .group-card .group-title {
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        
        .group-card .group-title i {
            margin-right: 0.75rem;
            color: var(--telegram-color);
        }
        
        .group-card .card-body {
            padding: 1.25rem;
        }
        
        .group-info {
            margin-bottom: 1rem;
        }
        
        .group-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .group-meta-item {
            display: inline-flex;
            align-items: center;
            font-size: 0.85rem;
            color: var(--dark-color);
        }
        
        .group-meta-item i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .group-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .badge-group {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        .badge-pg-soft {
            background-color: rgba(133, 64, 245, 0.1);
            color: var(--purple-color);
        }
        
        .badge-pragmatic {
            background-color: rgba(54, 185, 204, 0.1);
            color: var(--info-color);
        }
        
        .badge-vip {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
        }
        
        .badge-comum {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        .badge-active {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        .badge-inactive {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }
        
        .group-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-action {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: none;
            color: white;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            filter: brightness(1.1);
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
        
        /* Cards Grid Layout */
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.5rem;
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
            
            .groups-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
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
                <!-- Cards de Resumo -->
                <div class="dashboard-summary">
                    <div class="summary-card blue">
                        <div class="card-inner">
                            <div class="summary-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="card-info">
                                <div class="card-title">Total de Grupos</div>
                                <div class="card-value"><?php echo $total_count; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-card green">
                        <div class="card-inner">
                            <div class="summary-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="card-info">
                                <div class="card-title">Grupos Ativos</div>
                                <div class="card-value"><?php echo $active_count; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-card purple">
                        <div class="card-inner">
                            <div class="summary-icon">
                                <i class="fas fa-dice"></i>
                            </div>
                            <div class="card-info">
                                <div class="card-title">PG Soft</div>
                                <div class="card-value"><?php echo $pg_soft_count; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-card info">
                        <div class="card-inner">
                            <div class="summary-icon">
                                <i class="fas fa-gamepad"></i>
                            </div>
                            <div class="card-info">
                                <div class="card-title">Pragmatic</div>
                                <div class="card-value"><?php echo $pragmatic_count; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-card yellow">
                        <div class="card-inner">
                            <div class="summary-icon">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div class="card-info">
                                <div class="card-title">Grupos VIP</div>
                                <div class="card-value"><?php echo $vip_count; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-card teal">
                        <div class="card-inner">
                            <div class="summary-icon">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <div class="card-info">
                                <div class="card-title">Grupos Comum</div>
                                <div class="card-value"><?php echo $comum_count; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cabeçalho da página -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Gerenciar Grupos do Telegram</h1>
                    
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo Grupo
                    </a>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Filtros e Pesquisa -->
                <div class="filters-container mb-4">
                    <div class="filters-nav">
                        <!-- Filtros por tipo e status -->
                        <a href="index.php" class="filter-item <?php echo empty($type_filter) && empty($level_filter) && empty($status_filter) ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> Todos <span class="badge <?php echo empty($type_filter) && empty($level_filter) && empty($status_filter) ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $total_count; ?></span>
                        </a>
                        
                        <a href="index.php?type=pg_soft" class="filter-item <?php echo $type_filter == 'pg_soft' ? 'active' : ''; ?>">
                            <i class="fas fa-dice"></i> PG Soft <span class="badge <?php echo $type_filter == 'pg_soft' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $pg_soft_count; ?></span>
                        </a>
                        
                        <a href="index.php?type=pragmatic" class="filter-item <?php echo $type_filter == 'pragmatic' ? 'active' : ''; ?>">
                            <i class="fas fa-gamepad"></i> Pragmatic <span class="badge <?php echo $type_filter == 'pragmatic' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $pragmatic_count; ?></span>
                        </a>
                        
                        <a href="index.php?level=vip" class="filter-item <?php echo $level_filter == 'vip' ? 'active' : ''; ?>">
                            <i class="fas fa-crown"></i> VIP <span class="badge <?php echo $level_filter == 'vip' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $vip_count; ?></span>
                        </a>
                        
                        <a href="index.php?level=comum" class="filter-item <?php echo $level_filter == 'comum' ? 'active' : ''; ?>">
                            <i class="fas fa-user-friends"></i> Comum <span class="badge <?php echo $level_filter == 'comum' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $comum_count; ?></span>
                        </a>
                        
                        <a href="index.php?status=active" class="filter-item <?php echo $status_filter == 'active' ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle"></i> Ativos <span class="badge <?php echo $status_filter == 'active' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $active_count; ?></span>
                        </a>
                        
                        <a href="index.php?status=inactive" class="filter-item <?php echo $status_filter == 'inactive' ? 'active' : ''; ?>">
                            <i class="fas fa-times-circle"></i> Inativos <span class="badge <?php echo $status_filter == 'inactive' ? 'bg-white text-primary' : 'bg-light text-dark'; ?>"><?php echo $inactive_count; ?></span>
                        </a>
                    </div>
                    
                    <form action="index.php" method="get" class="d-flex">
                        <?php if (!empty($type_filter)): ?>
                        <input type="hidden" name="type" value="<?php echo $type_filter; ?>">
                        <?php endif; ?>
                        
                        <?php if (!empty($level_filter)): ?>
                        <input type="hidden" name="level" value="<?php echo $level_filter; ?>">
                        <?php endif; ?>
                        
                        <?php if (!empty($status_filter)): ?>
                        <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                        <?php endif; ?>
                        
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Pesquisar grupos..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                            
                            <?php if (!empty($search)): ?>
                            <a href="index.php<?php echo !empty($type_filter) || !empty($level_filter) || !empty($status_filter) ? '?' . http_build_query(array_filter(['type' => $type_filter, 'level' => $level_filter, 'status' => $status_filter])) : ''; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Lista de Grupos em Cards -->
                <div class="groups-grid">
                    <?php if ($groups && $groups->num_rows > 0): ?>
                        <?php while ($group = $groups->fetch_assoc()): ?>
                        <div class="group-card">
                            <div class="card-header">
                                <h5 class="group-title">
                                    <i class="fas fa-users"></i>
                                    <?php echo htmlspecialchars($group['name']); ?>
                                </h5>
                                
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="edit.php?id=<?php echo $group['id']; ?>"><i class="fas fa-edit me-2 text-primary"></i> Editar</a></li>
                                        
                                        <?php if ($group['status'] == 'active'): ?>
                                        <li>
                                            <button class="dropdown-item text-warning" type="button" data-bs-toggle="modal" data-bs-target="#toggleStatusModal" 
                                                data-group-id="<?php echo $group['id']; ?>" 
                                                data-group-name="<?php echo htmlspecialchars($group['name']); ?>"
                                                data-status="<?php echo $group['status']; ?>"
                                                data-action="desativar">
                                                <i class="fas fa-times-circle me-2 text-warning"></i> Desativar
                                            </button>
                                        </li>
                                        <?php else: ?>
                                        <li>
                                            <button class="dropdown-item text-success" type="button" data-bs-toggle="modal" data-bs-target="#toggleStatusModal" 
                                                data-group-id="<?php echo $group['id']; ?>" 
                                                data-group-name="<?php echo htmlspecialchars($group['name']); ?>"
                                                data-status="<?php echo $group['status']; ?>"
                                                data-action="ativar">
                                                <i class="fas fa-check-circle me-2 text-success"></i> Ativar
                                            </button>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                data-group-id="<?php echo $group['id']; ?>"
                                                data-group-name="<?php echo htmlspecialchars($group['name']); ?>">
                                                <i class="fas fa-trash me-2 text-danger"></i> Excluir
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="group-info">
                                    <p class="mb-2"><?php echo !empty($group['description']) ? htmlspecialchars($group['description']) : '<span class="text-muted">Sem descrição</span>'; ?></p>
                                </div>
                                
                                <div class="group-meta">
                                    <span class="group-meta-item" title="ID do Grupo no Telegram">
                                        <i class="fab fa-telegram"></i>
                                        <?php echo htmlspecialchars($group['group_id']); ?>
                                    </span>
                                    
                                    <span class="group-meta-item" title="Frequência de Sinais">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $group['signal_frequency']; ?> minutos
                                    </span>
                                    
                                    <span class="group-meta-item" title="Tempo de Execução">
                                        <i class="fas fa-hourglass-half"></i>
                                        <?php echo $group['min_minutes'] . '-' . $group['max_minutes']; ?> minutos
                                    </span>
                                </div>
                                
                                <div class="group-badges">
                                    <!-- Tipo de Sinal -->
                                    <?php if ($group['type'] == 'pg_soft'): ?>
                                    <span class="badge-group badge-pg-soft">
                                        <i class="fas fa-dice"></i> PG Soft
                                    </span>
                                    <?php elseif ($group['type'] == 'pragmatic'): ?>
                                    <span class="badge-group badge-pragmatic">
                                        <i class="fas fa-gamepad"></i> Pragmatic
                                    </span>
                                    <?php else: ?>
                                    <span class="badge-group bg-light text-dark">
                                        <i class="fas fa-question-circle"></i> Não definido
                                    </span>
                                    <?php endif; ?>
                                    
                                    <!-- Nível do Grupo -->
                                    <?php if ($group['level'] == 'vip'): ?>
                                    <span class="badge-group badge-vip">
                                        <i class="fas fa-crown"></i> VIP
                                    </span>
                                    <?php else: ?>
                                    <span class="badge-group badge-comum">
                                        <i class="fas fa-user-friends"></i> Comum
                                    </span>
                                    <?php endif; ?>
                                    
                                    <!-- Status -->
                                    <?php if ($group['status'] == 'active'): ?>
                                    <span class="badge-group badge-active">
                                        <i class="fas fa-check-circle"></i> Ativo
                                    </span>
                                    <?php else: ?>
                                    <span class="badge-group badge-inactive">
                                        <i class="fas fa-times-circle"></i> Inativo
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="group-actions">
                                    <a href="edit.php?id=<?php echo $group['id']; ?>" class="btn btn-action" style="background-color: var(--primary-color);" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($group['status'] == 'active'): ?>
                                    <button type="button" class="btn btn-action" style="background-color: var(--warning-color);" data-bs-toggle="modal" data-bs-target="#toggleStatusModal" 
                                        data-group-id="<?php echo $group['id']; ?>" 
                                        data-group-name="<?php echo htmlspecialchars($group['name']); ?>"
                                        data-status="<?php echo $group['status']; ?>"
                                        data-action="desativar"
                                        title="Desativar">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-action" style="background-color: var(--success-color);" data-bs-toggle="modal" data-bs-target="#toggleStatusModal" 
                                        data-group-id="<?php echo $group['id']; ?>" 
                                        data-group-name="<?php echo htmlspecialchars($group['name']); ?>"
                                        data-status="<?php echo $group['status']; ?>"
                                        data-action="ativar"
                                        title="Ativar">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-action" style="background-color: var(--danger-color);" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                        data-group-id="<?php echo $group['id']; ?>"
                                        data-group-name="<?php echo htmlspecialchars($group['name']); ?>"
                                        title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <?php if (!empty($search) || !empty($type_filter) || !empty($level_filter) || !empty($status_filter)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-search fa-3x mb-3 text-secondary"></i>
                                    <h5>Nenhum grupo encontrado com os filtros aplicados</h5>
                                    <p class="text-muted">Tente modificar sua pesquisa ou limpar os filtros</p>
                                    <a href="index.php" class="btn btn-outline-primary mt-3">Limpar Filtros</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-3x mb-3 text-secondary"></i>
                                    <h5>Nenhum grupo cadastrado</h5>
                                    <p class="text-muted">Comece adicionando seu primeiro grupo do Telegram</p>
                                    <a href="add.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-plus"></i> Adicionar Grupo
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container mt-4">
                    <div class="pagination-info">
                        Mostrando <?php echo min(($page - 1) * $per_page + 1, $total_groups); ?> a <?php echo min($page * $per_page, $total_groups); ?> de <?php echo $total_groups; ?> grupos
                    </div>
                    
                    <nav aria-label="Paginação">
                        <ul class="pagination">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . $type_filter : ''; ?><?php echo !empty($level_filter) ? '&level=' . $level_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?>">
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
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . $type_filter : ''; ?><?php echo !empty($level_filter) ? '&level=' . $level_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($type_filter) ? '&type=' . $type_filter : ''; ?><?php echo !empty($level_filter) ? '&level=' . $level_filter : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?>">
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
    
    <!-- Modal de Confirmação para Ativar/Desativar -->
    <div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="statusIconBox" class="mb-4 mx-auto" style="width: 80px; height: 80px; background-color: var(--warning-color); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-question-circle" style="font-size: 2.5rem; color: white;"></i>
                    </div>
                    <h5 class="modal-title mb-3" id="toggleStatusTitle">Confirmar Ação</h5>
                    <p id="toggleStatusMessage">Tem certeza que deseja alterar o status deste grupo?</p>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="group_id" id="toggleStatusGroupId">
                        <input type="hidden" name="group_name" id="toggleStatusGroupName">
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
    
    <!-- Modal de Confirmação para Excluir -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4 mx-auto" style="width: 80px; height: 80px; background-color: var(--danger-color); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2.5rem; color: white;"></i>
                    </div>
                    <h5 class="modal-title mb-3">Excluir Grupo</h5>
                    <p>Tem certeza que deseja excluir o grupo <strong id="deleteGroupName"></strong>?</p>
                    <p class="text-danger small">Esta ação não pode ser desfeita! Todas as conexões deste grupo serão removidas.</p>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="group_id" id="deleteGroupId">
                        <input type="hidden" name="group_name" id="deleteFormGroupName">
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Excluir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts JavaScript -->
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
                var groupId = button.getAttribute('data-group-id');
                var groupName = button.getAttribute('data-group-name');
                var currentStatus = button.getAttribute('data-status');
                var action = button.getAttribute('data-action');
                
                // Atualiza o modal
                var title = document.getElementById('toggleStatusTitle');
                var message = document.getElementById('toggleStatusMessage');
                var iconBox = document.getElementById('statusIconBox');
                var confirmBtn = document.getElementById('toggleStatusBtn');
                
                // Preenche os campos do formulário
                document.getElementById('toggleStatusGroupId').value = groupId;
                document.getElementById('toggleStatusGroupName').value = groupName;
                document.getElementById('toggleStatusCurrentStatus').value = currentStatus;
                
                // Personaliza com base na ação
                if (action === 'ativar') {
                    title.textContent = 'Ativar Grupo';
                    message.innerHTML = 'Deseja ativar o grupo <strong>' + groupName + '</strong>?';
                    iconBox.style.backgroundColor = 'var(--success-color)';
                    iconBox.innerHTML = '<i class="fas fa-check-circle" style="font-size: 2.5rem; color: white;"></i>';
                    confirmBtn.className = 'btn btn-success';
                    confirmBtn.textContent = 'Ativar';
                } else {
                    title.textContent = 'Desativar Grupo';
                    message.innerHTML = 'Deseja desativar o grupo <strong>' + groupName + '</strong>?';
                    iconBox.style.backgroundColor = 'var(--warning-color)';
                    iconBox.innerHTML = '<i class="fas fa-times-circle" style="font-size: 2.5rem; color: white;"></i>';
                    confirmBtn.className = 'btn btn-warning';
                    confirmBtn.textContent = 'Desativar';
                }
            });
        }
        
        // Configurações para o modal de exclusão
        var deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var groupId = button.getAttribute('data-group-id');
                var groupName = button.getAttribute('data-group-name');
                
                document.getElementById('deleteGroupId').value = groupId;
                document.getElementById('deleteFormGroupName').value = groupName;
                document.getElementById('deleteGroupName').textContent = groupName;
            });
        }
    });
    </script>
</body>
</html>