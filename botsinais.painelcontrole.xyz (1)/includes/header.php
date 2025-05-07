<?php
// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . (isset($base_path) ? $base_path : '../') . 'index.php');
    exit;
}

// Obter informações da sessão
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Obter configurações do site
$siteName = getSetting($conn, 'site_name') ?: 'BotDeSinais';
$siteLogo = getSetting($conn, 'site_logo') ?: 'logo.png';

// Definir título da página
$pageTitle = $pageTitle ?? $siteName;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
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
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            min-height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 70px;
            z-index: 100;
            box-shadow: 0 10px 20px rgba(0,0,0,0.19), 0 6px 6px rgba(0,0,0,0.23);
            transition: all 0.3s;
        }
        
        .sidebar.collapsed {
            margin-left: -250px;
        }
        
        .sidebar-brand {
            height: 70px;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            background: rgba(0,0,0,0.1);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            z-index: 101;
        }
        
        .sidebar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        
        .sidebar-brand h2 {
            color: white;
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            height: 70px;
            margin-left: 250px;
            transition: all 0.3s;
        }
        
        .navbar.expanded {
            margin-left: 0;
        }
        
        main {
            margin-left: 250px;
            padding-top: 90px;
            padding-bottom: 30px;
            transition: all 0.3s;
        }
        
        main.expanded {
            margin-left: 0;
        }
        
        .nav-item {
            margin-bottom: 5px;
        }
        
        .nav-link {
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            font-weight: 500;
            border-radius: 5px;
            margin: 0 15px;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .nav-link i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .user-dropdown img {
            height: 40px;
            width: 40px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .dropdown-item {
            padding: 8px 15px;
            border-radius: 5px;
            margin: 5px;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background-color: rgba(78, 115, 223, 0.1);
        }
        
        .dropdown-item i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
    </style>
    
    <?php if (isset($customCss)): ?>
    <link rel="stylesheet" href="<?php echo $customCss; ?>">
    <?php endif; ?>
</head>
<body>
    <!-- Barra lateral -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="<?= isset($basePath) ? $basePath : '../'; ?>assets/img/<?php echo $siteLogo; ?>" alt="<?php echo $siteName; ?>">
            <h2><?php echo $siteName; ?></h2>
        </div>
        
        <ul class="nav flex-column mt-4">
            <?php if ($role == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>admin/dashboard.php">
                    <i class="fas fa-tachometer-alt" style="color: var(--info-color);"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/bots/') !== false ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>admin/bots/">
                    <i class="fas fa-robot" style="color: var(--success-color);"></i>
                    Bots
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>admin/users/">
                    <i class="fas fa-users" style="color: var(--warning-color);"></i>
                    Usuários
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/games/') !== false ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>admin/games/">
                    <i class="fas fa-gamepad" style="color: var(--purple-color);"></i>
                    Jogos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/platforms/') !== false ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>admin/platforms/">
                    <i class="fas fa-external-link-alt" style="color: var(--pink-color);"></i>
                    Plataformas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/channels/') !== false ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>admin/channels/">
                    <i class="fab fa-telegram" style="color: var(--info-color);"></i>
                    Canais e Grupos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/signals/') !== false ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>admin/signals/">
                    <i class="fas fa-signal" style="color: var(--teal-color);"></i>
                    Sinais
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/settings/') !== false ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>admin/settings/">
                    <i class="fas fa-cogs" style="color: var(--orange-color);"></i>
                    Configurações
                </a>
            </li>
            <?php else: ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>user/dashboard.php">
                    <i class="fas fa-tachometer-alt" style="color: var(--info-color);"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/bots/') !== false ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>user/bots/">
                    <i class="fas fa-robot" style="color: var(--success-color);"></i>
                    Meus Bots
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/channels/') !== false ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>user/channels/">
                    <i class="fab fa-telegram" style="color: var(--info-color);"></i>
                    Canais e Grupos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/signals/') !== false ? 'active' : ''; ?>" href="<?= isset($basePath) ? $basePath : ''; ?>user/signals/">
                    <i class="fas fa-signal" style="color: var(--teal-color);"></i>
                    Sinais
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item mt-4">
                <a class="nav-link" href="<?= isset($basePath) ? $basePath : ''; ?>logout.php">
                    <i class="fas fa-sign-out-alt" style="color: var(--danger-color);"></i>
                    Sair
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Barra de navegação superior -->
    <nav class="navbar navbar-expand navbar-light" id="topNav">
        <div class="container-fluid">
            <button class="btn btn-link sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="d-none d-md-inline-block me-2"><?php echo $username; ?></span>
                        <div class="user-dropdown">
                            <img src="<?= isset($basePath) ? $basePath : '../'; ?>assets/img/user.png" alt="User">
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="<?= isset($basePath) ? $basePath : '../'; ?><?php echo $role; ?>/profile.php"><i class="fas fa-user-circle"></i> Meu Perfil</a></li>
                        <?php if ($role == 'admin'): ?>
                        <li><a class="dropdown-item" href="<?= isset($basePath) ? $basePath : ''; ?>admin/settings/"><i class="fas fa-cog"></i> Configurações</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= isset($basePath) ? $basePath : '../'; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>