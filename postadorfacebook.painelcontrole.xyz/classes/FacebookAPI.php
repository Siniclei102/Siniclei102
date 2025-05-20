<?php
/**
 * Classe para integração com a API do Facebook
 * 
 * Esta classe gerencia operações relacionadas à API do Facebook para obtenção de métricas
 */
class FacebookAPI {
    private $db;
    private $appId;
    private $appSecret;
    private $defaultAccessToken;
    
    /**
     * Construtor da classe
     * 
     * @param mysqli $db Conexão com o banco de dados
     */
    public function __construct($db) {
        $this->db = $db;
        
        // Obter configurações do Facebook
        $query = "SELECT facebook_app_id, facebook_app_secret, facebook_default_token FROM configuracoes LIMIT 1";
        $result = $db->query($query);
        
        if ($result->num_rows > 0) {
            $config = $result->fetch_assoc();
            $this->appId = $config['facebook_app_id'];
            $this->appSecret = $config['facebook_app_secret'];
            $this->defaultAccessToken = $config['facebook_default_token'];
        }
    }
    
    /**
     * Verifica se o usuário possui token de acesso ao Facebook
     * 
     * @param int $userId ID do usuário
     * @return bool
     */
    public function hasUserToken($userId) {
        $query = "SELECT facebook_token FROM usuarios WHERE id = ? AND facebook_token IS NOT NULL AND facebook_token != ''";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    /**
     * Obtém o token de acesso do usuário
     * 
     * @param int $userId ID do usuário
     * @return string|null Token de acesso ou null se não houver
     */
    public function getUserToken($userId) {
        $query = "SELECT facebook_token, facebook_token_expiry FROM usuarios WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verificar se o token não expirou
            if ($user['facebook_token_expiry'] && strtotime($user['facebook_token_expiry']) > time()) {
                return $user['facebook_token'];
            }
        }
        
        return null;
    }
    
    /**
     * Atualiza o token de acesso do usuário
     * 
     * @param int $userId ID do usuário
     * @param string $token Token de acesso
     * @param string $expiry Data de expiração (Y-m-d H:i:s)
     * @return bool
     */
    public function updateUserToken($userId, $token, $expiry) {
        $query = "UPDATE usuarios SET facebook_token = ?, facebook_token_expiry = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ssi", $token, $expiry, $userId);
        
        return $stmt->execute();
    }
    
    /**
     * Busca métricas de um post do Facebook via API
     * 
     * @param string $postId ID do post no Facebook
     * @param string $token Token de acesso
     * @return array|false Métricas do post ou false em caso de erro
     */
    public function getPostMetrics($postId, $token) {
        $fields = "reactions.summary(true),comments.summary(true),shares";
        $url = "https://graph.facebook.com/v16.0/{$postId}?fields={$fields}&access_token={$token}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            
            if (isset($data['id'])) {
                // Extrair métricas
                $metrics = [
                    'curtidas' => isset($data['reactions']['summary']['total_count']) ? $data['reactions']['summary']['total_count'] : 0,
                    'comentarios' => isset($data['comments']['summary']['total_count']) ? $data['comments']['summary']['total_count'] : 0,
                    'compartilhamentos' => isset($data['shares']['count']) ? $data['shares']['count'] : 0,
                    'reacao_like' => 0,
                    'reacao_love' => 0,
                    'reacao_wow' => 0,
                    'reacao_haha' => 0,
                    'reacao_sad' => 0,
                    'reacao_angry' => 0,
                    'reacao_care' => 0
                ];
                
                // Buscar detalhes das reações
                if (isset($data['reactions']['data'])) {
                    foreach ($data['reactions']['data'] as $reaction) {
                        switch ($reaction['type']) {
                            case 'LIKE':
                                $metrics['reacao_like']++;
                                break;
                            case 'LOVE':
                                $metrics['reacao_love']++;
                                break;
                            case 'WOW':
                                $metrics['reacao_wow']++;
                                break;
                            case 'HAHA':
                                $metrics['reacao_haha']++;
                                break;
                            case 'SAD':
                                $metrics['reacao_sad']++;
                                break;
                            case 'ANGRY':
                                $metrics['reacao_angry']++;
                                break;
                            case 'CARE':
                                $metrics['reacao_care']++;
                                break;
                        }
                    }
                }
                
                return $metrics;
            }
        }
        
        return false;
    }
    
    /**
     * Atualiza as métricas de posts de um usuário
     * 
     * @param int $userId ID do usuário
     * @param int $dias Número de dias para trás para atualizar
     * @return array Estatísticas da atualização
     */
    public function updateUserPostMetrics($userId, $dias = 30) {
        // Obter token de acesso
        $token = $this->getUserToken($userId);
        
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Usuário não possui token de acesso válido ao Facebook'
            ];
        }
        
        // Obter posts recentes
        $dataLimite = date('Y-m-d', strtotime("-{$dias} days"));
        $query = "
            SELECT 
                fp.id,
                fp.post_id
            FROM 
                facebook_posts fp
            WHERE 
                fp.usuario_id = ?
                AND fp.data_postagem >= ?
                AND fp.post_id IS NOT NULL
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("is", $userId, $dataLimite);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $estatisticas = [
            'total' => $result->num_rows,
            'atualizados' => 0,
            'erros' => 0
        ];
        
        // Processar cada post
        while ($post = $result->fetch_assoc()) {
            if (empty($post['post_id'])) {
                $estatisticas['erros']++;
                continue;
            }
            
            // Obter métricas via API
            $metrics = $this->getPostMetrics($post['post_id'], $token);
            
            if ($metrics) {
                // Atualizar no banco de dados
                $queryUpdate = "
                    UPDATE facebook_posts 
                    SET 
                        curtidas = ?,
                        comentarios = ?,
                        compartilhamentos = ?,
                        reacao_like = ?,
                        reacao_love = ?,
                        reacao_wow = ?,
                        reacao_haha = ?,
                        reacao_sad = ?,
                        reacao_angry = ?,
                        reacao_care = ?,
                        ultima_atualizacao = NOW()
                    WHERE id = ?
                ";
                
                $stmtUpdate = $this->db->prepare($queryUpdate);
                $stmtUpdate->bind_param(
                    "iiiiiiiiiii",
                    $metrics['curtidas'],
                    $metrics['comentarios'],
                    $metrics['compartilhamentos'],
                    $metrics['reacao_like'],
                    $metrics['reacao_love'],
                    $metrics['reacao_wow'],
                    $metrics['reacao_haha'],
                    $metrics['reacao_sad'],
                    $metrics['reacao_angry'],
                    $metrics['reacao_care'],
                    $post['id']
                );
                
                if ($stmtUpdate->execute()) {
                    $estatisticas['atualizados']++;
                } else {
                    $estatisticas['erros']++;
                }
            } else {
                $estatisticas['erros']++;
            }
            
            // Pausa para não sobrecarregar a API
            usleep(200000); // 200ms
        }
        
        return [
            'success' => true,
            'stats' => $estatisticas
        ];
    }
    
    /**
     * Salva um novo post do Facebook no banco de dados
     * 
     * @param array $data Dados do post
     * @return int|false ID do post inserido ou false em caso de erro
     */
    public function savePost($data) {
        $query = "
            INSERT INTO facebook_posts (
                usuario_id,
                campanha_id,
                grupo_id,
                anuncio_id,
                post_id,
                texto,
                link,
                imagem_url,
                data_postagem,
                curtidas,
                comentarios,
                compartilhamentos
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, 0, 0)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "iiissss",
            $data['usuario_id'],
            $data['campanha_id'],
            $data['grupo_id'],
            $data['anuncio_id'],
            $data['post_id'],
            $data['texto'],
            $data['link'],
            $data['imagem_url']
        );
        
        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        
        return false;
    }
    
    /**
     * Gera URL de login com Facebook para obtenção de token
     * 
     * @param string $redirectUrl URL de redirecionamento após autorização
     * @return string URL de autorização
     */
    public function getLoginUrl($redirectUrl) {
        $state = bin2hex(random_bytes(16)); // Token anti-CSRF
        
        // Salvar state em sessão para verificar no retorno
        $_SESSION['fb_state'] = $state;
        
        $permissions = 'public_profile,email,pages_read_engagement,pages_show_list,groups_access_member_info';
        
        return "https://www.facebook.com/v16.0/dialog/oauth"
            . "?client_id={$this->appId}"
            . "&redirect_uri=" . urlencode($redirectUrl)
            . "&state={$state}"
            . "&scope=" . urlencode($permissions);
    }
    
    /**
     * Troca código de autorização por token de acesso
     * 
     * @param string $code Código retornado pelo Facebook
     * @param string $redirectUrl URL de redirecionamento (deve ser a mesma usada na autorização)
     * @return array|false Token de acesso ou false em caso de erro
     */
    public function getAccessToken($code, $redirectUrl) {
        $url = "https://graph.facebook.com/v16.0/oauth/access_token"
            . "?client_id={$this->appId}"
            . "&client_secret={$this->appSecret}"
            . "&redirect_uri=" . urlencode($redirectUrl)
            . "&code={$code}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            
            if (isset($data['access_token'])) {
                // Obter informações do token
                $tokenInfo = $this->getTokenInfo($data['access_token']);
                
                if ($tokenInfo) {
                    return [
                        'access_token' => $data['access_token'],
                        'expires_in' => isset($data['expires_in']) ? $data['expires_in'] : 0,
                        'expiry_date' => date('Y-m-d H:i:s', time() + $data['expires_in']),
                        'user_id' => $tokenInfo['user_id']
                    ];
                }
            }
        }
        
        return false;
    }
    
    /**
     * Obtém informações do token de acesso
     * 
     * @param string $token Token de acesso
     * @return array|false Informações do token ou false em caso de erro
     */
    public function getTokenInfo($token) {
        $url = "https://graph.facebook.com/debug_token"
            . "?input_token={$token}"
            . "&access_token={$this->appId}|{$this->appSecret}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            
            if (isset($data['data']) && isset($data['data']['user_id'])) {
                return [
                    'user_id' => $data['data']['user_id'],
                    'app_id' => $data['data']['app_id'],
                    'expires_at' => isset($data['data']['expires_at']) ? $data['data']['expires_at'] : 0,
                    'is_valid' => $data['data']['is_valid']
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Obtém informações do usuário do Facebook
     * 
     * @param string $token Token de acesso
     * @return array|false Informações do usuário ou false em caso de erro
     */
    public function getUserInfo($token) {
        $url = "https://graph.facebook.com/me?fields=id,name,email,picture.width(200).height(200)&access_token={$token}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    /**
     * Obtém lista de grupos do usuário
     * 
     * @param string $token Token de acesso
     * @return array|false Lista de grupos ou false em caso de erro
     */
    public function getUserGroups($token) {
        $url = "https://graph.facebook.com/me/groups?fields=id,name,administrator,member_count,privacy&limit=100&access_token={$token}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            
            if (isset($data['data'])) {
                return $data['data'];
            }
        }
        
        return false;
    }
    
    /**
     * Publica um post em um grupo do Facebook
     * 
     * @param string $groupId ID do grupo no Facebook
     * @param string $message Mensagem do post
     * @param string $link Link opcional
     * @param string $token Token de acesso
     * @return array|false ID do post criado ou false em caso de erro
     */
    public function postToGroup($groupId, $message, $link = null, $token = null) {
        // Usar token do usuário ou token padrão
        $accessToken = $token ?? $this->defaultAccessToken;
        
        if (!$accessToken) {
            return false;
        }
        
        $url = "https://graph.facebook.com/{$groupId}/feed";
        $params = ['message' => $message];
        
        if ($link) {
            $params['link'] = $link;
        }
        
        $params['access_token'] = $accessToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            
            if (isset($data['id'])) {
                return [
                    'post_id' => $data['id'],
                    'success' => true
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => json_decode($response, true)
        ];
    }
    
    /**
     * Obtém estatísticas gerais de um usuário no Facebook
     * 
     * @param int $userId ID do usuário no banco de dados
     * @param int $dias Número de dias para análise
     * @return array Estatísticas do usuário
     */
    public function getUserStats($userId, $dias = 30) {
        $dataLimite = date('Y-m-d', strtotime("-{$dias} days"));
        
        $query = "
            SELECT 
                COUNT(*) as total_posts,
                SUM(fp.curtidas) as total_curtidas,
                SUM(fp.comentarios) as total_comentarios,
                SUM(fp.compartilhamentos) as total_compartilhamentos,
                ROUND(AVG(fp.curtidas), 1) as media_curtidas,
                ROUND(AVG(fp.comentarios), 1) as media_comentarios,
                ROUND(AVG(fp.compartilhamentos), 1) as media_compartilhamentos,
                COUNT(DISTINCT fp.grupo_id) as grupos_alcancados
            FROM 
                facebook_posts fp
            WHERE 
                fp.usuario_id = ?
                AND fp.data_postagem >= ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("is", $userId, $dataLimite);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return [
            'total_posts' => 0,
            'total_curtidas' => 0,
            'total_comentarios' => 0,
            'total_compartilhamentos' => 0,
            'media_curtidas' => 0,
            'media_comentarios' => 0,
            'media_compartilhamentos' => 0,
            'grupos_alcancados' => 0
        ];
    }
    
    /**
     * Programa um post para um horário específico
     * 
     * @param array $postData Dados do post
     * @return bool Sucesso ou falha
     */
    public function schedulePost($postData) {
        $query = "
            INSERT INTO posts_agendados (
                usuario_id,
                campanha_id,
                grupo_id,
                anuncio_id,
                data_agendada,
                status
            ) VALUES (?, ?, ?, ?, ?, 'agendado')
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "iiiis",
            $postData['usuario_id'],
            $postData['campanha_id'],
            $postData['grupo_id'],
            $postData['anuncio_id'],
            $postData['data_agendada']
        );
        
        return $stmt->execute();
    }
    
    /**
     * Publica uma imagem em um grupo do Facebook
     * 
     * @param string $groupId ID do grupo no Facebook
     * @param string $message Mensagem do post
     * @param string $imageUrl URL da imagem
     * @param string $token Token de acesso
     * @return array|false ID do post criado ou false em caso de erro
     */
    public function postImageToGroup($groupId, $message, $imageUrl, $token = null) {
        // Usar token do usuário ou token padrão
        $accessToken = $token ?? $this->defaultAccessToken;
        
        if (!$accessToken) {
            return false;
        }
        
        // Publicar imagem no grupo
        $url = "https://graph.facebook.com/{$groupId}/photos";
        $params = [
            'message' => $message,
            'url' => $imageUrl,
            'access_token' => $accessToken
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            
            if (isset($data['id']) || isset($data['post_id'])) {
                return [
                    'post_id' => isset($data['post_id']) ? $data['post_id'] : $data['id'],
                    'success' => true
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => json_decode($response, true)
        ];
    }
    
    /**
     * Atualiza os dados de um grupo do Facebook
     * 
     * @param int $groupId ID do grupo no banco de dados
     * @param string $facebookId ID do grupo no Facebook
     * @param string $token Token de acesso
     * @return bool Sucesso ou falha
     */
    public function updateGroupInfo($groupId, $facebookId, $token) {
        // Buscar informações do grupo
        $url = "https://graph.facebook.com/{$facebookId}?fields=name,member_count,privacy,description&access_token={$token}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            
            if (isset($data['id'])) {
                $query = "
                    UPDATE grupos_facebook SET 
                        nome = ?,
                        membros = ?,
                        privacidade = ?,
                        descricao = ?,
                        ultima_atualizacao = NOW()
                    WHERE id = ?
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->bind_param(
                    "sissi",
                    $data['name'],
                    isset($data['member_count']) ? $data['member_count'] : 0,
                    isset($data['privacy']) ? $data['privacy'] : 'UNKNOWN',
                    isset($data['description']) ? $data['description'] : '',
                    $groupId
                );
                
                return $stmt->execute();
            }
        }
        
        return false;
    }
    
    /**
     * Renova o token de acesso do usuário
     * 
     * @param int $userId ID do usuário
     * @param string $token Token de acesso atual
     * @return bool Sucesso ou falha
     */
    public function renewToken($userId, $token) {
        // Tentar obter um token de longa duração
        $url = "https://graph.facebook.com/v16.0/oauth/access_token"
            . "?grant_type=fb_exchange_token"
            . "&client_id={$this->appId}"
            . "&client_secret={$this->appSecret}"
            . "&fb_exchange_token={$token}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            
            if (isset($data['access_token']) && isset($data['expires_in'])) {
                $expiryDate = date('Y-m-d H:i:s', time() + $data['expires_in']);
                
                return $this->updateUserToken($userId, $data['access_token'], $expiryDate);
            }
        }
        
        return false;
    }
}
?>