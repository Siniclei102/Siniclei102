// background.js - Script em segundo plano para coordenar a extensão

// Estado global
let activePosts = {};

// Inicialização
chrome.runtime.onInstalled.addListener(() => {
  console.log('PostGroup Facebook Extension instalada');
});

// Escutar mensagens de content scripts e popups
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'startBatchPost') {
    startBatchPost(message.postId, message.groups, message.message)
      .then(result => sendResponse(result))
      .catch(error => sendResponse({success: false, error: error.message}));
    return true;
  }
  
  if (message.action === 'getPostStatus') {
    const status = getPostStatus(message.postId);
    sendResponse(status);
    return true;
  }
  
  if (message.action === 'cancelPost') {
    cancelPost(message.postId);
    sendResponse({success: true});
    return true;
  }
});

// Escutar alterações em abas para detectar grupos do Facebook
chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
  if (changeInfo.status === 'complete' && tab.url && tab.url.includes('facebook.com/groups/')) {
    // Executar script para coletar informações do grupo atual
    chrome.scripting.executeScript({
      target: {tabId: tabId},
      files: ['facebook-scraper.js']
    }).catch(err => console.error('Erro ao injetar script:', err));
    
    // Verificar se há postagem pendente para este grupo
    checkPendingPostForGroup(tab.url, tabId);
  }
});

// Funções principais
async function startBatchPost(postId, groups, message) {
  if (!groups || groups.length === 0) {
    return {success: false, error: 'Nenhum grupo selecionado'};
  }
  
  // Configurar estado da postagem
  activePosts[postId] = {
    groups: groups,
    message: message,
    status: 'running',
    completed: 0,
    failed: 0,
    pending: groups.length,
    results: {}
  };
  
  // Executar postagem no servidor
  try {
    // Obter token de autenticação
    const data = await chrome.storage.local.get(['authToken']);
    
    if (!data.authToken) {
      throw new Error('Usuário não autenticado');
    }
    
    // Enviar dados para o servidor
    const response = await fetch('https://postadorfacebook.painelcontrole.xyz/api/posts/create', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${data.authToken}`
      },
      body: JSON.stringify({
        message: message,
        groupIds: groups.map(group => group.id)
      })
    });
    
    if (!response.ok) {
      throw new Error('Falha ao iniciar postagem no servidor');
    }
    
    const responseData = await response.json();
    
    // Iniciar monitoramento de progresso
    monitorPostProgress(postId, responseData.postId);
    
    return {
      success: true,
      postId: postId,
      serverPostId: responseData.postId
    };
  } catch (error) {
    delete activePosts[postId];
    return {
      success: false,
      error: error.message
    };
  }
}

function getPostStatus(postId) {
  if (!activePosts[postId]) {
    return {
      exists: false,
      error: 'Postagem não encontrada'
    };
  }
  
  return {
    exists: true,
    status: activePosts[postId].status,
    completed: activePosts[postId].completed,
    failed: activePosts[postId].failed,
    pending: activePosts[postId].pending,
    total: activePosts[postId].groups.length
  };
}

function cancelPost(postId) {
  if (activePosts[postId]) {
    activePosts[postId].status = 'cancelled';
  }
}

async function monitorPostProgress(postId, serverPostId) {
  if (!activePosts[postId]) return;
  
  try {
    // Obter token de autenticação
    const data = await chrome.storage.local.get(['authToken']);
    
    if (!data.authToken) {
      throw new Error('Usuário não autenticado');
    }
    
    // Verificar status no servidor
    const checkProgress = async () => {
      if (!activePosts[postId] || activePosts[postId].status === 'cancelled') {
        return;
      }
      
      try {
        const response = await fetch(`https://postadorfacebook.painelcontrole.xyz/api/posts/${serverPostId}/status`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${data.authToken}`
          }
        });
        
        if (!response.ok) {
          throw new Error('Falha ao verificar status da postagem');
        }
        
        const progressData = await response.json();
        
        // Atualizar estado
        if (activePosts[postId]) {
          activePosts[postId].completed = progressData.completedCount || 0;
          activePosts[postId].failed = progressData.failedCount || 0;
          activePosts[postId].pending = progressData.pendingCount || 0;
          
          // Verificar se terminou
          if (progressData.status === 'completed') {
            activePosts[postId].status = 'completed';
            
            // Notificar conclusão
            chrome.runtime.sendMessage({
              action: 'postCompleted',
              postId: postId,
              results: {
                completed: activePosts[postId].completed,
                failed: activePosts[postId].failed,
                total: activePosts[postId].groups.length
              }
            });
            
            // Limpar após um tempo
            setTimeout(() => {
              delete activePosts[postId];
            }, 60000); // Manter por 1 minuto para consultas
            
            return;
          } else if (progressData.status === 'failed') {
            activePosts[postId].status = 'failed';
            
            // Notificar falha
            chrome.runtime.sendMessage({
              action: 'postFailed',
              postId: postId,
              error: progressData.error || 'Erro desconhecido'
            });
            
            // Limpar após um tempo
            setTimeout(() => {
              delete activePosts[postId];
            }, 60000);
            
            return;
          }
          
          // Continuar verificando a cada 2 segundos
          setTimeout(checkProgress, 2000);
        }
      } catch (error) {
        console.error('Erro ao verificar progresso:', error);
        
        // Tentar novamente após 5 segundos em caso de erro
        setTimeout(checkProgress, 5000);
      }
    };
    
    // Iniciar verificação
    checkProgress();
  } catch (error) {
    console.error('Erro ao iniciar monitoramento:', error);
    
    if (activePosts[postId]) {
      activePosts[postId].status = 'failed';
      activePosts[postId].error = error.message;
    }
  }
}

function checkPendingPostForGroup(groupUrl, tabId) {
  // Verificar todos os posts ativos
  Object.keys(activePosts).forEach(postId => {
    const post = activePosts[postId];
    
    if (post.status === 'running') {
      // Verificar se este grupo está na lista de pendentes
      const groupMatch = post.groups.find(group => 
        groupUrl.includes(`/groups/${group.id}`) || 
        groupUrl === group.url
      );
      
      if (groupMatch) {
        // Verificar se já foi processado
        if (!post.results[groupMatch.id]) {
          // Tentar postar neste grupo
          chrome.tabs.sendMessage(tabId, {
            action: 'postToGroup',
            message: post.message
          }, response => {
            if (response && response.success) {
              // Marcar como processado
              if (activePosts[postId]) {
                activePosts[postId].results[groupMatch.id] = {
                  success: true,
                  timestamp: new Date().toISOString()
                };
              }
            }
          });
        }
      }
    }
  });
}

// Continuação do arquivo background.js

// Verificar periodicamente o estado de autenticação
setInterval(async () => {
  try {
    const data = await chrome.storage.local.get(['authToken', 'lastTokenCheck']);
    
    // Verificar se o usuário está autenticado
    if (!data.authToken) {
      return;
    }
    
    // Verificar a cada 30 minutos apenas
    const now = Date.now();
    if (data.lastTokenCheck && (now - data.lastTokenCheck < 30 * 60 * 1000)) {
      return;
    }
    
    // Registrar verificação
    chrome.storage.local.set({lastTokenCheck: now});
    
    // Verificar validade do token
    const response = await fetch('https://postadorfacebook.painelcontrole.xyz/api/auth/verify', {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${data.authToken}`
      }
    });
    
    if (!response.ok) {
      // Token inválido, fazer logout
      console.log('Token inválido ou expirado, limpando sessão');
      chrome.storage.local.remove(['authToken', 'userData']);
      
      // Notificar extensão
      chrome.runtime.sendMessage({
        action: 'sessionExpired'
      });
    }
  } catch (error) {
    console.error('Erro ao verificar autenticação:', error);
  }
}, 10 * 60 * 1000); // Verificar a cada 10 minutos

// Escutar comandos de navegador
chrome.action.onClicked.addListener((tab) => {
  // Abrir popup se usuário clicar no ícone da extensão quando não tiver popup aberto
  if (!chrome.action.getPopup) {
    chrome.action.setPopup({popup: 'popup.html'});
  }
});

// Mensagens entre abas
chrome.runtime.onConnect.addListener((port) => {
  if (port.name === 'postgroup-port') {
    port.onMessage.addListener((msg) => {
      if (msg.action === 'syncTabData') {
        // Sincronizar dados entre abas
        port.postMessage({
          action: 'syncedData',
          activePosts: activePosts
        });
      }
    });
  }
});

// Função para notificar o usuário sobre eventos importantes
function notifyUser(title, message) {
  chrome.notifications.create({
    type: 'basic',
    iconUrl: 'icons/icon128.png',
    title: title,
    message: message
  });
}

// Processamento em lote de grupos
async function processBatchGroups(groups, action) {
  const results = {
    success: 0,
    failed: 0,
    total: groups.length
  };
  
  // Processar em pequenos lotes para evitar sobrecarga
  const batchSize = 5;
  const batches = [];
  
  for (let i = 0; i < groups.length; i += batchSize) {
    batches.push(groups.slice(i, i + batchSize));
  }
  
  for (const batch of batches) {
    const promises = batch.map(group => processGroup(group, action));
    const batchResults = await Promise.allSettled(promises);
    
    batchResults.forEach(result => {
      if (result.status === 'fulfilled' && result.value.success) {
        results.success++;
      } else {
        results.failed++;
      }
    });
    
    // Pequeno delay entre lotes
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  
  return results;
}

// Processar ação em um grupo individual
async function processGroup(group, action) {
  try {
    const data = await chrome.storage.local.get(['authToken']);
    
    if (!data.authToken) {
      throw new Error('Usuário não autenticado');
    }
    
    let endpoint = '';
    let method = 'POST';
    let body = {};
    
    switch (action) {
      case 'verify':
        endpoint = `api/groups/verify/${group.id}`;
        method = 'GET';
        break;
      case 'sync':
        endpoint = 'api/groups/add';
        body = { group };
        break;
      case 'remove':
        endpoint = `api/groups/${group.id}`;
        method = 'DELETE';
        break;
      default:
        throw new Error('Ação desconhecida');
    }
    
    const response = await fetch(`https://postadorfacebook.painelcontrole.xyz/${endpoint}`, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${data.authToken}`
      },
      body: method !== 'GET' ? JSON.stringify(body) : undefined
    });
    
    if (!response.ok) {
      throw new Error(`Falha na operação: ${response.status}`);
    }
    
    const result = await response.json();
    
    return {
      success: true,
      groupId: group.id,
      result: result
    };
  } catch (error) {
    console.error(`Erro ao processar grupo ${group.id}:`, error);
    return {
      success: false,
      groupId: group.id,
      error: error.message
    };
  }
}

// Verificar se há grupos a serem processados automaticamente
chrome.storage.onChanged.addListener((changes, namespace) => {
  if (namespace === 'local' && changes.pendingGroups) {
    const pendingGroups = changes.pendingGroups.newValue;
    
    if (pendingGroups && pendingGroups.length > 0) {
      processBatchGroups(pendingGroups, 'sync')
        .then(results => {
          console.log('Processamento automático concluído:', results);
          
          // Limpar grupos pendentes
          chrome.storage.local.remove(['pendingGroups']);
          
          // Notificar resultado
          if (results.success > 0) {
            notifyUser(
              'Sincronização de Grupos',
              `${results.success} grupos sincronizados com sucesso. ${results.failed} falhas.`
            );
          }
        })
        .catch(error => {
          console.error('Erro no processamento automático:', error);
        });
    }
  }
});

// Configurar estado inicial ao iniciar
(async function() {
  try {
    // Verificar se há postagens ativas salvas
    const data = await chrome.storage.local.get(['activePosts']);
    
    if (data.activePosts) {
      // Restaurar estado de postagens
      activePosts = data.activePosts;
      
      // Verificar postagens em andamento
      Object.keys(activePosts).forEach(postId => {
        const post = activePosts[postId];
        
        if (post.status === 'running') {
          // Continuar monitoramento
          monitorPostProgress(postId, post.serverPostId);
        }
      });
    }
  } catch (error) {
    console.error('Erro ao inicializar estado:', error);
  }
})();

// Salvar estado de postagens antes de fechar
window.addEventListener('beforeunload', () => {
  if (Object.keys(activePosts).length > 0) {
    chrome.storage.local.set({activePosts});
  }
});