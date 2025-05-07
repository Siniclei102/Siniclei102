<?php
/**
 * Sistema de verificação de validade de usuários
 * Este arquivo deve ser incluído no início de cada página do sistema
 */

if (!function_exists('checkUserExpiry')) {
    /**
     * Verifica se um usuário está com a assinatura expirada
     * e suspende relacionados caso necessário
     * 
     * @param mysqli $conn Conexão com o banco de dados
     * @return void
     */
    function checkUserExpiry($conn) {
        if (!isset($_SESSION['user_id'])) {
            return;
        }
        
        // Verificar se o usuário atual está expirado
        $userId = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id, username, role, expiry_date, status FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Se o usuário não existir ou já estiver suspenso, encerra a sessão
        if (!$user || $user['status'] !== 'active') {
            session_destroy();
            header('Location: login.php?expired=1');
            exit;
        }
        
        // Se a data de expiração estiver definida e for anterior à data atual
        if (!empty($user['expiry_date']) && strtotime($user['expiry_date']) < time()) {
            // Suspender usuário atual
            $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // Registrar log
            logSystemAction($conn, 'user_expired', "Usuário {$user['username']} expirou em {$user['expiry_date']}", $userId);
            
            // Se for administrador master, realizar ações adicionais
            if ($user['role'] === 'admin') {
                // 1. Suspender todos os bots associados ao administrador
                $stmt = $conn->prepare("UPDATE bots SET status = 'inactive' WHERE created_by = ? OR master_id = ?");
                $stmt->bind_param("ii", $userId, $userId);
                $stmt->execute();
                
                // 2. Suspender todos os usuários associados a esses bots
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET status = 'suspended' 
                    WHERE bot_id IN (
                        SELECT id FROM bots WHERE created_by = ? OR master_id = ?
                    )
                ");
                $stmt->bind_param("ii", $userId, $userId);
                $stmt->execute();
                
                // Registrar log
                logSystemAction($conn, 'admin_expired', "Administrador {$user['username']} expirou. Bots e usuários associados foram suspensos.", $userId);
            }
            
            // Encerrar sessão e redirecionar para login
            session_destroy();
            header('Location: login.php?expired=1');
            exit;
        }
    }
}

if (!function_exists('logSystemAction')) {
    /**
     * Registra uma ação no log do sistema
     * 
     * @param mysqli $conn Conexão com o banco de dados
     * @param string $action Tipo de ação
     * @param string $description Descrição da ação
     * @param int $user_id ID do usuário (opcional)
     * @return void
     */
    function logSystemAction($conn, $action, $description, $user_id = null) {
        // Verificar se a tabela de logs existe
        $result = $conn->query("SHOW TABLES LIKE 'system_logs'");
        if ($result->num_rows == 0) {
            // Criar tabela se não existir
            $conn->query("CREATE TABLE system_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(50) NOT NULL,
                description TEXT,
                user_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
        
        // Registrar log
        $stmt = $conn->prepare("INSERT INTO system_logs (action, description, user_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $action, $description, $user_id);
        $stmt->execute();
    }
}

// Executar verificação se houver uma conexão com o banco disponível
if (isset($conn) && $conn) {
    checkUserExpiry($conn);
}