<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Verificar validade da conta
include 'includes/check_validity.php';

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Marcar notificação como lida
if (isset($_GET['marcar_lida']) && is_numeric($_GET['marcar_lida'])) {
    $notificacaoId = intval($_GET['marcar_lida']);
    
    $queryMarcar = "UPDATE notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ?";
    $stmtMarcar = $db->prepare($queryMarcar);
    $stmtMarcar->bind_param("ii", $notificacaoId, $userId);
    $stmtMarcar->execute();
    
    header('Location: notificacoes.php');
    exit;
}

// Marcar todas como lidas
if (isset($_GET['marcar_todas_lidas'])) {
    $queryMarcarTodas = "UPDATE notificacoes SET lida = 1 WHERE usuario_id = ? AND lida = 0";
    $stmtMarcarTodas = $db->prepare($queryMarcarTodas);
    $stmtMarcarTodas->bind_param("i", $userId);
    $stmtMarcarTodas->execute();
    
    header('Location: notificacoes.php');
    exit;
}

// Excluir notificação
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $notificacaoId = intval($_GET['excluir']);
    
    $queryExcluir = "DELETE FROM notificacoes WHERE id = ? AND usuario_id = ?";
    $stmtExcluir = $db->prepare($queryExcluir);
    $stmtExcluir->bind_param("ii", $notificacaoId, $userId);
    $stmtExcluir->execute();
    
    header('Location: notificacoes.php');
    exit;
}

// Definir filtro
$filtroTipo = isset($_GET['tipo']) ? $db->real_escape_string($_GET['tipo']) : '';
$filtroStatus = isset($_GET['status']) ? $db->real_escape_string($_GET['status']) : '';

// Buscar notificações
$query = "
    SELECT * FROM notificacoes
    WHERE usuario_id = ?
";

$params = [$userId];
$paramTypes = "i";

if (!empty($filtroTipo)) {
    $query .= " AND tipo = ?";
    $params[] = $filtroTipo;
    $paramTypes .= "s";
}

if ($filtroStatus === 'lidas') {
    $query .= " AND lida = 1";
} elseif ($filtroStatus === 'nao_lidas') {
    $query .= " AND lida = 0";
}

$query .= " ORDER BY criado_em DESC";

$stmt = $db->prepare($query);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Contar notificações por tipo
$queryCount = "
    SELECT 
        tipo,
        COUNT(*) as total,
        SUM(CASE WHEN lida = 0 THEN 1 ELSE 0 END) as nao_lidas
    FROM notificacoes
    WHERE usuario_id = ?
    GROUP BY tipo
";

$stmtCount = $db->prepare($queryCount);
$stmtCount->bind_param("i", $userId);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();

$countPorTipo = [];
while ($row = $resultCount->fetch_assoc()) {
    $countPorTipo[$row['tipo']] = [
        'total' => $row['total'],
        'nao_lidas' => $row['nao_lidas']
    ];
}

// Total de notificações
$queryTotal = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ?";
$stmtTotal = $db->prepare($queryTotal);
$stmtTotal->bind_param("i", $userId);
$stmtTotal->execute();
$totalNotificacoes = $stmtTotal->get_result()->fetch_assoc()['total'];

// Total de não lidas
$queryNaoLidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = 0";
$stmtNaoLidas = $db->prepare($queryNaoLidas);
$stmtNaoLidas->bind_param("i", $userId);
$stmtNaoLidas->execute();
$totalNaoLidas = $stmtNaoLidas->get_result()->fetch_assoc()['total'];

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
                        <i class="fas fa-bell me-2 text-primary"></i> Notificações e Alertas
                    </h5>
                </div>
                <div class="modern-card-body">
                    <!-- Resumo de Notificações -->
                    <div class="notification-summary mb-4">
                        <div class="row">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="notification-count-card">
                                    <div class="notification-count-icon all">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                    <div class="notification-count-content">
                                        <h4><?php echo $totalNotificacoes; ?></h4>
                                        <p>Total de Notificações</p>
                                        <?php if ($totalNaoLidas > 0): ?>
                                            <span class="notification-badge"><?php echo $totalNaoLidas; ?> não lidas</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="notification-count-card">
                                    <div class="notification-count-icon system">
                                        <i class="fas fa-cogs"></i>
                                    </div>
                                    <div class="notification-count-content">
                                        <h4><?php echo isset($countPorTipo['sistema']) ? $countPorTipo['sistema']['total'] : 0; ?></h4>
                                        <p>Sistema</p>
                                        <?php if (isset($countPorTipo['sistema']) && $countPorTipo['sistema']['nao_lidas'] > 0): ?>
                                            <span class="notification-badge"><?php echo $countPorTipo['sistema']['nao_lidas']; ?> não lidas</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="notification-count-card">
                                    <div class="notification-count-icon campaign">
                                        <i class="fas fa-bullhorn"></i>
                                    </div>
                                    <div class="notification-count-content">
                                        <h4><?php echo isset($countPorTipo['campanha']) ? $countPorTipo['campanha']['total'] : 0; ?></h4>
                                        <p>Campanhas</p>
                                        <?php if (isset($countPorTipo['campanha']) && $countPorTipo['campanha']['nao_lidas'] > 0): ?>
                                            <span class="notification-badge"><?php echo $countPorTipo['campanha']['nao_lidas']; ?> não lidas</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="notification-count-card">
                                    <div class="notification-count-icon account">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    <div class="notification-count-content">
                                        <h4><?php echo isset($countPorTipo['conta']) ? $countPorTipo['conta']['total'] : 0; ?></h4>
                                        <p>Conta</p>
                                        <?php if (isset($countPorTipo['conta']) && $countPorTipo['conta']['nao_lidas'] > 0): ?>
                                            <span class="notification-badge"><?php echo $countPorTipo['conta']['nao_lidas']; ?> não lidas</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtros -->
                    <div class="notification-filter mb-4">
                        <div class="row">
                            <div class="col-md-8">
                                <form method="GET" action="notificacoes.php" class="d-flex gap-2">
                                    <div class="form-group">
                                        <select class="form-select" name="tipo">
                                            <option value="" <?php echo empty($filtroTipo) ? 'selected' : ''; ?>>Todos os tipos</option>
                                            <option value="sistema" <?php echo $filtroTipo === 'sistema' ? 'selected' : ''; ?>>Sistema</option>
                                            <option value="campanha" <?php echo $filtroTipo === 'campanha' ? 'selected' : ''; ?>>Campanha</option>
                                            <option value="conta" <?php echo $filtroTipo === 'conta' ? 'selected' : ''; ?>>Conta</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <select class="form-select" name="status">
                                            <option value="" <?php echo empty($filtroStatus) ? 'selected' : ''; ?>>Todas</option>
                                            <option value="lidas" <?php echo $filtroStatus === 'lidas' ? 'selected' : ''; ?>>Lidas</option>
                                            <option value="nao_lidas" <?php echo $filtroStatus === 'nao_lidas' ? 'selected' : ''; ?>>Não lidas</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter me-1"></i> Filtrar
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-4 text-end">
                                <?php if ($totalNaoLidas > 0): ?>
                                    <a href="?marcar_todas_lidas=1" class="btn btn-outline-secondary">
                                        <i class="fas fa-check-double me-1"></i> Marcar todas como lidas
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista de Notificações -->
                    <div class="notification-list">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($notificacao = $result->fetch_assoc()): ?>
                                <?php
                                $classeTipo = 'default';
                                $icone = 'bell';
                                
                                if ($notificacao['tipo'] === 'sistema') {
                                    $classeTipo = 'system';
                                    $icone = 'cogs';
                                } elseif ($notificacao['tipo'] === 'campanha') {
                                    $classeTipo = 'campaign';
                                    $icone = 'bullhorn';
                                } elseif ($notificacao['tipo'] === 'conta') {
                                    $classeTipo = 'account';
                                    $icone = 'user-shield';
                                }
                                
                                $tempo = time_elapsed_string($notificacao['criado_em']);
                                ?>
                                <div class="notification-item <?php echo $notificacao['lida'] ? '' : 'unread'; ?>">
                                    <div class="notification-icon <?php echo $classeTipo; ?>">
                                        <i class="fas fa-<?php echo $icone; ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-header">
                                            <h6 class="notification-title"><?php echo htmlspecialchars($notificacao['titulo']); ?></h6>
                                            <div class="notification-time" title="<?php echo date('d/m/Y H:i', strtotime($notificacao['criado_em'])); ?>">
                                                <?php echo $tempo; ?>
                                            </div>
                                        </div>
                                        <div class="notification-body">
                                            <?php echo htmlspecialchars($notificacao['mensagem']); ?>
                                            <?php if (!empty($notificacao['link'])): ?>
                                                <div class="notification-link">
                                                    <a href="<?php echo htmlspecialchars($notificacao['link']); ?>" class="btn btn-sm btn-outline-primary">
                                                        Ver detalhes <i class="fas fa-arrow-right ms-1"></i>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="notification-actions">
                                        <?php if (!$notificacao['lida']): ?>
                                            <a href="?marcar_lida=<?php echo $notificacao['id']; ?>" class="btn btn-sm btn-light" title="Marcar como lida">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?excluir=<?php echo $notificacao['id']; ?>" class="btn btn-sm btn-light" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta notificação?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="notification-empty">
                                <div class="notification-empty-icon">
                                    <i class="fas fa-bell-slash"></i>
                                </div>
                                <h5>Nenhuma notificação</h5>
                                <p>Você não tem notificações <?php echo !empty($filtroStatus) || !empty($filtroTipo) ? 'com os filtros aplicados' : 'no momento'; ?>.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Cards de contagem de notificações */
.notification-count-card {
    display: flex;
    padding: 15px;
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    height: 100%;
    align-items: center;
    transition: transform 0.2s;
}

.notification-count-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.notification-count-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin-right: 15px;
    flex-shrink: 0;
}

.notification-count-icon.all {
    background-color: rgba(52, 152, 219, 0.15);
    color: #3498db;
}

.notification-count-icon.system {
    background-color: rgba(155, 89, 182, 0.15);
    color: #9b59b6;
}

.notification-count-icon.campaign {
    background-color: rgba(243, 156, 18, 0.15);
    color: #f39c12;
}

.notification-count-icon.account {
    background-color: rgba(46, 204, 113, 0.15);
    color: #2ecc71;
}

.notification-count-content {
    flex: 1;
}

.notification-count-content h4 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 2px;
    line-height: 1;
}

.notification-count-content p {
    margin: 0 0 5px;
    font-size: 0.85rem;
    color: #6c757d;
}

.notification-badge {
    display: inline-block;
    padding: 0.2em 0.6em;
    font-size: 0.65rem;
    font-weight: 600;
    border-radius: 50rem;
    background-color: #dc3545;
    color: #fff;
}

/* Lista de notificações */
.notification-list {
    margin-top: 20px;
}

.notification-item {
    display: flex;
    padding: 15px;
    margin-bottom: 10px;
    background-color: #fff;
    border-radius: 8px;
    border-left: 4px solid #e9ecef;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    transition: all 0.2s;
}

.notification-item:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.notification-item.unread {
    background-color: #f8f9fa;
    border-left-color: #007bff;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    margin-right: 15px;
    flex-shrink: 0;
    background-color: #e9ecef;
    color: #6c757d;
}

.notification-icon.system {
    background-color: rgba(155, 89, 182, 0.15);
    color: #9b59b6;
}

.notification-icon.campaign {
    background-color: rgba(243, 156, 18, 0.15);
    color: #f39c12;
}

.notification-icon.account {
    background-color: rgba(46, 204, 113, 0.15);
    color: #2ecc71;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 5px;
}

.notification-title {
    margin: 0;
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.3;
}

.notification-time {
    font-size: 0.75rem;
    color: #6c757d;
    white-space: nowrap;
    margin-left: 10px;
}

.notification-body {
    color: #495057;
    font-size: 0.9rem;
}

.notification-link {
    margin-top: 10px;
}

.notification-actions {
    display: flex;
    flex-direction: column;
    margin-left: 15px;
    gap: 5px;
}

.notification-actions .btn {
    padding: 0.25rem 0.5rem;
    line-height: 1;
}

.notification-empty {
    text-align: center;
    padding: 50px 20px;
    color: #6c757d;
}

.notification-empty-icon {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.notification-empty h5 {
    font-weight: 600;
    margin-bottom: 10px;
}

.notification-empty p {
    color: #adb5bd;
    max-width: 300px;
    margin: 0 auto;
}

/* Responsividade */
@media (max-width: 767px) {
    .notification-header {
        flex-direction: column;
    }
    
    .notification-time {
        margin-left: 0;
        margin-top: 5px;
    }
    
    .notification-item {
        flex-wrap: wrap;
    }
    
    .notification-actions {
        flex-direction: row;
        margin-top: 15px;
        margin-left: 0;
        width: 100%;
        justify-content: flex-end;
    }
}
</style>

<!-- Script para função de tempo relativo -->
<script>
function time_elapsed_string(datetime) {
    var now = new Date();
    var date = new Date(datetime);
    var diff = Math.floor((now - date) / 1000);
    
    var intervals = {
        'ano': 31536000,
        'mês': 2592000,
        'semana': 604800,
        'dia': 86400,
        'hora': 3600,
        'minuto': 60,
        'segundo': 1
    };
    
    var plural = {
        'ano': 'anos',
        'mês': 'meses',
        'semana': 'semanas',
        'dia': 'dias',
        'hora': 'horas',
        'minuto': 'minutos',
        'segundo': 'segundos'
    };
    
    for (var i in intervals) {
        var count = Math.floor(diff / intervals[i]);
        if (count > 0) {
            return "Há " + count + " " + (count === 1 ? i : plural[i]);
        }
    }
    
    return "Agora mesmo";
}
</script>

<?php
// Função de tempo relativo
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = [
        'y' => 'ano',
        'm' => 'mês',
        'w' => 'semana',
        'd' => 'dia',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    ];
    
    $plural = [
        'ano' => 'anos',
        'mês' => 'meses', 
        'semana' => 'semanas',
        'dia' => 'dias',
        'hora' => 'horas',
        'minuto' => 'minutos',
        'segundo' => 'segundos',
    ];

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . ($diff->$k > 1 ? $plural[$v] : $v);
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'Há ' . implode(', ', $string) : 'Agora mesmo';
}

// Incluir o rodapé
include 'includes/footer.php';
?>