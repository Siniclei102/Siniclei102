<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'api/facebook.php';
require_once 'includes/functions.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Obter token do Facebook do usuário
$query = "SELECT facebook_token, facebook_token_expira FROM usuarios WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

$facebookConnected = !empty($userData['facebook_token']);
$facebookExpired = $facebookConnected && strtotime($userData['facebook_token_expira']) < time();

// Mensagens de erro/sucesso
$messages = [];

// Sincronizar grupos do Facebook
if (isset($_POST['sync_groups']) && $facebookConnected && !$facebookExpired) {
    $facebookAPI = new FacebookAPI($db, $userData['facebook_token']);
    $groups = $facebookAPI->getUserGroups();
    
    if ($groups['success']) {
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($groups['groups'] as $group) {
            // Verificar se o grupo já existe
            $checkQuery = "SELECT id FROM grupos_facebook WHERE usuario_id = ? AND facebook_group_id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bind_param("is", $userId, $group['id']);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                // Inserir novo grupo
                $insertQuery = "INSERT INTO grupos_facebook (usuario_id, facebook_group_id, nome, url, ativo) VALUES (?, ?, ?, ?, 1)";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bind_param("isss", $userId, $group['id'], $group['name'], $group['url']);
                
                if ($insertStmt->execute()) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
        }
        
        if ($successCount > 0) {
            $messages[] = [
                'type' => 'success',
                'text' => "Sincronização concluída! {$successCount} novos grupos adicionados."
            ];
        } else {
            $messages[] = [
                'type' => 'info',
                'text' => "Todos os seus grupos já estão sincronizados."
            ];
        }
        
        if ($errorCount > 0) {
            $messages[] = [
                'type' => 'warning',
                'text' => "{$errorCount} grupos não puderam ser adicionados."
            ];
        }
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Erro ao sincronizar grupos: " . $groups['message']
        ];
    }
}

// Marcar/desmarcar grupo como favorito
if (isset($_GET['favorite']) && is_numeric($_GET['favorite'])) {
    $grupoId = intval($_GET['favorite']);
    $favorito = isset($_GET['status']) ? ($_GET['status'] == '1' ? 1 : 0) : 1;
    
    $query = "UPDATE grupos_facebook SET favorito = ? WHERE id = ? AND usuario_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("iii", $favorito, $grupoId, $userId);
    
    if ($stmt->execute()) {
        $messages[] = [
            'type' => 'success',
            'text' => $favorito ? "Grupo marcado como favorito!" : "Grupo removido dos favoritos!"
        ];
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Erro ao atualizar favorito: " . $db->error
        ];
    }
}

// Ativar/desativar grupo
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $grupoId = intval($_GET['toggle']);
    $ativo = isset($_GET['status']) ? ($_GET['status'] == '1' ? 1 : 0) : 1;
    
    $query = "UPDATE grupos_facebook SET ativo = ? WHERE id = ? AND usuario_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("iii", $ativo, $grupoId, $userId);
    
    if ($stmt->execute()) {
        $messages[] = [
            'type' => 'success',
            'text' => $ativo ? "Grupo ativado com sucesso!" : "Grupo desativado com sucesso!"
        ];
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Erro ao atualizar status do grupo: " . $db->error
        ];
    }
}

// Excluir grupo
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $grupoId = intval($_GET['delete']);
    
    $query = "DELETE FROM grupos_facebook WHERE id = ? AND usuario_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $grupoId, $userId);
    
    if ($stmt->execute()) {
        $messages[] = [
            'type' => 'success',
            'text' => "Grupo excluído com sucesso!"
        ];
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Erro ao excluir grupo: " . $db->error
        ];
    }
}

// Definir filtros de busca
$search = isset($_GET['search']) ? $db->real_escape_string($_GET['search']) : '';
$filterFavorite = isset($_GET['filter_favorite']) ? ($_GET['filter_favorite'] === '1' ? true : false) : false;
$filterActive = isset($_GET['filter_active']) ? ($_GET['filter_active'] === '1' ? 1 : ($_GET['filter_active'] === '0' ? 0 : null)) : null;

// Construir query com filtros
$queryGroups = "SELECT * FROM grupos_facebook WHERE usuario_id = ?";

// Aplicar filtro de busca
if (!empty($search)) {
    $queryGroups .= " AND nome LIKE ?";
    $searchParam = "%{$search}%";
}

// Aplicar filtro de favoritos
if ($filterFavorite) {
    $queryGroups .= " AND favorito = 1";
}

// Aplicar filtro de status
if ($filterActive !== null) {
    $queryGroups .= " AND ativo = ?";
}

// Ordenação
$queryGroups .= " ORDER BY favorito DESC, nome ASC";

// Preparar e executar a consulta
$stmtGroups = $db->prepare($queryGroups);

if (!empty($search) && $filterActive !== null) {
    $stmtGroups->bind_param("isi", $userId, $searchParam, $filterActive);
} else if (!empty($search)) {
    $stmtGroups->bind_param("is", $userId, $searchParam);
} else if ($filterActive !== null) {
    $stmtGroups->bind_param("ii", $userId, $filterActive);
} else {
    $stmtGroups->bind_param("i", $userId);
}

$stmtGroups->execute();
$resultGroups = $stmtGroups->get_result();

// Incluir o cabeçalho
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-users me-2 text-primary"></i> Meus Grupos do Facebook
                    </h5>
                    <div>
                        <?php if ($facebookConnected && !$facebookExpired): ?>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="sync_groups" class="btn btn-primary btn-sm rounded-pill">
                                    <i class="fas fa-sync-alt me-1"></i> Sincronizar Grupos
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="perfil.php" class="btn btn-warning btn-sm rounded-pill">
                                <i class="fab fa-facebook me-1"></i> Conectar ao Facebook
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modern-card-body">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message['text']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Filtros -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card filter-card">
                                <div class="card-body">
                                    <form method="GET" action="grupos.php">
                                        <div class="row">
                                            <div class="col-md-5">
                                                <div class="input-group">
                                                    <span class="input-group-text bg-transparent border-end-0">
                                                        <i class="fas fa-search"></i>
                                                    </span>
                                                    <input type="text" class="form-control border-start-0" name="search" placeholder="Pesquisar por nome..." value="<?php echo htmlspecialchars($search); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <select class="form-select" name="filter_active">
                                                    <option value="" <?php echo $filterActive === null ? 'selected' : ''; ?>>Todos os Status</option>
                                                    <option value="1" <?php echo $filterActive === 1 ? 'selected' : ''; ?>>Ativos</option>
                                                    <option value="0" <?php echo $filterActive === 0 ? 'selected' : ''; ?>>Inativos</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-check form-switch mt-2">
                                                    <input class="form-check-input" type="checkbox" id="filterFavorite" name="filter_favorite" value="1" <?php echo $filterFavorite ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="filterFavorite">Apenas Favoritos</label>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="d-grid gap-2">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-filter me-1"></i> Filtrar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Grupos -->
                    <div class="row groups-container">
                        <?php if ($resultGroups->num_rows > 0): ?>
                            <?php while ($grupo = $resultGroups->fetch_assoc()): ?>
                                <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                                    <div class="group-card <?php echo $grupo['ativo'] ? 'active-group' : 'inactive-group'; ?>">
                                        <div class="group-card-header">
                                            <div class="favorite-toggle">
                                                <?php if ($grupo['favorito']): ?>
                                                    <a href="?favorite=<?php echo $grupo['id']; ?>&status=0" class="favorite-star active" data-bs-toggle="tooltip" title="Remover dos favoritos">
                                                        <i class="fas fa-star"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?favorite=<?php echo $grupo['id']; ?>&status=1" class="favorite-star" data-bs-toggle="tooltip" title="Adicionar aos favoritos">
                                                        <i class="far fa-star"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="status-badge">
                                                <?php if ($grupo['ativo']): ?>
                                                    <span class="badge bg-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inativo</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="group-card-body">
                                            <div class="group-icon">
                                                <i class="fas fa-users"></i>
                                            </div>
                                            <h5 class="group-name"><?php echo htmlspecialchars($grupo['nome']); ?></h5>
                                            <div class="group-actions">
                                                <div class="btn-group" role="group">
                                                    <a href="<?php echo htmlspecialchars($grupo['url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Visitar Grupo">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                    
                                                    <?php if ($grupo['ativo']): ?>
                                                        <a href="?toggle=<?php echo $grupo['id']; ?>&status=0" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Desativar Grupo">
                                                            <i class="fas fa-toggle-on"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="?toggle=<?php echo $grupo['id']; ?>&status=1" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Ativar Grupo">
                                                            <i class="fas fa-toggle-off"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="?delete=<?php echo $grupo['id']; ?>" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Excluir Grupo" onclick="return confirm('Tem certeza que deseja excluir este grupo?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> 
                                    <?php if (!$facebookConnected): ?>
                                        Você precisa conectar sua conta ao Facebook para sincronizar seus grupos.
                                    <?php else: ?>
                                        Nenhum grupo encontrado. Clique em "Sincronizar Grupos" para importar seus grupos do Facebook.
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!$facebookConnected): ?>
                                    <div class="text-center mt-3">
                                        <a href="perfil.php" class="btn btn-primary btn-lg">
                                            <i class="fab fa-facebook me-2"></i> Conectar ao Facebook
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Estatísticas dos Grupos -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-chart-pie me-2 text-warning"></i> Estatísticas de Grupos
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="row">
                        <?php
                        // Total de grupos
                        $queryTotal = "SELECT COUNT(*) as total FROM grupos_facebook WHERE usuario_id = ?";
                        $stmtTotal = $db->prepare($queryTotal);
                        $stmtTotal->bind_param("i", $userId);
                        $stmtTotal->execute();
                        $resultTotal = $stmtTotal->get_result();
                        $totalGrupos = $resultTotal->fetch_assoc()['total'];
                        
                        // Total de grupos ativos
                        $queryAtivos = "SELECT COUNT(*) as total FROM grupos_facebook WHERE usuario_id = ? AND ativo = 1";
                        $stmtAtivos = $db->prepare($queryAtivos);
                        $stmtAtivos->bind_param("i", $userId);
                        $stmtAtivos->execute();
                        $resultAtivos = $stmtAtivos->get_result();
                        $totalAtivos = $resultAtivos->fetch_assoc()['total'];
                        
                        // Total de grupos favoritos
                        $queryFavoritos = "SELECT COUNT(*) as total FROM grupos_facebook WHERE usuario_id = ? AND favorito = 1";
                        $stmtFavoritos = $db->prepare($queryFavoritos);
                        $stmtFavoritos->bind_param("i", $userId);
                        $stmtFavoritos->execute();
                        $resultFavoritos = $stmtFavoritos->get_result();
                        $totalFavoritos = $resultFavoritos->fetch_assoc()['total'];
                        
                        // Total de postagens por grupo
                        $queryPostagens = "SELECT g.id, g.nome, COUNT(l.id) as total_postagens 
                                          FROM grupos_facebook g 
                                          LEFT JOIN logs_postagem l ON g.id = l.grupo_id 
                                          WHERE g.usuario_id = ? 
                                          GROUP BY g.id 
                                          ORDER BY total_postagens DESC 
                                          LIMIT 5";
                        $stmtPostagens = $db->prepare($queryPostagens);
                        $stmtPostagens->bind_param("i", $userId);
                        $stmtPostagens->execute();
                        $resultPostagens = $stmtPostagens->get_result();
                        ?>
                        
                        <div class="col-md-4">
                            <div class="stats-widget">
                                <h6 class="stats-title">Resumo de Grupos</h6>
                                <div class="stats-content">
                                    <div class="stats-item">
                                        <div class="stats-info">
                                            <span>Total de Grupos</span>
                                            <strong><?php echo $totalGrupos; ?></strong>
                                        </div>
                                        <div class="stats-icon bg-primary-light text-primary">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                    <div class="stats-item">
                                        <div class="stats-info">
                                            <span>Grupos Ativos</span>
                                            <strong><?php echo $totalAtivos; ?></strong>
                                        </div>
                                        <div class="stats-icon bg-success-light text-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                    <div class="stats-item">
                                        <div class="stats-info">
                                            <span>Grupos Favoritos</span>
                                            <strong><?php echo $totalFavoritos; ?></strong>
                                        </div>
                                        <div class="stats-icon bg-warning-light text-warning">
                                            <i class="fas fa-star"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="stats-widget h-100">
                                <h6 class="stats-title">Grupos Mais Utilizados</h6>
                                <div class="stats-content pt-2">
                                    <?php if ($resultPostagens->num_rows > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Grupo</th>
                                                        <th>Total de Postagens</th>
                                                        <th>Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($postagem = $resultPostagens->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($postagem['nome']); ?></td>
                                                            <td>
                                                                <span class="badge bg-primary rounded-pill"><?php echo $postagem['total_postagens']; ?></span>
                                                            </td>
                                                            <td>
                                                                <a href="logs.php?grupo_id=<?php echo $postagem['id']; ?>" class="btn btn-sm btn-outline-info">
                                                                    <i class="fas fa-history"></i> Ver Histórico
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i> Ainda não há postagens registradas em seus grupos.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Estilos para Cards de Grupo */
.filter-card {
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    border: none;
}

.groups-container {
    margin-left: -10px;
    margin-right: -10px;
}

.group-card {
    background-color: #fff;
    border-radius: 15px;
    height: 100%;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.05);
}

.group-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.inactive-group {
    opacity: 0.7;
    background-color: #f8f9fa;
}

.group-card-header {
    padding: 10px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.favorite-star {
    color: #f1c40f;
    font-size: 1.2rem;
    opacity: 0.4;
    transition: all 0.3s;
}

.favorite-star:hover, .favorite-star.active {
    opacity: 1;
}

.group-card-body {
    padding: 20px;
    text-align: center;
}

.group-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background-color: rgba(52, 152, 219, 0.1);
    color: #3498db;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin: 0 auto 15px auto;
}

.group-name {
    font-size: 1.1rem;
    margin-bottom: 15px;
    font-weight: 600;
    height: 2.5rem;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.group-actions {
    margin-top: 15px;
}

/* Estilos para Estatísticas */
.stats-widget {
    background-color: #fff;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    height: 100%;
}

.stats-title {
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.stats-content {
    padding-top: 10px;
}

.stats-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
}

.stats-item:last-child {
    border-bottom: none;
}

.stats-info {
    display: flex;
    flex-direction: column;
}

.stats-info span {
    font-size: 0.9rem;
    color: #6c757d;
}

.stats-info strong {
    font-size: 1.25rem;
    font-weight: 600;
}

.stats-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.bg-primary-light { background-color: rgba(52, 152, 219, 0.1); }
.bg-success-light { background-color: rgba(46, 204, 113, 0.1); }
.bg-warning-light { background-color: rgba(243, 156, 18, 0.1); }
.bg-info-light { background-color: rgba(26, 188, 156, 0.1); }
</style>

<?php
// Incluir o rodapé
include 'includes/footer.php';
?>