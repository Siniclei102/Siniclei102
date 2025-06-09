<?php
/**
 * Sistema de Autenticação - CORRIGIDO
 */

require_once 'Database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        // Iniciar sessão apenas se não estiver ativa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function login($username, $password, $remember = false) {
        // Verificar tentativas de login
        $user = $this->getUserByUsername($username);
        if (!$user) {
            $this->logSystemAction(null, 'login_failed', "Login failed for username: {$username}");
            return ['success' => false, 'message' => 'Usuário ou senha inválidos'];
        }
        
        // Verificar se conta está bloqueada
        if (!empty($user['locked_until']) && new DateTime() < new DateTime($user['locked_until'])) {
            return ['success' => false, 'message' => 'Conta temporariamente bloqueada. Tente novamente mais tarde.'];
        }
        
        // Verificar se conta está suspensa
        if ($user['account_status'] !== 'active') {
            return ['success' => false, 'message' => 'Conta suspensa. Entre em contato com o administrador.'];
        }
        
        // Verificar assinatura expirada (só para não-admins)
        if ($user['account_type'] !== 'admin' && 
            !empty($user['subscription_expires_at']) && 
            $user['subscription_expires_at'] < date('Y-m-d')) {
            $this->suspendUser($user['id'], 'Assinatura expirada');
            return ['success' => false, 'message' => 'Sua assinatura expirou. Entre em contato com o administrador.'];
        }
        
        // Verificar senha
        if (!password_verify($password, $user['password'])) {
            $this->incrementLoginAttempts($user['id']);
            $this->logSystemAction($user['id'], 'login_failed', "Password incorrect for user: {$username}");
            return ['success' => false, 'message' => 'Usuário ou senha inválidos'];
        }
        
        // Login bem-sucedido
        $this->resetLoginAttempts($user['id']);
        $this->updateLastLogin($user['id']);
        $this->createSession($user, $remember);
        $this->logSystemAction($user['id'], 'login_success', "User logged in successfully");
        
        return ['success' => true, 'user' => $this->sanitizeUserData($user)];
    }
    
    public function logout() {
        // Iniciar sessão se não estiver ativa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            $this->logSystemAction($_SESSION['user_id'], 'logout', "User logged out");
        }
        
        // Limpar todas as variáveis de sessão
        $_SESSION = array();
        
        // Destruir cookie de sessão
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Remover cookie "lembrar-me"
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        if (isset($_COOKIE['remember_user'])) {
            setcookie('remember_user', '', time() - 3600, '/');
        }
        
        // Destruir sessão
        session_destroy();
    }
    
    public function isLoggedIn() {
        // Verificar se a sessão está ativa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar se user_id existe e não está vazio
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            return false;
        }
        
        // Verificar se o usuário ainda existe no banco
        $user = $this->getUserById($_SESSION['user_id']);
        if (!$user) {
            $this->logout();
            return false;
        }
        
        // Verificar se a conta ainda está ativa
        if ($user['account_status'] !== 'active') {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $user = $this->getUserById($_SESSION['user_id']);
        if (!$user || $user['account_status'] !== 'active') {
            $this->logout();
            return null;
        }
        
        return $this->sanitizeUserData($user);
    }
    
    public function isAdmin() {
        $user = $this->getCurrentUser();
        return $user && $user['account_type'] === 'admin';
    }
    
    public function createUser($data, $createdBy = null) {
        // Validar dados
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'Todos os campos são obrigatórios'];
        }
        
        // Verificar se usuário já existe
        if ($this->getUserByUsername($data['username'])) {
            return ['success' => false, 'message' => 'Nome de usuário já existe'];
        }
        
        if ($this->getUserByEmail($data['email'])) {
            return ['success' => false, 'message' => 'Email já está em uso'];
        }
        
        // Preparar dados
        $userData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'name' => $data['name'] ?? $data['username'],
            'account_type' => $data['account_type'] ?? 'user',
            'subscription_expires_at' => $data['subscription_expires_at'] ?? date('Y-m-d', strtotime('+30 days')),
            'account_status' => 'active',
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $userId = $this->db->insert('users', $userData);
            $this->logSystemAction($createdBy, 'user_created', "Created user: {$data['username']} (ID: {$userId})");
            
            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            error_log("Error creating user: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao criar usuário'];
        }
    }
    
    private function getUserByUsername($username) {
        try {
            return $this->db->selectOne("SELECT * FROM users WHERE username = ?", [$username]);
        } catch (Exception $e) {
            error_log("Error getting user by username: " . $e->getMessage());
            return null;
        }
    }
    
    private function getUserByEmail($email) {
        try {
            return $this->db->selectOne("SELECT * FROM users WHERE email = ?", [$email]);
        } catch (Exception $e) {
            error_log("Error getting user by email: " . $e->getMessage());
            return null;
        }
    }
    
    private function getUserById($id) {
        try {
            return $this->db->selectOne("SELECT * FROM users WHERE id = ?", [$id]);
        } catch (Exception $e) {
            error_log("Error getting user by ID: " . $e->getMessage());
            return null;
        }
    }
    
    private function incrementLoginAttempts($userId) {
        try {
            $this->db->query("UPDATE users SET login_attempts = COALESCE(login_attempts, 0) + 1 WHERE id = ?", [$userId]);
            
            $user = $this->getUserById($userId);
            
            // Verificar se as constantes estão definidas
            $maxAttempts = defined('LOGIN_MAX_ATTEMPTS') ? LOGIN_MAX_ATTEMPTS : 5;
            $lockoutTime = defined('LOGIN_LOCKOUT_TIME') ? LOGIN_LOCKOUT_TIME : 900; // 15 minutos
            
            if ($user && $user['login_attempts'] >= $maxAttempts) {
                $lockUntil = date('Y-m-d H:i:s', time() + $lockoutTime);
                $this->db->query("UPDATE users SET locked_until = ? WHERE id = ?", [$lockUntil, $userId]);
            }
        } catch (Exception $e) {
            error_log("Error incrementing login attempts: " . $e->getMessage());
        }
    }
    
    private function resetLoginAttempts($userId) {
        try {
            $this->db->query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?", [$userId]);
        } catch (Exception $e) {
            error_log("Error resetting login attempts: " . $e->getMessage());
        }
    }
    
    private function updateLastLogin($userId) {
        try {
            $this->db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$userId]);
        } catch (Exception $e) {
            error_log("Error updating last login: " . $e->getMessage());
        }
    }
    
    private function createSession($user, $remember = false) {
        // NÃO chamar session_start() aqui - já foi chamado no construtor
        
        // Regenerar ID da sessão por segurança
        session_regenerate_id(true);
        
        // Definir variáveis de sessão
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'] ?? $user['username'];
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['account_type'] = $user['account_type'];
        $_SESSION['logged_in_at'] = time();
        $_SESSION['last_activity'] = time();
        
        // Cookie "lembrar-me"
        if ($remember) {
            try {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 dias, httponly
                
                // Salvar token no banco (opcional)
                $this->db->query("UPDATE users SET api_token = ?, token_expires_at = ? WHERE id = ?", 
                    [$token, date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)), $user['id']]);
            } catch (Exception $e) {
                error_log("Error creating remember token: " . $e->getMessage());
            }
        }
    }
    
    private function suspendUser($userId, $reason = '') {
        try {
            $this->db->query("UPDATE users SET account_status = 'suspended' WHERE id = ?", [$userId]);
            $this->logSystemAction(null, 'user_suspended', "User ID {$userId} suspended: {$reason}");
        } catch (Exception $e) {
            error_log("Error suspending user: " . $e->getMessage());
        }
    }
    
    private function sanitizeUserData($user) {
        // Remover dados sensíveis
        unset($user['password']);
        unset($user['api_token']);
        return $user;
    }
    
    private function logSystemAction($userId, $action, $details = null) {
        try {
            // Verificar se a tabela existe
            if (!$this->db->tableExists('system_logs')) {
                return; // Se não existe, não loga
            }
            
            $logData = [
                'user_id' => $userId,
                'action' => $action,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('system_logs', $logData);
        } catch (Exception $e) {
            error_log("Error logging system action: " . $e->getMessage());
        }
    }
}
?>