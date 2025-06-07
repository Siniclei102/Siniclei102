/**
 * Script principal para todas as páginas
 */
document.addEventListener('DOMContentLoaded', function() {
    // Manipulação do menu lateral
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    function toggleSidebar() {
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
    }
    
    // Fechar sidebar ao clicar fora em dispositivos móveis
    document.addEventListener('click', function(event) {
        const isClickInsideSidebar = sidebar && sidebar.contains(event.target);
        const isClickOnToggle = sidebarToggle && sidebarToggle.contains(event.target) || 
                               mobileSidebarToggle && mobileSidebarToggle.contains(event.target);
        
        if (window.innerWidth <= 992 && !isClickInsideSidebar && !isClickOnToggle && sidebar && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
        }
    });
    
    // Manipulação de dropdowns
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parent = this.closest('.dropdown');
            const menu = parent.querySelector('.dropdown-menu');
            
            if (menu) {
                menu.classList.toggle('show');
            }
        });
    });
    
    // Fechar dropdowns ao clicar fora
    document.addEventListener('click', function(event) {
        const dropdowns = document.querySelectorAll('.dropdown-menu.show');
        
        dropdowns.forEach(dropdown => {
            if (!dropdown.parentElement.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    });
    
    // Manipulação do dropdown de usuário
    const userDropdownToggle = document.getElementById('userDropdownToggle');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    if (userDropdownToggle && userDropdownMenu) {
        userDropdownToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            userDropdownMenu.classList.toggle('show');
        });
    }
    
    // Manipulação de modais
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const modalCloses = document.querySelectorAll('[data-dismiss="modal"]');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            
            const modalId = this.dataset.modal;
            const modal = document.getElementById(modalId);
            
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        });
    });
    
    modalCloses.forEach(close => {
        close.addEventListener('click', function() {
            const modal = this.closest('.modal');
            
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Fechar modal ao clicar no backdrop
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(modal => {
        const backdrop = modal.querySelector('.modal-backdrop');
        
        if (backdrop) {
            backdrop.addEventListener('click', function() {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            });
        }
    });
    
    // Manipulação de alertas descartáveis
    const dismissibleAlerts = document.querySelectorAll('.alert-dismissible');
    
    dismissibleAlerts.forEach(alert => {
        const closeBtn = alert.querySelector('.alert-close');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                alert.remove();
            });
        }
    });
    
    // Auto-ocultar alertas de sucesso após 5 segundos
    const successAlerts = document.querySelectorAll('.alert.success');
    
    successAlerts.forEach(alert => {
        setTimeout(() => {
            if (alert.classList.contains('alert-dismissible')) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    });
    
    // Verificar estado da assinatura periodicamente
    checkSubscriptionStatus();
    
    function checkSubscriptionStatus() {
        fetch('/api/check-subscription', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'expired') {
                // Mostrar overlay de conta suspensa
                showSuspendedOverlay(data.message, data.expiryDate);
            } else if (data.status === 'expiring_soon' && !localStorage.getItem('expiry_notice_shown')) {
                // Mostrar notificação de expiração próxima
                showExpiryNotice(data.message, data.daysRemaining);
                localStorage.setItem('expiry_notice_shown', 'true');
                
                // Limpar após 24 horas para mostrar novamente
                setTimeout(() => {
                    localStorage.removeItem('expiry_notice_shown');
                }, 24 * 60 * 60 * 1000);
            }
        })
        .catch(error => {
            console.error('Erro ao verificar status da assinatura:', error);
        });
    }
    
    function showSuspendedOverlay(message, expiryDate) {
        // Criar overlay dinâmico se não existir no DOM
        if (!document.querySelector('.suspended-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'suspended-overlay';
            
            overlay.innerHTML = `
                <div class="suspended-box animate-slide">
                    <div class="suspended-icon">
                        <i class="fas fa-user-lock"></i>
                    </div>
                    <h2 class="suspended-title">Conta Suspensa</h2>
                    <p class="suspended-message">
                        ${message || 'Sua assinatura expirou. Entre em contato com o administrador para renovar.'}
                    </p>
                    <a href="/logout" class="btn btn-primary suspended-action">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            `;
            
            document.body.appendChild(overlay);
        }
    }
    
    function showExpiryNotice(message, daysRemaining) {
        // Criar notificação de expiração
        const notice = document.createElement('div');
        notice.className = 'expiry-notice animate-slide';
        
        notice.innerHTML = `
            <div class="expiry-notice-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="expiry-notice-content">
                <h3 class="expiry-notice-title">Assinatura Expirando</h3>
                <p class="expiry-notice-message">
                    ${message || `Sua assinatura expira em ${daysRemaining} dias. Entre em contato para renovar.`}
                </p>
                <div class="expiry-notice-actions">
                    <a href="/contact" class="btn btn-sm btn-primary">Renovar</a>
                    <button class="btn btn-sm btn-outline expiry-notice-close">Fechar</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(notice);
        
        // Adicionar evento para fechar
        const closeBtn = notice.querySelector('.expiry-notice-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                notice.style.opacity = '0';
                setTimeout(() => notice.remove(), 300);
            });
        }
        
        // Auto-fechar após 1 minuto
        setTimeout(() => {
            notice.style.opacity = '0';
            setTimeout(() => notice.remove(), 300);
        }, 60000);
    }
    
    // Funções auxiliares
    window.formatNumber = function(number) {
        return new Intl.NumberFormat().format(number);
    };
    
    window.formatDate = function(dateString) {
        if (!dateString) return 'N/A';
        
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    };
    
    window.formatDateTime = function(dateString) {
        if (!dateString) return 'N/A';
        
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };
});