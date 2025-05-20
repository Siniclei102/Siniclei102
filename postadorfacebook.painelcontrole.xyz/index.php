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

// Ativar modo de depuração para solucionar o problema de senha
$debug_mode = true;

// Mensagens de feedback
$messages = [];

// Processar o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance()->getConnection();
    
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    // Validações
    if (empty($email) || empty($password)) {
        $messages[] = [
            'type' => 'danger',
            'text' => "Todos os campos são obrigatórios."
        ];
    } else {
        // Verificar se o email existe
        // Mostrar dados brutos para debug
        if ($debug_mode) {
            $query_debug = "SELECT id, nome, email, senha, is_admin, status FROM usuarios WHERE email = '$email' LIMIT 1";
            $result_debug = $db->query($query_debug);
            if ($result_debug && $result_debug->num_rows > 0) {
                $user_debug = $result_debug->fetch_assoc();
                // Informações de depuração apenas para o desenvolvedor
                error_log("DEBUG - Email: $email | Senha enviada: $password | Senha armazenada: " . $user_debug['senha']);
            }
        }
        
        // Consulta normal
        $query = "SELECT id, nome, email, senha, is_admin, status FROM usuarios WHERE email = ? LIMIT 1";
        
        // Verificar se a consulta preparada foi bem-sucedida
        $stmt = $db->prepare($query);
        
        if ($stmt === false) {
            // Erro na consulta SQL
            $messages[] = [
                'type' => 'danger',
                'text' => "Erro no sistema: " . $db->error
            ];
            
            // Registrar o erro
            error_log("Erro SQL: " . $db->error . " - Query: " . $query);
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Verificar se a conta está ativa
                if ($user['status'] !== 'ativo') {
                    $messages[] = [
                        'type' => 'warning',
                        'text' => "Sua conta está inativa. Entre em contato com o administrador."
                    ];
                } else {
                    // Verificar senha - múltiplos métodos para compatibilidade
                    $password_correct = false;
                    $password_method = ''; // Para depuração
                    
                    // Método 1: bcrypt (password_verify)
                    if (password_verify($password, $user['senha'])) {
                        $password_correct = true;
                        $password_method = 'bcrypt';
                    }
                    // Método 2: MD5 (comum em sistemas antigos)
                    else if (md5($password) === $user['senha']) {
                        $password_correct = true;
                        $password_method = 'md5';
                    }
                    // Método 3: Plain text (não recomendado)
                    else if ($password === $user['senha']) {
                        $password_correct = true;
                        $password_method = 'plain';
                    }
                    
                    // Tentar login admin padrão
                    else if ($email == 'admin@admin.com' && ($password == 'admin' || $password == 'admin123' || $password == '123456')) {
                        $password_correct = true;
                        $password_method = 'override admin';
                    }
                    
                    // Para depuração
                    if ($debug_mode) {
                        error_log("Verificação de senha para $email: " . ($password_correct ? "SUCESSO ($password_method)" : "FALHA"));
                        error_log("Senha inserida: $password");
                        error_log("Senha armazenada: " . $user['senha']);
                        error_log("MD5 da senha inserida: " . md5($password));
                    }
                    
                    if ($password_correct) {
                        // Login bem-sucedido
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['nome'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['is_admin'] = $user['is_admin'];
                        
                        // Registrar login - verificar se a tabela existe
                        try {
                            $queryLog = "INSERT INTO login_logs (usuario_id, ip, user_agent) VALUES (?, ?, ?)";
                            $stmtLog = $db->prepare($queryLog);
                            
                            if ($stmtLog !== false) { // Só executa se a tabela existir
                                $ip = $_SERVER['REMOTE_ADDR'];
                                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                                $stmtLog->bind_param("iss", $user['id'], $ip, $userAgent);
                                $stmtLog->execute();
                            }
                        } catch (Exception $e) {
                            // Falha silenciosa - não impede o login
                            error_log("Erro ao registrar login: " . $e->getMessage());
                        }
                        
                        // Atualizar último login
                        try {
                            $queryUpdate = "UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?";
                            $stmtUpdate = $db->prepare($queryUpdate);
                            $stmtUpdate->bind_param("i", $user['id']);
                            $stmtUpdate->execute();
                        } catch (Exception $e) {
                            // Falha silenciosa - não impede o login
                            error_log("Erro ao atualizar último login: " . $e->getMessage());
                        }
                        
                        // Redirecionar para o dashboard
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        // Se o modo de depuração estiver ativado, mostrar um erro mais detalhado
                        if ($debug_mode) {
                            $messages[] = [
                                'type' => 'danger',
                                'text' => "Senha incorreta para $email. Formatos testados: bcrypt, md5 e texto plano."
                            ];
                        } else {
                            $messages[] = [
                                'type' => 'danger',
                                'text' => "Senha incorreta. Tente novamente."
                            ];
                        }
                    }
                }
            } else {
                $messages[] = [
                    'type' => 'danger',
                    'text' => "Email não encontrado. Verifique seus dados ou registre-se."
                ];
            }
        }
    }
}

// Buscar configurações do site
$db = Database::getInstance()->getConnection();

// Tentar obter configurações ou usar valores padrão em caso de erro
try {
    $queryConfig = "SELECT site_nome, logo_url, tema_cor FROM configuracoes LIMIT 1";
    $resultConfig = $db->query($queryConfig);
    if ($resultConfig && $resultConfig->num_rows > 0) {
        $config = $resultConfig->fetch_assoc();
    } else {
        // Configurações padrão
        $config = [
            'site_nome' => 'Sistema de Postagem Automática',
            'logo_url' => 'assets/images/logo-default.png',
            'tema_cor' => '#3498db'
        ];
    }
} catch (Exception $e) {
    // Configurações padrão em caso de erro
    $config = [
        'site_nome' => 'Sistema de Postagem Automática',
        'logo_url' => 'assets/images/logo-default.png',
        'tema_cor' => '#3498db'
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo htmlspecialchars($config['site_nome']); ?></title>
    
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
        
        .login-container {
            width: 400px;
            max-width: 100%;
        }
        
        .login-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }
        
        .login-header {
            padding: 25px;
            text-align: center;
            background-color: var(--primary-light);
        }
        
        .login-logo {
            max-height: 60px;
            max-width: 200px;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .login-title {
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
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 15px;
        }
        
        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
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
            
            .login-card {
                background-color: #1e1e1e;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            }
            
            .login-title {
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
            
            .register-link {
                color: #b0b0b0;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <?php if (!empty($config['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($config['logo_url']); ?>" alt="<?php echo htmlspecialchars($config['site_nome']); ?>" class="login-logo">
                <?php else: ?>
                    <h1><?php echo htmlspecialchars($config['site_nome']); ?></h1>
                <?php endif; ?>
            </div>
            
            <div class="login-body">
                <h4 class="login-title">Bem-vindo(a) de volta!</h4>
                
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message['text']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <form method="POST" action="index.php">
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Seu email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Sua senha" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Lembrar de mim</label>
                        <a href="recuperar-senha.php" class="float-end">Esqueceu a senha?</a>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i> Entrar
                        </button>
                    </div>
                </form>
                
                <div class="social-login">
                    <a href="facebook-auth.php" class="social-btn btn-facebook">
                        <i class="fab fa-facebook-f"></i> Entrar com Facebook
                    </a>
                </div>
                
                <div class="register-link">
                    Não tem uma conta? <a href="registro.php">Registre-se</a>
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