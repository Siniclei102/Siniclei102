<header class="main-header">
    <div class="header-left">
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="breadcrumb">
            <span class="breadcrumb-item">
                <i class="fas fa-home"></i>
                <?php echo $pageTitle; ?>
            </span>
        </div>
    </div>
    
    <div class="header-center">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Pesquisar posts, grupos ou usuários...">
        </div>
    </div>
    
    <div class="header-right">
        <div class="header-actions">
            <!-- Notificações -->
            <div class="header-action notifications" id="notificationsDropdown">
                <button class="action-btn">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </button>
                <div class="dropdown-menu">
                    <div class="dropdown-header">
                        <h4>Notificações</h4>
                        <span class="mark-all-read">Marcar todas como lidas</span>
                    </div>
                    <div class="dropdown-content">
                        <div class="notification-item unread">
                            <div class="notification-icon success">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="notification-content">
                                <p>Post enviado com sucesso para 15 grupos</p>
                                <span class="notification-time">5 min atrás</span>
                            </div>
                        </div>
                        <div class="notification-item unread">
                            <div class="notification-icon warning">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="notification-content">
                                <p>Falha ao enviar para 2 grupos</p>
                                <span class="notification-time">10 min atrás</span>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div class="notification-icon info">
                                <i class="fas fa-info"></i>
                            </div>
                            <div class="notification-content">
                                <p>Novo grupo adicionado à lista</p>
                                <span class="notification-time">1 hora atrás</span>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown-footer">
                        <a href="notifications.php">Ver todas as notificações</a>
                    </div>
                </div>
            </div>
            
            <!-- Novo Post -->
            <div class="header-action">
                <a href="new-post.php" class="action-btn primary" title="Novo Post">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            
            <!-- Perfil do Usuário -->
            <div class="header-action user-menu" id="userMenuDropdown">
                <button class="user-btn">
                    <div class="user-avatar-small">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu">
                    <div class="user-info-dropdown">
                        <div class="user-avatar-large">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="user-details">
                            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                            <span class="user-role-badge"><?php echo ucfirst($user['account_type']); ?></span>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i>
                        Meu Perfil
                    </a>
                    <a href="account.php" class="dropdown-item">
                        <i class="fas fa-cog"></i>
                        Configurações da Conta
                    </a>
                    <a href="billing.php" class="dropdown-item">
                        <i class="fas fa-credit-card"></i>
                        Faturamento
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="help.php" class="dropdown-item">
                        <i class="fas fa-question-circle"></i>
                        Ajuda
                    </a>
                    <a href="logout.php" class="dropdown-item logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>