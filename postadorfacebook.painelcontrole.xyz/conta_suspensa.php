<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Iniciar sessão
session_start();

// Se o usuário não está logado, redirecionar para a página de login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Obter informações do usuário
$query = "SELECT nome, email, validade_ate, suspenso FROM usuarios WHERE id = ? LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Verificar se o usuário ainda está suspenso (pode ter sido renovado em outra sessão)
if (!$user['suspenso'] && ($user['validade_ate'] === null || strtotime($user['validade_ate']) >= time())) {
    // Usuário não está mais suspenso, redirecionar para o dashboard
    header('Location: dashboard.php');
    exit;
}

// Verificar se existe data de validade ou se a conta está suspensa manualmente
$motivoSuspensao = "Sua conta está suspensa.";
if ($user['validade_ate'] !== null && strtotime($user['validade_ate']) < time()) {
    $motivoSuspensao = "Sua assinatura expirou em " . date('d/m/Y', strtotime($user['validade_ate'])) . ".";
}

// Processar logout se solicitado
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Buscar configurações do site
$queryConfig = "SELECT site_nome, logo_url, tema_cor FROM configuracoes LIMIT 1";
$resultConfig = $db->query($queryConfig);
$config = $resultConfig->num_rows > 0 ? $resultConfig->fetch_assoc() : [
    'site_nome' => 'Sistema de Postagem Automática',
    'logo_url' => 'assets/images/logo-default.png',
    'tema_cor' => '#3498db'
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conta Suspensa | <?php echo htmlspecialchars($config['site_nome']); ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Estilos Personalizados -->
    <style>
        :root {
            --primary-color: <?php echo $config['tema_cor']; ?>;
            --primary-light: <?php echo $config['tema_cor'] . '15'; ?>;
            --primary-dark: <?php echo $config['tema_cor'] . 'dd'; ?>;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .suspended-container {
            width: 550px;
            max-width: 100%;
        }
        
        .suspended-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
            text-align: center;
        }
        
        .suspended-header {
            padding: 25px;
            text-align: center;
            background-color: #dc3545;
            color: white;
        }
        
        .suspended-logo {
            max-height: 60px;
            max-width: 200px;
            margin-bottom: 20px;
        }
        
        .suspended-body {
            padding: 30px;
        }
        
        .suspended-title {
            font-weight: 600;
            margin-bottom: 25px;
            color: #333;
            font-size: 24px;
        }
        
        .suspended-icon {
            font-size: 70px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .suspended-message {
            font-size: 18px;
            margin-bottom: 25px;
            color: #555;
        }
        
        .btn {
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
        }
        
        .user-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }
        
        .user-info p {
            margin-bottom: 5px;
        }
        
        .copyright {
            text-align: center;
            margin-top: 30px;
            font-size: 13px;
            color: #6c757d;
        }
        
        /* Modo escuro */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #121212;
            }
            
            .suspended-card {
                background-color: #1e1e1e;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            }
            
            .suspended-title {
                color: #e1e1e1;
            }
            
            .suspended-message {
                color: #b0b0b0;
            }
            
            .user-info {
                background-color: #2a2a2a;
                color: #e1e1e1;
            }
        }
    </style>
</head>
<body>
    <div class="suspended-container">
        <div class="suspended-card">
            <div class="suspended-header">
                <i class="fas fa-exclamation-circle mb-2" style="font-size: 32px;"></i>
                <h4 class="mb-0">Acesso Suspenso</h4>
            </div>
            
            <div class="suspended-body">
                <div class="suspended-icon">
                    <i class="fas fa-user-lock"></i>
                </div>
                
                <h2 class="suspended-title">Sua conta está suspensa</h2>
                
                <div class="user-info">
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($user['nome']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Motivo:</strong> <?php echo htmlspecialchars($motivoSuspensao); ?></p>
                </div>
                
                <p class="suspended-message">
                    Todas as funcionalidades estão temporariamente indisponíveis.
                    Para reativar sua conta, entre em contato com o administrador ou renove sua assinatura.
                </p>
                
                <div class="d-grid gap-3">
                    <a href="contato.php" class="btn btn-primary">
                        <i class="fas fa-headset me-2"></i> Falar com Suporte
                    </a>
                    <a href="?logout=1" class="btn btn-outline-secondary">
                        <i class="fas fa-sign-out-alt me-2"></i> Sair
                    </a>
                </div>
            </div>
        </div>
        
        <div class="copyright">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config['site_nome']); ?>. Todos os direitos reservados.
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>