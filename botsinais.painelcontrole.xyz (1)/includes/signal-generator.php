<?php
/**
 * Gerador Automático de Sinais
 * 
 * Este script gera sinais automáticos para jogos:
 * - Sinais premium a cada 15-30 minutos, enviados 45 minutos depois
 * - Sinais regulares a cada 1-2 horas, enviados 5 minutos depois
 * 
 * Execute este script via cronjob a cada minuto:
 * * * * * php /caminho/para/includes/signal-generator.php
 */

// Define o modo de execução CLI ou Web
define('IS_CLI', php_sapi_name() === 'cli');

// Inicializar ambiente
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/database.php';

// Função para log
function signal_log($message) {
    $logFile = BASE_PATH . '/logs/signal_generator_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    // Criar diretório de logs se não existir
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Se estiver em CLI, mostrar mensagem no console
    if (IS_CLI) {
        echo $logMessage;
    }
}

// Classe do gerador de sinais
class SignalGenerator {
    private $conn;
    private $settings = [];
    
    // Configurações de atraso entre geração e envio (em minutos)
    private $delay_settings = [
        'premium_delay' => 45,  // 45 minutos de atraso para sinais premium
        'regular_delay' => 5    // 5 minutos de atraso para sinais regulares
    ];
    
    // Tipos de estratégias para diferentes jogos
    private $strategies = [
        'slot' => [
            'Entrada Manual', 
            'Auto', 
            '5 Giros', 
            '10 Giros', 
            '15 Giros', 
            'Jogada única'
        ],
        'crash' => [
            'Entrada única', 
            'Entrar em 1.5x', 
            'Sair em 2x', 
            'Martingale leve', 
            'Apostar na tendência'
        ],
        'fortune_tiger' => [
            'Modo turbo ligado', 
            'Entrada manual', 
            'Esperar 3 vitórias', 
            'Jogar após derrota', 
            'Entrada após tigre'
        ],
        'mines' => [
            '2 minas', 
            '3 minas', 
            '5 minas', 
            'Padrão cruzado', 
            'Apostar nas bordas'
        ],
        'aviator' => [
            'Sair em 1.5x', 
            'Sair em 2.0x', 
            'Dobrar aposta após perda', 
            'Apostar metade', 
            'Aposta dupla'
        ],
        'roleta' => [
            'Apostar em vermelho', 
            'Apostar em preto', 
            'Apostar em dúzia', 
            'Sequência de 5 apostas', 
            'Dobrar em perda'
        ],
        'default' => [
            'Entrada simples', 
            'Estratégia padrão', 
            'Aposta única', 
            'Jogar normal', 
            'Seguir o sinal'
        ]
    ];

    // Valores de entrada padrão
    private $entryValues = ['R$5', 'R$10', 'R$20', 'R$50', 'R$100'];
    
    // Tipos de entrada
    private $entryTypes = ['Valor Fixo', 'Porcentagem', 'Valor Mínimo', 'Dobrar Após Perda'];
    
    // Multiplicadores de ganho
    private $multipliers = [1.5, 2.0, 2.5, 3.0, 5.0, 10.0];

    /**
     * Construtor
     */
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadSettings();
    }
    
    /**
     * Carregar configurações do banco de dados
     */
    private function loadSettings() {
        // Verificar se a tabela existe
        $checkTable = $this->conn->query("SHOW TABLES LIKE 'signal_generator_settings'");
        if ($checkTable->num_rows == 0) {
            $this->createSettingsTable();
        }
        
        $query = "SELECT setting_key, setting_value FROM signal_generator_settings";
        $result = $this->conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
            
            // Verificar se temos todas as configurações necessárias
            $requiredSettings = [
                'premium_min_interval', 'premium_max_interval',
                'regular_min_interval', 'regular_max_interval',
                'win_rate_percentage', 'active',
                'last_premium_signal', 'last_regular_signal'
            ];
            
            foreach ($requiredSettings as $setting) {
                if (!isset($this->settings[$setting])) {
                    $this->createDefaultSettings();
                    $this->loadSettings(); // Recarregar configurações
                    break;
                }
            }

            // Adicionar configurações de atraso se não existirem
            $this->ensureDelaySettingsExist();
        } else {
            signal_log("ERRO: Não foi possível carregar as configurações: " . $this->conn->error);
            $this->createDefaultSettings();
        }
    }
    
    /**
     * Criar tabela de configurações
     */
    private function createSettingsTable() {
        signal_log("Criando tabela de configurações...");
        
        $sql = "CREATE TABLE IF NOT EXISTS signal_generator_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if ($this->conn->query($sql)) {
            signal_log("Tabela signal_generator_settings criada com sucesso");
            $this->createDefaultSettings();
        } else {
            signal_log("ERRO ao criar tabela: " . $this->conn->error);
        }
    }
    
    /**
     * Criar configurações padrão
     */
    private function createDefaultSettings() {
        signal_log("Criando configurações padrão...");
        
        $defaults = [
            ['premium_min_interval', '15', 'Intervalo mínimo em minutos entre sinais premium (15-30)'],
            ['premium_max_interval', '30', 'Intervalo máximo em minutos entre sinais premium (15-30)'],
            ['regular_min_interval', '60', 'Intervalo mínimo em minutos entre sinais regulares (60-120)'],
            ['regular_max_interval', '120', 'Intervalo máximo em minutos entre sinais regulares (60-120)'],
            ['win_rate_percentage', '80', 'Taxa de acerto simulada para os sinais gerados (%)'],
            ['active', 'true', 'Se o gerador de sinais está ativo ou não'],
            ['last_premium_signal', '0', 'Timestamp do último sinal premium gerado'],
            ['last_regular_signal', '0', 'Timestamp do último sinal regular gerado'],
            ['premium_delay', '45', 'Atraso em minutos para enviar sinais premium após geração'],
            ['regular_delay', '5', 'Atraso em minutos para enviar sinais regulares após geração']
        ];
        
        $stmt = $this->conn->prepare("INSERT INTO signal_generator_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        
        foreach ($defaults as $setting) {
            $stmt->bind_param("sss", $setting[0], $setting[1], $setting[2]);
            if ($stmt->execute()) {
                signal_log("Configuração {$setting[0]} criada com sucesso");
            } else {
                signal_log("ERRO ao criar configuração {$setting[0]}: " . $stmt->error);
            }
        }
    }
    
    /**
     * Garantir que as configurações de atraso existam no banco
     */
    private function ensureDelaySettingsExist() {
        foreach ($this->delay_settings as $key => $default_value) {
            $check = $this->conn->prepare("SELECT COUNT(*) as count FROM signal_generator_settings WHERE setting_key = ?");
            $check->bind_param("s", $key);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->fetch_assoc()['count'] == 0) {
                // Inserir configuração de atraso
                $description = ($key == 'premium_delay') ? 
                    'Atraso em minutos para enviar sinais premium após geração' :
                    'Atraso em minutos para enviar sinais regulares após geração';
                
                $stmt = $this->conn->prepare("INSERT INTO signal_generator_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $key, $default_value, $description);
                $stmt->execute();
                signal_log("Configuração de atraso '$key' criada com valor padrão: $default_value");
                
                $this->settings[$key] = $default_value;
            } else {
                // Carregar valor existente
                $stmt = $this->conn->prepare("SELECT setting_value FROM signal_generator_settings WHERE setting_key = ?");
                $stmt->bind_param("s", $key);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $this->settings[$key] = $row['setting_value'];
            }
        }
    }
    
    /**
     * Verificar se é hora de gerar um novo sinal
     * @param string $type premium ou regular
     * @return bool
     */
    public function shouldGenerateSignal($type) {
        if ($this->settings['active'] !== 'true') {
            signal_log("Gerador está inativo. Nenhum sinal será gerado.");
            return false;
        }
        
        $now = time();
        $lastSignalKey = 'last_' . $type . '_signal';
        $minIntervalKey = $type . '_min_interval';
        $maxIntervalKey = $type . '_max_interval';
        
        $lastSignalTime = (int)$this->settings[$lastSignalKey];
        $minInterval = (int)$this->settings[$minIntervalKey] * 60; // Converter para segundos
        $maxInterval = (int)$this->settings[$maxIntervalKey] * 60; // Converter para segundos
        
        // Se não houver registro do último sinal ou o intervalo mínimo já passou
        if ($lastSignalTime == 0 || ($now - $lastSignalTime) >= $minInterval) {
            // Decisão aleatória para não gerar sinais exatamente no mesmo intervalo sempre
            $randomInterval = mt_rand($minInterval, $maxInterval);
            $shouldGenerate = ($now - $lastSignalTime) >= $randomInterval;
            
            signal_log("Verificando horário para sinal $type: " .
                      "último={$lastSignalTime}, " . 
                      "atual={$now}, " . 
                      "diferença=" . ($now - $lastSignalTime) . "s, " .
                      "intervalo mínimo={$minInterval}s, " . 
                      "intervalo aleatório={$randomInterval}s, " .
                      "gerar=" . ($shouldGenerate ? "sim" : "não"));
                      
            return $shouldGenerate;
        }
        
        return false;
    }
    
    /**
     * Atualizar o timestamp do último sinal gerado
     * @param string $type premium ou regular
     */
    private function updateLastSignalTime($type) {
        $now = time();
        $settingKey = 'last_' . $type . '_signal';
        
        $stmt = $this->conn->prepare("UPDATE signal_generator_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("is", $now, $settingKey);
        
        if (!$stmt->execute()) {
            signal_log("ERRO: Falha ao atualizar horário do último sinal $type: " . $stmt->error);
        } else {
            signal_log("Timestamp do último sinal $type atualizado para: " . date('Y-m-d H:i:s', $now));
        }
        
        // Também atualiza o valor local
        $this->settings[$settingKey] = $now;
    }
    
    /**
     * Obter um jogo aleatório ativo
     * @param string $provider (opcional) filtrar por provedor
     * @return array|null
     */
    private function getRandomGame($provider = null) {
        $sql = "SELECT * FROM games WHERE status = 'active'";
        
        if ($provider) {
            $sql .= " AND provider = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $provider);
        } else {
            $stmt = $this->conn->prepare($sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            signal_log("ERRO: Nenhum jogo ativo encontrado" . ($provider ? " para o provider $provider" : ""));
            return null;
        }
        
        // Pegar todos os jogos e selecionar um aleatoriamente
        $games = [];
        while ($row = $result->fetch_assoc()) {
            $games[] = $row;
        }
        
        $randomGame = $games[array_rand($games)];
        signal_log("Jogo selecionado: ID={$randomGame['id']}, Nome={$randomGame['name']}");
        
        return $randomGame;
    }
    
    /**
     * Obter plataforma aleatória ativa
     * @return array|null
     */
    private function getRandomPlatform() {
        $sql = "SELECT * FROM platforms WHERE status = 'active'";
        $result = $this->conn->query($sql);
        
        if ($result->num_rows == 0) {
            signal_log("ERRO: Nenhuma plataforma ativa encontrada");
            return null;
        }
        
        // Pegar todas as plataformas e selecionar uma aleatoriamente
        $platforms = [];
        while ($row = $result->fetch_assoc()) {
            $platforms[] = $row;
        }
        
        $randomPlatform = $platforms[array_rand($platforms)];
        signal_log("Plataforma selecionada: ID={$randomPlatform['id']}, Nome={$randomPlatform['name']}");
        
        return $randomPlatform;
    }
    
    /**
     * Obter estratégia com base no tipo de jogo
     * @param string $gameType
     * @return string
     */
    private function getRandomStrategy($gameType) {
        if (isset($this->strategies[$gameType])) {
            $strategies = $this->strategies[$gameType];
        } else {
            $strategies = $this->strategies['default'];
        }
        
        return $strategies[array_rand($strategies)];
    }
    
    /**
     * Gerar horário programado para envio baseado no tipo de sinal
     * @param string $type premium ou regular
     * @return string Formato MySQL DATETIME
     */
    private function generateScheduledTime($type) {
        $now = time();
        $delayKey = $type . '_delay';
        $delayMinutes = isset($this->settings[$delayKey]) ? (int)$this->settings[$delayKey] : $this->delay_settings[$delayKey];
        
        // Converter delay para segundos
        $delaySeconds = $delayMinutes * 60;
        
        // Calcular horário de envio
        $scheduledTime = $now + $delaySeconds;
        
        signal_log("Sinal $type agendado para: " . date('Y-m-d H:i:s', $scheduledTime) . " (atraso de {$delayMinutes} minutos)");
        
        return date('Y-m-d H:i:s', $scheduledTime);
    }
    
    /**
     * Gerar um novo sinal e adicioná-lo à fila
     * @param string $type premium ou regular
     * @return bool
     */
    public function generateSignal($type) {
        // Verificar se a conexão com o banco de dados está ok
        if ($this->conn->connect_error) {
            signal_log("ERRO: Conexão com o banco de dados falhou: " . $this->conn->connect_error);
            return false;
        }
        
        // Verificar se as tabelas existem
        $this->ensureTablesExist();
        
        // Obter jogo e plataforma aleatórios
        $game = $this->getRandomGame();
        $platform = $this->getRandomPlatform();
        
        if (!$game || !$platform) {
            signal_log("ERRO: Não foi possível gerar sinal $type. Jogos ou plataformas não encontrados.");
            return false;
        }
        
        // Determinar tipo de jogo (usando o campo type se disponível, ou inferindo do nome)
        $gameType = isset($game['type']) ? $game['type'] : $this->inferGameType($game['name']);
        
        // Selecionar estratégia
        $strategy = $this->getRandomStrategy($gameType);
        
        signal_log("Estratégia selecionada para $gameType: $strategy");
        
        // Selecionar valores aleatórios
        $entryValue = $this->entryValues[array_rand($this->entryValues)];
        $entryType = $this->entryTypes[array_rand($this->entryTypes)];
        $multiplier = $this->multipliers[array_rand($this->multipliers)];
        
        // Gerar horário agendado com atraso
        $scheduledAt = $this->generateScheduledTime($type);
        
        try {
            // Inserir na fila
            $stmt = $this->conn->prepare(
                "INSERT INTO signal_queue 
                (game_id, platform_id, signal_type, strategy, entry_value, entry_type, multiplier, scheduled_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
            
            if (!$stmt) {
                signal_log("ERRO: Falha na preparação do statement: " . $this->conn->error);
                return false;
            }
            
            $stmt->bind_param(
                "iissssds", 
                $game['id'], 
                $platform['id'], 
                $type, 
                $strategy, 
                $entryValue, 
                $entryType, 
                $multiplier, 
                $scheduledAt
            );
            
            if ($stmt->execute()) {
                $signalId = $stmt->insert_id;
                signal_log("Sinal $type #{$signalId} gerado com sucesso para o jogo {$game['name']} e plataforma {$platform['name']}.");
                signal_log("Será enviado em $scheduledAt (atraso de ".($type == 'premium' ? "45" : "5")." minutos após geração).");
                
                // Atualizar o timestamp do último sinal
                $this->updateLastSignalTime($type);
                return true;
            } else {
                signal_log("ERRO: Falha ao gerar sinal $type: " . $stmt->error);
                return false;
            }
        } catch (Exception $e) {
            signal_log("EXCEÇÃO: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar e criar as tabelas necessárias
     */
    private function ensureTablesExist() {
        $tables = [
            'signal_queue' => "CREATE TABLE IF NOT EXISTS signal_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                game_id INT NOT NULL,
                platform_id INT NOT NULL,
                signal_type ENUM('premium', 'regular') NOT NULL,
                strategy VARCHAR(100) NOT NULL,
                entry_value VARCHAR(100) NOT NULL,
                entry_type VARCHAR(50) NOT NULL,
                multiplier DECIMAL(10,2) NOT NULL,
                scheduled_at TIMESTAMP NOT NULL,
                status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                sent_at TIMESTAMP NULL,
                error_message TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
            
            'signal_history' => "CREATE TABLE IF NOT EXISTS signal_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                queue_id INT NOT NULL,
                bot_id INT NOT NULL,
                channel_id INT NULL,
                signal_type ENUM('premium', 'regular') NOT NULL,
                status ENUM('sent', 'failed') NOT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                error_message TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        ];
        
        foreach ($tables as $table => $create_sql) {
            $check = $this->conn->query("SHOW TABLES LIKE '$table'");
            if ($check->num_rows == 0) {
                signal_log("Criando tabela $table...");
                if ($this->conn->query($create_sql)) {
                    signal_log("Tabela $table criada com sucesso");
                } else {
                    signal_log("ERRO ao criar tabela $table: " . $this->conn->error);
                }
            }
        }
    }
    
    /**
     * Inferir o tipo de jogo pelo nome
     * @param string $gameName
     * @return string
     */
    private function inferGameType($gameName) {
        $gameName = strtolower($gameName);
        
        if (strpos($gameName, 'fortune') !== false) {
            if (strpos($gameName, 'tiger') !== false) return 'fortune_tiger';
            if (strpos($gameName, 'ox') !== false) return 'fortune_ox';
            if (strpos($gameName, 'mouse') !== false) return 'fortune_mouse';
            if (strpos($gameName, 'rabbit') !== false) return 'fortune_rabbit';
            return 'slot';
        }
        
        if (strpos($gameName, 'crash') !== false || strpos($gameName, 'aviator') !== false || strpos($gameName, 'spaceman') !== false) {
            return 'crash';
        }
        
        if (strpos($gameName, 'mine') !== false || strpos($gameName, 'mines') !== false) {
            return 'mines';
        }
        
        if (strpos($gameName, 'rolet') !== false || strpos($gameName, 'roulette') !== false) {
            return 'roleta';
        }
        
        // Default é slot
        return 'slot';
    }
    
    /**
     * Processar sinais agendados que estão pendentes
     * @return int Número de sinais processados
     */
    public function processScheduledSignals() {
        $now = date('Y-m-d H:i:s');
        
        signal_log("Verificando sinais agendados para processamento até $now");
        
        $sql = "SELECT q.*, g.name as game_name, p.name as platform_name, p.url as platform_url
                FROM signal_queue q 
                JOIN games g ON q.game_id = g.id
                JOIN platforms p ON q.platform_id = p.id
                WHERE q.status = 'pending' AND q.scheduled_at <= ?";
                
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            signal_log("ERRO: Falha na preparação do statement: " . $this->conn->error);
            return 0;
        }
        
        $stmt->bind_param("s", $now);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $processed = 0;
        
        while ($signal = $result->fetch_assoc()) {
            signal_log("Processando sinal #{$signal['id']} ({$signal['signal_type']}) para {$signal['game_name']}");
            
            // Enviar o sinal para os bots correspondentes
            $success = $this->sendSignalToBots($signal);
            
            // Atualizar o status do sinal
            $newStatus = $success ? 'sent' : 'failed';
            $updateStmt = $this->conn->prepare("UPDATE signal_queue SET status = ?, sent_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("si", $newStatus, $signal['id']);
            $updateStmt->execute();
            
            $processed++;
        }
        
        if ($processed > 0) {
            signal_log("$processed sinais processados e enviados");
        } else {
            signal_log("Nenhum sinal pendente para processar");
        }
        
        return $processed;
    }
    
    /**
     * Enviar sinal para os bots
     * @param array $signal
     * @return bool
     */
    private function sendSignalToBots($signal) {
        // Verificar se a tabela de bots existe
        $check = $this->conn->query("SHOW TABLES LIKE 'bots'");
        if ($check->num_rows == 0) {
            signal_log("Tabela 'bots' não existe. Pulando envio do sinal.");
            return false;
        }
        
        // Obter todos os bots ativos
        $sql = "SELECT * FROM bots WHERE status = 'active'";
        $result = $this->conn->query($sql);
        
        if ($result->num_rows == 0) {
            signal_log("Nenhum bot ativo encontrado para enviar o sinal #{$signal['id']}");
            return false;
        }
        
        signal_log("Encontrados " . $result->num_rows . " bots ativos para enviar o sinal");
        
        $success = true;
        $botCount = 0;
        
        while ($bot = $result->fetch_assoc()) {
            // Verificar se o bot deve receber este tipo de sinal
            $shouldSend = $this->shouldBotReceiveSignal($bot, $signal['signal_type']);
            
            if ($shouldSend) {
                $botCount++;
                
                // Preparar a mensagem
                $message = $this->formatSignalMessage($signal, $bot);
                
                // Lógica para enviar a mensagem para o bot (usando a API do Telegram por exemplo)
                $sent = $this->sendMessageToBot($bot, $message);
                
                // Registrar o histórico
                $this->logSignalHistory($signal['id'], $bot['id'], null, $signal['signal_type'], $sent);
                
                if (!$sent) {
                    $success = false;
                }
            }
        }
        
        signal_log("Sinal enviado para $botCount bots");
        
        return $success;
    }
    
    /**
     * Verificar se o bot deve receber este tipo de sinal
     * @param array $bot
     * @param string $signalType
     * @return bool
     */
    private function shouldBotReceiveSignal($bot, $signalType) {
        // Primeira verificação: se o bot tem a coluna is_premium
        if (array_key_exists('is_premium', $bot)) {
            if ($bot['is_premium'] == 1) {
                return true; // Bots premium recebem todos os sinais
            } else {
                // Bots não premium só recebem sinais regulares
                return $signalType == 'regular';
            }
        }
        
        // Segunda verificação: tentar adivinhar pelo nome ou outros campos
        $botName = strtolower($bot['name'] ?? '');
        if (strpos($botName, 'premium') !== false || strpos($botName, 'vip') !== false) {
            return true; // Se tem "premium" ou "vip" no nome, recebe todos os sinais
        }
        
        // Por padrão, assume que recebe apenas sinais regulares
        return $signalType == 'regular';
    }
    
    /**
     * Formatar mensagem de sinal
     * @param array $signal
     * @param array $bot
     * @return string
     */
    private function formatSignalMessage($signal, $bot) {
        $message = "🚨 *SINAL CONFIRMADO* 🚨\n\n";
        $message .= "🎮 *JOGO*: {$signal['game_name']}\n";
        $message .= "🎯 *ESTRATÉGIA*: {$signal['strategy']}\n\n";
        
        $message .= "💰 *ENTRADA*: {$signal['entry_value']}\n";
        $message .= "📊 *TIPO*: {$signal['entry_type']}\n";
        $message .= "✅ *MULTIPLICADOR*: {$signal['multiplier']}x\n\n";
        
        if (!empty($signal['platform_url'])) {
            $message .= "🔗 *CADASTRE-SE AQUI*: [Acessar Plataforma]({$signal['platform_url']})\n\n";
        }
        
        $message .= "⏱️ Válido por 10 minutos";
        
        return $message;
    }
    
    /**
     * Enviar mensagem para o bot
     * @param array $bot
     * @param string $message
     * @return bool
     */
    private function sendMessageToBot($bot, $message) {
        signal_log("Enviando mensagem para bot #{$bot['id']} - {$bot['name']}");
        
        // Versão simplificada - assume sucesso
        // Em produção, esta função deve integrar com a API do Telegram
        
        // Exemplo de código para enviar mensagem ao Telegram (descomente e configure)
        /*
        $bot_token = $bot['token'];
        $chat_id = $bot['chat_id'];
        
        $telegram_api_url = "https://api.telegram.org/bot$bot_token/sendMessage";
        $params = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $telegram_api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $result = json_decode($response, true);
            return isset($result['ok']) && $result['ok'] === true;
        }
        return false;
        */
        
        // Simula envio apenas para log
        signal_log("Conteúdo da mensagem: " . substr($message, 0, 100) . "...");
        
        return true;
    }
    
    /**
     * Registrar histórico de envio de sinal
     * @param int $signalId
     * @param int $botId
     * @param int|null $channelId
     * @param string $signalType
     * @param bool $success
     */
    private function logSignalHistory($signalId, $botId, $channelId, $signalType, $success) {
        // Verificar se a tabela existe
        $check = $this->conn->query("SHOW TABLES LIKE 'signal_history'");
        if ($check->num_rows == 0) {
            // Criar tabela se não existir
            $this->ensureTablesExist();
        }
        
        $status = $success ? 'sent' : 'failed';
        $error = $success ? NULL : 'Falha ao enviar mensagem para o bot';
        
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO signal_history 
                (queue_id, bot_id, channel_id, signal_type, status, error_message) 
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            
            $stmt->bind_param("iiisss", $signalId, $botId, $channelId, $signalType, $status, $error);
            $stmt->execute();
        } catch (Exception $e) {
            signal_log("ERRO ao registrar histórico: " . $e->getMessage());
        }
    }
    
    /**
     * Método principal para executar o gerador
     */
    public function run() {
        signal_log("Iniciando execução do gerador de sinais");
        
        // Primeiro processar sinais agendados pendentes
        $processed = $this->processScheduledSignals();
        
        // Verificar e gerar novos sinais premium
        if ($this->shouldGenerateSignal('premium')) {
            signal_log("Gerando novo sinal premium");
            $this->generateSignal('premium');
        }
        
        // Verificar e gerar novos sinais regulares
        if ($this->shouldGenerateSignal('regular')) {
            signal_log("Gerando novo sinal regular");
            $this->generateSignal('regular');
        }
        
        signal_log("Execução finalizada");
    }
}

// Executar o gerador
try {
    signal_log("==== INÍCIO DA EXECUÇÃO DO GERADOR (" . (IS_CLI ? "CLI" : "Web") . ") ====");
    $generator = new SignalGenerator($conn);
    $generator->run();
    signal_log("==== FIM DA EXECUÇÃO DO GERADOR ====");
} catch (Exception $e) {
    signal_log("ERRO GRAVE: " . $e->getMessage());
    signal_log("Stack trace: " . $e->getTraceAsString());
}

// Se for executado via web, mostrar mensagem de sucesso
if (!IS_CLI) {
    echo "Gerador de sinais executado com sucesso. Verifique os logs para mais detalhes.";
}