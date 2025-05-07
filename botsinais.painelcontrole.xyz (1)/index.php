<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Verificar cookie de "lembrar usuário"
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_user'])) {
    list($user_id, $token) = explode(':', $_COOKIE['remember_user']);
    
    // Verificar token na base de dados
    $stmt = $conn->prepare("SELECT id, username, role, status, expiry_date FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verificar status e data de expiração
        if ($user['status'] == 'active' && !isExpired($user['expiry_date'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Redirecionar para o dashboard.php (que direcionará para o local correto)
            header('Location: dashboard.php');
            exit;
        }
    }
}

// Redirecionar se já estiver logado
if (isset($_SESSION['user_id'])) {
    // Redirecionar para o dashboard.php (que direcionará para o local correto)
    header('Location: dashboard.php');
    exit;
}

// Processar login
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Limpar dados de entrada
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validar campos
    if (empty($username) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        // Verificar credenciais
        $stmt = $conn->prepare("SELECT id, username, password, role, status, expiry_date FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verificar senha
            if (password_verify($password, $user['password'])) {
                // Verificar status e data de expiração
                if ($user['status'] == 'suspended') {
                    $error = 'Sua conta está suspensa. Entre em contato com o administrador.';
                } elseif (isExpired($user['expiry_date'])) {
                    $error = 'Sua assinatura expirou. Entre em contato para renovação.';
                    
                    // Atualizar status para suspenso
                    $updateStmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
                    $updateStmt->bind_param("i", $user['id']);
                    $updateStmt->execute();
                    
                    // Suspender bots e usuários relacionados se for admin
                    if ($user['role'] == 'admin') {
                        // Suspender bots
                        $botStmt = $conn->prepare("UPDATE bots SET status = 'inactive' WHERE created_by = ? OR master_id = ?");
                        $botStmt->bind_param("ii", $user['id'], $user['id']);
                        $botStmt->execute();
                        
                        // Suspender usuários vinculados
                        $userStmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE bot_id IN (SELECT id FROM bots WHERE created_by = ? OR master_id = ?)");
                        $userStmt->bind_param("ii", $user['id'], $user['id']);
                        $userStmt->execute();
                    }
                } else {
                    // Login bem-sucedido
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Configurar cookie "lembrar usuário" se selecionado
                    if ($remember) {
                        $token = bin2hex(random_bytes(16)); // Token seguro
                        $cookie_value = $user['id'] . ':' . $token;
                        setcookie('remember_user', $cookie_value, time() + 30 * 24 * 60 * 60, '/'); // 30 dias
                    }
                    
                    // Atualizar último login
                    $updateLoginStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateLoginStmt->bind_param("i", $user['id']);
                    $updateLoginStmt->execute();
                    
                    // Redirecionar para o dashboard.php (que direcionará para o local correto)
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = 'Senha incorreta!';
            }
        } else {
            $error = 'Usuário não encontrado!';
        }
    }
}

// Obter logo do site
$siteLogo = getSetting($conn, 'site_logo');
$siteName = getSetting($conn, 'site_name');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $siteName ?: 'BotDeSinais'; ?> - Login</title>
    
    <!-- Estilos CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #2e59d9;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --purple-color: #8540f5;
            --pink-color: #e83e8c;
            --orange-color: #fd7e14;
            --teal-color: #20c9a6;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #8540f5 0%, #4e73df 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            padding: 0;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--purple-color) 100%);
            padding: 30px 20px;
            text-align: center;
            border-radius: 16px 16px 0 0;
        }
        
        /* Logo simplificada - sem container especial */
        .site-logo {
            max-width: 200px;
            max-height: 100px;
            margin: 0 auto;
            display: block;
            position: relative;
            z-index: 10;
        }
        
        .login-body {
            padding: 40px 30px 30px;
            position: relative;
            z-index: 5;
        }
        
        .login-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .login-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating .form-control {
            border-radius: 50px;
            height: 60px;
            padding-left: 60px;
            font-size: 16px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        
        .form-floating .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
            border-width: 2px;
        }
        
        .form-floating > label {
            padding-left: 60px;
            height: 60px;
            padding-top: 19px;
            opacity: 0.6;
        }
        
        .input-icon {
            position: absolute;
            left: 20px;
            top: 19px;
            font-size: 20px;
            z-index: 10;
        }
        
        .username-icon {
            color: var(--purple-color);
        }
        
        .password-icon {
            color: var(--info-color);
        }
        
        .login-button {
            background: linear-gradient(135deg, var(--info-color) 0%, var(--teal-color) 100%);
            border: none;
            border-radius: 50px;
            height: 60px;
            font-weight: 600;
            font-size: 18px;
            letter-spacing: 1px;
            margin-top: 10px;
            box-shadow: 0 10px 20px rgba(54, 185, 204, 0.3);
            transition: all 0.3s;
        }
        
        .login-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(54, 185, 204, 0.4);
            background: linear-gradient(135deg, var(--teal-color) 0%, var(--info-color) 100%);
        }
        
        .login-button .login-icon {
            margin-right: 8px;
            font-size: 20px;
        }
        
        .login-footer {
            text-align: center;
            padding: 15px 0;
            color: #666;
            font-size: 12px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .alert {
            margin-bottom: 20px;
            border-radius: 12px;
            padding: 15px;
            font-size: 14px;
            display: flex;
            align-items: center;
            border: none;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .alert-danger {
            background-color: rgba(231, 74, 59, 0.1);
            color: #721c24;
        }
        
        .alert-icon {
            background: rgba(231, 74, 59, 0.2);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--danger-color);
        }
        
        /* Estilo do checkbox personalizado para "Lembrar-me" */
        .form-check {
            padding-left: 0;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        
        .form-check-input {
            display: none; /* Ocultar checkbox padrão */
        }
        
        .custom-checkbox {
            display: inline-block;
            position: relative;
            padding-left: 35px;
            cursor: pointer;
            font-size: 14px;
            user-select: none;
            margin-right: 5px;
            color: #555;
        }
        
        .custom-checkbox:hover {
            color: var(--primary-color);
        }
        
        .custom-checkbox::before {
            content: '';
            position: absolute;
            left: 0;
            top: -2px;
            width: 24px;
            height: 24px;
            border: 2px solid #ccc;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .form-check-input:checked + .custom-checkbox::before {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .custom-checkbox::after {
            content: '\f00c';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            left: 5px;
            top: 0px;
            color: white;
            font-size: 14px;
            opacity: 0;
            transition: all 0.3s;
        }
        
        .form-check-input:checked + .custom-checkbox::after {
            opacity: 1;
        }
        
        /* Responsividade */
        @media (max-width: 576px) {
            .login-card {
                border-radius: 12px;
            }
            
            .login-header {
                padding: 20px;
                border-radius: 12px 12px 0 0;
            }
            
            .site-logo {
                max-width: 160px;
                max-height: 80px;
            }
            
            .login-body {
                padding: 30px 20px 20px;
            }
            
            .login-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <!-- Logo simplificada -->
                <img src="assets/img/<?php echo $siteLogo ?: 'logo.png'; ?>" alt="<?php echo $siteName ?: 'BotDeSinais'; ?>" class="site-logo" id="siteLogo">
            </div>
            
            <div class="login-body">
                <h1 class="login-title">Bem-vindo</h1>
                <p class="login-subtitle">Faça login para continuar</p>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <div class="alert-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" id="loginForm">
                    <div class="form-floating">
                        <span class="input-icon username-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Nome de usuário" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        <label for="username">Nome de usuário</label>
                    </div>
                    
                    <div class="form-floating">
                        <span class="input-icon password-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Senha" required>
                        <label for="password">Senha</label>
                    </div>
                    
                    <!-- Opção "Lembrar-me" com checkbox personalizado -->
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="custom-checkbox" for="remember">Lembrar usuário</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary login-button w-100">
                        <i class="fas fa-sign-in-alt login-icon"></i> Entrar
                    </button>
                </form>
            </div>
            
            <div class="login-footer">
                &copy; <?php echo date('Y'); ?> <?php echo $siteName ?: 'BotDeSinais'; ?> - Todos os direitos reservados
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar se a imagem do logo existe, caso contrário, usar um fallback
            const siteLogo = document.getElementById('siteLogo');
            siteLogo.onerror = function() {
                // Carregar logo padrão caso a configurada não seja encontrada
                this.src = 'assets/img/default-logo.png';
            };
        });
    </script>
</body>
</html>