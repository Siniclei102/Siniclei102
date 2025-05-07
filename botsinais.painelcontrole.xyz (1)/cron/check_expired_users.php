<?php
/**
 * Script para verificar e suspender usuários expirados
 * Deve ser executado diariamente via cronjob
 * 
 * Exemplo de cronjob:
 * 0 0 * * * php /caminho/para/check_expired_users.php
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';

// Função para registrar log no arquivo
function log_to_file($message) {
    $log_file = ROOT_PATH . '/logs/expiry_check_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

log_to_file("Iniciando verificação de usuários expirados...");

// 1. Encontrar todos os usuários expirados
$current_date = date('Y-m-d');
$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE status = 'active' AND expiry_date < ?");
$stmt->bind_param("s", $current_date);
$stmt->execute();
$result = $stmt->get_result();

$expired_count = $result->num_rows;
log_to_file("Encontrados $expired_count usuário(s) expirado(s)");

if ($expired_count > 0) {
    while ($user = $result->fetch_assoc()) {
        $userId = $user['id'];
        $username = $user['username'];
        $role = $user['role'];
        
        log_to_file("Suspendendo usuário: $username (ID: $userId, Role: $role)");
        
        // Suspender usuário
        $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        // Se for admin, suspender bots e usuários vinculados
        if ($role === 'admin') {
            log_to_file("Usuário é administrador. Suspendendo bots e usuários vinculados...");
            
            // Suspender bots
            $stmt = $conn->prepare("UPDATE bots SET status = 'inactive' WHERE created_by = ? OR master_id = ?");
            $stmt->bind_param("ii", $userId, $userId);
            $stmt->execute();
            $affected_bots = $stmt->affected_rows;
            log_to_file("$affected_bots bots desativados");
            
            // Suspender usuários vinculados aos bots
            $stmt = $conn->prepare("
                UPDATE users 
                SET status = 'suspended' 
                WHERE status = 'active' AND bot_id IN (
                    SELECT id FROM bots WHERE created_by = ? OR master_id = ?
                )
            ");
            $stmt->bind_param("ii", $userId, $userId);
            $stmt->execute();
            $affected_users = $stmt->affected_rows;
            log_to_file("$affected_users usuários suspensos");
        }
        
        // Registrar no log do sistema
        logSystemAction($conn, 'user_expired', "Usuário $username expirado e suspenso automaticamente", $userId);
    }
} else {
    log_to_file("Nenhum usuário expirado encontrado");
}

log_to_file("Verificação concluída");