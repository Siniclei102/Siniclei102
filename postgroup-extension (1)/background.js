// ============================================
// PostGroup - Background Script
// Gerencia o estado da extensão e a comunicação
// ============================================

// Estado global da extensão
let state = {
  version: '1.0.0',
  connected: false,
  groups: [],
  user: null,
  siteUrl: 'https://postadorfacebook.painelcontrole.xyz',
  lastSync: null
};

// Carregar estado salvo
chrome.storage.local.get(['postGroupState'], function(result) {
  if (result.postGroupState) {
    state = {...state, ...result.postGroupState};
    console.log("Estado carregado:", state);
  }
});

// Salvar estado
function saveState() {
  chrome.storage.local.set({postGroupState: state});
}

// Verificar login no Facebook
function checkFacebookLogin() {
  return new Promise((resolve) => {
    chrome.cookies.get({url: 'https://www.facebook.com', name: 'c_user'}, function(cookie) {
      if (cookie) {
        state.connected = true;
        state.user = {
          id: cookie.value
        };
        saveState();
        console.log("Conectado ao Facebook como ID:", cookie.value);
        resolve(true);
      } else {
        state.connected = false;
        saveState();
        console.log("Não conectado ao Facebook");
        resolve(false);
      }
    });
  });
}

// Obter dados do usuário do Facebook
function fetchUserData() {
  chrome.tabs.query({active: true, currentWindow: true}, function(tabs) {
    if (tabs.length > 0 && tabs[0].url.includes('facebook.com')) {
      chrome.tabs.sendMessage(tabs[0].id, {action: 'getUserData'});
    }
  });
}

// Buscar grupos do Facebook
function fetchGroups() {
  return new Promise((resolve, reject) => {
    // Criar nova aba com página de grupos
    chrome.tabs.create({url: 'https://www.facebook.com/groups/feed/'}, function(tab) {
      // Esperar pela página carregar
      chrome.tabs.onUpdated.addListener(function listener(tabId, info) {
        if (tabId === tab.id && info.status === 'complete') {
          chrome.tabs.onUpdated.removeListener(listener);
          
          console.log("Página de grupos carregada, buscando dados...");
          
          // Dar tempo para a página carregar completamente
          setTimeout(() => {
            // Enviar mensagem para o content script extrair grupos
            chrome.tabs.sendMessage(tab.id, {action: 'getGroups'}, function(response) {
              if (response && response.success && response.groups) {
                state.groups = response.groups;
                saveState();
                
                // Fechar a aba após extrair os dados
                setTimeout(() => {
                  chrome.tabs.remove(tab.id);
                }, 2000);
                
                console.log("Grupos obtidos:", response.groups.length);
                resolve(response.groups);
              } else {
                reject(new Error("Falha ao extrair grupos: " + (response ? response.error : 'Erro desconhecido')));
              }
            });
          }, 5000);
        }
      });
    });
  });
}

// Função para postar em um grupo
function postToGroup(groupId, content) {
  return new Promise((resolve, reject) => {
    // Abrir o grupo em uma nova aba
    chrome.tabs.create({url: `https://www.facebook.com/groups/${groupId}`}, function(tab) {
      // Aguardar carregamento da página
      chrome.tabs.onUpdated.addListener(function listener(tabId, info) {
        if (tabId === tab.id && info.status === 'complete') {
          chrome.tabs.onUpdated.removeListener(listener);
          
          // Aguardar carregamento completo
          setTimeout(() => {
            // Enviar mensagem para o content script fazer a postagem
            chrome.tabs.sendMessage(tab.id, {
              action: 'postToGroup',
              content: content
            }, function(response) {
              if (response && response.success) {
                resolve({success: true, groupId: groupId});
              } else {
                reject('Falha ao postar no grupo: ' + (response ? response.error : 'Erro desconhecido'));
              }
              
              // Fechar a aba após a postagem
              setTimeout(() => {
                chrome.tabs.remove(tab.id);
              }, 2000);
            });
          }, 5000); // Aguardar 5 segundos para carregamento completo
        }
      });
    });
  });
}

// 🔧 SINCRONIZAR DADOS COM O SITE - CORRIGIDO
function syncWithSite() {
  return new Promise((resolve, reject) => {
    // Verificar se está conectado
    if (!state.connected) {
      reject('Não conectado ao Facebook');
      return;
    }
    
    // 🔧 CORREÇÃO: Preparar dados no formato correto para o PHP
    const syncData = {
      groups: state.groups,  // ✅ Apenas os grupos (já no formato correto do content.js)
      user_id: state.user ? state.user.id : null,
      timestamp: new Date().toISOString()
    };
    
    console.log('Enviando dados para sincronização:', syncData);
    
    // 🔧 CORREÇÃO: Enviar como FormData em vez de JSON
    const formData = new FormData();
    formData.append('extension_data', JSON.stringify(syncData));
    
    // Enviar dados para o site
    fetch(`${state.siteUrl}/api/sync-facebook.php`, {
      method: 'POST',
      body: formData  // ✅ Usar FormData em vez de JSON
    })
    .then(response => {
      console.log('Resposta da sincronização:', response.status);
      return response.json();
    })
    .then(data => {
      console.log('Dados da resposta:', data);
      if (data.success) {
        state.lastSync = new Date().toISOString();
        saveState();
        resolve({success: true, message: data.message});
      } else {
        reject(data.error || 'Erro na sincronização');
      }
    })
    .catch(error => {
      console.error('Erro na sincronização:', error);
      reject(error.message || 'Falha na conexão com o site');
    });
  });
}

// Lidar com mensagens do popup e do content script
chrome.runtime.onMessage.addListener(function(request, sender, sendResponse) {
  console.log("Mensagem recebida:", request.action);
  
  if (request.action === 'getStatus') {
    checkFacebookLogin().then(() => {
      sendResponse({
        status: 'ok',
        connected: state.connected,
        facebookConnected: state.connected,
        groupsCount: state.groups.length,
        lastSync: state.lastSync,
        groups: state.groups
      });
    });
    return true; // Indica que a resposta será assíncrona
  }
  
  else if (request.action === 'connectFacebook') {
    sendResponse({success: true});
  }
  
  else if (request.action === 'checkGroups') {
    fetchGroups()
      .then(groups => sendResponse({success: true, groups: groups}))
      .catch(error => sendResponse({success: false, error: String(error)}));
    return true;
  }
  
  else if (request.action === 'syncWithSite') {
    syncWithSite()
      .then(result => sendResponse(result))
      .catch(error => sendResponse({success: false, error: error}));
    return true;
  }
  
  else if (request.action === 'groupsExtracted') {
    console.log('Grupos extraídos recebidos:', request.groups);
    state.groups = request.groups;
    saveState();
    sendResponse({success: true});
  }
  
  else if (request.action === 'userDataExtracted') {
    state.user = {...state.user, ...request.userData};
    saveState();
    sendResponse({success: true});
  }
  
  else if (request.action === 'postToGroups') {
    const {groups, content} = request;
    const results = [];
    
    // Postar em cada grupo sequencialmente
    const postSequentially = async () => {
      for (const groupId of groups) {
        try {
          const result = await postToGroup(groupId, content);
          results.push({groupId, success: true});
        } catch (error) {
          results.push({groupId, success: false, error});
        }
      }
      sendResponse({success: true, results});
    };
    
    postSequentially();
    return true;
  }
});

// Verificar login quando a extensão é iniciada
checkFacebookLogin();

// Verificar conexão com o Facebook periodicamente
setInterval(checkFacebookLogin, 60000); // A cada minuto

// Lidar com mensagens do site
chrome.runtime.onMessageExternal.addListener(function(request, sender, sendResponse) {
  console.log("Mensagem externa recebida de:", sender.origin);
  console.log("Conteúdo:", request);
  
  // Verificar se a mensagem veio do nosso site
  if (sender.origin !== state.siteUrl) {
    sendResponse({success: false, error: 'Origem não permitida'});
    return;
  }
  
  if (request.action === 'checkStatus') {
    checkFacebookLogin().then(() => {
      sendResponse({
        status: 'ok',
        facebookConnected: state.connected,
        groups: state.groups
      });
    });
    return true;
  }
  
  else if (request.action === 'getGroups') {
    if (state.groups.length === 0) {
      fetchGroups()
        .then(groups => sendResponse({success: true, groups}))
        .catch(error => sendResponse({success: false, error: String(error)}));
      return true;
    } else {
      sendResponse({
        success: true,
        groups: state.groups
      });
    }
  }
  
  else if (request.action === 'postToGroups') {
    const {groups, content, requestId} = request;
    
    if (!Array.isArray(groups) || !content) {
      sendResponse({success: false, error: 'Parâmetros inválidos'});
      return;
    }
    
    // Verificar se está conectado ao Facebook
    if (!state.connected) {
      sendResponse({success: false, error: 'Não conectado ao Facebook'});
      return;
    }
    
    // Iniciar processo de postagem
    const postingProcess = async () => {
      const results = [];
      
      for (const groupId of groups) {
        try {
          await postToGroup(groupId, content);
          results.push({groupId, success: true});
        } catch (error) {
          results.push({groupId, success: false, error: String(error)});
        }
      }
      
      // Enviar resultado para o site via fetch
      fetch(`${state.siteUrl}/api/posting-result.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          requestId: requestId || 'post-' + Date.now(),
          results: results
        })
      });
    };
    
    // Iniciar processo e retornar resposta imediatamente
    postingProcess();
    sendResponse({success: true, message: 'Processo de postagem iniciado'});
  }
});