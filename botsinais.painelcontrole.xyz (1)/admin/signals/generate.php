<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'manager')) {
    header('Location: ../../index.php');
    exit;
}

// Get games list
$stmt = $conn->prepare("
    SELECT id, name, provider 
    FROM games 
    WHERE status = 'active' 
    ORDER BY provider, name
");
$stmt->execute();
$result = $stmt->get_result();

$games = [
    'PG' => [],
    'Pragmatic' => []
];

while ($row = $result->fetch_assoc()) {
    $games[$row['provider']][] = $row;
}

// Get platforms list
$platformsStmt = $conn->prepare("SELECT id, name FROM platforms WHERE status = 'active' ORDER BY name");
$platformsStmt->execute();
$platforms = $platformsStmt->get_result();

// Get bot list
$whereClause = $_SESSION['role'] != 'admin' ? "WHERE created_by = ? AND status = 'active'" : "WHERE status = 'active'";
$botsStmt = $conn->prepare("SELECT id, name, provider FROM bots $whereClause ORDER BY name");

if ($_SESSION['role'] != 'admin') {
    $botsStmt->bind_param("i", $_SESSION['user_id']);
}

$botsStmt->execute();
$bots = $botsStmt->get_result();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $gameId = $_POST['game_id'];
    $platformId = $_POST['platform_id'];
    $botId = $_POST['bot_id'];
    $roundsNormal = $_POST['rounds_normal'];
    $roundsTurbo = $_POST['rounds_turbo'];
    $scheduleType = $_POST['schedule_type'];
    
    if ($scheduleType == 'now') {
        $scheduleTime = date('Y-m-d H:i:s', strtotime('+2 minutes'));
    } else {
        $scheduleTime = $_POST['schedule_date'] . ' ' . $_POST['schedule_time'] . ':00';
    }
    
    // Validate bot ownership if not admin
    if ($_SESSION['role'] != 'admin') {
        $checkStmt = $conn->prepare("SELECT created_by FROM bots WHERE id = ?");
        $checkStmt->bind_param("i", $botId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $botOwner = $checkResult->fetch_assoc();
        
        if ($botOwner['created_by'] != $_SESSION['user_id']) {
            $_SESSION['error'] = "Você não tem permissão para gerar sinais para este bot.";
            header('Location: generate.php');
            exit;
        }
    }
    
    // Check if bot is active
    $checkStmt = $conn->prepare("SELECT status FROM bots WHERE id = ?");
    $checkStmt->bind_param("i", $botId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $botStatus = $checkResult->fetch_assoc();
    
    if ($botStatus['status'] != 'active') {
        $_SESSION['error'] = "Este bot está inativo ou expirado e não pode gerar sinais.";
        header('Location: generate.php');
        exit;
    }
    
    // Insert signal
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
        $_SESSION['error'] = "Erro ao gerar sinal: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerar Sinal - BotDeSinais</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gerar Novo Sinal</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="provider_select" class="form-label">Provedor de Jogos</label>
                                    <select id="provider_select" class="form-select" onchange="toggleGamesList()">
                                        <option value="PG">PG</option>
                                        <option value="Pragmatic">Pragmatic</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3" id="pg_games_container">
                                <div class="col-md-6">
                                    <label for="game_id" class="form-label">Jogo PG</label>
                                    <select name="game_id" id="game_id_pg" class="form-select">
                                        <?php foreach ($games['PG'] as $game): ?>
                                            <option value="<?php echo $game['id']; ?>"><?php echo htmlspecialchars($game['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3" id="pragmatic_games_container" style="display: none;">
                                <div class="col-md-6">
                                    <label for="game_id" class="form-label">Jogo Pragmatic</label>
                                    <select name="game_id" id="game_id_pragmatic" class="form-select">
                                        <?php foreach ($games['Pragmatic'] as $game): ?>
                                            <option value="<?php echo $game['id']; ?>"><?php echo htmlspecialchars($game['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="platform_id" class="form-label">Plataforma</label>
                                    <select name="platform_id" id="platform_id" class="form-select" required>
                                        <?php while ($platform = $platforms->fetch_assoc()): ?>
                                            <option value="<?php echo $platform['id']; ?>"><?php echo htmlspecialchars($platform['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="bot_id" class="form-label">Bot</label>
                                    <select name="bot_id" id="bot_id" class="form-select" required>
                                        <option value="">Selecione um bot</option>
                                        <?php while ($bot = $bots->fetch_assoc()): ?>
                                            <option value="<?php echo $bot['id']; ?>" data-provider="<?php echo $bot['provider']; ?>">
                                                <?php echo htmlspecialchars($bot['name']); ?> (<?php echo $bot['provider']; ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="rounds_normal" class="form-label">Rodadas Modo Normal</label>
                                    <input type="number" name="rounds_normal" id="rounds_normal" class="form-control" min="1" max="20" value="10" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="rounds_turbo" class="form-label">Rodadas Modo Turbo</label>
                                    <input type="number" name="rounds_turbo" id="rounds_turbo" class="form-control" min="1" max="20" value="5" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Agendamento</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="schedule_type" id="schedule_now" value="now" checked>
                                        <label class="form-check-label" for="schedule_now">
                                            Enviar agora
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="schedule_type" id="schedule_later" value="later">
                                        <label class="form-check-label" for="schedule_later">
                                            Agendar para mais tarde
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3" id="schedule_fields" style="display: none;">
                                <div class="col-md-6">
                                    <label for="schedule_date" class="form-label">Data</label>
                                    <input type="date" name="schedule_date" id="schedule_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="schedule_time" class="form-label">Hora</label>
                                    <input type="time" name="schedule_time" id="schedule_time" class="form-control" value="<?php echo date('H:i', strtotime('+30 minutes')); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Gerar Sinal
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="../../assets/js/admin.js"></script>
    <script>
        function toggleGamesList() {
            var provider = document.getElementById('provider_select').value;
            
            if (provider === 'PG') {
                document.getElementById('pg_games_container').style.display = 'block';
                document.getElementById('pragmatic_games_container').style.display = 'none';
                document.getElementById('game_id_pg').name = 'game_id';
                document.getElementById('game_id_pragmatic').name = 'game_id_disabled';
            } else {
                document.getElementById('pg_games_container').style.display = 'none';
                document.getElementById('pragmatic_games_container').style.display = 'block';
                document.getElementById('game_id_pg').name = 'game_id_disabled';
                document.getElementById('game_id_pragmatic').name = 'game_id';
            }
            
            // Filter bots by provider
            filterBotsByProvider(provider);
        }
        
        function filterBotsByProvider(provider) {
            var botSelect = document.getElementById('bot_id');
            var options = botSelect.options;
            var hasSelectionForProvider = false;
            
            for (var i = 0; i < options.length; i++) {
                var botProvider = options[i].getAttribute('data-provider');
                if (botProvider === provider) {
                    options[i].style.display = '';
                    if (!hasSelectionForProvider) {
                        options[i].selected = true;
                        hasSelectionForProvider = true;
                    }
                } else {
                    options[i].style.display = 'none';
                    options[i].selected = false;
                }
            }
        }
        
        // Schedule type toggle
        document.querySelector('input[name="schedule_type"]').addEventListener('change', function() {
            toggleScheduleFields();
        });
        
        document.querySelector('input[name="schedule_type"]:checked').addEventListener('change', function() {
            toggleScheduleFields();
        });
        
        function toggleScheduleFields() {
            var scheduleType = document.querySelector('input[name="schedule_type"]:checked').value;
            var scheduleFields = document.getElementById('schedule_fields');
            
            if (scheduleType === 'later') {
                scheduleFields.style.display = 'flex';
            } else {
                scheduleFields.style.display = 'none';
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            filterBotsByProvider(document.getElementById('provider_select').value);
            toggleScheduleFields();
        });
    </script>
</body>
</html>