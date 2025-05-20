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

// Verificar validade da conta
include 'includes/check_validity.php';

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Inicializar objeto de API do Facebook
$fb = new FacebookAPI($db);

// Verificar se usuário tem token de acesso
if (!$fb->hasUserToken($userId)) {
    $_SESSION['alert'] = [
        'type' => 'warning',
        'message' => 'Você precisa conectar sua conta do Facebook para atualizar as métricas. <a href="conectar-facebook.php">Conectar agora</a>.'
    ];
    
    header('Location: metricas-facebook.php');
    exit;
}

// Definir dias para atualização
$dias = isset($_GET['dias']) ? intval($_GET['dias']) : 30;
if ($dias <= 0 || $dias > 90) {
    $dias = 30; // Padrão: 30 dias
}

// Atualizar métricas
$resultado = $fb->updateUserPostMetrics($userId, $dias);

if ($resultado['success']) {
    $_SESSION['alert'] = [
        'type' => 'success',
        'message' => "Métricas atualizadas com sucesso! Foram processados {$resultado['stats']['total']} posts, com {$resultado['stats']['atualizados']} atualizados e {$resultado['stats']['erros']} erros."
    ];
} else {
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => "Erro ao atualizar métricas: {$resultado['message']}"
    ];
}

header('Location: metricas-facebook.php');
exit;
?>