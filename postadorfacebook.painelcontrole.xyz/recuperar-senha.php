<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Iniciar sessão
session_start();

// Se o usuário já está logado, redirecionar para o dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Mensagens de feedback
$messages = [];

// Processar solicitação de recuperação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messages[] = [
            'type' => 'danger',
            'text' => "Por favor, informe um email válido."
        ];
    } else {
        // Verificar se o email existe
        $query = "SELECT id, nome FROM usuarios WHERE email = ? AND is_active = 1 LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Gerar token único
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Salvar token no banco de dados
            $queryToken = "INSERT INTO password_resets (usuario_id, token, expira_em) VALUES (?, ?, ?)";
            $stmtToken = $db->prepare($queryToken);
            $stmtToken->bind_param("iss", $user['id'], $token, $expiry);
            
            if ($stmtToken->execute()) {
                // Construir URL de recuperação
                $resetUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/redefinir-senha.php?token=" . $token;
                
                // Enviar email 
                $to = $email;
                $subject = "Recuperação de Senha - " . $config['site_nome'];
                $message = "
                    <html>
                    <head>
                        <title>Recuperação de Senha</title>
                    </head>
                    <body>
                        <p>Olá {$user['nome']},</p>
                        <p>Recebemos uma solicitação para redefinir sua senha.</p>
                        <p>Clique no link abaixo para criar uma nova senha:</p>
                        <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
                        <p>Este link expira em 24 horas.</p>
                        <p>Se você não solicitou a recuperação de senha, ignore este email.</p>
                        <p>Atenciosamente,<br>Equipe " . $config['site_nome'] . "</p>
                    </body>
                    </html>
                ";
                
                // Headers para envio de email HTML
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: " . $config['site_nome'] . " <no-reply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                
                if (mail($to, $subject, $message, $headers)) {
                    $messages[] = [
                        'type' => 'success',
                        'text' => "Enviamos instruções de recuperação de senha para seu email."
                    ];
                } else {
                    $messages[] = [
                        'type' => 'warning',
                        'text' => "Não foi possível enviar o email. Entre em contato com o suporte."
                    ];
                }
            } else {
                $messages[] = [
                    'type' => 'danger',
                    'text' => "Erro ao processar solicitação: " . $db->error
                ];
            }
        } else {
            // Para segurança, não informar se o email existe ou não
            $messages[] = [
                'type' => 'success',
                'text' => "Se o email estiver registrado, enviaremos instruções para recuperação de senha."
            ];
        }
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
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha | <?php echo htmlspecialchars($config['site_nome']); ?></title>
    
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
        
        .recovery-container {
            width: 400px;
            max-width: 100%;
        }
        
        .recovery-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }
        
        .recovery-header {
            padding: 25px;
            text-align: center;
            background-color: var(--primary-light);
        }
        
        .recovery-logo {
            max-height: 60px;
            max-width: 200px;
        }
        
        .recovery-body {
            padding: 30px;
        }
        
        .recovery-title {
            font-weight: 600;
            margin-bottom: 25px;
            color: #333;
            text-align: center;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 15px;
            border: 1px solid #e1e1e1;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem var(--primary-light);
        }
        
        .input-group-text {
            border-radius: 10px 0 0 10px;
            background-color: #f8f9fa;
            border: 1px solid #e1e1e1;
            border-right: none;
            color: #6c757d;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 10px;
            padding: 12px 15px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 15px;
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
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
            
            .recovery-card {
                background-color: #1e1e1e;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            }
            
            .recovery-title {
                color: #e1e1e1;
            }
            
            .form-control {
                background-color: #333;
                border-color: #444;
                color: #e1e1e1;
            }
            
            .form-control:focus {
                background-color: #333;
                color: #e1e1e1;
            }
            
            .input-group-text {
                background-color: #272727;
                border-color: #444;
                color: #e1e1e1;
            }
            
            .login-link {
                color: #b0b0b0;
            }
        }
    </style>
</head>
<body>
    <div class="recovery-container">
        <div class="recovery-card">
            <div class="recovery-header">
                <?php if (!empty($config['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($config['logo_url']); ?>" alt="<?php echo htmlspecialchars($config['site_nome']); ?>" class="recovery-logo">
                <?php else: ?>
                    <h1><?php echo htmlspecialchars($config['site_nome']); ?></h1>
                <?php endif; ?>
            </div>
            
            <div class="recovery-body">
                <h4 class="recovery-title">Recuperação de Senha</h4>
                
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message['text']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <p class="text-center mb-4">
                    Informe seu email cadastrado e enviaremos instruções para criar uma nova senha.
                </p>
                
                <form method="POST" action="recuperar-senha.php">
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Seu email" required>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i> Enviar Instruções
                        </button>
                    </div>
                </form>
                
                <div class="login-link">
                    <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Voltar para login</a>
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