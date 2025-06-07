// Configurações da API
const API_CONFIG = {
    baseUrl: 'https://postadorfacebook.painelcontrole.xyz',
    endpoints: {
        auth: '/api/extension_auth.php',
        validate: '/api/extension_validate_token.php',
        sync: '/api/extension_sync.php'
    }
};

// Elementos DOM
let loginForm, connectedInfo;
let usernameInput, passwordInput, loginBtn, loginStatus;
let syncBtn, logoutBtn, syncStatus, userName;

// Estado da aplicação
let currentUser = null;
let authToken = null;

document.addEventListener('DOMContentLoaded', function() {
    initializeElements();
    checkAuthStatus();
    setupEventListeners();
});

function initializeElements() {
    loginForm = document.getElementById('loginForm');
    usernameInput = document.getElementById('username');
    passwordInput = document.getElementById('password');
    loginBtn = document.getElementById('loginBtn');
    loginStatus = document.getElementById('loginStatus');
    
    connectedInfo = document.getElementById('connectedInfo');
    userName = document.getElementById('userName');
    syncBtn = document.getElementById('syncBtn');
    logoutBtn = document.getElementById('logoutBtn');
    syncStatus = document.getElementById('syncStatus');
}

function setupEventListeners() {
    loginBtn.addEventListener('click', handleLogin);
    syncBtn.addEventListener('click', handleSync);
    logoutBtn.addEventListener('click', handleLogout);
    
    passwordInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            handleLogin();
        }
    });
}

async function checkAuthStatus() {
    try {
        const result = await chrome.storage.local.get(['authToken', 'currentUser']);
        
        if (result.authToken && result.currentUser) {
            // Validar token no servidor
            const response = await fetch(`${API_CONFIG.baseUrl}${API_CONFIG.endpoints.validate}`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${result.authToken}`
                }
            });
            
            const data = await response.json();
            
            if (data.valid) {
                authToken = result.authToken;
                currentUser = data.user;
                showConnectedView();
            } else {
                // Token inválido, limpar storage
                await chrome.storage.local.clear();
                showLoginView();
            }
        } else {
            showLoginView();
        }
    } catch (error) {
        console.error('Erro ao verificar status de autenticação:', error);
        showLoginView();
    }
}

async function handleLogin() {
    const username = usernameInput.value.trim();
    const password = passwordInput.value.trim();
    
    if (!username || !password) {
        showStatus('❌ Preencha usuário e senha', 'error');
        return;
    }
    
    loginBtn.textContent = 'Conectando...';
    loginBtn.disabled = true;
    
    try {
        const response = await fetch(`${API_CONFIG.baseUrl}${API_CONFIG.endpoints.auth}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: username,
                password: password
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            authToken = data.token;
            currentUser = data.user;
            
            await chrome.storage.local.set({
                authToken: authToken,
                currentUser: currentUser
            });
            
            showStatus('✅ Conectado com sucesso!', 'success');
            
            setTimeout(() => {
                showConnectedView();
            }, 1000);
            
        } else {
            showStatus(`❌ ${data.error}`, 'error');
        }
        
    } catch (error) {
        console.error('Erro de conexão:', error);
        showStatus('❌ Erro de conexão com o servidor', 'error');
    }
    
    loginBtn.textContent = 'Conectar ao Sistema';
    loginBtn.disabled = false;
}

async function handleSync() {
    if (!authToken) {
        showSyncStatus('❌ Não autenticado', 'error');
        return;
    }
    
    showSyncStatus('🔄 Sincronizando grupos...', 'info');
    syncBtn.textContent = 'Sincronizando...';
    syncBtn.disabled = true;
    
    try {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        
        if (!tab.url.includes('facebook.com')) {
            showSyncStatus('❌ Abra uma página do Facebook primeiro', 'error');
            syncBtn.textContent = '🔄 Sincronizar Grupos';
            syncBtn.disabled = false;
            return;
        }
        
        // Aqui você pode implementar a lógica real de extração de grupos do Facebook
        const mockGroups = [
            {
                facebook_id: Date.now().toString(),
                name: 'Grupo de Teste - ' + new Date().toLocaleTimeString(),
                url: 'https://facebook.com/groups/' + Date.now(),
                members: Math.floor(Math.random() * 1000),
                status: 'active'
            }
        ];
        
        const response = await fetch(`${API_CONFIG.baseUrl}${API_CONFIG.endpoints.sync}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({
                groups: mockGroups,
                user_id: currentUser.id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSyncStatus(`✅ ${mockGroups.length} grupos sincronizados!`, 'success');
        } else {
            showSyncStatus(`❌ ${data.error}`, 'error');
        }
        
    } catch (error) {
        console.error('Erro na sincronização:', error);
        showSyncStatus('❌ Erro na sincronização', 'error');
    }
    
    syncBtn.textContent = '🔄 Sincronizar Grupos';
    syncBtn.disabled = false;
}

async function handleLogout() {
    try {
        await chrome.storage.local.clear();
        authToken = null;
        currentUser = null;
        showLoginView();
        showStatus('✅ Desconectado com sucesso', 'info');
    } catch (error) {
        console.error('Erro ao fazer logout:', error);
    }
}

function showLoginView() {
    loginForm.classList.add('active');
    connectedInfo.classList.remove('active');
    
    // Limpar campos
    usernameInput.value = '';
    passwordInput.value = '';
}

function showConnectedView() {
    loginForm.classList.remove('active');
    connectedInfo.classList.add('active');
    
    if (currentUser) {
        userName.textContent = currentUser.username;
        document.querySelector('.user-avatar').textContent = currentUser.username.charAt(0).toUpperCase();
    }
}

function showStatus(message, type) {
    loginStatus.innerHTML = `<div class="status ${type}">${message}</div>`;
}

function showSyncStatus(message, type) {
    syncStatus.innerHTML = `<div class="status ${type}">${message}</div>`;
}