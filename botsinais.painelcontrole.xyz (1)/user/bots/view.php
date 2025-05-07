<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar permissão
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Definir variáveis de caminho
$pageTitle = 'Detalhes do Bot';
$basePath = '../../';

// Obter o ID do usuário atual
$userId = $_SESSION['user_id'];

// Verificar se o ID do bot foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID do bot inválido.";
    header('Location: index.php');
    exit;
}

$botId = (int)$_GET['id'];

// Buscar detalhes do bot
$stmt = $conn->prepare("
    SELECT * FROM bots 
    WHERE id = ? AND created_by = ?
");
$stmt->bind_param("ii", $botId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Bot não encontrado ou você não tem permissão para visualizá-lo.";
    header('Location: index.php');
    exit;
}

$bot = $result->fetch_assoc();

// Atualizar o título da página
$pageTitle = 'Bot: ' . $bot['name'];

// Buscar canais associados a este bot
$channelsStmt = $conn->prepare("
    SELECT * FROM channels
    WHERE bot_id = ?
    ORDER BY created_at DESC
");
$channelsStmt->bind_param("i", $botId);
$channelsStmt->execute();
$channels = $channelsStmt->get_result();

// Buscar sinais recentes deste bot
$signalsStmt = $conn->prepare("
    SELECT s.*, g.name as game_name, p.name as platform_name
    FROM signals s
    JOIN games g ON s.game_id = g.id
    JOIN platforms p ON s.platform_id = p.id
    WHERE s.bot_id = ?
    ORDER BY s.schedule_time DESC
    LIMIT 10
");
$signalsStmt->bind_param("i", $botId);
$signalsStmt->execute();
$signals = $signalsStmt->get_result();

// Incluir header
include '../../includes/header.php';
?>

<!-- Conteúdo principal -->
<main class="container-fluid">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center">
            <a href="index.php" class="btn btn-sm btn-outline-secondary me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="h2 mb-0">
                <?php if ($bot['provider'] == 'PG'): ?>
                    <span class="badge bg-primary me-2">PG</span>
                <?php else: ?>
                    <span class="badge bg-success me-2">Pragmatic</span>
                <?php endif; ?>
                <?php echo htmlspecialchars($bot['name']); ?>
            </h1>
        </div>
        
        <div class="btn-group">
            <a href="edit.php?id=<?php echo $botId; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i> Editar
            </a>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="visually-hidden">Toggle Dropdown</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="../signals/generate.php?bot_id=<?php echo $botId; ?>">
                        <i class="fas fa-paper-plane me-2"></i> Gerar Sinal
                    </a>
                                    </li>
                <li>
                    <a class="dropdown-item" href="../channels/create.php?bot_id=<?php echo $botId; ?>">
                        <i class="fab fa-telegram me-2"></i> Adicionar Canal/Grupo
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="index.php?action=delete&id=<?php echo $botId; ?>" 
                       onclick="return confirm('Tem certeza que deseja excluir este bot? Esta ação não pode ser desfeita.')">
                        <i class="fas fa-trash me-2"></i> Excluir Bot
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Informações do Bot -->
            <div class="card shadow h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        Informações do Bot
                    </h6>
                </div>
                <div class="card-body">
                    <div class="bot-info">
                        <div class="bot-info-item">
                            <div class="bot-info-label">Status</div>
                            <div class="bot-info-value">
                                <?php if ($bot['status'] == 'active'): ?>
                                    <span class="badge bg-success">Ativo</span>
                                <?php elseif ($bot['status'] == 'suspended'): ?>
                                    <span class="badge bg-warning text-dark">Suspenso</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Expirado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bot-info-item">
                            <div class="bot-info-label">Provedor</div>
                            <div class="bot-info-value">
                                <?php if ($bot['provider'] == 'PG'): ?>
                                    <span class="badge bg-primary">PG Soft</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Pragmatic Play</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bot-info-item">
                            <div class="bot-info-label">Data de Criação</div>
                            <div class="bot-info-value">
                                <?php echo formatDate($bot['created_at']); ?>
                            </div>
                        </div>
                        <div class="bot-info-item">
                            <div class="bot-info-label">Data de Expiração</div>
                            <div class="bot-info-value">
                                <?php 
                                    $days_left = daysRemaining($bot['expiry_date']);
                                    echo formatDate($bot['expiry_date']);
                                    
                                    if ($days_left <= 7 && $days_left >= 0) {
                                        echo " <span class='badge bg-warning text-dark'>{$days_left} dias</span>";
                                    } elseif ($days_left < 0) {
                                        echo " <span class='badge bg-danger'>Expirado</span>";
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="bot-info-item">
                            <div class="bot-info-label">Token Telegram</div>
                            <div class="bot-info-value token-field">
                                <div class="input-group">
                                    <input type="password" class="form-control" id="telegram_token" value="<?php echo $bot['telegram_token']; ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleToken">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" id="copyToken" data-bs-toggle="tooltip" title="Copiar">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8 mb-4">
            <!-- Canais e Grupos -->
            <div class="card shadow mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fab fa-telegram me-2 text-info"></i>
                        Canais e Grupos
                    </h6>
                    <a href="../channels/create.php?bot_id=<?php echo $botId; ?>" class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i> Adicionar
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome</th>
                                    <th>Tipo</th>
                                    <th>ID Telegram</th>
                                    <th>Expira em</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($channels->num_rows > 0): ?>
                                    <?php while ($channel = $channels->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($channel['name']); ?></td>
                                            <td>
                                                <?php if ($channel['type'] == 'channel'): ?>
                                                    <span class="badge bg-info">Canal</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Grupo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($channel['telegram_id']); ?></td>
                                            <td>
                                                <?php 
                                                    $days_left = daysRemaining($channel['expiry_date']);
                                                    echo formatDate($channel['expiry_date']);
                                                    
                                                    if ($days_left <= 7 && $days_left >= 0) {
                                                        echo " <span class='badge bg-warning text-dark'>{$days_left} dias</span>";
                                                    } elseif ($days_left < 0) {
                                                        echo " <span class='badge bg-danger'>Expirado</span>";
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($channel['status'] == 'active'): ?>
                                                    <span class="badge bg-success">Ativo</span>
                                                <?php elseif ($channel['status'] == 'suspended'): ?>
                                                    <span class="badge bg-warning text-dark">Suspenso</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Expirado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="../channels/edit.php?id=<?php echo $channel['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="../channels/index.php?action=delete&id=<?php echo $channel['id']; ?>" class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Tem certeza que deseja excluir este canal/grupo?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="fab fa-telegram fa-3x mb-3 text-muted"></i>
                                                <p class="mb-3">Este bot ainda não tem canais ou grupos associados.</p>
                                                <a href="../channels/create.php?bot_id=<?php echo $botId; ?>" class="btn btn-outline-success">
                                                    <i class="fas fa-plus me-2"></i> Adicionar Canal/Grupo
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Sinais Recentes -->
            <div class="card shadow">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-signal me-2 text-success"></i>
                        Sinais Recentes
                    </h6>
                    <a href="../signals/generate.php?bot_id=<?php echo $botId; ?>" class="btn btn-sm btn-info text-white">
                        <i class="fas fa-plus me-1"></i> Gerar
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Jogo</th>
                                    <th>Plataforma</th>
                                    <th>Rodadas</th>
                                    <th>Horário</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($signals->num_rows > 0): ?>
                                    <?php while ($signal = $signals->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($signal['game_name']); ?></td>
                                            <td><?php echo htmlspecialchars($signal['platform_name']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $signal['rounds_normal']; ?> normal</span>
                                                <span class="badge bg-dark"><?php echo $signal['rounds_turbo']; ?> turbo</span>
                                            </td>
                                            <td><?php echo date('d/m H:i', strtotime($signal['schedule_time'])); ?></td>
                                            <td>
                                                <?php if ($signal['status'] == 'sent'): ?>
                                                    <span class="badge bg-success">Enviado</span>
                                                <?php elseif ($signal['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">Pendente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Falha</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="fas fa-signal fa-3x mb-3 text-muted"></i>
                                                <p class="mb-3">Este bot ainda não tem sinais gerados.</p>
                                                <a href="../signals/generate.php?bot_id=<?php echo $botId; ?>" class="btn btn-outline-info">
                                                    <i class="fas fa-plus me-2"></i> Gerar Sinal
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    .bot-info {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .bot-info-item {
        display: flex;
        flex-direction: column;
    }
    
    .bot-info-label {
        font-size: 12px;
        text-transform: uppercase;
        color: #6e7d91;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .bot-info-value {
        font-size: 16px;
    }
    
    .token-field {
        margin-top: 5px;
    }
</style>

<script>
    // Toggle token visibility
    document.getElementById('toggleToken').addEventListener('click', function() {
        const tokenInput = document.getElementById('telegram_token');
        const icon = this.querySelector('i');
        
        if (tokenInput.type === 'password') {
            tokenInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            tokenInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
    
    // Copy token
    document.getElementById('copyToken').addEventListener('click', function() {
        const tokenInput = document.getElementById('telegram_token');
        tokenInput.type = 'text';
        tokenInput.select();
        document.execCommand('copy');
        tokenInput.type = 'password';
        
        // Show tooltip
        this.setAttribute('title', 'Copiado!');
        const tooltip = new bootstrap.Tooltip(this);
        tooltip.show();
        
        // Reset tooltip after delay
        setTimeout(() => {
            this.setAttribute('title', 'Copiar');
            tooltip.dispose();
        }, 2000);
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
</script>

<?php include '../../includes/footer.php'; ?>