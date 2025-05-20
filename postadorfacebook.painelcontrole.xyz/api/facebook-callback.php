<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once 'facebook.php';

session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$facebookAPI = new FacebookAPI($db);

// Processar o callback do Facebook
$result = $facebookAPI->handleCallback();

if ($result['success']) {
    // Salvar o token do Facebook no banco de dados
    $userId = $_SESSION['user_id'];
    $accessToken = $result['access_token'];
    $expireDate = $result['expire_date'];
    
    $stmt = $db->prepare("UPDATE usuarios SET facebook_token = ?, facebook_token_expira = ? WHERE id = ?");
    $stmt->bind_param("ssi", $accessToken, $expireDate, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['facebook_connected'] = true;
        $_SESSION['message'] = "Conectado com sucesso ao Facebook!";
        header('Location: ../grupos.php');
    } else {
        $_SESSION['error'] = "Erro ao salvar token do Facebook: " . $db->error;
        header('Location: ../perfil.php');
    }
} else {
    $_SESSION['error'] = $result['message'];
    header('Location: ../perfil.php');
}

exit;
?>