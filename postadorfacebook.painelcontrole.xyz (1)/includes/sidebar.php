<nav class="sidebar" id="sidebar">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="sidebar-container">
        <!-- Header do Sidebar com novo design -->
        <div class="sidebar-header">
            <div class="brand-section">
                <div class="brand-logo">
                    <div class="logo-circle">
                        <i class="fab fa-facebook-f"></i>
                    </div>
                    <div class="brand-info">
                        <h3>PostGrupo</h3>
                        <span>Facebook Manager</span>
                    </div>
                </div>
                <button class="sidebar-close" id="sidebarClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Perfil do usuário -->
            <div class="user-profile">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($user['name'] ?? 'Siniclei102'); ?></h4>
                    <span class="user-role"><?php echo ucfirst($user['account_type'] ?? 'admin'); ?></span>
                </div>
                <div class="user-status online"></div>
            </div>
        </div>

        <!-- Menu de navegação -->
        <div class="sidebar-content">
            <div class="menu-section">
                <span class="section-title">Principal</span>
                <ul class="sidebar-menu">
                    <li class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <a href="dashboard.php">
                            <div class="menu-icon icon-gradient-1">
                                <i class="fas fa-home"></i>
                            </div>
                            <span class="menu-text">Dashboard</span>
                            <div class="menu-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </li>
                    
                    <li class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'posts.php' ? 'active' : ''; ?>">
                        <a href="posts.php">
                            <div class="menu-icon icon-gradient-2">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <span class="menu-text">Posts</span>
                            <?php if (($totalPosts ?? 0) > 0): ?>
                                <span class="menu-badge"><?php echo $totalPosts; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'groups.php' ? 'active' : ''; ?>">
                        <a href="groups.php">
                            <div class="menu-icon icon-gradient-3">
                                <i class="fab fa-facebook"></i>
                            </div>
                            <span class="menu-text">Grupos</span>
                            <?php if (($totalGroups ?? 0) > 0): ?>
                                <span class="menu-badge"><?php echo $totalGroups; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="schedule.php">
                            <div class="menu-icon icon-gradient-4">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <span class="menu-text">Agendamentos</span>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="analytics.php">
                            <div class="menu-icon icon-gradient-5">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <span class="menu-text">Relatórios</span>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="media.php">
                            <div class="menu-icon icon-gradient-6">
                                <i class="fas fa-images"></i>
                            </div>
                            <span class="menu-text">Mídia</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <?php if (($isAdmin ?? false)): ?>
            <div class="menu-section">
                <span class="section-title">Administração</span>
                <ul class="sidebar-menu">
                    <li class="menu-item">
                        <a href="users.php">
                            <div class="menu-icon icon-gradient-7">
                                <i class="fas fa-users"></i>
                            </div>
                            <span class="menu-text">Usuários</span>
                            <?php if (($totalUsers ?? 0) > 0): ?>
                                <span class="menu-badge"><?php echo $totalUsers; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="logs.php">
                            <div class="menu-icon icon-gradient-8">
                                <i class="fas fa-list-alt"></i>
                            </div>
                            <span class="menu-text">Logs</span>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="settings.php">
                            <div class="menu-icon icon-gradient-9">
                                <i class="fas fa-cog"></i>
                            </div>
                            <span class="menu-text">Configurações</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="menu-section">
                <span class="section-title">Suporte</span>
                <ul class="sidebar-menu">
                    <li class="menu-item">
                        <a href="help.php">
                            <div class="menu-icon icon-gradient-10">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <span class="menu-text">Ajuda</span>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="contact.php">
                            <div class="menu-icon icon-gradient-11">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <span class="menu-text">Contato</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Footer do Sidebar -->
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <div class="menu-icon icon-gradient-12">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span class="menu-text">Sair</span>
            </a>
            
            <div class="footer-info">
                <span>© 2025 PostGrupo</span>
                <span>v2.1.0</span>
            </div>
        </div>
    </div>
</nav>

<!-- Botão de menu mobile -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
</button>