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

// Definir período de análise
$periodos = [
    '7' => 'Últimos 7 dias',
    '14' => 'Últimos 14 dias',
    '30' => 'Últimos 30 dias',
    '60' => 'Últimos 60 dias',
    '90' => 'Últimos 90 dias'
];

$periodo = isset($_GET['periodo']) && array_key_exists($_GET['periodo'], $periodos) ? $_GET['periodo'] : '30';
$dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));
$dataFim = date('Y-m-d');

// Filtro por campanha
$campanhaId = isset($_GET['campanha']) && is_numeric($_GET['campanha']) ? intval($_GET['campanha']) : null;

// Filtro por grupo
$grupoId = isset($_GET['grupo']) && is_numeric($_GET['grupo']) ? intval($_GET['grupo']) : null;

// Filtrar por período personalizado
if (isset($_GET['data_inicio']) && isset($_GET['data_fim'])) {
    $dataInicio = $_GET['data_inicio'];
    $dataFim = $_GET['data_fim'];
    
    // Validar formato de datas
    if (DateTime::createFromFormat('Y-m-d', $dataInicio) && DateTime::createFromFormat('Y-m-d', $dataFim)) {
        $periodo = 'custom';
        $periodos['custom'] = 'Período personalizado';
    }
}

// Obter campanhas do usuário para o filtro
$queryCampanhas = "SELECT id, nome FROM campanhas WHERE usuario_id = ? ORDER BY nome";
$stmtCampanhas = $db->prepare($queryCampanhas);
$stmtCampanhas->bind_param("i", $userId);
$stmtCampanhas->execute();
$resultCampanhas = $stmtCampanhas->get_result();

// Obter grupos do usuário para o filtro
$queryGrupos = "SELECT id, nome FROM grupos_facebook WHERE usuario_id = ? ORDER BY nome";
$stmtGrupos = $db->prepare($queryGrupos);
$stmtGrupos->bind_param("i", $userId);
$stmtGrupos->execute();
$resultGrupos = $stmtGrupos->get_result();

// Construir condições SQL com base nos filtros
$sqlCond = "p.usuario_id = ? AND DATE(p.data_postagem) BETWEEN ? AND ?";
$sqlParams = [$userId, $dataInicio, $dataFim];
$sqlTypes = "iss";

if ($campanhaId) {
    $sqlCond .= " AND p.campanha_id = ?";
    $sqlParams[] = $campanhaId;
    $sqlTypes .= "i";
}

if ($grupoId) {
    $sqlCond .= " AND p.grupo_id = ?";
    $sqlParams[] = $grupoId;
    $sqlTypes .= "i";
}

// Análise 1: Desempenho por dia da semana
$queryDiaSemana = "
    SELECT 
        WEEKDAY(p.data_postagem) as dia_semana,
        COUNT(*) as total_posts,
        ROUND(AVG(f.curtidas)) as media_curtidas,
        ROUND(AVG(f.comentarios)) as media_comentarios,
        ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos
    FROM 
        logs_postagem p
        LEFT JOIN facebook_posts f ON p.post_id = f.post_id
    WHERE 
        {$sqlCond}
        AND p.status = 'sucesso'
    GROUP BY 
        WEEKDAY(p.data_postagem)
    ORDER BY 
        media_curtidas DESC, dia_semana
";

$stmtDiaSemana = $db->prepare($queryDiaSemana);
$stmtDiaSemana->bind_param($sqlTypes, ...$sqlParams);
$stmtDiaSemana->execute();
$resultDiaSemana = $stmtDiaSemana->get_result();

$diasSemana = [
    'Segunda-feira', 'Terça-feira', 'Quarta-feira', 
    'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'
];

$dadosDiaSemana = [];
while ($row = $resultDiaSemana->fetch_assoc()) {
    $row['nome_dia'] = $diasSemana[$row['dia_semana']];
    $row['total_engajamento'] = $row['media_curtidas'] + $row['media_comentarios'] + $row['media_compartilhamentos'];
    $dadosDiaSemana[] = $row;
}

// Ordenar por engajamento total
usort($dadosDiaSemana, function($a, $b) {
    return $b['total_engajamento'] - $a['total_engajamento'];
});

// Análise 2: Desempenho por horário do dia
$queryHorario = "
    SELECT 
        HOUR(p.data_postagem) as hora,
        COUNT(*) as total_posts,
        ROUND(AVG(f.curtidas)) as media_curtidas,
        ROUND(AVG(f.comentarios)) as media_comentarios,
        ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos
    FROM 
        logs_postagem p
        LEFT JOIN facebook_posts f ON p.post_id = f.post_id
    WHERE 
        {$sqlCond}
        AND p.status = 'sucesso'
    GROUP BY 
        HOUR(p.data_postagem)
    ORDER BY 
        media_curtidas DESC, hora
";

$stmtHorario = $db->prepare($queryHorario);
$stmtHorario->bind_param($sqlTypes, ...$sqlParams);
$stmtHorario->execute();
$resultHorario = $stmtHorario->get_result();

$dadosHorario = [];
while ($row = $resultHorario->fetch_assoc()) {
    $row['hora_formatada'] = sprintf('%02d:00', $row['hora']);
    $row['total_engajamento'] = $row['media_curtidas'] + $row['media_comentarios'] + $row['media_compartilhamentos'];
    $dadosHorario[] = $row;
}

// Ordenar por engajamento total
usort($dadosHorario, function($a, $b) {
    return $b['total_engajamento'] - $a['total_engajamento'];
});

// Análise 3: Desempenho por tipo de conteúdo
$queryTipoConteudo = "
    SELECT 
        CASE
            WHEN a.imagem_url != '' AND a.link != '' THEN 'Imagem+Link'
            WHEN a.imagem_url != '' THEN 'Apenas Imagem'
            WHEN a.link != '' THEN 'Apenas Link'
            ELSE 'Apenas Texto'
        END as tipo_conteudo,
        COUNT(*) as total_posts,
        ROUND(AVG(f.curtidas)) as media_curtidas,
        ROUND(AVG(f.comentarios)) as media_comentarios,
        ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos
    FROM 
        logs_postagem p
        LEFT JOIN facebook_posts f ON p.post_id = f.post_id
        LEFT JOIN anuncios a ON p.anuncio_id = a.id
    WHERE 
        {$sqlCond}
        AND p.status = 'sucesso'
    GROUP BY 
        tipo_conteudo
    ORDER BY 
        media_curtidas DESC
";

$stmtTipoConteudo = $db->prepare($queryTipoConteudo);
$stmtTipoConteudo->bind_param($sqlTypes, ...$sqlParams);
$stmtTipoConteudo->execute();
$resultTipoConteudo = $stmtTipoConteudo->get_result();

$dadosTipoConteudo = [];
while ($row = $resultTipoConteudo->fetch_assoc()) {
    $row['total_engajamento'] = $row['media_curtidas'] + $row['media_comentarios'] + $row['media_compartilhamentos'];
    $dadosTipoConteudo[] = $row;
}

// Análise 4: Desempenho por tamanho da descrição
$queryTamanhoDescricao = "
    SELECT 
        CASE
            WHEN LENGTH(a.descricao) < 100 THEN 'Curto (< 100 caracteres)'
            WHEN LENGTH(a.descricao) < 300 THEN 'Médio (100-300 caracteres)'
            ELSE 'Longo (> 300 caracteres)'
        END as tamanho_descricao,
        COUNT(*) as total_posts,
        ROUND(AVG(f.curtidas)) as media_curtidas,
        ROUND(AVG(f.comentarios)) as media_comentarios,
        ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos
    FROM 
        logs_postagem p
        LEFT JOIN facebook_posts f ON p.post_id = f.post_id
        LEFT JOIN anuncios a ON p.anuncio_id = a.id
    WHERE 
        {$sqlCond}
        AND p.status = 'sucesso'
    GROUP BY 
        tamanho_descricao
    ORDER BY 
        media_curtidas DESC
";

$stmtTamanhoDescricao = $db->prepare($queryTamanhoDescricao);
$stmtTamanhoDescricao->bind_param($sqlTypes, ...$sqlParams);
$stmtTamanhoDescricao->execute();
$resultTamanhoDescricao = $stmtTamanhoDescricao->get_result();

$dadosTamanhoDescricao = [];
while ($row = $resultTamanhoDescricao->fetch_assoc()) {
    $row['total_engajamento'] = $row['media_curtidas'] + $row['media_comentarios'] + $row['media_compartilhamentos'];
    $dadosTamanhoDescricao[] = $row;
}

// Análise 5: Melhores grupos por engajamento
$queryMelhoresGrupos = "
    SELECT 
        g.id,
        g.nome,
        COUNT(*) as total_posts,
        ROUND(AVG(f.curtidas)) as media_curtidas,
        ROUND(AVG(f.comentarios)) as media_comentarios,
        ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos
    FROM 
        logs_postagem p
        LEFT JOIN facebook_posts f ON p.post_id = f.post_id
        LEFT JOIN grupos_facebook g ON p.grupo_id = g.id
    WHERE 
        {$sqlCond}
        AND p.status = 'sucesso'
    GROUP BY 
        g.id, g.nome
    ORDER BY 
        (AVG(f.curtidas) + AVG(f.comentarios) + AVG(f.compartilhamentos)) DESC
    LIMIT 10
";

$stmtMelhoresGrupos = $db->prepare($queryMelhoresGrupos);
$stmtMelhoresGrupos->bind_param($sqlTypes, ...$sqlParams);
$stmtMelhoresGrupos->execute();
$resultMelhoresGrupos = $stmtMelhoresGrupos->get_result();

$dadosMelhoresGrupos = [];
while ($row = $resultMelhoresGrupos->fetch_assoc()) {
    $row['total_engajamento'] = $row['media_curtidas'] + $row['media_comentarios'] + $row['media_compartilhamentos'];
    $dadosMelhoresGrupos[] = $row;
}

// Análise 6: Palavras-chave mais eficazes
$queryPalavrasChave = "
    SELECT 
        a.palavras_chave,
        COUNT(*) as total_posts,
        ROUND(AVG(f.curtidas)) as media_curtidas,
        ROUND(AVG(f.comentarios)) as media_comentarios,
        ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos
    FROM 
        logs_postagem p
        LEFT JOIN facebook_posts f ON p.post_id = f.post_id
        LEFT JOIN anuncios a ON p.anuncio_id = a.id
    WHERE 
        {$sqlCond}
        AND p.status = 'sucesso'
        AND a.palavras_chave IS NOT NULL
        AND a.palavras_chave != ''
    GROUP BY 
        a.palavras_chave
    ORDER BY 
        (AVG(f.curtidas) + AVG(f.comentarios) + AVG(f.compartilhamentos)) DESC
    LIMIT 10
";

$stmtPalavrasChave = $db->prepare($queryPalavrasChave);
$stmtPalavrasChave->bind_param($sqlTypes, ...$sqlParams);
$stmtPalavrasChave->execute();
$resultPalavrasChave = $stmtPalavrasChave->get_result();

$dadosPalavrasChave = [];
while ($row = $resultPalavrasChave->fetch_assoc()) {
    $row['total_engajamento'] = $row['media_curtidas'] + $row['media_comentarios'] + $row['media_compartilhamentos'];
    $dadosPalavrasChave[] = $row;
}

// Análise 7: Melhores combinações de dia e hora
$queryMelhoresCombinacoes = "
    SELECT 
        WEEKDAY(p.data_postagem) as dia_semana,
        HOUR(p.data_postagem) as hora,
        COUNT(*) as total_posts,
        ROUND(AVG(f.curtidas)) as media_curtidas,
        ROUND(AVG(f.comentarios)) as media_comentarios,
        ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos
    FROM 
        logs_postagem p
        LEFT JOIN facebook_posts f ON p.post_id = f.post_id
    WHERE 
        {$sqlCond}
        AND p.status = 'sucesso'
    GROUP BY 
        WEEKDAY(p.data_postagem), HOUR(p.data_postagem)
    HAVING
        COUNT(*) >= 3
    ORDER BY 
        (AVG(f.curtidas) + AVG(f.comentarios) + AVG(f.compartilhamentos)) DESC
    LIMIT 5
";

$stmtMelhoresCombinacoes = $db->prepare($queryMelhoresCombinacoes);
$stmtMelhoresCombinacoes->bind_param($sqlTypes, ...$sqlParams);
$stmtMelhoresCombinacoes->execute();
$resultMelhoresCombinacoes = $stmtMelhoresCombinacoes->get_result();

$dadosMelhoresCombinacoes = [];
while ($row = $resultMelhoresCombinacoes->fetch_assoc()) {
    $row['nome_dia'] = $diasSemana[$row['dia_semana']];
    $row['hora_formatada'] = sprintf('%02d:00', $row['hora']);
    $row['total_engajamento'] = $row['media_curtidas'] + $row['media_comentarios'] + $row['media_compartilhamentos'];
    $dadosMelhoresCombinacoes[] = $row;
}

// Obter total de posts analisados
$queryTotalPosts = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN p.status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
        SUM(CASE WHEN p.status = 'falha' THEN 1 ELSE 0 END) as falha
    FROM 
        logs_postagem p
    WHERE 
        {$sqlCond}
";

$stmtTotalPosts = $db->prepare($queryTotalPosts);
$stmtTotalPosts->bind_param($sqlTypes, ...$sqlParams);
$stmtTotalPosts->execute();
$totalPosts = $stmtTotalPosts->get_result()->fetch_assoc();

// Incluir o cabeçalho
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0 text-gray-800">Análise Avançada de Desempenho</h1>
            <p class="mb-0 text-muted">Análise detalhada do desempenho das postagens em grupos do Facebook</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filtrosModal">
                    <i class="fas fa-filter me-1"></i> Filtros
                </button>
                <button class="btn btn-outline-success" id="exportarRelatorio">
                    <i class="fas fa-file-excel me-1"></i> Exportar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Filtros Ativos -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-body">
                    <div class="d-flex flex-wrap align-items-center">
                        <div class="me-3 mb-2">
                            <span class="text-muted">Período:</span>
                            <span class="ms-2 badge bg-primary"><?php echo $periodos[$periodo]; ?></span>
                        </div>
                        
                        <?php if ($campanhaId): ?>
                            <?php 
                            $queryCampanhaNome = "SELECT nome FROM campanhas WHERE id = ? LIMIT 1";
                            $stmtCampanhaNome = $db->prepare($queryCampanhaNome);
                            $stmtCampanhaNome->bind_param("i", $campanhaId);
                            $stmtCampanhaNome->execute();
                            $campanhaNome = $stmtCampanhaNome->get_result()->fetch_assoc()['nome'];
                            ?>
                            <div class="me-3 mb-2">
                                <span class="text-muted">Campanha:</span>
                                <span class="ms-2 badge bg-info"><?php echo htmlspecialchars($campanhaNome); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($grupoId): ?>
                            <?php 
                            $queryGrupoNome = "SELECT nome FROM grupos_facebook WHERE id = ? LIMIT 1";
                            $stmtGrupoNome = $db->prepare($queryGrupoNome);
                            $stmtGrupoNome->bind_param("i", $grupoId);
                            $stmtGrupoNome->execute();
                            $grupoNome = $stmtGrupoNome->get_result()->fetch_assoc()['nome'];
                            ?>
                            <div class="me-3 mb-2">
                                <span class="text-muted">Grupo:</span>
                                <span class="ms-2 badge bg-warning"><?php echo htmlspecialchars($grupoNome); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($periodo === 'custom'): ?>
                            <div class="me-3 mb-2">
                                <span class="text-muted">Data:</span>
                                <span class="ms-2 badge bg-secondary">
                                    <?php echo date('d/m/Y', strtotime($dataInicio)); ?> - 
                                    <?php echo date('d/m/Y', strtotime($dataFim)); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="me-3 mb-2">
                            <span class="text-muted">Total analisado:</span>
                            <span class="ms-2 badge bg-success"><?php echo number_format($totalPosts['total']); ?> postagens</span>
                        </div>
                        
                        <div class="ms-auto mb-2">
                            <a href="analise-desempenho.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Limpar Filtros
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alertas sobre dados insuficientes -->
    <?php if ($totalPosts['sucesso'] < 10): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Dados insuficientes:</strong> Para obter análises mais precisas, recomendamos ter pelo menos 10 postagens bem-sucedidas. Atualmente você tem <?php echo $totalPosts['sucesso']; ?> postagens bem-sucedidas no período.
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Resumo dos Melhores Horários -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-star me-2 text-warning"></i> Resumo de Melhores Práticas
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="best-practice-card">
                                <div class="best-practice-icon">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="best-practice-content">
                                    <h6>Melhor Dia para Postar</h6>
                                    <?php if (count($dadosDiaSemana) > 0): ?>
                                        <p class="best-practice-value"><?php echo $dadosDiaSemana[0]['nome_dia']; ?></p>
                                        <small class="text-muted">
                                            Engajamento médio: <?php echo number_format($dadosDiaSemana[0]['total_engajamento']); ?>
                                            <span class="ms-1 text-success">
                                                <i class="fas fa-heart"></i> <?php echo number_format($dadosDiaSemana[0]['media_curtidas']); ?>
                                            </span>
                                        </small>
                                    <?php else: ?>
                                        <p class="best-practice-value text-muted">Dados insuficientes</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="best-practice-card">
                                <div class="best-practice-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="best-practice-content">
                                    <h6>Melhor Horário para Postar</h6>
                                    <?php if (count($dadosHorario) > 0): ?>
                                        <p class="best-practice-value"><?php echo $dadosHorario[0]['hora_formatada']; ?></p>
                                        <small class="text-muted">
                                            Engajamento médio: <?php echo number_format($dadosHorario[0]['total_engajamento']); ?>
                                            <span class="ms-1 text-success">
                                                <i class="fas fa-heart"></i> <?php echo number_format($dadosHorario[0]['media_curtidas']); ?>
                                            </span>
                                        </small>
                                    <?php else: ?>
                                        <p class="best-practice-value text-muted">Dados insuficientes</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="best-practice-card">
                                <div class="best-practice-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="best-practice-content">
                                    <h6>Tipo de Conteúdo Ideal</h6>
                                    <?php if (count($dadosTipoConteudo) > 0): ?>
                                        <p class="best-practice-value"><?php echo $dadosTipoConteudo[0]['tipo_conteudo']; ?></p>
                                        <small class="text-muted">
                                            Engajamento médio: <?php echo number_format($dadosTipoConteudo[0]['total_engajamento']); ?>
                                            <span class="ms-1 text-success">
                                                <i class="fas fa-heart"></i> <?php echo number_format($dadosTipoConteudo[0]['media_curtidas']); ?>
                                            </span>
                                        </small>
                                    <?php else: ?>
                                        <p class="best-practice-value text-muted">Dados insuficientes</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="text-center mb-3">Melhores Combinações de Dia e Hora</h6>
                            
                            <?php if (count($dadosMelhoresCombinacoes) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Dia</th>
                                                <th>Hora</th>
                                                <th>Posts</th>
                                                <th>Curtidas</th>
                                                <th>Comentários</th>
                                                <th>Compartilhamentos</th>
                                                <th>Engajamento Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dadosMelhoresCombinacoes as $idx => $combinacao): ?>
                                                <tr <?php echo $idx === 0 ? 'class="table-success"' : ''; ?>>
                                                    <td><?php echo $combinacao['nome_dia']; ?></td>
                                                    <td><?php echo $combinacao['hora_formatada']; ?></td>
                                                    <td><?php echo number_format($combinacao['total_posts']); ?></td>
                                                    <td><?php echo number_format($combinacao['media_curtidas']); ?></td>
                                                    <td><?php echo number_format($combinacao['media_comentarios']); ?></td>
                                                    <td><?php echo number_format($combinacao['media_compartilhamentos']); ?></td>
                                                    <td><strong><?php echo number_format($combinacao['total_engajamento']); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted">
                                    Dados insuficientes para análise de melhores combinações
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Desempenho por Dia da Semana -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-calendar-week me-2 text-primary"></i> Desempenho por Dia da Semana
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if (count($dadosDiaSemana) > 0): ?>
                        <div class="chart-container">
                            <canvas id="chartDiaSemana"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Dia</th>
                                        <th>Posts</th>
                                        <th>Curtidas</th>
                                        <th>Comentários</th>
                                        <th>Compartilhamentos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Reorganizar por dia da semana
                                    $tempDados = [];
                                    foreach ($dadosDiaSemana as $dia) {
                                        $tempDados[$dia['dia_semana']] = $dia;
                                    }
                                    
                                    for ($i = 0; $i < 7; $i++) {
                                        if (isset($tempDados[$i])):
                                    ?>
                                        <tr>
                                            <td><?php echo $tempDados[$i]['nome_dia']; ?></td>
                                            <td><?php echo number_format($tempDados[$i]['total_posts']); ?></td>
                                            <td><?php echo number_format($tempDados[$i]['media_curtidas']); ?></td>
                                            <td><?php echo number_format($tempDados[$i]['media_comentarios']); ?></td>
                                            <td><?php echo number_format($tempDados[$i]['media_compartilhamentos']); ?></td>
                                        </tr>
                                    <?php 
                                        endif;
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h5>Dados insuficientes</h5>
                            <p class="text-muted">Não há dados suficientes para esta análise no período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Desempenho por Horário -->
        <div class="col-md-6">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-clock me-2 text-info"></i> Desempenho por Horário
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if (count($dadosHorario) > 0): ?>
                        <div class="chart-container">
                            <canvas id="chartHorario"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Horário</th>
                                        <th>Posts</th>
                                        <th>Curtidas</th>
                                        <th>Comentários</th>
                                        <th>Engajamento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dadosHorario as $idx => $hora): ?>
                                        <?php if ($idx < 5): ?>
                                        <tr>
                                            <td><?php echo $hora['hora_formatada']; ?></td>
                                            <td><?php echo number_format($hora['total_posts']); ?></td>
                                            <td><?php echo number_format($hora['media_curtidas']); ?></td>
                                            <td><?php echo number_format($hora['media_comentarios']); ?></td>
                                            <td><?php echo number_format($hora['total_engajamento']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h5>Dados insuficientes</h5>
                            <p class="text-muted">Não há dados suficientes para esta análise no período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Desempenho por Tipo de Conteúdo e Tamanho da Descrição -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-file-alt me-2 text-success"></i> Desempenho por Tipo de Conteúdo
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if (count($dadosTipoConteudo) > 0): ?>
                        <div class="chart-container">
                            <canvas id="chartTipoConteudo"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tipo de Conteúdo</th>
                                        <th>Posts</th>
                                        <th>Curtidas</th>
                                        <th>Comentários</th>
                                        <th>Compartilhamentos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dadosTipoConteudo as $tipo): ?>
                                        <tr>
                                            <td><?php echo $tipo['tipo_conteudo']; ?></td>
                                            <td><?php echo number_format($tipo['total_posts']); ?></td>
                                            <td><?php echo number_format($tipo['media_curtidas']); ?></td>
                                            <td><?php echo number_format($tipo['media_comentarios']); ?></td>
                                            <td><?php echo number_format($tipo['media_compartilhamentos']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h5>Dados insuficientes</h5>
                            <p class="text-muted">Não há dados suficientes para esta análise no período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-text-height me-2 text-warning"></i> Desempenho por Tamanho da Descrição
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if (count($dadosTamanhoDescricao) > 0): ?>
                        <div class="chart-container">
                            <canvas id="chartTamanhoDescricao"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tamanho</th>
                                        <th>Posts</th>
                                        <th>Curtidas</th>
                                        <th>Comentários</th>
                                        <th>Compartilhamentos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dadosTamanhoDescricao as $tamanho): ?>
                                        <tr>
                                            <td><?php echo $tamanho['tamanho_descricao']; ?></td>
                                            <td><?php echo number_format($tamanho['total_posts']); ?></td>
                                            <td><?php echo number_format($tamanho['media_curtidas']); ?></td>
                                            <td><?php echo number_format($tamanho['media_comentarios']); ?></td>
                                            <td><?php echo number_format($tamanho['media_compartilhamentos']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h5>Dados insuficientes</h5>
                            <p class="text-muted">Não há dados suficientes para esta análise no período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Melhores Grupos e Palavras-chave -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-users me-2 text-danger"></i> Grupos com Melhor Desempenho
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if (count($dadosMelhoresGrupos) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Grupo</th>
                                        <th>Posts</th>
                                        <th>Curtidas</th>
                                        <th>Comentários</th>
                                        <th>Compartilhamentos</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dadosMelhoresGrupos as $idx => $grupo): ?>
                                        <tr <?php echo $idx === 0 ? 'class="table-success"' : ''; ?>>
                                            <td>
                                                <a href="grupos.php?id=<?php echo $grupo['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($grupo['nome']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo number_format($grupo['total_posts']); ?></td>
                                            <td><?php echo number_format($grupo['media_curtidas']); ?></td>
                                            <td><?php echo number_format($grupo['media_comentarios']); ?></td>
                                            <td><?php echo number_format($grupo['media_compartilhamentos']); ?></td>
                                            <td><strong><?php echo number_format($grupo['total_engajamento']); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Dica:</strong> Concentre suas postagens nos grupos com melhor desempenho para maximizar seu alcance.
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h5>Dados insuficientes</h5>
                            <p class="text-muted">Não há dados suficientes para esta análise no período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-key me-2 text-purple"></i> Palavras-chave com Melhor Desempenho
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if (count($dadosPalavrasChave) > 0): ?>
                        <div class="chart-container">
                            <canvas id="chartPalavrasChave"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Palavras-chave</th>
                                        <th>Posts</th>
                                        <th>Curtidas</th>
                                        <th>Comentários</th>
                                        <th>Compartilhamentos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dadosPalavrasChave as $idx => $palavra): ?>
                                        <?php if ($idx < 5): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($palavra['palavras_chave']); ?></td>
                                            <td><?php echo number_format($palavra['total_posts']); ?></td>
                                            <td><?php echo number_format($palavra['media_curtidas']); ?></td>
                                            <td><?php echo number_format($palavra['media_comentarios']); ?></td>
                                            <td><?php echo number_format($palavra['media_compartilhamentos']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h5>Dados insuficientes</h5>
                            <p class="text-muted">Não há dados suficientes para esta análise no período selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recomendações -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-lightbulb me-2 text-warning"></i> Recomendações Personalizadas
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="row">
                        <?php 
                        $recomendacoes = [];
                        
                        // Recomendações de dias e horários
                        if (count($dadosMelhoresCombinacoes) > 0) {
                            $melhorCombinacao = $dadosMelhoresCombinacoes[0];
                            $recomendacoes[] = [
                                'titulo' => 'Otimize seu Horário de Postagem',
                                'descricao' => "Com base na análise, considere programar suas postagens para {$melhorCombinacao['nome_dia']} às {$melhorCombinacao['hora_formatada']} para maximizar o engajamento. Posts nesse horário tiveram uma média de {$melhorCombinacao['total_engajamento']} interações.",
                                'icone' => 'fas fa-clock'
                            ];
                        }
                        
                        // Recomendações de tipo de conteúdo
                        if (count($dadosTipoConteudo) > 0) {
                            $tipoRecomendado = $dadosTipoConteudo[0];
                            $recomendacoes[] = [
                                'titulo' => 'Utilize o Tipo de Conteúdo Ideal',
                                'descricao' => "Posts com \"{$tipoRecomendado['tipo_conteudo']}\" tiveram o melhor desempenho, com uma média de {$tipoRecomendado['total_engajamento']} interações. Priorize este formato em suas próximas campanhas.",
                                'icone' => 'fas fa-file-alt'
                            ];
                        }
                        
                        // Recomendação de tamanho de descrição
                        if (count($dadosTamanhoDescricao) > 0) {
                            $tamanhoRecomendado = $dadosTamanhoDescricao[0];
                            $recomendacoes[] = [
                                'titulo' => 'Ajuste o Tamanho da Descrição',
                                'descricao' => "Postagens com descrições {$tamanhoRecomendado['tamanho_descricao']} obtiveram melhor engajamento. Considere ajustar o comprimento de suas descrições para este padrão.",
                                'icone' => 'fas fa-text-height'
                            ];
                        }
                        
                        // Grupos recomendados
                        if (count($dadosMelhoresGrupos) > 0) {
                            $gruposRecomendados = array_slice($dadosMelhoresGrupos, 0, 3);
                            $nomesGrupos = array_map(function($g) {
                                return $g['nome'];
                            }, $gruposRecomendados);
                            
                            $recomendacoes[] = [
                                'titulo' => 'Foque nos Grupos de Melhor Desempenho',
                                'descricao' => "Os grupos " . implode(', ', $nomesGrupos) . " mostraram melhor engajamento. Priorize esses grupos em suas campanhas ou use conteúdo semelhante em outros grupos.",
                                'icone' => 'fas fa-users'
                            ];
                        }
                        
                        // Recomendação genérica caso não haja dados suficientes
                        if (count($recomendacoes) == 0) {
                            $recomendacoes[] = [
                                'titulo' => 'Colete Mais Dados para Análise',
                                'descricao' => "Para obter recomendações personalizadas, realize mais postagens através do sistema. Recomendamos pelo menos 10 postagens bem-sucedidas para análises precisas.",
                                'icone' => 'fas fa-database'
                            ];
                        }
                        
                        // Limitar a 4 recomendações
                        $recomendacoes = array_slice($recomendacoes, 0, 4);
                        
                        foreach ($recomendacoes as $rec):
                        ?>
                            <div class="col-md-6">
                                <div class="recommendation-card mb-3">
                                    <div class="recommendation-icon">
                                        <i class="<?php echo $rec['icone']; ?>"></i>
                                    </div>
                                    <div class="recommendation-content">
                                        <h6 class="recommendation-title"><?php echo $rec['titulo']; ?></h6>
                                        <p class="recommendation-text"><?php echo $rec['descricao']; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Filtros -->
<div class="modal fade" id="filtrosModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filtros de Análise</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form action="analise-desempenho.php" method="GET" id="filtroForm">
                    <div class="mb-3">
                        <label for="periodo" class="form-label">Período</label>
                        <select class="form-select" id="periodo" name="periodo">
                            <?php foreach ($periodos as $key => $value): ?>
                                <?php if ($key != 'custom'): ?>
                                <option value="<?php echo $key; ?>" <?php echo $periodo == $key ? 'selected' : ''; ?>><?php echo $value; ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <option value="custom" <?php echo $periodo == 'custom' ? 'selected' : ''; ?>>Período personalizado</option>
                        </select>
                    </div>
                    
                    <div id="periodoCustom" class="row <?php echo $periodo == 'custom' ? '' : 'd-none'; ?>">
                        <div class="col-md-6 mb-3">
                            <label for="data_inicio" class="form-label">Data Inicial</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $dataInicio; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="data_fim" class="form-label">Data Final</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $dataFim; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="campanha" class="form-label">Campanha</label>
                        <select class="form-select" id="campanha" name="campanha">
                            <option value="">Todas as campanhas</option>
                            <?php while ($campanha = $resultCampanhas->fetch_assoc()): ?>
                                <option value="<?php echo $campanha['id']; ?>" <?php echo $campanhaId == $campanha['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($campanha['nome']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="grupo" class="form-label">Grupo</label>
                        <select class="form-select" id="grupo" name="grupo">
                            <option value="">Todos os grupos</option>
                            <?php while ($grupo = $resultGrupos->fetch_assoc()): ?>
                                <option value="<?php echo $grupo['id']; ?>" <?php echo $grupoId == $grupo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grupo['nome']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="aplicarFiltros">Aplicar Filtros</button>
            </div>
        </div>
    </div>
</div>

<!-- CSS Personalizado -->
<style>
/* Cards de estatísticas */
.best-practice-card {
    display: flex;
    align-items: center;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 15px;
    border-left: 4px solid var(--primary-color);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.best-practice-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.best-practice-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--primary-color);
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    margin-right: 20px;
}

.best-practice-content {
    flex: 1;
}

.best-practice-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 5px;
}

/* Tamanho dos gráficos */
.chart-container {
    position: relative;
    height: 250px;
    width: 100%;
}

/* Empty state */
.empty-state-icon {
    font-size: 3rem;
    color: #dee2e6;
}

/* Recomendações */
.recommendation-card {
    display: flex;
    padding: 20px;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    height: 100%;
    border-left: 3px solid var(--primary-color);
}

.recommendation-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(0,123,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--primary-color);
    margin-right: 15px;
    flex-shrink: 0;
}

.recommendation-content {
    flex: 1;
}

.recommendation-title {
    margin-bottom: 8px;
    font-weight: 600;
}

.recommendation-text {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 0;
}

/* Cor púrpura para ícones */
.text-purple {
    color: #6f42c1;
}
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar modais Bootstrap
    const modais = document.querySelectorAll('.modal');
    modais.forEach(modal => {
        new bootstrap.Modal(modal);
    });
    
    // Mostrar/ocultar campos de data personalizada
    const selectPeriodo = document.getElementById('periodo');
    const divPeriodoCustom = document.getElementById('periodoCustom');
    
    selectPeriodo.addEventListener('change', function() {
        if (this.value === 'custom') {
            divPeriodoCustom.classList.remove('d-none');
        } else {
            divPeriodoCustom.classList.add('d-none');
        }
    });
    
    // Botão de aplicar filtros
    document.getElementById('aplicarFiltros').addEventListener('click', function() {
        document.getElementById('filtroForm').submit();
    });
    
    // Botão de exportar relatório
    document.getElementById('exportarRelatorio').addEventListener('click', function() {
        // Implementar exportação para Excel
        alert('Exportação para Excel será implementada em breve!');
    });
    
    <?php if (count($dadosDiaSemana) > 0): ?>
    // Gráfico de desempenho por dia da semana
    const ctxDiaSemana = document.getElementById('chartDiaSemana').getContext('2d');
    
    const diasOrdenados = <?php
        $diasIndex = array_column($dadosDiaSemana, 'dia_semana');
        $diasLabels = [];
        for ($i = 0; $i < 7; $i++) {
            $key = array_search($i, $diasIndex);
            if ($key !== false) {
                $diasLabels[] = $dadosDiaSemana[$key]['nome_dia'];
            } else {
                $diasLabels[] = $diasSemana[$i];
            }
        }
        echo json_encode($diasLabels);
    ?>;
    
    const dadosCurtidas = <?php
        $curtidasPorDia = array_fill(0, 7, 0);
        foreach ($dadosDiaSemana as $dia) {
            $curtidasPorDia[$dia['dia_semana']] = $dia['media_curtidas'];
        }
        echo json_encode($curtidasPorDia);
    ?>;
    
    const dadosComentarios = <?php
        $comentariosPorDia = array_fill(0, 7, 0);
        foreach ($dadosDiaSemana as $dia) {
            $comentariosPorDia[$dia['dia_semana']] = $dia['media_comentarios'];
        }
        echo json_encode($comentariosPorDia);
    ?>;
    
    const dadosCompartilhamentos = <?php
        $compartilhamentosPorDia = array_fill(0, 7, 0);
        foreach ($dadosDiaSemana as $dia) {
            $compartilhamentosPorDia[$dia['dia_semana']] = $dia['media_compartilhamentos'];
        }
        echo json_encode($compartilhamentosPorDia);
    ?>;
    
    new Chart(ctxDiaSemana, {
        type: 'bar',
        data: {
            labels: diasOrdenados,
            datasets: [
                {
                    label: 'Curtidas',
                    data: dadosCurtidas,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Comentários',
                    data: dadosComentarios,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Compartilhamentos',
                    data: dadosCompartilhamentos,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
                       maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Engajamento Médio'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Dia da Semana'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Engajamento por Dia da Semana'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if (count($dadosHorario) > 0): ?>
    // Gráfico de desempenho por horário
    const ctxHorario = document.getElementById('chartHorario').getContext('2d');
    
    const horasLabels = <?php
        $horasOrdenadas = [];
        $horasValues = [];
        $comentariosValues = [];
        $compartilhamentosValues = [];
        
        // Ordenar por hora
        $tempHoras = [];
        foreach ($dadosHorario as $hora) {
            $tempHoras[$hora['hora']] = $hora;
        }
        ksort($tempHoras);
        
        // Preencher arrays para o gráfico
        for ($i = 0; $i < 24; $i++) {
            $horasOrdenadas[] = sprintf('%02d:00', $i);
            if (isset($tempHoras[$i])) {
                $horasValues[] = $tempHoras[$i]['media_curtidas'];
                $comentariosValues[] = $tempHoras[$i]['media_comentarios'];
                $compartilhamentosValues[] = $tempHoras[$i]['media_compartilhamentos'];
            } else {
                $horasValues[] = 0;
                $comentariosValues[] = 0;
                $compartilhamentosValues[] = 0;
            }
        }
        
        echo json_encode($horasOrdenadas);
    ?>;
    
    const dadosHorasCurtidas = <?php echo json_encode($horasValues); ?>;
    const dadosHorasComentarios = <?php echo json_encode($comentariosValues); ?>;
    const dadosHorasCompartilhamentos = <?php echo json_encode($compartilhamentosValues); ?>;
    
    new Chart(ctxHorario, {
        type: 'line',
        data: {
            labels: horasLabels,
            datasets: [
                {
                    label: 'Curtidas',
                    data: dadosHorasCurtidas,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Comentários',
                    data: dadosHorasComentarios,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Engajamento Médio'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Horário (hora do dia)'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Engajamento por Horário do Dia'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if (count($dadosTipoConteudo) > 0): ?>
    // Gráfico de desempenho por tipo de conteúdo
    const ctxTipoConteudo = document.getElementById('chartTipoConteudo').getContext('2d');
    
    const tiposConteudo = <?php echo json_encode(array_column($dadosTipoConteudo, 'tipo_conteudo')); ?>;
    const engajamentoTipoConteudo = <?php echo json_encode(array_column($dadosTipoConteudo, 'total_engajamento')); ?>;
    const curtidasTipoConteudo = <?php echo json_encode(array_column($dadosTipoConteudo, 'media_curtidas')); ?>;
    const comentariosTipoConteudo = <?php echo json_encode(array_column($dadosTipoConteudo, 'media_comentarios')); ?>;
    
    new Chart(ctxTipoConteudo, {
        type: 'bar',
        data: {
            labels: tiposConteudo,
            datasets: [
                {
                    label: 'Curtidas',
                    data: curtidasTipoConteudo,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Comentários',
                    data: comentariosTipoConteudo,
                    backgroundColor: 'rgba(153, 102, 255, 0.6)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Engajamento Médio'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Tipo de Conteúdo'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Desempenho por Tipo de Conteúdo'
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if (count($dadosTamanhoDescricao) > 0): ?>
    // Gráfico de desempenho por tamanho da descrição
    const ctxTamanhoDescricao = document.getElementById('chartTamanhoDescricao').getContext('2d');
    
    const tiposTamanhoDescricao = <?php echo json_encode(array_column($dadosTamanhoDescricao, 'tamanho_descricao')); ?>;
    const engajamentoTamanhoDescricao = <?php echo json_encode(array_column($dadosTamanhoDescricao, 'total_engajamento')); ?>;
    
    new Chart(ctxTamanhoDescricao, {
        type: 'doughnut',
        data: {
            labels: tiposTamanhoDescricao,
            datasets: [{
                data: engajamentoTamanhoDescricao,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Engajamento por Tamanho da Descrição'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.raw.toLocaleString() + ' engajamento médio';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if (count($dadosPalavrasChave) > 0): ?>
    // Gráfico de palavras-chave mais eficazes
    const ctxPalavrasChave = document.getElementById('chartPalavrasChave').getContext('2d');
    
    const palavrasChave = <?php echo json_encode(array_column($dadosPalavrasChave, 'palavras_chave')); ?>;
    const engajamentoPalavrasChave = <?php echo json_encode(array_column($dadosPalavrasChave, 'total_engajamento')); ?>;
    
    new Chart(ctxPalavrasChave, {
        type: 'bar',
        data: {
            labels: palavrasChave,
            datasets: [{
                label: 'Engajamento Total',
                data: engajamentoPalavrasChave,
                backgroundColor: 'rgba(153, 102, 255, 0.6)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Engajamento Médio'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Palavras-chave'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Palavras-chave com Melhor Desempenho'
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php
// Incluir o rodapé
include 'includes/footer.php';
?>