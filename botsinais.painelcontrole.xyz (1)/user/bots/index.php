<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Definir título da página
$pageTitle = 'Gerenciar Bots';
$basePath = '../../';

// Obter o ID do usuário atual
$userId = $_SESSION['user_id'];

// Processar ações (ativar, suspender, excluir bot)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $botId = (int)$_GET['id'];
    
    // Verificar se o bot pertence ao usuário
    $checkStmt = $conn->prepare("SELECT created_by FROM bots WHERE id = ?");
    $checkStmt->bind_param("i", $botId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $bot = $checkResult->fetch_assoc();
        
        if ($bot['created_by'] == $userId) {
            // Executar a ação solicitada
            if ($_GET['action'] == 'delete') {
                // Verificar se há sinais associados
                $signalCheckStmt = $conn->prepare("SELECT COUNT(*) FROM signals WHERE bot_id = ?");
                $signalCheckStmt->bind_param("i", $botId);
                $signalCheckStmt->execute();
                $signalCheckStmt->bind_result($signalCount);
                $signalCheckStmt->fetch();
                $signalCheckStmt->close();
                
                // Verificar se há canais associados
                $channelCheckStmt = $conn->prepare("SELECT COUNT(*) FROM channels WHERE bot_id = ?");
                $channelCheckStmt->bind_param("i", $botId);
                $channelCheckStmt->execute();
                $channelCheckStmt->bind_result($channelCount);
                $channelCheckStmt->fetch();
                $channelCheckStmt->close();
                
                if ($signalCount == 0 && $channelCount == 0) {
                    // Seguro para excluir
                    $deleteStmt = $conn->prepare("DELETE FROM bots WHERE id = ?");
                    $deleteStmt->bind_param("i", $botId);
                    if ($deleteStmt->execute()) {
                        $_SESSION['success'] = "Bot excluído com sucesso.";
                    } else {
                        $_SESSION['error'] = "Erro ao excluir o bot.";
                    }
                } else {
                    $_SESSION['error'] = "Este bot não pode ser excluído pois possui sinais ou canais associados.";
                }
            }
        } else {
            $_SESSION['error'] = "Você não tem permissão para modificar este bot.";
        }
    } else {
        $_SESSION['error'] = "Bot não encontrado.";
    }
    
    // Redirecionar de volta para a lista
    header('Location: index.php');
    exit;
}

// Filtros
$provider = isset($_GET['provider']) ? $_GET['provider'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construir a consulta SQL com base nos filtros
$whereConditions = ["created_by = ?"];
$params = [$userId];
$types = "i";

if (!empty($provider)) {
    $whereConditions[] = "provider = ?";
    $params[] = $provider;
    $types .= "s";
}

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    $whereConditions[] = "name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$whereClause = implode(" AND ", $whereConditions);

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Contar total de registros para paginação
$countStmt = $conn->prepare("SELECT COUNT(*) FROM bots WHERE $whereClause");
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countStmt->bind_result($totalBots);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($totalBots / $perPage);
$page = max(1, min($page, $totalPages));

// Buscar bots com paginação
$sql = "SELECT * FROM bots WHERE $whereClause ORDER BY created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmtTypes = $types . "ii";
$stmtParams = array_merge($params, [$offset, $perPage]);
$stmt->bind_param($stmtTypes, ...$stmtParams);
$stmt->execute();
$result = $stmt->get_result();

// Incluir header
include '../../includes/header.php';
?>

<!-- Conteúdo principal -->
<main class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Gerenciar Bots</h1>
        <div>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Novo Bot
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="provider" class="form-label">Provedor</label>
                    <select name="provider" id="provider" class="form-select">
                        <option value="">Todos</option>
                        <option value="PG" <?php echo $provider == 'PG' ? 'selected' : ''; ?>>PG</option>
                        <option value="Pragmatic" <?php echo $provider == 'Pragmatic' ? 'selected' : ''; ?>>Pragmatic</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="suspended" <?php echo $status == 'suspended' ? 'selected' : ''; ?>>Suspenso</option>
                        <option value="expired" <?php echo $status == 'expired' ? 'selected' : ''; ?>>Expirado</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Buscar</label>
                    <div class="input-group">
                        <input type="text" name="search" id="search" class="form-control" placeholder="Nome do bot..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle m-0">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col">Nome</th>
                            <th scope="col">Provedor</th>
                            <th scope="col">Data Criação</th>
                            <th scope="col">Expira em</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($bot = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="view.php?id=<?php echo $bot['id']; ?>" class="fw-bold text-decoration-none">
                                            <?php echo htmlspecialchars($bot['name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($bot['provider'] == 'PG'): ?>
                                            <span class="badge bg-primary rounded-pill">PG</span>
                                        <?php else: ?>
                                            <span class="badge bg-success rounded-pill">Pragmatic</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($bot['created_at']); ?></td>
                                    <td>
                                        <?php 
                                            $days_left = daysRemaining($bot['expiry_date']);
                                            echo formatDate($bot['expiry_date']);
                                            
                                            if ($days_left <= 7 && $days_left >= 0) {
                                                echo " <span class='badge bg-warning text-dark'>{$days_left} dias</span>";
                                            } elseif ($days_left < 0) {
                                                echo " <span class='badge bg-danger'>Expirado</span>";
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($bot['status'] == 'active'): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php elseif ($bot['status'] == 'suspended'): ?>
                                            <span class="badge bg-warning text-dark">Suspenso</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Expirado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $bot['id']; ?>" class="btn btn-sm btn-info text-white" title="Visualizar">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $bot['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="index.php?action=delete&id=<?php echo $bot['id']; ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Tem certeza que deseja excluir este bot? Esta ação não pode ser desfeita.')" title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="fas fa-robot fa-3x mb-3 text-muted"></i>
                                        <h5>Nenhum bot encontrado</h5>
                                        <p class="mb-3">Você ainda não tem bots cadastrados ou nenhum bot corresponde aos filtros aplicados.</p>
                                        <a href="create.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i> Criar Novo Bot
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container p-3">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&provider=<?php echo urlencode($provider); ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&provider=<?php echo urlencode($provider); ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&provider=<?php echo urlencode($provider); ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" aria-label="Próximo">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
    .table th {
        font-weight: 600;
    }
    
    .pagination .page-link {
        color: var(--primary-color);
    }
    
    .pagination .active .page-link {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }
</style>

<?php include '../../includes/footer.php'; ?>