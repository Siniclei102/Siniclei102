<?php
/**
 * Script CRON para processar agendamentos de postagens
 * 
 * Este script deve ser executado a cada minuto para verificar e processar
 * agendamentos pendentes no sistema de postagem automática.
 * 
 * Exemplo de configuração no crontab:
 * * * * * * /usr/bin/php /path/to/cron/processar_agendamentos.php >> /path/to/logs/agendamentos.log 2>&1
 */

// Incluir arquivos necessários
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/FacebookAPI.php';

// Definir fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Conectar ao banco de dados
$db = Database::getInstance()->getConnection();

// Inicializar classe de API do Facebook
$fb = new FacebookAPI($db);

echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de agendamentos...\n";

// Buscar agendamentos pendentes
$query = "
    SELECT 
        a.id,
        a.usuario_id,
        a.campanha_id,
        a.grupo_id,
        a.data_agendada,
        a.repetir,
        a.dias_repeticao,
        u.facebook_token,
        g.facebook_id as grupo_facebook_id,
        g.nome as grupo_nome
    FROM 
        agendamentos a
        JOIN usuarios u ON a.usuario_id = u.id
        JOIN grupos_facebook g ON a.grupo_id = g.id
    WHERE 
        a.status = 'agendado'
        AND a.data_agendada <= NOW()
        AND g.ativo = 1
    ORDER BY 
        a.data_agendada ASC
    LIMIT 10
";

$result = $db->query($query);
$total = $result->num_rows;

echo "[" . date('Y-m-d H:i:s') . "] Encontrados {$total} agendamentos para processar.\n";

// Processar cada agendamento
while ($agendamento = $result->fetch_assoc()) {
    $agendamento_id = $agendamento['id'];
    $usuario_id = $agendamento['usuario_id'];
    $campanha_id = $agendamento['campanha_id'];
    $grupo_id = $agendamento['grupo_id'];
    $grupo_facebook_id = $agendamento['grupo_facebook_id'];
    $grupo_nome = $agendamento['grupo_nome'];
    $repetir = $agendamento['repetir'];
    $dias_repeticao = $agendamento['dias_repeticao'];
    $facebook_token = $agendamento['facebook_token'];
    
    echo "[" . date('Y-m-d H:i:s') . "] Processando agendamento #{$agendamento_id} (Campanha #{$campanha_id}, Grupo: {$grupo_nome})...\n";
    
    try {
        // Verificar validade do token do Facebook
        if (empty($facebook_token)) {
            throw new Exception("Token do Facebook não encontrado para o usuário #{$usuario_id}");
        }
        
        // Verificar se o grupo do Facebook é válido
        if (empty($grupo_facebook_id)) {
            throw new Exception("ID do Facebook não encontrado para o grupo #{$grupo_id}");
        }
        
        // Obter um anúncio da campanha
        $queryAnuncio = "
            SELECT 
                a.id,
                a.titulo,
                a.descricao,
                a.link,
                a.imagem_url
            FROM 
                anuncios a
            WHERE 
                a.campanha_id = ?
                AND a.ativo = 1
            ORDER BY 
                RAND()
            LIMIT 1
        ";
        
        $stmtAnuncio = $db->prepare($queryAnuncio);
        $stmtAnuncio->bind_param("i", $campanha_id);
        $stmtAnuncio->execute();
        $anuncio = $stmtAnuncio->get_result()->fetch_assoc();
        
        if (!$anuncio) {
            throw new Exception("Nenhum anúncio ativo encontrado para a campanha #{$campanha_id}");
        }
        
        // Construir mensagem para postagem
        $mensagem = "{$anuncio['titulo']}\n\n{$anuncio['descricao']}";
        
        // Publicar no grupo do Facebook
        $resultado = $fb->postToGroup(
            $grupo_facebook_id,
            $mensagem,
            $anuncio['link'],
            $facebook_token
        );
        
        if ($resultado['success']) {
            echo "[" . date('Y-m-d H:i:s') . "] Postagem realizada com sucesso no grupo {$grupo_nome}!\n";
            
            // Registrar postagem no log
            $queryLog = "
                INSERT INTO logs_postagem (
                    usuario_id,
                    campanha_id,
                    grupo_id,
                    anuncio_id,
                    status,
                    postado_em
                ) VALUES (?, ?, ?, ?, 'sucesso', NOW())
            ";
            
            $stmtLog = $db->prepare($queryLog);
            $stmtLog->bind_param("iiii", $usuario_id, $campanha_id, $grupo_id, $anuncio['id']);
            $stmtLog->execute();
            
            // Salvar métricas no Facebook
            $fb->savePost([
                'usuario_id' => $usuario_id,
                'campanha_id' => $campanha_id,
                'grupo_id' => $grupo_id,
                'anuncio_id' => $anuncio['id'],
                'post_id' => $resultado['post_id'],
                'texto' => $mensagem,
                'link' => $anuncio['link'],
                'imagem_url' => $anuncio['imagem_url']
            ]);
            
            // Atualizar status do agendamento
            if ($repetir) {
                // Calcular próxima data de execução
                $proxima_data = date('Y-m-d H:i:s', strtotime("+{$dias_repeticao} days", strtotime($agendamento['data_agendada'])));
                
                $queryAtualizar = "
                    UPDATE agendamentos 
                    SET 
                        data_agendada = ?,
                        ultima_execucao = NOW()
                    WHERE id = ?
                ";
                
                $stmtAtualizar = $db->prepare($queryAtualizar);
                $stmtAtualizar->bind_param("si", $proxima_data, $agendamento_id);
                $stmtAtualizar->execute();
                
                echo "[" . date('Y-m-d H:i:s') . "] Agendamento reprogramado para {$proxima_data}.\n";
            } else {
                $queryAtualizar = "
                    UPDATE agendamentos 
                    SET 
                        status = 'concluido',
                        ultima_execucao = NOW()
                    WHERE id = ?
                ";
                
                $stmtAtualizar = $db->prepare($queryAtualizar);
                $stmtAtualizar->bind_param("i", $agendamento_id);
                $stmtAtualizar->execute();
                
                echo "[" . date('Y-m-d H:i:s') . "] Agendamento marcado como concluído.\n";
            }
        } else {
            $error_msg = isset($resultado['error']['error']['message']) 
                ? $resultado['error']['error']['message'] 
                : "Erro desconhecido ao publicar no Facebook";
                
            throw new Exception($error_msg);
        }
    } catch (Exception $e) {
        $erro = $e->getMessage();
        echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $erro . "\n";
        
        // Registrar erro no log
        $queryLog = "
            INSERT INTO logs_postagem (
                usuario_id,
                campanha_id,
                grupo_id,
                anuncio_id,
                status,
                mensagem_erro,
                postado_em
            ) VALUES (?, ?, ?, ?, 'falha', ?, NOW())
        ";
        
        $anuncio_id = isset($anuncio['id']) ? $anuncio['id'] : null;
        $stmtLog = $db->prepare($queryLog);
        $stmtLog->bind_param("iiiis", $usuario_id, $campanha_id, $grupo_id, $anuncio_id, $erro);
        $stmtLog->execute();
        
        // Atualizar status do agendamento
        $queryAtualizar = "
            UPDATE agendamentos 
            SET 
                status = 'erro',
                ultima_execucao = NOW(),
                mensagem_erro = ?
            WHERE id = ?
        ";
        
        $stmtAtualizar = $db->prepare($queryAtualizar);
        $stmtAtualizar->bind_param("si", $erro, $agendamento_id);
        $stmtAtualizar->execute();
    }
    
    // Pausa entre postagens para evitar bloqueios
    sleep(2);
}

echo "[" . date('Y-m-d H:i:s') . "] Processamento de agendamentos concluído.\n";
?>