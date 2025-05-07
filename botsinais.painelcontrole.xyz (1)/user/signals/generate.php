<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Definir título da página
$pageTitle = 'Gerar Sinal';
$basePath = '../../';

// Obter o ID do usuário atual
$userId = $_SESSION['user_id'];

// Verificar se foi fornecido um bot específico
$selectedBotId = isset($_GET['bot_id']) ? (int)$_GET['bot_id'] : null;

// Buscar bots do usuário
$whereClause = "WHERE created_by = ? AND status = 'active'";
if ($selectedBotId) {
    $whereClause .= " AND id = ?";
    $stmtTypes = "ii";
    $stmtParams = [$userId, $selectedBotId];
} else {
    $stmtTypes = "i";
    $stmtParams = [$userId];
}

$botsStmt = $conn->prepare("SELECT id, name, provider FROM bots $whereClause ORDER BY name");
$botsStmt->bind_param($stmtTypes, ...$stmtParams);
$botsStmt->execute();
$botsResult = $botsStmt->get_result();

// Verificar se o usuário tem bots ativos
if ($botsResult->num_rows == 0) {
    $_SESSION['error'] = "Você não possui bots ativos para gerar sinais.";
    header('Location: index.php');
    exit;
}

// Buscar jogos disponíveis por provedor
$gamesStmt = $conn->prepare("
    SELECT id, name, provider 
    FROM games 
    WHERE status = 'active' 
    ORDER BY provider, name
");
$gamesStmt->execute();
$gamesResult = $gamesStmt->get_result();

$games = [
    'PG' => [],
    'Pragmatic' => []
];

while ($game = $gamesResult->fetch_assoc()) {
    $games[$game['provider']][] = $game;
}

// Buscar plataformas disponíveis
$platformsStmt = $conn->prepare("
    SELECT id, name 
    FROM platforms 
    WHERE status = 'active' 
    ORDER BY name
");
$platformsStmt->execute();
$platforms = $platformsStmt->get_result();

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Extrair e validar os dados do formulário
    $botId = $_POST['bot_id'];
    $gameId = $_POST['game_id'];
    $platformId = $_POST['platform_id'];
    $roundsNormal = (int)$_POST['rounds_normal'];
    $roundsTurbo = (int)$_POST['rounds_turbo'];
    $scheduleType = $_POST['schedule_type'];
    
    // Validação básica
    $errors = [];
    
    if (empty($botId)) {
        $errors[] = "Selecione um bot.";
    }
    
    if (empty($gameId)) {
        $errors[] = "Selecione um jogo.";
    }
    
    if (empty($platformId)) {
        $errors[] = "Selecione uma plataforma.";
    }
    
    if ($roundsNormal < 1 || $roundsNormal > 20) {
        $errors[] = "O número de rodadas no modo normal deve estar entre 1 e 20.";
    }
    
    if ($roundsTurbo < 1 || $roundsTurbo > 20) {
        $errors[] = "O número de rodadas no modo turbo deve estar entre 1 e 20.";
    }
    
    // Verificar propriedade do bot
    $botCheckStmt = $conn->prepare("SELECT id, provider FROM bots WHERE id = ? AND created_by = ? AND status = 'active'");
    $botCheckStmt->bind_param("ii", $botId, $userId);
    $botCheckStmt->execute();
    $botCheckResult = $botCheckStmt->get_result();
    
    if ($botCheckResult->num_rows == 0) {
        $errors[] = "Bot inválido ou você não tem permissão para gerar sinais com este bot.";
    } else {
        $botInfo = $botCheckResult->fetch_assoc();
        
        // Verificar se o jogo e o bot são do mesmo provedor
        $gameCheckStmt = $conn->prepare("SELECT provider FROM games WHERE id = ? AND status = 'active'");
        $gameCheckStmt->bind_param("i", $gameId);
        $gameCheckStmt->execute();
        $gameCheckResult = $gameCheckStmt->get_result();
        
        if ($gameCheckResult->num_rows == 0) {
            $errors[] = "Jogo inválido ou inativo.";
        } else {
            $gameInfo = $gameCheckResult->fetch_assoc();
            
            if ($gameInfo['provider'] != $botInfo['provider']) {
                $errors[] = "O jogo selecionado e o bot devem ser do mesmo provedor (" . $botInfo['provider'] . ").";
            }
        }
    }
    
    // Definir horário de agendamento
    if ($scheduleType == 'now') {
        // Agendar para daqui a poucos minutos
        $minMinutes = (int)getSetting($conn, 'signal_frequency_min') ?: 25;
        $maxMinutes = (int)getSetting($conn, 'signal_frequency_max') ?: 35;
        $minutesToAdd = rand($minMinutes, $maxMinutes);
        $scheduleTime = date('Y-m-d H:i:s', strtotime("+$minutesToAdd minutes"));
    } else {
        // Agendar para data/hora específica
        $scheduleDate = $_POST['schedule_date'];
        $scheduleTime = $_POST['schedule_time'];
        $scheduleTime = $scheduleDate . ' ' . $scheduleTime . ':00';
        
        // Verificar se a data é futura
        if (strtotime($scheduleTime) <= time()) {
            $errors[] = "O horário de agendamento deve ser no futuro.";
        }
    }
    
    // Se não houver erros, inserir o sinal
    if (empty($errors)) {
        $insertStmt = $conn->prepare("
            INSERT INTO signals (game_id, platform_id, bot_id, rounds_normal, rounds_turbo, schedule_time, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $insertStmt->bind_param("iiiiss", $gameId, $platformId, $botId, $roundsNormal, $roundsTurbo, $scheduleTime);
        
        if ($insertStmt->execute()) {
            $_SESSION['success'] = "Sinal gerado com sucesso para envio às " . date('d/m/Y H:i', strtotime($scheduleTime));
            header('Location: index.php');
            exit;
        } else {
            $error = "Erro ao gerar sinal: " . $conn->error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Incluir header
include '../../includes/header.php';
?>

<!-- Conteúdo principal -->
<main class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Gerar Novo Sinal</h1>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar para Lista
            </a>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card shadow">
        <div class="card-body">
            <form method="post" action="" id="signalForm">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="bot_id" class="form-label">Bot <span class="text-danger">*</span></label>
                        <select name="bot_id" id="bot_id" class="form-select" required>
                            <option value="">Selecione um bot</option>
                            <?php while ($bot = $botsResult->fetch_assoc()): ?>
                                <option value="<?php echo $bot['id']; ?>" 
                                        data-provider="<?php echo $bot['provider']; ?>"
                                        <?php echo ($selectedBotId == $bot['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bot['name']); ?> 
                                    (<?php echo $bot['provider']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="game_provider" class="form-label">Provedor de Jogo</label>
                        <select id="game_provider" class="form-select" disabled>
                            <option value="PG">PG Soft</option>
                            <option value="Pragmatic">Pragmatic Play</option>
                        </select>
                        <div class="form-text">O provedor é determinado pelo bot selecionado.</div>
                    </div>
                </div>
                
                <div class="row mb-3" id="pg_games_container">
                    <div class="col-md-6">
                        <label for="game_id_pg" class="form-label">Jogo PG <span class="text-danger">*</span></label>
                        <select name="game_id" id="game_id_pg" class="form-select" required>
                            <option value="">Selecione um jogo</option>
                            <?php foreach ($games['PG'] as $game): ?>
                                <option value="<?php echo $game['id']; ?>">
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3" id="pragmatic_games_container" style="display: none;">
                    <div class="col-md-6">
                        <label for="game_id_pragmatic" class="form-label">Jogo Pragmatic <span class="text-danger">*</span></label>
                        <select name="game_id_disabled" id="game_id_pragmatic" class="form-select">
                            <option value="">Selecione um jogo</option>
                            <?php foreach ($games['Pragmatic'] as $game): ?>
                                <option value="<?php echo $game['id']; ?>">
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="platform_id" class="form-label">Plataforma <span class="text-danger">*</span></label>
                        <select name="platform_id" id="platform_id" class="form-select" required>
                            <option value="">Selecione uma plataforma</option>
                            <?php while ($platform = $platforms->fetch_assoc()): ?>
                                <option value="<?php echo $platform['id']; ?>">
                                    <?php echo htmlspecialchars($platform['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="rounds_normal" class="form-label">Rodadas Modo Normal <span class="text-danger">*</span></label>
                        <input type="number" name="rounds_normal" id="rounds_normal" class="form-control" min="1" max="20" value="10" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="rounds_turbo" class="form-label">Rodadas Modo Turbo <span class="text-danger">*</span></label>
                        <input type="number" name="rounds_turbo" id="rounds_turbo" class="form-control" min="1" max="20" value="5" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Agendamento</label>
                        <div class="schedule-selector">
                            <div class="form-check form-check-inline schedule-option">
                                <input class="form-check-input" type="radio" name="schedule_type" id="schedule_now" value="now" checked>
                                <label class="form-check-label" for="schedule_now">
                                    <div class="schedule-icon">
                                        <i class="fas fa-bolt text-warning"></i>
                                    </div>
                                    <span>Enviar Automaticamente</span>
                                    <small class="d-block text-muted">O sinal será enviado entre 25-35 min</small>
                                </label>
                            </div>
                            
                            <div class="form-check form-check-inline schedule-option">
                                <input class="form-check-input" type="radio" name="schedule_type" id="schedule_later" value="later">
                                <label class="form-check-label" for="schedule_later">
                                    <div class="schedule-icon">
                                        <i class="fas fa-calendar-alt text-info"></i>
                                    </div>
                                    <span>Agendar para Depois</span>
                                    <small class="d-block text-muted">Defina data e hora específicas</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3" id="schedule_fields" style="display: none;">
                    <div class="col-md-3">
                        <label for="schedule_date" class="form-label">Data <span class="text-danger">*</span></label>
                        <input type="date" name="schedule_date" id="schedule_date" class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="schedule_time" class="form-label">Hora <span class="text-danger">*</span></label>
                        <input type="time" name="schedule_time" id="schedule_time" class="form-control" 
                               value="<?php echo date('H:i', strtotime('+30 minutes')); ?>">
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="button" class="btn btn-outline-secondary me-md-2" onclick="window.location.href='index.php'">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> Gerar Sinal
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<style>
    .schedule-selector {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 10px;
    }
    
    .schedule-option {
        flex: 1;
        min-width: 200px;
        margin: 0;
    }
    
    .schedule-option .form-check-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px 15px;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
        width: 100%;
    }
    
    .schedule-option .form-check-input:checked + .form-check-label {
        background-color: rgba(78, 115, 223, 0.1);
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }
    
    .schedule-icon {
        font-size: 2rem;
        margin-bottom: 10px;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .schedule-option:hover .form-check-label {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .form-check-input {
        position: absolute;
        opacity: 0;
    }
</style>

<script>
    // Função para atualizar o seletor de jogos com base no provedor do bot
    function updateGameSelector() {
        const botSelect = document.getElementById('bot_id');
        const selectedOption = botSelect.options[botSelect.selectedIndex];
        const provider = selectedOption ? selectedOption.getAttribute('data-provider') : '';
        
        document.getElementById('game_provider').value = provider;
        
        if (provider === 'PG') {
            document.getElementById('pg_games_container').style.display = 'block';
            document.getElementById('pragmatic_games_container').style.display = 'none';
            document.getElementById('game_id_pg').name = 'game_id';
            document.getElementById('game_id_pragmatic').name = 'game_id_disabled';
            document.getElementById('game_id_pragmatic').required = false;
            document.getElementById('game_id_pg').required = true;
        } else if (provider === 'Pragmatic') {
            document.getElementById('pg_games_container').style.display = 'none';
            document.getElementById('pragmatic_games_container').style.display = 'block';
            document.getElementById('game_id_pg').name = 'game_id_disabled';
            document.getElementById('game_id_pragmatic').name = 'game_id';
            document.getElementById('game_id_pg').required = false;
            document.getElementById('game_id_pragmatic').required = true;
        } else {
            document.getElementById('pg_games_container').style.display = 'none';
            document.getElementById('pragmatic_games_container').style.display = 'none';
        }
    }
    
    // Função para alternar campos de agendamento
    function toggleScheduleFields() {
        const scheduleType = document.querySelector('input[name="schedule_type"]:checked').value;
        const scheduleFields = document.getElementById('schedule_fields');
        
        if (scheduleType === 'later') {
            scheduleFields.style.display = 'flex';
            document.getElementById('schedule_date').required = true;
            document.getElementById('schedule_time').required = true;
        } else {
            scheduleFields.style.display = 'none';
            document.getElementById('schedule_date').required = false;
            document.getElementById('schedule_time').required = false;
        }
    }
    
    // Configurar event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar seletores
        updateGameSelector();
        toggleScheduleFields();
        
        // Event listener para alteração do bot
        document.getElementById('bot_id').addEventListener('change', updateGameSelector);
        
        // Event listeners para alteração do tipo de agendamento
        const scheduleRadios = document.querySelectorAll('input[name="schedule_type"]');
        scheduleRadios.forEach(radio => {
            radio.addEventListener('change', toggleScheduleFields);
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>