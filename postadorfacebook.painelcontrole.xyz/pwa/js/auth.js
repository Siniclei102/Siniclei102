/**
 * Service de Autenticação
 */
class AuthService {
    constructor() {
        this.isAuthenticated = false;
        this.userData = null;
        this.authListeners = [];
        
        // Verificar se já existe token armazenado
        const token = localStorage.getItem('token');
        if (token) {
            this.isAuthenticated = true;
            // Tentar obter dados do usuário
            this.fetchUserData();
        }
    }
    
    /**
     * Adiciona listener para eventos de autenticação
     * @param {function} listener Função a ser chamada quando o estado de autenticação mudar
     */
    addAuthListener(listener) {
        this.authListeners.push(listener);
    }
    
    /**
     * Notifica todos os listeners sobre mudança no estado de autenticação
     */
    notifyAuthListeners() {
        this.authListeners.forEach(listener => listener(this.isAuthenticated));
    }
    
    /**
     * Realiza login no sistema
     * @param {string} email Email do usuário
     * @param {string} senha Senha do usuário
     * @returns {Promise} Resultado do login
     */
    async login(email, senha) {
        try {
            const result = await api.login(email, senha);
            
            if (result.status === 'success') {
                this.isAuthenticated = true;
                this.userData = result.data.usuario;
                this.notifyAuthListeners();
            }
            
            return result;
        } catch (error) {
            console.error('Login error:', error);
            throw error;
        }
    }
    
    /**
     * Realiza logout do sistema
     */
    logout() {
        api.clearToken();
        this.isAuthenticated = false;
        this.userData = null;
        this.notifyAuthListeners();
    }
    
    /**
     * Registra um novo usuário
     * @param {object} userData Dados do usuário
     * @returns {Promise} Resultado do registro
     */
    async register(userData) {
        try {
            return await api.register(userData);
        } catch (error) {
            console.error('Register error:', error);
            throw error;
        }
    }
    
    /**
     * Obtém dados do usuário atual
     * @returns {Promise} Dados do usuário
     */
    async fetchUserData() {
        if (!this.isAuthenticated) {
            return null;
        }
        
        try {
            const result = await api.getPerfil();
            
            if (result.status === 'success') {
                this.userData = result.data;
                return this.userData;
            }
            
            return null;
        } catch (error) {
            console.error('Fetch user data error:', error);
            // Se ocorrer erro ao obter dados do usuário, fazer logout
            this.logout();
            throw error;
        }
    }
    
    /**
     * Verifica se o usuário está autenticado
     * @returns {boolean} Estado de autenticação
     */
    isLoggedIn() {
        return this.isAuthenticated;
    }
    
    /**
     * Obtém dados do usuário atual
     * @returns {object} Dados do usuário
     */
    getUserData() {
        return this.userData;
    }
}

// Instância global do serviço de autenticação
const auth = new AuthService();