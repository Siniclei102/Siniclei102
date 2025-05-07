<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Verificar estrutura da tabela primeiro
$table_check = $conn->query("SHOW COLUMNS FROM platforms LIKE 'image_url'");
$has_image_url = $table_check->num_rows > 0;

// Variáveis para alertas e mensagens
$alert = "";
$alert_type = "";

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar ou editar plataforma
    if (isset($_POST['action']) && ($_POST['action'] == 'add' || $_POST['action'] == 'edit')) {
        $name = trim($_POST['name']);
        $url = trim($_POST['url']);
        $status = isset($_POST['status']) ? 'active' : 'inactive';
        $id = isset($_POST['platform_id']) ? (int)$_POST['platform_id'] : 0;
        
        // Validar dados
        if (empty($name) || empty($url)) {
            $alert = "Nome e URL são obrigatórios.";
            $alert_type = "danger";
        } else {
            // Processar o upload da imagem (apenas se a coluna existir)
            $image_url = null;
            $upload_success = false;
            
            if ($has_image_url && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (in_array($_FILES['image']['type'], $allowed_types)) {
                    // Gerar nome único para o arquivo
                    $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $image_name = uniqid() . '.' . $extension;
                    $upload_dir = '../assets/img/platforms/';
                    
                    // Criar diretório se não existir
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Mover o arquivo
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name)) {
                        $image_url = 'platforms/' . $image_name;
                        $upload_success = true;
                    } else {
                        $alert = "Falha ao fazer upload da imagem.";
                        $alert_type = "danger";
                    }
                } else {
                    $alert = "Tipo de arquivo não permitido. Use apenas JPG, PNG ou GIF.";
                    $alert_type = "danger";
                }
            }
            
            // Se não houver erro de upload, continuar com a inserção/atualização no banco
            if (empty($alert)) {
                if ($_POST['action'] == 'add') {
                    // Adicionar nova plataforma
                    if ($has_image_url && $upload_success) {
                        $stmt = $conn->prepare("INSERT INTO platforms (name, url, image_url, status) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssss", $name, $url, $image_url, $status);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO platforms (name, url, status) VALUES (?, ?, ?)");
                        $stmt->bind_param("sss", $name, $url, $status);
                    }
                    
                    if ($stmt->execute()) {
                        $alert = "Plataforma adicionada com sucesso!";
                        $alert_type = "success";
                    } else {
                        $alert = "Erro ao adicionar plataforma: " . $conn->error;
                        $alert_type = "danger";
                    }
                } else {
                    // Editar plataforma existente
                    if ($has_image_url && $upload_success) {
                        // Se enviou nova imagem
                        $stmt = $conn->prepare("UPDATE platforms SET name = ?, url = ?, image_url = ?, status = ? WHERE id = ?");
                        $stmt->bind_param("ssssi", $name, $url, $image_url, $status, $id);
                    } else {
                        // Se não enviou nova imagem ou não tem suporte a imagem
                        $stmt = $conn->prepare("UPDATE platforms SET name = ?, url = ?, status = ? WHERE id = ?");
                        $stmt->bind_param("sssi", $name, $url, $status, $id);
                    }
                    
                    if ($stmt->execute()) {
                        $alert = "Plataforma atualizada com sucesso!";
                        $alert_type = "success";
                    } else {
                        $alert = "Erro ao atualizar plataforma: " . $conn->error;
                        $alert_type = "danger";
                    }
                }
            }
        }
    }
    
    // Ativar/Desativar plataforma
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_status') {
        $id = (int)$_POST['platform_id'];
        $new_status = $_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE platforms SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        
        if ($stmt->execute()) {
            $status_text = $new_status == 'active' ? 'ativada' : 'desativada';
            $alert = "Plataforma $status_text com sucesso!";
            $alert_type = "success";
        } else {
            $alert = "Erro ao alterar status da plataforma: " . $conn->error;
            $alert_type = "danger";
        }
    }
    
    // Excluir plataforma
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = (int)$_POST['platform_id'];
        
        // Verificar se a plataforma está sendo usada em sinais
        $table_check = $conn->query("SHOW TABLES LIKE 'signal_queue'");
        if ($table_check->num_rows > 0) {
            // A tabela signal_queue existe, verificar referências
            $check = $conn->prepare("SELECT COUNT(*) as count FROM signal_queue WHERE platform_id = ?");
            if ($check) {
                $check->bind_param("i", $id);
                $check->execute();
                $result = $check->get_result();
                $usage = $result->fetch_assoc();
                
                if ($usage['count'] > 0) {
                    $alert = "Esta plataforma não pode ser excluída pois está sendo usada em sinais.";
                    $alert_type = "danger";
                } else {
                    // Processar exclusão
                    $delete_platform = true;
                }
            } else {
                // Erro ao preparar a consulta, mas prossiga com a exclusão
                $delete_platform = true;
            }
        } else {
            // A tabela signal_queue não existe, prosseguir com exclusão
            $delete_platform = true;
        }
        
        // Executar exclusão se necessário
        if (isset($delete_platform) && $delete_platform) {
            // Excluir a imagem, se existir e a coluna existir
            if ($has_image_url) {
                $img_stmt = $conn->prepare("SELECT image_url FROM platforms WHERE id = ?");
                $img_stmt->bind_param("i", $id);
                $img_stmt->execute();
                $img_result = $img_stmt->get_result();
                
                if ($img_result->num_rows > 0) {
                    $platform = $img_result->fetch_assoc();
                    if (!empty($platform['image_url'])) {
                        $image_path = '../assets/img/' . $platform['image_url'];
                        if (file_exists($image_path)) {
                            unlink($image_path);
                        }
                    }
                }
            }
            
            // Excluir plataforma
            $stmt = $conn->prepare("DELETE FROM platforms WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $alert = "Plataforma excluída com sucesso!";
                $alert_type = "success";
            } else {
                $alert = "Erro ao excluir plataforma: " . $conn->error;
                $alert_type = "danger";
            }
        }
    }
    
    // Processar importação de plataformas padrão
    if (isset($_POST['action']) && $_POST['action'] == 'import_default') {
        // Plataformas predefinidas para importação
        $default_platforms = [
            ['Bet365', 'https://bet365.com', 'active'],
            ['Betano', 'https://betano.com', 'active'],
            ['KTO', 'https://kto.com', 'active'],
            ['Estrelabet', 'https://estrelabet.com', 'active'],
            ['Parimatch', 'https://parimatch.com', 'active']
        ];
        
        $imported = 0;
        $errors = 0;
        
        foreach ($default_platforms as $platform) {
            // Verificar se a plataforma já existe
            $check = $conn->prepare("SELECT COUNT(*) as count FROM platforms WHERE name = ?");
            $check->bind_param("s", $platform[0]);
            $check->execute();
            $result = $check->get_result();
            $exists = $result->fetch_assoc()['count'] > 0;
            
            if (!$exists) {
                $stmt = $conn->prepare("INSERT INTO platforms (name, url, status) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $platform[0], $platform[1], $platform[2]);
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $errors++;
                }
            }
        }
        
        if ($imported > 0) {
            $alert = "$imported plataformas importadas com sucesso!";
            $alert_type = "success";
        } else if ($errors > 0) {
            $alert = "Erro ao importar plataformas.";
            $alert_type = "danger";
        } else {
            $alert = "Nenhuma plataforma foi importada. Pode ser que já existam plataformas com os mesmos nomes.";
            $alert_type = "info";
        }
    }
}

// Obter dados para edição, se necessário
$editing = false;
$platform_data = [
    'id' => '',
    'name' => '',
    'url' => '',
    'status' => 'active'
];

if ($has_image_url) {
    $platform_data['image_url'] = '';
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM platforms WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $platform_data = $result->fetch_assoc();
        $editing = true;
    }
}

// Obter todas as plataformas para exibição
$platforms = [];
$result = $conn->query("SELECT * FROM platforms ORDER BY status='active' DESC, name ASC");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $platforms[] = $row;
    }
}

// Título da página
$pageTitle = $editing ? "Editar Plataforma" : "Gerenciar Plataformas";

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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
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
        
        /* Resto dos estilos... */
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
                <h2><?php echo $siteName; ?> <span class="admin-badge">Admin</span></h2>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-tachometer-alt" style="color: var(--danger-color);"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <div class="menu-header">Gerenciamento</div>
                
                <li>
                    <a href="users/">
                        <i class="fas fa-users" style="color: var(--primary-color);"></i>
                        <span>Usuários</span>
                    </a>
                </li>
                <li>
                    <a href="bots/">
                        <i class="fas fa-robot" style="color: var(--success-color);"></i>
                        <span>Bots</span>
                    </a>
                </li>
                <li>
                    <a href="games/">
                        <i class="fas fa-gamepad" style="color: var(--warning-color);"></i>
                        <span>Jogos</span>
                    </a>
                </li>
                <li>
                    <a href="platforms/">
                        <i class="fas fa-desktop" style="color: var(--info-color);"></i>
                        <span>Plataformas</span>
                    </a>
                </li>
                
                <div class="menu-header">Configurações</div>
                
                <li>
                    <a href="settings/">
                        <i class="fas fa-cog" style="color: var(--purple-color);"></i>
                        <span>Configurações</span>
                    </a>
                </li>
                <li>
                    <a href="logs/">
                        <i class="fas fa-clipboard-list" style="color: var(--teal-color);"></i>
                        <span>Logs do Sistema</span>
                    </a>
                </li>
                
                <div class="menu-divider"></div>
                
                <li>

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
                
                <div class="topbar-admin-badge">Admin</div>
                
                <div class="topbar-user">
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-md-inline-block me-1"><?php echo $_SESSION['username']; ?></span>
                            <img src="../assets/img/admin-avatar.png" alt="Admin">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Configurações</a></li>
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
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo $pageTitle; ?></li>
                        </ol>
                    </nav>
                    
                    <a href="signal-generator.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-signal"></i> Gerador de Sinais
                    </a>
                </div>
                
                <?php if (!empty($alert)): ?>
                <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $alert; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Formulário de Plataforma -->
                    <div class="col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <?php echo $editing ? "Editar Plataforma" : "Adicionar Nova Plataforma"; ?>
                                </h6>
                                <?php if ($editing): ?>
                                <a href="platforms.php" class="btn btn-sm btn-secondary">Cancelar Edição</a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="<?php echo $editing ? 'edit' : 'add'; ?>">
                                    <?php if ($editing): ?>
                                    <input type="hidden" name="platform_id" value="<?php echo $platform_data['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nome da Plataforma*</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($platform_data['name']); ?>" required>
                                        <div class="form-text">Nome da plataforma de apostas ou cassino</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="url" class="form-label">URL da Plataforma*</label>
                                        <input type="url" class="form-control" id="url" name="url" 
                                               value="<?php echo htmlspecialchars($platform_data['url']); ?>" required>
                                        <div class="form-text">Link completo para a plataforma, inclua http:// ou https://</div>
                                    </div>
                                    
                                    <?php if ($has_image_url): ?>
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Logo da Plataforma</label>
                                        <?php if (!empty($platform_data['image_url'])): ?>
                                        <div class="mb-2">
                                            <img src="../assets/img/<?php echo htmlspecialchars($platform_data['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($platform_data['name']); ?>" 
                                                 class="img-thumbnail" style="max-height: 100px;">
                                        </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <div class="form-text">
                                            <?php echo $editing ? "Deixe em branco para manter a imagem atual" : "Opcional: Selecione uma imagem para logo da plataforma"; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="status" name="status" 
                                               <?php echo (!$editing || $platform_data['status'] == 'active') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="status">Plataforma Ativa</label>
                                        <div class="form-text">Apenas plataformas ativas serão usadas nos sinais</div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <?php echo $editing ? "Salvar Alterações" : "Adicionar Plataforma"; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Card de dica -->
                        <div class="card bg-info text-white shadow mb-4">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-lightbulb"></i> Dica</h5>
                                <p class="card-text mb-0">Certifique-se de adicionar pelo menos uma plataforma ativa para que o gerador de sinais funcione corretamente.</p>
                            </div>
                        </div>
                        
                        <!-- Botão de importação rápida -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Importação Rápida</h6>
                            </div>
                            <div class="card-body">
                                <p>Importe rapidamente plataformas populares para uso imediato.</p>
                                <form method="post">
                                    <input type="hidden" name="action" value="import_default">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-file-import"></i> Importar Plataformas Padrão
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Listagem de Plataformas -->
                    <div class="col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Plataformas Cadastradas</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($platforms)): ?>
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle"></i> Nenhuma plataforma cadastrada. Adicione sua primeira plataforma usando o formulário ao lado.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="platformsTable" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <?php if ($has_image_url): ?>
                                                    <th style="width: 60px;">Logo</th>
                                                    <?php endif; ?>
                                                    <th>Nome</th>
                                                    <th>URL</th>
                                                    <th style="width: 80px;">Status</th>
                                                    <th style="width: 120px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($platforms as $platform): ?>
                                                <tr>
                                                    <?php if ($has_image_url): ?>
                                                    <td class="text-center">
                                                        <?php if (!empty($platform['image_url'])): ?>
                                                            <img src="../assets/img/<?php echo htmlspecialchars($platform['image_url']); ?>"
                                                                 alt="<?php echo htmlspecialchars($platform['name']); ?>"
                                                                 class="img-thumbnail" style="max-height: 40px;">
                                                        <?php else: ?>
                                                            <i class="fas fa-globe fa-2x text-muted"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <?php endif; ?>
                                                    <td><?php echo htmlspecialchars($platform['name']); ?></td>
                                                    <td>
                                                        <a href="<?php echo htmlspecialchars($platform['url']); ?>" 
                                                           target="_blank" rel="noopener noreferrer">
                                                            <?php echo htmlspecialchars($platform['url']); ?>
                                                            <i class="fas fa-external-link-alt ms-1 small"></i>
                                                        </a>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?php echo $platform['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                            <?php echo $platform['status'] == 'active' ? 'Ativo' : 'Inativo'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="platforms.php?edit=<?php echo $platform['id']; ?>" 
                                                               class="btn btn-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Deseja alterar o status desta plataforma?')">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="platform_id" value="<?php echo $platform['id']; ?>">
                                                                <input type="hidden" name="new_status" 
                                                                       value="<?php echo $platform['status'] == 'active' ? 'inactive' : 'active'; ?>">
                                                                <button type="submit" class="btn btn-<?php echo $platform['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                                    <i class="fas fa-<?php echo $platform['status'] == 'active' ? 'ban' : 'check'; ?>"></i>
                                                                </button>
                                                            </form>
                                                            
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta plataforma?')">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="platform_id" value="<?php echo $platform['id']; ?>">
                                                                <button type="submit" class="btn btn-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
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
        
        // Event listeners
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
        
        // DataTable para a tabela de plataformas
        if (document.getElementById('platformsTable')) {
            $('#platformsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json"
                },
                "pageLength": 10
            });
        }
    });
    </script>
</body>
</html>