<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Definir título da página
$pageTitle = 'Criar Novo Usuário';

// Definir mensagem
$message = '';
$alertType = '';
$showSuccessModal = false;
$newUserId = 0;
$newUsername = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter dados do formulário
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $telegram = trim($_POST['telegram']);
    $status = $_POST['status'];
    $role = $_POST['role'];
    $expiryDate = $_POST['expiry_date'];
    
    // Validar dados
    $errors = [];
    
    // Validar username
    if (empty($username)) {
        $errors[] = "Nome de usuário é obrigatório";
    } elseif (strlen($username) < 3) {
        $errors[] = "Nome de usuário deve ter pelo menos 3 caracteres";
    } else {
        // Verificar se o username já existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Nome de usuário já está em uso";
        }
    }
    
    // Validar senha
    if (empty($password)) {
        $errors[] = "Senha é obrigatória";
    } elseif (strlen($password) < 6) {
        $errors[] = "Senha deve ter pelo menos 6 caracteres";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "As senhas não coincidem";
    }
    
    // Validar email (se fornecido)
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido";
    }
    
    // Validar data de expiração
    if (empty($expiryDate)) {
        $errors[] = "Data de expiração é obrigatória";
    } else {
        $currentDate = date('Y-m-d');
        if ($expiryDate < $currentDate) {
            $errors[] = "Data de expiração não pode ser no passado";
        }
    }
    
    // Se não houver erros, inserir no banco de dados
    if (empty($errors)) {
        // Hash da senha
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            // Inserir usuário
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, telegram, status, role, created_at, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->bind_param("sssssssss", $username, $hashedPassword, $fullName, $email, $phone, $telegram, $status, $role, $expiryDate);
            
            if ($stmt->execute()) {
                $newUserId = $stmt->insert_id;
                $newUsername = $username;
                
                // Registrar ação de criação de usuário se a função existir
                if (function_exists('logAdminAction')) {
                    logAdminAction($conn, $_SESSION['user_id'], "Criou o usuário $username (ID: {$newUserId})");
                }
                
                $message = "Usuário criado com sucesso!";
                $alertType = "success";
                $showSuccessModal = true;
                
                // Limpar os campos após sucesso
                $username = $fullName = $email = $phone = $telegram = '';
            } else {
                $message = "Erro ao criar usuário: " . $conn->error;
                $alertType = "danger";
            }
        } catch (Exception $e) {
            $message = "Erro ao criar usuário: " . $e->getMessage();
            $alertType = "danger";
        }
    } else {
        $message = "Erros de validação:<ul>";
        foreach ($errors as $error) {
            $message .= "<li>$error</li>";
        }
        $message .= "</ul>";
        $alertType = "danger";
    }
}

// Obter configurações do site
$siteName = getSetting($conn, 'site_name') ?: 'BotDeSinais';
$siteLogo = getSetting($conn, 'site_logo') ?: 'logo.png';

// Data atual adicionada 30 dias para expiração padrão
$defaultExpiryDate = date('Y-m-d', strtotime('+30 days'));
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
        
        /* Formulário de Novo Usuário */
        .form-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .form-card .card-header {
            padding: 1.25rem 1.5rem;
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .form-card .card-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        
        .form-card .card-title i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .form-card .card-body {
            padding: 1.5rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section-title {
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .form-text {
            font-size: 0.85rem;
        }
        
        /* Campos de formulário estilizados */
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #6e7d91;
            font-size: 1rem;
        }
        
        .input-with-icon .form-control {
            padding-left: 40px;
        }
        
        /* Alertas estilizados */
        .alert {
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
        }
        
        /* Botões de Ação */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        /* Copiador de senha */
        .password-container {
            position: relative;
        }
        
        .password-generate-btn {
            position: absolute;
            top: 50%;
            right: 45px;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
        }
        
        .password-toggle-btn {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6e7d91;
            cursor: pointer;
        }
        
        /* Modal de confirmação personalizado */
        .modal-confirm .modal-header {
            border-bottom: none;
            position: relative;
        }
        
        .modal-confirm .modal-content {
            border-radius: 1rem;
            border: none;
        }
        
        .modal-confirm .modal-footer {
            border-top: none;
            justify-content: center;
            gap: 0.5rem;
            padding-bottom: 1.5rem;
        }
        
        .modal-confirm .icon-box {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            border-radius: 50%;
            z-index: 9;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-confirm .icon-box i {
            font-size: 2.5rem;
            color: white;
        }
        
        .modal-confirm .icon-box.icon-success {
            background: var(--success-color);
            box-shadow: 0 0.5rem 1rem rgba(28, 200, 138, 0.5);
        }
        
        .modal-confirm .modal-body {
            text-align: center;
            padding: 1rem;
        }
        
        .modal-confirm .modal-title {
            text-align: center;
            font-size: 1.5rem;
            margin: 1.5rem 0 1rem;
        }
        
        .modal-confirm .btn {
            font-size: 1rem;
            font-weight: 600;
            padding: 0.5rem 2rem;
            border-radius: 50px;
        }
        
        /* Cards coloridos */
        .info-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            text-align: center;
            padding: 1.5rem;
            transition: all 0.3s;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
        }
        
        .info-card .icon-container {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            margin: 0 auto 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .info-card .icon-container i {
            font-size: 1.75rem;
            color: white;
        }
        
        .info-card h5 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .info-card p {
            color: #6e7d91;
            margin-bottom: 1.5rem;
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
            
            /* Quando o menu mobile está ativo, escurece o resto da tela */
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Criar Novo Usuário</h1>
                    <div class="d-flex">
                        <span class="badge bg-dark p-2">
                            <?php echo date('d/m/Y H:i:s'); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Cards informativos -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="icon-container" style="background-color: var(--primary-color);">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h5>Novo Usuário</h5>
                            <p>Crie uma nova conta de usuário com acesso ao sistema de bots.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="icon-container" style="background-color: var(--success-color);">
                                <i class="fas fa-key"></i>
                            </div>
                            <h5>Senhas Seguras</h5>
                            <p>Utilize o gerador para criar senhas seguras ou escolha sua própria.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="icon-container" style="background-color: var(--warning-color);">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h5>Assinatura</h5>
                            <p>Defina a data de expiração da conta do usuário no sistema.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Notificações e Alertas -->
                <?php if (!empty($message) && !$showSuccessModal): ?>
                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                    <i class="fas <?php echo $alertType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Navegação de Formulário -->
                <div class="mb-4">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Usuários</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Criar Novo Usuário</li>
                        </ol>
                    </nav>
                </div>
                
                <!-- Formulário de Criação de Usuário -->
                <div class="form-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-user-plus"></i> Informações do Novo Usuário
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" id="createUserForm">
                            <!-- Seção de Informações de Login -->
                            <div class="form-section">
                                <h6 class="form-section-title">Informações de Login</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="username" class="form-label">Nome de Usuário <span class="text-danger">*</span></label>
                                        <div class="input-with-icon">
                                            <i class="fas fa-user"></i>
                                            <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>
                                        </div>
                                        <div class="form-text">Nome de usuário único para login (min. 3 caracteres)</div>
                                    </div>
                            <div class="col-md-6">
    <label for="role" class="form-label">Tipo de Usuário <span class="text-danger">*</span></label>
    <select class="form-select" id="role" name="role" required>
        <option value="user" selected>Usuário Comum</option>
        <option value="master">Usuário Master</option>
        <option value="admin">Administrador</option>
    </select>
    <div class="form-text">
        <strong>Usuário Comum:</strong> Acessa apenas bots específicos<br>
        <strong>Usuário Master:</strong> Pode criar bots próprios e cadastrar usuários<br>
        <strong>Administrador:</strong> Acesso total ao sistema
    </div>
</div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="password" class="form-label">Senha <span class="text-danger">*</span></label>
                                        <div class="password-container">
                                            <input type="password" class="form-control" id="password" name="password" required>
                                            <button type="button" class="password-generate-btn" id="generatePassword">
                                                <i class="fas fa-magic"></i>
                                            </button>
                                            <button type="button" class="password-toggle-btn" id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Senha com pelo menos 6 caracteres</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label">Confirmar Senha <span class="text-danger">*</span></label>
                                        <div class="password-container">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <button type="button" class="password-toggle-btn" id="toggleConfirmPassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Digite a senha novamente para confirmar</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Seção de Informações Pessoais -->
                            <div class="form-section">
                                <h6 class="form-section-title">Informações Pessoais</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="full_name" class="form-label">Nome Completo</label>
                                        <div class="input-with-icon">
                                            <i class="fas fa-user-tag"></i>
                                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($fullName) ? htmlspecialchars($fullName) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <div class="input-with-icon">
                                            <i class="fas fa-envelope"></i>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label">Telefone</label>
                                        <div class="input-with-icon">
                                            <i class="fas fa-phone"></i>
                                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="telegram" class="form-label">Nome de Usuário do Telegram</label>
                                        <div class="input-with-icon">
                                            <i class="fab fa-telegram"></i>
                                            <input type="text" class="form-control" id="telegram" name="telegram" value="<?php echo isset($telegram) ? htmlspecialchars($telegram) : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Seção de Configurações da Conta -->
                            <div class="form-section">
                                <h6 class="form-section-title">Configurações da Conta</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" selected>Ativo</option>
                                            <option value="suspended">Suspenso</option>
                                        </select>
                                        <div class="form-text">Status inicial da conta</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="expiry_date" class="form-label">Data de Expiração <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo isset($expiryDate) ? $expiryDate : $defaultExpiryDate; ?>" required>
                                        <div class="form-text">Data em que a conta expirará</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Botões de Ação -->
                            <div class="form-actions">
                                <a href="index.php" class="btn btn-light">Cancelar</a>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirmCreateModal">
                                    <i class="fas fa-user-plus me-1"></i> Criar Usuário
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Dicas e Informações Adicionais -->
                <div class="card bg-light border-0 rounded-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-info-circle me-2 text-primary"></i>
                            Dicas para criação de usuários
                        </h5>
                        <ul class="mb-0">
                            <li>Senhas devem ter pelo menos 6 caracteres.</li>
                            <li>Use o botão <i class="fas fa-magic text-primary mx-1"></i> para gerar uma senha forte automaticamente.</li>
                            <li>Usuários com nível <strong>Admin</strong> têm acesso completo ao sistema.</li>
                            <li>A data de expiração determina quando a conta ficará inativa automaticamente.</li>
                            <li>Emails e telefones são opcionais, mas úteis para contato.</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Rodapé da página -->
                <div class="text-center text-muted mt-4">
                    <p class="small">
                        Data e hora atual do servidor: <?php echo date('d/m/Y H:i:s'); ?><br>
                        Usuário logado: <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Confirmação para Criar Usuário -->
    <div class="modal fade" id="confirmCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content modal-confirm">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="icon-box icon-success mb-4">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h5 class="modal-title mb-3">Confirmar Criação</h5>
                    <p>Tem certeza que deseja criar este novo usuário?</p>
                    <p class="small text-muted">Verifique se as informações estão corretas.</p>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" id="submitCreateForm" class="btn btn-success">Confirmar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Sucesso -->
    <?php if ($showSuccessModal): ?>
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-confirm">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="icon-box icon-success mb-4">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h5 class="modal-title mb-3">Usuário Criado com Sucesso!</h5>
                    <p>O usuário <strong><?php echo htmlspecialchars($newUsername); ?></strong> foi criado com sucesso.</p>
                    <div class="modal-footer">
                        <a href="edit.php?id=<?php echo $newUserId; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> Editar Usuário
                        </a>
                        <a href="index.php" class="btn btn-success">
                            <i class="fas fa-users me-1"></i> Lista de Usuários
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const contentWrapper = document.getElementById('content-wrapper');
        const sidebarToggler = document.getElementById('sidebar-toggler');
        const overlay = document.getElementById('overlay');
        
        // Função para verificar se é mobile
        const isMobileDevice = function() {
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
        sidebarToggler.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-visible');
            overlay.classList.remove('active');
        });
        
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
        
        // Funcionalidade de mostrar/ocultar senha
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
        
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
        
        // Funcionalidade de gerar senha aleatória
        const generatePassword = document.getElementById('generatePassword');
        
        generatePassword.addEventListener('click', function() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            let generatedPassword = '';
            
            for (let i = 0; i < 12; i++) {
                generatedPassword += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            password.value = generatedPassword;
            confirmPassword.value = generatedPassword;
            
            // Mostrar a senha gerada temporariamente
            password.setAttribute('type', 'text');
            confirmPassword.setAttribute('type', 'text');
            togglePassword.innerHTML = '<i class="fas fa-eye-slash"></i>';
            toggleConfirmPassword.innerHTML = '<i class="fas fa-eye-slash"></i>';
            
            // Retornar para o modo senha após 3 segundos
            setTimeout(() => {
                password.setAttribute('type', 'password');
                confirmPassword.setAttribute('type', 'password');
                togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
                toggleConfirmPassword.innerHTML = '<i class="fas fa-eye"></i>';
            }, 3000);
        });
        
        // Modal de confirmação para criar usuário
        document.getElementById('submitCreateForm').addEventListener('click', function() {
            document.getElementById('createUserForm').submit();
        });
        
        // Mostrar modal de sucesso se necessário
        <?php if ($showSuccessModal): ?>
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
        <?php endif; ?>
    });
    </script>
</body>
</html>