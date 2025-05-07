/**
 * Enviar mensagem para o bot
 * @param array $bot
 * @param string $message
 * @return bool
 */
private function sendMessageToBot($bot, $message) {
    $this->log("Enviando mensagem para bot #{$bot['id']} - {$bot['name']}");
    
    // Incluir classe do Telegram
    require_once BASE_PATH . '/includes/telegram-bot.php';
    
    try {
        // Verificar se o bot tem token do Telegram
        if (empty($bot['token'])) {
            $this->log("ERRO: Bot #{$bot['id']} não possui token do Telegram");
            return false;
        }
        
        // Criar instância do bot Telegram
        $telegram = new TelegramBot($bot['token']);
        
        // Obter canais e grupos associados ao bot
        $channelsStmt = $this->conn->prepare("SELECT * FROM bot_channels WHERE bot_id = ? AND status = 'active'");
        $channelsStmt->bind_param("i", $bot['id']);
        $channelsStmt->execute();
        $channels = $channelsStmt->get_result();
        
        $success = true;
        
        // Enviar para todos os canais
        while ($channel = $channels->fetch_assoc()) {
            $chatId = $channel['chat_id'];
            $result = $telegram->sendMessage($chatId, $message);
            
            if (!$result) {
                $this->log("ERRO: Falha ao enviar mensagem para o canal {$channel['name']} (ID: {$chatId})");
                $success = false;
            }
        }
        
        return $success;
    } catch (Exception $e) {
        $this->log("ERRO: Exceção ao enviar mensagem: " . $e->getMessage());
        return false;
    }
}