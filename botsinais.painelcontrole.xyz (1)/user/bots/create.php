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
$pageTitle = 'Criar Novo Bot';
$basePath = '../../';

// Obter o ID do usuário atual
$userId = $_SESSION['user_id'];

// Verificar se o usuário tem permissão para criar bots
// Verificar limite de bots
$botsCount = getCountWhere($conn, 'bots', "created_by = $userId");
$maxBots = 5; // Defina o limite de bots por usuário (pode ser dinâmico baseado no tipo de conta)

// Verificar se a conta do usuário está ativa
$userStmt = $conn->prepare("SELECT status, expiry_date FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

if ($user['status'] != 'active' || isExpired($user['expiry_date'])) {
    $_SESSION['error'] = "Sua conta está suspensa ou expirada. Entre em contato com o administrador para reativá-la.";
    header('Location: index.php');
    exit;
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar campos obrigatórios
    $name = trim($_POST['name']);
    $provider = $_POST['provider'];
    
    // Validação básica
    if (empty($name)) {
        $error = "O nome do bot é obrigatório.";
    } elseif (strlen($name) < 3 || strlen($name) > 50) {
        $error = "O nome do bot deve ter entre 3 e 50 caracteres.";
    } elseif (!in_array($provider, ['PG', 'Pragmatic'])) {
        $error = "Provedor inválido.";
    } elseif ($botsCount >= $maxBots) {
        $error = "Você atingiu o limite de $maxBots bots. Entre em contato com o administrador para aumentar seu limite.";
    } else {
        // Verificar se já existe um bot com o mesmo nome
        $checkStmt = $conn->prepare("SELECT id FROM bots WHERE name = ? AND created_by = ?");
        $checkStmt->bind_param("si", $name, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $error = "Você já tem um bot com este nome. Por favor, escolha outro nome.";
        } else {
            // Gerar token do Telegram
            $telegramToken = generateBotToken();
            
            // Definir data de expiração para 30 dias a partir de hoje
            $expiryDate = date('Y-m-d', strtotime('+30 days'));
            
            // Inserir o novo bot
            $insertStmt = $conn->prepare("
                INSERT INTO bots (name, telegram_token, provider, created_by, expiry_date, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            
            $insertStmt->bind_param("sssss", $name, $telegramToken, $provider, $userId, $expiryDate);
            
            if ($insertStmt->execute()) {
                $newBotId = $conn->insert_id;
                $_SESSION['success'] = "Bot criado com sucesso!";
                header("Location: view.php?id=$newBotId");
                exit;
            } else {
                $error = "Erro ao criar bot: " . $conn->error;
            }
        }
    }
}

// Incluir header
include '../../includes/header.php';
?>

<!-- Conteúdo principal -->
<main class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Criar Novo Bot</h1>
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
    
    <!-- Aviso de limite de bots -->
    <div class="alert alert-info mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-info-circle fa-2x me-3"></i>
            <div>
                <h5 class="alert-heading mb-1">Informações Importantes</h5>
                <p class="mb-0">
                    Você atualmente possui <strong><?php echo $botsCount; ?></strong> de <strong><?php echo $maxBots; ?></strong> bots permitidos.
                    Os bots criados terão validade de <strong>30 dias</strong> a partir da data de criação.
                </p>
            </div>
        </div>
    </div>
    
    <div class="card shadow">
        <div class="card-body">
            <form method="post" action="">
                <div class="mb-3">
                    <label for="name" class="form-label">Nome do Bot <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" required 
                           minlength="3" maxlength="50" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
                    <div class="form-text">Escolha um nome único e descritivo para o seu bot (3-50 caracteres).</div>
                </div>
                
                <div class="mb-4">
                    <label for="provider" class="form-label">Provedor <span class="text-danger">*</span></label>
                    <div class="provider-selection">
                        <div class="form-check form-check-inline provider-option">
                            <input class="form-check-input" type="radio" name="provider" id="provider_pg" value="PG" required
                                <?php echo (!isset($provider) || $provider == 'PG') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="provider_pg">
                                <div class="provider-icon">
                                    <i class="fas fa-dice text-primary"></i>
                                </div>
                                <span>PG Soft</span>
                            </label>
                        </div>
                        
                        <div class="form-check form-check-inline provider-option">
                            <input class="form-check-input" type="radio" name="provider" id="provider_pragmatic" value="Pragmatic"
                                <?php echo (isset($provider) && $provider == 'Pragmatic') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="provider_pragmatic">
                                <div class="provider-icon">
                                    <i class="fas fa-gamepad text-success"></i>
                                </div>
                                <span>Pragmatic Play</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-text">Selecione o provedor de jogos para este bot. Este campo não poderá ser alterado posteriormente.</div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="button" class="btn btn-outline-secondary me-md-2" onclick="window.location.href='index.php'">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-robot me-1"></i> Criar Bot
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<style>
    .provider-selection {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 10px;
    }
    
    .provider-option {
        flex: 1;
        min-width: 200px;
        margin: 0;
    }
    
    .provider-option .form-check-label {
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
    
    .provider-option .form-check-input:checked + .form-check-label {
        background-color: rgba(78, 115, 223, 0.1);
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }
    
    .provider-icon {
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
    
    .provider-option:hover .form-check-label {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .form-check-input {
        position: absolute;
        opacity: 0;
    }
</style>

<?php include '../../includes/footer.php'; ?>