<!-- Adicionar este código ao arquivo /includes/menu.php antes do fechamento da navbar -->

<?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
    <!-- Dropdown de Administração -->
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-shield-alt me-1"></i> Administração
        </a>
        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
            <li>
                <a class="dropdown-item" href="/admin/index.php">
                    <i class="fas fa-home me-2"></i> Painel Admin
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/admin/dashboard.php">
                    <i class="fas fa-chart-line me-2"></i> Dashboard
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/admin/usuarios.php">
                    <i class="fas fa-users-cog me-2"></i> Usuários
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/admin/relatorio_validade.php">
                    <i class="fas fa-calendar-check me-2"></i> Validade de Contas
                </a>
            </li>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li>
                <a class="dropdown-item" href="/admin/backups.php">
                    <i class="fas fa-database me-2"></i> Backups
                </a>
            </li>
        </ul>
    </li>
<?php endif; ?>