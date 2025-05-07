<style>
    :root {
        --primary-color: #4e73df;
        --secondary-color: #2e59d9;
        --success-color: #1cc88a;
        --info-color: #36b9cc;
        --warning-color: #f6c23e;
        --danger-color: #e74a3b;
        --purple-color: #8540f5;
        --pink-color: #e83e8c;
        --orange-color: #fd7e14;
        --teal-color: #20c9a6;
        --light-color: #f8f9fc;
        --dark-color: #5a5c69;
        
        --sidebar-width: 250px;
        --topbar-height: 70px;
        --sidebar-collapsed-width: 70px;
    }
    
    * {
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Nunito', sans-serif;
        background-color: #f8f9fc;
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        min-height: 100vh;
        display: flex;
    }
    
    /* Layout Principal - Design de Painéis Laterais */
    .layout-wrapper {
        display: flex;
        width: 100%;
        overflow: hidden;
    }
    
    /* Sidebar Esquerda */
    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1030;
        background: linear-gradient(180deg, <?php echo $isAdmin ? '#222222 10%, #000000' : '#4e73df 10%, #224abe'; ?> 100%);
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        color: #fff;
        transition: all 0.3s ease;
        border-radius: 0 15px 15px 0; /* Bordas arredondadas do lado direito */
    }
    
    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
    }
    
    /* Logo e Branding */
    .sidebar-brand {
        height: var(--topbar-height);
        display: flex;
        align-items: center;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 0 15px 0 0; /* Borda superior direita arredondada */
    }
    
    .sidebar-brand img {
        height: 42px;
        margin-right: 0.8rem;
        transition: all 0.3s ease;
    }
    
    .sidebar-brand h2 {
        font-size: 1.2rem;
        margin: 0;
        color: white;
        font-weight: 700;
        white-space: nowrap;
        transition: opacity 0.3s ease;
    }
    
    .sidebar.collapsed .sidebar-brand h2 {
        opacity: 0;
        width: 0;
    }
    
    /* Admin Badge */
    .admin-badge {
        background-color: var(--danger-color);
        color: white;
        font-size: 0.7rem;
        padding: 0.15rem 0.5rem;
        border-radius: 20px;
        margin-left: 0.5rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: inline-block;
    }
    
    .sidebar.collapsed .admin-badge,
    .sidebar.mobile-visible .admin-badge {
        display: none;
    }
    
    /* Menu de Navegação */
    .sidebar-menu {
        padding: 1.5rem 0;
        list-style: none;
        margin: 0;
        overflow-y: auto;
        max-height: calc(100vh - var(--topbar-height));
    }
    
    .sidebar-menu a {
        display: flex;
        align-items: center;
        color: rgba(255, 255, 255, 0.8);
        padding: 0.8rem 1.5rem;
        text-decoration: none;
        transition: all 0.3s ease;
        font-weight: 600;
        border-radius: 0 50px 50px 0; /* Bordas arredondadas nos itens do menu */
        margin-right: 12px;
    }
    
    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        color: #fff;
        background: rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-menu i {
        margin-right: 0.8rem;
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
        transition: margin 0.3s ease;
    }
    
    .sidebar-menu span {
        white-space: nowrap;
        transition: opacity 0.3s ease;
    }
    
    .sidebar.collapsed .sidebar-menu span {
        opacity: 0;
        width: 0;
    }
    
    .sidebar.collapsed .sidebar-menu i {
        margin-right: 0;
        font-size: 1.2rem;
    }
    
    .menu-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin: 1rem 0;
    }
    
    .menu-header {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05rem;
        padding: 0.8rem 1.5rem;
        margin-top: 0.5rem;
        pointer-events: none;
    }
    
    .sidebar.collapsed .menu-header {
        opacity: 0;
        width: 0;
    }
    
    /* Conteúdo Principal */
    .content-wrapper {
        flex: 1;
        margin-left: var(--sidebar-width);
        transition: margin 0.3s ease;
        width: calc(100% - var(--sidebar-width));
        position: relative;
    }
    
    .content-wrapper.expanded {
        margin-left: var(--sidebar-collapsed-width);
        width: calc(100% - var(--sidebar-collapsed-width));
    }
    
    /* Barra de Topo */
    .topbar {
        height: var(--topbar-height);
        background-color: #fff;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        display: flex;
        align-items: center;
        padding: 0 1.5rem;
        position: sticky;
        top: 0;
        z-index: 1020;
        border-radius: 0 0 15px 15px; /* Bordas arredondadas na parte inferior */
    }
    
    .topbar-toggler {
        background: none;
        border: none;
        color: #333;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.25rem 0.75rem;
        border-radius: 0.25rem;
        margin-right: 1rem;
    }
    
    .topbar-toggler:hover {
        background-color: #f8f9fc;
    }
    
    /* Badge do Admin na topbar fixa e não no menu móvel */
    .topbar-admin-badge {
        display: inline-block;
        background-color: var(--danger-color);
        color: white;
        font-weight: 700;
        padding: 0.35rem 0.75rem;
        border-radius: 0.25rem;
        margin-right: 1rem;
    }
    
    .topbar-user {
        display: flex;
        align-items: center;
        margin-left: auto;
    }
    
    .topbar-user .dropdown-toggle {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #333;
        font-weight: 600;
    }
    
    .topbar-user .dropdown-toggle::after {
        display: none;
    }
    
    .topbar-user img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-left: 0.75rem;
        border: 2px solid #eaecf4;
    }
    
    /* Conteúdo da Página */
    .content {
        padding: 1.5rem;
    }
    
    /* Cards estilizados */
    .stat-card {
        position: relative;
        display: flex;
        flex-direction: column;
        min-width: 0;
        word-wrap: break-word;
        background-color: #fff;
        background-clip: border-box;
        border: 0;
        border-radius: 1rem; /* Bordas mais arredondadas */
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        margin-bottom: 1.5rem;
        transition: transform 0.2s ease-in-out;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-card .card-body {
        display: flex;
        padding: 1.25rem;
    }
    
    .stat-card .icon-container {
        width: 64px;
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 1rem; /* Bordas mais arredondadas */
        margin-right: 1rem;
    }
    
    .stat-card .icon-container i {
        font-size: 2rem;
        color: white;
    }
    
    .stat-card .card-content {
        flex: 1;
    }
    
    .stat-card .card-title {
        text-transform: uppercase;
        font-size: 0.7rem;
        font-weight: 700;
        color: #6e7d91;
        margin-bottom: 0.25rem;
        letter-spacing: 0.05rem;
    }
    
    .stat-card .card-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #333;
        margin-bottom: 0;
    }
    
    .stat-card .card-footer {
        background: transparent;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        padding: 0;
    }
    
    .stat-card .card-footer a {
        display: block;
        padding: 0.75rem;
        text-align: center;
        text-decoration: none;
        color: #6e7d91;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
        border-radius: 0 0 1rem 1rem; /* Bordas arredondadas no footer */
    }
    
    .stat-card .card-footer a:hover {
        background-color: #f8f9fc;
        color: var(--primary-color);
    }
    
    /* Estilos para responsividade */
    @media (max-width: 991.98px) {
        .sidebar {
            transform: translateX(-100%);
            z-index: 1040;
        }
        
        .sidebar.mobile-visible {
            transform: translateX(0);
        }
        
        .content-wrapper {
            margin-left: 0;
            width: 100%;
        }
        
        .content-wrapper.expanded {
            margin-left: 0;
            width: 100%;
        }
        
        /* Quando o menu mobile está ativo, escurece o resto da tela */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1035;
        }
        
        .overlay.active {
            display: block;
        }
    }
    
    @media (max-width: 767.98px) {
        .stat-card .card-body {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .stat-card .icon-container {
            margin-right: 0;
            margin-bottom: 0.75rem;
        }
    }
</style>