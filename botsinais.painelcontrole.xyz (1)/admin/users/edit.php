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
    $_SESSION['message'] = "ID de usuário inválido!";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

$user_id = (int)$_GET['id'];

// Mensagens de Feedback
function showMessage($type, $message) {
    $_SESSION['alert_type'] = $type;
    $_SESSION['message'] = $message;
}

// Obter dados do usuário
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Usuário não encontrado
    $_SESSION['message'] = "Usuário não encontrado!";
    $_SESSION['alert_type'] = "warning";
    header('Location: index.php');
    exit;
}

$user = $result->fetch_assoc();

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obter dados do formulário
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $password = trim($_POST['password']);
    $status = $_POST['status'];
    $expiry_date = $_POST['expiry_date'] ? $_POST['expiry_date'] : null;
    
    $errors = [];
    
    // Validar username (obrigatório e único)
    if (empty($username)) {
        $errors[] = "Nome de usuário é obrigatório.";
    } else {
        // Verificar se o username já existe (exceto para o usuário atual)
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Este nome de usuário já está em uso.";
        }
    }
    
    // Validar email (único se fornecido)
    if (!empty($email)) {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Este email já está em uso.";
        }
    }
    
    // Se não há erros, atualizar o usuário
    if (empty($errors)) {
        // Construir a query base
        $query = "UPDATE users SET username = ?, email = ?, full_name = ?, 
                 phone = ?, role = ?, status = ?, expiry_date = ?";
        $params = [$username, $email, $full_name, $phone, $role, $status, $expiry_date];
        $types = "ssssss";
        
        // Adicionar o tipo para expiry_date
        $types .= $expiry_date ? "s" : "s";
        
        // Se senha foi fornecida, incluir na atualização
        if (!empty($password)) {
            // Hash da senha
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query .= ", password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }
        
        // Finalizar a query
        $query .= " WHERE id = ?";
        $params[] = $user_id;
        $types .= "i";
        
        // Preparar e executar a query
        $update_stmt = $conn->prepare($query);
        $update_stmt->bind_param($types, ...$params);
        
        if ($update_stmt->execute()) {
            // Registrar a ação
            logAdminAction($conn, $_SESSION['user_id'], "Atualizou o usuário $username (ID: $user_id)");
            
            showMessage("success", "Usuário atualizado com sucesso!");
            header('Location: view.php?id=' . $user_id);
            exit;
        } else {
            $errors[] = "Erro ao atualizar usuário: " . $conn->error;
        }
    }
}

// Definir título da página
$pageTitle = 'Editar Usuário';

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
        
        /* Form Styling */
        .form-label {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d3e2;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .form-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e3e6f0;
            color: var(--primary-color);
        }
        
        .form-section-title:first-child {
            margin-top: 0;
        }
        
        .form-buttons {
            margin-top: 2rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .btn-rounded {
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
        }
        
        .form-text {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        .required-field::after {
            content: "*";
            color: var(--danger-color);
            margin-left: 4px;
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
                            <li class="breadcrumb-item active" aria-current="page">Editar Usuário</li>
                        </ol>
                    </nav>
                    
                    <div>
                        <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-outline-primary btn-sm me-2">
                            <i class="fas fa-eye me-1"></i> Visualizar
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                </div>
                
                <!-- Formulário de Edição -->
                <div class="info-card">
                    <h5 class="card-title mb-4">
                        <i class="fas fa-user-edit me-2" style="color: var(--primary-color);"></i>
                        Editar Usuário: <?php echo htmlspecialchars($user['username']); ?>
                    </h5>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Erro!</strong> Verifique os seguintes problemas:
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="edit.php?id=<?php echo $user_id; ?>" method="post">
                        <!-- Informações Básicas -->
                        <div class="form-section-title">Informações Básicas</div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label required-field">Nome de Usuário</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Nome Completo</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Acesso e Permissões -->
                        <div class="form-section-title">Acesso e Permissões</div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label required-field">Tipo de Usuário</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user" <?php echo ($user['role'] == 'user') ? 'selected' : ''; ?>>Usuário</option>
                                    <option value="master" <?php echo ($user['role'] == 'master') ? 'selected' : ''; ?>>Master</option>
                                    <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label required-field">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo ($user['status'] == 'active') ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="suspended" <?php echo ($user['status'] == 'suspended') ? 'selected' : ''; ?>>Suspenso</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="expiry_date" class="form-label">Data de Expiração</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo $user['expiry_date'] ?? ''; ?>">
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    <?php if ($user['role'] == 'master'): ?>
                                    Usuários do tipo Master não expiram.
                                    <?php else: ?>
                                    Deixe em branco para usuários sem data de expiração.
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Senha</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Digite para alterar a senha">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    Deixe em branco para manter a senha atual.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botões de Ação -->
                        <div class="form-buttons">
                            <button type="reset" class="btn btn-outline-secondary btn-rounded">
                                <i class="fas fa-undo me-1"></i> Redefinir
                            </button>
                            <button type="submit" class="btn btn-primary btn-rounded">
                                <i class="fas fa-save me-1"></i> Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Rodapé da página -->
                <div class="text-center text-muted mt-4">
                    <p class="small">
                        <i class="fas fa-info-circle me-1"></i>
                        Campos marcados com <span class="text-danger">*</span> são obrigatórios.
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
    
    // Mostrar/ocultar senha
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Alternar ícone
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }
    
    // REMOVIDO: Não desabilitar mais a data de expiração para usuários master
    // Agora todos os tipos de usuário podem ter data de expiração
});
</script>
</body>
</html>