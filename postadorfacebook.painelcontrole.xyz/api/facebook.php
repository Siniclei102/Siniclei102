<?php
/**
 * Classe de integração com a API do Facebook
 * Gerencia autenticação, acesso a grupos e postagens
 */
class FacebookAPI {
    private $db;
    private $accessToken;
    private $fb;
    
    /**
     * Construtor da classe
     * @param mysqli $db Conexão com o banco de dados
     * @param string $accessToken Token de acesso do usuário (opcional)
     */
    public function __construct($db, $accessToken = null) {
        $this->db = $db;
        $this->accessToken = $accessToken;
        
        // Carregar configurações do Facebook
        $fbConfigPath = __DIR__ . '/../config/facebook-app.php';
        if (file_exists($fbConfigPath)) {
            require_once $fbConfigPath;
        } else {
            throw new Exception("Arquivo de configuração do Facebook não encontrado.");
        }
        
        // Inicializar SDK do Facebook
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $this->fb = new \Facebook\Facebook([
            'app_id' => FB_APP_ID,
            'app_secret' => FB_APP_SECRET,
            'default_graph_version' => FB_APP_VERSION,
        ]);
    }
    
    /**
     * Gerar URL de login
     * @return string URL de login
     */
    public function getLoginUrl() {
        $helper = $this->fb->getRedirectLoginHelper();
        $permissions = FB_PERMISSIONS;
        
        return $helper->getLoginUrl(FB_REDIRECT_URI, $permissions);
    }
    
    /**
     * Processar callback após login
     * @return array Informações do usuário e token
     */
    public function processCallback() {
        $helper = $this->fb->getRedirectLoginHelper();
        
        try {
            $accessToken = $helper->getAccessToken();
            
            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado pelo usuário ou erro de autorização.'
                ];
            }
            
            // Verificar se o token é válido
            $oAuth2Client = $this->fb->getOAuth2Client();
            $tokenMetadata = $oAuth2Client->debugToken($accessToken);
            $tokenMetadata->validateAppId(FB_APP_ID);
            $tokenMetadata->validateExpiration();
            
            // Trocar por um token de longa duração
            if (!$accessToken->isLongLived()) {
                $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
            }
            
            // Obter informações do usuário
            $response = $this->fb->get('/me?fields=id,name,email', $accessToken->getValue());
            $user = $response->getGraphUser();
            
            return [
                'success' => true,
                'token' => $accessToken->getValue(),
                'expires' => $accessToken->getExpiresAt()->format('Y-m-d H:i:s'),
                'user_id' => $user->getId(),
                'user_name' => $user->getName(),
                'user_email' => $user->getEmail()
            ];
        } catch (\Facebook\Exceptions\FacebookResponseException $e) {
            return [
                'success' => false,
                'message' => 'Erro de resposta: ' . $e->getMessage()
            ];
        } catch (\Facebook\Exceptions\FacebookSDKException $e) {
            return [
                'success' => false,
                'message' => 'Erro do SDK: ' . $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter grupos do usuário
     * @return array Lista de grupos
     */
    public function getUserGroups() {
        if (!$this->accessToken) {
            return [
                'success' => false,
                'message' => 'Token de acesso não fornecido'
            ];
        }
        
        try {
            $response = $this->fb->get('/me/groups?fields=id,name,administrator,privacy,description', $this->accessToken);
            $graphEdge = $response->getGraphEdge();
            
            $groups = [];
            foreach ($graphEdge as $group) {
                $groups[] = [
                    'id' => $group['id'],
                    'name' => $group['name'],
                    'administrator' => isset($group['administrator']) ? $group['administrator'] : false,
                    'privacy' => isset($group['privacy']) ? $group['privacy'] : 'UNKNOWN',
                    'url' => 'https://facebook.com/groups/' . $group['id']
                ];
            }
            
            return [
                'success' => true,
                'groups' => $groups
            ];
        } catch (\Facebook\Exceptions\FacebookResponseException $e) {
            return [
                'success' => false,
                'message' => 'Erro de resposta: ' . $e->getMessage()
            ];
        } catch (\Facebook\Exceptions\FacebookSDKException $e) {
            return [
                'success' => false,
                'message' => 'Erro do SDK: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Postar em um grupo
     * @param string $groupId ID do grupo
     * @param array $postData Dados da postagem (texto, link, imagem)
     * @return array Resultado da postagem
     */
    public function postToGroup($groupId, $postData) {
        if (!$this->accessToken) {
            return [
                'success' => false,
                'message' => 'Token de acesso não fornecido'
            ];
        }
        
        try {
            $endpoint = '/' . $groupId . '/feed';
            $data = [];
            
            // Adicionar texto
            if (!empty($postData['texto'])) {
                $data['message'] = $postData['texto'];
            } else {
                return [
                    'success' => false,
                    'message' => 'O texto da postagem é obrigatório'
                ];
            }
            
            // Adicionar link
            if (!empty($postData['link'])) {
                $data['link'] = $postData['link'];
            }
            
            // Se houver imagem, fazer upload primeiro
            if (!empty($postData['imagem']) && file_exists($postData['imagem'])) {
                // Postar com foto anexada
                try {
                    $photo = $this->fb->fileToUpload($postData['imagem']);
                    $data['source'] = $photo;
                    $response = $this->fb->post('/' . $groupId . '/photos', $data, $this->accessToken);
                } catch (Exception $e) {
                    // Se falhar o upload da foto, tentar postar apenas o texto
                    $response = $this->fb->post($endpoint, $data, $this->accessToken);
                }
            } else {
                // Postar sem foto
                $response = $this->fb->post($endpoint, $data, $this->accessToken);
            }
            
            $graphNode = $response->getGraphNode();
            
            return [
                'success' => true,
                'post_id' => isset($graphNode['id']) ? $graphNode['id'] : null,
                'message' => 'Postagem realizada com sucesso'
            ];
        } catch (\Facebook\Exceptions\FacebookResponseException $e) {
            return [
                'success' => false,
                'message' => 'Erro de resposta: ' . $e->getMessage(),
                'code' => $e->getCode()
            ];
        } catch (\Facebook\Exceptions\FacebookSDKException $e) {
            return [
                'success' => false,
                'message' => 'Erro do SDK: ' . $e->getMessage(),
                'code' => $e->getCode()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Verificar se o token de acesso é válido
     * @return array Resultado da verificação
     */
    public function validateToken() {
        if (!$this->accessToken) {
            return [
                'success' => false,
                'message' => 'Token de acesso não fornecido'
            ];
        }
        
        try {
            $oAuth2Client = $this->fb->getOAuth2Client();
            $tokenMetadata = $oAuth2Client->debugToken($this->accessToken);
            
            // Verificar validade
            $isValid = !$tokenMetadata->getIsExpired();
            $expiresAt = $tokenMetadata->getExpiresAt();
            
            return [
                'success' => $isValid,
                'message' => $isValid ? 'Token válido' : 'Token expirado',
                'expires_at' => $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null,
                'data' => $tokenMetadata
            ];
        } catch (\Facebook\Exceptions\FacebookResponseException $e) {
            return [
                'success' => false,
                'message' => 'Erro de resposta: ' . $e->getMessage()
            ];
        } catch (\Facebook\Exceptions\FacebookSDKException $e) {
            return [
                'success' => false,
                'message' => 'Erro do SDK: ' . $e->getMessage()
            ];
        }
    }
}
?>