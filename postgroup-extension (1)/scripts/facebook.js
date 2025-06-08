// ============================================
// PostGroup - Facebook API Helper
// Funções auxiliares para interagir com o FB
// ============================================

// Funções para interagir com a interface do Facebook
const FacebookAPI = {
  // Detectar se o usuário está logado
  isLoggedIn: function() {
    return new Promise((resolve) => {
      chrome.cookies.get({url: 'https://www.facebook.com', name: 'c_user'}, function(cookie) {
        resolve(!!cookie);
      });
    });
  },
  
  // Obter ID do usuário
  getUserId: function() {
    return new Promise((resolve) => {
      chrome.cookies.get({url: 'https://www.facebook.com', name: 'c_user'}, function(cookie) {
        resolve(cookie ? cookie.value : null);
      });
    });
  },
  
  // Detectar nome de usuário na página
  getUserName: function(document) {
    const possibleSelectors = [
      'div[role="navigation"] span[dir="auto"]',
      'a[aria-label*="perfil"] span',
      'a[href*="/profile.php"] span'
    ];
    
    for (const selector of possibleSelectors) {
      const element = document.querySelector(selector);
      if (element && element.textContent.trim().length > 0) {
        return element.textContent.trim();
      }
    }
    
    return null;
  },
  
  // Detectar foto de perfil na página
  getProfilePicture: function(document) {
    const possibleSelectors = [
      'image[xlink:href]',
      'a[aria-label*="perfil"] img',
      'a[href*="/profile.php"] img'
    ];
    
    for (const selector of possibleSelectors) {
      const element = document.querySelector(selector);
      if (element) {
        return element.getAttribute('xlink:href') || element.src;
      }
    }
    
    return null;
  },
  
  // Criar publicação em um grupo
  createPost: async function(document, content) {
    // Clicar no campo "Criar publicação"
    const createPostButtons = Array.from(document.querySelectorAll('[role="button"]'));
    const createPostButton = createPostButtons.find(el => 
      el.textContent.includes('Criar publicação') || 
      el.textContent.includes('Escrever algo...') || 
      el.textContent.includes('No que você está pensando') ||
      el.textContent.includes('Publicar')
    );
    
    if (!createPostButton) {
      throw new Error('Campo de publicação não encontrado');
    }
    
    createPostButton.click();
    
    // Aguardar abertura do modal
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Encontrar campo de texto
    const postTextField = document.querySelector('[contenteditable="true"][role="textbox"]');
    if (!postTextField) {
      throw new Error('Campo de texto não encontrado');
    }
    
    // Inserir texto
    postTextField.focus();
    document.execCommand('insertText', false, content.text);
    
    // Adicionar mídia se houver
    if (content.imageUrl) {
      // TODO: Implementar adição de imagem
    }
    
    // Aguardar um pouco para garantir que o texto foi inserido
    await new Promise(resolve => setTimeout(resolve, 1500));
    
    // Clicar em "Publicar"
    const publishButtons = Array.from(document.querySelectorAll('[role="button"]'));
    const publishButton = publishButtons.find(el => 
      el.textContent.includes('Publicar') || 
      el.textContent.includes('Compartilhar')
    );
    
    if (!publishButton) {
      throw new Error('Botão de publicação não encontrado');
    }
    
    publishButton.click();
    
    // Aguardar conclusão da postagem
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    return true;
  },
  
  // Extrair grupos da página
  extractGroups: function(document) {
    const groups = [];
    const groupLinks = document.querySelectorAll('a[href*="/groups/"]');
    
    groupLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (!href || href.includes('/hashtag/') || href.includes('/help') || href.includes('marketplace')) {
        return;
      }
      
      const match = href.match(/\/groups\/(\d+)/);
      if (!match) return;
      
      const groupId = match[1];
      if (groups.some(g => g.id === groupId)) return;
      
      const nameElement = link.querySelector('span') || link;
      const imageElement = link.closest('div')?.querySelector('img');
      
      const name = nameElement.textContent.trim();
      if (!name || name.length < 2) return;
      
      const parentCard = link.closest('div[role="listitem"]') || link.closest('div[role="none"]');
      let memberCount = '';
      let privacy = 'Desconhecido';
      
      if (parentCard) {
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
        name: name,
        image: imageElement ? imageElement.src : '',
        memberCount: memberCount,
        privacy: privacy,
        url: `https://www.facebook.com/groups/${groupId}`
      });
    });
    
    return groups;
  }
};

// Exportar para uso em outros scripts
if (typeof module !== 'undefined') {
  module.exports = FacebookAPI;
}