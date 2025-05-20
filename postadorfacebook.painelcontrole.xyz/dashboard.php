<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';

// Iniciar sessão (já está feito no auth.php)
// session_start();

// Verificar se o usuário está logado (já está feito no auth.php)
// if (!isset($_SESSION['user_id'])) {
//     header('Location: index.php');
//     exit;
// }

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Definir título da página
$pageTitle = "Dashboard";

// Buscar estatísticas do usuário
$stats = [
    'anuncios' => 0,
    'campanhas' => 0,
    'grupos' => 0,
    'postagens' => 0
];

// Total de anúncios - verificando se a tabela existe
try {
    $query = "SELECT COUNT(*) as total FROM anuncios WHERE usuario_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['anuncios'] = $row['total'];
        }
    }
} catch (Exception $e) {
    // Tabela não existe ou outro erro
}

// Total de campanhas - verificando se a tabela existe
try {
    $query = "SELECT COUNT(*) as total FROM campanhas WHERE usuario_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['campanhas'] = $row['total'];
        }
    }
} catch (Exception $e) {
    // Tabela não existe ou outro erro
}

// Total de grupos - verificando se a tabela existe
try {
    $query = "SELECT COUNT(*) as total FROM grupos_facebook WHERE usuario_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['grupos'] = $row['total'];
        }
    }
} catch (Exception $e) {
    // Tabela não existe ou outro erro
}

// Total de postagens - verificando se a tabela existe
try {
    $query = "SELECT COUNT(*) as total FROM logs_postagem l 
              JOIN campanhas c ON l.campanha_id = c.id
              WHERE c.usuario_id = ? AND l.status = 'sucesso'";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats['postagens'] = $row['total'];
        }
    }
} catch (Exception $e) {
    // Tabela não existe ou outro erro
}

// Buscar campanhas ativas - verificando se a tabela existe
$campanhasAtivas = false;
try {
    $query = "SELECT c.*, a.titulo as anuncio_titulo, 
              (SELECT COUNT(*) FROM campanha_grupos WHERE campanha_id = c.id AND postado = 1) as grupos_postados,
              (SELECT COUNT(*) FROM campanha_grupos WHERE campanha_id = c.id) as total_grupos
              FROM campanhas c 
              JOIN anuncios a ON c.anuncio_id = a.id
              WHERE c.usuario_id = ? AND c.ativa = 1
              ORDER BY c.proxima_execucao ASC
              LIMIT 5";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $campanhasAtivas = $stmt->get_result();
    }
} catch (Exception $e) {
    // Tabela não existe ou outro erro
}

// Buscar últimas postagens - verificando se a tabela existe
$ultimasPostagens = false;
try {
    $query = "SELECT l.*, g.nome as grupo_nome, a.titulo as anuncio_titulo
              FROM logs_postagem l
              JOIN campanhas c ON l.campanha_id = c.id
              JOIN grupos_facebook g ON l.grupo_id = g.id
              JOIN anuncios a ON l.anuncio_id = a.id
              WHERE c.usuario_id = ?
              ORDER BY l.postado_em DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $ultimasPostagens = $stmt->get_result();
    }
} catch (Exception $e) {
    // Tabela não existe ou outro erro
}

// Inclui o cabeçalho
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Bem-vindo & Resumo -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-tachometer-alt me-2 text-primary"></i> Dashboard
                    </h5>
                    <div>
                        <button class="btn btn-sm btn-outline-primary rounded-pill">
                            <i class="fas fa-sync-alt me-1"></i> Atualizar
                        </button>
                    </div>
                </div>
                <div class="modern-card-body">
                    <h4 class="welcome-message">Olá, <?php echo $_SESSION['user_name']; ?>!</h4>
                    <p class="text-muted">
                        Bem-vindo ao seu painel de controle. Aqui você pode gerenciar seus anúncios e campanhas nas redes sociais.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="stats-icon" style="background-color: rgba(52, 152, 219, 0.1); color: #3498db;">
                    <i class="fas fa-ad"></i>
                </div>
                <div class="stats-content">
                    <h3 class="stats-number"><?php echo number_format($stats['anuncios']); ?></h3>
                    <p class="stats-label">Anúncios</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="stats-icon" style="background-color: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="stats-content">
                    <h3 class="stats-number"><?php echo number_format($stats['campanhas']); ?></h3>
                    <p class="stats-label">Campanhas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="stats-icon" style="background-color: rgba(243, 156, 18, 0.1); color: #f39c12;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-content">
                    <h3 class="stats-number"><?php echo number_format($stats['grupos']); ?></h3>
                    <p class="stats-label">Grupos</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stats-card">
                <div class="stats-icon" style="background-color: rgba(231, 76, 60, 0.1); color: #e74c3c;">
                    <i class="fas fa-share-alt"></i>
                </div>
                <div class="stats-content">
                    <h3 class="stats-number"><?php echo number_format($stats['postagens']); ?></h3>
                    <p class="stats-label">Postagens</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Campanhas Ativas e Últimas Postagens -->
    <div class="row">
        <!-- Campanhas Ativas -->
        <div class="col-md-7">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-bullhorn me-2 text-success"></i> Campanhas Ativas
                    </h5>
                    <div>
                        <a href="campanhas.php" class="btn btn-sm btn-outline-primary rounded-pill">
                            <i class="fas fa-plus me-1"></i> Nova Campanha
                        </a>
                    </div>
                </div>
                <div class="modern-card-body">
                    <?php if ($campanhasAtivas && $campanhasAtivas->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Campanha</th>
                                        <th>Anúncio</th>
                                        <th>Progresso</th>
                                        <th>Próxima Postagem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($campanha = $campanhasAtivas->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($campanha['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($campanha['anuncio_titulo']); ?></td>
                                            <td>
                                                <?php 
                                                    $progresso = $campanha['total_grupos'] > 0 
                                                        ? ($campanha['grupos_postados'] / $campanha['total_grupos']) * 100 
                                                        : 0;
                                                ?>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                        style="width: <?php echo $progresso; ?>%;" 
                                                        aria-valuenow="<?php echo $progresso; ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100"></div>
                                                </div>
                                                <span class="small"><?php echo $campanha['grupos_postados']; ?>/<?php echo $campanha['total_grupos']; ?> grupos</span>
                                            </td>
                                            <td>
                                                <?php if ($campanha['proxima_execucao']): ?>
                                                    <?php echo date('d/m/Y H:i', strtotime($campanha['proxima_execucao'])); ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Concluída</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Você não possui campanhas ativas no momento.
                        </div>
                        <div class="text-center mt-3">
                            <a href="campanhas.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Criar Nova Campanha
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Últimas Postagens -->
        <div class="col-md-5">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-history me-2 text-info"></i> Últimas Postagens
                    </h5>
                    <div>
                        <a href="logs.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                            <i class="fas fa-list me-1"></i> Ver Todas
                        </a>
                    </div>
                </div>
                <div class="modern-card-body">
                    <?php if ($ultimasPostagens && $ultimasPostagens->num_rows > 0): ?>
                        <div class="timeline">
                            <?php while ($postagem = $ultimasPostagens->fetch_assoc()): ?>
                                <div class="timeline-item">
                                    <div class="timeline-badge <?php echo $postagem['status'] === 'sucesso' ? 'bg-success' : 'bg-danger'; ?>">
                                        <i class="<?php echo $postagem['status'] === 'sucesso' ? 'fas fa-check' : 'fas fa-times'; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($postagem['anuncio_titulo']); ?></h6>
                                        <p class="mb-1">
                                            Grupo: <?php echo htmlspecialchars($postagem['grupo_nome']); ?>
                                        </p>
                                        <span class="time-badge">
                                            <i class="far fa-clock me-1"></i> <?php echo date('d/m H:i', strtotime($postagem['postado_em'])); ?>
                                        </span>
                                        <?php if ($postagem['status'] === 'falha'): ?>
                                            <p class="text-danger small mb-0">
                                                <i class="fas fa-exclamation-circle me-1"></i> 
                                                <?php echo htmlspecialchars($postagem['mensagem_erro']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Nenhuma postagem realizada ainda.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ações Rápidas -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-bolt me-2 text-warning"></i> Ações Rápidas
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="row quick-actions">
                        <div class="col-md-3 col-sm-6">
                            <a href="anuncios.php" class="quick-action-card">
                                <div class="quick-action-icon bg-primary-light text-primary">
                                    <i class="fas fa-ad"></i>
                                </div>
                                <div class="quick-action-content">
                                    <h6>Criar Anúncio</h6>
                                    <p class="text-muted small">Adicione um novo anúncio</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <a href="campanhas.php" class="quick-action-card">
                                <div class="quick-action-icon bg-success-light text-success">
                                    <i class="fas fa-bullhorn"></i>
                                </div>
                                <div class="quick-action-content">
                                    <h6>Nova Campanha</h6>
                                    <p class="text-muted small">Inicie uma campanha</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <a href="grupos.php" class="quick-action-card">
                                <div class="quick-action-icon bg-warning-light text-warning">
                                    <i class="fas fa-sync-alt"></i>
                                </div>
                                <div class="quick-action-content">
                                    <h6>Atualizar Grupos</h6>
                                    <p class="text-muted small">Sincronize seus grupos</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <a href="estatisticas.php" class="quick-action-card">
                                <div class="quick-action-icon bg-info-light text-info">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="quick-action-content">
                                    <h6>Ver Estatísticas</h6>
                                    <p class="text-muted small">Analise seu desempenho</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Estilos para Timeline */
.timeline {
    position: relative;
    padding: 10px 0;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 20px;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item {
    position: relative;
    padding-left: 45px;
    margin-bottom: 20px;
}

.timeline-badge {
    position: absolute;
    left: 10px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    text-align: center;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
}

.timeline-content {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
}

.time-badge {
    display: inline-block;
    font-size: 0.8rem;
    color: #6c757d;
}

/* Estilos para Ações Rápidas */
.quick-actions {
    margin: 0 -10px;
}

.quick-action-card {
    display: flex;
    align-items: center;
    background-color: #fff;
    border-radius: 15px;
    padding: 15px;
    margin: 10px;
    text-decoration: none;
    color: #333;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.quick-action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.quick-action-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-right: 15px;
}

.bg-primary-light { background-color: rgba(52, 152, 219, 0.1); }
.bg-success-light { background-color: rgba(46, 204, 113, 0.1); }
.bg-warning-light { background-color: rgba(243, 156, 18, 0.1); }
.bg-info-light { background-color: rgba(26, 188, 156, 0.1); }

.welcome-message {
    font-weight: 700;
    margin-bottom: 10px;
    color: #333;
}
</style>

<?php
// Inclui o rodapé
include 'includes/footer.php';
?>