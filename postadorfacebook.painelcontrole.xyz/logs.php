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

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Filtros
$campanha_id = isset($_GET['campanha_id']) && is_numeric($_GET['campanha_id']) ? intval($_GET['campanha_id']) : null;
$grupo_id = isset($_GET['grupo_id']) && is_numeric($_GET['grupo_id']) ? intval($_GET['grupo_id']) : null;
$status = isset($_GET['status']) ? $db->real_escape_string($_GET['status']) : null;
$data_inicio = isset($_GET['data_inicio']) ? $db->real_escape_string($_GET['data_inicio']) : date('Y-m-d', strtotime('-7 days'));
$data_fim = isset($_GET['data_fim']) ? $db->real_escape_string($_GET['data_fim']) : date('Y-m-d');
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? intval($_GET['pagina']) : 1;

// Itens por página
$itens_por_pagina = 20;
$offset = ($pagina - 1) * $itens_por_pagina;

// Construir consulta base
$query_base = "
    FROM logs_postagem l
    JOIN campanhas c ON l.campanha_id = c.id
    JOIN anuncios a ON l.anuncio_id = a.id
    JOIN grupos_facebook g ON l.grupo_id = g.id
    WHERE c.usuario_id = ?
";

$params = [$userId];
$param_types = "i";

// Aplicar filtros
if ($campanha_id) {
    $query_base .= " AND l.campanha_id = ?";
    $params[] = $campanha_id;
    $param_types .= "i";
}

if ($grupo_id) {
    $query_base .= " AND l.grupo_id = ?";
    $params[] = $grupo_id;
    $param_types .= "i";
}

if ($status) {
    $query_base .= " AND l.status = ?";
    $params[] = $status;
    $param_types .= "s";
}

if ($data_inicio) {
    $query_base .= " AND DATE(l.postado_em) >= ?";
    $params[] = $data_inicio;
    $param_types .= "s";
}

if ($data_fim) {
    $query_base .= " AND DATE(l.postado_em) <= ?";
    $params[] = $data_fim;
    $param_types .= "s";
}

// Contar total de registros
$query_count = "SELECT COUNT(*) as total " . $query_base;
$stmt_count = $db->prepare($query_count);
$stmt_count->bind_param($param_types, ...$params);
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];

// Calcular total de páginas
$total_paginas = ceil($total_registros / $itens_por_pagina);

// Consultar logs com paginação
$query_logs = "
    SELECT l.*, 
           c.nome as campanha_nome, 
           a.titulo as anuncio_titulo,
           g.nome as grupo_nome
    " . $query_base . "
    ORDER BY l.postado_em DESC
    LIMIT ? OFFSET ?
";

$params_paginados = $params;
$params_paginados[] = $itens_por_pagina;
$params_paginados[] = $offset;
$param_types_paginados = $param_types . "ii";

$stmt_logs = $db->prepare($query_logs);
$stmt_logs->bind_param($param_types_paginados, ...$params_paginados);
$stmt_logs->execute();
$result_logs = $stmt_logs->get_result();

// Buscar campanhas para o filtro
$query_campanhas = "SELECT id, nome FROM campanhas WHERE usuario_id = ? ORDER BY nome ASC";
$stmt_campanhas = $db->prepare($query_campanhas);
$stmt_campanhas->bind_param("i", $userId);
$stmt_campanhas->execute();
$result_campanhas = $stmt_campanhas->get_result();

// Buscar grupos para o filtro
$query_grupos = "SELECT id, nome FROM grupos_facebook WHERE usuario_id = ? ORDER BY nome ASC";
$stmt_grupos = $db->prepare($query_grupos);
$stmt_grupos->bind_param("i", $userId);
$stmt_grupos->execute();
$result_grupos = $stmt_grupos->get_result();

// Calcular estatísticas
$query_stats = "
    SELECT 
        COUNT(*) as total_postagens,
        SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as total_sucesso,
        SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as total_falhas,
        COUNT(DISTINCT campanha_id) as total_campanhas,
        COUNT(DISTINCT grupo_id) as total_grupos
    " . $query_base;

$stmt_stats = $db->prepare($query_stats);
$stmt_stats->bind_param($param_types, ...$params);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

// Taxa de sucesso
$taxa_sucesso = $stats['total_postagens'] > 0 ? ($stats['total_sucesso'] / $stats['total_postagens']) * 100 : 0;

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
                        <i class="fas fa-history me-2 text-primary"></i> Logs de Postagens
                    </h5>
                </div>
                <div class="modern-card-body">
                    <!-- Estatísticas em Cards -->
                    <div class="row mb-4">
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-primary-light text-primary">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['total_postagens']); ?></h5>
                                    <span class="stats-label">Total de Postagens</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-success-light text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['total_sucesso']); ?></h5>
                                    <span class="stats-label">Postagens com Sucesso</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-danger-light text-danger">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['total_falhas']); ?></h5>
                                    <span class="stats-label">Postagens com Falha</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-info-light text-info">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($taxa_sucesso, 1); ?>%</h5>
                                    <span class="stats-label">Taxa de Sucesso</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-warning-light text-warning">
                                    <i class="fas fa-bullhorn"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['total_campanhas']); ?></h5>
                                    <span class="stats-label">Campanhas</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-purple-light text-purple">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stats-content">
                                    <h5 class="stats-number"><?php echo number_format($stats['total_grupos']); ?></h5>
                                    <span class="stats-label">Grupos Alcançados</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtros -->
                    <div class="card filter-card mb-4">
                        <div class="card-body">
                            <form method="GET" action="logs.php" class="row g-3">
                                <div class="col-md-2">
                                    <label for="campanha_id" class="form-label">Campanha</label>
                                    <select class="form-select" id="campanha_id" name="campanha_id">
                                        <option value="">Todas</option>
                                        <?php while ($campanha = $result_campanhas->fetch_assoc()): ?>
                                            <option value="<?php echo $campanha['id']; ?>" <?php echo $campanha_id == $campanha['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($campanha['nome']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="grupo_id" class="form-label">Grupo</label>
                                    <select class="form-select" id="grupo_id" name="grupo_id">
                                        <option value="">Todos</option>
                                        <?php while ($grupo = $result_grupos->fetch_assoc()): ?>
                                            <option value="<?php echo $grupo['id']; ?>" <?php echo $grupo_id == $grupo['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($grupo['nome']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Todos</option>
                                        <option value="sucesso" <?php echo $status === 'sucesso' ? 'selected' : ''; ?>>Sucesso</option>
                                        <option value="falha" <?php echo $status === 'falha' ? 'selected' : ''; ?>>Falha</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="data_inicio" class="form-label">Data Início</label>
                                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="data_fim" class="form-label">Data Fim</label>
                                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label d-block">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter me-1"></i> Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Tabela de Logs -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Campanha</th>
                                    <th>Anúncio</th>
                                    <th>Grupo</th>
                                    <th>Status</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_logs->num_rows > 0): ?>
                                    <?php while ($log = $result_logs->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['postado_em'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['campanha_nome']); ?></td>
                                            <td><?php echo htmlspecialchars($log['anuncio_titulo']); ?></td>
                                            <td><?php echo htmlspecialchars($log['grupo_nome']); ?></td>
                                            <td>
                                                <?php if ($log['status'] === 'sucesso'): ?>
                                                    <span class="badge bg-success">Sucesso</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Falha</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($log['status'] === 'sucesso'): ?>
                                                    <?php if (!empty($log['post_id'])): ?>
                                                        <a href="https://facebook.com/<?php echo $log['post_id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-external-link-alt me-1"></i> Ver Post
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">ID não disponível</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($log['mensagem_erro']); ?>">
                                                        <i class="fas fa-exclamation-triangle me-1"></i> Ver Erro
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-3">
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i> Nenhum registro de postagem encontrado com os filtros aplicados.
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginação -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                <span class="text-muted">Exibindo <?php echo min($offset + 1, $total_registros); ?>-<?php echo min($offset + $itens_por_pagina, $total_registros); ?> de <?php echo $total_registros; ?> registros</span>
                            </div>
                            <nav aria-label="Navegação de página">
                                <ul class="pagination mb-0">
                                    <?php if ($pagina > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=1<?php echo http_build_query(array_filter(['campanha_id' => $campanha_id, 'grupo_id' => $grupo_id, 'status' => $status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])) ? '&' . http_build_query(array_filter(['campanha_id' => $campanha_id, 'grupo_id' => $grupo_id, 'status' => $status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])) : ''; ?>" aria-label="Primeira">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?><?php echo http_build_query(array_filter(['campanha_id' => $campanha_id, 'grupo_id' => $grupo_id, 'status' => $status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])) ? '&' . http_build_query(array_filter(['campanha_id' => $campanha_id, 'grupo_id' => $grupo_id, 'status' => $status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])) : ''; ?>">
                                                <span aria-hidden="true">&lt;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php 
                                        $pagina_inicio = max(1, $pagina - 2);
                                        $pagina_fim = min($total_paginas, $pagina + 2);
                                    ?>
                                    
                                    <?php for ($i = $pagina_inicio; $i <= $pagina_fim; $i++): ?>
                                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo http_build_query(array_filter(['campanha_id' => $campanha_id, 'grupo_id' => $grupo_id, 'status' => $status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])) ? '&' . http_build_query(array_filter(['campanha_id' => $campanha_id, 'grupo_id' => $grupo_id, 'status' => $status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($pagina < $total_paginas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?><?php echo http_build_query(array_filter(['campanha_id' => $campanha_id, 'grupo_id' => $grupo_id, 'status' => $status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])) ? '&' . http_build_query(array_filter(['campanha_id' => $campanha_id, 'grupo_id' => $grupo_id, 'status' => $status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])) : ''; ?>">
                                                <span aria-hidden="true">&gt;</span>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?php echo $total_paginas; ?><?php echo http_build_query(array_filter(['campanha_id' => $campanha_id, 'grupo_id' => $grupo_id, 'status' => $status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])) ? '&' . http_build_query(array_filter(['campanha_id' => $campanha_id, 'grupo_id' => $grupo_id, 'status' => $status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])) : ''; ?>" aria-label="Última">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficos de Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-chart-pie me-2 text-primary"></i> Distribuição de Status
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-chart-line me-2 text-primary"></i> Evolução de Postagens
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="chart-container">
                        <canvas id="evolutionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Principais Erros (se aplicável) -->
    <?php if ($stats['total_falhas'] > 0): ?>
        <?php
        // Consultar os principais tipos de erro
        $query_erros = "
            SELECT 
                mensagem_erro,
                COUNT(*) as total
            " . $query_base . "
            AND status = 'falha'
            GROUP BY mensagem_erro
            ORDER BY total DESC
            LIMIT 5
        ";
        $stmt_erros = $db->prepare($query_erros);
        $stmt_erros->bind_param($param_types, ...$params);
        $stmt_erros->execute();
        $result_erros = $stmt_erros->get_result();
        ?>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="modern-card">
                    <div class="modern-card-header">
                        <h5 class="modern-card-title">
                            <i class="fas fa-exclamation-triangle me-2 text-warning"></i> Principais Erros Encontrados
                        </h5>
                    </div>
                    <div class="modern-card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Mensagem de Erro</th>
                                        <th>Ocorrências</th>
                                        <th>Porcentagem</th>
                                        <th>Sugestão de Resolução</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($erro = $result_erros->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($erro['mensagem_erro']); ?></td>
                                            <td><?php echo number_format($erro['total']); ?></td>
                                            <td>
                                                <?php $percent = ($erro['total'] / $stats['total_falhas']) * 100; ?>
                                                <div class="progress">
                                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $percent; ?>%;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo number_format($percent, 1); ?>%</div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                // Sugestões baseadas em mensagens comuns de erro
                                                if (stripos($erro['mensagem_erro'], 'token') !== false) {
                                                    echo "Reconecte sua conta do Facebook para renovar o token de acesso.";
                                                } elseif (stripos($erro['mensagem_erro'], 'permission') !== false) {
                                                    echo "Verifique se o Facebook App tem as permissões necessárias.";
                                                } elseif (stripos($erro['mensagem_erro'], 'limit') !== false) {
                                                    echo "Aguarde um tempo antes de fazer novas postagens (limite de API).";
                                                } else {
                                                    echo "Verifique as configurações do seu aplicativo do Facebook.";
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 2px;
    line-height: 1;
}

.stats-label {
    font-size: 0.8rem;
    color: #6c757d;
}

/* Cartão de filtro */
.filter-card {
    border-radius: 15px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    background-color: #f8f9fa;
}

/* Contêiner de gráfico */
.chart-container {
    position: relative;
    height: 250px;
    width: 100%;
}

/* Cores de fundo para gráficos */
.bg-primary-light { background-color: rgba(52, 152, 219, 0.1); }
.bg-success-light { background-color: rgba(46, 204, 113, 0.1); }
.bg-warning-light { background-color: rgba(243, 156, 18, 0.1); }
.bg-danger-light { background-color: rgba(231, 76, 60, 0.1); }
.bg-info-light { background-color: rgba(26, 188, 156, 0.1); }
.bg-purple-light { background-color: rgba(155, 89, 182, 0.1); }

.text-purple { color: #9b59b6; }

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

/* Paginação estilizada */
.pagination .page-link {
    border-radius: 6px;
    margin: 0 2px;
    color: #3498db;
}

.pagination .page-item.active .page-link {
    background-color: #3498db;
    border-color: #3498db;
}
</style>

<!-- JavaScript para Gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    if(typeof bootstrap !== 'undefined') {
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Gráfico de distribuição de status
    const statusChart = document.getElementById('statusChart');
    if (statusChart) {
        new Chart(statusChart, {
            type: 'doughnut',
            data: {
                labels: ['Sucesso', 'Falha'],
                datasets: [{
                    data: [
                        <?php echo $stats['total_sucesso']; ?>,
                        <?php echo $stats['total_falhas']; ?>
                    ],
                    backgroundColor: [
                        'rgba(46, 204, 113, 0.7)',
                        'rgba(231, 76, 60, 0.7)'
                    ],
                    borderColor: [
                        'rgba(46, 204, 113, 1)',
                        'rgba(231, 76, 60, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Gráfico de evolução das postagens
    <?php
    // Dados para o gráfico de evolução
    $query_evolucao = "
        SELECT 
            DATE(postado_em) as data,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
            SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falhas
        " . $query_base . "
        GROUP BY DATE(postado_em)
        ORDER BY data ASC
        LIMIT 30
    ";
    
    $stmt_evolucao = $db->prepare($query_evolucao);
    $stmt_evolucao->bind_param($param_types, ...$params);
    $stmt_evolucao->execute();
    $result_evolucao = $stmt_evolucao->get_result();
    
    $datas = [];
    $sucessos = [];
    $falhas = [];
    
    while ($row = $result_evolucao->fetch_assoc()) {
        $datas[] = date('d/m', strtotime($row['data']));
        $sucessos[] = $row['sucessos'];
        $falhas[] = $row['falhas'];
    }
    ?>
    
    const evolutionChart = document.getElementById('evolutionChart');
    if (evolutionChart) {
        new Chart(evolutionChart, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($datas); ?>,
                datasets: [
                    {
                        label: 'Sucesso',
                        data: <?php echo json_encode($sucessos); ?>,
                        backgroundColor: 'rgba(46, 204, 113, 0.2)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Falha',
                        data: <?php echo json_encode($falhas); ?>,
                        backgroundColor: 'rgba(231, 76, 60, 0.2)',
                        borderColor: 'rgba(231, 76, 60, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php
// Incluir o rodapé
include 'includes/footer.php';
?>