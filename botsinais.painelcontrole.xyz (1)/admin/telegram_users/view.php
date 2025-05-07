<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Verificar ID do usuário
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID do usuário não especificado ou inválido.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

$user_id = (int)$_GET['id'];

// Buscar dados do usuário
$user_stmt = $conn->prepare("SELECT * FROM telegram_users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    $_SESSION['message'] = "Usuário não encontrado.";
    $_SESSION['alert_type'] = "danger";
    header('Location: index.php');
    exit;
}

$user = $user_result->fetch_assoc();
$display_name = trim($user['first_name'] . ' ' . $user['last_name']) ?: ($user['username'] ? '@' . $user['username'] : 'Usuário #' . $user['user_id']);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Remover acesso de grupo
        if ($_POST['action'] == 'remove_access') {
            $access_id = (int)$_POST['access_id'];
            
            $update_access = $conn->prepare("UPDATE user_group_access SET status = 'revoked', updated_at = NOW() WHERE id = ? AND user_id = ?");
            $update_access->bind_param("ii", $access_id, $user_id);
            
            if ($update_access->execute()) {
                $_SESSION['message'] = "Acesso removido com sucesso.";
                $_SESSION['alert_type'] = "success";
            } else {
                $_SESSION['message'] = "Erro ao remover acesso.";
                $_SESSION['alert_type'] = "danger";
            }
        }
        
        // Estender acesso de grupo
        if ($_POST['action'] == 'extend_access') {
            $access_id = (int)$_POST['access_id'];
            $new_expires_at = $_POST['new_expires_at'] . ' 23:59:59';
            
            $update_access = $conn->prepare("UPDATE user_group_access SET expires_at = ?, status = 'active', updated_at = NOW() WHERE id = ? AND user_id = ?");
            $update_access->bind_param("sii", $new_expires_at, $access_id, $user_id);
            
            if ($update_access->execute()) {
                $_SESSION['message'] = "Data de expiração atualizada com sucesso.";
                $_SESSION['alert_type'] = "success";
            } else {
                $_SESSION['message'] = "Erro ao atualizar data de expiração.";
                $_SESSION['alert_type'] = "danger";
            }
        }
        
        // Remover todos os acessos
        if ($_POST['action'] == 'remove_all_access') {
            $update_all = $conn->prepare("UPDATE user_group_access SET status = 'revoked', updated_at = NOW() WHERE user_id = ? AND status = 'active'");
            $update_all->bind_param("i", $user_id);
            
            if ($update_all->execute() && $update_all->affected_rows > 0) {
                // Remover status premium
                $update_user = $conn->prepare("UPDATE telegram_users SET premium = 0 WHERE id = ?");
                $update_user->bind_param("i", $user_id);
                $update_user->execute();
                
                $_SESSION['message'] = "Todos os acessos do usuário foram removidos.";
                $_SESSION['alert_type'] = "success";
            } else {
                $_SESSION['message'] = "Não há acessos ativos para remover.";
                $_SESSION['alert_type'] = "warning";
            }
        }
        
        header('Location: view.php?id=' . $user_id);
        exit;
    }
}

// Buscar acessos do usuário
$pg_soft_access = [];
$pragmatic_access = [];

// Garantir que a tabela existe
$table_check = $conn->query("SHOW TABLES LIKE 'user_group_access'");
if ($table_check->num_rows == 0) {
    $conn->query("CREATE TABLE `user_group_access` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `group_id` int(11) NOT NULL,
        `group_type` varchar(50) NOT NULL,
        `expires_at` datetime DEFAULT NULL,
        `status` enum('active','expired','revoked') NOT NULL DEFAULT 'active',
        `created_at` datetime DEFAULT current_timestamp(),
        `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `group_id` (`group_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Buscar acessos ativos
$access_sql = "SELECT a.*, g.name as group_name, g.group_id as telegram_group_id 
               FROM user_group_access a 
               LEFT JOIN telegram_groups g ON a.group_id = g.id 
               WHERE a.user_id = ? AND a.status = 'active'";
$access_stmt = $conn->prepare($access_sql);
$access_stmt->bind_param("i", $user_id);
$access_stmt->execute();
$access_result = $access_stmt->get_result();

while ($access = $access_result->fetch_assoc()) {
    if ($access['group_type'] == 'pg_soft') {
        $pg_soft_access[] = $access;
    } else if ($access['group_type'] == 'pragmatic') {
        $pragmatic_access[] = $access;
    }
}

// Verificar se há algum acesso válido
$has_active_access = !empty($pg_soft_access) || !empty($pragmatic_access);

// Verificar se o status premium está correto
if ($has_active_access && $user['premium'] == 0) {
    $update_premium = $conn->prepare("UPDATE telegram_users SET premium = 1 WHERE id = ?");
    $update_premium->bind_param("i", $user_id);
    $update_premium->execute();
    $user['premium'] = 1;
} else if (!$has_active_access && $user['premium'] == 1) {
    $update_premium = $conn->prepare("UPDATE telegram_users SET premium = 0 WHERE id = ?");
    $update_premium->bind_param("i", $user_id);
    $update_premium->execute();
    $user['premium'] = 0;
}

// Mensagens de Feedback
$message = isset($_SESSION['message']) ? $_SESSION['message'] : null;
$alert_type = isset($_SESSION['alert_type']) ? $_SESSION['alert_type'] : null;

// Limpar as mensagens da sessão
unset($_SESSION['message']);
unset($_SESSION['alert_type']);

// Definir título da página
$pageTitle = 'Detalhes do Usuário: ' . $display_name;
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Estilos personalizados (reuse os mesmos estilos do template) -->
    <style>
        /* Estilos específicos para esta página */
        .access-card {
            border-left: 4px solid transparent;
            transition: all 0.2s;
        }
        
        .access-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
        
        .pg-soft-border {
            border-left-color: #9c27b0;
        }
        
        .pragmatic-border {
            border-left-color: #2196f3;
        }
        
        .pg-soft-color {
            color: #9c27b0;
        }
        
        .pragmatic-color {
            color: #2196f3;
        }
        
        .badge-expiring {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <!-- Overlay para menu mobile -->
    <div class="overlay" id="overlay"></div>
    
    <div class="layout-wrapper">
        <!-- Sidebar - Igual ao template anterior -->
        <nav id="sidebar" class="sidebar">
            <!-- Conteúdo da sidebar... -->
        </nav>
        
        <!-- Conteúdo Principal -->
        <div class="content-wrapper" id="content-wrapper">
            <!-- Barra Superior - Igual ao template anterior -->
            <div class="topbar">
                <!-- Conteúdo da barra superior... -->
            </div>
            
            <!-- Conteúdo da Página -->
            <div class="content">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Usuários do Telegram</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($display_name); ?></li>
                            </ol>
                        </nav>
                        
                        <div>
                            <a href="add_access.php?user_id=<?php echo $user_id; ?>" class="btn btn-success me-2">
                                <i class="fas fa-plus"></i> Adicionar Acesso
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Voltar
                            </a>
                        </div>
                    </div>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Informações do Usuário -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <?php if ($user['premium']): ?>
                                <i class="fas fa-crown text-warning me-1"></i>
                                <?php endif; ?>
                                Informações do Usuário
                            </h6>
                            
                            <?php if ($has_active_access): ?>
                            <form method="post" onsubmit="return confirm('Tem certeza que deseja remover todos os acessos deste usuário?');">
                                <input type="hidden" name="action" value="remove_all_access">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i> Remover Todos os Acessos
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th style="width: 150px">ID do Telegram:</th>
                                            <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Username:</th>
                                            <td>
                                                <?php if ($user['username']): ?>
                                                <a href="https://t.me/<?php echo htmlspecialchars($user['username']); ?>" target="_blank">
                                                    @<?php echo htmlspecialchars($user['username']); ?>
                                                </a>
                                                <?php else: ?>
                                                <span class="text-muted">Não informado</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Nome:</th>
                                            <td><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']) ?: 'Não informado'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th style="width: 150px">Status:</th>
                                            <td>
                                                <?php if ($user['status'] == 'active'): ?>
                                                <span class="badge bg-success">Ativo</span>
                                                <?php elseif ($user['status'] == 'blocked'): ?>
                                                <span class="badge bg-danger">Bloqueado</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo ucfirst($user['status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Acesso VIP:</th>
                                            <td>
                                                <?php if ($user['premium']): ?>
                                                <span class="badge bg-purple text-white">Premium</span>
                                                <?php else: ?>
                                                <span class="badge bg-light text-dark">Regular</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Cadastrado em:</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PG Soft Access -->
                    <h5 class="mb-3">
                        <i class="fas fa-dice pg-soft-color"></i> Acesso aos Sinais PG Soft
                    </h5>
                    
                    <div class="row mb-4">
                        <?php if (count($pg_soft_access) > 0): ?>
                            <?php foreach ($pg_soft_access as $access): 
                                $is_expiring = false;
                                $days_left = 0;
                                
                                if (isset($access['expires_at']) && $access['expires_at']) {
                                    $expiry_date = new DateTime($access['expires_at']);
                                    $today = new DateTime();
                                    $interval = $today->diff($expiry_date);
                                    $days_left = $interval->days;
                                    $is_expiring = $days_left <= 7 && $expiry_date > $today;
                                }
                            ?>
                                <div class="col-lg-6 col-xl-4 mb-3">
                                    <div class="card access-card pg-soft-border shadow-sm">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($access['group_name']); ?></h5>
                                            
                                            <ul class="list-group list-group-flush mb-3">
                                                <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                                                    <span>ID do Grupo:</span>
                                                    <span class="text-monospace"><?php echo htmlspecialchars($access['telegram_group_id']); ?></span>
                                                </li>
                                                <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                                                    <span>Adicionado em:</span>
                                                    <span><?php echo date('d/m/Y', strtotime($access['created_at'])); ?></span>
                                                </li>
                                                <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                                                    <span>Validade:</span>
                                                    <span>
                                                        <?php if (isset($access['expires_at']) && $access['expires_at']): ?>
                                                            <?php if ($is_expiring): ?>
                                                                <span class="badge badge-expiring">
                                                                    <i class="fas fa-clock"></i>
                                                                    <?php echo date('d/m/Y', strtotime($access['expires_at'])); ?>
                                                                    (<?php echo $days_left; ?> dias)
                                                                </span>
                                                            <?php else: ?>
                                                                <?php echo date('d/m/Y', strtotime($access['expires_at'])); ?>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sem data de expiração</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </li>
                                            </ul>
                                            
                                            <div class="d-flex gap-2">
                                                <?php if (isset($access['expires_at']) && $access['expires_at']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#extendAccessModal<?php echo $access['id']; ?>">
                                                    <i class="fas fa-calendar-plus"></i> Estender
                                                </button>
                                                <?php endif; ?>
                                                
                                                <form method="post" class="ms-auto" onsubmit="return confirm('Tem certeza que deseja remover este acesso?');">
                                                    <input type="hidden" name="action" value="remove_access">
                                                    <input type="hidden" name="access_id" value="<?php echo $access['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-times"></i> Remover Acesso
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Modal para estender acesso -->
                                <div class="modal fade" id="extendAccessModal<?php echo $access['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Estender Acesso</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="extend_access">
                                                    <input type="hidden" name="access_id" value="<?php echo $access['id']; ?>">
                                                    
                                                    <p>Estender acesso para <strong><?php echo htmlspecialchars($access['group_name']); ?></strong>.</p>
                                                    
                                                    <div class="mb-3">
                                                        <label for="new_expires_at_<?php echo $access['id']; ?>" class="form-label">Nova Data de Validade</label>
                                                        <input type="text" class="form-control datepicker" id="new_expires_at_<?php echo $access['id']; ?>" name="new_expires_at" 
                                                               value="<?php echo isset($access['expires_at']) ? date('Y-m-d', strtotime($access['expires_at'])) : ''; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary">Atualizar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Este usuário não tem acesso a nenhum grupo PG Soft.
                                    <a href="add_access.php?user_id=<?php echo $user_id; ?>" class="alert-link">Adicionar acesso</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pragmatic Access -->
                    <h5 class="mb-3">
                        <i class="fas fa-gamepad pragmatic-color"></i> Acesso aos Sinais Pragmatic
                    </h5>
                    
                    <div class="row mb-4">
                        <?php if (count($pragmatic_access) > 0): ?>
                            <?php foreach ($pragmatic_access as $access): 
                                $is_expiring = false;
                                $days_left = 0;
                                
                                if (isset($access['expires_at']) && $access['expires_at']) {
                                    $expiry_date = new DateTime($access['expires_at']);
                                    $today = new DateTime();
                                    $interval = $today->diff($expiry_date);
                                    $days_left = $interval->days;
                                    $is_expiring = $days_left <= 7 && $expiry_date > $today;
                                }
                            ?>
                                <div class="col-lg-6 col-xl-4 mb-3">
                                    <div class="card access-card pragmatic-border shadow-sm">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($access['group_name']); ?></h5>
                                            
                                            <ul class="list-group list-group-flush mb-3">
                                                <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                                                    <span>ID do Grupo:</span>
                                                    <span class="text-monospace"><?php echo htmlspecialchars($access['telegram_group_id']); ?></span>
                                                </li>
                                                <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                                                    <span>Adicionado em:</span>
                                                    <span><?php echo date('d/m/Y', strtotime($access['created_at'])); ?></span>
                                                </li>
                                                <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                                                    <span>Validade:</span>
                                                    <span>
                                                        <?php if (isset($access['expires_at']) && $access['expires_at']): ?>
                                                            <?php if ($is_expiring): ?>
                                                                <span class="badge badge-expiring">
                                                                    <i class="fas fa-clock"></i>
                                                                    <?php echo date('d/m/Y', strtotime($access['expires_at'])); ?>
                                                                    (<?php echo $days_left; ?> dias)
                                                                </span>
                                                            <?php else: ?>
                                                                <?php echo date('d/m/Y', strtotime($access['expires_at'])); ?>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sem data de expiração</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </li>
                                            </ul>
                                            
                                            <div class="d-flex gap-2">
                                                <?php if (isset($access['expires_at']) && $access['expires_at']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#extendAccessModal<?php echo $access['id']; ?>">
                                                    <i class="fas fa-calendar-plus"></i> Estender
                                                </button>
                                                <?php endif; ?>
                                                
                                                <form method="post" class="ms-auto" onsubmit="return confirm('Tem certeza que deseja remover este acesso?');">
                                                    <input type="hidden" name="action" value="remove_access">
                                                    <input type="hidden" name="access_id" value="<?php echo $access['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-times"></i> Remover Acesso
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Modal para estender acesso -->
                                <div class="modal fade" id="extendAccessModal<?php echo $access['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Estender Acesso</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="extend_access">
                                                    <input type="hidden" name="access_id" value="<?php echo $access['id']; ?>">
                                                    
                                                    <p>Estender acesso para <strong><?php echo htmlspecialchars($access['group_name']); ?></strong>.</p>
                                                    
                                                    <div class="mb-3">
                                                        <label for="new_expires_at_<?php echo $access['id']; ?>" class="form-label">Nova Data de Validade</label>
                                                        <input type="text" class="form-control datepicker" id="new_expires_at_<?php echo $access['id']; ?>" name="new_expires_at" 
                                                               value="<?php echo isset($access['expires_at']) ? date('Y-m-d', strtotime($access['expires_at'])) : ''; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary">Atualizar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Este usuário não tem acesso a nenhum grupo Pragmatic.
                                    <a href="add_access.php?user_id=<?php echo $user_id; ?>" class="alert-link">Adicionar acesso</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar datepickers
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d",
            minDate: "today",
            locale: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
                    longhand: ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']
                },
                months: {
                    shorthand: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                    longhand: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']
                }
            }
        });
    });
    </script>
</body>
</html>