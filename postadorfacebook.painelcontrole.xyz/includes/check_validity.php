<?php
// Verificar validade da conta de usuário
if (isset($_SESSION['user_id']) && !isset($_SESSION['is_admin'])) {
    // Não verificar para administradores
    $userId = $_SESSION['user_id'];
    
    // Obter conexão com o banco de dados se não existir
    if (!isset($db) || !$db) {
        $db = Database::getInstance()->getConnection();
    }
    
    // Verificar validade da conta
    $queryValidity = "SELECT validade_ate, suspenso FROM usuarios WHERE id = ? LIMIT 1";
    $stmtValidity = $db->prepare($queryValidity);
    $stmtValidity->bind_param("i", $userId);
    $stmtValidity->execute();
    $resultValidity = $stmtValidity->get_result();
    
    if ($resultValidity->num_rows > 0) {
        $userData = $resultValidity->fetch_assoc();
        
        // Verificar se a conta está explicitamente suspensa
        if ($userData['suspenso'] == 1) {
            // Redirecionar para a página de conta suspensa
            header('Location: conta_suspensa.php');
            exit;
        }
        
        // Verificar se a validade expirou
        if ($userData['validade_ate'] !== null) {
            $validadeAte = new DateTime($userData['validade_ate']);
            $hoje = new DateTime();
            
            if ($validadeAte < $hoje) {
                // Marcar usuário como suspenso no banco de dados
                $queryUpdateSuspend = "UPDATE usuarios SET suspenso = 1 WHERE id = ?";
                $stmtUpdateSuspend = $db->prepare($queryUpdateSuspend);
                $stmtUpdateSuspend->bind_param("i", $userId);
                $stmtUpdateSuspend->execute();
                
                // Redirecionar para a página de conta suspensa
                header('Location: conta_suspensa.php');
                exit;
            } else {
                // Verificar se está próximo de vencer (5 dias)
                $diasRestantes = $hoje->diff($validadeAte)->days;
                
                if ($diasRestantes <= 5) {
                    // Definir variável de alerta para exibir no topo da página
                    $_SESSION['validity_alert'] = [
                        'days' => $diasRestantes,
                        'date' => $validadeAte->format('d/m/Y')
                    ];
                }
            }
        }
    }
}
?>