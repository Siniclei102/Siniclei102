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

// Mensagens de feedback
$messages = [];

// Verificar se há anúncio pré-selecionado
$selectedAnuncioId = isset($_GET['anuncio']) && is_numeric($_GET['anuncio']) ? intval($_GET['anuncio']) : null;

// Buscar dados para edição se necessário
$campanhaPara = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $campanhaId = intval($_GET['edit']);
    
    $queryCampanha = "SELECT * FROM campanhas WHERE id = ? AND usuario_id = ?";
    $stmtCampanha = $db->prepare($queryCampanha);
    $stmtCampanha->bind_param("ii", $campanhaId, $userId);
    $stmtCampanha->execute();
    $resultCampanha = $stmtCampanha->get_result();
    
    if ($resultCampanha->num_rows > 0) {
        $campanhaPara = $resultCampanha->fetch_assoc();
        $selectedAnuncioId = $campanhaPara['anuncio_id'];
        
        // Buscar grupos selecionados para esta campanha
        $queryGrupos = "SELECT grupo_id FROM campanha_grupos WHERE campanha_id = ?";
        $stmtGrupos = $db->prepare($queryGrupos);
        $stmtGrupos->bind_param("i", $campanhaId);
        $stmtGrupos->execute();
        $resultGrupos = $stmtGrupos->get_result();
        
        $gruposSelecionados = [];
        while ($row = $resultGrupos->fetch_assoc()) {
            $gruposSelecionados[] = $row['grupo_id'];
        }
        
        $campanhaPara['grupos'] = $gruposSelecionados;
    }
}

// Processar a criação/edição de campanha
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campanhaId = isset($_POST['campanha_id']) ? intval($_POST['campanha_id']) : null;
    $nome = $db->real_escape_string($_POST['nome']);
    $anuncioId = intval($_POST['anuncio_id']);
    $intervaloMinutos = intval($_POST['intervalo_minutos']);
    $repeticaoHoras = empty($_POST['repeticao_horas']) ? null : intval($_POST['repeticao_horas']);
    $grupos = isset($_POST['grupos']) ? $_POST['grupos'] : [];
    
    // Validações
    $errors = [];
    
    if (empty($nome)) {
        $errors[] = "O nome da campanha é obrigatório.";
    }
    
    if ($anuncioId <= 0) {
        $errors[] = "Você deve selecionar um anúncio.";
    } else {
        // Verificar se o anúncio pertence ao usuário
        $queryCheckAnuncio = "SELECT id FROM anuncios WHERE id = ? AND usuario_id = ?";
        $stmtCheckAnuncio = $db->prepare($queryCheckAnuncio);
        $stmtCheckAnuncio->bind_param("ii", $anuncioId, $userId);
        $stmtCheckAnuncio->execute();
        $resultCheckAnuncio = $stmtCheckAnuncio->get_result();
        
        if ($resultCheckAnuncio->num_rows === 0) {
            $errors[] = "O anúncio selecionado é inválido.";
        }
    }
    
    if ($intervaloMinutos < 2) {
        $errors[] = "O intervalo mínimo entre postagens deve ser de pelo menos 2 minutos.";
    }
    
    if (empty($grupos)) {
        $errors[] = "Você deve selecionar pelo menos um grupo.";
    } else {
        // Verificar se os grupos pertencem ao usuário
        $gruposStr = implode(',', array_map('intval', $grupos));
        $queryCheckGrupos = "SELECT COUNT(*) as total FROM grupos_facebook WHERE id IN ({$gruposStr}) AND usuario_id = ?";
        $stmtCheckGrupos = $db->prepare($queryCheckGrupos);
        $stmtCheckGrupos->bind_param("i", $userId);
        $stmtCheckGrupos->execute();
        $resultCheckGrupos = $stmtCheckGrupos->get_result();
        $totalGruposValidos = $resultCheckGrupos->fetch_assoc()['total'];
        
        if ($totalGruposValidos !== count($grupos)) {
            $errors[] = "Um ou mais grupos selecionados são inválidos.";
        }
    }
    
    // Se não há erros, prosseguir com a criação/edição
    if (empty($errors)) {
        if ($campanhaId) {
            // Atualizar campanha existente
            $queryCampanha = "UPDATE campanhas SET nome = ?, anuncio_id = ?, intervalo_minutos = ?, repeticao_horas = ? WHERE id = ? AND usuario_id = ?";
            $stmtCampanha = $db->prepare($queryCampanha);
            $stmtCampanha->bind_param("siiiii", $nome, $anuncioId, $intervaloMinutos, $repeticaoHoras, $campanhaId, $userId);
            
            if ($stmtCampanha->execute()) {
                // Excluir grupos antigos e inserir os novos
                $queryDeleteGrupos = "DELETE FROM campanha_grupos WHERE campanha_id = ?";
                $stmtDeleteGrupos = $db->prepare($queryDeleteGrupos);
                $stmtDeleteGrupos->bind_param("i", $campanhaId);
                $stmtDeleteGrupos->execute();
                
                // Inserir novos grupos
                $success = true;
                foreach ($grupos as $grupoId) {
                    $queryInsertGrupo = "INSERT INTO campanha_grupos (campanha_id, grupo_id) VALUES (?, ?)";
                    $stmtInsertGrupo = $db->prepare($queryInsertGrupo);
                    $stmtInsertGrupo->bind_param("ii", $campanhaId, $grupoId);
                    
                    if (!$stmtInsertGrupo->execute()) {
                        $success = false;
                        break;
                    }
                }
                
                if ($success) {
                    $messages[] = [
                        'type' => 'success',
                        'text' => "Campanha atualizada com sucesso!"
                    ];
                    
                    // Limpar variáveis para nova campanha
                    $campanhaPara = null;
                    $selectedAnuncioId = null;
                } else {
                    $messages[] = [
                        'type' => 'danger',
                        'text' => "Erro ao atualizar grupos da campanha: " . $db->error
                    ];
                }
            } else {
                $messages[] = [
                    'type' => 'danger',
                    'text' => "Erro ao atualizar campanha: " . $db->error
                ];
            }
        } else {
            // Criar nova campanha
            $queryCampanha = "INSERT INTO campanhas (usuario_id, anuncio_id, nome, intervalo_minutos, repeticao_horas, ativa) VALUES (?, ?, ?, ?, ?, 1)";
            $stmtCampanha = $db->prepare($queryCampanha);
            $stmtCampanha->bind_param("iisii", $userId, $anuncioId, $nome, $intervaloMinutos, $repeticaoHoras);
            
            if ($stmtCampanha->execute()) {
                $newCampanhaId = $db->insert_id;
                
                // Inserir grupos selecionados
                $success = true;
                foreach ($grupos as $grupoId) {
                    $queryInsertGrupo = "INSERT INTO campanha_grupos (campanha_id, grupo_id) VALUES (?, ?)";
                    $stmtInsertGrupo = $db->prepare($queryInsertGrupo);
                    $stmtInsertGrupo->bind_param("ii", $newCampanhaId, $grupoId);
                    
                    if (!$stmtInsertGrupo->execute()) {
                        $success = false;
                        break;
                    }
                }
                
                if ($success) {
                    // Definir próxima execução
                    $queryUpdate = "UPDATE campanhas SET proxima_execucao = NOW() WHERE id = ?";
                    $stmtUpdate = $db->prepare($queryUpdate);
                    $stmtUpdate->bind_param("i", $newCampanhaId);
                    $stmtUpdate->execute();
                    
                    $messages[] = [
                        'type' => 'success',
                        'text' => "Campanha criada com sucesso! As postagens começarão em breve."
                    ];
                    
                    // Limpar seleção de anúncio
                    $selectedAnuncioId = null;
                } else {
                    $messages[] = [
                        'type' => 'danger',
                        'text' => "Erro ao adicionar grupos à campanha: " . $db->error
                    ];
                }
            } else {
                $messages[] = [
                    'type' => 'danger',
                    'text' => "Erro ao criar campanha: " . $db->error
                ];
            }
        }
    } else {
        // Exibir erros de validação
        foreach ($errors as $error) {
            $messages[] = [
                'type' => 'danger',
                'text' => $error
            ];
        }
    }
}

// Ativar/Desativar campanha
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $campanhaId = intval($_GET['toggle']);
    $ativa = isset($_GET['status']) ? ($_GET['status'] == '1' ? 1 : 0) : 1;
    
    $query = "UPDATE campanhas SET ativa = ? WHERE id = ? AND usuario_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("iii", $ativa, $campanhaId, $userId);
    
    if ($stmt->execute()) {
        // Se estiver ativando, definir próxima execução
        if ($ativa) {
            $queryUpdate = "UPDATE campanhas SET proxima_execucao = NOW() WHERE id = ?";
            $stmtUpdate = $db->prepare($queryUpdate);
            $stmtUpdate->bind_param("i", $campanhaId);
            $stmtUpdate->execute();
            
            // Resetar estado de postagem dos grupos
            $queryReset = "UPDATE campanha_grupos SET postado = 0 WHERE campanha_id = ?";
            $stmtReset = $db->prepare($queryReset);
            $stmtReset->bind_param("i", $campanhaId);
            $stmtReset->execute();
        }
        
        $messages[] = [
            'type' => 'success',
            'text' => $ativa ? "Campanha ativada com sucesso!" : "Campanha pausada com sucesso!"
        ];
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Erro ao atualizar campanha: " . $db->error
        ];
    }
}

// Excluir campanha
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $campanhaId = intval($_GET['delete']);
    
    $query = "DELETE FROM campanhas WHERE id = ? AND usuario_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $campanhaId, $userId);
    
    if ($stmt->execute()) {
        $messages[] = [
            'type' => 'success',
            'text' => "Campanha excluída com sucesso!"
        ];
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Erro ao excluir campanha: " . $db->error
        ];
    }
}

// Buscar anúncios do usuário
$queryAnuncios = "SELECT * FROM anuncios WHERE usuario_id = ? ORDER BY favorito DESC, criado_em DESC";
$stmtAnuncios = $db->prepare($queryAnuncios);
$stmtAnuncios->bind_param("i", $userId);
$stmtAnuncios->execute();
$resultAnuncios = $stmtAnuncios->get_result();

// Buscar grupos ativos do usuário
$queryGrupos = "SELECT * FROM grupos_facebook WHERE usuario_id = ? AND ativo = 1 ORDER BY favorito DESC, nome ASC";
$stmtGrupos = $db->prepare($queryGrupos);
$stmtGrupos->bind_param("i", $userId);
$stmtGrupos->execute();
$resultGrupos = $stmtGrupos->get_result();

// Buscar campanhas do usuário
$queryCampanhas = "SELECT c.*, a.titulo as anuncio_titulo, 
                  (SELECT COUNT(*) FROM campanha_grupos WHERE campanha_id = c.id) as total_grupos,
                  (SELECT COUNT(*) FROM campanha_grupos WHERE campanha_id = c.id AND postado = 1) as grupos_postados
                  FROM campanhas c 
                  JOIN anuncios a ON c.anuncio_id = a.id
                  WHERE c.usuario_id = ? 
                  ORDER BY c.criado_em DESC";
$stmtCampanhas = $db->prepare($queryCampanhas);
$stmtCampanhas->bind_param("i", $userId);
$stmtCampanhas->execute();
$resultCampanhas = $stmtCampanhas->get_result();

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
                        <i class="fas fa-bullhorn me-2 text-primary"></i> <?php echo $campanhaPara ? 'Editar Campanha' : 'Nova Campanha'; ?>
                    </h5>
                    <div>
                        <?php if ($campanhaPara): ?>
                            <a href="campanhas.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                                <i class="fas fa-plus me-1"></i> Nova Campanha
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modern-card-body">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message['text']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($resultAnuncios->num_rows === 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i> Você precisa criar pelo menos um anúncio antes de criar uma campanha.
                        </div>
                        <div class="text-center mt-3">
                            <a href="anuncios.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-ad me-2"></i> Criar Anúncio
                            </a>
                        </div>
                    <?php elseif ($resultGrupos->num_rows === 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i> Você precisa ter pelo menos um grupo ativo antes de criar uma campanha.
                        </div>
                        <div class="text-center mt-3">
                            <a href="grupos.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-users me-2"></i> Gerenciar Grupos
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Formulário de Campanha -->
                        <form method="POST" action="campanhas.php<?php echo $campanhaPara ? '?edit=' . $campanhaPara['id'] : ''; ?>">
                            <?php if ($campanhaPara): ?>
                                <input type="hidden" name="campanha_id" value="<?php echo $campanhaPara['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="section-title"><i class="fas fa-info-circle me-2"></i> Informações da Campanha</h5>
                                    
                                    <div class="mb-3">
                                        <label for="nome" class="form-label">Nome da Campanha</label>
                                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $campanhaPara ? htmlspecialchars($campanhaPara['nome']) : ''; ?>" required>
                                        <div class="form-text">Um nome para identificar sua campanha de postagens.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="anuncio_id" class="form-label">Anúncio a Publicar</label>
                                        <select class="form-select" id="anuncio_id" name="anuncio_id" required>
                                            <option value="">Selecione um anúncio</option>
                                            <?php while ($anuncio = $resultAnuncios->fetch_assoc()): ?>
                                                <option value="<?php echo $anuncio['id']; ?>" <?php echo ($selectedAnuncioId == $anuncio['id'] ? 'selected' : ''); ?>>
                                                    <?php echo $anuncio['favorito'] ? '⭐ ' : ''; ?>
                                                    <?php echo htmlspecialchars($anuncio['titulo']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="form-text">
                                            O anúncio que será publicado nos grupos selecionados.
                                            <a href="anuncios.php" target="_blank">Criar novo anúncio</a>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="intervalo_minutos" class="form-label">Intervalo Entre Postagens (minutos)</label>
                                        <input type="number" class="form-control" id="intervalo_minutos" name="intervalo_minutos" min="2" value="<?php echo $campanhaPara ? $campanhaPara['intervalo_minutos'] : '2'; ?>" required>
                                        <div class="form-text">Tempo de espera entre cada postagem. Mínimo de 2 minutos.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="repeticao_horas" class="form-label">Repetir Campanha a Cada (horas)</label>
                                        <input type="number" class="form-control" id="repeticao_horas" name="repeticao_horas" min="1" value="<?php echo $campanhaPara && $campanhaPara['repeticao_horas'] ? $campanhaPara['repeticao_horas'] : ''; ?>">
                                        <div class="form-text">Opcional: Deixe em branco para não repetir a campanha.</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5 class="section-title"><i class="fas fa-users me-2"></i> Grupos para Postagem</h5>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <div>
                                                <input type="text" class="form-control form-control-sm" id="searchGroups" placeholder="Buscar grupos..." style="width: 200px;">
                                            </div>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAll">Selecionar Todos</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="selectNone">Limpar Seleção</button>
                                                <button type="button" class="btn btn-sm btn-outline-info" id="selectFavorites">Apenas Favoritos</button>
                                            </div>
                                        </div>
                                        
                                        <div class="grupos-container">
                                            <?php 
                                            // Reset do ponteiro do resultado para reutilizá-lo
                                            $resultGrupos->data_seek(0);
                                            ?>
                                            
                                            <?php while ($grupo = $resultGrupos->fetch_assoc()): ?>
                                                <?php 
                                                $checked = false;
                                                if ($campanhaPara && in_array($grupo['id'], $campanhaPara['grupos'])) {
                                                    $checked = true;
                                                }
                                                ?>
                                                <div class="form-check grupo-item <?php echo $grupo['favorito'] ? 'grupo-favorito' : ''; ?>">
                                                    <input class="form-check-input" type="checkbox" name="grupos[]" value="<?php echo $grupo['id']; ?>" id="grupo_<?php echo $grupo['id']; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="grupo_<?php echo $grupo['id']; ?>">
                                                        <?php echo $grupo['favorito'] ? '<i class="fas fa-star text-warning me-1"></i> ' : ''; ?>
                                                        <?php echo htmlspecialchars($grupo['nome']); ?>
                                                    </label>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 col-md-6 mx-auto mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i> <?php echo $campanhaPara ? 'Atualizar Campanha' : 'Criar Campanha'; ?>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista de Campanhas -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-list me-2 text-primary"></i> Minhas Campanhas
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if ($resultCampanhas->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 20%;">Nome</th>
                                        <th style="width: 20%;">Anúncio</th>
                                        <th style="width: 15%;">Progresso</th>
                                        <th style="width: 15%;">Configuração</th>
                                        <th style="width: 15%;">Próxima Postagem</th>
                                        <th style="width: 15%;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($campanha = $resultCampanhas->fetch_assoc()): ?>
                                        <tr class="<?php echo $campanha['ativa'] ? 'table-row-active' : 'table-row-inactive'; ?>">
                                            <td>
                                                <?php echo htmlspecialchars($campanha['nome']); ?>
                                                <?php if ($campanha['ativa']): ?>
                                                    <span class="badge bg-success ms-2">Ativa</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary ms-2">Inativa</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($campanha['anuncio_titulo']); ?></td>
                                            <td>
                                                <?php 
                                                    $progresso = $campanha['total_grupos'] > 0 
                                                        ? ($campanha['grupos_postados'] / $campanha['total_grupos']) * 100 
                                                        : 0;
                                                ?>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                        style="width: <?php echo $progresso; ?>%;" 
                                                        aria-valuenow="<?php echo $progresso; ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100"></div>
                                                </div>
                                                <span class="small"><?php echo $campanha['grupos_postados']; ?>/<?php echo $campanha['total_grupos']; ?> grupos</span>
                                            </td>
                                            <td>
                                                <span data-bs-toggle="tooltip" title="Intervalo entre postagens">
                                                    <i class="fas fa-clock me-1"></i> <?php echo $campanha['intervalo_minutos']; ?> min
                                                </span>
                                                <br>
                                                <span data-bs-toggle="tooltip" title="Repetição da campanha">
                                                    <i class="fas fa-sync-alt me-1"></i> 
                                                    <?php echo $campanha['repeticao_horas'] ? 'Cada ' . $campanha['repeticao_horas'] . 'h' : 'Não repete'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($campanha['ativa'] && $campanha['proxima_execucao']): ?>
                                                    <span class="badge bg-primary">
                                                        <?php echo date('d/m/Y H:i', strtotime($campanha['proxima_execucao'])); ?>
                                                    </span>
                                                <?php elseif ($campanha['ativa']): ?>
                                                    <span class="badge bg-warning">Processando...</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Pausada</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="?edit=<?php echo $campanha['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <?php if ($campanha['ativa']): ?>
                                                        <a href="?toggle=<?php echo $campanha['id']; ?>&status=0" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Pausar Campanha">
                                                            <i class="fas fa-pause"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="?toggle=<?php echo $campanha['id']; ?>&status=1" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Ativar Campanha">
                                                            <i class="fas fa-play"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="logs.php?campanha_id=<?php echo $campanha['id']; ?>" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="Ver Logs">
                                                        <i class="fas fa-history"></i>
                                                    </a>
                                                    
                                                    <a href="?delete=<?php echo $campanha['id']; ?>" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta campanha?');">
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
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Você ainda não possui campanhas cadastradas.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Estilos para o formulário de campanha */
.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
    color: #3498db;
}

.grupos-container {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 15px;
    background-color: #f8f9fa;
}

.grupo-item {
    padding: 8px 15px;
    margin-bottom: 5px;
    border-radius: 8px;
    transition: all 0.2s;
}

.grupo-item:hover {
    background-color: rgba(52, 152, 219, 0.05);
}

.grupo-favorito label {
    font-weight: 500;
}

.table-row-active {
    background-color: rgba(46, 204, 113, 0.05);
}

.table-row-inactive {
    background-color: rgba(236, 240, 241, 0.5);
}
</style>

<!-- JavaScript Adicional -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Busca de grupos
    const searchGroups = document.getElementById('searchGroups');
    if (searchGroups) {
        searchGroups.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const grupoItems = document.querySelectorAll('.grupo-item');
            
            grupoItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
    
    // Botão Selecionar Todos
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.grupo-item input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                if (checkbox.closest('.grupo-item').style.display !== 'none') {
                    checkbox.checked = true;
                }
            });
        });
    }
    
    // Botão Limpar Seleção
    const selectNone = document.getElementById('selectNone');
    if (selectNone) {
        selectNone.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.grupo-item input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    }
    
    // Botão Apenas Favoritos
    const selectFavorites = document.getElementById('selectFavorites');
    if (selectFavorites) {
        selectFavorites.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.grupo-item input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            const favoriteCheckboxes = document.querySelectorAll('.grupo-favorito input[type="checkbox"]');
            favoriteCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
        });
    }
    
    // Tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    if(typeof bootstrap !== 'undefined') {
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});
</script>

<?php
// Incluir o rodapé
include 'includes/footer.php';
?>