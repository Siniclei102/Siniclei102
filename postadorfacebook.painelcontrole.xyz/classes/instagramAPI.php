<?php
/**
 * Classe para integração com a API do Instagram
 * 
 * Esta classe gerencia operações relacionadas à API do Instagram para
 * postagem em contas profissionais e business a partir do sistema de postagem automática
 * 
 * @version 1.0
 * @author FB AutoPost System
 */
class InstagramAPI {
    private $db;
    private $appId;
    private $appSecret;
    private $defaultAccessToken;
    private $apiVersion = 'v16.0';
    
    /**
     * Construtor da classe
     * 
     * @param mysqli $db Conexão com o banco de dados
     */
    public function __construct($db) {
        $this->db = $db;
        
        // Obter configurações do Facebook (usado para Instagram também)
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
     * Verifica se o usuário possui token de acesso para Instagram
     * 
     * @param int $userId ID do usuário
     * @return bool
     */
    public function hasUserToken($userId) {
        // O Instagram usa o mesmo token do Facebook
        $query = "SELECT instagram_token FROM usuarios WHERE id = ? AND instagram_token IS NOT NULL AND instagram_token != ''";
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
        $query = "SELECT instagram_token, instagram_token_expiry FROM usuarios WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verificar se o token não expirou
            if ($user['instagram_token_expiry'] && strtotime($user['instagram_token_expiry']) > time()) {
                return $user['instagram_token'];
            }
        }
        
        return null;
    }
    
    /**
     * Atualiza o token de acesso do usuário para Instagram
     * 
     * @param int $userId ID do usuário
     * @param string $token Token de acesso
     * @param string $expiry Data de expiração (Y-m-d H:i:s)
     * @return bool
     */
    public function updateUserToken($userId, $token, $expiry) {
        $query = "UPDATE usuarios SET instagram_token = ?, instagram_token_expiry = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ssi", $token, $expiry, $userId);
        
        return $stmt->execute();
    }
    
    /**
     * Obtém lista de contas do Instagram associadas à conta do Facebook
     * 
     * @param string $token Token de acesso do Facebook
     * @return array|false Lista de contas ou false em caso de erro
     */
    public function getInstagramAccounts($token) {
        // Primeiro, obter as páginas do Facebook do usuário
        $url = "https://graph.facebook.com/{$this->apiVersion}/me/accounts?fields=id,name,access_token,instagram_business_account{id,name,username,profile_picture_url}&access_token={$token}";
        
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
                $accounts = [];
                
                foreach ($data['data'] as $page) {
                    // Verificar se a página tem uma conta de Instagram Business associada
                    if (isset($page['instagram_business_account'])) {
                        $accounts[] = [
                            'page_id' => $page['id'],
                            'page_name' => $page['name'],
                            'page_access_token' => $page['access_token'],
                            'instagram_id' => $page['instagram_business_account']['id'],
                            'instagram_name' => isset($page['instagram_business_account']['name']) ? $page['instagram_business_account']['name'] : '',
                            'instagram_username' => $page['instagram_business_account']['username'],
                            'instagram_profile_picture' => $page['instagram_business_account']['profile_picture_url']
                        ];
                    }
                }
                
                return $accounts;
            }
        }
        
        return false;
    }
    
    /**
     * Vincula uma conta do Instagram ao usuário
     * 
     * @param int $userId ID do usuário
     * @param string $instagramId ID da conta do Instagram
     * @param string $instagramUsername Username da conta do Instagram
     * @param string $pageId ID da página do Facebook associada
     * @param string $pageAccessToken Token de acesso da página do Facebook
     * @return bool
     */
    public function linkInstagramAccount($userId, $instagramId, $instagramUsername, $pageId, $pageAccessToken) {
        // Verificar se já existe uma conta vinculada
        $queryCheck = "SELECT id FROM instagram_contas WHERE instagram_id = ? AND usuario_id = ?";
        $stmtCheck = $this->db->prepare($queryCheck);
        $stmtCheck->bind_param("si", $instagramId, $userId);
        $stmtCheck->execute();
        
        if ($stmtCheck->get_result()->num_rows > 0) {
            // Atualizar conta existente
            $query = "
                UPDATE instagram_contas SET 
                    username = ?, 
                    page_id = ?, 
                    page_access_token = ?, 
                    atualizado_em = NOW() 
                WHERE instagram_id = ? AND usuario_id = ?
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ssssi", $instagramUsername, $pageId, $pageAccessToken, $instagramId, $userId);
        } else {
            // Inserir nova conta
            $query = "
                INSERT INTO instagram_contas (
                    usuario_id, 
                    instagram_id, 
                    username, 
                    page_id, 
                    page_access_token, 
                    ativo, 
                    criado_em
                ) VALUES (?, ?, ?, ?, ?, 1, NOW())
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("issss", $userId, $instagramId, $instagramUsername, $pageId, $pageAccessToken);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Obtém contas do Instagram vinculadas ao usuário
     * 
     * @param int $userId ID do usuário
     * @return array Lista de contas
     */
    public function getUserInstagramAccounts($userId) {
        $query = "
            SELECT 
                id,
                instagram_id,
                username,
                page_id,
                page_access_token,
                ativo,
                criado_em,
                atualizado_em
            FROM 
                instagram_contas
            WHERE 
                usuario_id = ?
            ORDER BY 
                username
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $accounts = [];
        while ($account = $result->fetch_assoc()) {
            $accounts[] = $account;
        }
        
        return $accounts;
    }
    
    /**
     * Publica uma imagem no Instagram
     * 
     * @param string $instagramId ID da conta do Instagram
     * @param string $pageAccessToken Token de acesso da página do Facebook
     * @param string $caption Legenda da imagem
     * @param string $imageUrl URL da imagem
     * @param array $tags Hashtags (opcional)
     * @return array|false ID da publicação ou false em caso de erro
     */
    public function postImage($instagramId, $pageAccessToken, $caption, $imageUrl, $tags = []) {
        // Adicionar hashtags à legenda, se houver
        if (!empty($tags)) {
            $hashtags = ' ' . implode(' ', array_map(function($tag) {
                return '#' . str_replace(' ', '', $tag);
            }, $tags));
            
            $caption .= $hashtags;
        }
        
        try {
            // 1. Criar contêiner de mídia
            $containerUrl = "https://graph.facebook.com/{$this->apiVersion}/{$instagramId}/media";
            
            $containerParams = [
                'image_url' => $imageUrl,
                'caption' => $caption,
                'access_token' => $pageAccessToken
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $containerUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $containerParams);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $containerResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($httpCode != 200) {
                throw new Exception("Erro ao criar contêiner: " . $containerResponse);
            }
            
            $containerData = json_decode($containerResponse, true);
            
            if (!isset($containerData['id'])) {
                throw new Exception("ID do contêiner não encontrado na resposta");
            }
            
            $containerId = $containerData['id'];
            
            // 2. Publicar mídia
            $publishUrl = "https://graph.facebook.com/{$this->apiVersion}/{$instagramId}/media_publish";
            
            $publishParams = [
                'creation_id' => $containerId,
                'access_token' => $pageAccessToken
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $publishUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $publishParams);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $publishResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($httpCode != 200) {
                throw new Exception("Erro ao publicar mídia: " . $publishResponse);
            }
            
            $publishData = json_decode($publishResponse, true);
            
            if (isset($publishData['id'])) {
                return [
                    'success' => true,
                    'post_id' => $publishData['id']
                ];
            }
            
            throw new Exception("ID da publicação não encontrado na resposta");
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Publica um carrossel no Instagram
     * 
     * @param string $instagramId ID da conta do Instagram
     * @param string $pageAccessToken Token de acesso da página do Facebook
     * @param string $caption Legenda do carrossel
     * @param array $imageUrls URLs das imagens
     * @param array $tags Hashtags (opcional)
     * @return array|false ID da publicação ou false em caso de erro
     */
    public function postCarousel($instagramId, $pageAccessToken, $caption, $imageUrls, $tags = []) {
        // Adicionar hashtags à legenda, se houver
        if (!empty($tags)) {
            $hashtags = ' ' . implode(' ', array_map(function($tag) {
                return '#' . str_replace(' ', '', $tag);
            }, $tags));
            
            $caption .= $hashtags;
        }
        
        try {
            // 1. Criar containers para cada imagem
            $mediaIds = [];
            
            foreach ($imageUrls as $imageUrl) {
                $containerUrl = "https://graph.facebook.com/{$this->apiVersion}/{$instagramId}/media";
                
                $containerParams = [
                    'image_url' => $imageUrl,
                    'is_carousel_item' => 'true',
                    'access_token' => $pageAccessToken
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $containerUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $containerParams);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $containerResponse = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                curl_close($ch);
                
                if ($httpCode != 200) {
                    throw new Exception("Erro ao criar contêiner: " . $containerResponse);
                }
                
                $containerData = json_decode($containerResponse, true);
                
                if (!isset($containerData['id'])) {
                    throw new Exception("ID do contêiner não encontrado na resposta");
                }
                
                $mediaIds[] = $containerData['id'];
            }
            
            // 2. Criar contêiner do carrossel
            $carouselUrl = "https://graph.facebook.com/{$this->apiVersion}/{$instagramId}/media";
            
            $carouselParams = [
                'media_type' => 'CAROUSEL',
                'caption' => $caption,
                'children' => implode(',', $mediaIds),
                'access_token' => $pageAccessToken
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $carouselUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $carouselParams);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $carouselResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($httpCode != 200) {
                throw new Exception("Erro ao criar carrossel: " . $carouselResponse);
            }
            
            $carouselData = json_decode($carouselResponse, true);
            
            if (!isset($carouselData['id'])) {
                throw new Exception("ID do carrossel não encontrado na resposta");
            }
            
            $carouselId = $carouselData['id'];
            
            // 3. Publicar carrossel
            $publishUrl = "https://graph.facebook.com/{$this->apiVersion}/{$instagramId}/media_publish";
            
            $publishParams = [
                'creation_id' => $carouselId,
                'access_token' => $pageAccessToken
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $publishUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $publishParams);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $publishResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($httpCode != 200) {
                throw new Exception("Erro ao publicar carrossel: " . $publishResponse);
            }
            
            $publishData = json_decode($publishResponse, true);
            
            if (isset($publishData['id'])) {
                return [
                    'success' => true,
                    'post_id' => $publishData['id']
                ];
            }
            
            throw new Exception("ID da publicação não encontrado na resposta");
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtém métricas de uma publicação no Instagram
     * 
     * @param string $mediaId ID da publicação
     * @param string $pageAccessToken Token de acesso da página
     * @return array|false Métricas da publicação ou false em caso de erro
     */
    public function getMediaInsights($mediaId, $pageAccessToken) {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$mediaId}/insights?metric=engagement,impressions,reach,saved&access_token={$pageAccessToken}";
        
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
                $metrics = [];
                
                foreach ($data['data'] as $metric) {
                    $metrics[$metric['name']] = isset($metric['values'][0]['value']) ? $metric['values'][0]['value'] : 0;
                }
                
                return $metrics;
            }
        }
        
        return false;
    }
    
    /**
     * Obtém comentários de uma publicação no Instagram
     * 
     * @param string $mediaId ID da publicação
     * @param string $pageAccessToken Token de acesso da página
     * @return array|false Comentários da publicação ou false em caso de erro
     */
    public function getComments($mediaId, $pageAccessToken) {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$mediaId}/comments?fields=id,text,timestamp,username&access_token={$pageAccessToken}";
        
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
     * Responde a um comentário no Instagram
     * 
     * @param string $commentId ID do comentário
     * @param string $message Mensagem de resposta
     * @param string $pageAccessToken Token de acesso da página
     * @return array|false Resultado da operação ou false em caso de erro
     */
    public function replyToComment($commentId, $message, $pageAccessToken) {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$commentId}/replies";
        
        $params = [
            'message' => $message,
            'access_token' => $pageAccessToken
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
            
            if (isset($data['id'])) {
                return [
                    'success' => true,
                    'comment_id' => $data['id']
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => $response
        ];
    }
    
    /**
     * Salva um registro de postagem do Instagram no banco de dados
     * 
     * @param array $data Dados da postagem
     * @return int|false ID da postagem inserida ou false em caso de erro
     */
    public function savePost($data) {
        $query = "
            INSERT INTO instagram_posts (
                usuario_id,
                campanha_id,
                anuncio_id,
                instagram_conta_id,
                post_id,
                tipo,
                legenda,
                imagem_url,
                data_postagem,
                curtidas,
                comentarios,
                alcance,
                impressoes,
                salvamentos
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, 0, 0, 0, 0)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "iiisssssi",
            $data['usuario_id'],
            $data['campanha_id'],
            $data['anuncio_id'],
            $data['instagram_conta_id'],
            $data['post_id'],
            $data['tipo'],
            $data['legenda'],
            $data['imagem_url']
        );
        
        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        
        return false;
    }
    
    /**
     * Atualiza métricas de postagens do Instagram de um usuário
     * 
     * @param int $userId ID do usuário
     * @param int $dias Número de dias para trás para atualizar
     * @return array Estatísticas da atualização
     */
    public function updateUserPostMetrics($userId, $dias = 30) {
        $dataLimite = date('Y-m-d', strtotime("-{$dias} days"));
        
        // Obter contas do Instagram do usuário
        $contas = $this->getUserInstagramAccounts($userId);
        
        if (empty($contas)) {
            return [
                'success' => false,
                'message' => 'Usuário não possui contas do Instagram vinculadas'
            ];
        }
        
        $estatisticas = [
            'total' => 0,
            'atualizados' => 0,
            'erros' => 0
        ];
        
        foreach ($contas as $conta) {
            if (!$conta['ativo']) {
                continue;
            }
            
            // Obter postagens recentes
            $query = "
                SELECT 
                    id,
                    post_id
                FROM 
                    instagram_posts
                WHERE 
                    usuario_id = ?
                    AND instagram_conta_id = ?
                    AND data_postagem >= ?
                    AND post_id IS NOT NULL
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("iis", $userId, $conta['id'], $dataLimite);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $contaPosts = $result->num_rows;
            $estatisticas['total'] += $contaPosts;
            
            while ($post = $result->fetch_assoc()) {
                // Obter métricas via API
                $metrics = $this->getMediaInsights($post['post_id'], $conta['page_access_token']);
                
                if ($metrics) {
                    // Obter comentários
                    $comments = $this->getComments($post['post_id'], $conta['page_access_token']);
                    $commentsCount = is_array($comments) ? count($comments) : 0;
                    
                    // Atualizar no banco de dados
                    $queryUpdate = "
                        UPDATE instagram_posts 
                        SET 
                            curtidas = ?,
                            comentarios = ?,
                            alcance = ?,
                            impressoes = ?,
                            salvamentos = ?,
                            ultima_atualizacao = NOW()
                        WHERE id = ?
                    ";
                    
                    $engagement = isset($metrics['engagement']) ? $metrics['engagement'] : 0;
                    $reach = isset($metrics['reach']) ? $metrics['reach'] : 0;
                    $impressions = isset($metrics['impressions']) ? $metrics['impressions'] : 0;
                    $saved = isset($metrics['saved']) ? $metrics['saved'] : 0;
                    
                    $stmtUpdate = $this->db->prepare($queryUpdate);
                    $stmtUpdate->bind_param(
                        "iiiiii",
                        $engagement,
                        $commentsCount,
                        $reach,
                        $impressions,
                        $saved,
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
        }
        
        return [
            'success' => true,
            'stats' => $estatisticas
        ];
    }
}
?>