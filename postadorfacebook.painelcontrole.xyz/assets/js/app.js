// Script para Menu Moderno e UI Interativa

document.addEventListener('DOMContentLoaded', function() {
    // Toggle do menu lateral
    const sidebarToggle = document.getElementById('sidebarToggle');
    const body = document.body;
    
    if(sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            body.classList.toggle('sidebar-collapsed');
            
            // Salvar estado do menu no localStorage
            localStorage.setItem('sidebarState', body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
        });
    }
    
    // Verificar estado salvo do menu
    const sidebarState = localStorage.getItem('sidebarState');
    if(sidebarState === 'collapsed') {
        body.classList.add('sidebar-collapsed');
    }
    
    // Responsividade do menu para dispositivos móveis
    const menuToggle = document.querySelector('.menu-toggle');
    if(menuToggle) {
        menuToggle.addEventListener('click', function() {
            body.classList.toggle('sidebar-active');
        });
    }
    
    // Fechar menu ao clicar fora em dispositivos móveis
    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const menuToggle = document.querySelector('.menu-toggle');
        
        if(sidebar && menuToggle) {
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target) && body.classList.contains('sidebar-active')) {
                body.classList.remove('sidebar-active');
            }
        }
    });
    
    // Tooltip Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    if(typeof bootstrap !== 'undefined') {
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Popover Bootstrap
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    if(typeof bootstrap !== 'undefined') {
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }
    
    // Efeitos de hover em cards
    const cards = document.querySelectorAll('.modern-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('card-hover');
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('card-hover');
        });
    });
    
    // Animação suave de scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if(targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if(targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Contador de caracteres para áreas de texto
    const textareas = document.querySelectorAll('textarea[data-max-chars]');
    textareas.forEach(textarea => {
        const maxChars = textarea.getAttribute('data-max-chars');
        const counterElement = document.createElement('div');
        counterElement.className = 'char-counter text-muted small mt-1';
        counterElement.textContent = `0/${maxChars} caracteres`;
        
        textarea.parentNode.appendChild(counterElement);
        
        textarea.addEventListener('input', function() {
            const remaining = this.value.length;
            counterElement.textContent = `${remaining}/${maxChars} caracteres`;
            
            if(remaining > maxChars) {
                counterElement.classList.add('text-danger');
            } else {
                counterElement.classList.remove('text-danger');
            }
        });
    });
    
    // Verificar atualizações de notificações (simulado)
    function checkNotifications() {
        // Essa função seria implementada com AJAX para verificar notificações do servidor
        console.log('Verificando notificações...');
        
        // Simulação de notificação a cada 60 segundos
        setTimeout(checkNotifications, 60000);
    }
    
    // Iniciar verificação de notificações
    checkNotifications();
    
    // Feedback visual para botões
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            this.classList.add('btn-active');
            
            setTimeout(() => {
                this.classList.remove('btn-active');
            }, 200);
        });
    });
});

// Função para alternar tema claro/escuro
function toggleTheme() {
    const body = document.body;
    body.classList.toggle('dark-theme');
    
    // Salvar preferência do usuário
    const isDarkTheme = body.classList.contains('dark-theme');
    localStorage.setItem('darkTheme', isDarkTheme);
}

// Verificar tema preferido do usuário
if(localStorage.getItem('darkTheme') === 'true') {
    document.body.classList.add('dark-theme');
}