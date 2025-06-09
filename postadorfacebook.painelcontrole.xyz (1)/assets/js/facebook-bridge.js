// ===============================================
// BRIDGE PARA COMUNICAÇÃO COM A EXTENSÃO
// ===============================================

(function() {
    console.log("🔄 Facebook Bridge iniciado");
    
    // ID da extensão (você precisa pegar o ID real após instalar)
    const EXTENSION_ID = "seu-extension-id-aqui";
    
    let bridgeState = {
        extensionFound: false,
        facebookConnected: false,
        groups: [],
        lastUpdate: null
    };
    
    // Elementos da página
    const statusElements = {
        extensionStatus: document.querySelector('#extension-status'),
        facebookStatus: document.querySelector('#facebook-status'),
        groupsCount: document.querySelector('#groups-count'),
        groupsList: document.querySelector('#groups-list'),
        syncButton: document.querySelector('#sync-groups-btn'),
        refreshButton: document.querySelector('#refresh-groups-btn')
    };
    
    // Verificar se a extensão está instalada
    function checkExtension() {
        if (!chrome || !chrome.runtime) {
            updateStatus('Extension API não disponível');
            return;
        }
        
        console.log("🔍 Verificando extensão...");
        
        try {
            chrome.runtime.sendMessage(EXTENSION_ID, {
                action: 'checkStatus',
                from: 'groups-page'
            }, function(response) {
                if (chrome.runtime.lastError) {
                    console.log("❌ Extensão não encontrada:", chrome.runtime.lastError.message);
                    updateStatus('Extensão não instalada ou desativada');
                    return;
                }
                
                if (response && response.status === 'ok') {
                    console.log("✅ Extensão encontrada!");
                    bridgeState.extensionFound = true;
                    bridgeState.facebookConnected = response.facebookConnected;
                    bridgeState.groups = response.groups || [];
                    
                    updateUI();
                    
                    // Se há grupos, exibir
                    if (bridgeState.groups.length > 0) {
                        displayGroups(bridgeState.groups);
                    }
                } else {
                    updateStatus('Extensão respondeu com erro');
                }
            });
        } catch (error) {
            console.error("❌ Erro ao verificar extensão:", error);
            updateStatus('Erro ao comunicar com a extensão');
        }
    }
    
    // Solicitar grupos da extensão
    function requestGroups() {
        if (!bridgeState.extensionFound) {
            alert('Extensão não encontrada. Instale e ative a extensão PostGroup.');
            return;
        }
        
        console.log("📥 Solicitando grupos da extensão...");
        
        chrome.runtime.sendMessage(EXTENSION_ID, {
            action: 'getGroups',
            from: 'groups-page'
        }, function(response) {
            if (chrome.runtime.lastError) {
                console.error("❌ Erro:", chrome.runtime.lastError.message);
                alert('Erro ao solicitar grupos da extensão');
                return;
            }
            
            if (response && response.success) {
                console.log(`✅ ${response.groups.length} grupos recebidos`);
                bridgeState.groups = response.groups;
                displayGroups(response.groups);
                updateGroupsCount(response.groups.length);
            } else {
                console.error("❌ Falha ao obter grupos:", response);
                alert('Falha ao obter grupos: ' + (response.error || 'Erro desconhecido'));
            }
        });
    }
    
    // Atualizar interface
    function updateUI() {
        // Status da extensão
        if (statusElements.extensionStatus) {
            statusElements.extensionStatus.textContent = bridgeState.extensionFound ? 
                '✅ Extensão Conectada' : '❌ Extensão Não Encontrada';
            statusElements.extensionStatus.className = bridgeState.extensionFound ? 
                'status-ok' : 'status-error';
        }
        
        // Status do Facebook
        if (statusElements.facebookStatus) {
            statusElements.facebookStatus.textContent = bridgeState.facebookConnected ? 
                '✅ Facebook Conectado' : '❌ Facebook Desconectado';
            statusElements.facebookStatus.className = bridgeState.facebookConnected ? 
                'status-ok' : 'status-error';
        }
        
        // Habilitar/desabilitar botões
        if (statusElements.syncButton) {
            statusElements.syncButton.disabled = !bridgeState.extensionFound || !bridgeState.facebookConnected;
        }
        
        if (statusElements.refreshButton) {
            statusElements.refreshButton.disabled = !bridgeState.extensionFound;
        }
    }
    
    // Exibir grupos na página
    function displayGroups(groups) {
        if (!statusElements.groupsList) {
            console.log("📋 Lista de grupos:");
            groups.forEach((group, index) => {
                console.log(`${index + 1}. ${group.name} (ID: ${group.id})`);
            });
            return;
        }
        
        if (groups.length === 0) {
            statusElements.groupsList.innerHTML = '<p>Nenhum grupo detectado.</p>';
            return;
        }
        
        const html = groups.map(group => `
            <div class="group-card">
                <div class="group-info">
                    <h4>${group.name}</h4>
                    <p>ID: ${group.id}</p>
                    ${group.memberCount ? `<p>Membros: ${group.memberCount}</p>` : ''}
                    ${group.privacy ? `<p>Privacidade: ${group.privacy}</p>` : ''}
                </div>
                <div class="group-actions">
                    <a href="${group.url}" target="_blank" class="btn-view">Ver Grupo</a>
                </div>
            </div>
        `).join('');
        
        statusElements.groupsList.innerHTML = html;
        updateGroupsCount(groups.length);
    }
    
    // Atualizar contador
    function updateGroupsCount(count) {
        if (statusElements.groupsCount) {
            statusElements.groupsCount.textContent = count;
        }
    }
    
    // Atualizar status geral
    function updateStatus(message) {
        console.log("📝 Status:", message);
        
        // Criar elemento de status se não existir
        let statusEl = document.querySelector('#bridge-status');
        if (!statusEl) {
            statusEl = document.createElement('div');
            statusEl.id = 'bridge-status';
            statusEl.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #333;
                color: white;
                padding: 10px;
                border-radius: 5px;
                z-index: 9999;
            `;
            document.body.appendChild(statusEl);
        }
        
        statusEl.textContent = message;
        
        // Remover após 5 segundos
        setTimeout(() => {
            if (statusEl.parentNode) {
                statusEl.parentNode.removeChild(statusEl);
            }
        }, 5000);
    }
    
    // Event listeners
    if (statusElements.syncButton) {
        statusElements.syncButton.addEventListener('click', requestGroups);
    }
    
    if (statusElements.refreshButton) {
        statusElements.refreshButton.addEventListener('click', checkExtension);
    }
    
    // Expor funções globalmente para debug
    window.facebookBridge = {
        checkExtension,
        requestGroups,
        getState: () => bridgeState,
        displayGroups
    };
    
    // Inicialização
    document.addEventListener('DOMContentLoaded', function() {
        console.log("📄 DOM carregado, iniciando verificação...");
        setTimeout(checkExtension, 1000);
    });
    
    // Verificar periodicamente
    setInterval(checkExtension, 30000); // A cada 30 segundos
    
    console.log("✅ Facebook Bridge pronto!");
})();