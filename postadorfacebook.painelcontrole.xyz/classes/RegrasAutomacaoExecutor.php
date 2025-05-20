<?php
/**
 * Classe para execução das regras de automação
 * 
 * Esta classe gerencia a lógica de execução das regras de automação 
 * configuradas pelos usuários.
 * 
 * @version 1.0
 */
class RegrasAutomacaoExecutor {
    private $db;
    
    /**
     * Construtor
     * 
     * @param mysqli $db Conexão com o banco de dados
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Executa todas as regras ativas
     * 
     * @return array Resultado da execução
     */
    public function executarRegrasAtivas() {
        // Obter todas as regras ativas
        $query = "SELECT id, usuario_id, nome, tipo FROM regras_automacao WHERE ativa = 1";
        $result = $this->db->query($query);
        
        $estatisticas = [
            'total' => $result->num_rows,
            'sucesso' => 0,
            'falha' => 0,
            'detalhes' => []
        ];
        
        while ($regra = $result->fetch_assoc()) {
            $resultado = $this->executarRegra($regra['id'], $regra['usuario_id']);
            
            if ($resultado['sucesso']) {
                $estatisticas['sucesso']++;
            } else {
                $estatisticas['falha']++;
            }
            
            $estatisticas['detalhes'][] = [
                'regra_id' => $regra['id'],
                'usuario_id' => $regra['usuario_id'],
                'nome' => $regra['nome'],
                'tipo' => $regra['tipo'],
                'resultado' => $resultado
            ];
        }
        
        return $estatisticas;
    }
    
    /**
     * Executa uma regra específica
     * 
     * @param int $regra_id ID da regra
     * @param int $usuario_id ID do usuário
     * @return array Resultado da execução
     */
    public function executarRegra($regra_id, $usuario_id) {
        // Obter detalhes da regra
        $query = "
            SELECT 
                id, 
                nome, 
                tipo, 
                condicoes, 
                acoes, 
                ativa, 
                ultima_execucao
            FROM 
                regras_automacao 
            WHERE 
                id = ? 
                AND usuario_id = ?
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $regra_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'sucesso' => false,
                'mensagem' => 'Regra não encontrada ou não pertence ao usuário'
            ];
        }
        
        $regra = $result->fetch_assoc();
        
        // Verificar se a regra está ativa
        if (!$regra['ativa']) {
            return [
                'sucesso' => false,
                'mensagem' => 'Regra está inativa'
            ];
        }
        
        $tipo = $regra['tipo'];
        $condicoes = json_decode($regra['condicoes'], true);
        $acoes = json_decode($regra['acoes'], true);
        
        // Executar regra com base no tipo
        try {
            $resultado = [
                'sucesso' => false,
                'mensagem' => 'Tipo de regra desconhecido'
            ];
            
            switch ($tipo) {
                case 'horario':
                    $resultado = $this->executarRegraHorario($regra, $condicoes, $acoes, $usuario_id);
                    break;
                    
                case 'engajamento':
                    $resultado = $this->executarRegraEngajamento($regra, $condicoes, $acoes, $usuario_id);
                                        break;
                    
                case 'rotacao':
                    $resultado = $this->executarRegraRotacao($regra, $condicoes, $acoes, $usuario_id);
                    break;
            }
            
            // Registrar execução da regra
            if ($resultado['sucesso']) {
                $mensagem = $resultado['mensagem'];
                $this->registrarExecucao($regra_id, $usuario_id, 'sucesso', $mensagem);
                
                // Atualizar última execução da regra
                $query_update = "
                    UPDATE regras_automacao 
                    SET ultima_execucao = NOW(), ultima_mensagem = ? 
                    WHERE id = ?
                ";
                
                $stmt_update = $this->db->prepare($query_update);
                $stmt_update->bind_param("si", $mensagem, $regra_id);
                $stmt_update->execute();
            } else {
                $mensagem = $resultado['mensagem'];
                $this->registrarExecucao($regra_id, $usuario_id, 'falha', $mensagem);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            $mensagem = "Erro ao executar regra: " . $e->getMessage();
            $this->registrarExecucao($regra_id, $usuario_id, 'falha', $mensagem);
            
            return [
                'sucesso' => false,
                'mensagem' => $mensagem
            ];
        }
    }
    
    /**
     * Executa regra baseada em horário
     * 
     * @param array $regra Dados da regra
     * @param array $condicoes Condições da regra
     * @param array $acoes Ações da regra
     * @param int $usuario_id ID do usuário
     * @return array Resultado da execução
     */
    private function executarRegraHorario($regra, $condicoes, $acoes, $usuario_id) {
        // Verificar se está dentro do horário especificado
        $agora = new DateTime();
        $hoje_dia_semana = strtolower($agora->format('D')); // Retorna 'mon', 'tue', etc
        $agora_hora = $agora->format('H:i');
        
        // Verificar se hoje é um dia válido
        if (!in_array($hoje_dia_semana, $condicoes['dias_semana'])) {
            return [
                'sucesso' => false,
                'mensagem' => "Dia da semana não configurado para esta regra"
            ];
        }
        
        // Verificar se está dentro do horário configurado
        $hora_inicio = $condicoes['hora_inicio'];
        $hora_fim = $condicoes['hora_fim'];
        
        if ($agora_hora < $hora_inicio || $agora_hora > $hora_fim) {
            return [
                'sucesso' => false,
                'mensagem' => "Fora do horário configurado ({$hora_inicio} - {$hora_fim})"
            ];
        }
        
        // Verificar última postagem para não exceder o limite
        $max_posts = isset($acoes['max_posts']) ? intval($acoes['max_posts']) : 1;
        $intervalo_minutos = isset($acoes['intervalo_minutos']) ? intval($acoes['intervalo_minutos']) : 30;
        
        $hoje_inicio = $agora->format('Y-m-d') . ' 00:00:00';
        $hoje_fim = $agora->format('Y-m-d') . ' 23:59:59';
        
        // Contar postagens já realizadas hoje por esta regra
        $query = "
            SELECT COUNT(*) as total
            FROM logs_execucao_regras
            WHERE regra_id = ? 
                AND usuario_id = ?
                AND status = 'sucesso'
                AND data_hora BETWEEN ? AND ?
                AND acoes_realizadas LIKE '%Postagem realizada%'
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iiss", $regra['id'], $usuario_id, $hoje_inicio, $hoje_fim);
        $stmt->execute();
        $total_hoje = $stmt->get_result()->fetch_assoc()['total'];
        
        // Verificar se já atingiu o máximo de posts para hoje
        if ($total_hoje >= $max_posts) {
            return [
                'sucesso' => false,
                'mensagem' => "Limite diário de {$max_posts} postagens já atingido"
            ];
        }
        
        // Verificar intervalo mínimo desde a última postagem
        $query = "
            SELECT MAX(data_hora) as ultima_postagem
            FROM logs_execucao_regras
            WHERE regra_id = ? 
                AND usuario_id = ?
                AND status = 'sucesso'
                AND acoes_realizadas LIKE '%Postagem realizada%'
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $regra['id'], $usuario_id);
        $stmt->execute();
        $ultima_postagem = $stmt->get_result()->fetch_assoc()['ultima_postagem'];
        
        if ($ultima_postagem) {
            $ultima_data = new DateTime($ultima_postagem);
            $diferenca = $agora->diff($ultima_data);
            $minutos_passados = ($diferenca->days * 24 * 60) + ($diferenca->h * 60) + $diferenca->i;
            
            if ($minutos_passados < $intervalo_minutos) {
                return [
                    'sucesso' => false,
                    'mensagem' => "Intervalo mínimo de {$intervalo_minutos} minutos não atingido (passaram {$minutos_passados} minutos)"
                ];
            }
        }
        
        // Realizar postagem
        $campanha_id = $acoes['campanha_id'];
        $grupos_ids = $acoes['grupos'];
        
        // Escolher um grupo aleatório dentre os configurados
        $grupo_id = $grupos_ids[array_rand($grupos_ids)];
        
        // Verificar se a campanha e o grupo existem
        $query = "
            SELECT c.id as campanha_id, c.nome as campanha_nome,
                   g.id as grupo_id, g.nome as grupo_nome
            FROM campanhas c, grupos_facebook g
            WHERE c.id = ? AND g.id = ?
                AND c.usuario_id = ? AND g.usuario_id = ?
                AND c.ativa = 1 AND g.ativo = 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iiii", $campanha_id, $grupo_id, $usuario_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'sucesso' => false,
                'mensagem' => "Campanha ou grupo não encontrado ou inativo"
            ];
        }
        
        $dados = $result->fetch_assoc();
        
        // Escolher um anúncio aleatório da campanha
        $query = "
            SELECT id, titulo
            FROM anuncios
            WHERE campanha_id = ? AND usuario_id = ? AND ativo = 1
            ORDER BY RAND()
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $campanha_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'sucesso' => false,
                'mensagem' => "Não há anúncios ativos na campanha {$dados['campanha_nome']}"
            ];
        }
        
        $anuncio = $result->fetch_assoc();
        
        // Realizar a postagem no Facebook
        try {
            require_once dirname(__FILE__) . '/../classes/FacebookAPI.php';
            $fb = new FacebookAPI($this->db);
            
            $postagem = $fb->postarNoGrupo($usuario_id, $grupo_id, $anuncio['id']);
            
            if ($postagem['status'] === 'sucesso') {
                return [
                    'sucesso' => true,
                    'mensagem' => "Postagem realizada com sucesso no grupo {$dados['grupo_nome']} usando anúncio {$anuncio['titulo']}",
                    'post_id' => $postagem['post_id'] ?? null
                ];
            } else {
                return [
                    'sucesso' => false,
                    'mensagem' => "Falha ao postar: " . ($postagem['mensagem'] ?? 'Erro desconhecido')
                ];
            }
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => "Erro ao realizar postagem: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Executa regra baseada em engajamento
     * 
     * @param array $regra Dados da regra
     * @param array $condicoes Condições da regra
     * @param array $acoes Ações da regra
     * @param int $usuario_id ID do usuário
     * @return array Resultado da execução
     */
    private function executarRegraEngajamento($regra, $condicoes, $acoes, $usuario_id) {
        // Obter parâmetros da regra
        $metrica = $condicoes['metricas'];
        $operador = $condicoes['operador'];
        $valor_limite = floatval($condicoes['valor_limite']);
        $periodo_dias = intval($condicoes['periodo_dias']);
        
        // Calcular data de início do período
        $data_inicio = date('Y-m-d', strtotime("-{$periodo_dias} days"));
        
        // Obter métricas de engajamento do período
        $query = "
            SELECT 
                AVG(fp.curtidas) as media_curtidas,
                AVG(fp.comentarios) as media_comentarios,
                AVG(fp.compartilhamentos) as media_compartilhamentos,
                AVG(fp.curtidas + fp.comentarios + fp.compartilhamentos) as media_engajamento_total,
                COUNT(*) as total_posts
            FROM 
                logs_postagem lp
                JOIN facebook_posts fp ON lp.post_id = fp.post_id
            WHERE 
                lp.usuario_id = ?
                AND lp.status = 'sucesso'
                AND lp.postado_em >= ?
        ";
        
        // Adicionar filtros específicos com base no tipo de ação
        if ($acoes['acao_tipo'] === 'trocar_campanha' && !empty($acoes['acao_campanha_id'])) {
            $query .= " AND lp.campanha_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("isi", $usuario_id, $data_inicio, $acoes['acao_campanha_id']);
        } else {
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("is", $usuario_id, $data_inicio);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $metricas = $result->fetch_assoc();
        
        // Verificar se há posts suficientes para análise
        if ($metricas['total_posts'] < 3) {
            return [
                'sucesso' => false,
                'mensagem' => "Dados insuficientes para análise. São necessários pelo menos 3 posts no período (encontrados: {$metricas['total_posts']})"
            ];
        }
        
        // Obter valor da métrica escolhida
        $valor_metrica = null;
        switch ($metrica) {
            case 'curtidas':
                $valor_metrica = $metricas['media_curtidas'];
                break;
            case 'comentarios':
                $valor_metrica = $metricas['media_comentarios'];
                break;
            case 'compartilhamentos':
                $valor_metrica = $metricas['media_compartilhamentos'];
                break;
            case 'engajamento_total':
                $valor_metrica = $metricas['media_engajamento_total'];
                break;
            default:
                return [
                    'sucesso' => false,
                    'mensagem' => "Métrica inválida: {$metrica}"
                ];
        }
        
        // Formatar para exibição
        $valor_metrica_formatado = number_format($valor_metrica, 1);
        
        // Verificar condição com base no operador
        $condicao_satisfeita = false;
        
        switch ($operador) {
            case 'maior':
                $condicao_satisfeita = $valor_metrica > $valor_limite;
                break;
            case 'menor':
                $condicao_satisfeita = $valor_metrica < $valor_limite;
                break;
            case 'igual':
                $condicao_satisfeita = abs($valor_metrica - $valor_limite) < 0.1; // Aproximação para float
                break;
            case 'maior_igual':
                $condicao_satisfeita = $valor_metrica >= $valor_limite;
                break;
            case 'menor_igual':
                $condicao_satisfeita = $valor_metrica <= $valor_limite;
                break;
            default:
                return [
                    'sucesso' => false,
                    'mensagem' => "Operador inválido: {$operador}"
                ];
        }
        
        // Se a condição não foi satisfeita, retornar sem executar ação
        if (!$condicao_satisfeita) {
            return [
                'sucesso' => false,
                'mensagem' => "Condição não satisfeita: {$metrica} ({$valor_metrica_formatado}) não é {$operador} que {$valor_limite}"
            ];
        }
        
        // Executar ação com base no tipo
        switch ($acoes['acao_tipo']) {
            case 'trocar_campanha':
                return $this->executarAcaoTrocarCampanha($usuario_id, $acoes['acao_campanha_id'], $metrica, $valor_metrica_formatado);
                
            case 'adicionar_grupos':
                return $this->executarAcaoAdicionarGrupos($usuario_id, $acoes['acao_grupos'], $metrica, $valor_metrica_formatado);
                
            case 'pausar_postagens':
                return $this->executarAcaoPausarPostagens($usuario_id, $metrica, $valor_metrica_formatado);
                
            case 'aumentar_frequencia':
                return $this->executarAcaoAumentarFrequencia($usuario_id, $metrica, $valor_metrica_formatado);
                
            case 'diminuir_frequencia':
                return $this->executarAcaoDiminuirFrequencia($usuario_id, $metrica, $valor_metrica_formatado);
                
            default:
                return [
                    'sucesso' => false,
                    'mensagem' => "Tipo de ação desconhecido: {$acoes['acao_tipo']}"
                ];
        }
    }
    
    /**
     * Executa regra de rotação de conteúdo
     * 
     * @param array $regra Dados da regra
     * @param array $condicoes Condições da regra
     * @param array $acoes Ações da regra
     * @param int $usuario_id ID do usuário
     * @return array Resultado da execução
     */
    private function executarRegraRotacao($regra, $condicoes, $acoes, $usuario_id) {
        $frequencia = $condicoes['rotacao_frequencia'];
        $campanhas = $condicoes['campanhas_rotacao'];
        $grupos = $acoes['rotacao_grupos'];
        $posts_por_dia = intval($acoes['rotacao_posts_por_dia']);
        $horarios = isset($acoes['rotacao_horarios']) ? $acoes['rotacao_horarios'] : [];
        
        if (empty($campanhas) || count($campanhas) < 2) {
            return [
                'sucesso' => false,
                'mensagem' => "São necessárias pelo menos 2 campanhas para rotação"
            ];
        }
        
        if (empty($grupos)) {
            return [
                'sucesso' => false,
                'mensagem' => "Pelo menos um grupo deve ser selecionado para postagem"
            ];
        }
        
        // Determinar campanha atual baseada na frequência
        $data_atual = new DateTime();
        $campanha_index = 0;
        
        switch ($frequencia) {
            case 'diaria':
                // Rotação diária (dia do ano % número de campanhas)
                $dia_ano = intval($data_atual->format('z'));
                $campanha_index = $dia_ano % count($campanhas);
                break;
                
            case 'semanal':
                // Rotação semanal (semana do ano % número de campanhas)
                $semana_ano = intval($data_atual->format('W'));
                $campanha_index = $semana_ano % count($campanhas);
                break;
                
            case 'mensal':
                // Rotação mensal (mês do ano % número de campanhas)
                $mes_ano = intval($data_atual->format('n'));
                $campanha_index = ($mes_ano - 1) % count($campanhas);
                break;
                
            default:
                return [
                    'sucesso' => false,
                    'mensagem' => "Frequência de rotação inválida: {$frequencia}"
                ];
        }
        
        $campanha_id = $campanhas[$campanha_index];
        
        // Verificar se a campanha existe e está ativa
        $query = "SELECT id, nome FROM campanhas WHERE id = ? AND usuario_id = ? AND ativa = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $campanha_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'sucesso' => false,
                'mensagem' => "Campanha ID {$campanha_id} não encontrada ou inativa"
            ];
        }
        
        $campanha = $result->fetch_assoc();
        
        // Verificar postagens já feitas hoje para esta regra
        $hoje_inicio = $data_atual->format('Y-m-d') . ' 00:00:00';
        $hoje_fim = $data_atual->format('Y-m-d') . ' 23:59:59';
        
        $query = "
            SELECT COUNT(*) as total
            FROM logs_execucao_regras
            WHERE regra_id = ? 
                AND usuario_id = ?
                AND status = 'sucesso'
                AND data_hora BETWEEN ? AND ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iiss", $regra['id'], $usuario_id, $hoje_inicio, $hoje_fim);
        $stmt->execute();
        $posts_hoje = $stmt->get_result()->fetch_assoc()['total'];
        
        if ($posts_hoje >= $posts_por_dia) {
            return [
                'sucesso' => false,
                'mensagem' => "Limite diário de {$posts_por_dia} postagens já atingido"
            ];
        }
        
        // Verificar se estamos em um dos horários programados (se houver)
        if (!empty($horarios)) {
            $hora_atual = $data_atual->format('H:i');
            $horario_valido = false;
            
            // Verificar se estamos próximos de algum horário programado (± 15min)
            foreach ($horarios as $horario) {
                $hora_programada = new DateTime($horario);
                $diferenca = $data_atual->diff($hora_programada);
                $minutos_diferenca = ($diferenca->h * 60) + $diferenca->i;
                
                if ($minutos_diferenca <= 15) {
                    $horario_valido = true;
                    break;
                }
            }
            
            if (!$horario_valido) {
                return [
                    'sucesso' => false,
                    'mensagem' => "Fora dos horários programados para postagem"
                ];
            }
        }
        
        // Escolher um grupo aleatório dentre os configurados
        $grupo_id = $grupos[array_rand($grupos)];
        
        // Verificar se o grupo existe e está ativo
        $query = "SELECT id, nome FROM grupos_facebook WHERE id = ? AND usuario_id = ? AND ativo = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $grupo_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'sucesso' => false,
                'mensagem' => "Grupo ID {$grupo_id} não encontrado ou inativo"
            ];
        }
        
        $grupo = $result->fetch_assoc();
        
        // Escolher um anúncio aleatório da campanha
        $query = "
            SELECT id, titulo
            FROM anuncios
            WHERE campanha_id = ? AND usuario_id = ? AND ativo = 1
            ORDER BY RAND()
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $campanha_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'sucesso' => false,
                'mensagem' => "Não há anúncios ativos na campanha {$campanha['nome']}"
            ];
        }
        
        $anuncio = $result->fetch_assoc();
        
        // Realizar a postagem no Facebook
        try {
            require_once dirname(__FILE__) . '/../classes/FacebookAPI.php';
            $fb = new FacebookAPI($this->db);
            
            $postagem = $fb->postarNoGrupo($usuario_id, $grupo_id, $anuncio['id']);
            
            if ($postagem['status'] === 'sucesso') {
                $frequencias = [
                    'diaria' => 'diária',
                    'semanal' => 'semanal',
                    'mensal' => 'mensal'
                ];
                
                return [
                    'sucesso' => true,
                    'mensagem' => "Rotação {$frequencias[$frequencia]}: Postagem realizada com sucesso no grupo {$grupo['nome']} usando campanha {$campanha['nome']}",
                    'post_id' => $postagem['post_id'] ?? null
                ];
            } else {
                return [
                    'sucesso' => false,
                    'mensagem' => "Falha ao postar: " . ($postagem['mensagem'] ?? 'Erro desconhecido')
                ];
            }
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => "Erro ao realizar postagem: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Executa ação de trocar campanha
     * 
     * @param int $usuario_id ID do usuário
     * @param int $campanha_id ID da campanha destino
     * @param string $metrica Nome da métrica avaliada
     * @param string $valor_metrica Valor da métrica formatado
     * @return array Resultado da execução
     */
    private function executarAcaoTrocarCampanha($usuario_id, $campanha_id, $metrica, $valor_metrica) {
        // Verificar se a campanha destino existe
        $query = "SELECT id, nome FROM campanhas WHERE id = ? AND usuario_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $campanha_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'sucesso' => false,
                'mensagem' => "Campanha destino não encontrada"
            ];
        }
        
        $campanha = $result->fetch_assoc();
        
        // Atualizar configurações de agendamento para usar a nova campanha
        $query = "
            UPDATE agendamentos SET
                campanha_id = ?
            WHERE 
                usuario_id = ? AND status = 'agendado'
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $campanha_id, $usuario_id);
        $stmt->execute();
        $agendamentos_afetados = $stmt->affected_rows;
        
        return [
            'sucesso' => true,
            'mensagem' => "Campanha alterada para '{$campanha['nome']}' devido à métrica de {$metrica} ({$valor_metrica}). {$agendamentos_afetados} agendamentos atualizados."
        ];
    }
    
    /**
     * Executa ação de adicionar grupos
     * 
     * @param int $usuario_id ID do usuário
     * @param array $grupos_ids IDs dos grupos a adicionar
     * @param string $metrica Nome da métrica avaliada
     * @param string $valor_metrica Valor da métrica formatado
     * @return array Resultado da execução
     */
    private function executarAcaoAdicionarGrupos($usuario_id, $grupos_ids, $metrica, $valor_metrica) {
        if (empty($grupos_ids)) {
            return [
                'sucesso' => false,
                'mensagem' => "Nenhum grupo selecionado para adicionar"
            ];
        }
        
        // Obter nomes dos grupos
        $grupos_placeholders = implode(',', array_fill(0, count($grupos_ids), '?'));
        $query = "SELECT id, nome FROM grupos_facebook WHERE id IN ($grupos_placeholders) AND usuario_id = ?";
        
        $tipos = str_repeat('i', count($grupos_ids)) . 'i';
        $params = array_merge($grupos_ids, [$usuario_id]);
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param($tipos, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'sucesso' => false,
                'mensagem' => "Nenhum grupo válido encontrado"
            ];
        }
        
        $grupos = [];
        while ($grupo = $result->fetch_assoc()) {
            $grupos[] = $grupo;
        }
        
        // Atualizar próximos agendamentos para incluir os novos grupos
        $grupos_adicionados = 0;
        $hoje = date('Y-m-d H:i:s');
        
        foreach ($grupos as $grupo) {
            // Obter agendamentos ativos
            $query = "
                SELECT id, grupos_ids FROM agendamentos 
                WHERE usuario_id = ? AND status = 'agendado' AND data_agendada > ?
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("is", $usuario_id, $hoje);
            $stmt->execute();
            $agendamentos = $stmt->get_result();
            
            while ($agendamento = $agendamentos->fetch_assoc()) {
                $grupos_existentes = json_decode($agendamento['grupos_ids'], true) ?? [];
                
                // Verificar se o grupo já está incluído
                if (!in_array($grupo['id'], $grupos_existentes)) {
                    $grupos_existentes[] = $grupo['id'];
                    $grupos_json = json_encode($grupos_existentes);
                    
                    // Atualizar agendamento
                    $query_update = "UPDATE agendamentos SET grupos_ids = ? WHERE id = ?";
                    $stmt_update = $this->db->prepare($query_update);
                    $stmt_update->bind_param("si", $grupos_json, $agendamento['id']);
                    $stmt_update->execute();
                    
                    $grupos_adicionados++;
                }
            }
        }
        
        // Formatar nomes dos grupos para a mensagem
        $grupos_nomes = array_column($grupos, 'nome');
        $grupos_texto = implode(', ', $grupos_nomes);
        
        return [
            'sucesso' => true,
            'mensagem' => "Grupos adicionados ({$grupos_texto}) devido à métrica de {$metrica} ({$valor_metrica}). {$grupos_adicionados} agendamentos atualizados."
        ];
    }
    
    /**
     * Executa ação de pausar postagens
     * 
     * @param int $usuario_id ID do usuário
     * @param string $metrica Nome da métrica avaliada
     * @param string $valor_metrica Valor da métrica formatado
     * @return array Resultado da execução
     */
    private function executarAcaoPausarPostagens($usuario_id, $metrica, $valor_metrica) {
        // Pausar agendamentos futuros
        $query = "
            UPDATE agendamentos SET
                status = 'suspenso'
            WHERE 
                usuario_id = ? AND status = 'agendado'
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $agendamentos_pausados = $stmt->affected_rows;
        
        return [
            'sucesso' => true,
            'mensagem' => "Postagens pausadas devido à métrica de {$metrica} ({$valor_metrica}). {$agendamentos_pausados} agendamentos foram suspensos."
        ];
    }
    
    /**
     * Executa ação de aumentar frequência de postagens
     * 
     * @param int $usuario_id ID do usuário
     * @param string $metrica Nome da métrica avaliada
     * @param string $valor_metrica Valor da métrica formatado
     * @return array Resultado da execução
     */
    private function executarAcaoAumentarFrequencia($usuario_id, $metrica, $valor_metrica) {
        // Obter configuração de postagem automática do usuário
        $query = "
            SELECT valor FROM configuracoes_usuario 
            WHERE usuario_id = ? AND chave = 'frequencia_postagem' LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $frequencia_atual = intval($result->fetch_assoc()['valor']);
            $nova_frequencia = min($frequencia_atual + 1, 10); // Limitar a 10 posts por dia
            
            if ($nova_frequencia > $frequencia_atual) {
                $query_update = "
                    UPDATE configuracoes_usuario 
                    SET valor = ?
                    WHERE usuario_id = ? AND chave = 'frequencia_postagem'
                ";
                
                $stmt_update = $this->db->prepare($query_update);
                $stmt_update->bind_param("ii", $nova_frequencia, $usuario_id);
                $stmt_update->execute();
                
                return [
                    'sucesso' => true,
                    'mensagem' => "Frequência de postagem aumentada de {$frequencia_atual} para {$nova_frequencia} posts por dia devido à alta métrica de {$metrica} ({$valor_metrica})."
                ];
            } else {
                return [
                    'sucesso' => false,
                    'mensagem' => "Frequência já está no valor máximo (10 posts por dia)"
                ];
            }
        } else {
            // Configuração não existe, criar com valor inicial 2
            $query_insert = "
                INSERT INTO configuracoes_usuario (usuario_id, chave, valor)
                VALUES (?, 'frequencia_postagem', 2)
            ";
            
            $stmt_insert = $this->db->prepare($query_insert);
            $stmt_insert->bind_param("i", $usuario_id);
            $stmt_insert->execute();
            
            return [
                'sucesso' => true,
                'mensagem' => "Frequência de postagem configurada para 2 posts por dia devido à alta métrica de {$metrica} ({$valor_metrica})."
            ];
        }
    }
    
    /**
     * Executa ação de diminuir frequência de postagens
     * 
     * @param int $usuario_id ID do usuário
     * @param string $metrica Nome da métrica avaliada
     * @param string $valor_metrica Valor da métrica formatado
     * @return array Resultado da execução
     */
    private function executarAcaoDiminuirFrequencia($usuario_id, $metrica, $valor_metrica) {
        // Obter configuração de postagem automática do usuário
        $query = "
            SELECT valor FROM configuracoes_usuario 
            WHERE usuario_id = ? AND chave = 'frequencia_postagem' LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $frequencia_atual = intval($result->fetch_assoc()['valor']);
            $nova_frequencia = max($frequencia_atual - 1, 1); // Mínimo de 1 post por dia
            
            if ($nova_frequencia < $frequencia_atual) {
                $query_update = "
                    UPDATE configuracoes_usuario 
                    SET valor = ?
                    WHERE usuario_id = ? AND chave = 'frequencia_postagem'
                ";
                
                $stmt_update = $this->db->prepare($query_update);
                $stmt_update->bind_param("ii", $nova_frequencia, $usuario_id);
                $stmt_update->execute();
                
                return [
                    'sucesso' => true,
                    'mensagem' => "Frequência de postagem reduzida de {$frequencia_atual} para {$nova_frequencia} posts por dia devido à baixa métrica de {$metrica} ({$valor_metrica})."
                ];
            } else {
                return [
                    'sucesso' => false,
                    'mensagem' => "Frequência já está no valor mínimo (1 post por dia)"
                ];
            }
        } else {
            // Configuração não existe, criar com valor inicial 1
            $query_insert = "
                INSERT INTO configuracoes_usuario (usuario_id, chave, valor)
                VALUES (?, 'frequencia_postagem', 1)
            ";
            
            $stmt_insert = $this->db->prepare($query_insert);
            $stmt_insert->bind_param("i", $usuario_id);
            $stmt_insert->execute();
            
            return [
                'sucesso' => true,
                'mensagem' => "Frequência de postagem configurada para 1 post por dia devido à baixa métrica de {$metrica} ({$valor_metrica})."
            ];
        }
    }
    
    /**
     * Registra a execução de uma regra no log
     * 
     * @param int $regra_id ID da regra
     * @param int $usuario_id ID do usuário
     * @param string $status Status da execução (sucesso, falha)
     * @param string $acoes_realizadas Descrição das ações realizadas
     * @return bool Resultado do registro
     */
    private function registrarExecucao($regra_id, $usuario_id, $status, $acoes_realizadas) {
        $query = "
            INSERT INTO logs_execucao_regras (
                regra_id,
                usuario_id,
                data_hora,
                status,
                acoes_realizadas
            ) VALUES (?, ?, NOW(), ?, ?)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iiss", $regra_id, $usuario_id, $status, $acoes_realizadas);
        
        return $stmt->execute();
    }
}
?>