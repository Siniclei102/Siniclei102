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

// Processar formulário de agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agendar'])) {
    $campanha_id = isset($_POST['campanha_id']) ? intval($_POST['campanha_id']) : 0;
    $grupos = isset($_POST['grupos']) ? $_POST['grupos'] : [];
    $data = isset($_POST['data']) ? $_POST['data'] : '';
    $hora = isset($_POST['hora']) ? $_POST['hora'] : '';
    $repetir = isset($_POST['repetir']) ? $_POST['repetir'] : 'nao';
    $dias_repeticao = isset($_POST['dias_repeticao']) ? intval($_POST['dias_repeticao']) : 0;
    
    $erros = [];
    
    // Validar campos
    if ($campanha_id <= 0) {
        $erros[] = "Selecione uma campanha válida.";
    } else {
        // Verificar se a campanha pertence ao usuário
        $queryCampanha = "SELECT id FROM campanhas WHERE id = ? AND usuario_id = ?";
        $stmtCampanha = $db->prepare($queryCampanha);
        $stmtCampanha->bind_param("ii", $campanha_id, $userId);
        $stmtCampanha->execute();
        if ($stmtCampanha->get_result()->num_rows == 0) {
            $erros[] = "Campanha inválida.";
        }
    }
    
    if (empty($grupos)) {
        $erros[] = "Selecione pelo menos um grupo.";
    } else {
        // Verificar se os grupos pertencem ao usuário
        $gruposString = implode(',', array_map('intval', $grupos));
        $queryGrupos = "SELECT COUNT(*) as total FROM grupos_facebook WHERE id IN ($gruposString) AND usuario_id = ?";
        $stmtGrupos = $db->prepare($queryGrupos);
        $stmtGrupos->bind_param("i", $userId);
        $stmtGrupos->execute();
        $resultGrupos = $stmtGrupos->get_result()->fetch_assoc();
        
        if ($resultGrupos['total'] != count($grupos)) {
            $erros[] = "Um ou mais grupos selecionados são inválidos.";
        }
    }
    
    if (empty($data)) {
        $erros[] = "Selecione uma data.";
    } else {
        // Validar formato da data
        $dataObj = DateTime::createFromFormat('Y-m-d', $data);
        if (!$dataObj || $dataObj->format('Y-m-d') !== $data) {
            $erros[] = "Formato de data inválido.";
        } else {
            // Verificar se a data é futura
            $hoje = new DateTime('today');
            if ($dataObj < $hoje) {
                $erros[] = "A data de agendamento deve ser futura.";
            }
        }
    }
    
    if (empty($hora)) {
        $erros[] = "Selecione um horário.";
    } else {
        // Validar formato da hora
        $horaObj = DateTime::createFromFormat('H:i', $hora);
        if (!$horaObj || $horaObj->format('H:i') !== $hora) {
            $erros[] = "Formato de horário inválido.";
        }
    }
    
    // Verificar repetição
    if ($repetir === 'sim' && $dias_repeticao <= 0) {
        $erros[] = "Informe um intervalo de repetição válido.";
    }
    
    // Se não houver erros, salvar agendamento
    if (empty($erros)) {
        // Montar data e hora completa
        $data_hora = $data . ' ' . $hora . ':00';
        
        // Inserir agendamento para cada grupo selecionado
        $sucesso = true;
        $agendamentos_criados = 0;
        
        foreach ($grupos as $grupo_id) {
            // Verificar se já existe um agendamento próximo para evitar duplicações
            $queryVerificar = "
                SELECT COUNT(*) as total 
                FROM agendamentos 
                WHERE usuario_id = ? 
                AND campanha_id = ? 
                AND grupo_id = ? 
                AND data_agendada BETWEEN DATE_SUB(?, INTERVAL 5 MINUTE) AND DATE_ADD(?, INTERVAL 5 MINUTE)
                AND status IN ('agendado', 'pendente')
            ";
            $stmtVerificar = $db->prepare($queryVerificar);
            $stmtVerificar->bind_param("iiiss", $userId, $campanha_id, $grupo_id, $data_hora, $data_hora);
            $stmtVerificar->execute();
            $resultVerificar = $stmtVerificar->get_result()->fetch_assoc();
            
            if ($resultVerificar['total'] > 0) {
                continue; // Pular, já existe agendamento similar
            }
            
            // Inserir agendamento
            $queryAgendar = "
                INSERT INTO agendamentos (
                    usuario_id, 
                    campanha_id, 
                    grupo_id, 
                    data_agendada, 
                    repetir, 
                    dias_repeticao, 
                    status, 
                    criado_em
                ) VALUES (?, ?, ?, ?, ?, ?, 'agendado', NOW())
            ";
            $stmtAgendar = $db->prepare($queryAgendar);
            $repetirFlag = ($repetir === 'sim') ? 1 : 0;
            $stmtAgendar->bind_param("iiisii", $userId, $campanha_id, $grupo_id, $data_hora, $repetirFlag, $dias_repeticao);
            
            if ($stmtAgendar->execute()) {
                $agendamentos_criados++;
            } else {
                $sucesso = false;
            }
        }
        
        if ($sucesso && $agendamentos_criados > 0) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => "Agendamento criado com sucesso! {$agendamentos_criados} postagens foram agendadas."
            ];
            
            header('Location: agendamento.php');
            exit;
        } else if ($agendamentos_criados === 0) {
            $_SESSION['alert'] = [
                'type' => 'warning',
                'message' => "Não foi possível criar novos agendamentos. Verifique se já existem agendamentos similares."
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => "Ocorreu um erro ao criar alguns agendamentos. Apenas {$agendamentos_criados} foram criados."
            ];
        }
    } else {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => "Erros no formulário: " . implode(" ", $erros)
        ];
    }
}

// Processar exclusão de agendamento
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $agendamento_id = intval($_GET['excluir']);
    
    // Verificar se o agendamento pertence ao usuário
    $queryVerificarExclusao = "SELECT id FROM agendamentos WHERE id = ? AND usuario_id = ?";
    $stmtVerificarExclusao = $db->prepare($queryVerificarExclusao);
    $stmtVerificarExclusao->bind_param("ii", $agendamento_id, $userId);
    $stmtVerificarExclusao->execute();
    
    if ($stmtVerificarExclusao->get_result()->num_rows > 0) {
        $queryExcluir = "DELETE FROM agendamentos WHERE id = ?";
        $stmtExcluir = $db->prepare($queryExcluir);
        $stmtExcluir->bind_param("i", $agendamento_id);
        
        if ($stmtExcluir->execute()) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => "Agendamento excluído com sucesso!"
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => "Erro ao excluir agendamento."
            ];
        }
    } else {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => "Agendamento não encontrado ou não pertence a você."
        ];
    }
    
    header('Location: agendamento.php');
    exit;
}

// Processar suspensão ou reativação de agendamento
if (isset($_GET['suspender']) && is_numeric($_GET['suspender'])) {
    $agendamento_id = intval($_GET['suspender']);
    
    // Verificar se o agendamento pertence ao usuário
    $queryVerificarSuspender = "SELECT id, status FROM agendamentos WHERE id = ? AND usuario_id = ?";
    $stmtVerificarSuspender = $db->prepare($queryVerificarSuspender);
    $stmtVerificarSuspender->bind_param("ii", $agendamento_id, $userId);
    $stmtVerificarSuspender->execute();
    $resultSuspender = $stmtVerificarSuspender->get_result();
    
    if ($resultSuspender->num_rows > 0) {
        $agendamento = $resultSuspender->fetch_assoc();
        $novo_status = ($agendamento['status'] === 'agendado') ? 'suspenso' : 'agendado';
        
        $queryAtualizar = "UPDATE agendamentos SET status = ? WHERE id = ?";
        $stmtAtualizar = $db->prepare($queryAtualizar);
        $stmtAtualizar->bind_param("si", $novo_status, $agendamento_id);
        
        if ($stmtAtualizar->execute()) {
            $mensagem = ($novo_status === 'suspenso') ? "Agendamento suspenso com sucesso!" : "Agendamento reativado com sucesso!";
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => $mensagem
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => "Erro ao atualizar status do agendamento."
            ];
        }
    } else {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => "Agendamento não encontrado ou não pertence a você."
        ];
    }
    
    header('Location: agendamento.php');
    exit;
}

// Obter campanhas do usuário
$query = "SELECT id, nome FROM campanhas WHERE usuario_id = ? AND ativa = 1 ORDER BY nome";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$campanhas = $stmt->get_result();

// Obter grupos do usuário
$query = "SELECT id, nome FROM grupos_facebook WHERE usuario_id = ? AND ativo = 1 ORDER BY nome";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$grupos = $stmt->get_result();

// Obter agendamentos
$query = "
    SELECT 
        a.id,
        a.campanha_id,
        a.grupo_id,
        a.data_agendada,
        a.repetir,
        a.dias_repeticao,
        a.status,
        a.ultima_execucao,
        a.criado_em,
        c.nome as campanha_nome,
        g.nome as grupo_nome
    FROM 
        agendamentos a
        JOIN campanhas c ON a.campanha_id = c.id
        JOIN grupos_facebook g ON a.grupo_id = g.id
    WHERE 
        a.usuario_id = ?
    ORDER BY 
        a.data_agendada ASC
";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$agendamentos = $stmt->get_result();

// Obter estatísticas de agendamento
$queryEstatisticas = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'agendado' THEN 1 ELSE 0 END) as agendados,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos,
        SUM(CASE WHEN status = 'suspenso' THEN 1 ELSE 0 END) as suspensos,
        SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros,
        COUNT(DISTINCT campanha_id) as total_campanhas,
        COUNT(DISTINCT grupo_id) as total_grupos
    FROM 
        agendamentos
    WHERE 
        usuario_id = ?
";
$stmtEstatisticas = $db->prepare($queryEstatisticas);
$stmtEstatisticas->bind_param("i", $userId);
$stmtEstatisticas->execute();
$estatisticas = $stmtEstatisticas->get_result()->fetch_assoc();

// Incluir o cabeçalho
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0 text-gray-800">Agendamento de Postagens</h1>
            <p class="mb-0 text-muted">Programe suas postagens para datas e horários específicos</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoAgendamento">
                <i class="fas fa-calendar-plus me-1"></i> Novo Agendamento
            </button>
        </div>
    </div>
    
    <?php if (isset($_SESSION['alert'])): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-<?php echo $_SESSION['alert']['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['alert']['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>
    
    <!-- Cards de Estatísticas -->
    <div class="row mb-4">
        <!-- Total de Agendamentos -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-primary-light text-primary">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="report-stat-value"><?php echo number_format($estatisticas['total']); ?></div>
                        <div class="report-stat-title">Total de Agendamentos</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Agendamentos Pendentes -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-success-light text-success">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="report-stat-value"><?php echo number_format($estatisticas['agendados']); ?></div>
                        <div class="report-stat-title">Agendamentos Pendentes</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Campanhas -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-info-light text-info">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="report-stat-value"><?php echo number_format($estatisticas['total_campanhas']); ?></div>
                        <div class="report-stat-title">Campanhas Agendadas</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Grupos -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="report-stat-card">
                        <div class="report-stat-icon bg-warning-light text-warning">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="report-stat-value"><?php echo number_format($estatisticas['total_grupos']); ?></div>
                        <div class="report-stat-title">Grupos Alcançados</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabela de Agendamentos -->
    <div class="row">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-calendar me-2 text-primary"></i> Agendamentos
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if ($agendamentos->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Campanha</th>
                                        <th>Grupo</th>
                                        <th>Data/Hora</th>
                                        <th>Repetição</th>
                                        <th>Status</th>
                                        <th>Última Execução</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($agendamento = $agendamentos->fetch_assoc()): ?>
                                        <?php
                                        $statusClass = 'primary';
                                        $statusText = 'Agendado';
                                        
                                        switch ($agendamento['status']) {
                                            case 'concluido':
                                                $statusClass = 'success';
                                                $statusText = 'Concluído';
                                                break;
                                            case 'suspenso':
                                                $statusClass = 'warning';
                                                $statusText = 'Suspenso';
                                                break;
                                            case 'erro':
                                                $statusClass = 'danger';
                                                $statusText = 'Erro';
                                                break;
                                        }
                                        
                                        $dataAgendada = new DateTime($agendamento['data_agendada']);
                                        $agora = new DateTime();
                                        $passado = $dataAgendada < $agora && $agendamento['status'] === 'agendado';
                                        ?>
                                        <tr <?php echo $passado ? 'class="table-warning"' : ''; ?>>
                                            <td>
                                                <a href="campanhas.php?editar=<?php echo $agendamento['campanha_id']; ?>">
                                                    <?php echo htmlspecialchars($agendamento['campanha_nome']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <a href="grupos.php?editar=<?php echo $agendamento['grupo_id']; ?>">
                                                    <?php echo htmlspecialchars($agendamento['grupo_nome']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php echo date('d/m/Y H:i', strtotime($agendamento['data_agendada'])); ?>
                                                <?php if ($passado): ?>
                                                    <span class="badge bg-warning">Atrasado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($agendamento['repetir']): ?>
                                                    A cada <?php echo $agendamento['dias_repeticao']; ?> dias
                                                <?php else: ?>
                                                    Não repete
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($agendamento['ultima_execucao']): ?>
                                                    <?php echo date('d/m/Y H:i', strtotime($agendamento['ultima_execucao'])); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($agendamento['status'] === 'agendado' || $agendamento['status'] === 'suspenso'): ?>
                                                    <?php if ($agendamento['status'] === 'agendado'): ?>
                                                        <a href="?suspender=<?php echo $agendamento['id']; ?>" class="btn btn-sm btn-warning me-1" title="Suspender">
                                                            <i class="fas fa-pause"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="?suspender=<?php echo $agendamento['id']; ?>" class="btn btn-sm btn-success me-1" title="Reativar">
                                                            <i class="fas fa-play"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="?excluir=<?php echo $agendamento['id']; ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este agendamento?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Não disponível</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h5>Nenhum agendamento encontrado</h5>
                            <p class="text-muted">Você ainda não tem postagens agendadas. Clique no botão "Novo Agendamento" para criar uma.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Agendamento -->
<div class="modal fade" id="modalNovoAgendamento" tabindex="-1" aria-labelledby="modalNovoAgendamentoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNovoAgendamentoLabel">Novo Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="campanha_id" class="form-label">Campanha</label>
                            <select class="form-select" id="campanha_id" name="campanha_id" required>
                                <option value="">Selecione uma campanha</option>
                                <?php while ($campanha = $campanhas->fetch_assoc()): ?>
                                    <option value="<?php echo $campanha['id']; ?>">
                                        <?php echo htmlspecialchars($campanha['nome']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-text">
                                Selecione a campanha de onde serão extraídos os anúncios para postagem.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Grupos</label>
                            <div class="grupos-container bg-light p-3 rounded">
                                <?php if ($grupos->num_rows > 0): ?>
                                    <?php $grupos->data_seek(0); ?>
                                    <?php while ($grupo = $grupos->fetch_assoc()): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="grupos[]" value="<?php echo $grupo['id']; ?>" id="grupo_<?php echo $grupo['id']; ?>">
                                            <label class="form-check-label" for="grupo_<?php echo $grupo['id']; ?>">
                                                <?php echo htmlspecialchars($grupo['nome']); ?>
                                            </label>
                                        </div>
                                    <?php endwhile; ?>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnSelecionarTodos">Selecionar Todos</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimparSelecao">Limpar Seleção</button>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        Nenhum grupo disponível. <a href="grupos.php">Adicione um grupo</a> primeiro.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="data" class="form-label">Data</label>
                            <input type="date" class="form-control" id="data" name="data" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="hora" class="form-label">Hora</label>
                            <input type="time" class="form-control" id="hora" name="hora" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Repetir Agendamento</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="repetir" id="repetir_nao" value="nao" checked>
                                <label class="form-check-label" for="repetir_nao">
                                    Não repetir
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="repetir" id="repetir_sim" value="sim">
                                <label class="form-check-label" for="repetir_sim">
                                    Repetir automaticamente
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-6" id="dias_repeticao_container" style="display: none;">
                            <label for="dias_repeticao" class="form-label">Repetir a cada</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="dias_repeticao" name="dias_repeticao" min="1" value="7">
                                <span class="input-group-text">dias</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i> As postagens serão realizadas automaticamente nos horários agendados, desde que o sistema de CRON esteja configurado corretamente.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="agendar" class="btn btn-primary">Agendar Postagem</button>
                </div>
            </form>
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

/* Container de grupos */
.grupos-container {
    max-height: 200px;
    overflow-y: auto;
}

/* Empty state */
.empty-state-icon {
    font-size: 3rem;
    color: #adb5bd;
}
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gerenciar exibição do campo de repetição
    const repetirSim = document.getElementById('repetir_sim');
    const repetirNao = document.getElementById('repetir_nao');
    const diasRepeticaoContainer = document.getElementById('dias_repeticao_container');
    
    repetirSim.addEventListener('change', function() {
        if (this.checked) {
            diasRepeticaoContainer.style.display = 'block';
        }
    });
    
    repetirNao.addEventListener('change', function() {
        if (this.checked) {
            diasRepeticaoContainer.style.display = 'none';
        }
    });
    
    // Botões de seleção de grupos
    const btnSelecionarTodos = document.getElementById('btnSelecionarTodos');
    const btnLimparSelecao = document.getElementById('btnLimparSelecao');
    
    btnSelecionarTodos.addEventListener('click', function() {
        document.querySelectorAll('input[name="grupos[]"]').forEach(checkbox => {
            checkbox.checked = true;
        });
    });
    
    btnLimparSelecao.addEventListener('click', function() {
        document.querySelectorAll('input[name="grupos[]"]').forEach(checkbox => {
            checkbox.checked = false;
        });
    });
    
    // Definir data mínima como hoje
    const inputData = document.getElementById('data');
    const hoje = new Date().toISOString().split('T')[0];
    inputData.setAttribute('min', hoje);
    
    // Definir horário atual como padrão
    const inputHora = document.getElementById('hora');
    const agora = new Date();
    const horaAtual = agora.getHours().toString().padStart(2, '0');
    const minutoAtual = agora.getMinutes().toString().padStart(2, '0');
    inputHora.value = `${horaAtual}:${minutoAtual}`;
});
</script>

<?php
// Incluir o rodapé
include 'includes/footer.php';
?>