<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'api/facebook.php';

// Iniciar sessão
session_start();

// Se o usuário já está logado, redirecionar para o dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Obter conexão com o banco de dados
$db = Database::getInstance()->getConnection();

try {
    // Instanciar API do Facebook
    $facebook = new FacebookAPI($db);
    
    // Verificar se é callback após login
    if (isset($_GET['code']) || isset($_GET['state'])) {
        // Processar callback
        $result = $facebook->processCallback();
        
        if ($result['success']) {
            // Verificar se o usuário já existe com este FB ID
            $queryUser = "SELECT id, nome, email, is_admin FROM usuarios WHERE facebook_id = ? LIMIT 1";
            $stmtUser = $db->prepare($queryUser);
            $stmtUser->bind_param("s", $result['user_id']);
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();
            
            if ($resultUser->num_rows > 0) {
                // Usuário existe, atualizar token e fazer login
                $user = $resultUser->fetch_assoc();
                
                // Atualizar token
                $queryUpdate = "UPDATE usuarios SET facebook_token = ?, facebook_token_expira = ? WHERE id = ?";
                $stmtUpdate = $db->prepare($queryUpdate);
                $stmtUpdate->bind_param("ssi", $result['token'], $result['expires'], $user['id']);
                $stmtUpdate->execute();
                
                // Iniciar sessão
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                // Registrar login
                $queryLog = "INSERT INTO login_logs (usuario_id, ip, user_agent, acao) VALUES (?, ?, ?, 'login_facebook')";
                $stmtLog = $db->prepare($queryLog);
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $stmtLog->bind_param("iss", $user['id'], $ip, $userAgent);
                $stmtLog->execute();
            } else {
                // Verificar se existe um usuário com o mesmo email
                $queryEmail = "SELECT id FROM usuarios WHERE email = ? LIMIT 1";
                $stmtEmail = $db->prepare($queryEmail);
                $stmtEmail->bind_param("s", $result['user_email']);
                $stmtEmail->execute();
                $resultEmail = $stmtEmail->get_result();
                
                if ($resultEmail->num_rows > 0) {
                    // Atualizar usuário existente com dados do Facebook
                    $userId = $resultEmail->fetch_assoc()['id'];
                    
                    $queryUpdateUser = "UPDATE usuarios SET facebook_id = ?, facebook_token = ?, facebook_token_expira = ? WHERE id = ?";
                    $stmtUpdateUser = $db->prepare($queryUpdateUser);
                    $stmtUpdateUser->bind_param("sssi", $result['user_id'], $result['token'], $result['expires'], $userId);
                    $stmtUpdateUser->execute();
                    
                    // Buscar dados do usuário para a sessão
                    $queryUserData = "SELECT id, nome, email, is_admin FROM usuarios WHERE id = ?";
                    $stmtUserData = $db->prepare($queryUserData);
                    $stmtUserData->bind_param("i", $userId);
                    $stmtUserData->execute();
                    $user = $stmtUserData->get_result()->fetch_assoc();
                } else {
                    // Verificar se é o primeiro usuário (será admin)
                    $queryCount = "SELECT COUNT(*) as total FROM usuarios";
                    $resultCount = $db->query($queryCount);
                    $isFirstUser = ($resultCount->fetch_assoc()['total'] === 0);
                    
                    // Criar novo usuário
                    $queryInsert = "INSERT INTO usuarios (nome, email, facebook_id, facebook_token, facebook_token_expira, is_admin, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)";
                    $stmtInsert = $db->prepare($queryInsert);
                    $isAdmin = $isFirstUser ? 1 : 0;
                    $stmtInsert->bind_param("sssssi", $result['user_name'], $result['user_email'], $result['user_id'], $result['token'], $result['expires'], $isAdmin);
                    $stmtInsert->execute();
                    
                    $userId = $db->insert_id;
                    
                    // Preparar dados para a sessão
                    $user = [
                        'id' => $userId,
                        'nome' => $result['user_name'],
                        'email' => $result['user_email'],
                        'is_admin' => $isAdmin
                    ];
                    
                    // Registrar log de registro
                    $queryLog = "INSERT INTO login_logs (usuario_id, ip, user_agent, acao) VALUES (?, ?, ?, 'registro_facebook')";
                    $stmtLog = $db->prepare($queryLog);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $userAgent = $_SERVER['HTTP_USER_AGENT'];
                    $stmtLog->bind_param("iss", $userId, $ip, $userAgent);
                    $stmtLog->execute();
                }
                
                // Iniciar sessão
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['is_admin'] = $user['is_admin'];
            }
            
            // Redirecionar para o dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            // Erro ao processar login do Facebook
            $_SESSION['error_message'] = "Erro ao autenticar com Facebook: " . $result['message'];
            header('Location: index.php');
            exit;
        }
    } else {
        // Gerar URL de login e redirecionar
        $loginUrl = $facebook->getLoginUrl();
        header("Location: {$loginUrl}");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Erro: " . $e->getMessage();
    header('Location: index.php');
    exit;
}
?>