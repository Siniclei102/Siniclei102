<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../api/telegram.php';

// Definir limite de tempo de execução (0 = ilimitado)
set_time_limit(0);

// Obter conexão com o banco de dados
$db = Database::getInstance()->getConnection();

// Log de início do processo
logMessage("Iniciando processo de backup automático");

// Buscar configurações do Telegram
$queryConfig = "SELECT telegram_token, telegram_chat_id FROM configuracoes LIMIT 1";
$resultConfig = $db->query($queryConfig);

if ($resultConfig->num_rows > 0) {
    $config = $resultConfig->fetch_assoc();
    
    if (!empty($config['telegram_token']) && !empty($config['telegram_chat_id'])) {
        // Verificar agendamentos de backup
        $queryAgendamento = "SELECT * FROM backup_agendamentos WHERE ativo = 1";
        $resultAgendamento = $db->query($queryAgendamento);
        
        if ($resultAgendamento->num_rows > 0) {
            $agendamento = $resultAgendamento->fetch_assoc();
            
            // Verificar se é hora de executar o backup
            $executar = false;
            $agora = time();
            $ultimaExecucao = strtotime($agendamento['ultima_execucao'] ?? '2000-01-01');
            $frequenciaDias = $agendamento['frequencia'];
            $horaAgendada = $agendamento['hora'];
            
            $horaAtual = (int)date('G', $agora);
            $diaAtual = (int)date('z', $agora);
            $diaUltimoBackup = (int)date('z', $ultimaExecucao);
            
            // Verifica dias desde o último backup
            $diasPassados = $diaAtual - $diaUltimoBackup;
            if ($diasPassados < 0) {
                // Ajuste para mudança de ano
                $diasPassados += 365;
            }
            
            // Verificar se deve executar baseado na frequência e hora agendada
            if ($diasPassados >= $frequenciaDias && $horaAtual == $horaAgendada) {
                // Verificar se já foi executado hoje nesta hora
                $dataUltima = date('Y-m-d H', $ultimaExecucao);
                $dataAgora = date('Y-m-d H');
                
                if ($dataUltima != $dataAgora) {
                    $executar = true;
                }
            }
            
            if ($executar) {
                logMessage("Executando backup agendado: frequência {$frequenciaDias} dias, hora {$horaAgendada}h");
                
                // Executar backup
                $telegramBackup = new TelegramBackup($db, $config['telegram_token'], $config['telegram_chat_id']);
                $result = $telegramBackup->realizarBackup();
                
                if ($result['success']) {
                    logMessage("Backup realizado com sucesso! Arquivo: {$result['file']} ({$result['size']} bytes)");
                    
                    if ($result['telegram_sent']) {
                        logMessage("Backup enviado ao Telegram com sucesso!");
                        
                        // Atualizar data da última execução
                        $queryUpdate = "UPDATE backup_agendamentos SET ultima_execucao = NOW() WHERE id = ?";
                        $stmtUpdate = $db->prepare($queryUpdate);
                        $stmtUpdate->bind_param("i", $agendamento['id']);
                        $stmtUpdate->execute();
                    } else {
                        logMessage("AVISO: O backup foi salvo localmente, mas houve um erro ao enviá-lo ao Telegram.");
                    }
                } else {
                    logMessage("ERRO: Falha ao realizar backup: {$result['message']}");
                }
            } else {
                logMessage("Nenhum backup agendado para execução agora");
            }
        } else {
            logMessage("Nenhum agendamento de backup ativo encontrado");
        }
    } else {
        logMessage("Telegram não configurado. Backup automático não disponível.");
    }
} else {
    logMessage("Configurações não encontradas no banco de dados");
}

logMessage("Processo de backup automático finalizado");

// Função para registrar logs
function logMessage($message) {
    $date = date('Y-m-d H:i:s');
    $logFile = '../logs/backups_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, "[{$date}] {$message}" . PHP_EOL, FILE_APPEND);
    echo "[{$date}] {$message}" . PHP_EOL;
}
?>