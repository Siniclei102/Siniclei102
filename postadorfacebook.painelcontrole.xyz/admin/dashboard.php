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

// Obter período de análise (padrão: últimos 30 dias)
$periodo = isset($_GET['periodo']) ? intval($_GET['periodo']) : 30;
$periodos_validos = [7, 15, 30, 60, 90, 180, 365];
if (!in_array($periodo, $periodos_validos)) {
    $periodo = 30;
}

$data_inicio = date('Y-m-d', strtotime("-{$periodo} days"));
$data_fim = date('Y-m-d');

// Estatísticas de Usuários
$queryUsuarios = "
    SELECT 
        COUNT(*) as total_usuarios,
        SUM(CASE WHEN criado_em >= ? THEN 1 ELSE 0 END) as novos_usuarios,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as usuarios_ativos,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as usuarios_inativos,
        SUM(CASE WHEN validade_ate < CURDATE() AND validade_ate IS NOT NULL THEN 1 ELSE 0 END) as contas_vencidas,
        SUM(CASE WHEN validade_ate >= CURDATE() AND validade_ate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND validade_ate IS NOT NULL THEN 1 ELSE 0 END) as contas_vencendo_7dias
    FROM usuarios
";
$stmtUsuarios = $db->prepare($queryUsuarios);
$stmtUsuarios->bind_param("s", $data_inicio);
$stmtUsuarios->execute();
$estatisticas_usuarios = $stmtUsuarios->get_result()->fetch_assoc();

// Estatísticas de Campanhas
$queryCampanhas = "
    SELECT 
        COUNT(*) as total_campanhas,
        SUM(CASE WHEN criado_em >= ? THEN 1 ELSE 0 END) as novas_campanhas,
        SUM(CASE WHEN ativa = 1 THEN 1 ELSE 0 END) as campanhas_ativas,
        SUM(CASE WHEN ativa = 0 THEN 1 ELSE 0 END) as campanhas_inativas
    FROM campanhas
";
$stmtCampanhas = $db->prepare($queryCampanhas);
$stmtCampanhas->bind_param("s", $data_inicio);
$stmtCampanhas->execute();
$estatisticas_campanhas = $stmtCampanhas->get_result()->fetch_assoc();

// Estatísticas de Grupos
$queryGrupos = "
    SELECT 
        COUNT(*) as total_grupos,
        SUM(CASE WHEN criado_em >= ? THEN 1 ELSE 0 END) as novos_grupos,
        SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as grupos_ativos
    FROM grupos_facebook
";
$stmtGrupos = $db->prepare($queryGrupos);
$stmtGrupos->bind_param("s", $data_inicio);
$stmtGrupos->execute();
$estatisticas_grupos = $stmtGrupos->get_result()->fetch_assoc();

// Estatísticas de Postagens
$queryPostagens = "
    SELECT 
        COUNT(*) as total_postagens,
        SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as postagens_sucesso,
        SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as postagens_falha,
        COUNT(DISTINCT grupo_id) as grupos_alcancados
    FROM logs_postagem
    WHERE postado_em >= ?
";
$stmtPostagens = $db->prepare($queryPostagens);
$stmtPostagens->bind_param("s", $data_inicio);
$stmtPostagens->execute();
$estatisticas_postagens = $stmtPostagens->get_result()->fetch_assoc();

// Taxa de sucesso
$taxa_sucesso = $estatisticas_postagens['total_postagens'] > 0 
    ? ($estatisticas_postagens['postagens_sucesso'] / $estatisticas_postagens['total_postagens']) * 100 
    : 0;

// Estatísticas de Login
$queryLogins = "
    SELECT 
        COUNT(*) as total_logins,
        COUNT(DISTINCT usuario_id) as usuarios_unicos
    FROM login_logs
    WHERE data_criacao >= ?
";
$stmtLogins = $db->prepare($queryLogins);
$stmtLogins->bind_param("s", $data_inicio);
$stmtLogins->execute();
$estatisticas_logins = $stmtLogins->get_result()->fetch_assoc();

// Principais erros de postagem
$queryErros = "
    SELECT 
        mensagem_erro,
        COUNT(*) as total
    FROM logs_postagem
    WHERE status = 'falha' AND postado_em >= ?
    GROUP BY mensagem_erro
    ORDER BY total DESC
    LIMIT 5
";
$stmtErros = $db->prepare($queryErros);
$stmtErros->bind_param("s", $data_inicio);
$stmtErros->execute();
$resultErros = $stmtErros->get_result();

// Dados para gráficos - Postagens por dia
$queryGraficoPostagens = "
    SELECT 
        DATE(postado_em) as data,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
        SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falha
    FROM logs_postagem
    WHERE postado_em >= ?
    GROUP BY DATE(postado_em)
    ORDER BY data ASC
";
$stmtGraficoPostagens = $db->prepare($queryGraficoPostagens);
$stmtGraficoPostagens->bind_param("s", $data_inicio);
$stmtGraficoPostagens->execute();
$resultGraficoPostagens = $stmtGraficoPostagens->get_result();

// Preparar dados para o gráfico
$datas = [];
$dados_sucesso = [];
$dados_falha = [];

while ($row = $resultGraficoPostagens->fetch_assoc()) {
    $datas[] = date('d/m', strtotime($row['data']));
    $dados_sucesso[] = $row['sucesso'];
    $dados_falha[] = $row['falha'];
}

// Dados para gráfico - Novos usuários por dia
$queryGraficoUsuarios = "
    SELECT 
        DATE(criado_em) as data,
        COUNT(*) as total
    FROM usuarios
    WHERE criado_em >= ?
    GROUP BY DATE(criado_em)
    ORDER BY data ASC
";
$stmtGraficoUsuarios = $db->prepare($queryGraficoUsuarios);
$stmtGraficoUsuarios->bind_param("s", $data_inicio);
$stmtGraficoUsuarios->execute();
$resultGraficoUsuarios = $stmtGraficoUsuarios->get_result();

// Preparar dados para o gráfico de usuários
$datas_usuarios = [];
$dados_usuarios = [];

while ($row = $resultGraficoUsuarios->fetch_assoc()) {
    $datas_usuarios[] = date('d/m', strtotime($row['data']));
    $dados_usuarios[] = $row['total'];
}

// Últimos usuários registrados
$queryUltimosUsuarios = "
    SELECT id, nome, email, criado_em, is_admin, is_active, validade_ate
    FROM usuarios
    ORDER BY criado_em DESC
    LIMIT 5
";
$resultUltimosUsuarios = $db->query($queryUltimosUsuarios);

// Últimas atividades de administração
$queryUltimasAtividades = "
    SELECT 
        a.id, 
        a.criado_em, 
        a.acao, 
        a.detalhes,
        u_admin.nome as admin_nome,
        u_usuario.nome as usuario_nome
    FROM admin_logs a
    JOIN usuarios u_admin ON a.admin_id = u_admin.id
    JOIN usuarios u_usuario ON a.usuario_id = u_usuario.id
    ORDER BY a.criado_em DESC
    LIMIT 10
";
$resultUltimasAtividades = $db->query($queryUltimasAtividades);

// Incluir o cabeçalho
include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Cabeçalho da Página com Filtro de Período -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0 text-gray-800">Dashboard Administrativo</h1>
            <p class="mb-0 text-muted">Visão geral do sistema de postagem automática</p>
        </div>
        <div class="col-md-6 text-end">
            <form method="GET" action="dashboard.php" class="d-inline-flex">
                <select class="form-select form-select-sm me-2" name="periodo" onchange="this.form.submit()">
                    <option value="7" <?php echo $periodo == 7 ? 'selected' : ''; ?>>Últimos 7 dias</option>
                    <option value="15" <?php echo $periodo == 15 ? 'selected' : ''; ?>>Últimos 15 dias</option>
                    <option value="30" <?php echo $periodo == 30 ? 'selected' : ''; ?>>Últimos 30 dias</option>
                    <option value="60" <?php echo $periodo == 60 ? 'selected' : ''; ?>>Últimos 60 dias</option>
                    <option value="90" <?php echo $periodo == 90 ? 'selected' : ''; ?>>Últimos 90 dias</option>
                    <option value="180" <?php echo $periodo == 180 ? 'selected' : ''; ?>>Últimos 6 meses</option>
                    <option value="365" <?php echo $periodo == 365 ? 'selected' : ''; ?>>Último ano</option>
                </select>
                <span class="form-text ms-2 mt-1">
                    <strong>Período:</strong> <?php echo date('d/m/Y', strtotime($data_inicio)); ?> - <?php echo date('d/m/Y', strtotime($data_fim)); ?>
                </span>
            </form>
        </div>
    </div>
    
    <!-- Alertas de Sistema -->
    <?php
    // Consulta para obter contas vencendo em breve (próximos 7 dias)
    $contasVencendo = $estatisticas_usuarios['contas_vencendo_7dias'];
    $contasVencidas = $estatisticas_usuarios['contas_vencidas'];
    
    // Se houver contas vencendo ou vencidas, mostrar alerta
    if ($contasVencendo > 0 || $contasVencidas > 0):
    ?>
    <div class="alert alert-warning admin-notification mb-4">
        <div class="d-flex align-items-center">
            <div class="alert-icon me-3">
                <i class="fas fa-bell"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="mb-1">Alerta de Vencimento de Contas</h5>
                <p class="mb-0">
                    <?php if ($contasVencendo > 0): ?>
                        <span class="badge bg-warning me-2"><?php echo $contasVencendo; ?></span> conta(s) vencendo nos próximos 7 dias.
                    <?php endif; ?>
                    
                    <?php if ($contasVencidas > 0): ?>
                        <span class="badge bg-danger me-2"><?php echo $contasVencidas; ?></span> conta(s) vencida(s).
                    <?php endif; ?>
                </p>
            </div>
            <a href="relatorio_validade.php" class="btn btn-sm btn-warning ms-3">
                <i class="fas fa-eye me-1"></i> Ver Detalhes
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Cards de Estatísticas Principais -->
    <div class="row mb-4">
        <!-- Estatísticas de Usuários -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <div class="stat-card-title">Usuários</div>
                            <div class="stat-card-value"><?php echo number_format($estatisticas_usuarios['total_usuarios']); ?></div>
                            <div class="stat-card-desc">
                                <span class="text-success"><i class="fas fa-arrow-up me-1"></i> <?php echo number_format($estatisticas_usuarios['novos_usuarios']); ?></span> novos no período
                            </div>
                        </div>
                        <div class="stat-card-icon bg-primary-light text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-progress-container mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="stat-label">Ativos</span>
                            <span class="stat-value"><?php echo number_format($estatisticas_usuarios['usuarios_ativos']); ?></span>
                        </div>
                        <div class="progress stat-progress">
                            <?php 
                            $percent_ativos = $estatisticas_usuarios['total_usuarios'] > 0 
                                ? ($estatisticas_usuarios['usuarios_ativos'] / $estatisticas_usuarios['total_usuarios']) * 100 
                                : 0;
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percent_ativos; ?>%" 
                                 aria-valuenow="<?php echo $percent_ativos; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
                <div class="modern-card-footer">
                    <a href="usuarios.php" class="stat-card-link">
                        <span>Ver Detalhes</span>
                        <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Estatísticas de Campanhas -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <div class="stat-card-title">Campanhas</div>
                            <div class="stat-card-value"><?php echo number_format($estatisticas_campanhas['total_campanhas']); ?></div>
                            <div class="stat-card-desc">
                                <span class="text-success"><i class="fas fa-arrow-up me-1"></i> <?php echo number_format($estatisticas_campanhas['novas_campanhas']); ?></span> novas no período
                            </div>
                        </div>
                        <div class="stat-card-icon bg-success-light text-success">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                    </div>
                    <div class="stat-progress-container mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="stat-label">Ativas</span>
                            <span class="stat-value"><?php echo number_format($estatisticas_campanhas['campanhas_ativas']); ?></span>
                        </div>
                        <div class="progress stat-progress">
                            <?php 
                            $percent_campanhas = $estatisticas_campanhas['total_campanhas'] > 0 
                                ? ($estatisticas_campanhas['campanhas_ativas'] / $estatisticas_campanhas['total_campanhas']) * 100 
                                : 0;
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percent_campanhas; ?>%" 
                                 aria-valuenow="<?php echo $percent_campanhas; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
                <div class="modern-card-footer">
                    <a href="../campanhas.php" class="stat-card-link">
                        <span>Ver Detalhes</span>
                        <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Estatísticas de Grupos -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <div class="stat-card-title">Grupos</div>
                            <div class="stat-card-value"><?php echo number_format($estatisticas_grupos['total_grupos']); ?></div>
                            <div class="stat-card-desc">
                                <span class="text-success"><i class="fas fa-arrow-up me-1"></i> <?php echo number_format($estatisticas_grupos['novos_grupos']); ?></span> novos no período
                            </div>
                        </div>
                        <div class="stat-card-icon bg-info-light text-info">
                            <i class="fas fa-users-cog"></i>
                        </div>
                    </div>
                    <div class="stat-progress-container mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="stat-label">Ativos</span>
                            <span class="stat-value"><?php echo number_format($estatisticas_grupos['grupos_ativos']); ?></span>
                        </div>
                        <div class="progress stat-progress">
                            <?php 
                            $percent_grupos = $estatisticas_grupos['total_grupos'] > 0 
                                ? ($estatisticas_grupos['grupos_ativos'] / $estatisticas_grupos['total_grupos']) * 100 
                                : 0;
                            ?>
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $percent_grupos; ?>%" 
                                 aria-valuenow="<?php echo $percent_grupos; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
                <div class="modern-card-footer">
                    <a href="../grupos.php" class="stat-card-link">
                        <span>Ver Detalhes</span>
                        <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Estatísticas de Postagens -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <div class="stat-card-title">Postagens</div>
                            <div class="stat-card-value"><?php echo number_format($estatisticas_postagens['total_postagens']); ?></div>
                            <div class="stat-card-desc">
                                Taxa de sucesso: <span class="text-<?php echo $taxa_sucesso >= 80 ? 'success' : ($taxa_sucesso >= 50 ? 'warning' : 'danger'); ?>"><?php echo number_format($taxa_sucesso, 1); ?>%</span>
                            </div>
                        </div>
                        <div class="stat-card-icon bg-warning-light text-warning">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                    </div>
                    <div class="stat-progress-container mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="stat-label">Sucesso</span>
                            <span class="stat-value"><?php echo number_format($estatisticas_postagens['postagens_sucesso']); ?></span>
                        </div>
                        <div class="progress stat-progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $taxa_sucesso; ?>%" 
                                 aria-valuenow="<?php echo $taxa_sucesso; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
                <div class="modern-card-footer">
                    <a href="../logs.php" class="stat-card-link">
                        <span>Ver Detalhes</span>
                        <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficos e Métricas -->
    <div class="row mb-4">
        <!-- Gráfico de Postagens -->
        <div class="col-xl-8 col-lg-7">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-chart-bar me-2 text-primary"></i> Postagens Diárias
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
        <div class="col-xl-4 col-lg-5">
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
                                    $percent = $estatisticas_postagens['postagens_falha'] > 0 
                                        ? ($erro['total'] / $estatisticas_postagens['postagens_falha']) * 100 
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
                <?php if ($resultErros->num_rows > 0): ?>
                    <div class="modern-card-footer">
                        <a href="../logs.php?status=falha" class="stat-card-link">
                            <span>Ver Todos os Erros</span>
                            <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Segunda Linha de Gráficos e Tabelas -->
    <div class="row mb-4">
        <!-- Gráfico de Novos Usuários -->
        <div class="col-xl-6">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-user-plus me-2 text-success"></i> Novos Usuários
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="chart-container">
                        <canvas id="usuariosChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Distribuição de Validade -->
        <div class="col-xl-6">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-calendar-check me-2 text-primary"></i> Distribuição de Validade
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="chart-container">
                        <canvas id="validadeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Últimos Registros e Atividades -->
    <div class="row">
        <!-- Últimos Usuários Registrados -->
        <div class="col-xl-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header d-flex justify-content-between align-items-center">
                    <h5 class="modern-card-title mb-0">
                        <i class="fas fa-user-plus me-2 text-primary"></i> Últimos Registros
                    </h5>
                    <a href="usuarios.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                </div>
                <div class="modern-card-body">
                    <?php if ($resultUltimosUsuarios->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Usuário</th>
                                        <th>Email</th>
                                        <th>Tipo</th>
                                        <th>Validade</th>
                                        <th>Registrado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $resultUltimosUsuarios->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-2">
                                                        <div class="avatar-initial rounded-circle bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                                            <?php echo mb_substr($user['nome'], 0, 1, 'UTF-8'); ?>
                                                        </div>
                                                    </div>
                                                    <div><?php echo htmlspecialchars($user['nome']); ?></div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php if ($user['is_admin']): ?>
                                                    <span class="badge bg-primary">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Usuário</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($user['validade_ate']) {
                                                    $validade = new DateTime($user['validade_ate']);
                                                    $hoje = new DateTime();
                                                    
                                                    if ($validade < $hoje) {
                                                        echo '<span class="badge bg-danger">Vencida</span>';
                                                    } else {
                                                        $diff = $hoje->diff($validade);
                                                        if ($diff->days <= 7) {
                                                            echo '<span class="badge bg-warning">'. $diff->days .' dias</span>';
                                                        } else {
                                                            echo date('d/m/Y', strtotime($user['validade_ate']));
                                                        }
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-secondary">Sem validade</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo time_elapsed_string($user['criado_em']); ?></td>
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
                            <h5>Sem registros recentes</h5>
                            <p class="text-muted">Nenhum usuário registrado recentemente.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Últimas Atividades de Administração -->
        <div class="col-xl-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-history me-2 text-info"></i> Últimas Atividades
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if ($resultUltimasAtividades && $resultUltimasAtividades->num_rows > 0): ?>
                        <div class="activity-timeline">
                            <?php while ($atividade = $resultUltimasAtividades->fetch_assoc()): ?>
                                <?php 
                                $detalhes = json_decode($atividade['detalhes'], true);
                                $iconClass = '';
                                $badgeClass = '';
                                
                                if ($atividade['acao'] === 'extensao_validade') {
                                    $iconClass = 'calendar-plus';
                                    $badgeClass = 'success';
                                    $acaoTexto = 'estendeu a validade de';
                                } elseif ($atividade['acao'] === 'suspension') {
                                    $iconClass = 'user-lock';
                                    $badgeClass = 'danger';
                                    $acaoTexto = 'suspendeu';
                                } else {
                                    $iconClass = 'cog';
                                    $badgeClass = 'info';
                                    $acaoTexto = 'atualizou';
                                }
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon bg-<?php echo $badgeClass; ?>-light text-<?php echo $badgeClass; ?>">
                                        <i class="fas fa-<?php echo $iconClass; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-text">
                                            <strong><?php echo htmlspecialchars($atividade['admin_nome']); ?></strong> 
                                            <?php echo $acaoTexto; ?> 
                                            <strong><?php echo htmlspecialchars($atividade['usuario_nome']); ?></strong>
                                            
                                            <?php if ($atividade['acao'] === 'extensao_validade' && isset($detalhes['dias_adicionados'])): ?>
                                                - adicionado <?php echo $detalhes['dias_adicionados']; ?> dias (até <?php echo date('d/m/Y', strtotime($detalhes['nova_validade'])); ?>)
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($detalhes['observacao'])): ?>
                                                <span class="activity-note">"<?php echo htmlspecialchars($detalhes['observacao']); ?>"</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-time"><?php echo time_elapsed_string($atividade['criado_em']); ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h5>Sem atividades recentes</h5>
                            <p class="text-muted">Nenhuma atividade administrativa registrada.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Estilos para cards de estatísticas */
.stat-card {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.stat-card-info {
    flex: 1;
}

.stat-card-title {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 5px;
}

.stat-card-value {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 5px;
    line-height: 1;
}

.stat-card-desc {
    font-size: 0.75rem;
    color: #6c757d;
}

.stat-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-progress-container {
    margin-top: 15px;
}

.stat-label {
    font-size: 0.75rem;
    color: #6c757d;
}

.stat-value {
    font-size: 0.75rem;
    font-weight: 600;
}

.stat-progress {
    height: 6px;
    border-radius: 3px;
}

.stat-card-link {
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #495057;
    font-size: 0.875rem;
    font-weight: 500;
    transition: color 0.2s;
}

.stat-card-link:hover {
    color: var(--bs-primary);
}

.modern-card-footer {
    padding: 0.75rem 1.25rem;
    background-color: rgba(0, 0, 0, 0.02);
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

/* Cores de fundo para ícones */
.bg-primary-light { background-color: rgba(52, 152, 219, 0.1); }
.bg-success-light { background-color: rgba(46, 204, 113, 0.1); }
.bg-warning-light { background-color: rgba(243, 156, 18, 0.1); }
.bg-danger-light { background-color: rgba(231, 76, 60, 0.1); }
.bg-info-light { background-color: rgba(26, 188, 156, 0.1); }

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
    padding: 15px 0;
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

/* Timeline de atividades */
.activity-timeline {
    position: relative;
    padding-left: 10px;
}

.activity-item {
    display: flex;
    margin-bottom: 20px;
    position: relative;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    margin-right: 15px;
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
    min-width: 0;
}

.activity-text {
    margin-bottom: 5px;
    line-height: 1.4;
}

.activity-note {
    display: block;
    font-style: italic;
    color: #6c757d;
    margin-top: 3px;
}

.activity-time {
    font-size: 0.8rem;
    color: #6c757d;
}

.avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.avatar-sm {
    width: 32px;
    height: 32px;
}

.avatar-initial {
    font-weight: 600;
    color: white;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Alerta administrativo */
.admin-notification {
    border-left: 5px solid #ffc107;
}

.alert-icon {
    font-size: 1.5rem;
    color: #ffc107;
}
</style>

<!-- JavaScript para Gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Postagens
    const ctxPostagens = document.getElementById('postagensChart').getContext('2d');
    new Chart(ctxPostagens, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($datas); ?>,
            datasets: [
                {
                    label: 'Sucesso',
                    data: <?php echo json_encode($dados_sucesso); ?>,
                    backgroundColor: 'rgba(46, 204, 113, 0.7)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Falha',
                    data: <?php echo json_encode($dados_falha); ?>,
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
    
    // Gráfico de Novos Usuários
    const ctxUsuarios = document.getElementById('usuariosChart').getContext('2d');
    new Chart(ctxUsuarios, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($datas_usuarios); ?>,
            datasets: [
                {
                    label: 'Novos Usuários',
                    data: <?php echo json_encode($dados_usuarios); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
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
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false,
                }
            }
        }
    });
    
    // Gráfico de Distribuição de Validade
    const ctxValidade = document.getElementById('validadeChart').getContext('2d');
    new Chart(ctxValidade, {
        type: 'doughnut',
        data: {
            labels: ['Contas Vencidas', 'Vencendo em 7 Dias', 'Ativas', 'Sem Validade'],
            datasets: [{
                data: [
                    <?php echo $estatisticas_usuarios['contas_vencidas']; ?>,
                    <?php echo $estatisticas_usuarios['contas_vencendo_7dias']; ?>,
                    <?php echo $estatisticas_usuarios['usuarios_ativos'] - $estatisticas_usuarios['contas_vencendo_7dias'] - $estatisticas_usuarios['contas_vencidas']; ?>,
                    <?php echo $estatisticas_usuarios['total_usuarios'] - $estatisticas_usuarios['usuarios_ativos']; ?>
                ],
                backgroundColor: [
                    'rgba(231, 76, 60, 0.7)',
                    'rgba(243, 156, 18, 0.7)',
                    'rgba(46, 204, 113, 0.7)',
                    'rgba(108, 117, 125, 0.7)'
                ],
                borderColor: [
                    'rgba(231, 76, 60, 1)',
                    'rgba(243, 156, 18, 1)',
                    'rgba(46, 204, 113, 1)',
                    'rgba(108, 117, 125, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
            },
            cutout: '60%'
        }
    });
});
</script>

<?php
// Função auxiliar para tempo relativo
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
    return $string ? 'Há ' . implode(', ', $string) : 'Agora';
}

// Incluir o rodapé
include '../includes/footer.php';
?>