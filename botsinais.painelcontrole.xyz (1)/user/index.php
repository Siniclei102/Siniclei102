<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Handle user status changes
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Don't allow actions on the current user
    if ($id != $_SESSION['user_id']) {
        if ($_GET['action'] == 'activate') {
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        } elseif ($_GET['action'] == 'suspend') {
            $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        } elseif ($_GET['action'] == 'delete') {
            // Check if user has any bots or channels
            $checkStmt = $conn->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM bots WHERE created_by = ?) +
                    (SELECT COUNT(*) FROM channels WHERE created_by = ?) as total
            ");
            $checkStmt->bind_param("ii", $id, $id);
            $checkStmt->execute();
            $checkStmt->bind_result($total);
            $checkStmt->fetch();
            $checkStmt->close();
            
            if ($total == 0) {
                // Safe to delete
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
            } else {
                $_SESSION['error'] = "Este usuário possui bots ou canais associados e não pode ser excluído.";
            }
        }
    } else {
        $_SESSION['error'] = "Você não pode modificar seu próprio usuário.";
    }
    
    header('Location: index.php');
    exit;
}

// Get filter parameters
$role = isset($_GET['role']) ? $_GET['role'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query conditions based on filters
$conditions = [];
$params = [];
$types = "";

if (!empty($role)) {
    $conditions[] = "role = ?";
    $params[] = $role;
    $types .= "s";
}

if (!empty($status)) {
    $conditions[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    $conditions[] = "username LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM users $whereClause";
$countStmt = $conn->prepare($countQuery);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$countStmt->bind_result($totalUsers);
$countStmt->fetch();
$countStmt->close();

// Pagination settings
$usersPerPage = 10;
$totalPages = ceil($totalUsers / $usersPerPage);
$currentPage = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $totalPages)) : 1;
$offset = ($currentPage - 1) * $usersPerPage;

// Get users with pagination
$query = "
    SELECT id, username, role, created_at, expiry_date, status,
           (SELECT COUNT(*) FROM bots WHERE created_by = users.id) as bot_count,
           (SELECT COUNT(*) FROM channels WHERE created_by = users.id) as channel_count
    FROM users 
    $whereClause
    ORDER BY created_at DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);
$limitTypes = $types . "ii";
$limitParams = array_merge($params, [$offset, $usersPerPage]);
$stmt->bind_param($limitTypes, ...$limitParams);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Usuários - BotDeSinais</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gerenciamento de Usuários</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="create.php" class="btn btn-primary btn-sm me-2">
                            <i class="fas fa-user-plus"></i> Novo Usuário
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5>Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <label for="role" class="form-label">Tipo de Usuário</label>
                                <select name="role" id="role" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="admin" <?php echo $role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="manager" <?php echo $role == 'manager' ? 'selected' : ''; ?>>Gerente</option>
                                    <option value="user" <?php echo $role == 'user' ? 'selected' : ''; ?>>Usuário</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="suspended" <?php echo $status == 'suspended' ? 'selected' : ''; ?>>Suspenso</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Pesquisar</label>
                                <input type="text" name="search" id="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nome de usuário">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="table-responsive mt-4">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuário</th>
                                <th>Tipo</th>
                                <th>Data Criação</th>
                                <th>Expira em</th>
                                <th>Status</th>
                                <th>Bots / Canais</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <?php if ($user['role'] == 'admin'): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php elseif ($user['role'] == 'manager'): ?>
                                        <span class="badge bg-warning">Gerente</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Usuário</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php 
                                        $days_left = floor((strtotime($user['expiry_date']) - time()) / (60 * 60 * 24));
                                        echo date('d/m/Y', strtotime($user['expiry_date']));
                                        if ($days_left <= 7 && $days_left >= 0) {
                                            echo " <span class='badge bg-warning'>{$days_left} dias</span>";
                                        } elseif ($days_left < 0) {
                                            echo " <span class='badge bg-danger'>Expirado</span>";
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Suspenso</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $user['bot_count']; ?> / <?php echo $user['channel_count']; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id']