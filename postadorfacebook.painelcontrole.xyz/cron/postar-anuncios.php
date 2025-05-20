<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../api/facebook.php';
require_once '../includes/functions.php';

// Definir limite de tempo de execução (0 = ilimitado)
set_time_limit(0);

// Obter conexão com o banco de dados
$db = Database::getInstance()->getConnection();

// Log de início do processo
logMessage("Iniciando processo de postagem automática");

// Buscar campanhas ativas
$query = "
    SELECT 
        c.*,
        a.mensagem, a.imagem_url, a.video_url, a.link_url,
        u.facebook_token
    FROM 
        campanhas c
    JOIN 
        anuncios a ON c.anuncio_id = a.id
    JOIN 
        usuarios u ON c.usuario_id = u.id
    WHERE 
        c.ativa = 1
        AND (c.proxima_execucao IS NULL OR c.proxima_execucao <= NOW())
        AND u.facebook_token IS NOT NULL
        AND u.status = 'ativo'
";

$result = $db->query($query);

if ($result->num_rows > 0) {
    while ($campanha = $result->fetch_assoc()) {
        processaCampanha($db, $campanha);
    }
}

logMessage("Processo de postagem finalizado");

// Função para processar cada campanha
function processaCampanha($db, $campanha) {
    $campanhaId = $campanha['id'];
    $userId = $campanha['usuario_id'];
    $anuncioId = $campanha['anuncio_id'];
    $intervaloMinutos = $campanha['intervalo_minutos'];
    $repeticaoHoras = $campanha['repeticao_horas'];
    $facebookToken = $campanha['facebook_token'];
    
    logMessage("Processando campanha #{$campanhaId} do usuário #{$userId}");
    
    // Instância da API do Facebook
    $facebookAPI = new FacebookAPI($db, $facebookToken);
    
    // Buscar grupos da campanha que ainda não foram postados
    $queryGrupos = "
        SELECT 
            cg.*,
            g.facebook_group_id, g.nome as grupo_nome
        FROM 
            campanha_grupos cg
        JOIN 
            grupos_facebook g ON cg.grupo_id = g.id
        WHERE 
            cg.campanha_id = ?
            AND (cg.postado = 0 OR (cg.proxima_postagem IS NOT NULL AND cg.proxima_postagem <= NOW()))
        ORDER BY 
            cg.proxima_postagem ASC
        LIMIT 1
    ";
    
    $stmtGrupos = $db->prepare($queryGrupos);
    $stmtGrupos->bind_param("i", $campanhaId);
    $stmtGrupos->execute();
    $resultGrupos = $stmtGrupos->get_result();
    
    if ($resultGrupos->num_rows > 0) {
        $grupo = $resultGrupos->fetch_assoc();
        
        // Realizar a postagem no grupo
        $resultado = $facebookAPI->postToGroup(
            $grupo['facebook_group_id'],
            $campanha['mensagem'],
            $campanha['imagem_url'],
            $campanha['video_url'],
            $campanha['link_url']
        );
        
        // Registrar resultado da postagem
        if ($resultado['success']) {
            logMessage("Postagem realizada com sucesso no grupo '{$grupo['grupo_nome']}'");
            
            // Marcar grupo como postado
            $updateGrupo = "UPDATE campanha_grupos SET 
                postado = 1,
                ultima_postagem = NOW(),
                proxima_postagem = " . ($repeticaoHoras ? "DATE_ADD(NOW(), INTERVAL {$repeticaoHoras} HOUR)" : "NULL") . "
                WHERE id = ?";
            
            $stmtUpdateGrupo = $db->prepare($updateGrupo);
            $stmtUpdateGrupo->bind_param("i", $grupo['id']);
            $stmtUpdateGrupo->execute();
            
            // Registrar log de sucesso
            $insertLog = "INSERT INTO logs_postagem 
                (campanha_id, grupo_id, anuncio_id, status, facebook_post_id) 
                VALUES (?, ?, ?, 'sucesso', ?)";
            
            $stmtLog = $db->prepare($insertLog);
            $stmtLog->bind_param("iiis", $campanhaId, $grupo['grupo_id'], $anuncioId, $resultado['post_id']);
            $stmtLog->execute();
        }
        else {
            logMessage("Falha na postagem no grupo '{$grupo['grupo_nome']}': {$resultado['message']}");
            
            // Registrar log de falha
            $insertLog = "INSERT INTO logs_postagem 
                (campanha_id, grupo_id, anuncio_id, status, mensagem_erro) 
                VALUES (?, ?, ?, 'falha', ?)";
            
            $stmtLog = $db->prepare($insertLog);
            $stmtLog->bind_param("iiis", $campanhaId, $grupo['grupo_id'], $anuncioId, $resultado['message']);
            $stmtLog->execute();
        }
        
        // Atualizar próxima execução da campanha (em 2 minutos)
        $updateCampanha = "UPDATE campanhas SET 
            ultima_execucao = NOW(),
            proxima_execucao = DATE_ADD(NOW(), INTERVAL {$intervaloMinutos} MINUTE)
            WHERE id = ?";
        
        $stmtUpdateCampanha = $db->prepare($updateCampanha);
        $stmtUpdateCampanha->bind_param("i", $campanhaId);
        $stmtUpdateCampanha->execute();
    }
    else {
        // Verificar se todos os grupos foram postados
        $checkAllPosted = "SELECT COUNT(*) as total FROM campanha_grupos WHERE campanha_id = ? AND postado = 0";
        $stmtCheck = $db->prepare($checkAllPosted);
        $stmtCheck->bind_param("i", $campanhaId);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        $totals = $resultCheck->fetch_assoc();
        
        // Se não há mais grupos para postar
        if ($totals['total'] == 0) {
            // Se tem repetição configurada
            if ($repeticaoHoras) {
                // Resetar todos os grupos para postagem após o intervalo de repetição
                $resetGrupos = "UPDATE campanha_grupos SET 
                    postado = 0,
                    proxima_postagem = DATE_ADD(NOW(), INTERVAL {$repeticaoHoras} HOUR)
                    WHERE campanha_id = ?";
                
                $stmtReset = $db->prepare($resetGrupos);
                $stmtReset->bind_param("i", $campanhaId);
                $stmtReset->execute();
                
                // Atualizar próxima execução da campanha
                $updateCampanha = "UPDATE campanhas SET 
                    proxima_execucao = DATE_ADD(NOW(), INTERVAL {$repeticaoHoras} HOUR)
                    WHERE id = ?";
                
                $stmtUpdate = $db->prepare($updateCampanha);
                $stmtUpdate->bind_param("i", $campanhaId);
                $stmtUpdate->execute();
                
                logMessage("Campanha #{$campanhaId} completada. Repetição programada em {$repeticaoHoras} horas");
            } else {
                // Se não tem repetição, marcar campanha como inativa
                $updateCampanha = "UPDATE campanhas SET 
                    ativa = 0,
                    proxima_execucao = NULL
                    WHERE id = ?";
                
                $stmtUpdate = $db->prepare($updateCampanha);
                $stmtUpdate->bind_param("i", $campanhaId);
                $stmtUpdate->execute();
                
                logMessage("Campanha #{$campanhaId} completada e finalizada");
            }
        }
    }
}

// Função para registrar logs
function logMessage($message) {
    $date = date('Y-m-d H:i:s');
    $logFile = '../logs/postagens_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, "[{$date}] {$message}" . PHP_EOL, FILE_APPEND);
    echo "[{$date}] {$message}" . PHP_EOL;
}
?>