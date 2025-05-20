<?php
/**
 * Script para análise automática de desempenho de postagens
 * 
 * Este script analisa as postagens recentes e gera recomendações
 * personalizadas para cada usuário
 * 
 * Execução recomendada: Uma vez por dia
 */

// Diretório raiz
$root = dirname(dirname(__FILE__));

// Incluir arquivos necessários
require_once $root . '/config/config.php';
require_once $root . '/config/database.php';
require_once $root . '/includes/functions.php';
require_once $root . '/classes/NotificacoesManager.php';

// Inicializar conexão com banco de dados
$db = Database::getInstance()->getConnection();

// Inicializar gerenciador de notificações
$notificacoes = new NotificacoesManager($db);

// Log
echo "[" . date('Y-m-d H:i:s') . "] Iniciando análise automática de desempenho...\n";

// Obter usuários ativos para análise
$query = "
    SELECT 
        u.id, 
        u.nome, 
        u.email, 
        COUNT(p.id) as total_posts
    FROM 
        usuarios u
        LEFT JOIN logs_postagem p ON u.id = p.usuario_id 
            AND p.status = 'sucesso' 
            AND p.postado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE 
        u.status = 'ativo'
    GROUP BY 
        u.id, u.nome, u.email
    HAVING 
        COUNT(p.id) >= 5
";

$usuarios = $db->query($query);
echo "[" . date('Y-m-d H:i:s') . "] Encontrados " . $usuarios->num_rows . " usuários com postagens suficientes para análise.\n";

// Analisar cada usuário
while ($usuario = $usuarios->fetch_assoc()) {
    echo "[" . date('Y-m-d H:i:s') . "] Analisando usuário: " . $usuario['nome'] . " (ID: " . $usuario['id'] . ")...\n";
    
    $userId = $usuario['id'];
    
    // 1. Análise de melhores dias da semana
    $queryDias = "
        SELECT 
            WEEKDAY(p.data_postagem) as dia_semana,
            COUNT(*) as total_posts,
            ROUND(AVG(f.curtidas)) as media_curtidas,
            ROUND(AVG(f.comentarios)) as media_comentarios,
            ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos
        FROM 
            logs_postagem p
            LEFT JOIN facebook_posts f ON p.post_id = f.post_id
        WHERE 
            p.usuario_id = ?
            AND p.status = 'sucesso'
            AND p.postado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY 
            WEEKDAY(p.data_postagem)
        ORDER BY 
            (AVG(f.curtidas) + AVG(f.comentarios) + AVG(f.compartilhamentos)) DESC
        LIMIT 1
    ";
    
    $stmtDias = $db->prepare($queryDias);
    $stmtDias->bind_param("i", $userId);
    $stmtDias->execute();
    $melhorDia = $stmtDias->get_result()->fetch_assoc();
    
    // 2. Análise de melhores horários
    $queryHoras = "
        SELECT 
            HOUR(p.data_postagem) as hora,
            COUNT(*) as total_posts,
            ROUND(AVG(f.curtidas)) as media_curtidas,
            ROUND(AVG(f.comentarios)) as media_comentarios,
            ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos
        FROM 
            logs_postagem p
            LEFT JOIN facebook_posts f ON p.post_id = f.post_id
        WHERE 
            p.usuario_id = ?
            AND p.status = 'sucesso'
            AND p.postado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY 
            HOUR(p.data_postagem)
        ORDER BY 
            (AVG(f.curtidas) + AVG(f.comentarios) + AVG(f.compartilhamentos)) DESC
        LIMIT 1
    ";
    
    $stmtHoras = $db->prepare($queryHoras);
    $stmtHoras->bind_param("i", $userId);
    $stmtHoras->execute();
    $melhorHora = $stmtHoras->get_result()->fetch_assoc();
    
    // 3. Análise de melhores grupos
    $queryGrupos = "
        SELECT 
            g.id,
            g.nome,
            COUNT(*) as total_posts,
            ROUND(AVG(f.curtidas)) as media_curtidas,
            ROUND(AVG(f.comentarios)) as media_comentarios,
            ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos,
            (AVG(f.curtidas) + AVG(f.comentarios) + AVG(f.compartilhamentos)) as engajamento_total
        FROM 
            logs_postagem p
            LEFT JOIN facebook_posts f ON p.post_id = f.post_id
            JOIN grupos_facebook g ON p.grupo_id = g.id
        WHERE 
            p.usuario_id = ?
            AND p.status = 'sucesso'
            AND p.postado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY 
            g.id, g.nome
        ORDER BY 
            engajamento_total DESC
        LIMIT 3
    ";
    
    $stmtGrupos = $db->prepare($queryGrupos);
    $stmtGrupos->bind_param("i", $userId);
    $stmtGrupos->execute();
    $melhoresGrupos = $stmtGrupos->get_result();
    
    // 4. Análise de tipo de conteúdo
    $queryTipo = "
        SELECT 
            CASE
                WHEN a.imagem_url != '' AND a.link != '' THEN 'Imagem+Link'
                WHEN a.imagem_url != '' THEN 'Apenas Imagem'
                WHEN a.link != '' THEN 'Apenas Link'
                ELSE 'Apenas Texto'
            END as tipo_conteudo,
            COUNT(*) as total_posts,
            ROUND(AVG(f.curtidas)) as media_curtidas,
            ROUND(AVG(f.comentarios)) as media_comentarios,
            ROUND(AVG(f.compartilhamentos)) as media_compartilhamentos
        FROM 
            logs_postagem p
            LEFT JOIN facebook_posts f ON p.post_id = f.post_id
            LEFT JOIN anuncios a ON p.anuncio_id = a.id
        WHERE 
            p.usuario_id = ?
            AND p.status = 'sucesso'
            AND p.postado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY 
            tipo_conteudo
        ORDER BY 
            (AVG(f.curtidas) + AVG(f.comentarios) + AVG(f.compartilhamentos)) DESC
        LIMIT 1
    ";
    
    $stmtTipo = $db->prepare($queryTipo);
    $stmtTipo->bind_param("i", $userId);
    $stmtTipo->execute();
    $melhorTipo = $stmtTipo->get_result()->fetch_assoc();
    
    // Gerar mensagens de recomendação
    $recomendacoes = [];
    $diasSemana = [
        'Segunda-feira', 'Terça-feira', 'Quarta-feira', 
        'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'
    ];
    
    if ($melhorDia && $melhorHora) {
        $diaSemana = $diasSemana[$melhorDia['dia_semana']];
        $horario = sprintf('%02d:00', $melhorHora['hora']);
        
        $recomendacoes[] = "🕒 Melhor momento para postar: {$diaSemana} às {$horario} - As postagens neste horário tiveram média de {$melhorDia['media_curtidas']} curtidas";
    }
    
    if ($melhorTipo) {
        $recomendacoes[] = "📄 Tipo de conteúdo mais eficaz: {$melhorTipo['tipo_conteudo']} - Média de {$melhorTipo['media_curtidas']} curtidas e {$melhorTipo['media_comentarios']} comentários";
    }
    
    if ($melhoresGrupos && $melhoresGrupos->num_rows > 0) {
        $gruposNomes = [];
        while ($grupo = $melhoresGrupos->fetch_assoc()) {
            $gruposNomes[] = $grupo['nome'];
        }
        
        if (count($gruposNomes) > 0) {
            $nomesGrupos = implode(', ', $gruposNomes);
            $recomendacoes[] = "👥 Melhores grupos para postar: {$nomesGrupos}";
        }
    }
    
    // Se houver recomendações, criar notificação
    if (count($recomendacoes) > 0) {
        $mensagem = "Análise de Desempenho das Suas Últimas Postagens:\n\n";
        $mensagem .= implode("\n\n", $recomendacoes);
        $mensagem .= "\n\nPara ver a análise completa, acesse o painel de análise de desempenho.";
        
        $notificacoes->adicionarNotificacao(
            $userId, 
            'Análise Semanal de Desempenho', 
            $mensagem, 
            'analise', 
            'analise-desempenho.php'
        );
        
        echo "[" . date('Y-m-d H:i:s') . "] Notificação de análise criada para o usuário ID $userId.\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Dados insuficientes para análise completa do usuário ID $userId.\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Análise automática concluída.\n";
?>