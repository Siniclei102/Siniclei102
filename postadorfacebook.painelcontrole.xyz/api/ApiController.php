<?php
/**
 * Controlador principal da API REST
 * 
 * Esta classe gerencia todas as operações da API, incluindo autenticação,
 * validação de dados e interação com o banco de dados.
 * 
 * @version 1.0
 */
class ApiController {
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
     * Autenticar uma requisição usando o token fornecido
     * 
     * @param string $token Token de autenticação
     * @return array Resultado da autenticação
     */
    public function authenticate($token) {
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Token de autenticação não fornecido'
            ];
        }
        
        // Verificar token na tabela de tokens API
        $query = "
            SELECT 
                t.id,
                t.usuario_id,
                t.validade,
                u.status
            FROM 
                api_tokens t
                JOIN usuarios u ON t.usuario_id = u.id
            WHERE 
                t.token = ? 
                AND t.validade > NOW()
                AND t.revogado = 0
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => 'Token inválido ou expirado'
            ];
        }
        
        $token_data = $result->fetch_assoc();
        
        // Verificar se o usuário está ativo
        if ($token_data['status'] !== 'ativo') {
            return [
                'success' => false,
                'message' => 'Conta de usuário inativa ou suspensa'
            ];
        }
        
        // Atualizar último uso do token
        $query_update = "UPDATE api_tokens SET ultimo_uso = NOW() WHERE id = ?";
        $stmt_update = $this->db->prepare($query_update);
        $stmt_update->bind_param("i", $token_data['id']);
        $stmt_update->execute();
        
        return [
            'success' => true,
            'user_id' => $token_data['usuario_id']
        ];
    }
    
    /**
     * Processar login de usuário e gerar token de acesso
     * 
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function login($params) {
        // Validar parâmetros
        if (!isset($params['email']) || !isset($params['senha'])) {
            return [
                'status' => 'error',
                'message' => 'Email e senha são obrigatórios',
                'code' => 400
            ];
        }
        
        $email = $params['email'];
        $senha = $params['senha'];
        
        // Verificar usuário no banco de dados
        $query = "SELECT id, nome, senha, status FROM usuarios WHERE email = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => 'Usuário não encontrado',
                'code' => 404
            ];
        }
        
        $usuario = $result->fetch_assoc();
        
        // Verificar se a senha está correta
        if (!password_verify($senha, $usuario['senha'])) {
            return [
                'status' => 'error',
                'message' => 'Senha incorreta',
                'code' => 401
            ];
        }
        
        // Verificar se o usuário está ativo
        if ($usuario['status'] !== 'ativo') {
            return [
                'status' => 'error',
                'message' => 'Conta suspensa ou inativa',
                'code' => 403
            ];
        }
        
        // Gerar token de acesso
        $token = $this->generateToken();
        $usuario_id = $usuario['id'];
        $validade = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Inserir token no banco de dados
        $query_token = "
            INSERT INTO api_tokens (
                usuario_id, 
                token, 
                validade, 
                criado_em,
                ultimo_uso,
                descricao
            ) VALUES (?, ?, ?, NOW(), NOW(), 'Login via API')
        ";
        
        $stmt_token = $this->db->prepare($query_token);
        $stmt_token->bind_param("iss", $usuario_id, $token, $validade);
        
        if (!$stmt_token->execute()) {
            return [
                'status' => 'error',
                'message' => 'Erro ao gerar token de acesso',
                'code' => 500
            ];
        }
        
        // Registrar login
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'API Client';
        
        $query_log = "
            INSERT INTO logs_login (
                usuario_id,
                ip,
                user_agent,
                sucesso,
                data_login,
                metodo
            ) VALUES (?, ?, ?, 1, NOW(), 'api')
        ";
        
        $stmt_log = $this->db->prepare($query_log);
        $stmt_log->bind_param("iss", $usuario_id, $ip, $user_agent);
        $stmt_log->execute();
        
        return [
            'status' => 'success',
            'message' => 'Login realizado com sucesso',
            'data' => [
                'token' => $token,
                'validade' => $validade,
                'usuario' => [
                    'id' => $usuario_id,
                    'nome' => $usuario['nome']
                ]
            ],
            'code' => 200
        ];
    }
    
    /**
     * Registrar novo usuário
     * 
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function register($params) {
        // Validar parâmetros
        $campos_obrigatorios = ['nome', 'email', 'senha', 'senha_confirmacao'];
        
        foreach ($campos_obrigatorios as $campo) {
            if (!isset($params[$campo]) || empty($params[$campo])) {
                return [
                    'status' => 'error',
                    'message' => "O campo {$campo} é obrigatório",
                    'code' => 400
                ];
            }
        }
        
        $nome = $params['nome'];
        $email = $params['email'];
        $senha = $params['senha'];
        $senha_confirmacao = $params['senha_confirmacao'];
        
        // Verificar se as senhas coincidem
        if ($senha !== $senha_confirmacao) {
            return [
                'status' => 'error',
                'message' => 'As senhas não coincidem',
                'code' => 400
            ];
        }
        
        // Verificar comprimento da senha
        if (strlen($senha) < 6) {
            return [
                'status' => 'error',
                'message' => 'A senha deve ter pelo menos 6 caracteres',
                'code' => 400
            ];
        }
        
        // Verificar se o email já está em uso
        $query_check = "SELECT id FROM usuarios WHERE email = ? LIMIT 1";
        $stmt_check = $this->db->prepare($query_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            return [
                'status' => 'error',
                'message' => 'Este email já está em uso',
                'code' => 409
            ];
        }
        
        // Hash da senha
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Definir validade padrão (30 dias)
        $validade = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Inserir usuário
        $query = "
            INSERT INTO usuarios (
                nome, 
                email, 
                senha, 
                status, 
                tipo,
                criado_em,
                validade_ate
            ) VALUES (?, ?, ?, 'ativo', 'usuario', NOW(), ?)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ssss", $nome, $email, $senha_hash, $validade);
        
        if (!$stmt->execute()) {
            return [
                'status' => 'error',
                'message' => 'Erro ao registrar usuário',
                'code' => 500
            ];
        }
        
        $usuario_id = $stmt->insert_id;
        
        return [
            'status' => 'success',
            'message' => 'Usuário registrado com sucesso',
            'data' => [
                'id' => $usuario_id,
                'nome' => $nome,
                'email' => $email
            ],
            'code' => 201
        ];
    }
    
    /**
     * Obter dados do dashboard
     * 
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function getDashboard($params) {
        $user_id = $params['user_id'];
        $periodo = isset($params['periodo']) ? intval($params['periodo']) : 30;
        
        $periodos_validos = [7, 15, 30, 60, 90];
        if (!in_array($periodo, $periodos_validos)) {
            $periodo = 30;
        }
        
        $data_inicio = date('Y-m-d', strtotime("-{$periodo} days"));
        
        // Estatísticas gerais
        $query_stats = "
            SELECT
                (SELECT COUNT(*) FROM grupos_facebook WHERE usuario_id = ?) as total_grupos,
                (SELECT COUNT(*) FROM campanhas WHERE usuario_id = ? AND ativa = 1) as campanhas_ativas,
                (SELECT COUNT(*) FROM anuncios WHERE usuario_id = ? AND ativo = 1) as anuncios_ativos,
                (SELECT COUNT(*) FROM agendamentos WHERE usuario_id = ? AND status = 'agendado') as agendamentos_pendentes
        ";
        
        $stmt_stats = $this->db->prepare($query_stats);
        $stmt_stats->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
        $stmt_stats->execute();
        $stats = $stmt_stats->get_result()->fetch_assoc();
        
        // Estatísticas de postagens
        $query_posts = "
            SELECT
                COUNT(*) as total_posts,
                SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as posts_sucesso,
                SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as posts_falha
            FROM
                logs_postagem
            WHERE
                usuario_id = ?
                AND postado_em >= ?
        ";
        
        $stmt_posts = $this->db->prepare($query_posts);
        $stmt_posts->bind_param("is", $user_id, $data_inicio);
        $stmt_posts->execute();
        $posts_stats = $stmt_posts->get_result()->fetch_assoc();
        
        // Calcular taxa de sucesso
        $taxa_sucesso = $posts_stats['total_posts'] > 0 
            ? round(($posts_stats['posts_sucesso'] / $posts_stats['total_posts']) * 100, 1) 
            : 0;
        
        // Dados de engajamento (se disponíveis)
        $engagement = null;
        
        $query_engagement = "
            SELECT
                SUM(curtidas) as total_curtidas,
                SUM(comentarios) as total_comentarios,
                SUM(compartilhamentos) as total_compartilhamentos
            FROM
                facebook_posts
            WHERE
                usuario_id = ?
                AND data_postagem >= ?
        ";
        
        $stmt_engagement = $this->db->prepare($query_engagement);
        $stmt_engagement->bind_param("is", $user_id, $data_inicio);
        $stmt_engagement->execute();
        $result_engagement = $stmt_engagement->get_result();
        
        if ($result_engagement->num_rows > 0) {
            $engagement = $result_engagement->fetch_assoc();
        }
        
        // Postagens recentes
        $query_recentes = "
            SELECT
                l.id,
                l.campanha_id,
                c.nome as campanha_nome,
                g.nome as grupo_nome,
                a.titulo as anuncio_titulo,
                l.status,
                l.postado_em
            FROM
                logs_postagem l
                JOIN campanhas c ON l.campanha_id = c.id
                JOIN grupos_facebook g ON l.grupo_id = g.id
                JOIN anuncios a ON l.anuncio_id = a.id
            WHERE
                l.usuario_id = ?
            ORDER BY
                l.postado_em DESC
            LIMIT 5
        ";
        
        $stmt_recentes = $this->db->prepare($query_recentes);
        $stmt_recentes->bind_param("i", $user_id);
        $stmt_recentes->execute();
        $result_recentes = $stmt_recentes->get_result();
        
        $posts_recentes = [];
        while ($post = $result_recentes->fetch_assoc()) {
            $posts_recentes[] = [
                'id' => $post['id'],
                'campanha' => $post['campanha_nome'],
                'grupo' => $post['grupo_nome'],
                'anuncio' => $post['anuncio_titulo'],
                'status' => $post['status'],
                'data' => date('Y-m-d H:i:s', strtotime($post['postado_em']))
            ];
        }
        
        // Agendamentos próximos
        $query_agendamentos = "
            SELECT
                a.id,
                c.nome as campanha_nome,
                g.nome as grupo_nome,
                a.data_agendada
            FROM
                agendamentos a
                JOIN campanhas c ON a.campanha_id = c.id
                JOIN grupos_facebook g ON a.grupo_id = g.id
            WHERE
                a.usuario_id = ?
                AND a.status = 'agendado'
                AND a.data_agendada > NOW()
            ORDER BY
                a.data_agendada ASC
            LIMIT 5
        ";
        
        $stmt_agendamentos = $this->db->prepare($query_agendamentos);
        $stmt_agendamentos->bind_param("i", $user_id);
        $stmt_agendamentos->execute();
        $result_agendamentos = $stmt_agendamentos->get_result();
        
        $proximos_agendamentos = [];
        while ($agendamento = $result_agendamentos->fetch_assoc()) {
            $proximos_agendamentos[] = [
                'id' => $agendamento['id'],
                'campanha' => $agendamento['campanha_nome'],
                'grupo' => $agendamento['grupo_nome'],
                'data' => date('Y-m-d H:i:s', strtotime($agendamento['data_agendada']))
            ];
        }
        
        return [
            'status' => 'success',
            'data' => [
                'stats' => $stats,
                'posts' => [
                    'total' => $posts_stats['total_posts'],
                    'sucesso' => $posts_stats['posts_sucesso'],
                    'falha' => $posts_stats['posts_falha'],
                    'taxa_sucesso' => $taxa_sucesso
                ],
                'engagement' => $engagement,
                'posts_recentes' => $posts_recentes,
                'agendamentos' => $proximos_agendamentos
            ],
            'code' => 200
        ];
    }
    
    /**
     * Obter lista de grupos
     * 
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function getGrupos($params) {
        $user_id = $params['user_id'];
        $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
        $per_page = isset($params['per_page']) ? min(100, max(1, intval($params['per_page']))) : 20;
        $offset = ($page - 1) * $per_page;
        
        // Filtros
        $where = "WHERE g.usuario_id = ?";
        $where_params = [$user_id];
        $param_types = "i";
        
        // Filtro por nome
        if (isset($params['nome']) && !empty($params['nome'])) {
            $nome = "%" . $params['nome'] . "%";
            $where .= " AND g.nome LIKE ?";
            $where_params[] = $nome;
            $param_types .= "s";
        }
        
        // Filtro por status
        if (isset($params['ativo']) && ($params['ativo'] === '0' || $params['ativo'] === '1')) {
            $ativo = intval($params['ativo']);
            $where .= " AND g.ativo = ?";
            $where_params[] = $ativo;
            $param_types .= "i";
        }
        
        // Contar total de registros
        $query_count = "SELECT COUNT(*) as total FROM grupos_facebook g $where";
        $stmt_count = $this->db->prepare($query_count);
        $stmt_count->bind_param($param_types, ...$where_params);
        $stmt_count->execute();
        $total = $stmt_count->get_result()->fetch_assoc()['total'];
        
        // Buscar registros
        $query = "
            SELECT
                g.id,
                g.nome,
                g.facebook_id,
                g.url,
                g.ativo,
                g.membros,
                g.privacidade,
                g.ativo,
                g.ordem,
                g.criado_em,
                g.ultima_atualizacao,
                IFNULL(
                    (SELECT COUNT(*) FROM logs_postagem lp WHERE lp.grupo_id = g.id)
                , 0) as total_posts
            FROM
                grupos_facebook g
            $where
            ORDER BY g.nome ASC
            LIMIT ?, ?
        ";
        
        $stmt = $this->db->prepare($query);
        $where_params[] = $offset;
        $where_params[] = $per_page;
        $param_types .= "ii";
        $stmt->bind_param($param_types, ...$where_params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $grupos = [];
        while ($grupo = $result->fetch_assoc()) {
            $grupos[] = [
                'id' => $grupo['id'],
                'nome' => $grupo['nome'],
                'facebook_id' => $grupo['facebook_id'],
                'url' => $grupo['url'],
                'ativo' => (bool)$grupo['ativo'],
                'membros' => $grupo['membros'],
                'privacidade' => $grupo['privacidade'],
                'ordem' => $grupo['ordem'],
                'criado_em' => $grupo['criado_em'],
                'ultima_atualizacao' => $grupo['ultima_atualizacao'],
                'total_posts' => $grupo['total_posts']
            ];
        }
        
        // Calcular paginação
        $total_pages = ceil($total / $per_page);
        
        return [
            'status' => 'success',
            'data' => [
                'grupos' => $grupos,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $per_page,
                    'current_page' => $page,
                    'total_pages' => $total_pages
                ]
            ],
            'code' => 200
        ];
    }
    
    /**
     * Obter detalhes de um grupo específico
     * 
     * @param int $grupo_id ID do grupo
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function getGrupo($grupo_id, $params) {
        $user_id = $params['user_id'];
        
        // Verificar se o grupo pertence ao usuário
        $query = "
            SELECT
                g.id,
                g.nome,
                g.facebook_id,
                g.url,
                g.ativo,
                g.membros,
                g.privacidade,
                g.descricao,
                g.regras,
                g.ativo,
                g.ordem,
                g.criado_em,
                g.ultima_atualizacao
            FROM
                grupos_facebook g
            WHERE
                g.id = ?
                AND g.usuario_id = ?
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $grupo_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => 'Grupo não encontrado',
                'code' => 404
            ];
        }
        
        $grupo = $result->fetch_assoc();
        
        // Obter estatísticas de postagem
        $query_stats = "
            SELECT
                COUNT(*) as total_posts,
                SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as posts_sucesso,
                SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as posts_falha,
                MAX(postado_em) as ultima_postagem
            FROM
                logs_postagem
            WHERE
                grupo_id = ?
                AND usuario_id = ?
        ";
        
        $stmt_stats = $this->db->prepare($query_stats);
        $stmt_stats->bind_param("ii", $grupo_id, $user_id);
        $stmt_stats->execute();
        $stats = $stmt_stats->get_result()->fetch_assoc();
        
        // Calcular taxa de sucesso
        $taxa_sucesso = $stats['total_posts'] > 0 
            ? round(($stats['posts_sucesso'] / $stats['total_posts']) * 100, 1) 
            : 0;
            
        // Formatar resposta
        $grupo_data = [
            'id' => $grupo['id'],
            'nome' => $grupo['nome'],
            'facebook_id' => $grupo['facebook_id'],
            'url' => $grupo['url'],
            'ativo' => (bool)$grupo['ativo'],
            'membros' => $grupo['membros'],
            'privacidade' => $grupo['privacidade'],
            'descricao' => $grupo['descricao'],
            'regras' => $grupo['regras'],
            'ordem' => $grupo['ordem'],
            'criado_em' => $grupo['criado_em'],
            'ultima_atualizacao' => $grupo['ultima_atualizacao'],
            'stats' => [
                'total_posts' => $stats['total_posts'],
                'posts_sucesso' => $stats['posts_sucesso'],
                'posts_falha' => $stats['posts_falha'],
                'taxa_sucesso' => $taxa_sucesso,
                'ultima_postagem' => $stats['ultima_postagem']
            ]
        ];
        
        return [
            'status' => 'success',
            'data' => $grupo_data,
            'code' => 200
        ];
    }
    
    /**
     * Criar novo grupo
     * 
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function createGrupo($params) {
        $user_id = $params['user_id'];
        
        // Validar campos obrigatórios
        if (!isset($params['nome']) || empty($params['nome'])) {
            return [
                'status' => 'error',
                'message' => 'O nome do grupo é obrigatório',
                'code' => 400
            ];
        }
        
        $nome = $params['nome'];
        $url = isset($params['url']) ? $params['url'] : '';
        $facebook_id = isset($params['facebook_id']) ? $params['facebook_id'] : '';
        $descricao = isset($params['descricao']) ? $params['descricao'] : '';
        $regras = isset($params['regras']) ? $params['regras'] : '';
        $ativo = isset($params['ativo']) ? (int)$params['ativo'] : 1;
        $membros = isset($params['membros']) ? (int)$params['membros'] : 0;
        $privacidade = isset($params['privacidade']) ? $params['privacidade'] : 'UNKNOWN';
        $ordem = isset($params['ordem']) ? (int)$params['ordem'] : 0;
        
        // Inserir grupo
        $query = "
            INSERT INTO grupos_facebook (
                usuario_id,
                nome,
                url,
                facebook_id,
                descricao,
                regras,
                ativo,
                membros,
                privacidade,
                ordem,
                criado_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "isssssisis",
            $user_id,
            $nome,
            $url,
            $facebook_id,
            $descricao,
            $regras,
            $ativo,
            $membros,
            $privacidade,
            $ordem
        );
        
        if (!$stmt->execute()) {
            return [
                'status' => 'error',
                'message' => 'Erro ao criar grupo',
                'code' => 500
            ];
        }
        
        $grupo_id = $stmt->insert_id;
        
        return [
            'status' => 'success',
            'message' => 'Grupo criado com sucesso',
            'data' => [
                'id' => $grupo_id,
                'nome' => $nome
            ],
            'code' => 201
        ];
    }
    
    /**
     * Atualizar grupo existente
     * 
     * @param int $grupo_id ID do grupo
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function updateGrupo($grupo_id, $params) {
        $user_id = $params['user_id'];
        
        // Verificar se o grupo pertence ao usuário
        $query_check = "SELECT id FROM grupos_facebook WHERE id = ? AND usuario_id = ?";
        $stmt_check = $this->db->prepare($query_check);
        $stmt_check->bind_param("ii", $grupo_id, $user_id);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => 'Grupo não encontrado ou não pertence a este usuário',
                'code' => 404
            ];
        }
        
        // Construir query de atualização
        $campos = [];
        $valores = [];
        $tipos = "";
        
        if (isset($params['nome'])) {
            $campos[] = "nome = ?";
            $valores[] = $params['nome'];
            $tipos .= "s";
        }
        
        if (isset($params['url'])) {
            $campos[] = "url = ?";
            $valores[] = $params['url'];
            $tipos .= "s";
        }
        
        if (isset($params['facebook_id'])) {
            $campos[] = "facebook_id = ?";
            $valores[] = $params['facebook_id'];
            $tipos .= "s";
        }
        
        if (isset($params['descricao'])) {
            $campos[] = "descricao = ?";
            $valores[] = $params['descricao'];
            $tipos .= "s";
        }
        
        if (isset($params['regras'])) {
            $campos[] = "regras = ?";
            $valores[] = $params['regras'];
            $tipos .= "s";
        }
        
        if (isset($params['ativo'])) {
            $campos[] = "ativo = ?";
            $valores[] = (int)$params['ativo'];
            $tipos .= "i";
        }
        
        if (isset($params['membros'])) {
            $campos[] = "membros = ?";
            $valores[] = (int)$params['membros'];
            $tipos .= "i";
        }
        
        if (isset($params['privacidade'])) {
            $campos[] = "privacidade = ?";
            $valores[] = $params['privacidade'];
            $tipos .= "s";
        }
        
        if (isset($params['ordem'])) {
            $campos[] = "ordem = ?";
            $valores[] = (int)$params['ordem'];
            $tipos .= "i";
        }
        
        if (empty($campos)) {
            return [
                'status' => 'error',
                'message' => 'Nenhum campo para atualizar',
                'code' => 400
            ];
        }
        
        // Adicionar campo de última atualização
        $campos[] = "ultima_atualizacao = NOW()";
        
        // Construir e executar query
        $query = "UPDATE grupos_facebook SET " . implode(", ", $campos) . " WHERE id = ? AND usuario_id = ?";
        $stmt = $this->db->prepare($query);
        
        $valores[] = $grupo_id;
        $valores[] = $user_id;
        $tipos .= "ii";
        
        $stmt->bind_param($tipos, ...$valores);
        
        if (!$stmt->execute()) {
            return [
                'status' => 'error',
                'message' => 'Erro ao atualizar grupo',
                'code' => 500
            ];
        }
        
        return [
            'status' => 'success',
            'message' => 'Grupo atualizado com sucesso',
            'data' => [
                'id' => $grupo_id
            ],
            'code' => 200
        ];
    }
    
    /**
     * Excluir grupo
     * 
     * @param int $grupo_id ID do grupo
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function deleteGrupo($grupo_id, $params) {
        $user_id = $params['user_id'];
        
        // Verificar se o grupo pertence ao usuário
        $query_check = "SELECT id FROM grupos_facebook WHERE id = ? AND usuario_id = ?";
        $stmt_check = $this->db->prepare($query_check);
        $stmt_check->bind_param("ii", $grupo_id, $user_id);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => 'Grupo não encontrado ou não pertence a este usuário',
                'code' => 404
            ];
        }
        
        // Excluir grupo
        $query = "DELETE FROM grupos_facebook WHERE id = ? AND usuario_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $grupo_id, $user_id);
        
        if (!$stmt->execute()) {
            return [
                'status' => 'error',
                'message' => 'Erro ao excluir grupo',
                'code' => 500
            ];
        }
        
        return [
            'status' => 'success',
            'message' => 'Grupo excluído com sucesso',
            'code' => 200
        ];
    }
    
    /**
     * Método genérico para implementar
     * 
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function getRelatorios($params) {
        return [
            'status' => 'success',
            'message' => 'Método em implementação',
            'code' => 200
        ];
    }
    
    /**
     * Método genérico para implementar
     * 
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function getMetricas($params) {
        return [
            'status' => 'success',
            'message' => 'Método em implementação',
            'code' => 200
        ];
    }
    
    /**
     * Postar imediatamente em um grupo
     * 
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function postarAgora($params) {
        $user_id = $params['user_id'];
        
        // Validar parâmetros obrigatórios
        if (!isset($params['grupo_id']) || !isset($params['campanha_id'])) {
            return [
                'status' => 'error',
                'message' => 'Grupo e campanha são obrigatórios',
                'code' => 400
            ];
        }
        
        $grupo_id = intval($params['grupo_id']);
        $campanha_id = intval($params['campanha_id']);
        $anuncio_id = isset($params['anuncio_id']) ? intval($params['anuncio_id']) : null;
        
        // Verificar se o grupo pertence ao usuário
        $query_grupo = "
            SELECT
                g.id,
                g.nome,
                g.facebook_id
            FROM
                grupos_facebook g
            WHERE
                g.id = ?
                AND g.usuario_id = ?
                AND g.ativo = 1
            LIMIT 1
        ";
        
        $stmt_grupo = $this->db->prepare($query_grupo);
        $stmt_grupo->bind_param("ii", $grupo_id, $user_id);
        $stmt_grupo->execute();
        $result_grupo = $stmt_grupo->get_result();
        
        if ($result_grupo->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => 'Grupo não encontrado, inativo ou não pertence a este usuário',
                'code' => 404
            ];
        }
        
        $grupo = $result_grupo->fetch_assoc();
        
        if (empty($grupo['facebook_id'])) {
            return [
                'status' => 'error',
                'message' => 'O grupo não possui ID do Facebook definido',
                'code' => 400
            ];
        }
        
        // Verificar se a campanha pertence ao usuário
        $query_campanha = "
            SELECT
                c.id,
                c.nome
            FROM
                campanhas c
            WHERE
                c.id = ?
                AND c.usuario_id = ?
                AND c.ativa = 1
            LIMIT 1
        ";
        
        $stmt_campanha = $this->db->prepare($query_campanha);
        $stmt_campanha->bind_param("ii", $campanha_id, $user_id);
        $stmt_campanha->execute();
        $result_campanha = $stmt_campanha->get_result();
        
        if ($result_campanha->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => 'Campanha não encontrada, inativa ou não pertence a este usuário',
                'code' => 404
            ];
        }
        
        // Obter anúncio a ser postado
        if ($anuncio_id) {
            $query_anuncio = "
                SELECT
                    a.id,
                    a.titulo,
                    a.descricao,
                    a.link,
                    a.imagem_url
                FROM
                    anuncios a
                WHERE
                    a.id = ?
                    AND a.campanha_id = ?
                    AND a.ativo = 1
                LIMIT 1
            ";
            
            $stmt_anuncio = $this->db->prepare($query_anuncio);
            $stmt_anuncio->bind_param("ii", $anuncio_id, $campanha_id);
        } else {
            $query_anuncio = "
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
            
            $stmt_anuncio = $this->db->prepare($query_anuncio);
            $stmt_anuncio->bind_param("i", $campanha_id);
        }
        
        $stmt_anuncio->execute();
        $result_anuncio = $stmt_anuncio->get_result();
        
        if ($result_anuncio->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => 'Nenhum anúncio encontrado para esta campanha',
                'code' => 404
            ];
        }
        
        $anuncio = $result_anuncio->fetch_assoc();
        
        // Obter token de acesso do Facebook
        $query_token = "SELECT facebook_token FROM usuarios WHERE id = ? LIMIT 1";
        $stmt_token = $this->db->prepare($query_token);
        $stmt_token->bind_param("i", $user_id);
        $stmt_token->execute();
        $result_token = $stmt_token->get_result();
        
        if ($result_token->num_rows === 0 || empty($result_token->fetch_assoc()['facebook_token'])) {
            return [
                'status' => 'error',
                'message' => 'Token de acesso ao Facebook não encontrado',
                'code' => 400
            ];
        }
        
        $token = $result_token->fetch_assoc()['facebook_token'];
        
        // Construir mensagem e publicar no Facebook
        $mensagem = "{$anuncio['titulo']}\n\n{$anuncio['descricao']}";
        
        // Instanciar classe do Facebook
        $fb = new FacebookAPI($this->db);
        $resultado = $fb->postToGroup(
            $grupo['facebook_id'],
            $mensagem,
            $anuncio['link'],
            $token
        );
        
        if (!$resultado['success']) {
            // Registrar erro no log
            $erro = isset($resultado['error']['error']['message']) 
                ? $resultado['error']['error']['message'] 
                : 'Erro desconhecido ao publicar no Facebook';
                
            $query_log = "
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
            
            $stmt_log = $this->db->prepare($query_log);
            $stmt_log->bind_param("iiiis", $user_id, $campanha_id, $grupo_id, $anuncio['id'], $erro);
            $stmt_log->execute();
            
            return [
                'status' => 'error',
                'message' => "Erro ao publicar no Facebook: {$erro}",
                'code' => 500
            ];
        }
        
        // Registrar sucesso no log
        $query_log = "
            INSERT INTO logs_postagem (
                usuario_id,
                campanha_id,
                grupo_id,
                anuncio_id,
                status,
                postado_em
            ) VALUES (?, ?, ?, ?, 'sucesso', NOW())
        ";
        
        $stmt_log = $this->db->prepare($query_log);
        $stmt_log->bind_param("iiii", $user_id, $campanha_id, $grupo_id, $anuncio['id']);
        $stmt_log->execute();
        
        // Salvar métricas no Facebook
        $fb->savePost([
            'usuario_id' => $user_id,
            'campanha_id' => $campanha_id,
            'grupo_id' => $grupo_id,
            'anuncio_id' => $anuncio['id'],
            'post_id' => $resultado['post_id'],
            'texto' => $mensagem,
            'link' => $anuncio['link'],
            'imagem_url' => $anuncio['imagem_url']
        ]);
        
        return [
            'status' => 'success',
            'message' => "Publicação realizada com sucesso no grupo {$grupo['nome']}",
            'data' => [
                'post_id' => $resultado['post_id'],
                'grupo' => $grupo['nome'],
                'anuncio' => $anuncio['titulo']
            ],
            'code' => 200
        ];
    }
    
    /**
     * Obter perfil do usuário
     * 
     * @param array $params Parâmetros da requisição
     * @return array Resultado da operação
     */
    public function getPerfil($params) {
        $user_id = $params['user_id'];
        
        $query = "
            SELECT
                u.id,
                u.nome,
                u.email,
                u.tipo,
                u.status,
                u.criado_em,
                u.ultimo_login,
                u.validade_ate,
                u.facebook_id,
                u.facebook_token IS NOT NULL as has_facebook_token
            FROM
                usuarios u
            WHERE
                u.id = ?
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'status' => 'error',
                'message' => 'Usuário não encontrado',
                'code' => 404
            ];
        }
        
        $usuario = $result->fetch_assoc();
        
        // Calcular dias restantes de validade
        $dias_restantes = null;
        if ($usuario['validade_ate']) {
            $hoje = new DateTime();
            $validade = new DateTime($usuario['validade_ate']);
            
            if ($validade > $hoje) {
                $dias_restantes = $hoje->diff($validade)->days;
            }
        }
        
        // Obter tokens da API
        $query_tokens = "
            SELECT
                id,
                token,
                validade,
                criado_em,
                ultimo_uso,
                descricao,
                revogado
            FROM
                api_tokens
            WHERE
                usuario_id = ?
            ORDER BY
                criado_em DESC
        ";
        
        $stmt_tokens = $this->db->prepare($query_tokens);
        $stmt_tokens->bind_param("i", $user_id);
        $stmt_tokens->execute();
        $result_tokens = $stmt_tokens->get_result();
        
        $tokens = [];
        while ($token = $result_tokens->fetch_assoc()) {
            $tokens[] = [
                'id' => $token['id'],
                'token' => substr($token['token'], 0, 8) . '...',
                'validade' => $token['validade'],
                'criado_em' => $token['criado_em'],
                'ultimo_uso' => $token['ultimo_uso'],
                'descricao' => $token['descricao'],
                'revogado' => (bool)$token['revogado'],
                'is_valid' => !$token['revogado'] && (new DateTime($token['validade']) > new DateTime())
            ];
        }
        
        return [
            'status' => 'success',
            'data' => [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'tipo' => $usuario['tipo'],
                'status' => $usuario['status'],
                'criado_em' => $usuario['criado_em'],
                'ultimo_login' => $usuario['ultimo_login'],
                'validade_ate' => $usuario['validade_ate'],
                'dias_restantes' => $dias_restantes,
                'has_facebook_token' => (bool)$usuario['has_facebook_token'],
                'facebook_id' => $usuario['facebook_id'],
                'api_tokens' => $tokens
            ],
            'code' => 200
        ];
    }
    
    /**
     * Gerar token aleatório
     * 
     * @param int $length Comprimento do token
     * @return string Token gerado
     */
    private function generateToken($length = 64) {
        $token = bin2hex(random_bytes($length / 2));
        return $token;
    }
}
?>