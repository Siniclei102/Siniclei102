<?php
// Iniciar sessão se não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir arquivo de banco de dados
require_once __DIR__ . '/../config/database.php';

// Buscar configurações do site
$config = [];
try {
    $db = Database::getInstance()->getConnection();
    $queryConfig = "SELECT site_nome, logo_url, tema_cor FROM configuracoes LIMIT 1";
    $resultConfig = $db->query($queryConfig);
    if ($resultConfig && $resultConfig->num_rows > 0) {
        $config = $resultConfig->fetch_assoc();
    } else {
        // Configurações padrão
        $config = [
            'site_nome' => 'Postador Facebook',
            'logo_url' => 'assets/images/logo-default.png',
            'tema_cor' => '#4267B2'
        ];
    }
} catch (Exception $e) {
    // Configurações padrão em caso de erro
    $config = [
        'site_nome' => 'Postador Facebook',
        'logo_url' => 'assets/images/logo-default.png',
        'tema_cor' => '#4267B2'
    ];
}

// Verificar notificações
$notificacoes = [];
$notificacoesNaoLidas = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $userId = $_SESSION['user_id'];
        $queryNotif = "SELECT * FROM notificacoes WHERE usuario_id = ? AND lida = 0 ORDER BY criado_em DESC LIMIT 5";
        $stmt = $db->prepare($queryNotif);
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $resultNotif = $stmt->get_result();
            while ($row = $resultNotif->fetch_assoc()) {
                $notificacoes[] = $row;
            }
            $notificacoesNaoLidas = count($notificacoes);
        }
    } catch (Exception $e) {
        // Falha silenciosa
    }
}

// Determinar o caminho base para URLs
$isAdmin = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
$baseUrl = $isAdmin ? '../' : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . " | " : ""; ?><?php echo htmlspecialchars($config['site_nome']); ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?php echo $baseUrl; ?>assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Estilos personalizados -->
    <style>
        :root {
            --primary-color: <?php echo $config['tema_cor']; ?>;
            --primary-light: <?php echo $config['tema_cor'] . '15'; ?>;
            --primary-dark: <?php echo $config['tema_cor'] . 'dd'; ?>;
            --sidebar-width: 250px;
            --topbar-height: 70px;
            --footer-height: 60px;
        }
        
        /* Restante do CSS mantido igual... */
    </style>
</head>
<!-- Restante do arquivo mantido igual... -->