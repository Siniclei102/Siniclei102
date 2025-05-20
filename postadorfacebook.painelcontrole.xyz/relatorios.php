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
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Definir período de análise (padrão: últimos 30 dias)
$periodo = isset($_GET['periodo']) ? intval($_GET['periodo']) : 30;
$periodos_validos = [7, 15, 30, 60, 90];
if (!in_array($periodo, $periodos_validos)) {
    $periodo = 30;
}

$data_inicio = date('Y-m-d', strtotime("-{$periodo} days"));
$data_fim = date('Y-m-d');

// Definir filtros
$campanha_id = isset($_GET['campanha_id']) && is_numeric($_GET['campanha_id']) ? intval($_GET['campanha_id']) : 0;
$grupo_id = isset($_GET['grupo_id']) && is_numeric($_GET['grupo_id']) ? intval($_GET['grupo_id']) : 0;
$filtrar_status = isset($_GET['status']) ? $_GET['status'] : '';

// Obter campanhas do usuário para o seletor
$queryMinhaCampanhas = "SELECT id, nome FROM campanhas WHERE usuario_id = ? ORDER BY nome";
$stmtMinhaCampanhas = $db->prepare($queryMinhaCampanhas);
$stmtMinhaCampanhas->bind_param("i", $userId);
$stmtMinhaCampanhas->execute();
$resultMinhaCampanhas = $stmtMinhaCampanhas->get_result();

// Obter grupos do usuário para o seletor
$queryMeusGrupos = "SELECT id, nome FROM grupos_facebook WHERE usuario_id = ? ORDER BY nome";
$stmtMeusGrupos = $db->prepare($queryMeusGrupos);
$stmtMeusGrupos->bind_param("i", $userId);
$stmtMeusGrupos->execute();
$resultMeusGrupos = $stmtMeusGrupos->get_result();

// Construir where clause com base nos filtros
$where_clause = [];
$params = [];
$param_types = "";

// Filtro base por usuário (exceto admin)
if (!$isAdmin) {
    $where_clause[] = "(c.usuario_id = ?)";
    $params[] = $userId;
    $param_types .= "i";
}

// Filtros adicionais
if ($campanha_id > 0) {
    $where_clause[] = "(c.id = ?)";
    $params[] = $campanha_id;
    $param_types .= "i";
}

if ($grupo_id > 0) {
    $where_clause[] = "(lp.grupo_id = ?)";
    $params[] = $grupo_id;
    $param_types .= "i";
}

if ($filtrar_status === 'sucesso' || $filtrar_status === 'falha') {
    $where_clause[] = "(lp.status = ?)";
    $params[] = $filtrar_status;
    $param_types .= "s";
}

// Adicionar filtro de data
$where_clause[] = "(lp.postado_em >= ? AND lp.postado_em <= DATE_ADD(?, INTERVAL 1 DAY))";
$params[] = $data_inicio;
$params[] = $data_fim;
$param_types .= "ss";

$where_str = count($where_clause) > 0 ? "WHERE " . implode(" AND ", $where_clause) : "";

// Obter estatísticas gerais de postagens
$queryEstatisticas = "
    SELECT 
        COUNT(*) as total_postagens,
        SUM(CASE WHEN lp.status = 'sucesso' THEN 1 ELSE 0 END) as postagens_sucesso,
        SUM(CASE WHEN lp.status = 'falha' THEN 1 ELSE 0 END) as postagens_falha,
        COUNT(DISTINCT lp.grupo_id) as grupos_alcancados,
        COUNT(DISTINCT lp.campanha_id) as campanhas_ativas,
        COUNT(DISTINCT lp.anuncio_id) as anuncios_utilizados
    FROM 
        logs_postagem lp
        JOIN campanhas c ON lp.campanha_id = c.id
    $where_str
";

$stmtEstatisticas = $db->prepare($queryEstatisticas);
if (!empty($params)) {
    $stmtEstatisticas->bind_param($param_types, ...$params);
}
$stmtEstatisticas->execute();
$resultEstatisticas = $stmtEstatisticas->get_result()->fetch_assoc();

// Calcular taxa de sucesso
$taxa_sucesso = $resultEstatisticas['total_postagens'] > 0 
    ? round(($resultEstatisticas['postagens_sucesso'] / $resultEstatisticas['total_postagens']) * 100, 1) 
    : 0;

// Obter dados para gráfico de postagens por dia
$queryGrafico = "
    SELECT 
        DATE(lp.postado_em) as data,
        COUNT(*) as total,
        SUM(CASE WHEN lp.status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
        SUM(CASE WHEN lp.status = 'falha' THEN 1 ELSE 0 END) as falha
    FROM 
        logs_postagem lp
        JOIN campanhas c ON lp.campanha_id = c.id
    $where_str
    GROUP BY DATE(lp.postado_em)
    ORDER BY DATE(lp.postado_em) ASC
";

$stmtGrafico = $db->prepare($queryGrafico);
if (!empty($params)) {
    $stmtGrafico->bind_param($param_types, ...$params);
}
$stmtGrafico->execute();
$resultGrafico = $stmtGrafico->get_result();

// Preparar dados para o gráfico
$labels = [];
$sucessos = [];
$falhas = [];

while ($row = $resultGrafico->fetch_assoc()) {
    $labels[] = date('d/m', strtotime($row['data']));
    $sucessos[] = $row['sucesso'];
    $falhas[] = $row['falha'];
}

// Obter desempenho por grupo
$queryGrupos = "
    SELECT 
        g.id,
        g.nome as grupo_nome,
        COUNT(*) as total_postagens,
        SUM(CASE WHEN lp.status = 'sucesso' THEN 1 ELSE 0 END) as postagens_sucesso,
        SUM(CASE WHEN lp.status = 'falha' THEN 1 ELSE 0 END) as postagens_falha,
        MAX(lp.postado_em) as ultima_postagem
    FROM 
        logs_postagem lp
        JOIN campanhas c ON lp.campanha_id = c.id
        JOIN grupos_facebook g ON lp.grupo_id = g.id
    $where_str
    GROUP BY g.id
    ORDER BY total_postagens DESC
    LIMIT 10
";

$stmtGrupos = $db->prepare($queryGrupos);
if (!empty($params)) {
    $stmtGrupos->bind_param($param_types, ...$params);
}
$stmtGrupos->execute();
$resultGrupos = $stmtGrupos->get_result();

// Obter principais erros
$queryErros = "
    SELECT 
        lp.mensagem_erro,
        COUNT(*) as total
    FROM 
        logs_postagem lp
        JOIN campanhas c ON lp.campanha_id = c.id
    $where_str
    AND lp.status = 'falha'
    GROUP BY lp.mensagem_erro
    ORDER BY total DESC
    LIMIT 5
";

$stmtErros = $db->prepare($queryErros);
if (!empty($params)) {
    $stmtErros->bind_param($param_types, ...$params);
}
$stmtErros->execute();
$resultErros = $stmtErros->get_result();

// Obter anúncios mais usados
$queryAnuncios = "
    SELECT 
        a.id,
        a.titulo,
        COUNT(*) as total_postagens,
        SUM(CASE WHEN lp.status = 'sucesso' THEN 1 ELSE 0 END) as postagens_sucesso
    FROM 
        logs_postagem lp
        JOIN campanhas c ON lp.campanha_id = c.id
        JOIN anuncios a ON lp.anuncio_id = a.id
    $where_str
    GROUP BY a.id
    ORDER BY total_postagens DESC
    LIMIT 5
";

$stmtAnuncios = $db->prepare($queryAnuncios);
if (!empty($params)) {
    $stmtAnuncios->bind_param($param_types, ...$params);
}
$stmtAnuncios->execute();
$resultAnuncios = $stmtAnuncios->get_result();

// Obter atividade recente
$queryAtividade = "
    SELECT 
        lp.id,
        lp.campanha_id,
        c.nome as campanha_nome,
        g.nome as grupo_nome,
        a.titulo as anuncio_titulo,
        lp.status,
        lp.mensagem_erro,
        lp.postado_em
    FROM 
        logs_postagem lp
        JOIN campanhas c ON lp.campanha_id = c.id
        JOIN grupos_facebook g ON lp.grupo_id = g.id
        JOIN anuncios a ON lp.anuncio_id = a.id
    $where_str
    ORDER BY lp.postado_em DESC
    LIMIT 20
";

$stmtAtividade = $db->prepare($queryAtividade);
if (!empty($params)) {
    $stmtAtividade->bind_param($param_types, ...$params);
}
$stmtAtividade->execute();
$resultAtividade = $stmtAtividade->get_result();

// Verificar se foi solicitada exportação CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Obter todos os logs sem limite
    $queryExport = "
        SELECT 
            c.nome as campanha_nome,
            g.nome as grupo_nome,
            a.titulo as anuncio_titulo,
            lp.status,
            lp.mensagem_erro,
            lp.postado_em
        FROM 
            logs_postagem lp
            JOIN campanhas c ON lp.campanha_id = c.id
            JOIN grupos_facebook g ON lp.grupo_id = g.id
            JOIN anuncios a ON lp.anuncio_id = a.id
        $where_str
        ORDER BY lp.postado_em DESC
    ";

    $stmtExport = $db->prepare($queryExport);
    if (!empty($params)) {
        $stmtExport->bind_param($param_types, ...$params);
    }
    $stmtExport->execute();
    $resultExport = $stmtExport->get_result();

    // Configurar headers para download do CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_postagens_' . date('Y-m-d') . '.csv');

    // Criar arquivo CSV
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM para compatibilidade com Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos
    fputcsv($output, [
        'Campanha',
        'Grupo',
        'Anúncio',
        'Status',
        'Erro',
        'Data/Hora'
    ]);
    
    // Dados
    while ($row = $resultExport->fetch_assoc()) {
        fputcsv($output, [
            $row['campanha_nome'],
            $row['grupo_nome'],
            $row['anuncio_titulo'],
            $row['status'] === 'sucesso' ? 'Sucesso' : 'Falha',
            $row['mensagem_erro'],
            date('d/m/Y H:i', strtotime($row['postado_em']))
        ]);
    }
    
    fclose($output);
    exit;
}

// Incluir o cabeçalho
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0 text-gray-800">Relatórios Avançados</h1>
            <p class="mb-0 text-muted">Análise detalhada de desempenho das postagens</p>
        </div>
        <div class="col-md-4 text-end">
            <form method="GET" action="relatorios.php" class="d-inline-flex">
                <?php if ($campanha_id): ?>
                    <input type="hidden" name="campanha_id" value="<?php echo $campanha_id; ?>">
                <?php endif; ?>
                <?php if ($grupo_id): ?>
                    <input type="hidden" name="grupo_id" value="<?php echo $grupo_id; ?>">
                <?php endif; ?>
                <?php if ($filtrar_status): ?>
                    <input type="hidden" name="status" value="<?php echo $filtrar_status; ?>">
                <?php endif; ?>
                
                <select class="form-select form-select-sm me-2" name="periodo" onchange="this.form.submit()">
                    <option value="7" <?php echo $periodo == 7 ? 'selected' : ''; ?>>Últimos 7 dias</option>
                    <option value="15" <?php echo $periodo == 15 ? 'selected' : ''; ?>>Últimos 15 dias</option>
                    <option value="30" <?php echo $periodo == 30 ? 'selected' : ''; ?>>Últimos 30 dias</option>
                    <option value="60" <?php echo $periodo == 60 ? 'selected' : ''; ?>>Últimos 60 dias</option>
                    <option value="90" <?php echo $periodo == 90 ? 'selected' : ''; ?>>Últimos 90 dias</option>
                </select>
                <span class="form-text ms-2 mt-1">
                    <strong>Período:</strong> <?php echo date('d/m/Y', strtotime($data_inicio)); ?> - <?php echo date('d/m/Y', strtotime($data_fim)); ?>
                </span>
            </form>
        </div>
    </div>
    
    <!-- Filtros do Relatório -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-filter me-2 text-primary"></i> Filtros
                    </h5>
                </div>
                <div class="modern-card-body">
                    <form method="GET" action="relatorios.php" class="row g-3 align-items-end">
                        <!-- Filtro de período -->
                        <input type="hidden" name="periodo" value="<?php echo $periodo; ?>">
                        
                        <!-- Filtro de campanha -->
                        <div class="col-md-3">
                            <label for="campanha_id" class="form-label">Campanha</label>
                            <select class="form-select" id="campanha_id" name="campanha_id">
                                <option value="">Todas as campanhas</option>
                                <?php while ($campanha = $resultMinhaCampanhas->fetch_assoc()): ?>
                                    <option value="<?php echo $campanha['id']; ?>" <?php echo $campanha_id == $campanha['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($campanha['nome']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Filtro de grupo -->
                        <div class="col-md-3">
                            <label for="grupo_id" class="form-label">Grupo</label>
                            <select class="form-select" id="grupo_id" name="grupo_id">
                                <option value="">Todos os grupos</option>
                                <?php while ($grupo = $resultMeusGrupos->fetch_assoc()): ?>
                                    <option value="<?php echo $grupo['id']; ?>" <?php echo $grupo_id == $grupo['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($grupo['nome']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Filtro de status -->
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="sucesso" <?php echo $filtrar_status === 'sucesso' ? 'selected' : ''; ?>>Sucesso</option>
                                <option value="falha" <?php echo $filtrar_status === 'falha' ? 'selected' : ''; ?>>Falha</option>
                            </select>
                        </div>
                        
                        <!-- Botões -->
                        <div class="col-md-4 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Aplicar Filtros
                            </button>
                            <a href="relatorios.php" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-sync-alt me-1"></i> Limpar
                            </a>
                            <button type="submit" class="btn btn-success" name="export" value="csv">
                                <i class="fas fa-file-csv me-1"></i> Exportar CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cards de Estatísticas -->
    <div class="row mb-4">
        <!-- Total de Postagens -->
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-primary-light text-primary">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="report-stat-value"><?php echo number_format($resultEstatisticas['total_postagens']); ?></div>
                        <div class="report-stat-title">Total de Postagens</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Taxa de Sucesso -->
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-success-light text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="report-stat-value"><?php echo $taxa_sucesso; ?>%</div>
                        <div class="report-stat-title">Taxa de Sucesso</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Postagens com Sucesso -->
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-info-light text-info">
                            <i class="fas fa-thumbs-up"></i>
                        </div>
                        <div class="report-stat-value"><?php echo number_format($resultEstatisticas['postagens_sucesso']); ?></div>
                        <div class="report-stat-title">Postagens com Sucesso</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Falhas -->
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-danger-light text-danger">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="report-stat-value"><?php echo number_format($resultEstatisticas['postagens_falha']); ?></div>
                        <div class="report-stat-title">Falhas</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Grupos Alcançados -->
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-warning-light text-warning">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="report-stat-value"><?php echo number_format($resultEstatisticas['grupos_alcancados']); ?></div>
                        <div class="report-stat-title">Grupos Alcançados</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Anúncios Utilizados -->
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-secondary-light text-secondary">
                            <i class="fas fa-ad"></i>
                        </div>
                        <div class="report-stat-value"><?php echo number_format($resultEstatisticas['anuncios_utilizados']); ?></div>
                        <div class="report-stat-title">Anúncios Utilizados</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficos e Tabelas -->
    <div class="row mb-4">
        <!-- Gráfico de Postagens -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-chart-bar me-2 text-primary"></i> Postagens por Dia
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="chart-container">
                        <canvas id="postagensChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Principais Erros -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-exclamation-triangle me-2 text-warning"></i> Principais Erros
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if ($resultErros->num_rows > 0): ?>
                        <ul class="error-list">
                            <?php while ($erro = $resultErros->fetch_assoc()): ?>
                                <li class="error-item">
                                    <div class="error-info">
                                        <div class="error-message"><?php echo htmlspecialchars(substr($erro['mensagem_erro'], 0, 80) . (strlen($erro['mensagem_erro']) > 80 ? '...' : '')); ?></div>
                                        <div class="error-count"><?php echo $erro['total']; ?> ocorrências</div>
                                    </div>
                                    <?php
                                    $percent = $resultEstatisticas['postagens_falha'] > 0 
                                        ? ($erro['total'] / $resultEstatisticas['postagens_falha']) * 100 
                                        : 0;
                                    ?>
                                    <div class="progress error-progress" title="<?php echo number_format($percent, 1); ?>% de todos os erros">
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $percent; ?>%" 
                                             aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h5>Nenhum erro no período</h5>
                            <p class="text-muted">Não foram registrados erros de postagem no período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <!-- Anúncios Mais Usados -->
        <div class="col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-ad me-2 text-primary"></i> Anúncios Mais Usados
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if ($resultAnuncios->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Anúncio</th>
                                        <th class="text-center">Total de Postagens</th>
                                        <th class="text-center">Sucesso</th>
                                        <th class="text-center">Taxa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($anuncio = $resultAnuncios->fetch_assoc()): ?>
                                        <?php 
                                        $taxa = $anuncio['total_postagens'] > 0 
                                            ? ($anuncio['postagens_sucesso'] / $anuncio['total_postagens']) * 100 
                                            : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="anuncios.php?editar=<?php echo $anuncio['id']; ?>">
                                                    <?php echo htmlspecialchars($anuncio['titulo']); ?>
                                                </a>
                                            </td>
                                            <td class="text-center"><?php echo number_format($anuncio['total_postagens']); ?></td>
                                            <td class="text-center"><?php echo number_format($anuncio['postagens_sucesso']); ?></td>
                                            <td class="text-center">
                                                <div class="progress" style="height: 10px;">
                                                    <div class="progress-bar bg-<?php echo $taxa >= 80 ? 'success' : ($taxa >= 50 ? 'warning' : 'danger'); ?>"
                                                         role="progressbar"
                                                         style="width: <?php echo $taxa; ?>%"
                                                         aria-valuenow="<?php echo $taxa; ?>"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <span class="small"><?php echo number_format($taxa, 1); ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-ad"></i>
                            </div>
                            <h5>Sem dados de anúncios</h5>
                            <p class="text-muted">Não há dados de anúncios para o período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Desempenho por Grupo -->
        <div class="col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-users me-2 text-primary"></i> Desempenho por Grupo
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if ($resultGrupos->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Grupo</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Sucesso</th>
                                        <th class="text-center">Última Postagem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($grupo = $resultGrupos->fetch_assoc()): ?>
                                        <?php 
                                        $taxa = $grupo['total_postagens'] > 0 
                                            ? ($grupo['postagens_sucesso'] / $grupo['total_postagens']) * 100 
                                            : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="grupos.php?editar=<?php echo $grupo['id']; ?>">
                                                    <?php echo htmlspecialchars($grupo['grupo_nome']); ?>
                                                </a>
                                            </td>
                                            <td class="text-center">
                                                <?php echo number_format($grupo['total_postagens']); ?>
                                                <div class="progress mt-1" style="height: 5px;">
                                                    <div class="progress-bar bg-<?php echo $taxa >= 80 ? 'success' : ($taxa >= 50 ? 'warning' : 'danger'); ?>"
                                                         role="progressbar"
                                                         style="width: <?php echo $taxa; ?>%"
                                                         aria-valuenow="<?php echo $taxa; ?>"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center"><?php echo number_format($grupo['postagens_sucesso']); ?> (<?php echo number_format($taxa, 0); ?>%)</td>
                                            <td class="text-center"><?php echo time_elapsed_string($grupo['ultima_postagem']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5>Sem dados de grupos</h5>
                            <p class="text-muted">Não há dados de grupos para o período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Histórico Detalhado -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header d-flex justify-content-between align-items-center">
                    <h5 class="modern-card-title">
                        <i class="fas fa-history me-2 text-primary"></i> Histórico de Atividades
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="refresh-history">
                        <i class="fas fa-sync-alt me-1"></i> Atualizar
                    </button>
                </div>
                <div class="modern-card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Campanha</th>
                                    <th>Grupo</th>
                                    <th>Anúncio</th>
                                    <th>Status</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($resultAtividade->num_rows > 0): ?>
                                    <?php while ($log = $resultAtividade->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($log['postado_em'])); ?></td>
                                            <td>
                                                <a href="campanhas.php?editar=<?php echo $log['campanha_id']; ?>">
                                                    <?php echo htmlspecialchars($log['campanha_nome']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['grupo_nome']); ?></td>
                                            <td><?php echo htmlspecialchars($log['anuncio_titulo']); ?></td>
                                            <td>
                                                <?php if ($log['status'] === 'sucesso'): ?>
                                                    <span class="badge bg-success">Sucesso</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Falha</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($log['status'] === 'falha' && !empty($log['mensagem_erro'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                                            data-bs-toggle="tooltip" 
                                                            data-bs-placement="top" 
                                                            title="<?php echo htmlspecialchars($log['mensagem_erro']); ?>">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="empty-state-icon mb-3">
                                                <i class="fas fa-calendar-times"></i>
                                            </div>
                                            <h5>Sem atividades registradas</h5>
                                            <p class="text-muted">Não há atividades registradas para o período e filtros selecionados.</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modern-card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Mostrando até 20 registros mais recentes</span>
                        <form method="GET" action="relatorios.php">
                            <input type="hidden" name="periodo" value="<?php echo $periodo; ?>">
                            <?php if ($campanha_id): ?>
                                <input type="hidden" name="campanha_id" value="<?php echo $campanha_id; ?>">
                            <?php endif; ?>
                            <?php if ($grupo_id): ?>
                                <input type="hidden" name="grupo_id" value="<?php echo $grupo_id; ?>">
                            <?php endif; ?>
                            <?php if ($filtrar_status): ?>
                                <input type="hidden" name="status" value="<?php echo $filtrar_status; ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-success" name="export" value="csv">
                                <i class="fas fa-file-csv me-1"></i> Exportar Relatório Completo
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Cards de estatísticas */
.report-stat-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    height: 100%;
    padding: 10px;
}

.report-stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 15px;
}

.report-stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 5px;
    line-height: 1;
}

.report-stat-title {
    font-size: 0.85rem;
    color: #6c757d;
}

/* Cores de fundo para ícones */
.bg-primary-light { background-color: rgba(52, 152, 219, 0.1); }
.bg-success-light { background-color: rgba(46, 204, 113, 0.1); }
.bg-warning-light { background-color: rgba(243, 156, 18, 0.1); }
.bg-danger-light { background-color: rgba(231, 76, 60, 0.1); }
.bg-info-light { background-color: rgba(26, 188, 156, 0.1); }
.bg-secondary-light { background-color: rgba(108, 117, 125, 0.1); }

/* Container de gráfico */
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

/* Lista de erros */
.error-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.error-item {
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.error-item:last-child {
    border-bottom: none;
}

.error-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.error-message {
    font-size: 0.9rem;
    color: #333;
}

.error-count {
    font-size: 0.8rem;
    color: #dc3545;
    font-weight: 600;
    white-space: nowrap;
}

.error-progress {
    height: 6px;
    border-radius: 3px;
}

/* Empty state */
.empty-state-icon {
    font-size: 3rem;
    color: #adb5bd;
}

/* Tooltip override */
.tooltip-inner {
    max-width: 300px;
    text-align: left;
}
</style>

<!-- JavaScript para Gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips do Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Gráfico de Postagens
    const ctxPostagens = document.getElementById('postagensChart').getContext('2d');
    new Chart(ctxPostagens, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [
                {
                    label: 'Sucesso',
                    data: <?php echo json_encode($sucessos); ?>,
                    backgroundColor: 'rgba(46, 204, 113, 0.7)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Falha',
                    data: <?php echo json_encode($falhas); ?>,
                    backgroundColor: 'rgba(231, 76, 60, 0.7)',
                    borderColor: 'rgba(231, 76, 60, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true,
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Postagens Diárias (<?php echo date('d/m/Y', strtotime($data_inicio)); ?> - <?php echo date('d/m/Y', strtotime($data_fim)); ?>)'
                }
            }
        }
    });
    
    // Botão de atualizar histórico
    document.getElementById('refresh-history').addEventListener('click', function() {
        location.reload();
    });
});
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