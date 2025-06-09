<?php
// Iniciar sessão
session_start();

// Configurações do site (em produção, isso viria do banco de dados)
$site_title = "PostGrupo Facebook";
$site_logo = "assets/img/logo.png"; // Logo padrão
$primary_color = "#6c5ce7";
$secondary_color = "#00cec9";

// Verificar se há cookie de "lembrar-me"
$remembered_username = '';
if (isset($_COOKIE['remember_user'])) {
    $remembered_username = $_COOKIE['remember_user'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $site_title; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: <?php echo $primary_color; ?>;
            --secondary: <?php echo $secondary_color; ?>;
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #ff7675;
            --info: #74b9ff;
            --dark: #2d3436;
            --light: #f8f9fd;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 20px;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 5px 15px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 1000px;
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            display: flex;
            min-height: 600px;
        }
        
        .login-image {
            flex: 1;
            background-color: var(--primary);
            background-image: url('https://images.unsplash.com/photo-1579389083046-e3df9c2b3325?ixlib=rb-1.2.1&auto=format&fit=crop&w=634&q=80');
            background-size: cover;
            background-position: center;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: white;
            text-align: center;
        }
        
        .login-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(108, 92, 231, 0.8), rgba(0, 206, 201, 0.8));
        }
        
        .login-image-content {
            position: relative;
            z-index: 1;
        }
        
        .login-form {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
        }
        
        .login-logo {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .login-logo img {
            height: 50px;
            margin-right: 15px;
        }
        
        .login-logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .login-header {
            margin-bottom: 30px;
        }
        
        .login-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            color: #636e72;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 15px;
            padding-left: 45px;
            border: 2px solid #dfe6e9;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            transition: border-color 0.15s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .form-icon {
            position: absolute;
            left: 15px;
            top: 42px;
            font-size: 18px;
            color: #b2bec3;
        }
        
        .username-icon {
            color: #ff7675;
        }
        
        .password-icon {
            color: #55efc4;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check-input {
            position: relative;
            width: 20px;
            height: 20px;
            margin-right: 10px;
            appearance: none;
            -webkit-appearance: none;
            border: 2px solid #dfe6e9;
            border-radius: 4px;
            outline: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .form-check-input:checked::after {
            content: '✓';
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
        }
        
        .forgot-password {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.15s ease;
        }
        
        .forgot-password:hover {
            color: var(--secondary);
        }
        
        .login-button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background-color: var(--primary);
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-button:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .login-button i {
            margin-right: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .alert.danger {
            background-color: rgba(255, 118, 117, 0.1);
            border-left: 4px solid #ff7675;
        }
        
        .alert-icon {
            font-size: 20px;
            color: #ff7675;
        }
        
        .social-logins {
            margin-top: 30px;
            text-align: center;
        }
        
        .social-title {
            font-size: 14px;
            color: #636e72;
            margin-bottom: 15px;
            position: relative;
        }
        
        .social-title::before,
        .social-title::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background-color: #dfe6e9;
        }
        
        .social-title::before {
            left: 0;
        }
        
        .social-title::after {
            right: 0;
        }
        
        .social-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .social-button {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 20px;
            transition: transform 0.2s;
        }
        
        .social-button:hover {
            transform: scale(1.1);
        }
        
        .facebook {
            background-color: #3b5998;
            color: white;
        }
        
        .google {
            background-color: #db4437;
            color: white;
        }
        
        .twitter {
            background-color: #1da1f2;
            color: white;
        }
        
        .login-footer {
            margin-top: auto;
            text-align: center;
            color: #636e72;
            font-size: 14px;
        }
        
        .login-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
            }
            
            .login-image {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-image">
            <div class="login-image-content">
                <h2 style="font-size: 36px; font-weight: 700; margin-bottom: 20px;"><?php echo $site_title; ?></h2>
                <p style="font-size: 18px; margin-bottom: 30px;">
                    A maneira mais eficiente de gerenciar e automatizar postagens em múltiplos grupos do Facebook.
                </p>
                <div style="display: flex; justify-content: center; gap: 15px; margin-bottom: 30px;">
                    <div style="text-align: center;">
                        <div style="font-size: 36px; font-weight: 700; margin-bottom: 5px;">500+</div>
                        <div>Grupos</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 36px; font-weight: 700; margin-bottom: 5px;">1000+</div>
                        <div>Postagens</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 36px; font-weight: 700; margin-bottom: 5px;">100+</div>
                        <div>Clientes</div>
                    </div>
                </div>
                <div style="display: inline-block; background-color: rgba(255, 255, 255, 0.2); padding: 15px 30px; border-radius: 50px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-star" style="color: #ffeaa7;"></i>
                        <span>Economize tempo e aumente seu alcance nas redes sociais!</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="login-form">
            <div class="login-logo">
                <?php if (file_exists($site_logo)): ?>
                    <img src="<?php echo $site_logo; ?>" alt="Logo">
                <?php else: ?>
                    <div style="width:50px;height:50px;background:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:20px;">PG</div>
                <?php endif; ?>
                <div class="login-logo-text"><?php echo $site_title; ?></div>
            </div>
            
            <div class="login-header">
                <h1 class="login-title">Bem-vindo de volta!</h1>
                <p class="login-subtitle">Entre com suas credenciais para acessar sua conta</p>
            </div>
            
            <?php if (isset($_SESSION['login_error'])): ?>
            <div class="alert danger">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="alert-content">
                    <h4 style="margin-top: 0; margin-bottom: 5px;">Erro de Autenticação</h4>
                    <p style="margin: 0;"><?php echo $_SESSION['login_error']; ?></p>
                </div>
            </div>
            <?php unset($_SESSION['login_error']); ?>
            <?php endif; ?>
            
            <form action="process_login.php" method="post">
                <div class="form-group">
                    <label class="form-label" for="username">Nome de Usuário</label>
                    <i class="fas fa-user form-icon username-icon"></i>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Digite seu nome de usuário" value="<?php echo htmlspecialchars($remembered_username); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Senha</label>
                    <i class="fas fa-lock form-icon password-icon"></i>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Digite sua senha" required>
                </div>
                
                <div class="form-options">
                    <div class="form-check">
                        <input type="checkbox" id="remember" name="remember" class="form-check-input" <?php echo $remembered_username ? 'checked' : ''; ?>>
                        <label for="remember">Lembrar-me</label>
                    </div>
                    
                    <a href="forgot-password.php" class="forgot-password">Esqueceu a senha?</a>
                </div>
                
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>
            
            <div class="social-logins">
                <div class="social-title">Ou entre com</div>
                <div class="social-buttons">
                    <a href="#" class="social-button facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-button google">
                        <i class="fab fa-google"></i>
                    </a>
                    <a href="#" class="social-button twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                </div>
            </div>
            
            <div class="login-footer">
                <p>Não tem uma conta? <a href="contact.php">Entre em contato</a> para solicitar acesso</p>
                <p>&copy; <?php echo date('Y'); ?> <?php echo $site_title; ?> - Todos os direitos reservados</p>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('Por favor, preencha todos os campos.');
                }
            });
        });
    </script>
</body>
</html>