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

// Mensagens de feedback
$messages = [];

// Processar o formulário de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance()->getConnection();
    
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $passwordConfirm = $_POST['password_confirm'];
    
    // Validações
    $errors = [];
    
    if (empty($nome) || empty($email) || empty($password) || empty($passwordConfirm)) {
        $errors[] = "Todos os campos são obrigatórios.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Por favor, informe um email válido.";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "A senha deve conter pelo menos 6 caracteres.";
    }
    
    if ($password !== $passwordConfirm) {
        $errors[] = "As senhas não conferem.";
    }
    
    // Verificar se o email já está em uso
    $query = "SELECT id FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Este email já está em uso. Por favor, escolha outro ou faça login.";
    }
    
    // Se não há erros, criar a conta
    if (empty($errors)) {
        // Hash da senha
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Verificar se é o primeiro usuário (será admin)
        $queryCount = "SELECT COUNT(*) as total FROM usuarios";
        $resultCount = $db->query($queryCount);
        $isFirstUser = ($resultCount->fetch_assoc()['total'] === 0);
        
        // Inserir novo usuário
        $query = "INSERT INTO usuarios (nome, email, senha, is_admin, is_active) VALUES (?, ?, ?, ?, 1)";
        $stmt = $db->prepare($query);
        $isAdmin = $isFirstUser ? 1 : 0;
        $stmt->bind_param("sssi", $nome, $email, $passwordHash, $isAdmin);
        
        if ($stmt->execute()) {
            $userId = $db->insert_id;
            
            // Registrar log
            $queryLog = "INSERT INTO login_logs (usuario_id, ip, user_agent, acao) VALUES (?, ?, ?, 'registro')";
            $stmtLog = $db->prepare($queryLog);
            $ip = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            $stmtLog->bind_param("iss", $userId, $ip, $userAgent);
            $stmtLog->execute();
            
            // Exibir mensagem de sucesso
            $messages[] = [
                'type' => 'success',
                'text' => "Conta criada com sucesso! Você já pode fazer login."
            ];
            
            // Opcionalmente, fazer login automático
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $nome;
            $_SESSION['user_email'] = $email;
            $_SESSION['is_admin'] = $isAdmin;
            
            // Redirecionar para o dashboard com delay
            header("Refresh: 2; URL=dashboard.php");
        } else {
            $messages[] = [
                'type' => 'danger',
                'text' => "Erro ao criar conta: " . $db->error
            ];
        }
    } else {
        // Exibir erros de validação
        foreach ($errors as $error) {
            $messages[] = [
                'type' => 'danger',
                'text' => $error
            ];
        }
    }
}

// Buscar configurações do site
$db = Database::getInstance()->getConnection();
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
    <title>Registro | <?php echo htmlspecialchars($config['site_nome']); ?></title>
    
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
        
        .register-container {
            width: 450px;
            max-width: 100%;
        }
        
        .register-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }
        
        .register-header {
            padding: 25px;
            text-align: center;
            background-color: var(--primary-light);
        }
        
        .register-logo {
            max-height: 60px;
            max-width: 200px;
        }
        
        .register-body {
            padding: 30px;
        }
        
        .register-title {
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
        
        .social-login {
            display: flex;
            justify-content: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .social-btn {
            padding: 10px 15px;
            margin: 0 10px;
            border-radius: 10px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-facebook {
            background-color: #3b5998;
        }
        
        .btn-facebook:hover {
            background-color: #344e86;
            color: #fff;
        }
        
        .social-btn i {
            margin-right: 8px;
            font-size: 18px;
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
            
            .register-card {
                background-color: #1e1e1e;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            }
            
            .register-title {
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
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <?php if (!empty($config['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($config['logo_url']); ?>" alt="<?php echo htmlspecialchars($config['site_nome']); ?>" class="register-logo">
                <?php else: ?>
                    <h1><?php echo htmlspecialchars($config['site_nome']); ?></h1>
                <?php endif; ?>
            </div>
            
            <div class="register-body">
                <h4 class="register-title">Criar uma nova conta</h4>
                
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message['text']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <form method="POST" action="registro.php" id="registerForm">
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="nome" name="nome" placeholder="Nome completo" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Seu melhor email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Crie uma senha" required>
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
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Confirme sua senha" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            Li e aceito os <a href="termos.php" target="_blank">Termos de Uso</a> e <a href="privacidade.php" target="_blank">Política de Privacidade</a>
                        </label>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i> Criar Conta
                        </button>
                    </div>
                </form>
                
                <div class="social-login">
                    <a href="facebook-auth.php" class="social-btn btn-facebook">
                        <i class="fab fa-facebook-f"></i> Registrar com Facebook
                    </a>
                </div>
                
                <div class="login-link">
                    Já tem uma conta? <a href="index.php">Faça login</a>
                </div>
            </div>
        </div>
        
        <div class="copyright">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config['site_nome']); ?>. Todos os direitos reservados.
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
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
        document.getElementById('registerForm').addEventListener('submit', function(e) {
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
</body>
</html>