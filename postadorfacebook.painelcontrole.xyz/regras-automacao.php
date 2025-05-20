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

// Inicializar objeto de API do Facebook
$fb = new FacebookAPI($db);

// Salvar nova regra
if (isset($_POST['salvar_regra'])) {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $ativa = isset($_POST['ativa']) ? 1 : 0;
    $tipo = $_POST['tipo'];
    
    // Validar campos
    $erros = [];
    
    if (empty($nome)) {
        $erros[] = "O nome da regra é obrigatório";
    }
    
    // Validar condições e ações com base no tipo de regra
    $condicoes = [];
    $acoes = [];
    
    if ($tipo == 'horario') {
        // Condições para regra baseada em horário
        $dias_semana = isset($_POST['dias_semana']) ? $_POST['dias_semana'] : [];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fim = $_POST['hora_fim'];
        
        if (empty($dias_semana)) {
            $erros[] = "Selecione pelo menos um dia da semana";
        }
        
        if (empty($hora_inicio) || empty($hora_fim)) {
            $erros[] = "Horário de início e fim são obrigatórios";
        }
        
        $condicoes = [
            'dias_semana' => $dias_semana,
            'hora_inicio' => $hora_inicio,
            'hora_fim' => $hora_fim
        ];
        
        // Ações para regra de horário
        $campanha_id = $_POST['campanha_id'] ?? null;
        $grupos = isset($_POST['grupos']) ? $_POST['grupos'] : [];
        $max_posts = $_POST['max_posts'] ?? 1;
        $intervalo_minutos = $_POST['intervalo_minutos'] ?? 30;
        
        if (empty($campanha_id)) {
            $erros[] = "Selecione uma campanha";
        }
        
        if (empty($grupos)) {
            $erros[] = "Selecione pelo menos um grupo";
        }
        
        $acoes = [
            'campanha_id' => $campanha_id,
            'grupos' => $grupos,
            'max_posts' => $max_posts,
            'intervalo_minutos' => $intervalo_minutos
        ];
    } elseif ($tipo == 'engajamento') {
        // Condições para regra baseada em engajamento
        $metricas = $_POST['metricas'];
        $operador = $_POST['operador'];
        $valor_limite = $_POST['valor_limite'];
        
        if (empty($metricas)) {
            $erros[] = "Selecione uma métrica para avaliar";
        }
        
        if (empty($operador)) {
            $erros[] = "Selecione um operador de comparação";
        }
        
        if (!is_numeric($valor_limite)) {
            $erros[] = "O valor limite deve ser um número";
        }
        
        $condicoes = [
            'metricas' => $metricas,
            'operador' => $operador,
            'valor_limite' => $valor_limite,
            'periodo_dias' => $_POST['periodo_dias'] ?? 7
        ];
        
        // Ações para regra de engajamento
        $acao_tipo = $_POST['acao_tipo'];
        $acao_campanha_id = $_POST['acao_campanha_id'] ?? null;
        $acao_grupos = isset($_POST['acao_grupos']) ? $_POST['acao_grupos'] : [];
        
        if (empty($acao_tipo)) {
            $erros[] = "Selecione um tipo de ação";
        }
        
        if ($acao_tipo == 'trocar_campanha' && empty($acao_campanha_id)) {
            $erros[] = "Selecione uma campanha para a ação";
        }
        
        if ($acao_tipo == 'adicionar_grupos' && empty($acao_grupos)) {
            $erros[] = "Selecione pelo menos um grupo para a ação";
        }
        
        $acoes = [
            'acao_tipo' => $acao_tipo,
            'acao_campanha_id' => $acao_campanha_id,
            'acao_grupos' => $acao_grupos
        ];
    } elseif ($tipo == 'rotacao') {
        // Condições para regra de rotação de conteúdo
        $campanhas_rotacao = isset($_POST['campanhas_rotacao']) ? $_POST['campanhas_rotacao'] : [];
        $rotacao_frequencia = $_POST['rotacao_frequencia'] ?? 'diaria';
        
        if (count($campanhas_rotacao) < 2) {
            $erros[] = "Selecione pelo menos duas campanhas para rotação";
        }
        
        $condicoes = [
            'campanhas_rotacao' => $campanhas_rotacao,
            'rotacao_frequencia' => $rotacao_frequencia
        ];
        
        // Ações para regra de rotação
        $rotacao_grupos = isset($_POST['rotacao_grupos']) ? $_POST['rotacao_grupos'] : [];
        $rotacao_posts_por_dia = $_POST['rotacao_posts_por_dia'] ?? 1;
        
        if (empty($rotacao_grupos)) {
            $erros[] = "Selecione pelo menos um grupo para a rotação";
        }
        
        $acoes = [
            'rotacao_grupos' => $rotacao_grupos,
            'rotacao_posts_por_dia' => $rotacao_posts_por_dia,
            'rotacao_horarios' => $_POST['rotacao_horarios'] ?? []
        ];
    }
    
    // Se não houver erros, salvar a regra
    if (empty($erros)) {
        $condicoes_json = json_encode($condicoes, JSON_UNESCAPED_UNICODE);
        $acoes_json = json_encode($acoes, JSON_UNESCAPED_UNICODE);
        
        $query = "
            INSERT INTO regras_automacao (
                usuario_id,
                nome,
                descricao,
                tipo,
                condicoes,
                acoes,
                ativa,
                criado_em,
                ultima_execucao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NULL)
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("isssssi", $userId, $nome, $descricao, $tipo, $condicoes_json, $acoes_json, $ativa);
        
        if ($stmt->execute()) {
            $regra_id = $stmt->insert_id;
            
            // Criar log de criação da regra
            $log_query = "
                INSERT INTO logs_sistema (
                    usuario_id,
                    tipo,
                    descricao,
                    data_hora,
                    ip
                ) VALUES (?, 'regra', ?, NOW(), ?)
            ";
            
            $descricao_log = "Regra de automação '{$nome}' criada";
            $ip = $_SERVER['REMOTE_ADDR'];
            
            $stmt_log = $db->prepare($log_query);
            $stmt_log->bind_param("iss", $userId, $descricao_log, $ip);
            $stmt_log->execute();
            
            $_SESSION['mensagem'] = "Regra '{$nome}' criada com sucesso!";
            $_SESSION['mensagem_tipo'] = "success";
            
            header('Location: regras-automacao.php');
            exit;
        } else {
            $_SESSION['mensagem'] = "Erro ao criar regra: " . $db->error;
            $_SESSION['mensagem_tipo'] = "danger";
        }
    } else {
        $_SESSION['mensagem'] = "Corrija os seguintes erros:<br>" . implode('<br>', $erros);
        $_SESSION['mensagem_tipo'] = "danger";
    }
}

// Excluir regra
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $regra_id = intval($_GET['excluir']);
    
    // Verificar se a regra pertence ao usuário
    $query_check = "SELECT id, nome FROM regras_automacao WHERE id = ? AND usuario_id = ?";
    $stmt_check = $db->prepare($query_check);
    $stmt_check->bind_param("ii", $regra_id, $userId);
    $stmt_check->execute();
    $regra = $stmt_check->get_result()->fetch_assoc();
    
    if ($regra) {
        // Excluir a regra
        $query_delete = "DELETE FROM regras_automacao WHERE id = ?";
        $stmt_delete = $db->prepare($query_delete);
        $stmt_delete->bind_param("i", $regra_id);
        
        if ($stmt_delete->execute()) {
            $_SESSION['mensagem'] = "Regra '{$regra['nome']}' excluída com sucesso!";
            $_SESSION['mensagem_tipo'] = "success";
        } else {
            $_SESSION['mensagem'] = "Erro ao excluir regra: " . $db->error;
            $_SESSION['mensagem_tipo'] = "danger";
        }
    } else {
        $_SESSION['mensagem'] = "Regra não encontrada ou não pertence a este usuário";
        $_SESSION['mensagem_tipo'] = "danger";
    }
    
    header('Location: regras-automacao.php');
    exit;
}

// Alternar estado da regra (ativa/inativa)
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $regra_id = intval($_GET['toggle']);
    
    // Verificar se a regra pertence ao usuário
    $query_check = "SELECT id, nome, ativa FROM regras_automacao WHERE id = ? AND usuario_id = ?";
    $stmt_check = $db->prepare($query_check);
    $stmt_check->bind_param("ii", $regra_id, $userId);
    $stmt_check->execute();
    $regra = $stmt_check->get_result()->fetch_assoc();
    
    if ($regra) {
        // Alternar estado da regra
        $novo_estado = $regra['ativa'] ? 0 : 1;
        $estado_texto = $novo_estado ? "ativada" : "desativada";
        
        $query_update = "UPDATE regras_automacao SET ativa = ? WHERE id = ?";
        $stmt_update = $db->prepare($query_update);
        $stmt_update->bind_param("ii", $novo_estado, $regra_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['mensagem'] = "Regra '{$regra['nome']}' {$estado_texto} com sucesso!";
            $_SESSION['mensagem_tipo'] = "success";
        } else {
            $_SESSION['mensagem'] = "Erro ao atualizar regra: " . $db->error;
            $_SESSION['mensagem_tipo'] = "danger";
        }
    } else {
        $_SESSION['mensagem'] = "Regra não encontrada ou não pertence a este usuário";
        $_SESSION['mensagem_tipo'] = "danger";
    }
    
    header('Location: regras-automacao.php');
    exit;
}

// Executar regra manualmente
if (isset($_GET['executar']) && is_numeric($_GET['executar'])) {
    $regra_id = intval($_GET['executar']);
    
    // Verificar se a regra pertence ao usuário
    $query_check = "SELECT id, nome, tipo, condicoes, acoes FROM regras_automacao WHERE id = ? AND usuario_id = ?";
    $stmt_check = $db->prepare($query_check);
    $stmt_check->bind_param("ii", $regra_id, $userId);
    $stmt_check->execute();
    $regra = $stmt_check->get_result()->fetch_assoc();
    
    if ($regra) {
        // Incluir e chamar o executor de regras
        require_once 'classes/RegrasAutomacaoExecutor.php';
        $executor = new RegrasAutomacaoExecutor($db);
        
        $resultado = $executor->executarRegra($regra_id, $userId);
        
        if ($resultado['sucesso']) {
            $_SESSION['mensagem'] = "Regra '{$regra['nome']}' executada com sucesso! " . $resultado['mensagem'];
            $_SESSION['mensagem_tipo'] = "success";
        } else {
            $_SESSION['mensagem'] = "Erro ao executar regra: " . $resultado['mensagem'];
            $_SESSION['mensagem_tipo'] = "danger";
        }
    } else {
        $_SESSION['mensagem'] = "Regra não encontrada ou não pertence a este usuário";
        $_SESSION['mensagem_tipo'] = "danger";
    }
    
    header('Location: regras-automacao.php');
    exit;
}

// Obter todas as regras do usuário
$query_regras = "
    SELECT 
        r.id,
        r.nome,
        r.descricao,
        r.tipo,
        r.condicoes,
        r.acoes,
        r.ativa,
        r.criado_em,
        r.ultima_execucao,
        r.ultima_mensagem,
        COUNT(l.id) as total_execucoes
    FROM 
        regras_automacao r
        LEFT JOIN logs_execucao_regras l ON r.id = l.regra_id
    WHERE 
        r.usuario_id = ?
    GROUP BY 
        r.id
    ORDER BY 
        r.criado_em DESC
";

$stmt_regras = $db->prepare($query_regras);
$stmt_regras->bind_param("i", $userId);
$stmt_regras->execute();
$regras = $stmt_regras->get_result();

// Obter campanhas do usuário para o formulário
$query_campanhas = "SELECT id, nome FROM campanhas WHERE usuario_id = ? AND ativa = 1 ORDER BY nome";
$stmt_campanhas = $db->prepare($query_campanhas);
$stmt_campanhas->bind_param("i", $userId);
$stmt_campanhas->execute();
$campanhas = $stmt_campanhas->get_result();

// Obter grupos do usuário para o formulário
$query_grupos = "SELECT id, nome FROM grupos_facebook WHERE usuario_id = ? AND ativo = 1 ORDER BY nome";
$stmt_grupos = $db->prepare($query_grupos);
$stmt_grupos->bind_param("i", $userId);
$stmt_grupos->execute();
$grupos = $stmt_grupos->get_result();

// Incluir o cabeçalho
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-robot me-2"></i> Regras de Automação</h1>
            <p class="mb-0 text-muted">Configure regras personalizadas para automatizar suas postagens</p>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaRegra">
                <i class="fas fa-plus me-1"></i> Nova Regra de Automação
            </button>
        </div>
    </div>
    
    <!-- Mensagens de feedback -->
    <?php if(isset($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem_tipo']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['mensagem']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
        <?php unset($_SESSION['mensagem_tipo']); ?>
    <?php endif; ?>
    
    <!-- Cartões de explicação -->
    <div class="row mb-4">
        <div class="col-md-4 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-circle bg-primary text-white me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h5 class="mb-0">Regras baseadas em horário</h5>
                    </div>
                    <p>Configure postagens automáticas em horários específicos e dias da semana. Defina quais campanhas e grupos serão utilizados em cada faixa de horário.</p>
                    <div class="text-end">
                        <button class="btn btn-sm btn-outline-primary nova-regra" data-tipo="horario">
                            <i class="fas fa-plus me-1"></i> Criar regra de horário
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-circle bg-success text-white me-3">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h5 class="mb-0">Regras baseadas em engajamento</h5>
                    </div>
                    <p>Automatize decisões com base no desempenho. Altere campanhas, grupos ou frequência de postagens conforme o engajamento atingido.</p>
                    <div class="text-end">
                        <button class="btn btn-sm btn-outline-success nova-regra" data-tipo="engajamento">
                            <i class="fas fa-plus me-1"></i> Criar regra de engajamento
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-circle bg-info text-white me-3">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <h5 class="mb-0">Regras de rotação de conteúdo</h5>
                    </div>
                    <p>Alterne entre diferentes campanhas automaticamente. Configure rotações diárias, semanais ou mensais entre suas campanhas para manter o conteúdo fresco.</p>
                    <div class="text-end">
                        <button class="btn btn-sm btn-outline-info nova-regra" data-tipo="rotacao">
                            <i class="fas fa-plus me-1"></i> Criar regra de rotação
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista de Regras -->
    <div class="row">
        <div class="col-md-12">
            <div class="modern-card mb-4">
                <div class="modern-card-header">
                    <h5 class="modern-card-title"><i class="fas fa-list me-2"></i> Suas Regras de Automação</h5>
                </div>
                <div class="modern-card-body">
                    <?php if ($regras->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Detalhes</th>
                                        <th>Status</th>
                                        <th>Última Execução</th>
                                        <th>Execuções</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($regra = $regras->fetch_assoc()): 
                                        $condicoes = json_decode($regra['condicoes'], true);
                                        $acoes = json_decode($regra['acoes'], true);
                                        
                                        // Determinar ícone e descrição com base no tipo
                                        switch ($regra['tipo']) {
                                            case 'horario':
                                                $icone = '<i class="fas fa-clock text-primary"></i>';
                                                $tipo_texto = 'Baseada em horário';
                                                
                                                // Formatar dias da semana
                                                $dias_nomes = [
                                                    'sun' => 'Dom',
                                                    'mon' => 'Seg',
                                                    'tue' => 'Ter',
                                                    'wed' => 'Qua',
                                                    'thu' => 'Qui',
                                                    'fri' => 'Sex',
                                                    'sat' => 'Sáb'
                                                ];
                                                
                                                $dias_texto = [];
                                                foreach ($condicoes['dias_semana'] as $dia) {
                                                    $dias_texto[] = $dias_nomes[$dia] ?? $dia;
                                                }
                                                
                                                $detalhe = 'Dias: ' . implode(', ', $dias_texto) . 
                                                        ' | Horário: ' . $condicoes['hora_inicio'] . ' - ' . $condicoes['hora_fim'];
                                                break;
                                                
                                            case 'engajamento':
                                                $icone = '<i class="fas fa-chart-line text-success"></i>';
                                                $tipo_texto = 'Baseada em engajamento';
                                                
                                                $operadores = [
                                                    'maior' => '>',
                                                    'menor' => '<',
                                                    'igual' => '=',
                                                    'maior_igual' => '≥',
                                                    'menor_igual' => '≤'
                                                ];
                                                
                                                $metricas_nomes = [
                                                    'curtidas' => 'Curtidas',
                                                    'comentarios' => 'Comentários',
                                                    'compartilhamentos' => 'Compartilhamentos',
                                                    'engajamento_total' => 'Engajamento Total'
                                                ];
                                                
                                                $metrica = $metricas_nomes[$condicoes['metricas']] ?? $condicoes['metricas'];
                                                $operador = $operadores[$condicoes['operador']] ?? $condicoes['operador'];
                                                
                                                $detalhe = "Se {$metrica} {$operador} {$condicoes['valor_limite']} nos últimos {$condicoes['periodo_dias']} dias";
                                                break;
                                                
                                            case 'rotacao':
                                                $icone = '<i class="fas fa-sync-alt text-info"></i>';
                                                $tipo_texto = 'Rotação de conteúdo';
                                                
                                                $freq_nomes = [
                                                    'diaria' => 'Diária',
                                                    'semanal' => 'Semanal',
                                                    'mensal' => 'Mensal'
                                                ];
                                                
                                                $frequencia = $freq_nomes[$condicoes['rotacao_frequencia']] ?? $condicoes['rotacao_frequencia'];
                                                $num_campanhas = count($condicoes['campanhas_rotacao']);
                                                
                                                $detalhe = "Rotação {$frequencia} entre {$num_campanhas} campanhas com {$acoes['rotacao_posts_por_dia']} posts por dia";
                                                break;
                                                
                                            default:
                                                $icone = '<i class="fas fa-cog"></i>';
                                                $tipo_texto = 'Personalizada';
                                                $detalhe = 'Configuração personalizada';
                                                break;
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($regra['nome']); ?></td>
                                            <td>
                                                <span class="d-flex align-items-center">
                                                    <?php echo $icone; ?>
                                                    <span class="ms-2"><?php echo $tipo_texto; ?></span>
                                                </span>
                                            </td>
                                            <td><?php echo $detalhe; ?></td>
                                            <td>
                                                <?php if ($regra['ativa']): ?>
                                                    <span class="badge bg-success">Ativa</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inativa</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($regra['ultima_execucao']): ?>
                                                    <span title="<?php echo $regra['ultima_mensagem']; ?>">
                                                        <?php echo date('d/m/Y H:i', strtotime($regra['ultima_execucao'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Nunca executada</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($regra['total_execucoes']); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="?executar=<?php echo $regra['id']; ?>" class="btn btn-sm btn-outline-primary" title="Executar Agora">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                    <a href="regra-detalhes.php?id=<?php echo $regra['id']; ?>" class="btn btn-sm btn-outline-info" title="Ver Detalhes">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-warning editar-regra" data-id="<?php echo $regra['id']; ?>" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="?toggle=<?php echo $regra['id']; ?>" class="btn btn-sm btn-outline-<?php echo $regra['ativa'] ? 'danger' : 'success'; ?>" title="<?php echo $regra['ativa'] ? 'Desativar' : 'Ativar'; ?>">
                                                        <i class="fas fa-<?php echo $regra['ativa'] ? 'pause' : 'play'; ?>"></i>
                                                    </a>
                                                    <a href="?excluir=<?php echo $regra['id']; ?>" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta regra?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
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
                                <i class="fas fa-robot"></i>
                            </div>
                            <h5>Nenhuma regra de automação configurada</h5>
                            <p class="text-muted">Crie suas primeiras regras para automatizar suas postagens no Facebook</p>
                            <button class="btn btn-primary mt-3 nova-regra" data-tipo="horario">
                                <i class="fas fa-plus me-1"></i> Criar primeira regra
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dicas de Uso -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title"><i class="fas fa-lightbulb me-2 text-warning"></i> Dicas para Automação Eficiente</h5>
                </div>
                <div class="modern-card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="tip-card">
                                <h6><i class="fas fa-clock text-primary me-2"></i> Melhores Horários para Postar</h6>
                                <p>Conforme nossa análise de dados, os horários com maior engajamento são entre 19h e 21h nos dias de semana, e entre 10h e 14h nos finais de semana.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="tip-card">
                                <h6><i class="fas fa-rocket text-success me-2"></i> Diversifique o Conteúdo</h6>
                                <p>Utilize regras de rotação de conteúdo para manter suas postagens variadas. Alternando entre 3-4 campanhas diferentes gera melhores resultados do que focar em uma única.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="tip-card">
                                <h6><i class="fas fa-chart-line text-danger me-2"></i> Monitoramento Contínuo</h6>
                                <p>Configure regras de engajamento para substituir automaticamente campanhas com baixo desempenho. Sugerimos um limite mínimo de 5 interações por postagem.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Regra -->
<div class="modal fade" id="modalNovaRegra" tabindex="-1" aria-labelledby="modalNovaRegraLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovaRegraLabel">Nova Regra de Automação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="formNovaRegra" method="POST" action="regras-automacao.php">
                    <input type="hidden" name="salvar_regra" value="1">
                    
                    <!-- Informações Gerais -->
                    <div class="mb-4">
                        <h6 class="form-section-title">Informações Gerais</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nome" class="form-label">Nome da Regra <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nome" name="nome" required>
                            </div>
                            <div class="col-md-6">
                                <label for="tipo" class="form-label">Tipo de Regra <span class="text-danger">*</span></label>
                                <select class="form-select" id="tipo" name="tipo" required>
                                    <option value="horario">Baseada em Horário</option>
                                    <option value="engajamento">Baseada em Engajamento</option>
                                    <option value="rotacao">Rotação de Conteúdo</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="descricao" class="form-label">Descrição</label>
                                <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
                                <small class="form-text text-muted">Uma breve descrição do objetivo desta regra (opcional)</small>
                            </div>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="ativa" name="ativa" checked>
                            <label class="form-check-label" for="ativa">Regra ativa</label>
                        </div>
                    </div>
                    
                    <!-- Condições da Regra (baseada em horário) -->
                    <div id="condicoes-horario" class="regra-condicoes">
                        <h6 class="form-section-title">Condições de Horário</h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Dias da Semana <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap">
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" id="dia-sun" value="sun">
                                    <label class="form-check-label" for="dia-sun">Domingo</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" id="dia-mon" value="mon">
                                    <label class="form-check-label" for="dia-mon">Segunda</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" id="dia-tue" value="tue">
                                    <label class="form-check-label" for="dia-tue">Terça</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" id="dia-wed" value="wed">
                                    <label class="form-check-label" for="dia-wed">Quarta</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" id="dia-thu" value="thu">
                                    <label class="form-check-label" for="dia-thu">Quinta</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" id="dia-fri" value="fri">
                                    <label class="form-check-label" for="dia-fri">Sexta</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" name="dias_semana[]" id="dia-sat" value="sat">
                                    <label class="form-check-label" for="dia-sat">Sábado</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="hora_inicio" class="form-label">Horário de Início <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" required>
                            </div>
                            <div class="col-md-6">
                                <label for="hora_fim" class="form-label">Horário de Fim <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="hora_fim" name="hora_fim" required>
                            </div>
                        </div>
                        
                        <h6 class="form-section-title">Ações</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="campanha_id" class="form-label">Campanha <span class="text-danger">*</span></label>
                                <select class="form-select" id="campanha_id" name="campanha_id" required>
                                    <option value="">Selecione uma campanha</option>
                                    <?php
                                    $campanhas->data_seek(0);
                                    while ($campanha = $campanhas->fetch_assoc()) {
                                        echo '<option value="' . $campanha['id'] . '">' . htmlspecialchars($campanha['nome']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Grupos para Postagem <span class="text-danger">*</span></label>
                            <div class="select-grupos">
                                <?php
                                $grupos->data_seek(0);
                                while ($grupo = $grupos->fetch_assoc()) {
                                    echo '<div class="form-check mb-2">';
                                    echo '<input class="form-check-input" type="checkbox" name="grupos[]" id="grupo-' . $grupo['id'] . '" value="' . $grupo['id'] . '">';
                                    echo '<label class="form-check-label" for="grupo-' . $grupo['id'] . '">' . htmlspecialchars($grupo['nome']) . '</label>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="max_posts" class="form-label">Máximo de Posts</label>
                                <input type="number" class="form-control" id="max_posts" name="max_posts" value="1" min="1" max="10">
                                <small class="form-text text-muted">Máximo de postagens dentro do período</small>
                            </div>
                            <div class="col-md-6">
                                <label for="intervalo_minutos" class="form-label">Intervalo Mínimo (min)</label>
                                <input type="number" class="form-control" id="intervalo_minutos" name="intervalo_minutos" value="30" min="5">
                                <small class="form-text text-muted">Intervalo mínimo entre postagens</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Condições da Regra (baseada em engajamento) -->
                    <div id="condicoes-engajamento" class="regra-condicoes d-none">
                        <h6 class="form-section-title">Condições de Engajamento</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="metricas" class="form-label">Métrica <span class="text-danger">*</span></label>
                                <select class="form-select" id="metricas" name="metricas" required>
                                    <option value="curtidas">Curtidas</option>
                                    <option value="comentarios">Comentários</option>
                                    <option value="compartilhamentos">Compartilhamentos</option>
                                    <option value="engajamento_total">Engajamento Total</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="operador" class="form-label">Operador <span class="text-danger">*</span></label>
                                <select class="form-select" id="operador" name="operador" required>
                                    <option value="maior">Maior que (>)</option>
                                    <option value="menor">Menor que (<)</option>
                                    <option value="igual">Igual a (=)</option>
                                    <option value="maior_igual">Maior ou igual a (≥)</option>
                                    <option value="menor_igual">Menor ou igual a (≤)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="valor_limite" class="form-label">Valor Limite <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="valor_limite" name="valor_limite" min="0" value="5" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="periodo_dias" class="form-label">Período de Análise (dias)</label>
                                <select class="form-select" id="periodo_dias" name="periodo_dias">
                                    <option value="1">Último dia</option>
                                    <option value="3">Últimos 3 dias</option>
                                    <option value="7" selected>Últimos 7 dias</option>
                                    <option value="14">Últimos 14 dias</option>
                                    <option value="30">Últimos 30 dias</option>
                                </select>
                            </div>
                        </div>
                        
                        <h6 class="form-section-title">Ações</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="acao_tipo" class="form-label">Tipo de Ação <span class="text-danger">*</span></label>
                                <select class="form-select" id="acao_tipo" name="acao_tipo" required>
                                    <option value="trocar_campanha">Trocar de campanha</option>
                                    <option value="adicionar_grupos">Adicionar grupos de postagem</option>
                                    <option value="pausar_postagens">Pausar postagens</option>
                                    <option value="aumentar_frequencia">Aumentar frequência de postagem</option>
                                    <option value="diminuir_frequencia">Diminuir frequência de postagem</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="acao-condicional" id="acao-trocar_campanha">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="acao_campanha_id" class="form-label">Trocar para Campanha <span class="text-danger">*</span></label>
                                    <select class="form-select" id="acao_campanha_id" name="acao_campanha_id">
                                        <option value="">Selecione uma campanha</option>
                                        <?php
                                        $campanhas->data_seek(0);
                                        while ($campanha = $campanhas->fetch_assoc()) {
                                            echo '<option value="' . $campanha['id'] . '">' . htmlspecialchars($campanha['nome']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="acao-condicional d-none" id="acao-adicionar_grupos">
                            <div class="mb-3">
                                <label class="form-label">Adicionar Grupos <span class="text-danger">*</span></label>
                                <div class="select-grupos">
                                    <?php
                                    $grupos->data_seek(0);
                                    while ($grupo = $grupos->fetch_assoc()) {
                                        echo '<div class="form-check mb-2">';
                                        echo '<input class="form-check-input" type="checkbox" name="acao_grupos[]" id="acao-grupo-' . $grupo['id'] . '" value="' . $grupo['id'] . '">';
                                        echo '<label class="form-check-label" for="acao-grupo-' . $grupo['id'] . '">' . htmlspecialchars($grupo['nome']) . '</label>';
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Condições da Regra (rotação de conteúdo) -->
                    <div id="condicoes-rotacao" class="regra-condicoes d-none">
                        <h6 class="form-section-title">Configuração de Rotação</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rotacao_frequencia" class="form-label">Frequência de Rotação <span class="text-danger">*</span></label>
                                <select class="form-select" id="rotacao_frequencia" name="rotacao_frequencia" required>
                                    <option value="diaria">Diária (alterna a cada dia)</option>
                                    <option value="semanal">Semanal (alterna a cada semana)</option>
                                    <option value="mensal">Mensal (alterna a cada mês)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Campanhas para Rotação <span class="text-danger">*</span></label>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Selecione pelo menos duas campanhas para alternar.
                            </div>
                            <div class="select-campanhas">
                                <?php
                                $campanhas->data_seek(0);
                                while ($campanha = $campanhas->fetch_assoc()) {
                                    echo '<div class="form-check mb-2">';
                                    echo '<input class="form-check-input" type="checkbox" name="campanhas_rotacao[]" id="campanha-rotacao-' . $campanha['id'] . '" value="' . $campanha['id'] . '">';
                                    echo '<label class="form-check-label" for="campanha-rotacao-' . $campanha['id'] . '">' . htmlspecialchars($campanha['nome']) . '</label>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <h6 class="form-section-title">Configuração de Postagem</h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Grupos para Postagem <span class="text-danger">*</span></label>
                            <div class="select-grupos">
                                <?php
                                $grupos->data_seek(0);
                                while ($grupo = $grupos->fetch_assoc()) {
                                    echo '<div class="form-check mb-2">';
                                    echo '<input class="form-check-input" type="checkbox" name="rotacao_grupos[]" id="rotacao-grupo-' . $grupo['id'] . '" value="' . $grupo['id'] . '">';
                                    echo '<label class="form-check-label" for="rotacao-grupo-' . $grupo['id'] . '">' . htmlspecialchars($grupo['nome']) . '</label>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rotacao_posts_por_dia" class="form-label">Posts por Dia</label>
                                <input type="number" class="form-control" id="rotacao_posts_por_dia" name="rotacao_posts_por_dia" value="1" min="1" max="10">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Horários Preferidos (opcional)</label>
                            <div class="row">
                                <?php
                                $horarios = [
                                    '08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'
                                ];
                                
                                foreach ($horarios as $horario) {
                                    echo '<div class="col-md-3 col-6 mb-2">';
                                    echo '<div class="form-check">';
                                    echo '<input class="form-check-input" type="checkbox" name="rotacao_horarios[]" id="horario-' . str_replace(':', '', $horario) . '" value="' . $horario . '">';
                                    echo '<label class="form-check-label" for="horario-' . str_replace(':', '', $horario) . '">' . $horario . '</label>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            <small class="form-text text-muted">Se não selecionar, os posts serão distribuídos ao longo do dia</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarRegra">Salvar Regra</button>
            </div>
        </div>
    </div>
</div>

<!-- CSS Personalizado -->
<style>
.form-section-title {
    padding-bottom: 8px;
    margin-bottom: 16px;
    border-bottom: 1px solid #e9ecef;
    font-weight: 600;
}

.icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.select-grupos {
    max-height: 200px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
}

.select-campanhas {
    max-height: 200px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
}

.tip-card {
    padding: 15px;
    border-left: 4px solid #007bff;
    background-color: #f8f9fa;
    margin-bottom: 15px;
    border-radius: 0 6px 6px 0;
}

.tip-card h6 {
    font-weight: 600;
    margin-bottom: 8px;
}

.tip-card p {
    color: #6c757d;
    margin-bottom: 0;
    font-size: 0.9rem;
}

.empty-state-icon {
    font-size: 3.5rem;
    color: #dee2e6;
    margin-bottom: 1rem;
}
</style>

<!-- JavaScript personalizado -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar modal ao clicar em "Nova Regra"
    const modalNovaRegra = new bootstrap.Modal(document.getElementById('modalNovaRegra'));
    
    // Botões para criar nova regra com tipo específico
    const btnNovaRegra = document.querySelectorAll('.nova-regra');
    btnNovaRegra.forEach(btn => {
        btn.addEventListener('click', function() {
            const tipo = this.dataset.tipo;
            document.getElementById('tipo').value = tipo;
            atualizarFormulario(tipo);
            modalNovaRegra.show();
        });
    });
    
    // Atualizar formulário com base no tipo de regra
    const selectTipo = document.getElementById('tipo');
    selectTipo.addEventListener('change', function() {
        atualizarFormulario(this.value);
    });
    
    // Função para atualizar o formulário com base no tipo
    function atualizarFormulario(tipo) {
        const condicoes = document.querySelectorAll('.regra-condicoes');
        condicoes.forEach(cond => {
            cond.classList.add('d-none');
        });
        
        document.getElementById('condicoes-' + tipo).classList.remove('d-none');
    }
    
    // Alternar entre ações condicionais para regras de engajamento
    const selectAcaoTipo = document.getElementById('acao_tipo');
    if (selectAcaoTipo) {
        selectAcaoTipo.addEventListener('change', function() {
            const acoesCondicionais = document.querySelectorAll('.acao-condicional');
            acoesCondicionais.forEach(acao => {
                acao.classList.add('d-none');
            });
            
            const acaoAtual = document.getElementById('acao-' + this.value);
            if (acaoAtual) {
                acaoAtual.classList.remove('d-none');
            }
        });
    }
    
    // Validar e submeter o formulário
    document.getElementById('btnSalvarRegra').addEventListener('click', function() {
        const form = document.getElementById('formNovaRegra');
        
        // Validação customizada para campos específicos
        const tipo = document.getElementById('tipo').value;
        let valido = true;
        
        // Validar campos específicos por tipo de regra
        if (tipo === 'horario') {
            const diasSemana = document.querySelectorAll('input[name="dias_semana[]"]:checked');
            if (diasSemana.length === 0) {
                alert('Selecione pelo menos um dia da semana');
                valido = false;
            }
            
            const grupos = document.querySelectorAll('input[name="grupos[]"]:checked');
            if (grupos.length === 0) {
                alert('Selecione pelo menos um grupo para postagem');
                valido = false;
            }
        } else if (tipo === 'engajamento') {
            const acaoTipo = document.getElementById('acao_tipo').value;
            
            if (acaoTipo === 'trocar_campanha') {
                const acaoCampanhaId = document.getElementById('acao_campanha_id').value;
                if (!acaoCampanhaId) {
                    alert('Selecione uma campanha para trocar');
                    valido = false;
                }
            } else if (acaoTipo === 'adicionar_grupos') {
                const acaoGrupos = document.querySelectorAll('input[name="acao_grupos[]"]:checked');
                if (acaoGrupos.length === 0) {
                    alert('Selecione pelo menos um grupo para adicionar');
                    valido = false;
                }
            }
        } else if (tipo === 'rotacao') {
            const campanhasRotacao = document.querySelectorAll('input[name="campanhas_rotacao[]"]:checked');
            if (campanhasRotacao.length < 2) {
                alert('Selecione pelo menos duas campanhas para rotação');
                valido = false;
            }
            
            const rotacaoGrupos = document.querySelectorAll('input[name="rotacao_grupos[]"]:checked');
            if (rotacaoGrupos.length === 0) {
                alert('Selecione pelo menos um grupo para postagem');
                valido = false;
            }
        }
        
        if (valido) {
            form.submit();
        }
    });
});
</script>

<?php
// Incluir o rodapé
include 'includes/footer.php';
?>