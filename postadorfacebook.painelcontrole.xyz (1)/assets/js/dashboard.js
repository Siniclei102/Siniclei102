// Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Toggle de menu mobile
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Toggle de dropdown de notificações
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    if (notificationsDropdown) {
        notificationsDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
            
            // Fechar outros dropdowns
            document.querySelectorAll('.header-action').forEach(action => {
                if (action !== this) {
                    action.classList.remove('active');
                }
            });
        });
    }
    
    // Toggle de dropdown de usuário
    const userMenuDropdown = document.getElementById('userMenuDropdown');
    if (userMenuDropdown) {
        userMenuDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
            
            // Fechar outros dropdowns
            document.querySelectorAll('.header-action').forEach(action => {
                if (action !== this) {
                    action.classList.remove('active');
                }
            });
        });
    }
    
    // Fechar dropdowns ao clicar fora
    document.addEventListener('click', function() {
        document.querySelectorAll('.header-action').forEach(action => {
            action.classList.remove('active');
        });
    });
    
    // Marcar notificações como lidas
    const markAllRead = document.querySelector('.mark-all-read');
    if (markAllRead) {
        markAllRead.addEventListener('click', function() {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            // Atualizar badge
            const badge = document.querySelector('.notifications .badge');
            if (badge) {
                badge.style.display = 'none';
            }
        });
    }
    
    // Animações de entrada
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationDelay = '0s';
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);
    
    // Observar elementos para animação
    document.querySelectorAll('.stat-card, .dashboard-card').forEach(el => {
        observer.observe(el);
    });
    
    // Tooltip para ações rápidas
    document.querySelectorAll('[title]').forEach(el => {
        el.addEventListener('mouseenter', function() {
            // Implementar tooltip se necessário
        });
    });
    
    // Auto-refresh de dados (opcional)
    setInterval(function() {
        // Atualizar estatísticas em tempo real
        updateDashboardStats();
    }, 30000); // 30 segundos
    
    function updateDashboardStats() {
        // Implementar atualização via AJAX
        // fetch('/api/dashboard-stats')
        //     .then(response => response.json())
        //     .then(data => {
        //         // Atualizar números no dashboard
        //     });
    }
    
    // Busca em tempo real
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = this.value.trim();
                if (query.length > 2) {
                    // Implementar busca
                    performSearch(query);
                }
            }, 300);
        });
    }
    
    function performSearch(query) {
        // Implementar busca via AJAX
        console.log('Buscando:', query);
    }
});