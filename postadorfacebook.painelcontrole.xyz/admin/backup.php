<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../api/telegram.php';
require_once '../includes/functions.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Mensagens de feedback
$messages = [];

// Buscar configurações do Telegram
$queryConfig = "SELECT telegram_token, telegram_chat_id FROM configuracoes LIMIT 1";
$resultConfig = $db->query($queryConfig);
$config = $resultConfig->fetch_assoc();

$telegramConfigured = (!empty($config['telegram_token']) && !empty($config['telegram_chat_id']));

// Processar formulário de configuração do Telegram
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_telegram'])) {
    $telegramToken = $db->real_escape_string($_POST['telegram_token']);
    $telegramChatId = $db->real_escape_string($_POST['telegram_chat_id']);
    
    $queryUpdate = "UPDATE configuracoes SET telegram_token = ?, telegram_chat_id = ?";
    $stmtUpdate = $db->prepare($queryUpdate);
    $stmtUpdate->bind_param("ss", $telegramToken, $telegramChatId);
    
    if ($stmtUpdate->execute()) {
        $messages[] = [
            'type' => 'success',
            'text' => "Configurações do Telegram atualizadas com sucesso!"
        ];
        
        $config['telegram_token'] = $telegramToken;
        $config['telegram_chat_id'] = $telegramChatId;
        $telegramConfigured = (!empty($telegramToken) && !empty($telegramChatId));
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Erro ao atualizar configurações: " . $db->error
        ];
    }
}

// Executar backup manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_now']) && $telegramConfigured) {
    $telegramBackup = new TelegramBackup($db, $config['telegram_token'], $config['telegram_chat_id']);
    $result = $telegramBackup->realizarBackup();
    
    if ($result['success']) {
        $messages[] = [
            'type' => 'success',
            'text' => "Backup realizado com sucesso! Arquivo: {$result['file']} ({$result['size']} bytes)"
        ];
        
        if ($result['telegram_sent']) {
            $messages[] = [
                'type' => 'info',
                'text' => "Backup enviado ao Telegram com sucesso!"
            ];
        } else {
            $messages[] = [
                'type' => 'warning',
                'text' => "O backup foi salvo localmente, mas houve um erro ao enviá-lo ao Telegram."
            ];
        }
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Erro ao realizar backup: {$result['message']}"
        ];
    }
}

// Programar backup automático
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_backup']) && $telegramConfigured) {
    $frequencia = intval($_POST['frequencia']);
    $hora = intval($_POST['hora']);
    
    // Verificar se já existe um agendamento
    $queryCheck = "SELECT id FROM backup_agendamentos WHERE ativo = 1";
    $resultCheck = $db->query($queryCheck);
    
    if ($resultCheck->num_rows > 0) {
        // Atualizar agendamento existente
        $queryUpdate = "UPDATE backup_agendamentos SET frequencia = ?, hora = ? WHERE ativo = 1";
        $stmtUpdate = $db->prepare($queryUpdate);
        $stmtUpdate->bind_param("ii", $frequencia, $hora);
        
        if ($stmtUpdate->execute()) {
            $messages[] = [
                'type' => 'success',
                'text' => "Agendamento de backup atualizado com sucesso!"
            ];
        } else {
            $messages[] = [
                'type' => 'danger',
                'text' => "Erro ao atualizar agendamento: " . $db->error
            ];
        }
    } else {
        // Criar novo agendamento
        $queryInsert = "INSERT INTO backup_agendamentos (usuario_id, frequencia, hora, ativo) VALUES (?, ?, ?, 1)";
        $stmtInsert = $db->prepare($queryInsert);
        $stmtInsert->bind_param("iii", $userId, $frequencia, $hora);
        
        if ($stmtInsert->execute()) {
            $messages[] = [
                'type' => 'success',
                'text' => "Agendamento de backup criado com sucesso!"
            ];
        } else {
            $messages[] = [
                'type' => 'danger',
                'text' => "Erro ao criar agendamento: " . $db->error
            ];
        }
    }
}

// Excluir backup
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $backupId = intval($_GET['delete']);
    
    // Obter informações do backup antes de excluir
    $queryBackup = "SELECT arquivo FROM backups WHERE id = ?";
    $stmtBackup = $db->prepare($queryBackup);
    $stmtBackup->bind_param("i", $backupId);
    $stmtBackup->execute();
    $resultBackup = $stmtBackup->get_result();
    
    if ($resultBackup->num_rows > 0) {
        $backupInfo = $resultBackup->fetch_assoc();
        $backupPath = '../backups/' . $backupInfo['arquivo'];
        
        // Excluir arquivo físico se existir
        if (file_exists($backupPath)) {
            unlink($backupPath);
        }
        
        // Excluir registro do banco
        $queryDelete = "DELETE FROM backups WHERE id = ?";
        $stmtDelete = $db->prepare($queryDelete);
        $stmtDelete->bind_param("i", $backupId);
        
        if ($stmtDelete->execute()) {
            $messages[] = [
                'type' => 'success',
                'text' => "Backup excluído com sucesso!"
            ];
        } else {
            $messages[] = [
                'type' => 'danger',
                'text' => "Erro ao excluir backup: " . $db->error
            ];
        }
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Backup não encontrado!"
        ];
    }
}

// Buscar agendamento atual
$queryAgendamento = "SELECT * FROM backup_agendamentos WHERE ativo = 1";
$resultAgendamento = $db->query($queryAgendamento);
$agendamento = $resultAgendamento->num_rows > 0 ? $resultAgendamento->fetch_assoc() : null;

// Buscar histórico de backups
$queryBackups = "SELECT b.*, u.nome as usuario_nome 
                FROM backups b 
                JOIN usuarios u ON b.usuario_id = u.id 
                ORDER BY b.criado_em DESC";
$resultBackups = $db->query($queryBackups);

// Incluir o cabeçalho
include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-database me-2 text-primary"></i> Sistema de Backup
                    </h5>
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
                    
                    <!-- Status do Sistema de Backup -->
                    <div class="backup-status-card <?php echo $telegramConfigured ? 'configured' : 'not-configured'; ?>">
                        <div class="status-icon">
                            <i class="fas <?php echo $telegramConfigured ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        </div>
                        <div class="status-content">
                            <h5><?php echo $telegramConfigured ? 'Sistema de Backup Configurado' : 'Sistema de Backup Não Configurado'; ?></h5>
                            <p>
                                <?php if ($telegramConfigured): ?>
                                    O sistema de backup via Telegram está configurado e pronto para uso. 
                                    Você pode realizar backups manuais ou programados.
                                <?php else: ?>
                                    Configure o bot do Telegram para começar a fazer backups do banco de dados.
                                    Para isso, você precisa criar um bot no Telegram e obter o token de acesso.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Configuração do Telegram -->
        <div class="col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fab fa-telegram-plane me-2 text-info"></i> Configuração do Telegram
                    </h5>
                </div>
                <div class="modern-card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="telegram_token" class="form-label">Token do Bot do Telegram</label>
                            <input type="text" class="form-control" id="telegram_token" name="telegram_token" value="<?php echo htmlspecialchars($config['telegram_token'] ?? ''); ?>" required>
                            <div class="form-text">
                                Token fornecido pelo BotFather ao criar um novo bot no Telegram.
                                <a href="https://core.telegram.org/bots#botfather" target="_blank">Saiba mais</a>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telegram_chat_id" class="form-label">ID do Chat ou Canal</label>
                            <input type="text" class="form-control" id="telegram_chat_id" name="telegram_chat_id" value="<?php echo htmlspecialchars($config['telegram_chat_id'] ?? ''); ?>" required>
                            <div class="form-text">
                                ID do chat, grupo ou canal onde os backups serão enviados.
                            </div>
                        </div>
                        
                        <div class="setup-instructions">
                            <h6><i class="fas fa-info-circle me-2"></i> Como configurar:</h6>
                            <ol>
                                <li>Abra o Telegram e converse com <a href="https://t.me/botfather" target="_blank">@BotFather</a></li>
                                <li>Envie o comando <code>/newbot</code> e siga as instruções para criar um novo bot</li>
                                <li>Copie o token fornecido e cole no campo acima</li>
                                <li>Adicione o bot a um grupo ou canal onde deseja receber os backups</li>
                                <li>Para obter o ID do chat, adicione <a href="https://t.me/getidsbot" target="_blank">@getidsbot</a> ao mesmo grupo ou encaminhe uma mensagem do canal para ele</li>
                            </ol>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="save_telegram" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Salvar Configurações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Ações de Backup -->
        <div class="col-md-6 mb-4">
            <div class="modern-card h-100">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-cloud-upload-alt me-2 text-success"></i> Ações de Backup
                    </h5>
                </div>
                <div class="modern-card-body">
                    <div class="backup-actions-container">
                        <!-- Backup Manual -->
                        <div class="backup-action-section">
                            <h6><i class="fas fa-hand-point-right me-2"></i> Backup Manual</h6>
                            <p>Realize um backup imediato do banco de dados e envie para o Telegram.</p>
                            <form method="POST" class="d-grid gap-2">
                                <button type="submit" name="backup_now" class="btn btn-success" <?php echo !$telegramConfigured ? 'disabled' : ''; ?>>
                                    <i class="fas fa-download me-2"></i> Fazer Backup Agora
                                </button>
                            </form>
                        </div>
                        
                        <hr>
                        
                        <!-- Backup Programado -->
                        <div class="backup-action-section">
                            <h6><i class="fas fa-calendar-alt me-2"></i> Backup Automático</h6>
                            <p>Configure backups automáticos periódicos para seu banco de dados.</p>
                            
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="frequencia" class="form-label">Frequência</label>
                                        <select class="form-select" id="frequencia" name="frequencia" required>
                                            <option value="1" <?php echo ($agendamento && $agendamento['frequencia'] == 1) ? 'selected' : ''; ?>>Diário</option>
                                            <option value="7" <?php echo ($agendamento && $agendamento['frequencia'] == 7) ? 'selected' : ''; ?>>Semanal</option>
                                            <option value="30" <?php echo ($agendamento && $agendamento['frequencia'] == 30) ? 'selected' : ''; ?>>Mensal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="hora" class="form-label">Hora do Dia</label>
                                        <select class="form-select" id="hora" name="hora" required>
                                            <?php for($h=0; $h<24; $h++): ?>
                                                <option value="<?php echo $h; ?>" <?php echo ($agendamento && $agendamento['hora'] == $h) ? 'selected' : ''; ?>>
                                                    <?php echo sprintf('%02d:00', $h); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="schedule_backup" class="btn btn-primary" <?php echo !$telegramConfigured ? 'disabled' : ''; ?>>
                                        <i class="fas fa-clock me-2"></i> Agendar Backup Automático
                                    </button>
                                </div>
                            </form>
                            
                            <?php if ($agendamento): ?>
                                <div class="mt-3 schedule-info">
                                    <p>
                                        <strong>Agendamento atual:</strong> 
                                        <?php 
                                        $frequenciaTexto = [
                                            1 => 'Diário',
                                            7 => 'Semanal',
                                            30 => 'Mensal'
                                        ];
                                        echo $frequenciaTexto[$agendamento['frequencia']] ?? 'Personalizado';
                                        ?> às <?php echo sprintf('%02d:00', $agendamento['hora']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Histórico de Backups -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-history me-2 text-primary"></i> Histórico de Backups
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if ($resultBackups->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Arquivo</th>
                                        <th>Tamanho</th>
                                        <th>Enviado ao Telegram</th>
                                        <th>Criado por</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($backup = $resultBackups->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $backup['id']; ?></td>
                                            <td><?php echo htmlspecialchars($backup['arquivo']); ?></td>
                                            <td><?php echo formatBytes($backup['tamanho']); ?></td>
                                            <td>
                                                <?php if($backup['enviado_telegram']): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i> Sim</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning"><i class="fas fa-times me-1"></i> Não</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($backup['usuario_nome']); ?></td>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($backup['criado_em'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../backups/<?php echo htmlspecialchars($backup['arquivo']); ?>" class="btn btn-outline-primary" download>
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $backup['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir este backup?');">
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
                            <i class="fas fa-info-circle me-2"></i> Nenhum backup foi realizado ainda.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Estilos para o card de status de backup */
.backup-status-card {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
}

.backup-status-card.configured {
    background-color: rgba(46, 204, 113, 0.1);
    border-left: 4px solid #2ecc71;
}

.backup-status-card.not-configured {
    background-color: rgba(243, 156, 18, 0.1);
    border-left: 4px solid #f39c12;
}

.backup-status-card .status-icon {
    font-size: 2.5rem;
    margin-right: 20px;
}

.backup-status-card.configured .status-icon {
    color: #2ecc71;
}

.backup-status-card.not-configured .status-icon {
    color: #f39c12;
}

.backup-status-card .status-content {
    flex: 1;
}

.backup-status-card h5 {
    margin-bottom: 10px;
    font-weight: 600;
}

/* Estilos para instruções de configuração */
.setup-instructions {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin: 20px 0;
}

.setup-instructions h6 {
    font-weight: 600;
    margin-bottom: 10px;
}

.setup-instructions ol {
    padding-left: 20px;
}

.setup-instructions li {
    margin-bottom: 5px;
}

.setup-instructions code {
    background-color: #e9ecef;
    padding: 2px 5px;
    border-radius: 4px;
}

/* Estilos para ações de backup */
.backup-actions-container {
    padding: 10px;
}

.backup-action-section {
    margin-bottom: 20px;
}

.backup-action-section h6 {
    font-weight: 600;
    margin-bottom: 10px;
}

.schedule-info {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 10px 15px;
}

/* Função para formatar bytes em tamanho legível */
<?php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
</style>

<?php
// Incluir o rodapé
include '../includes/footer.php';
?>