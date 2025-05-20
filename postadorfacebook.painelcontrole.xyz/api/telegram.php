<?php
/**
 * Classe para gerenciar backups via Telegram
 * Realiza backup do banco de dados e envia para um chat ou canal
 */
class TelegramBackup {
    private $db;
    private $token;
    private $chatId;
    private $backupDir;
    
    /**
     * Construtor da classe
     * @param mysqli $db Conexão com o banco de dados
     * @param string $token Token do bot do Telegram
     * @param string $chatId ID do chat ou canal do Telegram
     */
    public function __construct($db, $token, $chatId) {
        $this->db = $db;
        $this->token = $token;
        $this->chatId = $chatId;
        $this->backupDir = __DIR__ . '/../backups/';
        
        // Criar diretório de backup se não existir
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Realiza o backup do banco de dados
     * @return array Resultado da operação
     */
    public function realizarBackup() {
        try {
            // Nome do arquivo de backup
            $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filePath = $this->backupDir . $fileName;
            
            // Obter conexão PDO
            $host = DB_HOST;
            $dbname = DB_NAME;
            $username = DB_USER;
            $password = DB_PASS;
            $port = defined('DB_PORT') ? DB_PORT : 3306;
            
            // Executar o comando mysqldump
            $command = sprintf(
                'mysqldump --opt --host=%s --port=%d --user=%s --password=%s %s > %s',
                escapeshellarg($host),
                $port,
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($dbname),
                escapeshellarg($filePath)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception('Erro ao executar mysqldump. Código: ' . $returnCode);
            }
            
            // Comprimir o arquivo SQL
            $zipFileName = $fileName . '.zip';
            $zipFilePath = $this->backupDir . $zipFileName;
            
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE) !== true) {
                throw new Exception('Não foi possível criar o arquivo ZIP');
            }
            
            $zip->addFile($filePath, $fileName);
            $zip->close();
            
            // Remover arquivo SQL original
            unlink($filePath);
            
            // Tamanho do backup
            $fileSize = filesize($zipFilePath);
            
            // Enviar para o Telegram
            $telegramSent = false;
            
            if ($fileSize <= 50 * 1024 * 1024) { // Limite de 50MB do Telegram
                $telegramSent = $this->enviarParaTelegram($zipFilePath, $zipFileName);
            }
            
            // Registrar no banco de dados
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Admin
            
            $query = "INSERT INTO backups (usuario_id, arquivo, tamanho, enviado_telegram) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("isii", $userId, $zipFileName, $fileSize, $telegramSent);
            $stmt->execute();
            
            return [
                'success' => true,
                'file' => $zipFileName,
                'size' => $fileSize,
                'telegram_sent' => $telegramSent,
                'message' => 'Backup realizado com sucesso'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao realizar backup: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia o backup para o Telegram
     * @param string $filePath Caminho do arquivo
     * @param string $fileName Nome do arquivo
     * @return bool Sucesso ou falha
     */
    private function enviarParaTelegram($filePath, $fileName) {
        try {
            // Preparar URL da API
            $url = "https://api.telegram.org/bot{$this->token}/sendDocument";
            
            // Preparar dados do formulário
            $postFields = [
                'chat_id' => $this->chatId,
                'document' => new CURLFile($filePath, 'application/zip', $fileName),
                'caption' => 'Backup automático do banco de dados - ' . date('d/m/Y H:i:s')
            ];
            
            // Inicializar cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // Executar requisição
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Verificar resposta
            $result = json_decode($response, true);
            
            return ($httpCode == 200 && isset($result['ok']) && $result['ok'] === true);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>