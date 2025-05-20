<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'classes/FacebookAPI.php';

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

// Inicializar objeto de API do Facebook
$fb = new FacebookAPI($db);

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

// Obter campanhas do usuário para o seletor
$queryMinhaCampanhas = "SELECT id, nome FROM campanhas WHERE usuario_id = ? ORDER BY nome";
$stmtMinhaCampanhas = $db->prepare($queryMinhaCampanhas);
$stmtMinhaCampanhas->bind_param("i", $userId);
$stmtMinhaCampanhas->execute();
$resultMinhaCampanhas = $stmtMinhaCampanhas->get_result();

// Obter grupos do usuário para o seletor
$queryMeusGrupos = "SELECT id, nome, facebook_id FROM grupos_facebook WHERE usuario_id = ? AND facebook_id IS NOT NULL ORDER BY nome";
$stmtMeusGrupos = $db->prepare($queryMeusGrupos);
$stmtMeusGrupos->bind_param("i", $userId);
$stmtMeusGrupos->execute();
$resultMeusGrupos = $stmtMeusGrupos->get_result();

// Construir a query base para estatísticas
$where_clause = [];
$params = [];
$param_types = "";

// Filtro base por usuário (exceto admin)
if (!$isAdmin) {
    $where_clause[] = "p.usuario_id = ?";
    $params[] = $userId;
    $param_types .= "i";
}

// Filtros adicionais
if ($campanha_id > 0) {
    $where_clause[] = "p.campanha_id = ?";
    $params[] = $campanha_id;
    $param_types .= "i";
}

if ($grupo_id > 0) {
    $where_clause[] = "p.grupo_id = ?";
    $params[] = $grupo_id;
    $param_types .= "i";
}

// Adicionar filtro de data
$where_clause[] = "p.data_postagem >= ? AND p.data_postagem <= DATE_ADD(?, INTERVAL 1 DAY)";
$params[] = $data_inicio;
$params[] = $data_fim;
$param_types .= "ss";

$where_str = count($where_clause) > 0 ? "WHERE " . implode(" AND ", $where_clause) : "";

// Obter estatísticas de engajamento
$queryEstatisticas = "
    SELECT 
        COUNT(*) as total_posts,
        SUM(p.curtidas) as total_curtidas,
        SUM(p.comentarios) as total_comentarios,
        SUM(p.compartilhamentos) as total_compartilhamentos,
        ROUND(AVG(p.curtidas), 1) as media_curtidas,
        ROUND(AVG(p.comentarios), 1) as media_comentarios,
        ROUND(AVG(p.compartilhamentos), 1) as media_compartilhamentos,
        COUNT(DISTINCT p.grupo_id) as grupos_alcancados
    FROM 
        facebook_posts p
    $where_str
";

$stmtEstatisticas = $db->prepare($queryEstatisticas);
if (!empty($params)) {
    $stmtEstatisticas->bind_param($param_types, ...$params);
}
$stmtEstatisticas->execute();
$resultEstatisticas = $stmtEstatisticas->get_result()->fetch_assoc();

// Estatísticas por tipo de reação
$queryReacoes = "
    SELECT 
        SUM(p.reacao_like) as total_likes,
        SUM(p.reacao_love) as total_love,
        SUM(p.reacao_wow) as total_wow,
        SUM(p.reacao_haha) as total_haha,
        SUM(p.reacao_sad) as total_sad,
        SUM(p.reacao_angry) as total_angry,
        SUM(p.reacao_care) as total_care
    FROM 
        facebook_posts p
    $where_str
";

$stmtReacoes = $db->prepare($queryReacoes);
if (!empty($params)) {
    $stmtReacoes->bind_param($param_types, ...$params);
}
$stmtReacoes->execute();
$resultReacoes = $stmtReacoes->get_result()->fetch_assoc();

// Calcular engajamento total
$engajamento_total = 
    $resultEstatisticas['total_curtidas'] + 
    $resultEstatisticas['total_comentarios'] + 
    $resultEstatisticas['total_compartilhamentos'];

$media_engajamento = $resultEstatisticas['total_posts'] > 0 
    ? $engajamento_total / $resultEstatisticas['total_posts'] 
    : 0;

// Obter dados para gráfico de engajamento por dia
$queryGrafico = "
    SELECT 
        DATE(p.data_postagem) as data,
        COUNT(*) as total_posts,
        SUM(p.curtidas) as total_curtidas,
        SUM(p.comentarios) as total_comentarios,
        SUM(p.compartilhamentos) as total_compartilhamentos
    FROM 
        facebook_posts p
    $where_str
    GROUP BY DATE(p.data_postagem)
    ORDER BY DATE(p.data_postagem)
";

$stmtGrafico = $db->prepare($queryGrafico);
if (!empty($params)) {
    $stmtGrafico->bind_param($param_types, ...$params);
}
$stmtGrafico->execute();
$resultGrafico = $stmtGrafico->get_result();

// Preparar dados para o gráfico
$labels = [];
$datasets_curtidas = [];
$datasets_comentarios = [];
$datasets_compartilhamentos = [];
$datasets_posts = [];

while ($row = $resultGrafico->fetch_assoc()) {
    $labels[] = date('d/m', strtotime($row['data']));
    $datasets_curtidas[] = $row['total_curtidas'];
    $datasets_comentarios[] = $row['total_comentarios'];
    $datasets_compartilhamentos[] = $row['total_compartilhamentos'];
    $datasets_posts[] = $row['total_posts'];
}

// Obter desempenho por grupo
$queryGrupos = "
    SELECT 
        g.id,
        g.nome,
        COUNT(*) as total_posts,
        SUM(p.curtidas) as total_curtidas,
        SUM(p.comentarios) as total_comentarios,
        SUM(p.compartilhamentos) as total_compartilhamentos,
        MAX(p.data_postagem) as ultima_postagem
    FROM 
        facebook_posts p
        JOIN grupos_facebook g ON p.grupo_id = g.id
    $where_str
    GROUP BY g.id
    ORDER BY (SUM(p.curtidas) + SUM(p.comentarios) + SUM(p.compartilhamentos)) DESC
    LIMIT 10
";

$stmtGrupos = $db->prepare($queryGrupos);
if (!empty($params)) {
    $stmtGrupos->bind_param($param_types, ...$params);
}
$stmtGrupos->execute();
$resultGrupos = $stmtGrupos->get_result();

// Obter desempenho por hora do dia
$queryHoras = "
    SELECT 
        HOUR(p.data_postagem) as hora,
        COUNT(*) as total_posts,
        SUM(p.curtidas) as total_curtidas,
        SUM(p.comentarios) as total_comentarios,
        SUM(p.compartilhamentos) as total_compartilhamentos,
        (SUM(p.curtidas) + SUM(p.comentarios) + SUM(p.compartilhamentos)) / COUNT(*) as media_engajamento
    FROM 
        facebook_posts p
    $where_str
    GROUP BY HOUR(p.data_postagem)
    ORDER BY HOUR(p.data_postagem)
";

$stmtHoras = $db->prepare($queryHoras);
if (!empty($params)) {
    $stmtHoras->bind_param($param_types, ...$params);
}
$stmtHoras->execute();
$resultHoras = $stmtHoras->get_result();

// Preparar dados para o gráfico de horas
$labels_horas = [];
$datasets_engajamento_horas = [];
$datasets_posts_horas = [];

while ($row = $resultHoras->fetch_assoc()) {
    $labels_horas[] = sprintf('%02d:00', $row['hora']);
    $datasets_engajamento_horas[] = round($row['media_engajamento'], 1);
    $datasets_posts_horas[] = $row['total_posts'];
}

// Obter desempenho por dia da semana
$queryDias = "
    SELECT 
        WEEKDAY(p.data_postagem) as dia_semana,
        COUNT(*) as total_posts,
        SUM(p.curtidas) as total_curtidas,
        SUM(p.comentarios) as total_comentarios,
        SUM(p.compartilhamentos) as total_compartilhamentos,
        (SUM(p.curtidas) + SUM(p.comentarios) + SUM(p.compartilhamentos)) / COUNT(*) as media_engajamento
    FROM 
        facebook_posts p
    $where_str
    GROUP BY WEEKDAY(p.data_postagem)
    ORDER BY WEEKDAY(p.data_postagem)
";

$stmtDias = $db->prepare($queryDias);
if (!empty($params)) {
    $stmtDias->bind_param($param_types, ...$params);
}
$stmtDias->execute();
$resultDias = $stmtDias->get_result();

// Mapear dias da semana
$dias_semana = [
    '0' => 'Segunda',
    '1' => 'Terça',
    '2' => 'Quarta',
    '3' => 'Quinta',
    '4' => 'Sexta',
    '5' => 'Sábado',
    '6' => 'Domingo'
];

// Preparar dados para o gráfico de dias da semana
$labels_dias = [];
$datasets_engajamento_dias = [];
$datasets_posts_dias = [];

// Inicializar arrays com zeros para todos os dias
foreach ($dias_semana as $index => $nome) {
    $dias_dados[$index] = [
        'nome' => $nome,
        'total_posts' => 0,
        'total_curtidas' => 0,
        'total_comentarios' => 0,
        'total_compartilhamentos' => 0,
        'media_engajamento' => 0
    ];
}

// Preencher com dados reais
while ($row = $resultDias->fetch_assoc()) {
    $dias_dados[$row['dia_semana']] = [
        'nome' => $dias_semana[$row['dia_semana']],
        'total_posts' => $row['total_posts'],
        'total_curtidas' => $row['total_curtidas'],
        'total_comentarios' => $row['total_comentarios'],
        'total_compartilhamentos' => $row['total_compartilhamentos'],
        'media_engajamento' => round($row['media_engajamento'], 1)
    ];
}

// Preparar arrays para o gráfico
foreach ($dias_dados as $dia) {
    $labels_dias[] = $dia['nome'];
    $datasets_engajamento_dias[] = $dia['media_engajamento'];
    $datasets_posts_dias[] = $dia['total_posts'];
}

// Obter as últimas postagens com maior engajamento
$queryMelhoresPostagens = "
    SELECT 
        p.id,
        p.post_id,
        p.texto,
        g.nome as grupo_nome,
        c.nome as campanha_nome,
        p.data_postagem,
        p.curtidas,
        p.comentarios,
        p.compartilhamentos,
        (p.curtidas + p.comentarios + p.compartilhamentos) as engajamento_total
    FROM 
        facebook_posts p
        JOIN grupos_facebook g ON p.grupo_id = g.id
        JOIN campanhas c ON p.campanha_id = c.id
    $where_str
    ORDER BY engajamento_total DESC
    LIMIT 5
";

$stmtMelhoresPostagens = $db->prepare($queryMelhoresPostagens);
if (!empty($params)) {
    $stmtMelhoresPostagens->bind_param($param_types, ...$params);
}
$stmtMelhoresPostagens->execute();
$resultMelhoresPostagens = $stmtMelhoresPostagens->get_result();

// Verificar se há tokens de acesso do Facebook
$has_facebook_token = $fb->hasUserToken($userId);

// Incluir o cabeçalho
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0 text-gray-800">Métricas do Facebook</h1>
            <p class="mb-0 text-muted">Análise de engajamento e alcance das postagens</p>
        </div>
        <div class="col-md-4 text-end">
            <form method="GET" action="metricas-facebook.php" class="d-inline-flex">
                <?php if ($campanha_id): ?>
                    <input type="hidden" name="campanha_id" value="<?php echo $campanha_id; ?>">
                <?php endif; ?>
                <?php if ($grupo_id): ?>
                    <input type="hidden" name="grupo_id" value="<?php echo $grupo_id; ?>">
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
    
    <?php if (!$has_facebook_token): ?>
    <!-- Aviso de conexão com Facebook -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <div class="alert-icon me-3">
                        <i class="fab fa-facebook-square"></i>
                    </div>
                    <div>
                        <strong>Conexão com Facebook necessária!</strong> Para obter métricas detalhadas e atualizadas, conecte-se com sua conta do Facebook.
                        <a href="conectar-facebook.php" class="alert-link">Conectar agora</a>.
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
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
                    <form method="GET" action="metricas-facebook.php" class="row g-3 align-items-end">
                        <!-- Filtro de período -->
                        <input type="hidden" name="periodo" value="<?php echo $periodo; ?>">
                        
                        <!-- Filtro de campanha -->
                        <div class="col-md-5">
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
                        <div class="col-md-5">
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
                        
                        <!-- Botões -->
                        <div class="col-md-2 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Aplicar Filtros
                            </button>
                            <a href="metricas-facebook.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sync-alt me-1"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cards de Estatísticas -->
    <div class="row mb-4">
        <!-- Total de Postagens -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-primary-light text-primary">
                            <i class="fas fa-thumbs-up"></i>
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <div class="report-stat-value"><?php echo number_format($resultEstatisticas['total_curtidas']); ?></div>
                            <div class="report-stat-title">Total de Curtidas</div>
                            <div class="report-stat-subtitle">
                                <span class="text-success">Média: <?php echo number_format($resultEstatisticas['media_curtidas'], 1); ?> por post</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total de Comentários -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-info-light text-info">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <div class="report-stat-value"><?php echo number_format($resultEstatisticas['total_comentarios']); ?></div>
                            <div class="report-stat-title">Total de Comentários</div>
                            <div class="report-stat-subtitle">
                                <span class="text-success">Média: <?php echo number_format($resultEstatisticas['media_comentarios'], 1); ?> por post</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total de Compartilhamentos -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-success-light text-success">
                            <i class="fas fa-share-alt"></i>
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <div class="report-stat-value"><?php echo number_format($resultEstatisticas['total_compartilhamentos']); ?></div>
                            <div class="report-stat-title">Total de Compartilhamentos</div>
                            <div class="report-stat-subtitle">
                                <span class="text-success">Média: <?php echo number_format($resultEstatisticas['media_compartilhamentos'], 1); ?> por post</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Engajamento Total -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-warning-light text-warning">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <div class="report-stat-value"><?php echo number_format($engajamento_total); ?></div>
                            <div class="report-stat-title">Engajamento Total</div>
                            <div class="report-stat-subtitle">
                                <span class="text-success">Média: <?php echo number_format($media_engajamento, 1); ?> por post</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficos -->
    <div class="row mb-4">
        <!-- Gráfico de Métricas -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-chart-line me-2 text-primary"></i> Engajamento Diário
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="chart-container">
                        <canvas id="engajamentoChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de Reações -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-heart me-2 text-danger"></i> Tipos de Reações
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="chart-container">
                        <canvas id="reacoesChart"></canvas>
                    </div>
                    <div class="reaction-legend mt-3">
                        <div class="row justify-content-center">
                            <div class="col-auto">
                                <div class="reaction-item">
                                    <div class="reaction-icon like">👍</div>
                                    <div class="reaction-count"><?php echo number_format($resultReacoes['total_likes']); ?></div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="reaction-item">
                                    <div class="reaction-icon love">❤️</div>
                                    <div class="reaction-count"><?php echo number_format($resultReacoes['total_love']); ?></div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="reaction-item">
                                    <div class="reaction-icon care">🤗</div>
                                    <div class="reaction-count"><?php echo number_format($resultReacoes['total_care']); ?></div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="reaction-item">
                                    <div class="reaction-icon haha">😆</div>
                                    <div class="reaction-count"><?php echo number_format($resultReacoes['total_haha']); ?></div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="reaction-item">
                                    <div class="reaction-icon wow">😮</div>
                                    <div class="reaction-count"><?php echo number_format($resultReacoes['total_wow']); ?></div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="reaction-item">
                                    <div class="reaction-icon sad">😢</div>
                                    <div class="reaction-count"><?php echo number_format($resultReacoes['total_sad']); ?></div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="reaction-item">
                                    <div class="reaction-icon angry">😡</div>
                                    <div class="reaction-count"><?php echo number_format($resultReacoes['total_angry']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficos de Análise Temporal -->
    <div class="row mb-4">
        <!-- Melhor Hora do Dia -->
        <div class="col-xl-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-clock me-2 text-primary"></i> Engajamento por Hora do Dia
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="chart-container">
                        <canvas id="horasChart"></canvas>
                    </div>
                    <?php
                    // Encontrar a melhor hora (com mais engajamento)
                    $melhor_hora = 0;
                    $maior_engajamento = 0;
                    
                    foreach ($resultHoras as $key => $row) {
                        if ($row['media_engajamento'] > $maior_engajamento) {
                            $maior_engajamento = $row['media_engajamento'];
                            $melhor_hora = $row['hora'];
                        }
                    }
                    
                    // Reset do ponteiro
                    $resultHoras->data_seek(0);
                    ?>
                    <div class="best-time-info text-center mt-3">
                        <div class="best-time-label">Melhor horário para postar</div>
                        <div class="best-time-value">
                            <i class="far fa-clock me-2"></i> <?php echo sprintf('%02d:00', $melhor_hora); ?> - <?php echo sprintf('%02d:59', $melhor_hora); ?>
                        </div>
                        <div class="best-time-engagement">
                            Média de <?php echo number_format($maior_engajamento, 1); ?> engajamentos por post
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Melhor Dia da Semana -->
        <div class="col-xl-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-calendar-alt me-2 text-primary"></i> Engajamento por Dia da Semana
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="chart-container">
                        <canvas id="diasChart"></canvas>
                    </div>
                    <?php
                    // Encontrar o melhor dia (com mais engajamento)
                    $melhor_dia = 0;
                    $maior_engajamento_dia = 0;
                    
                    foreach ($dias_dados as $indice => $dia) {
                        if ($dia['media_engajamento'] > $maior_engajamento_dia) {
                            $maior_engajamento_dia = $dia['media_engajamento'];
                            $melhor_dia = $indice;
                        }
                    }
                    ?>
                    <div class="best-time-info text-center mt-3">
                        <div class="best-time-label">Melhor dia para postar</div>
                        <div class="best-time-value">
                            <i class="far fa-calendar-check me-2"></i> <?php echo $dias_semana[$melhor_dia]; ?>
                        </div>
                        <div class="best-time-engagement">
                            Média de <?php echo number_format($maior_engajamento_dia, 1); ?> engajamentos por post
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <!-- Desempenho por Grupo -->
        <div class="col-md-7 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-users me-2 text-primary"></i> Desempenho por Grupo
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if ($resultGrupos && $resultGrupos->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Grupo</th>
                                        <th class="text-center">Posts</th>
                                        <th class="text-center">Curtidas</th>
                                        <th class="text-center">Comentários</th>
                                        <th class="text-center">Compartilhamentos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($grupo = $resultGrupos->fetch_assoc()): ?>
                                        <?php
                                        $total_engajamento = $grupo['total_curtidas'] + $grupo['total_comentarios'] + $grupo['total_compartilhamentos'];
                                        $media_engajamento = $grupo['total_posts'] > 0 ? $total_engajamento / $grupo['total_posts'] : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="grupos.php?editar=<?php echo $grupo['id']; ?>">
                                                    <?php echo htmlspecialchars($grupo['nome']); ?>
                                                </a>
                                            </td>
                                            <td class="text-center"><?php echo number_format($grupo['total_posts']); ?></td>
                                            <td class="text-center">
                                                <div class="engagement-stat">
                                                    <span class="engagement-value"><?php echo number_format($grupo['total_curtidas']); ?></span>
                                                    <span class="engagement-avg">(<?php echo number_format($grupo['total_posts'] > 0 ? $grupo['total_curtidas'] / $grupo['total_posts'] : 0, 1); ?>/post)</span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="engagement-stat">
                                                    <span class="engagement-value"><?php echo number_format($grupo['total_comentarios']); ?></span>
                                                    <span class="engagement-avg">(<?php echo number_format($grupo['total_posts'] > 0 ? $grupo['total_comentarios'] / $grupo['total_posts'] : 0, 1); ?>/post)</span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="engagement-stat">
                                                    <span class="engagement-value"><?php echo number_format($grupo['total_compartilhamentos']); ?></span>
                                                    <span class="engagement-avg">(<?php echo number_format($grupo['total_posts'] > 0 ? $grupo['total_compartilhamentos'] / $grupo['total_posts'] : 0, 1); ?>/post)</span>
                                                </div>
                                            </td>
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
                            <p class="text-muted">Não há dados de engajamento por grupo para o período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Top Postagens -->
        <div class="col-md-5 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-trophy me-2 text-warning"></i> Top Postagens
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if ($resultMelhoresPostagens && $resultMelhoresPostagens->num_rows > 0): ?>
                        <div class="top-posts-container">
                            <?php while ($post = $resultMelhoresPostagens->fetch_assoc()): ?>
                                <div class="top-post-item">
                                    <div class="top-post-content">
                                        <div class="top-post-text">
                                            <?php echo htmlspecialchars(substr($post['texto'], 0, 150) . (strlen($post['texto']) > 150 ? '...' : '')); ?>
                                        </div>
                                        <div class="top-post-meta">
                                            <div class="top-post-group"><?php echo htmlspecialchars($post['grupo_nome']); ?></div>
                                            <div class="top-post-date"><?php echo date('d/m/Y H:i', strtotime($post['data_postagem'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="top-post-stats">
                                        <div class="stat-item">
                                            <div class="stat-icon"><i class="fas fa-thumbs-up"></i></div>
                                            <div class="stat-value"><?php echo number_format($post['curtidas']); ?></div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-icon"><i class="fas fa-comment"></i></div>
                                            <div class="stat-value"><?php echo number_format($post['comentarios']); ?></div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-icon"><i class="fas fa-share"></i></div>
                                            <div class="stat-value"><?php echo number_format($post['compartilhamentos']); ?></div>
                                        </div>
                                    </div>
                                    <?php if ($has_facebook_token && !empty($post['post_id'])): ?>
                                        <a href="https://facebook.com/<?php echo htmlspecialchars($post['post_id']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="fab fa-facebook-square me-1"></i> Ver no Facebook
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <h5>Sem dados de postagens</h5>
                            <p class="text-muted">Não há dados de postagens para o período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Atualizar Métricas -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-sync-alt me-2 text-primary"></i> Atualizar Métricas
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <p class="mb-0">
                                Atualize manualmente as métricas de engajamento das suas postagens. Isso irá buscar os dados mais recentes do Facebook para análise.
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($has_facebook_token): ?>
                                <a href="atualizar-metricas.php" class="btn btn-primary">
                                    <i class="fas fa-sync-alt me-1"></i> Atualizar Métricas
                                </a>
                            <?php else: ?>
                                <a href="conectar-facebook.php" class="btn btn-primary">
                                    <i class="fab fa-facebook me-1"></i> Conectar com Facebook
                                </a>
                            <?php endif; ?>
                        </div>
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
    align-items: center;
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
    margin-right: 20px;
    flex-shrink: 0;
}

.report-stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 5px;
    line-height: 1;
}

.report-stat-title {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 5px;
}

.report-stat-subtitle {
    font-size: 0.8rem;
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

/* Legenda de reações */
.reaction-legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
}

.reaction-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 0 10px;
}

.reaction-icon {
    font-size: 24px;
    margin-bottom: 5px;
}

.reaction-count {
    font-size: 0.8rem;
    font-weight: 600;
    color: #495057;
}

/* Melhor horário */
.best-time-info {
    margin-top: 20px;
}

.best-time-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 5px;
}

.best-time-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 5px;
    color: var(--bs-primary);
}

.best-time-engagement {
    font-size: 0.8rem;
    color: #28a745;
}

/* Estatísticas de engajamento na tabela */
.engagement-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.engagement-value {
    font-weight: 600;
}

.engagement-avg {
    font-size: 0.7rem;
    color: #6c757d;
}

/* Top posts */
.top-posts-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.top-post-item {
    padding: 15px;
    border: 1px solid #eee;
    border-radius: 8px;
    background-color: #f9f9f9;
}

.top-post-content {
    margin-bottom: 10px;
}

.top-post-text {
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.top-post-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #6c757d;
}

.top-post-stats {
    display: flex;
    gap: 15px;
    margin-top: 10px;
}

.stat-item {
    display: flex;
    align-items: center;
}

.stat-icon {
    margin-right: 5px;
    color: #007bff;
}

.stat-value {
    font-weight: 600;
}

/* Empty state */
.empty-state-icon {
    font-size: 3rem;
    color: #adb5bd;
}
</style>

<!-- JavaScript para Gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Engajamento
    const ctxEngajamento = document.getElementById('engajamentoChart').getContext('2d');
    new Chart(ctxEngajamento, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [
                {
                    label: 'Curtidas',
                    data: <?php echo json_encode($datasets_curtidas); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Comentários',
                    data: <?php echo json_encode($datasets_comentarios); ?>,
                    backgroundColor: 'rgba(46, 204, 113, 0.2)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Compartilhamentos',
                    data: <?php echo json_encode($datasets_compartilhamentos); ?>,
                    backgroundColor: 'rgba(243, 156, 18, 0.2)',
                    borderColor: 'rgba(243, 156, 18, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Engajamento Diário'
                },
                legend: {
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            hover: {
                mode: 'index',
                intersect: false
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Gráfico de Reações
    const ctxReacoes = document.getElementById('reacoesChart').getContext('2d');
    new Chart(ctxReacoes, {
        type: 'doughnut',
        data: {
            labels: ['Like', 'Love', 'Care', 'Haha', 'Wow', 'Sad', 'Angry'],
            datasets: [{
                data: [
                    <?php echo $resultReacoes['total_likes']; ?>,
                    <?php echo $resultReacoes['total_love']; ?>,
                    <?php echo $resultReacoes['total_care']; ?>,
                    <?php echo $resultReacoes['total_haha']; ?>,
                    <?php echo $resultReacoes['total_wow']; ?>,
                    <?php echo $resultReacoes['total_sad']; ?>,
                    <?php echo $resultReacoes['total_angry']; ?>
                ],
                backgroundColor: [
                    'rgba(59, 89, 152, 0.7)',
                    'rgba(231, 76, 60, 0.7)',
                    'rgba(142, 68, 173, 0.7)',
                    'rgba(243, 156, 18, 0.7)',
                    'rgba(26, 188, 156, 0.7)',
                    'rgba(41, 128, 185, 0.7)',
                    'rgba(192, 57, 43, 0.7)'
                ],
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '65%'
        }
    });
    
    // Gráfico de Horas
    const ctxHoras = document.getElementById('horasChart').getContext('2d');
    new Chart(ctxHoras, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels_horas); ?>,
            datasets: [
                {
                    label: 'Engajamento Médio',
                    data: <?php echo json_encode($datasets_engajamento_horas); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Número de Posts',
                    data: <?php echo json_encode($datasets_posts_horas); ?>,
                    backgroundColor: 'rgba(243, 156, 18, 0.3)',
                    borderColor: 'rgba(243, 156, 18, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Engajamento por Hora do Dia'
                },
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Engajamento Médio'
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false
                    },
                    title: {
                        display: true,
                        text: 'Número de Posts'
                    }
                }
            }
        }
    });
    
    // Gráfico de Dias da Semana
    const ctxDias = document.getElementById('diasChart').getContext('2d');
    new Chart(ctxDias, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels_dias); ?>,
            datasets: [
                {
                    label: 'Engajamento Médio',
                    data: <?php echo json_encode($datasets_engajamento_dias); ?>,
                    backgroundColor: 'rgba(46, 204, 113, 0.7)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Número de Posts',
                    data: <?php echo json_encode($datasets_posts_dias); ?>,
                    backgroundColor: 'rgba(243, 156, 18, 0.3)',
                    borderColor: 'rgba(243, 156, 18, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Engajamento por Dia da Semana'
                },
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Engajamento Médio'
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false
                    },
                    title: {
                        display: true,
                        text: 'Número de Posts'
                    }
                }
            }
        }
    });
});
</script>

<?php
// Incluir o rodapé
include 'includes/footer.php';
?>