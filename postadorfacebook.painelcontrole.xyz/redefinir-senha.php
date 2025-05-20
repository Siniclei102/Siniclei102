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
$tokenValid = false;
$tokenData = null;

// Verificar token
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $db->real_escape_string($_GET['token']);
    
    // Verificar validade do token
    $queryToken = "
        SELECT pr.*, u.nome, u.email
        FROM password_resets pr
        JOIN usuarios u ON pr.usuario_id = u.id
        WHERE pr.token = ?
        AND pr.utilizado = 0
        AND pr.expira_em > NOW()
        LIMIT 1
    ";
    
    $stmtToken = $db->prepare($queryToken);
    $stmtToken->bind_param("s", $token);
    $stmtToken->execute();
    $resultToken = $stmtToken->get_result();
    
    if ($resultToken->num_rows > 0) {
        $tokenData = $resultToken->fetch_assoc();
        $tokenValid = true;
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "O link de redefinição é inválido ou expirou."
        ];
    }
} else {
    $messages[] = [
        'type' => 'danger',
        'text' => "Token de redefinição não fornecido."
    ];
}

// Processar formulário de redefinição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // Validações
    if (empty($password)) {
        $messages[] = [
            'type' => 'danger',
            'text' => "A senha não pode estar em branco."
        ];
    } elseif (strlen($password) < 6) {
        $messages[] = [
            'type' => 'danger',
            'text' => "A senha deve ter pelo menos 6 caracteres."
        ];
    } elseif ($password !== $passwordConfirm) {
        $messages[] = [
            'type' => 'danger',
            'text' => "As senhas não correspondem."
        ];
    } else {
        // Hash da nova senha
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Atualizar senha do usuário
        $queryUpdate = "UPDATE usuarios SET senha = ? WHERE id = ?";
        $stmtUpdate = $db->prepare($queryUpdate);
        $stmtUpdate->bind_param("si", $passwordHash, $tokenData['usuario_id']);
        
        if ($stmtUpdate->execute()) {
            // Marcar token como utilizado
            $queryMarkUsed = "UPDATE password_resets SET utilizado = 1 WHERE id = ?";
            $stmtMarkUsed = $db->prepare($queryMarkUsed);
            $stmtMarkUsed->bind_param("i", $tokenData['id']);
            $stmtMarkUsed->execute();
            
            $messages[] = [
                'type' => 'success',
                'text' => "Senha redefinida com sucesso! Você já pode fazer login com sua nova senha."
            ];
            
            // Redirecionar para login
            header("Refresh: 3; URL=index.php");
            $tokenValid = false; // Para não mostrar o formulário
        } else {
            $messages[] = [
                'type' => 'danger',
                'text' => "Erro ao redefinir senha: " . $db->error
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
    <title>Redefinir Senha | <?php echo htmlspecialchars($config['site_nome']); ?></title>
    
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
        
        .reset-container {
            width: 400px;
            max-width: 100%;
        }
        
        .reset-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }
        
        .reset-header {
            padding: 25px;
            text-align: center;
            background-color: var(--primary-light);
        }
        
        .reset-logo {
            max-height: 60px;
            max-width: 200px;
        }
        
        .reset-body {
            padding: 30px;
        }
        
        .reset-title {
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
        
        .password-requirements {
            margin-top: 5px;
            font-size: 12px;
            color: #6c757d;
        }
        
        .requirement {
            margin-bottom: 3px;
        }
        
        .requirement i {
            margin-right: 5px;
        }
        
        .requirement.valid {
            color: #28a745;
        }
        
        .requirement.invalid {
            color: #dc3545;
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
            
            .reset-card {
                background-color: #1e1e1e;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            }
            
            .reset-title {
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
            
            .password-requirements {
                color: #b0b0b0;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <?php if (!empty($config['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($config['logo_url']); ?>" alt="<?php echo htmlspecialchars($config['site_nome']); ?>" class="reset-logo">
                <?php else: ?>
                    <h1><?php echo htmlspecialchars($config['site_nome']); ?></h1>
                <?php endif; ?>
            </div>
            
            <div class="reset-body">
                <h4 class="reset-title">Redefinir Senha</h4>
                
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message['text']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if ($tokenValid && $tokenData): ?>
                    <p class="text-center mb-4">
                        Olá, <?php echo htmlspecialchars($tokenData['nome']); ?>! Crie uma nova senha para sua conta.
                    </p>
                    
                    <form method="POST" action="redefinir-senha.php?token=<?php echo htmlspecialchars($_GET['token']); ?>" id="resetForm">
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Nova senha" required>
                            </div>
                            <div class="password-requirements">
                                <div class="requirement" id="length">
                                    <i class="fas fa-circle"></i> Pelo menos 6 caracteres
                                </div>
                                <div class="requirement" id="letter">
                                    <i class="fas fa-circle"></i> Uma letra (a-z, A-Z)
                                </div>
                                <div class="requirement" id="number">
                                    <i class="fas fa-circle"></i> Um número (0-9)
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Confirme a nova senha" required>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i> Redefinir Senha
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="login-link">
                        <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Voltar para login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="copyright">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config['site_nome']); ?>. Todos os direitos reservados.
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($tokenValid): ?>
    <!-- Script de validação de senha -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('password_confirm');
        const lengthReq = document.getElementById('length');
        const letterReq = document.getElementById('letter');
        const numberReq = document.getElementById('number');
        
        // Validar requisitos da senha
        password.addEventListener('input', function() {
            const value = this.value;
            
            // Validar comprimento
            if (value.length >= 6) {
                lengthReq.classList.add('valid');
                lengthReq.classList.remove('invalid');
                lengthReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                lengthReq.classList.add('invalid');
                lengthReq.classList.remove('valid');
                lengthReq.querySelector('i').className = 'fas fa-circle';
            }
            
            // Validar letra
            if (/[a-zA-Z]/.test(value)) {
                letterReq.classList.add('valid');
                letterReq.classList.remove('invalid');
                letterReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                letterReq.classList.add('invalid');
                letterReq.classList.remove('valid');
                letterReq.querySelector('i').className = 'fas fa-circle';
            }
            
            // Validar número
            if (/[0-9]/.test(value)) {
                numberReq.classList.add('valid');
                numberReq.classList.remove('invalid');
                numberReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                numberReq.classList.add('invalid');
                numberReq.classList.remove('valid');
                numberReq.querySelector('i').className = 'fas fa-circle';
            }
            
            // Verificar se as senhas correspondem
            if (confirmPassword.value && confirmPassword.value !== value) {
                confirmPassword.classList.add('is-invalid');
            } else if (confirmPassword.value) {
                confirmPassword.classList.remove('is-invalid');
                confirmPassword.classList.add('is-valid');
            }
        });
        
        // Validar confirmação de senha
        confirmPassword.addEventListener('input', function() {
            if (this.value !== password.value) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
        
        // Validar formulário antes de enviar
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('As senhas não correspondem.');
                return false;
            }
            
            if (password.value.length < 6 || !/[a-zA-Z]/.test(password.value) || !/[0-9]/.test(password.value)) {
                e.preventDefault();
                alert('Sua senha não atende aos requisitos mínimos.');
                return false;
            }
            
            return true;
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>