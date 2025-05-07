<?php
/**
 * Arquivo de funções utilitárias para o sistema
 * 
 * Todas as funções auxiliares compartilhadas por múltiplos módulos são definidas aqui
 */

/**
 * Obtém o valor de uma configuração do sistema
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string $key Chave da configuração
 * @param mixed $default Valor padrão caso a configuração não exista
 * @return mixed Valor da configuração ou valor padrão
 */
function getSetting($conn, $key, $default = null) {
    $query = "SELECT setting_value FROM settings WHERE setting_key = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    
    return $default;
}

/**
 * Atualiza uma configuração do sistema
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string $key Chave da configuração
 * @param mixed $value Novo valor da configuração
 * @return bool True se a operação for bem-sucedida
 */
function updateSetting($conn, $key, $value) {
    // Verificar se a configuração já existe
    $check_query = "SELECT id FROM settings WHERE setting_key = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Atualizar configuração existente
        $update_query = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ss", $value, $key);
        return $stmt->execute();
    } else {
        // Criar nova configuração
        $insert_query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ss", $key, $value);
        return $stmt->execute();
    }
}

/**
 * Registra ações de administrador no log do sistema
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $user_id ID do usuário
 * @param string $action Descrição da ação
 * @return bool True se o registro for bem-sucedido
 */
function logAdminAction($conn, $user_id, $action) {
    // Verificar se a tabela de logs existe
    $check_table = $conn->query("SHOW TABLES LIKE 'admin_logs'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE `admin_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `action` text NOT NULL,
            `ip_address` varchar(45) NOT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $query = "INSERT INTO admin_logs (user_id, action, ip_address) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $action, $ip_address);
    return $stmt->execute();
}

/**
 * Formata uma data no padrão brasileiro
 * 
 * @param string $date Data no formato Y-m-d H:i:s
 * @param bool $show_time Mostrar também a hora
 * @return string Data formatada
 */
function formatDate($date, $show_time = true) {
    if (empty($date)) return '';
    
    $date_obj = new DateTime($date);
    return $date_obj->format($show_time ? 'd/m/Y H:i' : 'd/m/Y');
}

/**
 * Limita o tamanho de uma string e adiciona reticências se necessário
 * 
 * @param string $text Texto a ser limitado
 * @param int $length Tamanho máximo
 * @return string Texto limitado
 */
function truncateText($text, $length = 100) {
    if (empty($text) || strlen($text) <= $length) return $text;
    
    return substr($text, 0, $length) . '...';
}

/**
 * Registra uma mensagem no log do sistema
 * 
 * @param string $message Mensagem a ser registrada
 * @param string $level Nível do log (debug, info, warning, error)
 */
function logSystem($message, $level = 'info') {
    $log_dir = __DIR__ . '/../logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/system_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    
    // Formatar a mensagem
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Escrever no arquivo
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Verifica se a manutenção está ativada
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @return bool True se o modo de manutenção estiver ativo
 */
function isMaintenanceMode($conn) {
    $maintenance = getSetting($conn, 'maintenance_mode');
    return $maintenance === 'true';
}

/**
 * Envia um e-mail usando as configurações SMTP
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string $to E-mail do destinatário
 * @param string $subject Assunto do e-mail
 * @param string $message Corpo do e-mail
 * @return bool True se o e-mail for enviado com sucesso
 */
function sendEmail($conn, $to, $subject, $message) {
    // Incluir a biblioteca PHPMailer se estiver usando
    // require_once __DIR__ . '/../vendor/autoload.php';
    
    // Obter configurações de e-mail
    $smtp_host = getSetting($conn, 'smtp_host');
    $smtp_port = getSetting($conn, 'smtp_port');
    $smtp_username = getSetting($conn, 'smtp_username');
    $smtp_password = getSetting($conn, 'smtp_password');
    $from_email = getSetting($conn, 'smtp_from_email');
    $from_name = getSetting($conn, 'smtp_from_name');
    
    // Se as configurações não estiverem definidas, retorna falso
    if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
        logSystem("Falha ao enviar e-mail: Configurações SMTP incompletas", 'error');
        return false;
    }
    
    // Implementação da função de envio (usar biblioteca como PHPMailer)
    // A implementação exata depende da biblioteca escolhida
    
    /*
    // Exemplo com PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_port;
        
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        logSystem("E-mail enviado com sucesso para $to", 'info');
        return true;
    } catch (Exception $e) {
        logSystem("Falha ao enviar e-mail para $to: " . $mail->ErrorInfo, 'error');
        return false;
    }
    */
    
    // Substituição temporária (remover quando implementar o código real)
    logSystem("E-mail simulado enviado para $to: $subject", 'info');
    return true;
}

/**
 * Verifica as permissões de um usuário para acessar uma página
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $user_id ID do usuário
 * @param string $page_key Chave da página
 * @return bool True se o usuário tiver permissão
 */
function checkUserPermission($conn, $user_id, $page_key) {
    $query = "SELECT u.role FROM users u WHERE u.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Administradores têm acesso a tudo
        if ($user['role'] === 'admin') {
            return true;
        }
        
        // Verificar permissões específicas
        $perm_query = "SELECT COUNT(*) as count FROM user_permissions 
                      WHERE user_id = ? AND permission_key = ? AND active = 1";
        $stmt = $conn->prepare($perm_query);
        $stmt->bind_param("is", $user_id, $page_key);
        $stmt->execute();
        $perm_result = $stmt->get_result();
        
        if ($perm_result && $perm_result->fetch_assoc()['count'] > 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Gera um token de autenticação para o usuário
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $user_id ID do usuário
 * @return string Token gerado
 */
function generateAuthToken($conn, $user_id) {
    // Verificar se a tabela auth_tokens existe
    $check_table = $conn->query("SHOW TABLES LIKE 'auth_tokens'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE `auth_tokens` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `token` varchar(64) NOT NULL,
            `expires_at` datetime NOT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`),
            KEY `user_id` (`user_id`),
            KEY `expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    
    // Remover tokens antigos do mesmo usuário
    $cleanup_query = "DELETE FROM auth_tokens WHERE user_id = ? OR expires_at < NOW()";
    $stmt = $conn->prepare($cleanup_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Gerar novo token
    $token = bin2hex(random_bytes(32));
    
    // Definir data de expiração (30 dias)
    $expires_at = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
    
    // Salvar token no banco de dados
    $query = "INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $token, $expires_at);
    
    if ($stmt->execute()) {
        return $token;
    }
    
    return null;
}