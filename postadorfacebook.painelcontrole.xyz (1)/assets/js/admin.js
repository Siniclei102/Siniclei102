/**
 * Script para páginas de administração
 */
document.addEventListener('DOMContentLoaded', function() {
    // Manipulação da tabela de usuários
    const usersTable = document.getElementById('usersTable');
    if (usersTable) {
        setupUserTable();
    }
    
    // Manipulação de filtros de usuários
    const filterUsers = document.getElementById('filterUsers');
    if (filterUsers) {
        setupUserFilters();
    }
    
    // Manipulação do gráfico de estatísticas
    const statsChart = document.getElementById('statsChart');
    if (statsChart) {
        setupStatsChart();
    }
    
    // Manipulação de renovação de assinatura
    setupSubscriptionRenewal();
    
    // Manipulação de exclusão de usuário
    setupUserDeletion();
    
    // Manipulação de suspensão/ativação de usuário
    setupUserStatusToggle();
});

/**
 * Configurar tabela de usuários
 */
function setupUserTable() {
    // Ordenação de colunas
    const tableHeaders = document.querySelectorAll('#usersTable th[data-sort]');
    
    tableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortField = this.dataset.sort;
            const currentOrder = this.dataset.order || 'asc';
            const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            
            // Atualizar indicador de ordenação
            tableHeaders.forEach(h => {
                h.dataset.order = '';
                h.querySelector('.sort-icon')?.remove();
            });
            
            this.dataset.order = newOrder;
            this.innerHTML += `<i class="fas fa-chevron-${newOrder === 'asc' ? 'up' : 'down'} sort-icon"></i>`;
            
            // Ordenar tabela
            sortTable(sortField, newOrder);
        });
    });
}

/**
 * Ordenar tabela de usuários
 */
function sortTable(field, order) {
    const table = document.getElementById('usersTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Ordenar linhas
    rows.sort((a, b) => {
        let aValue = a.querySelector(`[data-${field}]`)?.dataset[field] || '';
        let bValue = b.querySelector(`[data-${field}]`)?.dataset[field] || '';
        
        // Converter para número se possível
        if (!isNaN(aValue) && !isNaN(bValue)) {
            aValue = parseFloat(aValue);
            bValue = parseFloat(bValue);
        }
        
        // Comparar valores
        if (aValue < bValue) {
            return order === 'asc' ? -1 : 1;
        } else if (aValue > bValue) {
            return order === 'asc' ? 1 : -1;
        }
        return 0;
    });
    
    // Reordenar linhas na tabela
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Configurar filtros de usuários
 */
function setupUserFilters() {
    const filterBtn = document.getElementById('filterUsers');
    const resetBtn = document.getElementById('resetFilters');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchUser');
    
    // Aplicar filtros
    filterBtn.addEventListener('click', function() {
        filterUsers();
    });
    
    // Limpar filtros
    resetBtn.addEventListener('click', function() {
        statusFilter.value = '';
        searchInput.value = '';
        filterUsers();
    });
    
    // Filtrar também ao pressionar Enter
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            filterUsers();
        }
    });
    
    // Função para filtrar usuários
    function filterUsers() {
        const status = statusFilter.value;
        const search = searchInput.value.toLowerCase();
        const rows = document.querySelectorAll('#usersTable tbody tr');
        
        rows.forEach(row => {
            let showRow = true;
            
            // Filtrar por status
            if (status) {
                if (status === 'active' && row.dataset.status !== 'active') {
                    showRow = false;
                } else if (status === 'suspended' && row.dataset.status !== 'suspended') {
                    showRow = false;
                } else if (status === 'expiring' && row.dataset.expiring !== 'true') {
                    showRow = false;
                }
            }
            
            // Filtrar por termo de busca
            if (showRow && search) {
                const username = row.querySelector('.user-username').textContent.toLowerCase();
                const name = row.querySelector('.user-name').textContent.toLowerCase();
                const email = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                
                if (!username.includes(search) && !name.includes(search) && !email.includes(search)) {
                    showRow = false;
                }
            }
            
            // Mostrar ou ocultar linha
            row.style.display = showRow ? '' : 'none';
        });
        
        // Mostrar mensagem quando não há resultados
        const noResultsRow = document.getElementById('noResultsRow');
        const visibleRows = document.querySelectorAll('#usersTable tbody tr:not([style*="display: none"])');
        
        if (visibleRows.length === 0) {
            if (!noResultsRow) {
                const tbody = document.querySelector('#usersTable tbody');
                const tr = document.createElement('tr');
                tr.id = 'noResultsRow';
                tr.innerHTML = '<td colspan="9" class="text-center py-3">Nenhum usuário corresponde aos filtros aplicados.</td>';
                tbody.appendChild(tr);
            } else {
                noResultsRow.style.display = '';
            }
        } else if (noResultsRow) {
            noResultsRow.style.display = 'none';
        }
    }
}

/**
 * Configurar renovação de assinatura
 */
function setupSubscriptionRenewal() {
    // Configurar botões de renovação
    const renewButtons = document.querySelectorAll('.renew-subscription');
    const renewModal = document.getElementById('renewSubscriptionModal');
    const renewForm = document.getElementById('renewSubscriptionForm');
    const renewUserId = document.getElementById('renewUserId');
    const confirmRenew = document.getElementById('confirmRenew');
    
    if (!renewButtons.length || !renewModal || !confirmRenew) return;
    
    // Abrir modal de renovação
    renewButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const userId = this.dataset.userId;
            const userName = this.dataset.userName || 'este usuário';
            
            renewUserId.value = userId;
            document.querySelector('#renewSubscriptionModal .modal-title').innerText = `Renovar Assinatura: ${userName}`;
            
            // Mostrar modal
            renewModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
    });
    
    // Confirmar renovação
    confirmRenew.addEventListener('click', function() {
        const userId = renewUserId.value;
        const days = document.getElementById('renewDays').value;
        const notes = document.getElementById('renewNotes').value;
        
        if (!userId || !days) {
            alert('Dados incompletos para renovação.');
            return;
        }
        
        // Enviar requisição
        fetch(`/admin/users/${userId}/renew`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                days: days,
                notes: notes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fechar modal
                renewModal.classList.remove('show');
                document.body.style.overflow = '';
                
                // Mostrar mensagem de sucesso
                showNotification(data.message, 'success');
                
                // Recarregar página após 1 segundo
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification(data.error, 'error');
            }
        })
        .catch(error => {
            showNotification('Erro ao processar renovação. Tente novamente.', 'error');
            console.error('Erro:', error);
        });
    });
}

/**
 * Configurar exclusão de usuário
 */
function setupUserDeletion() {
    // Configurar botões de exclusão
    const deleteButtons = document.querySelectorAll('.delete-user');
    const deleteModal = document.getElementById('deleteUserModal');
    const confirmDelete = document.getElementById('confirmDelete');
    
    if (!deleteButtons.length || !deleteModal || !confirmDelete) return;
    
    let currentUserId = null;
    
    // Abrir modal de exclusão
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            currentUserId = this.dataset.userId;
            const userName = this.closest('tr').querySelector('.user-name').textContent;
            
            document.querySelector('#deleteUserModal .modal-title').innerText = `Excluir Usuário: ${userName}`;
            
            // Mostrar modal
            deleteModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
    });
    
    // Confirmar exclusão
    confirmDelete.addEventListener('click', function() {
        if (!currentUserId) return;
        
        // Enviar requisição
        fetch(`/admin/users/${currentUserId}/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fechar modal
                deleteModal.classList.remove('show');
                document.body.style.overflow = '';
                
                // Mostrar mensagem de sucesso
                showNotification(data.message, 'success');
                
                // Remover linha da tabela
                const row = document.querySelector(`tr[data-user-id="${currentUserId}"]`);
                if (row) {
                    row.remove();
                }
                
                // Atualizar contadores
                updateCounters();
            } else {
                showNotification(data.error, 'error');
            }
        })
        .catch(error => {
            showNotification('Erro ao excluir usuário. Tente novamente.', 'error');
            console.error('Erro:', error);
        });
    });
}

/**
 * Configurar alternância de status do usuário (suspender/ativar)
 */
function setupUserStatusToggle() {
    // Configurar botões de suspensão
    const suspendButtons = document.querySelectorAll('.suspend-user');
    
    suspendButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const userId = this.dataset.userId;
            const row = this.closest('tr');
            const userName = row.querySelector('.user-name').textContent;
            
            if (confirm(`Tem certeza que deseja suspender o usuário ${userName}?`)) {
                // Enviar requisição
                fetch(`/admin/users/${userId}/suspend`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Mostrar mensagem de sucesso
                        showNotification(data.message, 'success');
                        
                        // Atualizar linha da tabela
                        row.dataset.status = 'suspended';
                        
                        // Atualizar badge de status
                        const statusBadge = row.querySelector('.status-badge');
                        statusBadge.className = 'status-badge danger';
                        statusBadge.textContent = 'Suspenso';
                        
                        // Atualizar botão
                        const buttonCell = this.parentElement.parentElement;
                        buttonCell.innerHTML = `
                            <a href="/admin/users/edit/${userId}" class="btn btn-sm btn-info">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="btn btn-sm btn-success activate-user" data-user-id="${userId}">
                                <i class="fas fa-user-check"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-user" data-user-id="${userId}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        `;
                        
                        // Reconfigurar botões
                        setupUserStatusToggle();
                        setupUserDeletion();
                        
                        // Atualizar contadores
                        updateCounters();
                    } else {
                        showNotification(data.error, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Erro ao suspender usuário. Tente novamente.', 'error');
                    console.error('Erro:', error);
                });
            }
        });
    });
    
    // Configurar botões de ativação
    const activateButtons = document.querySelectorAll('.activate-user');
    
    activateButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const userId = this.dataset.userId;
            const row = this.closest('tr');
            const userName = row.querySelector('.user-name').textContent;
            
            if (confirm(`Tem certeza que deseja ativar o usuário ${userName}?`)) {
                // Enviar requisição
                fetch(`/admin/users/${userId}/activate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Mostrar mensagem de sucesso
                        showNotification(data.message, 'success');
                        
                        // Atualizar linha da tabela
                        row.dataset.status = 'active';
                        
                        // Atualizar badge de status
                        const statusBadge = row.querySelector('.status-badge');
                        statusBadge.className = 'status-badge success';
                        statusBadge.textContent = 'Ativo';
                        
                        // Atualizar botão
                        const buttonCell = this.parentElement.parentElement;
                        buttonCell.innerHTML = `
                            <a href="/admin/users/edit/${userId}" class="btn btn-sm btn-info">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="btn btn-sm btn-warning suspend-user" data-user-id="${userId}">
                                <i class="fas fa-user-lock"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-user" data-user-id="${userId}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        `;
                        
                        // Reconfigurar botões
                        setupUserStatusToggle();
                        setupUserDeletion();
                        
                        // Atualizar contadores
                        updateCounters();
                    } else {
                        showNotification(data.error, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Erro ao ativar usuário. Tente novamente.', 'error');
                    console.error('Erro:', error);
                });
            }
        });
    });
}

/**
 * Atualizar contadores de usuários
 */
function updateCounters() {
    // Contar usuários por status
    const totalUsers = document.querySelectorAll('#usersTable tbody tr').length;
    const activeUsers = document.querySelectorAll('#usersTable tbody tr[data-status="active"]').length;
    const suspendedUsers = document.querySelectorAll('#usersTable tbody tr[data-status="suspended"]').length;
    const expiringUsers = document.querySelectorAll('#usersTable tbody tr[data-expiring="true"]').length;
    
    // Atualizar contadores na interface
    document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = totalUsers;
    document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = activeUsers;
    document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = expiringUsers;
    document.querySelector('.stat-card:nth-child(4) .stat-value').textContent = suspendedUsers;
}

/**
 * Mostrar notificação
 */
function showNotification(message, type) {
    // Verificar se já existe uma notificação
    let notification = document.querySelector('.floating-notification');
    
    if (!notification) {
        // Criar nova notificação
        notification = document.createElement('div');
        notification.className = `floating-notification ${type}`;
        document.body.appendChild(notification);
    } else {
        // Atualizar classe de tipo
        notification.className = `floating-notification ${type}`;
    }
    
    // Definir conteúdo
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        </div>
        <div class="notification-content">${message}</div>
    `;
    
    // Mostrar notificação
    notification.classList.add('show');
    
    // Esconder após 3 segundos
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}