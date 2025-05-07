<?php
/**
 * Classe para envio de mensagens via API do Telegram
 */
class TelegramBot {
    private $token;
    private $apiUrl = 'https://api.telegram.org/bot';
    
    /**
     * Construtor
     * 
     * @param string $token Token do bot do Telegram
     */
    public function __construct($token) {
        $this->token = $token;
        $this->apiUrl .= $this->token . '/';
    }
    
    /**
     * Enviar mensagem para um chat
     * 
     * @param int|string $chatId ID do chat (usuário, grupo ou canal)
     * @param string $text Texto da mensagem (suporta Markdown)
     * @param array $options Opções adicionais
     * @return bool|array Resultado da requisição
     */
    public function sendMessage($chatId, $text, $options = []) {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => false
        ];
        
        // Mesclar opções adicionais
        if (!empty($options)) {
            $params = array_merge($params, $options);
        }
        
        return $this->request('sendMessage', $params);
    }
    
    /**
     * Enviar uma foto com legenda
     * 
     * @param int|string $chatId ID do chat
     * @param string $photo URL da foto ou caminho do arquivo
     * @param string $caption Legenda da foto (opcional)
     * @param array $options Opções adicionais
     * @return bool|array Resultado da requisição
     */
    public function sendPhoto($chatId, $photo, $caption = '', $options = []) {
        $params = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => 'Markdown'
        ];
        
        // Mesclar opções adicionais
        if (!empty($options)) {
            $params = array_merge($params, $options);
        }
        
        return $this->request('sendPhoto', $params);
    }
    
    /**
     * Realizar requisição à API do Telegram
     * 
     * @param string $method Método da API
     * @param array $params Parâmetros
     * @return bool|array Resultado da requisição
     */
    private function request($method, $params = []) {
        $url = $this->apiUrl . $method;
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => null,
            CURLOPT_POSTFIELDS => null
        ];
        
        $ch = curl_init();
        
        if (!empty($params)) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $params;
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log('Erro Curl ao enviar mensagem via Telegram: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        if ($httpCode >= 400) {
            error_log('Erro ao enviar mensagem via Telegram. HTTP Code: ' . $httpCode . ' - Resposta: ' . $response);
            return false;
        }
        
        return json_decode($response, true);
    }
}