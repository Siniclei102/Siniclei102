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

// Filtros
$filtroStatusValidade = isset($_GET['status_validade']) ? $_GET['status_validade'] : '';
$filtroDias = isset($_GET['dias']) && is_numeric($_GET['dias']) ? intval($_GET['dias']) : 7;

// Construir consulta base
$query = "
    SELECT 
        u.id,
        u.nome,
        u.email,
        u.validade_ate,
        u.suspenso,
        u.is_admin,
        u.is_active,
        u.criado_em,
        (SELECT COUNT(*) FROM campanhas WHERE usuario_id = u.id) as total_campanhas,
        (SELECT COUNT(*) FROM campanhas WHERE usuario_id = u.id AND ativa = 1) as campanhas_ativas
    FROM usuarios u
    WHERE 1=1
";

// Aplicar filtros
if ($filtroStatusValidade === 'vencidas') {
    $query .= " AND u.validade_ate < CURDATE() AND u.validade_ate IS NOT NULL";
} elseif ($filtroStatusValidade === 'proximas') {
    $query .= " AND u.validade_ate >= CURDATE() AND u.validade_ate <= DATE_ADD(CURDATE(), INTERVAL {$filtroDias} DAY) AND u.validade_ate IS NOT NULL";
} elseif ($filtroStatusValidade === 'ativas') {
    $query .= " AND u.validade_ate > CURDATE() AND u.validade_ate IS NOT NULL";
} elseif ($filtroStatusValidade === 'sem_validade') {
    $query .= " AND u.validade_ate IS NULL";
}

// Ordenar por validade
$query .= " ORDER BY CASE WHEN u.validade_ate IS NULL THEN 1 ELSE 0 END, u.validade_ate ASC";

// Executar consulta
$result = $db->query($query);

// Resumo de estatísticas
$queryStats = "
    SELECT
        COUNT(*) as total_usuarios,
        SUM(CASE WHEN validade_ate < CURDATE() AND validade_ate IS NOT NULL THEN 1 ELSE 0 END) as contas_vencidas,
        SUM(CASE WHEN validade_ate >= CURDATE() AND validade_ate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND validade_ate IS NOT NULL THEN 1 ELSE 0 END) as contas_vencendo_7dias,
        SUM(CASE WHEN validade_ate > CURDATE() AND validade_ate IS NOT NULL THEN 1 ELSE 0 END) as contas_ativas,
        SUM(CASE WHEN validade_ate IS NULL THEN 1 ELSE 0 END) as contas_sem_validade,
        SUM(CASE WHEN suspenso = 1 THEN 1 ELSE 0 END) as contas_suspensas
    FROM usuarios
";

$statsResult = $db->query($queryStats);
$stats = $statsResult->fetch_assoc();

// Incluir o cabeçalho
include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-calendar-alt me-2 text-primary"></i> Relatório de Validade de Contas
                    </h5>
                </div>
                <div class="modern-card-body">
                    <!-- Cards de Estatísticas -->
                    <div class="row mb-4">
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-primary-light text-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['total_usuarios']); ?></h5>
                                    <span class="stats-label">Total de Usuários</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-danger-light text-danger">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['contas_vencidas']); ?></h5>
                                    <span class="stats-label">Contas Vencidas</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-warning-light text-warning">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['contas_vencendo_7dias']); ?></h5>
                                    <span class="stats-label">Vencendo em 7 dias</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-success-light text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['contas_ativas']); ?></h5>
                                    <span class="stats-label">Contas Ativas</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-info-light text-info">
                                    <i class="fas fa-infinity"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['contas_sem_validade']); ?></h5>
                                    <span class="stats-label">Sem Validade</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-secondary-light text-secondary">
                                    <i class="fas fa-ban"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['contas_suspensas']); ?></h5>
                                    <span class="stats-label">Contas Suspensas</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtros de Relatório -->
                    <div class="card filter-card mb-4">
                        <div class="card-body">
                            <form method="GET" action="relatorio_validade.php" class="row g-3">
                                <div class="col-md-4">
                                    <label for="status_validade" class="form-label">Status de Validade</label>
                                    <select class="form-select" id="status_validade" name="status_validade">
                                        <option value="" <?php echo $filtroStatusValidade === '' ? 'selected' : ''; ?>>Todos</option>
                                        <option value="vencidas" <?php echo $filtroStatusValidade === 'vencidas' ? 'selected' : ''; ?>>Contas Vencidas</option>
                                        <option value="proximas" <?php echo $filtroStatusValidade === 'proximas' ? 'selected' : ''; ?>>Vencendo em Breve</option>
                                        <option value="ativas" <?php echo $filtroStatusValidade === 'ativas' ? 'selected' : ''; ?>>Contas Ativas</option>
                                        <option value="sem_validade" <?php echo $filtroStatusValidade === 'sem_validade' ? 'selected' : ''; ?>>Sem Validade</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="dias" class="form-label">Dias para Vencimento</label>
                                    <input type="number" class="form-control" id="dias" name="dias" value="<?php echo $filtroDias; ?>" min="1" max="90">
                                    <div class="form-text">Para filtro "Vencendo em Breve"</div>
                                </div>
                                
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-filter me-1"></i> Filtrar
                                    </button>
                                    <a href="relatorio_validade.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-sync-alt me-1"></i> Limpar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Tabela de Resultados -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>Email</th>
                                    <th>Data de Validade</th>
                                    <th>Status</th>
                                    <th>Campanhas Ativas</th>
                                    <th>Data de Registro</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($user = $result->fetch_assoc()): ?>
                                        <?php
                                        $statusClass = '';
                                        $statusLabel = '';
                                        
                                        if ($user['suspenso']) {
                                            $statusClass = 'danger';
                                            $statusLabel = 'Suspenso';
                                        } elseif ($user['validade_ate'] === null) {
                                            $statusClass = 'secondary';
                                            $statusLabel = 'Sem Validade';
                                        } else {
                                            $validade = new DateTime($user['validade_ate']);
                                            $hoje = new DateTime();
                                            
                                            if ($validade < $hoje) {
                                                $statusClass = 'danger';
                                                $statusLabel = 'Vencida';
                                            } else {
                                                $diasRestantes = $hoje->diff($validade)->days;
                                                
                                                if ($diasRestantes <= 7) {
                                                    $statusClass = 'warning';
                                                    $statusLabel = 'Vence em ' . $diasRestantes . ' dias';
                                                } else {
                                                    $statusClass = 'success';
                                                    $statusLabel = 'Ativa';
                                                }
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($user['nome']); ?>
                                                <?php if ($user['is_admin']): ?>
                                                    <span class="badge bg-primary ms-1">Admin</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php if ($user['validade_ate']): ?>
                                                    <?php echo date('d/m/Y', strtotime($user['validade_ate'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Não definida</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                            </td>
                                            <td>
                                                <?php echo $user['campanhas_ativas']; ?> / <?php echo $user['total_campanhas']; ?>
                                            </td>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($user['criado_em'])); ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="usuarios.php?edit=<?php echo $user['id']; ?>" class="btn btn-outline-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-success" 
                                                            onclick="extenderValidade(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nome']); ?>')" 
                                                            title="Estender Validade">
                                                        <i class="fas fa-calendar-plus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-3">
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i> Nenhum usuário encontrado com os critérios selecionados.
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Extensão de Validade -->
<div class="modal fade" id="extenderValidadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Estender Validade da Conta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="formExtenderValidade" action="actions/extender_validade.php" method="POST">
                    <input type="hidden" id="usuario_id" name="usuario_id">
                    
                    <div class="mb-3">
                        <label for="nome_usuario" class="form-label">Usuário</label>
                        <input type="text" class="form-control" id="nome_usuario" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dias_extensao" class="form-label">Dias a Adicionar</label>
                        <select class="form-select" id="dias_extensao" name="dias_extensao">
                            <option value="30">30 dias</option>
                            <option value="60">60 dias</option>
                            <option value="90">90 dias</option>
                            <option value="180">180 dias</option>
                            <option value="365">365 dias</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacao" class="form-label">Observação (opcional)</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="document.getElementById('formExtenderValidade').submit();">
                    <i class="fas fa-save me-1"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Estilos para cards de estatísticas */
.stats-card {
    display: flex;
    align-items: center;
    padding: 15px;
    border-radius: 12px;
    background-color: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    height: 100%;
    transition: transform 0.3s;
}

.stats-card:hover {
    transform: translateY(-3px);
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-right: 15px;
}

.stats-content {
    flex: 1;
}

.stats-number {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 2px;
    line-height: 1;
}

.stats-label {
    font-size: 0.8rem;
    color: #6c757d;
}

/* Cores de fundo para ícones */
.bg-primary-light { background-color: rgba(52, 152, 219, 0.1); }
.bg-success-light { background-color: rgba(46, 204, 113, 0.1); }
.bg-warning-light { background-color: rgba(243, 156, 18, 0.1); }
.bg-danger-light { background-color: rgba(231, 76, 60, 0.1); }
.bg-info-light { background-color: rgba(26, 188, 156, 0.1); }
.bg-secondary-light { background-color: rgba(108, 117, 125, 0.1); }

/* Cartão de filtro */
.filter-card {
    border-radius: 15px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    background-color: #f8f9fa;
}

/* Tabela estilizada */
.table {
    border-collapse: separate;
    border-spacing: 0;
}

.table thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.table tbody tr:hover {
    background-color: rgba(52, 152, 219, 0.03);
}
</style>

<!-- JavaScript para Modal -->
<script>
function extenderValidade(userId, userName) {
    document.getElementById('usuario_id').value = userId;
    document.getElementById('nome_usuario').value = userName;
    
    // Abrir modal
    var modal = new bootstrap.Modal(document.getElementById('extenderValidadeModal'));
    modal.show();
}
</script>

<?php
// Incluir o rodapé
include '../includes/footer.php';
?>