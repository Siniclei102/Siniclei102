<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header('Location: ../index.php');
    exit;
}

// Obter informações do usuário
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Processar alteração de senha
$passwordMessage = '';
$profileMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Verificar senha atual
    if (!password_verify($currentPassword, $user['password'])) {
        $passwordMessage = '<div class="alert alert-danger">Senha atual incorreta.</div>';
    } elseif ($newPassword != $confirmPassword) {
        $passwordMessage = '<div class="alert alert-danger">As novas senhas não correspondem.</div>';
    } elseif (strlen($newPassword) < 6) {
        $passwordMessage = '<div class="alert alert-danger">A nova senha deve ter pelo menos 6 caracteres.</div>';
    } else {
        // Atualizar senha
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $userId);
        
        if ($updateStmt->execute()) {
            $passwordMessage = '<div class="alert alert-success">Senha atualizada com sucesso!</div>';
        } else {
            $passwordMessage = '<div class="alert alert-danger">Erro ao atualizar senha. Tente novamente.</div>';
        }
    }
}

// Processar atualização de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $telegram = $_POST['telegram'];
    
    $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, telegram = ? WHERE id = ?");
    $updateStmt->bind_param("ssssi", $fullName, $email, $phone, $telegram, $userId);
    
    if ($updateStmt->execute()) {
        $profileMessage = '<div class="alert alert-success">Perfil atualizado com sucesso!</div>';
        
        // Recarregar dados do usuário
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else {
        $profileMessage = '<div class="alert alert-danger">Erro ao atualizar perfil. Tente novamente.</div>';
    }
}

// Definir título da página
$pageTitle = 'Meu Perfil';

// Obter configurações do site
$siteName = getSetting($conn, 'site_name') ?: 'BotDeSinais';
$siteLogo = getSetting($conn, 'site_logo') ?: 'logo.png';

// Calcular dias restantes
$daysRemaining = daysRemaining($user['expiry_date']);
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
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); /* Cor para usuário padrão */
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
        
        /* Perfil Cards */
        .profile-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .profile-header {
            background: linear-gradient(120deg, var(--primary-color), var(--purple-color));
            color: white;
            padding: 3rem 1.5rem 7rem;
            position: relative;
            text-align: center;
        }
        
        .profile-header-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 320'%3E%3Cpath fill='%23ffffff' fill-opacity='1' d='M0,96L48,112C96,128,192,160,288,186.7C384,213,480,235,576,229.3C672,224,768,192,864,186.7C960,181,1056,203,1152,202.7C1248,203,1344,181,1392,170.7L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z'%3E%3C/path%3E%3C/svg%3E");
            background-size: cover;
        }
        
        .profile-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            border: 5px solid white;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 10;
            overflow: hidden;
            background-color: #eaecf4;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar .profile-avatar-icon {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: rgba(0, 0, 0, 0.2);
        }
        
        .profile-username {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 0.25rem;
        }
        
        .profile-role {
            display: inline-block;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .profile-body {
            padding: 3.5rem 1.5rem 1.5rem;
            position: relative;
            margin-top: -60px;
        }
        
        .status-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        
        .status-active {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        .status-expired {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }
        
        .status-warning {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
        }
        
        .profile-info {
            margin: 1.5rem 0;
        }
        
        .info-group {
            margin-bottom: 1.5rem;
        }
        
        .info-group-header {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        
        .info-group-header i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .info-item {
            display: flex;
            margin-bottom: 0.75rem;
            align-items: start;
        }
        
        .info-label {
            min-width: 120px;
            color: #6e7d91;
            font-size: 0.9rem;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        
        /* Botões de Ação */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin: 1.5rem 0;
        }
        
        .action-btn {
            flex: 1;
            text-align: center;
        }
        
        /* Estilos para formulários de perfil */
        .profile-form-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .profile-form-header {
            padding: 1.25rem 1.5rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: var(--dark-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .profile-form-header i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .profile-form-body {
            padding: 1.5rem;
        }
        
        .form-floating > label {
            color: #6e7d91;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
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
                <img src="../assets/img/<?php echo $siteLogo; ?>" alt="<?php echo $siteName; ?>">
                <h2><?php echo $siteName; ?></h2>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt" style="color: var(--info-color);"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="bots/">
                        <i class="fas fa-robot" style="color: var(--success-color);"></i>
                        <span>Meus Bots</span>
                    </a>
                </li>
                <li>
                    <a href="channels/">
                        <i class="fab fa-telegram" style="color: var(--info-color);"></i>
                        <span>Canais e Grupos</span>
                    </a>
                </li>
                <li>
                    <a href="signals/">
                        <i class="fas fa-signal" style="color: var(--teal-color);"></i>
                        <span>Sinais</span>
                    </a>
                </li>
                
                <div class="menu-divider"></div>
                
                <li>
                    <a href="profile.php" class="active">
                        <i class="fas fa-user-circle" style="color: var(--warning-color);"></i>
                        <span>Meu Perfil</span>
                    </a>
                </li>
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
                
                <?php if ($daysRemaining <= 7): ?>
                <div class="alert alert-warning py-1 px-3 mb-0 d-inline-flex align-items-center me-auto">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span>Sua conta expira em <?php echo $daysRemaining; ?> dias</span>
                </div>
                <?php endif; ?>
                
                <div class="topbar-user">
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-md-inline-block me-1"><?php echo $_SESSION['username']; ?></span>
                            <img src="../assets/img/user.png" alt="User">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Meu Perfil</h1>
                    <div class="d-flex align-items-center">
                        <span class="me-2">Data:</span>
                        <span class="badge bg-primary"><?php echo date('d/m/Y'); ?></span>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Coluna Esquerda - Informações do Perfil -->
                    <div class="col-lg-4">
                        <div class="profile-card mb-4">
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <img src="../assets/img/user.png" alt="Perfil">
                                    <div class="profile-avatar-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                                <h2 class="profile-username"><?php echo htmlspecialchars($_SESSION['username']); ?></h2>
                                <div class="profile-role">Usuário</div>
                                <div class="profile-header-overlay"></div>
                            </div>
                            
                            <div class="profile-body">
                                <?php
                                if ($daysRemaining > 7) {
                                    echo '<div class="status-indicator status-active"><i class="fas fa-check-circle me-2"></i> Conta Ativa</div>';
                                } elseif ($daysRemaining > 0) {
                                    echo '<div class="status-indicator status-warning"><i class="fas fa-exclamation-circle me-2"></i> Expira em breve</div>';
                                } else {
                                    echo '<div class="status-indicator status-expired"><i class="fas fa-times-circle me-2"></i> Conta Expirada</div>';
                                }
                                ?>
                                
                                <div class="profile-info">
                                    <div class="info-group">
                                        <div class="info-group-header">
                                            <i class="fas fa-user-tag"></i> Detalhes da Conta
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Nome Completo</div>
                                            <div class="info-value"><?php echo htmlspecialchars($user['full_name'] ?: 'Não informado'); ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Nome de Usuário</div>
                                            <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Status</div>
                                            <div class="info-value">
                                                <?php if ($user['status'] == 'active'): ?>
                                                    <span class="badge bg-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Suspenso</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Data de Criação</div>
                                            <div class="info-value"><?php echo formatDate($user['created_at']); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-group">
                                        <div class="info-group-header">
                                            <i class="fas fa-calendar-alt"></i> Assinatura
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Expira em</div>
                                            <div class="info-value">
                                                <?php echo formatDate($user['expiry_date']); ?>
                                                <span class="ms-2 badge <?php echo $daysRemaining > 7 ? 'bg-success' : ($daysRemaining > 0 ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                                    <?php echo $daysRemaining > 0 ? $daysRemaining . ' dias' : 'Expirada'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-group">
                                        <div class="info-group-header">
                                            <i class="fas fa-address-card"></i> Contato
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Email</div>
                                            <div class="info-value"><?php echo htmlspecialchars($user['email'] ?: 'Não informado'); ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Telefone</div>
                                            <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?: 'Não informado'); ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Telegram</div>
                                            <div class="info-value"><?php echo htmlspecialchars($user['telegram'] ?: 'Não informado'); ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="action-buttons">
                                    <div class="action-btn">
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                                            <i class="fas fa-envelope me-1"></i> Contatar Suporte
                                        </button>
                                    </div>
                                    <?php if ($daysRemaining <= 7): ?>
                                    <div class="action-btn">
                                        <button type="button" class="btn btn-warning">
                                            <i class="fas fa-sync me-1"></i> Renovar Assinatura
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Coluna Direita - Formulários de Atualização -->
                    <div class="col-lg-8">
                        <!-- Formulário de Atualização do Perfil -->
                        <div class="profile-form-card mb-4">
                            <div class="profile-form-header">
                                <div><i class="fas fa-user-edit"></i> Atualizar Informações do Perfil</div>
                            </div>
                            <div class="profile-form-body">
                                <?php echo $profileMessage; ?>
                                
                                <form method="post" action="">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Nome Completo" value="<?php echo htmlspecialchars($user['full_name'] ?: ''); ?>">
                                                <label for="full_name">Nome Completo</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($user['email'] ?: ''); ?>">
                                                <label for="email">Email</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="phone" name="phone" placeholder="Telefone" value="<?php echo htmlspecialchars($user['phone'] ?: ''); ?>">
                                                <label for="phone">Telefone</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="telegram" name="telegram" placeholder="Nome de Usuário do Telegram" value="<?php echo htmlspecialchars($user['telegram'] ?: ''); ?>">
                                                <label for="telegram">Nome de Usuário do Telegram</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Salvar Informações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Formulário de Alteração de Senha -->
                        <div class="profile-form-card">
                            <div class="profile-form-header">
                                <div><i class="fas fa-lock"></i> Alterar Senha</div>
                            </div>
                            <div class="profile-form-body">
                                <?php echo $passwordMessage; ?>
                                
                                <form method="post" action="">
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Senha Atual" required>
                                        <label for="current_password">Senha Atual</label>
                                    </div>
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Nova Senha" required>
                                        <label for="new_password">Nova Senha</label>
                                    </div>
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirmar Nova Senha" required>
                                        <label for="confirm_password">Confirmar Nova Senha</label>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="update_password" class="btn btn-danger">
                                            <i class="fas fa-key me-1"></i> Alterar Senha
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Perguntas Frequentes Card -->
                        <div class="profile-form-card mt-4">
                            <div class="profile-form-header">
                                <div><i class="fas fa-question-circle"></i> Perguntas Frequentes</div>
                            </div>
                            <div class="profile-form-body">
                                <div class="accordion" id="faqAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingOne">
                                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                                Como renovar minha assinatura?
                                            </button>
                                        </h2>
                                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Para renovar sua assinatura, clique no botão "Renovar Assinatura" no seu perfil ou entre em contato com nosso suporte através do botão "Contatar Suporte".
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingTwo">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                                Como adicionar um novo bot?
                                            </button>
                                        </h2>
                                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Para adicionar um novo bot, acesse a seção "Meus Bots" no menu lateral e clique no botão "Novo Bot". Siga as instruções para configurar seu novo bot.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingThree">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                                Como enviar sinais para meus canais?
                                            </button>
                                        </h2>
                                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Para enviar sinais, acesse a seção "Sinais" no menu lateral e clique em "Gerar Sinal". Escolha o bot, o canal, a plataforma e o jogo e defina o horário para envio do sinal.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Contato -->
    <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactModalLabel"><i class="fas fa-envelope me-2"></i> Contatar Suporte</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label for="contactSubject" class="form-label">Assunto</label>
                            <select class="form-select" id="contactSubject">
                                <option>Renovação de Assinatura</option>
                                <option>Problema com Bot</option>
                                <option>Problema com Canais</option>
                                <option>Problema com Sinais</option>
                                <option>Outro Assunto</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="contactMessage" class="form-label">Mensagem</label>
                            <textarea class="form-control" id="contactMessage" rows="5" placeholder="Descreva seu problema ou dúvida"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary">Enviar Mensagem</button>
                </div>
            </div>
        </div>
    </div>
    
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
    });
    </script>
</body>
</html>