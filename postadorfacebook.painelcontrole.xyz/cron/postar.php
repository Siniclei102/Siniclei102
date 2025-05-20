<?php
/**
 * Script CRON para realizar postagens automáticas nos grupos do Facebook
 * Executar a cada 1 minuto: * * * * * php /caminho/para/postar.php
 */

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

// Buscar campanhas ativas com próxima execução <= agora
$queryCampanhas = "SELECT c.*, a.titulo, a.texto, a.link, a.imagem_url, u.facebook_token
                  FROM campanhas c 
                  JOIN anuncios a ON c.anuncio_id = a.id
                  JOIN usuarios u ON c.usuario_id = u.id
                  WHERE c.ativa = 1 
                  AND c.proxima_execucao <= NOW()
                  AND u.facebook_token IS NOT NULL
                  AND u.facebook_token_expira > NOW()
                  ORDER BY c.proxima_execucao ASC";

$resultCampanhas = $db->query($queryCampanhas);

if ($resultCampanhas->num_rows === 0) {
    logMessage("Nenhuma campanha para executar no momento");
    exit;
}

logMessage("Encontradas {$resultCampanhas->num_rows} campanhas para executar");

// Processar cada campanha
while ($campanha = $resultCampanhas->fetch_assoc()) {
    $campanhaId = $campanha['id'];
    $usuarioId = $campanha['usuario_id'];
    $anuncioId = $campanha['anuncio_id'];
    $intervaloMinutos = $campanha['intervalo_minutos'];
    $repeticaoHoras = $campanha['repeticao_horas'];
    $token = $campanha['facebook_token'];
    
    logMessage("Processando campanha #{$campanhaId}: {$campanha['nome']} (usuário #{$usuarioId})");
    
    // Inicializar API do Facebook
    try {
        $facebook = new FacebookAPI($db, $token);
        
        // Validar token
        $tokenValido = $facebook->validateToken();
        if (!$tokenValido['success']) {
            logMessage("Token expirado ou inválido para usuário #{$usuarioId}. Pulando campanha.");
            continue;
        }
        
        // Buscar próximo grupo para postagem
        $queryGrupo = "SELECT cg.grupo_id, g.facebook_group_id, g.nome 
                      FROM campanha_grupos cg 
                      JOIN grupos_facebook g ON cg.grupo_id = g.id
                      WHERE cg.campanha_id = ? 
                      AND cg.postado = 0 
                      AND g.ativo = 1
                      LIMIT 1";
        
        $stmtGrupo = $db->prepare($queryGrupo);
        $stmtGrupo->bind_param("i", $campanhaId);
        $stmtGrupo->execute();
        $resultGrupo = $stmtGrupo->get_result();
        
        // Se não há grupos para postar, verificar se deve reiniciar ou finalizar
        if ($resultGrupo->num_rows === 0) {
            // Verificar se deve reiniciar (se tiver repetição configurada)
            if ($repeticaoHoras) {
                logMessage("Todos os grupos da campanha #{$campanhaId} já receberam postagens. Configurando próxima execução em {$repeticaoHoras} horas.");
                
                // Calcular próxima execução
                $proximaExecucao = date('Y-m-d H:i:s', strtotime("+{$repeticaoHoras} hours"));
                
                // Atualizar campanha com próxima execução e resetar grupos
                $queryUpdate = "UPDATE campanhas SET proxima_execucao = ? WHERE id = ?";
                $stmtUpdate = $db->prepare($queryUpdate);
                $stmtUpdate->bind_param("si", $proximaExecucao, $campanhaId);
                $stmtUpdate->execute();
                
                // Resetar estado dos grupos para a próxima rodada
                $queryReset = "UPDATE campanha_grupos SET postado = 0 WHERE campanha_id = ?";
                $stmtReset = $db->prepare($queryReset);
                $stmtReset->bind_param("i", $campanhaId);
                $stmtReset->execute();
            } else {
                logMessage("Todos os grupos da campanha #{$campanhaId} já receberam postagens. Desativando campanha.");
                
                // Desativar campanha se não tem repetição
                $queryDesativar = "UPDATE campanhas SET ativa = 0, proxima_execucao = NULL WHERE id = ?";
                $stmtDesativar = $db->prepare($queryDesativar);
                $stmtDesativar->bind_param("i", $campanhaId);
                $stmtDesativar->execute();
            }
            
            continue; // Ir para a próxima campanha
        }
        
        // Obter dados do grupo
        $grupo = $resultGrupo->fetch_assoc();
        $grupoId = $grupo['grupo_id'];
        $facebookGrupoId = $grupo['facebook_group_id'];
        $grupoNome = $grupo['nome'];
        
        logMessage("Postando no grupo: {$grupoNome} (ID: {$facebookGrupoId})");
        
        // Preparar dados da postagem
        $postData = [
            'texto' => $campanha['texto'],
            'link' => $campanha['link'],
            'imagem' => $campanha['imagem_url']
        ];
        
        // Postar no grupo
        $resultado = $facebook->postToGroup($facebookGrupoId, $postData);
        
        // Registrar resultado no log
        $postId = $resultado['success'] ? $resultado['post_id'] : null;
        $status = $resultado['success'] ? 'sucesso' : 'falha';
        $mensagemErro = $resultado['success'] ? null : $resultado['message'];
        
        // Registrar log da postagem no banco
        $queryLog = "INSERT INTO logs_postagem (campanha_id, anuncio_id, grupo_id, postado_em, status, post_id, mensagem_erro) 
                    VALUES (?, ?, ?, NOW(), ?, ?, ?)";
        $stmtLog = $db->prepare($queryLog);
        $stmtLog->bind_param("iiisss", $campanhaId, $anuncioId, $grupoId, $status, $postId, $mensagemErro);
        $stmtLog->execute();
        
        // Marcar grupo como postado
        $queryMarcaPostado = "UPDATE campanha_grupos SET postado = 1 WHERE campanha_id = ? AND grupo_id = ?";
        $stmtMarcaPostado = $db->prepare($queryMarcaPostado);
        $stmtMarcaPostado->bind_param("ii", $campanhaId, $grupoId);
        $stmtMarcaPostado->execute();
        
        // Log do resultado
        if ($resultado['success']) {
            logMessage("Postagem realizada com sucesso! Post ID: {$postId}");
        } else {
            logMessage("Falha ao postar: {$mensagemErro}");
        }
        
        // Calcular próxima execução
        $proximaExecucao = date('Y-m-d H:i:s', strtotime("+{$intervaloMinutos} minutes"));
        
        // Atualizar próxima execução da campanha
        $queryProxima = "UPDATE campanhas SET proxima_execucao = ? WHERE id = ?";
        $stmtProxima = $db->prepare($queryProxima);
        $stmtProxima->bind_param("si", $proximaExecucao, $campanhaId);
        $stmtProxima->execute();
        
        logMessage("Próxima execução da campanha #{$campanhaId} agendada para {$proximaExecucao}");
    } catch (Exception $e) {
        logMessage("ERRO na campanha #{$campanhaId}: " . $e->getMessage());
    }
}

logMessage("Processo de postagem automática finalizado");

// Função para registrar logs
function logMessage($message) {
    $date = date('Y-m-d H:i:s');
    $logFile = '../logs/postagems_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, "[{$date}] {$message}" . PHP_EOL, FILE_APPEND);
    echo "[{$date}] {$message}" . PHP_EOL;
}
?>