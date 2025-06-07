// facebook-scraper.js - Funções especializadas para extrair dados do Facebook

// Métodos avançados para extrair dados do Facebook sem depender da API oficial
// Este arquivo contém técnicas específicas para navegar e extrair dados

// Função para extrair dados dos grupos do Facebook
function extractFacebookGroups() {
  return new Promise((resolve) => {
    // Array para armazenar grupos
    const groups = [];
    
    // Métodos de extração em ordem de preferência
    const extractionMethods = [
      extractFromLeftSidebar,
      extractFromGroupsPage,
      extractFromNavigation,
      extractFromDOM
    ];
    
    // Executar métodos de extração sequencialmente
    function runNextMethod(index) {
      if (index >= extractionMethods.length) {
        // Todos os métodos foram tentados, retornar resultados
        resolve(groups);
        return;
      }
      
      // Executar método atual
      try {
        const result = extractionMethods[index]();
        if (result && result.length > 0) {
          // Adicionar resultados ao array de grupos
          result.forEach(group => {
            // Verificar duplicatas
            if (!groups.some(g => g.id === group.id)) {
              groups.push(group);
            }
          });
        }
      } catch (error) {
        console.error(`Erro no método de extração ${index}:`, error);
      }
      
      // Executar próximo método
      runNextMethod(index + 1);
    }
    
    // Iniciar processo de extração
    runNextMethod(0);
  });
}

// Método 1: Extrair da barra lateral esquerda
function extractFromLeftSidebar() {
  const groups = [];
  
  // Encontrar links para grupos na barra lateral
  const sidebarLinks = document.querySelectorAll('[role="navigation"] a[href*="/groups/"]');
  
  sidebarLinks.forEach(link => {
    const groupData = parseGroupLink(link);
    if (groupData) {
      groups.push(groupData);
    }
  });
  
  return groups;
}

// Método 2: Extrair da página de grupos
function extractFromGroupsPage() {
  const groups = [];
  
  // Verificar se estamos na página de grupos
  if (!window.location.href.includes('/groups/')) {
    return groups;
  }
  
  // Encontrar cartões de grupo
  const groupCards = document.querySelectorAll('[role="main"] a[href*="/groups/"]');
  
  groupCards.forEach(card => {
    const groupData = parseGroupLink(card);
    if (groupData) {
      groups.push(groupData);
    }
  });
  
  return groups;
}

// Método 3: Extrair da navegação superior
function extractFromNavigation() {
  const groups = [];
  
  // Encontrar links para grupos na navegação superior
  const navLinks = document.querySelectorAll('[role="banner"] a[href*="/groups/"]');
  
  navLinks.forEach(link => {
    const groupData = parseGroupLink(link);
    if (groupData) {
      groups.push(groupData);
    }
  });
  
  return groups;
}

// Método 4: Varredura completa do DOM
function extractFromDOM() {
  const groups = [];
  
  // Encontrar todos os links para grupos
  const allLinks = document.querySelectorAll('a[href*="/groups/"]');
  
  allLinks.forEach(link => {
    const groupData = parseGroupLink(link);
    if (groupData) {
      groups.push(groupData);
    }
  });
  
  return groups;
}

// Função auxiliar para analisar link de grupo
function parseGroupLink(element) {
  try {
    const href = element.getAttribute('href');
    const match = href.match(/\/groups\/([^\/\?]+)/);
    
    if (!match) return null;
    
    const id = match[1];
    
    // Ignorar links inválidos
    if (!id || id === 'feed' || id === 'discover' || id === 'create' || id === 'join') {
      return null;
    }
    
    // Obter nome do grupo
    let name = '';
    
    // Tentar várias maneiras de obter o nome
    if (element.getAttribute('aria-label')) {
      name = element.getAttribute('aria-label');
    } else if (element.title) {
      name = element.title;
    } else {
      const textNode = element.querySelector('span') || element;
      name = textNode.textContent.trim();
    }
    
    // Verificar se o nome é válido
    if (!name || name.length < 2) {
      return null;
    }
    
    // Limpar nome (remover "Grupo:" ou outros prefixos)
    name = name.replace(/^(Grupo:|Group:)\s*/i, '');
    
    return {
      id: id,
      name: name,
      url: href.startsWith('http') ? href : `https://www.facebook.com${href}`
    };
  } catch (error) {
    console.error('Erro ao analisar link de grupo:', error);
    return null;
  }
}

// Função para injetar botão de postagem em página de grupo
function injectPostButton(message) {
  try {
    // Verificar se estamos em uma página de grupo
    if (!window.location.href.includes('/groups/')) {
      return false;
    }
    
    // Encontrar campo de postagem
    const postField = document.querySelector('[role="button"][aria-label*="post"]') ||
                     document.querySelector('[role="button"][aria-label*="publicação"]') ||
                     document.querySelector('[contenteditable="true"][aria-label*="Mind"]');
    
    if (!postField) {
      console.log('Campo de postagem não encontrado');
      return false;
    }
    
    // Clicar no campo para abrir o compositor
    postField.click();
    
    // Aguardar abertura do compositor
    setTimeout(() => {
      // Encontrar área de texto
      const textArea = document.querySelector('[contenteditable="true"]');
      
      if (textArea) {
        // Preencher com a mensagem
        textArea.textContent = message;
        
        // Simular eventos para ativar o botão de publicar
        const events = ['input', 'change', 'blur', 'focus'];
        events.forEach(event => {
          textArea.dispatchEvent(new Event(event, { bubbles: true }));
        });
        
        // Aguardar e clicar no botão de publicar
        setTimeout(() => {
          const publishButton = document.querySelector('[aria-label="Post"]') ||
                              document.querySelector('[aria-label="Publicar"]');
          
          if (publishButton) {
            publishButton.click();
            return true;
          } else {
            console.log('Botão de publicar não encontrado');
            return false;
          }
        }, 1000);
      } else {
        console.log('Área de texto não encontrada');
        return false;
      }
    }, 1000);
    
  } catch (error) {
    console.error('Erro ao injetar botão de postagem:', error);
    return false;
  }
}

// Exportar funções para uso no content.js
window.facebookScraper = {
  extractGroups: extractFacebookGroups,
  injectPostButton: injectPostButton
};