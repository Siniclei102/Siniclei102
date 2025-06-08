// ===================================
// PostGroup - Popup Script
// Interface de usuário da extensão
// ===================================

document.addEventListener('DOMContentLoaded', function() {
  // Elementos da interface
  const statusCard = document.getElementById('facebook-status');
  const statusText = document.getElementById('status-text');
  const connectBtn = document.getElementById('connect-btn');
  const checkGroupsBtn = document.getElementById('check-groups-btn');
  const groupsCountEl = document.getElementById('groups-count');
  const lastSyncEl = document.getElementById('last-sync');
  const syncBtn = document.getElementById('sync-btn');
  
  // Verificar status atual
  function checkStatus() {
    chrome.runtime.sendMessage({action: 'getStatus'}, function(response) {
      if (response) {
        updateUI(response);
      }
    });
  }
  
  // Atualizar interface com status
  function updateUI(data) {
    // Status do Facebook
    if (data.connected) {
      statusCard.className = 'status-card connected';
      statusText.textContent = 'Conectado';
      
      // Habilitar botões
      checkGroupsBtn.disabled = false;
      syncBtn.disabled = false;
      
      connectBtn.textContent = 'Abrir Facebook';
    } else {
      statusCard.className = 'status-card disconnected';
      statusText.textContent = 'Desconectado';
      
      // Desabilitar botões
      checkGroupsBtn.disabled = true;
      syncBtn.disabled = true;
      
      connectBtn.textContent = 'Conectar ao Facebook';
    }
    
    // Contagem de grupos
    groupsCountEl.textContent = data.groups ? data.groups.length : 0;
    
    // Última sincronização
    if (data.lastSync) {
      const date = new Date(data.lastSync);
      lastSyncEl.textContent = formatDate(date);
    } else {
      lastSyncEl.textContent = 'Nunca';
    }
  }
  
  // Formatar data
  function formatDate(date) {
    return date.toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
  
  // Mostrar mensagem
  function showMessage(message, isError = false) {
    const messageEl = document.createElement('div');
    messageEl.className = `message ${isError ? 'error' : 'success'}`;
    messageEl.textContent = message;
    
    document.body.appendChild(messageEl);
    
    setTimeout(() => {
      messageEl.classList.add('show');
    }, 10);
    
    setTimeout(() => {
      messageEl.classList.remove('show');
      setTimeout(() => {
        document.body.removeChild(messageEl);
      }, 300);
    }, 3000);
  }
  
  // Botão de conectar ao Facebook
  connectBtn.addEventListener('click', function() {
    chrome.tabs.create({url: 'https://www.facebook.com'});
  });
  
  // Botão de verificar grupos
  checkGroupsBtn.addEventListener('click', function() {
    checkGroupsBtn.disabled = true;
    checkGroupsBtn.textContent = 'Buscando grupos...';
    
    chrome.runtime.sendMessage({action: 'checkGroups'}, function(response) {
      checkGroupsBtn.textContent = 'Verificar Grupos';
      checkGroupsBtn.disabled = false;
      
      if (response && response.success) {
        showMessage(`${response.groups.length} grupos encontrados!`);
        groupsCountEl.textContent = response.groups.length;
      } else {
        showMessage('Erro ao buscar grupos', true);
      }
    });
  });
  
  // Botão de sincronizar
  syncBtn.addEventListener('click', function() {
    syncBtn.disabled = true;
    syncBtn.textContent = 'Sincronizando...';
    
    chrome.runtime.sendMessage({action: 'syncWithSite'}, function(response) {
      syncBtn.textContent = 'Sincronizar com o Site';
      syncBtn.disabled = false;
      
      if (response && response.success) {
        showMessage('Sincronização concluída!');
        lastSyncEl.textContent = formatDate(new Date());
      } else {
        showMessage('Erro na sincronização', true);
      }
    });
  });
  
  // Verificar status inicialmente
  checkStatus();
  
  // Verificar periodicamente
  setInterval(checkStatus, 5000);
});