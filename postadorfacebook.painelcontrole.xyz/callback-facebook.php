<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'classes/FacebookAPI.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Inicializar objeto de API do Facebook
$fb = new FacebookAPI($db);

// URL de redirecionamento
$redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/callback-facebook.php';

// Verificar se estamos recebendo um código de autorização
if (isset($_GET['code']) && isset($_GET['state'])) {
    // Verificar state para evitar CSRF
    if (!isset($_SESSION['fb_state']) || $_GET['state'] !== $_SESSION['fb_state']) {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Erro de validação do estado. Tente novamente.'
        ];
        
        header('Location: conectar-facebook.php');
        exit;
    }
    
    // Trocar código por token de acesso
    $tokenData = $fb->getAccessToken($_GET['code'], $redirectUrl);
    
    if ($tokenData) {
        // Salvar token no banco de dados
        $fb->updateUserToken($userId, $tokenData['access_token'], $tokenData['expiry_date']);
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => 'Conexão com o Facebook realizada com sucesso!'
        ];
        
        header('Location: metricas-facebook.php');
        exit;
    } else {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Erro ao obter token de acesso do Facebook.'
        ];
        
        header('Location: conectar-facebook.php');
        exit;
    }
} elseif (isset($_GET['error'])) {
    // Erro na autenticação
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Erro ao conectar com o Facebook: ' . $_GET['error_description']
    ];
    
    header('Location: conectar-facebook.php');
    exit;
} else {
    // Acesso direto à página de callback não é permitido
    header('Location: conectar-facebook.php');
    exit;
}
?>