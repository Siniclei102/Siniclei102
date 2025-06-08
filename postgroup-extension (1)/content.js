// ===================================
// PostGroup - Content Script
// Interage diretamente com o Facebook
// ===================================

// Variáveis de controle
let isExtracting = false;

// Função para extrair dados do usuário
function extractUserData() {
  console.log("Extraindo dados do usuário...");
  
  const userName = document.querySelector('div[role="navigation"] span[dir="auto"]')?.textContent;
  const profilePicUrl = document.querySelector('image[xlink:href]')?.getAttribute('xlink:href') || 
                       document.querySelector('a[aria-label*="perfil"] img')?.src ||
                       document.querySelector('a[href*="/profile.php"] img')?.src;

  if (userName) {
    chrome.runtime.sendMessage({
      action: 'userDataExtracted',
      userData: {
        name: userName,
        profilePicUrl: profilePicUrl
      }
    });
    console.log("Dados do usuário extraídos:", userName);
  } else {
    console.log("Não foi possível encontrar dados do usuário");
  }
}

// Função para extrair grupos
function extractGroups() {
  if (isExtracting) return;
  isExtracting = true;
  
  console.log('Iniciando extração de grupos...');
  
  // Detectar se estamos na página de grupos
  if (!window.location.href.includes('facebook.com/groups')) {
    console.log('Não estamos na página de grupos do Facebook');
    isExtracting = false;
    return;
  }
  
  // Scroll até o final da página para carregar todos os grupos
  function scrollToBottom() {
    return new Promise(resolve => {
      const scrollInterval = setInterval(() => {
        window.scrollTo(0, document.body.scrollHeight);
        
        // Verificar se chegamos ao final (não há mais carregamento)
        const spinners = document.querySelectorAll('div[aria-label="Carregando..."]');
        if (spinners.length === 0) {
          clearTimeout(scrollTimeout);
          clearInterval(scrollInterval);
          setTimeout(resolve, 1000); // Espera final para garantir carregamento
        }
      }, 1000);
      
      // Timeout de segurança (30 segundos)
      const scrollTimeout = setTimeout(() => {
        clearInterval(scrollInterval);
        resolve();
      }, 30000);
    });
  }
  
  // Extrair informações dos grupos após rolagem
  async function getGroupsInfo() {
    await scrollToBottom();
    console.log('Rolagem concluída, extraindo informações dos grupos...');
    
    const groups = [];
    const groupElements = document.querySelectorAll('a[href*="/groups/"][role="link"]');
    
    groupElements.forEach(element => {
      // Verificar se este elemento é um link de grupo válido
      const href = element.getAttribute('href');
      if (!href || href.includes('/hashtag/') || href.includes('/help') || href.includes('marketplace')) {
        return;
      }
      
      // Extrair ID do grupo
      const groupIdMatch = href.match(/\/groups\/(\d+)/);
      if (!groupIdMatch) return;
      
      const groupId = groupIdMatch[1];
      
      // Evitar duplicatas
      if (groups.some(g => g.id === groupId)) return;
      
      // Encontrar nome e imagem do grupo
      const nameElement = element.querySelector('span');
      const imageElement = element.querySelector('img');
      
      if (!nameElement) return;
      
      const parentCard = element.closest('div[role="listitem"]') || element.closest('div[role="none"]');
      let memberCount = '';
      let privacy = 'Desconhecido';
      
      if (parentCard) {
        // Tentar encontrar contagem de membros e privacidade
        const textElements = parentCard.querySelectorAll('span');
        textElements.forEach(span => {
          const text = span.textContent;
          if (text.includes(' membro') || text.includes(' participante')) {
            memberCount = text.trim();
          }
          if (text.includes('Público') || text.includes('Privado')) {
            privacy = text.trim();
          }
        });
      }
      
      groups.push({
        id: groupId,
        name: nameElement.textContent.trim(),
        image: imageElement ? imageElement.src : '',
        memberCount: memberCount,
        privacy: privacy,
        url: `https://www.facebook.com/groups/${groupId}`
      });
    });
    
    console.log(`Extração concluída: ${groups.length} grupos encontrados`);
    
    chrome.runtime.sendMessage({
      action: 'groupsExtracted',
      groups: groups
    });
    
    isExtracting = false;
    return groups;
  }
  
  getGroupsInfo();
}

// Função para criar uma postagem em um grupo
function createPost(content) {
  return new Promise((resolve, reject) => {
    try {
      console.log("Iniciando processo de postagem...");
      
      // Clicar no campo "Criar publicação"
      const createPostButtons = Array.from(document.querySelectorAll('[role="button"]'));
      const createPostButton = createPostButtons.find(el => 
        el.textContent.includes('Criar publicação') || 
        el.textContent.includes('Escrever algo...') || 
        el.textContent.includes('No que você está pensando') ||
        el.textContent.includes('Publicar')
      );
      
      if (!createPostButton) {
        console.error('Campo de criação de publicação não encontrado');
        return reject('Campo de criação de publicação não encontrado');
      }
      
      createPostButton.click();
      console.log('Campo de criação de publicação clicado');
      
      // Aguardar o modal de postagem abrir
      setTimeout(() => {
        // Encontrar o campo de texto onde digitar o conteúdo
        const postTextField = document.querySelector('[contenteditable="true"][role="textbox"]');
        
        if (!postTextField) {
          console.error('Campo de texto não encontrado');
          return reject('Campo de texto não encontrado');
        }
        
        // Inserir o texto
        postTextField.focus();
        
        // Usar o método execCommand para inserir texto
        document.execCommand('insertText', false, content.text);
        
        console.log('Texto inserido');
        
        // Se tiver imagem, adicionar depois
        if (content.imageUrl) {
          // TODO: Implementar adição de imagem
          console.log('Adicionando imagem (não implementado)');
        }
        
        // Procurar e clicar no botão "Publicar"
        setTimeout(() => {
          const publishButtons = Array.from(document.querySelectorAll('[role="button"]'));
          const publishButton = publishButtons.find(el => 
            el.textContent.includes('Publicar') || 
            el.textContent.includes('Compartilhar')
          );
          
          if (!publishButton) {
            console.error('Botão de publicação não encontrado');
            return reject('Botão de publicação não encontrado');
          }
          
          publishButton.click();
          console.log('Botão de publicação clicado');
          
          // Aguardar a publicação ser concluída
          setTimeout(() => {
            resolve({success: true});
          }, 3000);
          
        }, 1500);
      }, 2000);
      
    } catch (error) {
      console.error('Erro ao criar publicação:', error);
      reject('Erro ao criar publicação: ' + error.message);
    }
  });
}

// Receber mensagens do background script
chrome.runtime.onMessage.addListener(function(request, sender, sendResponse) {
  console.log("Content script recebeu mensagem:", request.action);
  
  if (request.action === 'getUserData') {
    extractUserData();
    sendResponse({success: true});
  }
  
  else if (request.action === 'getGroups') {
    extractGroups()
      .then(groups => {
        sendResponse({success: true, groups: groups});
      })
      .catch(error => {
        sendResponse({success: false, error: String(error)});
      });
    return true;
  }
  
  else if (request.action === 'postToGroup') {
    createPost(request.content)
      .then(result => sendResponse(result))
      .catch(error => sendResponse({success: false, error: String(error)}));
    return true; // Necessário para resposta assíncrona
  }
});

// Executar quando a página carregar completamente
window.addEventListener('load', function() {
  // Verificar se estamos no Facebook
  if (window.location.hostname.includes('facebook.com')) {
    console.log('PostGroup Connector ativo no Facebook');
    
    // Extrair dados do usuário se estivermos em uma página relevante
    setTimeout(extractUserData, 3000);
    
    // Verificar se estamos na página de grupos
    if (window.location.href.includes('/groups/') || 
        window.location.href.includes('/groups_browse/')) {
      console.log('Página de grupos detectada');
      setTimeout(extractGroups, 5000);
    }
  }
});

// Observar mudanças na URL para detectar navegação entre páginas
let lastUrl = location.href; 
new MutationObserver(() => {
  if (location.href !== lastUrl) {
    lastUrl = location.href;
    
    // Se navegamos para uma página de grupos, extrair grupos
    if (location.href.includes('/groups/') || 
        location.href.includes('/groups_browse/')) {
      console.log('Navegação para página de grupos detectada');
      setTimeout(extractGroups, 3000);
    }
  }
}).observe(document, {subtree: true, childList: true});