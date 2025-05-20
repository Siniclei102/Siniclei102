<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$adminId = $_SESSION['user_id'];

// Buscar estatísticas rápidas
$queryStats = "
    SELECT 
        (SELECT COUNT(*) FROM usuarios) as total_usuarios,
        (SELECT COUNT(*) FROM campanhas WHERE ativa = 1) as campanhas_ativas,
        (SELECT COUNT(*) FROM grupos_facebook) as total_grupos,
        (SELECT COUNT(*) FROM anuncios) as total_anuncios
";
$resultStats = $db->query($queryStats);
$stats = $resultStats->fetch_assoc();

// Incluir o cabeçalho
include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-12 text-center">
            <h1 class="h3 text-gray-800">Painel de Administração</h1>
            <p class="lead text-muted">Ferramentas e recursos administrativos</p>
        </div>
    </div>
    
    <!-- Estatísticas Rápidas -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stats-card">
                <div class="stats-icon bg-primary-light text-primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-content">
                    <h5 class="stats-number"><?php echo number_format($stats['total_usuarios']); ?></h5>
                    <span class="stats-label">Usuários</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stats-card">
                <div class="stats-icon bg-success-light text-success">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="stats-content">
                    <h5 class="stats-number"><?php echo number_format($stats['campanhas_ativas']); ?></h5>
                    <span class="stats-label">Campanhas Ativas</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stats-card">
                <div class="stats-icon bg-info-light text-info">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div class="stats-content">
                    <h5 class="stats-number"><?php echo number_format($stats['total_grupos']); ?></h5>
                    <span class="stats-label">Grupos do Facebook</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stats-card">
                <div class="stats-icon bg-warning-light text-warning">
                    <i class="fas fa-ad"></i>
                </div>
                <div class="stats-content">
                    <h5 class="stats-number"><?php echo number_format($stats['total_anuncios']); ?></h5>
                    <span class="stats-label">Anúncios</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cards de Ferramentas Administrativas -->
    <div class="row">
        <!-- Gestão de Usuários -->
        <div class="col-md-4 mb-4">
            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div class="tool-content">
                    <h4 class="tool-title">Gestão de Usuários</h4>
                    <p class="tool-desc">Crie, edite, suspenda ou exclua contas de usuários. Gerencie permissões administrativas.</p>
                    <a href="usuarios.php" class="btn btn-primary btn-sm">Acessar</a>
                </div>
            </div>
        </div>
        
        <!-- Validação de Contas -->
        <div class="col-md-4 mb-4">
            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="tool-content">
                    <h4 class="tool-title">Validade de Contas</h4>
                    <p class="tool-desc">Monitore a validade das contas de usuários, estenda períodos e gerencie suspensões.</p>
                    <a href="relatorio_validade.php" class="btn btn-primary btn-sm">Acessar</a>
                </div>
            </div>
        </div>
        
        <!-- Configurações do Sistema -->
        <div class="col-md-4 mb-4">
            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="tool-content">
                    <h4 class="tool-title">Configurações do Sistema</h4>
                    <p class="tool-desc">Configure parâmetros globais, personalize a aparência e ajuste funcionalidades do sistema.</p>
                    <a href="../configuracoes.php" class="btn btn-primary btn-sm">Acessar</a>
                </div>
            </div>
        </div>
        
        <!-- Logs do Sistema -->
        <div class="col-md-4 mb-4">
            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="tool-content">
                    <h4 class="tool-title">Logs do Sistema</h4>
                    <p class="tool-desc">Visualize logs detalhados de postagens, operações administrativas e erros do sistema.</p>
                    <a href="../logs.php" class="btn btn-primary btn-sm">Acessar</a>
                </div>
            </div>
        </div>
        
        <!-- Backups -->
        <div class="col-md-4 mb-4">
            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="tool-content">
                    <h4 class="tool-title">Gerenciamento de Backups</h4>
                    <p class="tool-desc">Crie, restaure e gerencie backups do banco de dados e configurações do sistema.</p>
                    <a href="backups.php" class="btn btn-primary btn-sm">Acessar</a>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Completo -->
        <div class="col-md-4 mb-4">
            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="tool-content">
                    <h4 class="tool-title">Dashboard Completo</h4>
                    <p class="tool-desc">Visualize estatísticas detalhadas, métricas de desempenho e análises do sistema.</p>
                    <a href="dashboard.php" class="btn btn-primary btn-sm">Acessar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Estatísticas rápidas */
.stats-card {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 12px;
    background-color: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    height: 100%;
    transition: transform 0.3s;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.stats-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-right: 20px;
}

.stats-content {
    flex: 1;
}

.stats-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
    line-height: 1;
}

.stats-label {
    font-size: 0.9rem;
    color: #6c757d;
}

/* Cards de ferramentas */
.tool-card {
    background-color: #fff;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: all 0.3s;
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: 30px;
    text-align: center;
}

.tool-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.tool-icon {
    font-size: 3rem;
    margin-bottom: 20px;
    color: var(--bs-primary);
}

.tool-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.tool-desc {
    color: #6c757d;
    margin-bottom: 20px;
    flex-grow: 1;
}

/* Cores de fundo para ícones */
.bg-primary-light { background-color: rgba(52, 152, 219, 0.1); }
.bg-success-light { background-color: rgba(46, 204, 113, 0.1); }
.bg-warning-light { background-color: rgba(243, 156, 18, 0.1); }
.bg-danger-light { background-color: rgba(231, 76, 60, 0.1); }
.bg-info-light { background-color: rgba(26, 188, 156, 0.1); }
</style>

<?php
// Incluir o rodapé
include '../includes/footer.php';
?>