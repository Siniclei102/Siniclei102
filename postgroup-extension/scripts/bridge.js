// bridge.js - Ponte de comunicação entre a extensão e o site
(function() {
  console.log("🔄 PostGroup Bridge v1.0.0 inicializado");
  
  // ID conhecido da extensão (preencher com seu ID)
  const EXTENSION_ID = "kmmmhpopmfhdbhoonclgbilbeoknoehb";
  
  // Estado da ponte
  let bridgeState = {
    extensionDetected: false,
    facebookConnected: false,
    lastUpdated: null,
    groups: [],
    intervalId: null
  };
  
  // Elementos da interface que serão atualizados
  const uiElements = {
    // Status
    extensionBadge: document.querySelector('.extensão-não-instalada, [aria-label="Extensão não instalada"]'),
    facebookBadge: document.querySelector('.desconectado, [aria-label="Desconectado"]'),
    
    // Cards de status
    extensionCard: document.querySelector('#extensao-status, [data-status="extensao"]'),
    facebookCard: document.querySelector('#facebook-status, [data-status="facebook"]'),
    
    // Contadores
    totalGroups: document.querySelector('#total-de-grupos'),
    
    // Botões
    connectFacebookBtn: document.querySelector('[id*="conectar-facebook"], [onclick*="conectarFacebook"]')
  };
  
  // Log de status
  function logStatus(message) {
    console.log(`📌 PostGroup: ${message}`);
  }
  
  // Verifica a extensão e atualiza a interface
  function checkExtensionAndUpdateUI() {
    try {
      logStatus("Tentando comunicação com a extensão...");
      
      // Faz uma chamada para a extensão
      chrome.runtime.sendMessage(EXTENSION_ID, { 
        action: "getStatus", 
        from: "site",
        timestamp: new Date().getTime()
      }, function(response) {
        if (chrome.runtime.lastError) {
          logStatus(`Erro de comunicação: ${chrome.runtime.lastError.message}`);
          updateUI(false, false, []);
          return;
        }
        
        if (response && response.status === "ok") {
          logStatus("Comunicação estabelecida com a extensão!");
          
          // Atualiza o estado
          bridgeState.extensionDetected = true;
          bridgeState.facebookConnected = response.facebookConnected;
          bridgeState.lastUpdated = new Date();
          
          // Salva os grupos se disponíveis
          if (response.groups && response.groups.length > 0) {
            bridgeState.groups = response.groups;
            logStatus(`Grupos recebidos: ${response.groups.length}`);
          }
          
          // Atualiza a interface
          updateUI(true, response.facebookConnected, response.groups || []);
          
          // Se estiver tudo conectado, podemos reduzir a frequência das verificações
          if (bridgeState.extensionDetected && bridgeState.facebookConnected) {
            if (bridgeState.intervalId) {
              clearInterval(bridgeState.intervalId);
            }
            
            bridgeState.intervalId = setInterval(checkExtensionAndUpdateUI, 15000); // A cada 15 segundos
          }
          
          // Envia dados para o backend se necessário
          if (response.facebookConnected && response.groups && response.groups.length > 0) {
            syncWithBackend(response.groups);
          }
        } else {
          logStatus("Extensão respondeu, mas com status inválido");
          updateUI(false, false, []);
        }
      });
    } catch (e) {
      logStatus(`Erro ao verificar extensão: ${e.message}`);
      updateUI(false, false, []);
    }
  }
  
  // Atualiza a interface com base no estado da extensão e do Facebook
  function updateUI(extensionInstalled, facebookConnected, groups) {
    // 1. Atualiza os badges
    if (uiElements.extensionBadge) {
      updateBadge(uiElements.extensionBadge, extensionInstalled, "Extensão instalada", "Extensão não instalada");
    }
    
    if (uiElements.facebookBadge) {
      updateBadge(uiElements.facebookBadge, facebookConnected, "Conectado", "Desconectado");
    }
    
    // 2. Atualiza os cards de status
    if (uiElements.extensionCard) {
      updateStatusCard(uiElements.extensionCard, extensionInstalled);
    }
    
    if (uiElements.facebookCard) {
      updateStatusCard(uiElements.facebookCard, facebookConnected);
    }
    
    // 3. Atualiza os contadores
    if (uiElements.totalGroups && groups.length > 0) {
      uiElements.totalGroups.textContent = groups.length.toString();
    }
    
    // 4. Habilita/desabilita o botão de conexão com o Facebook
    if (uiElements.connectFacebookBtn) {
      uiElements.connectFacebookBtn.disabled = !extensionInstalled;
      
      if (extensionInstalled) {
        uiElements.connectFacebookBtn.classList.remove("bg-gray-300", "cursor-not-allowed");
        uiElements.connectFacebookBtn.classList.add("bg-blue-600", "hover:bg-blue-700");
      } else {
        uiElements.connectFacebookBtn.classList.add("bg-gray-300", "cursor-not-allowed");
        uiElements.connectFacebookBtn.classList.remove("bg-blue-600", "hover:bg-blue-700");
      }
    }
  }
  
  // Atualiza um badge de status
  function updateBadge(element, isActive, activeText, inactiveText) {
    if (!element) return;
    
    // Modifica o texto
    if (element.querySelector('span')) {
      element.querySelector('span').textContent = isActive ? activeText : inactiveText;
    } else {
      element.textContent = isActive ? activeText : inactiveText;
    }
    
    // Modifica as classes
    if (isActive) {
      element.classList.remove("bg-red-100", "text-red-800");
      element.classList.add("bg-green-100", "text-green-800");
    } else {
      element.classList.add("bg-red-100", "text-red-800");
      element.classList.remove("bg-green-100", "text-green-800");
    }
  }
  
  // Atualiza um card de status
  function updateStatusCard(element, isActive) {
    if (!element) return;
    
    // Encontra o ícone (se existir)
    const icon = element.querySelector('i, .icon');
    if (icon) {
      if (isActive) {
        icon.classList.remove("text-gray-500", "text-red-500");
        icon.classList.add("text-green-500");
      } else {
        icon.classList.remove("text-green-500");
        icon.classList.add("text-gray-500");
      }
    }
    
    // Encontra o texto (se existir)
    const text = element.querySelector('[id*="-text"], .status-text, p');
    if (text) {
      if (isActive) {
        text.textContent = element.id.includes("extensao") ? 
          "Extensão instalada" : "Facebook Conectado";
      } else {
        text.textContent = element.id.includes("extensao") ? 
          "Extensão não instalada" : "Facebook Desconectado";
      }
    }
  }
  
  // Sincroniza os dados com o backend
  function syncWithBackend(groups) {
    fetch('api/sync-facebook.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        groups: groups,
        timestamp: new Date().toISOString()
      })
    })
    .then(response => response.json())
    .then(data => {
      logStatus("Sincronização com backend: " + (data.success ? "Sucesso" : "Falha"));
    })
    .catch(error => {
      logStatus("Erro na sincronização com backend: " + error.message);
    });
  }
  
  // Adiciona um botão de diagnóstico no canto da tela
  function addDiagnosticButton() {
    const button = document.createElement('button');
    button.className = 'fixed bottom-5 left-5 bg-blue-600 text-white px-3 py-2 rounded-full shadow-lg z-50 hover:bg-blue-700 flex items-center';
    button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" /></svg><span>Diagnosticar</span>';
    
    button.addEventListener('click', function() {
      console.clear();
      logStatus("Diagnóstico iniciado");
      
      // Tentativas com vários IDs para debugging
      const potentialIds = [
        EXTENSION_ID,
        "dafbcblmlfoelilfpomajookbdfhlcko",
        "hpkiffhoeclbjojlfiihpclkkpiibjdm",
        "jhkpngghdgkcligpkfngiifienbcpdbp"
      ];
      
      potentialIds.forEach(id => {
        try {
          logStatus(`Tentando ID: ${id}`);
          chrome.runtime.sendMessage(id, { action: "getStatus", debug: true }, response => {
            if (chrome.runtime.lastError) {
              logStatus(`❌ ID ${id}: ${chrome.runtime.lastError.message}`);
              return;
            }
            
            if (response && response.status === "ok") {
              logStatus(`✅ ID ${id} respondeu! Facebook: ${response.facebookConnected ? 'Conectado' : 'Desconectado'}`);
              logStatus(`Grupos: ${response.groups ? response.groups.length : 0}`);
              
              // Se encontrarmos um ID que funciona, atualizamos o estado
              EXTENSION_ID = id;
              bridgeState.extensionDetected = true;
              bridgeState.facebookConnected = response.facebookConnected;
              
              // Força atualização da interface
              updateUI(true, response.facebookConnected, response.groups || []);
            }
          });
        } catch (e) {
          logStatus(`❌ Erro ao testar ID ${id}: ${e.message}`);
        }
      });
      
      // Diagnóstico do DOM
      logStatus("\n--- Diagnóstico do DOM ---");
      logStatus(`extensionBadge: ${!!uiElements.extensionBadge}`);
      logStatus(`facebookBadge: ${!!uiElements.facebookBadge}`);
      logStatus(`extensionCard: ${!!uiElements.extensionCard}`);
      logStatus(`facebookCard: ${!!uiElements.facebookCard}`);
      logStatus(`totalGroups: ${!!uiElements.totalGroups}`);
      logStatus(`connectFacebookBtn: ${!!uiElements.connectFacebookBtn}`);
      
      // Diagnóstico do estado
      logStatus("\n--- Estado da ponte ---");
      logStatus(`extensionDetected: ${bridgeState.extensionDetected}`);
      logStatus(`facebookConnected: ${bridgeState.facebookConnected}`);
      logStatus(`lastUpdated: ${bridgeState.lastUpdated}`);
      logStatus(`groups: ${bridgeState.groups.length}`);
    });
    
    document.body.appendChild(button);
  }
  
  // Força atualização do status
  function forceStatusUpdate() {
    logStatus("Forçando atualização de status");
    checkExtensionAndUpdateUI();
  }
  
  // Expõe funções globalmente para debug
  window.postGroupBridge = {
    checkStatus: checkExtensionAndUpdateUI,
    forceUpdate: forceStatusUpdate,
    getState: () => ({ ...bridgeState }),
    debug: {
      updateCardElements: () => {
        uiElements.extensionCard = document.querySelector('#extensao-status, [data-status="extensao"]');
        uiElements.facebookCard = document.querySelector('#facebook-status, [data-status="facebook"]');
        logStatus("Elementos de card atualizados");
      },
      updateBadgeElements: () => {
        uiElements.extensionBadge = document.querySelector('.extensão-não-instalada, [aria-label="Extensão não instalada"]');
        uiElements.facebookBadge = document.querySelector('.desconectado, [aria-label="Desconectado"]');
        logStatus("Elementos de badge atualizados");
      },
      testUIUpdate: (extStatus, fbStatus) => {
        updateUI(extStatus, fbStatus, bridgeState.groups);
        logStatus(`Interface atualizada manualmente: ext=${extStatus}, fb=${fbStatus}`);
      }
    }
  };
  
  // Adiciona seletores para identificação no DOM
  function addIdentifiersToDOM() {
    // Adds IDs and data attributes for easier targeting
    
    // Extension badge
    const extBadge = document.querySelector('.extensão-não-instalada, .extensão-instalada');
    if (extBadge && !extBadge.id) {
      extBadge.id = 'extension-badge';
      extBadge.setAttribute('data-status', 'extensao');
    }
    
    // Facebook badge  
    const fbBadge = document.querySelector('.desconectado, .conectado');
    if (fbBadge && !fbBadge.id) {
      fbBadge.id = 'facebook-badge';
      fbBadge.setAttribute('data-status', 'facebook');
    }
    
    // Extension status card
    const extCard = document.querySelector('[id*="extensao"], [class*="extensao-status"]');
    if (extCard && !extCard.id) {
      extCard.id = 'extensao-status';
      extCard.setAttribute('data-status', 'extensao');
    }
    
    // Facebook status card
    const fbCard = document.querySelector('[id*="facebook"], [class*="facebook-status"]');
    if (fbCard && !fbCard.id) {
      fbCard.id = 'facebook-status';
      fbCard.setAttribute('data-status', 'facebook');
    }
  }
  
  // Abordagem mais agressiva para encontrar e modificar elementos relevantes
  function findAndUpdateAllStatusElements() {
    // Esta função busca elementos que possivelmente contenham texto indicativo
    // de status e os atualiza diretamente
    
    const possibleExtTexts = ['extensão não instalada', 'extensão nao instalada', 'instale nossa extensão'];
    const possibleFbTexts = ['facebook desconectado', 'conecte sua conta', 'desconectado'];
    
    // 1. Procura por texto que indica status da extensão
    document.querySelectorAll('h1, h2, h3, h4, h5, h6, p, span, div').forEach(el => {
      const textContent = el.textContent.toLowerCase();
      
      // Verificar se o elemento contém texto sobre extensão
      if (possibleExtTexts.some(txt => textContent.includes(txt))) {
        if (bridgeState.extensionDetected) {
          el.textContent = el.textContent.replace(/extensão não instalada|extensão nao instalada|instale nossa extensão/i, 'Extensão instalada');
          
          // Tenta também mudar classes se forem aplicáveis
          el.classList.remove('text-red-800', 'bg-red-100');
          el.classList.add('text-green-800', 'bg-green-100');
        }
      }
      
      // Verificar se o elemento contém texto sobre Facebook
      if (possibleFbTexts.some(txt => textContent.includes(txt))) {
        if (bridgeState.facebookConnected) {
          el.textContent = el.textContent.replace(/facebook desconectado|conecte sua conta|desconectado/i, 'Facebook Conectado');
          
          // Tenta também mudar classes se forem aplicáveis
          el.classList.remove('text-red-800', 'bg-red-100');
          el.classList.add('text-green-800', 'bg-green-100');
        }
      }
      
      // Verificar se é um contador de grupos
      if (textContent.includes('total de grupos') && textContent.includes('0')) {
        const parentElement = el.parentElement;
        if (parentElement && bridgeState.groups.length > 0) {
          // Procura o número 0 no elemento pai e tenta substituí-lo
          const counterElement = parentElement.querySelector('p, span, div');
          if (counterElement && counterElement.textContent === '0') {
            counterElement.textContent = bridgeState.groups.length.toString();
          }
        }
      }
    });
  }
  
  // Inicializa a ponte
  function init() {
    logStatus("Inicializando bridge de comunicação");
    
    // Aguarda um momento para garantir que o DOM está pronto
    setTimeout(() => {
      addIdentifiersToDOM();
      
      // Verifica a extensão inicialmente
      checkExtensionAndUpdateUI();
      
      // Define intervalo para verificações periódicas
      bridgeState.intervalId = setInterval(checkExtensionAndUpdateUI, 5000); // A cada 5 segundos
      
      // Adiciona botão de diagnóstico
      addDiagnosticButton();
      
      // Atualização agressiva para corrigir possíveis textos de status
      setInterval(findAndUpdateAllStatusElements, 3000);
    }, 1000);
  }
  
  // Inicia quando o DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();