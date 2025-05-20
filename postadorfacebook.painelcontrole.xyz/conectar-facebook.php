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

// URL de redirecionamento
$redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/callback-facebook.php';

// Verificar se estamos recebendo um código de autorização
if (isset($_GET['code']) && isset($_GET['state'])) {
    // Verificar state para evitar CSRF
    if (!isset($_SESSION['fb_state']) || $_GET['state'] !== $_SESSION['fb_state']) {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Erro de validação do estado. Tente novamente.'
        ];
        
        header('Location: conectar-facebook.php');
        exit;
    }
    
    // Trocar código por token de acesso
    $tokenData = $fb->getAccessToken($_GET['code'], $redirectUrl);
    
    if ($tokenData) {
        // Salvar token no banco de dados
        $fb->updateUserToken($userId, $tokenData['access_token'], $tokenData['expiry_date']);
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => 'Conexão com o Facebook realizada com sucesso!'
        ];
        
        header('Location: metricas-facebook.php');
        exit;
    } else {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Erro ao obter token de acesso do Facebook.'
        ];
    }
}

// Obter informações do usuário atual
$query = "SELECT nome, facebook_token, facebook_token_expiry FROM usuarios WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// Verificar se já possui token válido
$tokenValido = false;
$tempoRestante = '';

if ($usuario['facebook_token'] && $usuario['facebook_token_expiry']) {
    $expiry = new DateTime($usuario['facebook_token_expiry']);
    $now = new DateTime();
    
    if ($expiry > $now) {
        $tokenValido = true;
        $diff = $now->diff($expiry);
        
        if ($diff->days > 0) {
            $tempoRestante = "{$diff->days} dias";
        } else {
            $tempoRestante = "{$diff->h} horas e {$diff->i} minutos";
        }
    }
}

// Gerar URL de login do Facebook
$loginUrl = $fb->getLoginUrl($redirectUrl);

// Incluir o cabeçalho
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h3 mb-0 text-gray-800">Conectar com Facebook</h1>
            <p class="mb-0 text-muted">Conecte sua conta para obter métricas detalhadas</p>
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
    
    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="modern-card">
                <div class="modern-card-body text-center pb-5">
                    <?php if ($tokenValido): ?>
                        <!-- Já está conectado -->
                        <div class="fb-connected">
                            <div class="fb-connected-icon mb-3">
                                <i class="fab fa-facebook-square"></i>
                            </div>
                            <h3 class="fb-connected-title mb-4">Conta já conectada!</h3>
                            <p class="fb-connected-description">
                                Sua conta do Facebook já está conectada ao sistema. Você pode acessar as métricas detalhadas das suas postagens.
                            </p>
                            <div class="fb-token-info mb-4">
                                <span class="fb-token-label">Validade da conexão:</span>
                                <span class="fb-token-value"><?php echo $tempoRestante; ?></span>
                            </div>
                            <a href="metricas-facebook.php" class="btn btn-primary btn-lg mb-3">
                                <i class="fas fa-chart-bar me-2"></i> Acessar Métricas
                            </a>
                            <div class="mt-3">
                                <a href="?reconnect=1" class="text-muted reconnect-link">
                                    <i class="fas fa-sync-alt me-1"></i> Reconectar conta
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Conectar com Facebook -->
                        <div class="fb-connect">
                            <div class="fb-connect-icon mb-4">
                                <i class="fab fa-facebook"></i>
                            </div>
                            <h3 class="fb-connect-title mb-4">Conectar com o Facebook</h3>
                            <p class="fb-connect-description mb-5">
                                Para obter métricas detalhadas sobre suas postagens, é necessário conectar sua conta do Facebook ao nosso sistema. Isso permitirá acessar dados de engajamento como curtidas, comentários e compartilhamentos.
                            </p>
                            
                            <div class="fb-permissions">
                                <h5 class="fb-permissions-title">Permissões solicitadas:</h5>
                                <ul class="fb-permissions-list">
                                    <li><i class="fas fa-check me-2"></i> Acesso ao seu perfil público</li>
                                    <li><i class="fas fa-check me-2"></i> Acesso aos grupos que você administra</li>
                                    <li><i class="fas fa-check me-2"></i> Métricas de engajamento das suas postagens</li>
                                </ul>
                            </div>
                            
                            <div class="fb-note mb-5">
                                <i class="fas fa-info-circle me-2"></i> Não postamos ou alteramos nada na sua conta sem sua permissão explícita.
                            </div>
                            
                            <a href="<?php echo $loginUrl; ?>" class="btn btn-facebook btn-lg">
                                <i class="fab fa-facebook me-2"></i> Conectar com Facebook
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
.fb-connect-icon {
    font-size: 5rem;
    color: #1877f2;
    margin: 20px 0;
}

.fb-connect-title {
    font-weight: 700;
    color: #333;
}

.fb-connect-description {
    color: #666;
    max-width: 500px;
    margin: 0 auto 30px;
}

.fb-permissions {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 30px;
    text-align: left;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.fb-permissions-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.fb-permissions-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.fb-permissions-list li {
    margin-bottom: 10px;
    color: #444;
}

.fb-note {
    font-size: 0.85rem;
    color: #666;
}

.btn-facebook {
    background-color: #1877f2;
    border-color: #1877f2;
    color: white;
    font-weight: 600;
    padding: 12px 24px;
}

.btn-facebook:hover {
    background-color: #166fe5;
    border-color: #166fe5;
    color: white;
}

.fb-connected-icon {
    font-size: 5rem;
    color: #1877f2;
    margin: 20px 0;
}

.fb-connected-title {
    font-weight: 700;
    color: #28a745;
}

.fb-token-info {
    background-color: #f8f9fa;
    border-radius: 50px;
    padding: 10px 20px;
    display: inline-block;
}

.fb-token-label {
    font-weight: 600;
    margin-right: 10px;
}

.fb-token-value {
    color: #1877f2;
    font-weight: 600;
}

.reconnect-link {
    font-size: 0.9rem;
    text-decoration: none;
}

.reconnect-link:hover {
    text-decoration: underline;
}
</style>

<?php
// Incluir o rodapé
include 'includes/footer.php';
?>