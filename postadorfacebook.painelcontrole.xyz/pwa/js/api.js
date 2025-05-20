/**
 * API Service para interação com a API REST
 */
class ApiService {
    constructor() {
        this.baseUrl = '/api';
        this.token = localStorage.getItem('token');
    }

    /**
     * Define o token de autenticação
     * @param {string} token Token de autenticação
     */
    setToken(token) {
        this.token = token;
        localStorage.setItem('token', token);
    }

    /**
     * Remove o token de autenticação
     */
    clearToken() {
        this.token = null;
        localStorage.removeItem('token');
    }

    /**
     * Realiza uma requisição para a API
     * @param {string} endpoint Endpoint da API
     * @param {string} method Método HTTP
     * @param {object} data Dados para enviar na requisição
     * @returns {Promise} Resultado da requisição
     */
    async request(endpoint, method = 'GET', data = null) {
        const url = this.baseUrl + endpoint;
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        // Adicionar token de autenticação se disponível
        if (this.token) {
            options.headers.Authorization = `Bearer ${this.token}`;
        }

        // Adicionar corpo da requisição se houver dados
        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            const result = await response.json();

            // Verificar se a requisição foi bem sucedida
            if (response.ok) {
                return result;
            }

            // Verificar se o token expirou
            if (response.status === 401) {
                // Token inválido ou expirado, redirecionar para login
                this.clearToken();
                throw new Error('Sessão expirada. Faça login novamente.');
            }

            throw new Error(result.message || 'Erro ao processar requisição');
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    /**
     * Realiza login no sistema
     * @param {string} email Email do usuário
     * @param {string} senha Senha do usuário
     * @returns {Promise} Resultado do login
     */
    async login(email, senha) {
        const result = await this.request('/login', 'POST', { email, senha });
        
        if (result.status === 'success' && result.data.token) {
            this.setToken(result.data.token);
        }
        
        return result;
    }

    /**
     * Registra um novo usuário
     * @param {object} userData Dados do usuário
     * @returns {Promise} Resultado do registro
     */
    async register(userData) {
        return this.request('/register', 'POST', userData);
    }

    /**
     * Obtém dados do dashboard
     * @param {number} periodo Período em dias para filtrar dados
     * @returns {Promise} Dados do dashboard
     */
    async getDashboard(periodo = 30) {
        return this.request(`/dashboard?periodo=${periodo}`);
    }

    /**
     * Obtém lista de grupos
     * @param {object} params Parâmetros para filtrar grupos
     * @returns {Promise} Lista de grupos
     */
    async getGrupos(params = {}) {
        const queryParams = new URLSearchParams();
        
        if (params.page) queryParams.append('page', params.page);
        if (params.per_page) queryParams.append('per_page', params.per_page);
        if (params.nome) queryParams.append('nome', params.nome);
        if (params.ativo !== undefined) queryParams.append('ativo', params.ativo);
        
        const query = queryParams.toString() ? `?${queryParams.toString()}` : '';
        
        return this.request(`/grupos${query}`);
    }

    /**
     * Obtém detalhes de um grupo específico
     * @param {number} id ID do grupo
     * @returns {Promise} Detalhes do grupo
     */
    async getGrupo(id) {
        return this.request(`/grupos/${id}`);
    }

    /**
     * Cria um novo grupo
     * @param {object} grupoData Dados do grupo
     * @returns {Promise} Resultado da criação
     */
    async createGrupo(grupoData) {
        return this.request('/grupos', 'POST', grupoData);
    }

    /**
     * Atualiza um grupo existente
     * @param {number} id ID do grupo
     * @param {object} grupoData Dados atualizados do grupo
     * @returns {Promise} Resultado da atualização
     */
    async updateGrupo(id, grupoData) {
        return this.request(`/grupos/${id}`, 'PUT', grupoData);
    }

    /**
     * Exclui um grupo
     * @param {number} id ID do grupo
     * @returns {Promise} Resultado da exclusão
     */
    async deleteGrupo(id) {
        return this.request(`/grupos/${id}`, 'DELETE');
    }

    /**
     * Obtém lista de campanhas
     * @param {object} params Parâmetros para filtrar campanhas
     * @returns {Promise} Lista de campanhas
     */
    async getCampanhas(params = {}) {
        const queryParams = new URLSearchParams();
        
        if (params.page) queryParams.append('page', params.page);
        if (params.per_page) queryParams.append('per_page', params.per_page);
        if (params.nome) queryParams.append('nome', params.nome);
        if (params.ativa !== undefined) queryParams.append('ativa', params.ativa);
        
        const query = queryParams.toString() ? `?${queryParams.toString()}` : '';
        
        return this.request(`/campanhas${query}`);
    }

    /**
     * Obtém lista de anúncios para uma campanha
     * @param {number} campanhaId ID da campanha
     * @param {object} params Parâmetros adicionais
     * @returns {Promise} Lista de anúncios
     */
    async getAnuncios(campanhaId, params = {}) {
        const queryParams = new URLSearchParams();
        
        queryParams.append('campanha_id', campanhaId);
        
        if (params.page) queryParams.append('page', params.page);
        if (params.per_page) queryParams.append('per_page', params.per_page);
        if (params.ativo !== undefined) queryParams.append('ativo', params.ativo);
        
        const query = queryParams.toString() ? `?${queryParams.toString()}` : '';
        
        return this.request(`/anuncios${query}`);
    }

    /**
     * Realiza uma postagem imediata
     * @param {object} postData Dados da postagem
     * @returns {Promise} Resultado da postagem
     */
    async postarAgora(postData) {
        return this.request('/postar', 'POST', postData);
    }

    /**
     * Obtém lista de agendamentos
     * @param {object} params Parâmetros para filtrar agendamentos
     * @returns {Promise} Lista de agendamentos
     */
    async getAgendamentos(params = {}) {
        const queryParams = new URLSearchParams();
        
        if (params.page) queryParams.append('page', params.page);
        if (params.per_page) queryParams.append('per_page', params.per_page);
        if (params.status) queryParams.append('status', params.status);
        
        const query = queryParams.toString() ? `?${queryParams.toString()}` : '';
        
        return this.request(`/agendamentos${query}`);
    }

    /**
     * Cria um novo agendamento
     * @param {object} agendamentoData Dados do agendamento
     * @returns {Promise} Resultado da criação
     */
    async createAgendamento(agendamentoData) {
        return this.request('/agendamentos', 'POST', agendamentoData);
    }

    /**
     * Obtém informações do perfil do usuário
     * @returns {Promise} Dados do perfil
     */
    async getPerfil() {
        return this.request('/perfil');
    }

    /**
     * Obtém métricas de engajamento
     * @param {object} params Parâmetros para filtrar métricas
     * @returns {Promise} Dados de métricas
     */
    async getMetricas(params = {}) {
        const queryParams = new URLSearchParams();
        
        if (params.periodo) queryParams.append('periodo', params.periodo);
        if (params.campanha_id) queryParams.append('campanha_id', params.campanha_id);
        if (params.grupo_id) queryParams.append('grupo_id', params.grupo_id);
        
        const query = queryParams.toString() ? `?${queryParams.toString()}` : '';
        
        return this.request(`/metricas${query}`);
    }

    /**
     * Obtém lista de notificações
     * @param {object} params Parâmetros para filtrar notificações
     * @returns {Promise} Lista de notificações
     */
    async getNotificacoes(params = {}) {
        const queryParams = new URLSearchParams();
        
        if (params.page) queryParams.append('page', params.page);
        if (params.per_page) queryParams.append('per_page', params.per_page);
        if (params.lidas !== undefined) queryParams.append('lidas', params.lidas);
        
        const query = queryParams.toString() ? `?${queryParams.toString()}` : '';
        
        return this.request(`/notificacoes${query}`);
    }

    /**
     * Marca notificações como lidas
     * @param {array} ids IDs das notificações
     * @returns {Promise} Resultado da operação
     */
    async marcarNotificacoesLidas(ids) {
        return this.request('/notificacoes/marcar-lidas', 'POST', { ids });
    }
}

// Instância global do serviço da API
const api = new ApiService();