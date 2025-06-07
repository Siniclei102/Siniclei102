// content.js - Script que é injetado na página do Facebook

console.log("PostGroup Facebook Extension - Content Script Loaded");

// Estado global
let isScanning = false;
let detectedGroups = [];
let facebookUserData = null;

// Inicialização
(function() {
  // Detectar informações do usuário do Facebook
  detectFacebookUser();
  
  // Verificar se estamos em uma página de grupo
  if (window.location.href.includes('facebook.com/groups/')) {
    collectGroupInfo();
  }
})();

// Listener para mensagens do popup ou background
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === "scanGroups") {
    if (!isScanning) {
      sendResponse({status: 'scanning'});
      startGroupScanning();
    } else {
      sendResponse({status: 'already_scanning'});
    }
    return true;
  }
  
  if (request.action === "postToGroup") {
    const { groupUrl, message } = request;
    postToFacebookGroup(groupUrl, message)
      .then(result => sendResponse(result))
      .catch(error => sendResponse({success: false, error: error.message}));
    return true;
  }
});

// Funções principais
async function startGroupScanning() {
  console.log("Iniciando escaneamento de grupos");
  
  if (isScanning) return;
  isScanning = true;
  detectedGroups = [];
  
  try {
    // Primeiro, tentamos via API do Facebook (mais confiável quando disponível)
    const groupsFromApi = await scanGroupsViaApi();
    
    if (groupsFromApi && groupsFromApi.length > 0) {
      detectedGroups = groupsFromApi;
    } else {
      // Método de fallback: escaneamento via navegação
      await scanGroupsViaScraping();
    }
    
    console.log(`Escaneamento concluído. ${detectedGroups.length} grupos encontrados.`);
    
    // Enviar resultado para o popup
    chrome.runtime.sendMessage({
      action: 'groupsDetected',
      groups: detectedGroups
    });
  } catch (error) {
    console.error("Erro durante escaneamento:", error);
    chrome.runtime.sendMessage({
      action: 'scanningError',
      error: error.message
    });
  } finally {
    isScanning = false;
  }
}

async function scanGroupsViaApi() {
  return new Promise((resolve) => {
    // Tentativa de usar API do Facebook
    // Esta é uma técnica que procura por objetos que o Facebook expõe globalmente
    const timeout = setTimeout(() => resolve([]), 2000); // Timeout para fallback
    
    try {
      // Injetar script para acessar propriedades do Facebook
      const script = document.createElement('script');
      script.textContent = `
        try {
          // Tentativa de acessar dados do usuário e grupos do require do Facebook
          // Esta é uma técnica avançada que pode quebrar com atualizações do Facebook
          let groups = [];
          
          // Diferentes caminhos onde o Facebook armazena os dados
          if (window.__REACT_DEVTOOLS_GLOBAL_HOOK__ && window.__REACT_DEVTOOLS_GLOBAL_HOOK__.renderers) {
            // Buscar via Fiber nodes (React 16+)
            const groupsData = [...document.querySelectorAll('[role="navigation"] a[href*="/groups/"]')]
              .map(a => {
                try {
                  const href = a.getAttribute('href');
                  const match = href.match(/\\/groups\\/([^\\/\\?]+)/);
                  const id = match ? match[1] : null;
                  return id ? {
                    id: id,
                    name: a.textContent.trim(),
                    url: 'https://www.facebook.com' + href
                  } : null;
                } catch (e) {
                  return null;
                }
              })
              .filter(Boolean);
              
            if (groupsData.length > 0) {
              groups = groupsData;
            }
          }
          
          // Enviar dados para o content script
          window.postMessage({
            type: 'FACEBOOK_GROUPS_DATA',
            groups: groups
          }, '*');
        } catch (e) {
          console.error('Erro ao extrair dados do Facebook:', e);
          window.postMessage({
            type: 'FACEBOOK_GROUPS_ERROR',
            error: e.toString()
          }, '*');
        }
      `;
      document.head.appendChild(script);
      document.head.removeChild(script);
      
      // Escutar a mensagem do script injetado
      window.addEventListener('message', function(event) {
        if (event.source !== window) return;
        
        if (event.data.type === 'FACEBOOK_GROUPS_DATA') {
          clearTimeout(timeout);
          resolve(event.data.groups || []);
        }
        
        if (event.data.type === 'FACEBOOK_GROUPS_ERROR') {
          console.error('Erro ao extrair dados via API:', event.data.error);
        }
      }, {once: true});
    } catch (error) {
      console.error('Erro ao injetar script:', error);
      clearTimeout(timeout);
      resolve([]);
    }
  });
}

async function scanGroupsViaScraping() {
  console.log("Iniciando escaneamento via scraping");
  
  // Método 1: Buscar links de grupos no menu de navegação
  let navGroups = [...document.querySelectorAll('[role="navigation"] a[href*="/groups/"]')]
    .map(extractGroupData)
    .filter(Boolean);
    
  if (navGroups.length > 0) {
    addToDetectedGroups(navGroups);
  }
  
  // Método 2: Tentar acessar a página de grupos
  try {
    // Salvar URL atual para voltar depois
    const currentUrl = window.location.href;
    
    // Navegar para a página de grupos
    window.location.href = 'https://www.facebook.com/groups/feed/';
    
    // Aguardar carregamento da página
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    // Executar scrolling para carregar mais grupos
    await autoScroll();
    
    // Coletar grupos da página
    const feedGroups = [...document.querySelectorAll('a[href*="/groups/"]')]
      .map(extractGroupData)
      .filter(Boolean);
      
    addToDetectedGroups(feedGroups);
    
    // Voltar para a página original
    window.location.href = currentUrl;
  } catch (error) {
    console.error('Erro ao navegar para página de grupos:', error);
  }
  
  return detectedGroups;
}

function extractGroupData(element) {
  try {
    const href = element.getAttribute('href');
    const match = href.match(/\/groups\/([^\/\?]+)/);
    
    if (!match) return null;
    
    const id = match[1];
    let name = element.textContent.trim();
    
    // Limpar nome se necessário
    if (name.includes('Notificações') || name.length < 2) {
      const nameElement = element.querySelector('span');
      if (nameElement) {
        name = nameElement.textContent.trim();
      }
    }
    
    // Validar dados
    if (!id || !name || id === 'feed' || id === 'discover' || name.length < 2) {
      return null;
    }
    
    return {
      id: id,
      name: name,
      url: href.startsWith('http') ? href : `https://www.facebook.com${href}`
    };
  } catch (error) {
    console.error('Erro ao extrair dados do grupo:', error);
    return null;
  }
}

function addToDetectedGroups(newGroups) {
  // Filtrar grupos já adicionados
  const uniqueGroups = newGroups.filter(newGroup => 
    !detectedGroups.some(existing => existing.id === newGroup.id)
  );
  
  if (uniqueGroups.length > 0) {
    detectedGroups = [...detectedGroups, ...uniqueGroups];
    
    // Enviar atualização de progresso
    chrome.runtime.sendMessage({
      action: 'scanningProgress',
      count: detectedGroups.length
    });
  }
}

async function autoScroll() {
  return new Promise((resolve) => {
    let totalHeight = 0;
    let distance = 300;
    let scrolls = 0;
    const maxScrolls = 10; // Limitar número de scrolls
    
    const timer = setInterval(() => {
      window.scrollBy(0, distance);
      totalHeight += distance;
      scrolls++;
      
      // Parar após certo número de scrolls ou se chegou ao fim da página
      if (scrolls >= maxScrolls || 
          window.scrollY + window.innerHeight >= document.body.scrollHeight) {
        clearInterval(timer);
        resolve();
      }
    }, 500);
  });
}

function collectGroupInfo() {
  try {
    const groupPath = window.location.pathname;
    const match = groupPath.match(/\/groups\/([^\/\?]+)/);
    
    if (!match) return;
    
    const groupId = match[1];
    let groupName = '';
    
    // Tentar encontrar o nome do grupo
    const nameElement = document.querySelector('h1') || 
                        document.querySelector('[role="main"] [role="heading"]');
    if (nameElement) {
      groupName = nameElement.textContent.trim();
    }
    
    if (groupId && groupName) {
      const groupInfo = {
        id: groupId,
        name: groupName,
        url: window.location.href
      };
      
      // Adicionar ao array de grupos detectados
      addToDetectedGroups([groupInfo]);
    }
  } catch (error) {
    console.error('Erro ao coletar informações do grupo:', error);
  }
}

async function postToFacebookGroup(groupUrl, message) {
  return new Promise((resolve, reject) => {
    try {
      // Abrir a página do grupo em uma nova aba
      window.open(groupUrl, '_blank');
      
      // Não podemos controlar a nova aba diretamente, então retornamos
      // success: true apenas para indicar que a aba foi aberta
      resolve({
        success: true,
        message: 'Página do grupo aberta para postagem'
      });
    } catch (error) {
      reject(error);
    }
  });
}

function detectFacebookUser() {
  try {
    // Buscar nome do usuário
    const nameElement = document.querySelector('[role="navigation"] [aria-label*="perfil"]') ||
                       document.querySelector('[data-pagelet="LeftRail"] a[role="link"][href*="/profile.php"]') ||
                       document.querySelector('a[href^="/profile.php"]');
    
    let userName = '';
    let userAvatar = '';
    
    if (nameElement) {
      userName = nameElement.textContent.trim();
      
      // Buscar avatar
      const avatarImg = nameElement.querySelector('img') || 
                       document.querySelector('[role="navigation"] img[height="40"]');
      if (avatarImg) {
        userAvatar = avatarImg.src;
      }
    }
    
    if (userName) {
      facebookUserData = {
        name: userName,
        avatar: userAvatar
      };
      
      // Enviar para o popup
      chrome.runtime.sendMessage({
        action: 'facebookUserData',
        userData: facebookUserData
      });
    }
  } catch (error) {
    console.error('Erro ao detectar usuário do Facebook:', error);
  }
}