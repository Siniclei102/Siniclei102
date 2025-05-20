<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Iniciar sessão
session_start();

// Se o usuário não está logado, redirecionar para login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Verificar se o usuário é um administrador
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Obter informações do usuário
$query = "SELECT nome, email, validade_ate, suspenso FROM usuarios WHERE id = ? LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Definir status da assinatura
$accountStatus = 'active';
$diasRestantes = 0;

if ($user['validade_ate'] !== null) {
    $validadeAte = new DateTime($user['validade_ate']);
    $hoje = new DateTime();
    
    if ($validadeAte < $hoje || $user['suspenso'] == 1) {
        $accountStatus = 'expired';
    } else {
        $diasRestantes = $hoje->diff($validadeAte)->days;
        if ($diasRestantes <= 5) {
            $accountStatus = 'warning';
        }
    }
}

// Mensagens de feedback
$messages = [];

// Processar simulação de renovação (em produção, integraria com gateway de pagamento)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'])) {
    $plan = intval($_POST['plan']);
    
    // Mapear planos para adicionar dias
    $addDays = [
        1 => 30,  // Plano mensal
        2 => 90,  // Plano trimestral
        3 => 365  // Plano anual
    ];
    
    if (isset($addDays[$plan])) {
        // Definir nova data de validade
        $hoje = new DateTime();
        $novaValidade = $hoje->add(new DateInterval("P{$addDays[$plan]}D"));
        $novaValidadeStr = $novaValidade->format('Y-m-d');
        
        // Atualizar validade no banco de dados
        $queryUpdate = "UPDATE usuarios SET validade_ate = ?, suspenso = 0 WHERE id = ?";
        $stmtUpdate = $db->prepare($queryUpdate);
        $stmtUpdate->bind_param("si", $novaValidadeStr, $userId);
        
        if ($stmtUpdate->execute()) {
            $messages[] = [
                'type' => 'success',
                'text' => "Assinatura renovada com sucesso! Sua conta é válida até " . $novaValidade->format('d/m/Y') . "."
            ];
            
            // Atualizar dados do usuário
            $user['validade_ate'] = $novaValidadeStr;
            $user['suspenso'] = 0;
            
            // Atualizar status
            $accountStatus = 'active';
            
            // Limpar alerta de validade, se existir
            if (isset($_SESSION['validity_alert'])) {
                unset($_SESSION['validity_alert']);
            }
        } else {
            $messages[] = [
                'type' => 'danger',
                'text' => "Erro ao renovar assinatura: " . $db->error
            ];
        }
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Plano inválido selecionado."
        ];
    }
}

// Buscar configurações do site
$queryConfig = "SELECT site_nome, logo_url, tema_cor FROM configuracoes LIMIT 1";
$resultConfig = $db->query($queryConfig);
$config = $resultConfig->num_rows > 0 ? $resultConfig->fetch_assoc() : [
    'site_nome' => 'Sistema de Postagem Automática',
    'logo_url' => 'assets/images/logo-default.png',
    'tema_cor' => '#3498db'
];

// Incluir cabeçalho
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-sync-alt me-2 text-primary"></i> Renovação de Assinatura
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
                    
                    <!-- Status da Conta -->
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-10">
                            <div class="account-status-card <?php echo $accountStatus; ?>">
                                <div class="account-status-icon">
                                    <?php if ($accountStatus === 'active'): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php elseif ($accountStatus === 'warning'): ?>
                                        <i class="fas fa-exclamation-triangle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="account-status-content">
                                    <h4>
                                        <?php if ($accountStatus === 'active'): ?>
                                            Conta Ativa
                                        <?php elseif ($accountStatus === 'warning'): ?>
                                            Atenção! Assinatura Próxima de Expirar
                                        <?php else: ?>
                                            Conta Suspensa - Assinatura Expirada
                                        <?php endif; ?>
                                    </h4>
                                    
                                    <p>
                                        <?php if ($user['validade_ate'] !== null): ?>
                                            <?php if ($accountStatus !== 'expired'): ?>
                                                Sua assinatura é válida até <strong><?php echo date('d/m/Y', strtotime($user['validade_ate'])); ?></strong> 
                                                (mais <?php echo $diasRestantes; ?> dias).
                                            <?php else: ?>
                                                Sua assinatura expirou em <strong><?php echo date('d/m/Y', strtotime($user['validade_ate'])); ?></strong>.
                                                Renove agora para restaurar o acesso completo.
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Sua conta não possui data de validade definida.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Planos de Assinatura -->
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="pricing-card">
                                <div class="pricing-header">
                                    <h5>Plano Mensal</h5>
                                    <div class="pricing">
                                        <span class="currency">R$</span>
                                        <span class="amount">49</span>
                                        <span class="period">/mês</span>
                                    </div>
                                </div>
                                <div class="pricing-body">
                                    <ul class="pricing-features">
                                        <li><i class="fas fa-check"></i> Acesso a todas as ferramentas</li>
                                        <li><i class="fas fa-check"></i> 30 dias de acesso</li>
                                        <li><i class="fas fa-check"></i> 50 grupos por campanha</li>
                                        <li><i class="fas fa-check"></i> 10 campanhas simultâneas</li>
                                        <li><i class="fas fa-check"></i> Suporte por email</li>
                                    </ul>
                                    <form method="POST" action="renovar.php">
                                        <input type="hidden" name="plan" value="1">
                                        <button type="submit" class="btn btn-primary btn-block">Assinar Agora</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <div class="pricing-card popular">
                                <div class="popular-badge">Mais Popular</div>
                                <div class="pricing-header">
                                    <h5>Plano Trimestral</h5>
                                    <div class="pricing">
                                        <span class="currency">R$</span>
                                        <span class="amount">129</span>
                                        <span class="period">/trimestre</span>
                                    </div>
                                    <div class="pricing-save">Economize 12%</div>
                                </div>
                                <div class="pricing-body">
                                    <ul class="pricing-features">
                                        <li><i class="fas fa-check"></i> Acesso a todas as ferramentas</li>
                                        <li><i class="fas fa-check"></i> 90 dias de acesso</li>
                                        <li><i class="fas fa-check"></i> 100 grupos por campanha</li>
                                        <li><i class="fas fa-check"></i> 25 campanhas simultâneas</li>
                                        <li><i class="fas fa-check"></i> Suporte prioritário</li>
                                    </ul>
                                    <form method="POST" action="renovar.php">
                                        <input type="hidden" name="plan" value="2">
                                        <button type="submit" class="btn btn-primary btn-block">Assinar Agora</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <div class="pricing-card">
                                <div class="pricing-header">
                                    <h5>Plano Anual</h5>
                                    <div class="pricing">
                                        <span class="currency">R$</span>
                                        <span class="amount">399</span>
                                        <span class="period">/ano</span>
                                    </div>
                                    <div class="pricing-save">Economize 32%</div>
                                </div>
                                <div class="pricing-body">
                                    <ul class="pricing-features">
                                        <li><i class="fas fa-check"></i> Acesso a todas as ferramentas</li>
                                        <li><i class="fas fa-check"></i> 365 dias de acesso</li>
                                        <li><i class="fas fa-check"></i> Grupos ilimitados</li>
                                        <li><i class="fas fa-check"></i> 50 campanhas simultâneas</li>
                                        <li><i class="fas fa-check"></i> Suporte VIP 24/7</li>
                                    </ul>
                                    <form method="POST" action="renovar.php">
                                        <input type="hidden" name="plan" value="3">
                                        <button type="submit" class="btn btn-primary btn-block">Assinar Agora</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Instruções e Informações Adicionais -->
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="payment-info">
                                <h5><i class="fas fa-info-circle me-2"></i> Informações Importantes</h5>
                                <p>Ao renovar sua assinatura, você terá acesso imediato a todas as funcionalidades do sistema de postagem automática.</p>
                                <p>Se tiver alguma dúvida sobre os planos ou processo de pagamento, entre em contato com nosso suporte.</p>
                                <p><strong>Nota:</strong> Este é um ambiente de simulação. Em um sistema real, aqui seria integrado um gateway de pagamento seguro.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Estilos para cards de status da conta */
.account-status-card {
    display: flex;
    align-items: center;
    padding: 25px;
    border-radius: 15px;
    background-color: #f8f9fa;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    margin-bottom: 30px;
    transition: all 0.3s;
}

.account-status-card.active {
    border-left: 5px solid #28a745;
}

.account-status-card.warning {
    border-left: 5px solid #ffc107;
    background-color: #fff8e1;
}

.account-status-card.expired {
    border-left: 5px solid #dc3545;
    background-color: #ffebee;
}

.account-status-icon {
    font-size: 40px;
    margin-right: 25px;
}

.account-status-card.active .account-status-icon {
    color: #28a745;
}

.account-status-card.warning .account-status-icon {
    color: #ffc107;
}

.account-status-card.expired .account-status-icon {
    color: #dc3545;
}

.account-status-content h4 {
    margin-bottom: 10px;
    font-weight: 600;
}

.account-status-content p {
    margin-bottom: 0;
    color: #555;
    font-size: 16px;
}

/* Estilos para cards de preços */
.pricing-card {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    background-color: #fff;
    transition: all 0.3s;
    height: 100%;
    position: relative;
}

.pricing-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.pricing-card.popular {
    transform: scale(1.03);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 2px solid var(--primary-color);
}

.pricing-card.popular:hover {
    transform: translateY(-10px) scale(1.03);
}

.popular-badge {
    position: absolute;
    top: 15px;
    right: -30px;
    background-color: var(--primary-color);
    color: white;
    padding: 5px 30px;
    font-size: 12px;
    font-weight: 600;
    transform: rotate(45deg);
    width: 150px;
    text-align: center;
    z-index: 1;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.pricing-header {
    background-color: #f8f9fa;
    padding: 25px;
    text-align: center;
    border-bottom: 1px solid #eee;
}

.pricing-card.popular .pricing-header {
    background-color: var(--primary-light);
}

.pricing-header h5 {
    margin-bottom: 15px;
    font-weight: 600;
}

.pricing {
    margin-bottom: 15px;
    position: relative;
    display: inline-block;
}

.currency {
    font-size: 20px;
    font-weight: 600;
    position: relative;
    top: -15px;
}

.amount {
    font-size: 48px;
    font-weight: 700;
    line-height: 1;
}

.period {
    font-size: 14px;
    color: #666;
}

.pricing-save {
    font-size: 14px;
    font-weight: 600;
    color: var(--primary-color);
}

.pricing-body {
    padding: 25px;
}

.pricing-features {
    list-style: none;
    padding: 0;
    margin: 0 0 25px;
}

.pricing-features li {
    margin-bottom: 12px;
    display: flex;
    align-items: center;
}

.pricing-features i {
    margin-right: 10px;
    color: var(--primary-color);
    font-size: 14px;
}

.btn-block {
    display: block;
    width: 100%;
}

/* Informações de pagamento */
.payment-info {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    border-left: 4px solid #17a2b8;
}

.payment-info h5 {
    color: #17a2b8;
    margin-bottom: 15px;
    font-weight: 600;
}

.payment-info p:last-child {
    margin-bottom: 0;
}

/* Modo escuro */
@media (prefers-color-scheme: dark) {
    .account-status-card {
        background-color: #2a2a2a;
    }
    
    .account-status-card.warning {
        background-color: #332d00;
    }
    
    .account-status-card.expired {
        background-color: #330a0a;
    }
    
    .account-status-content p {
        color: #e1e1e1;
    }
    
    .pricing-card {
        background-color: #1e1e1e;
    }
    
    .pricing-header {
        background-color: #2a2a2a;
        border-bottom-color: #333;
    }
    
    .pricing-card.popular .pricing-header {
        background-color: rgba(52, 152, 219, 0.2);
    }
    
    .period {
        color: #b0b0b0;
    }
    
    .payment-info {
        background-color: #2a2a2a;
    }
}
</style>

<?php
// Incluir rodapé
include 'includes/footer.php';
?>