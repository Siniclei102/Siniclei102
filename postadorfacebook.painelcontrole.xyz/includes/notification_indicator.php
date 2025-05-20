<!-- Adicionar ao menu.php antes do ícone de perfil do usuário -->
<?php
// Obter notificações não lidas
require_once 'classes/Notification.php';
$notificationObj = new Notification($db);
$notificacoesNaoLidas = $notificationObj->contarNaoLidas($_SESSION['user_id']);
?>

<li class="nav-item dropdown me-2">
    <a class="nav-link" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <?php if ($notificacoesNaoLidas > 0): ?>
            <span class="notification-indicator"><?php echo $notificacoesNaoLidas > 9 ? '9+' : $notificacoesNaoLidas; ?></span>
        <?php endif; ?>
    </a>
    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
        <div class="notification-header">
            <h6 class="dropdown-header">Notificações</h6>
            <?php if ($notificacoesNaoLidas > 0): ?>
                <a href="notificacoes.php?marcar_todas_lidas=1" class="notification-mark-all">Marcar todas como lidas</a>
            <?php endif; ?>
        </div>
        
        <?php
        $notificacoesRecentes = $notificationObj->obterRecentes($_SESSION['user_id']);
        if (count($notificacoesRecentes) > 0):
        ?>
            <div class="notification-list-dropdown">
                <?php foreach ($notificacoesRecentes as $notif): ?>
                    <?php
                    $classeTipo = 'default';
                    $icone = 'bell';
                    
                    if ($notif['tipo'] === 'sistema') {
                        $classeTipo = 'system';
                        $icone = 'cogs';
                    } elseif ($notif['tipo'] === 'campanha') {
                        $classeTipo = 'campaign';
                        $icone = 'bullhorn';
                    } elseif ($notif['tipo'] === 'conta') {
                        $classeTipo = 'account';
                        $icone = 'user-shield';
                    }
                    ?>
                    <li>
                        <a class="dropdown-item notification-item <?php echo $notif['lida'] ? '' : 'unread'; ?>" href="<?php echo !empty($notif['link']) ? htmlspecialchars($notif['link']) : 'notificacoes.php?marcar_lida=' . $notif['id']; ?>">
                            <div class="notification-icon-small <?php echo $classeTipo; ?>">
                                <i class="fas fa-<?php echo $icone; ?>"></i>
                            </div>
                            <div class="notification-content-small">
                                <div class="notification-title-small"><?php echo htmlspecialchars($notif['titulo']); ?></div>
                                <div class="notification-text-small"><?php echo htmlspecialchars(substr($notif['mensagem'], 0, 60) . (strlen($notif['mensagem']) > 60 ? '...' : '')); ?></div>
                                <div class="notification-time-small"><?php echo time_elapsed_string($notif['criado_em']); ?></div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </div>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center" href="notificacoes.php">Ver todas notificações</a></li>
        <?php else: ?>
            <li>
                <div class="dropdown-item notification-empty-small">
                    <i class="fas fa-bell-slash me-2"></i> Nenhuma notificação
                </div>
            </li>
        <?php endif; ?>
    </ul>
</li>

<style>
/* Ícone de notificação e contador */
.notification-indicator {
    position: absolute;
    top: 0;
    right: 0;
    background-color: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

/* Dropdown de notificações */
.notification-dropdown {
    width: 320px;
    padding: 0;
    box-shadow: 0 5px 25px rgba(0,0,0,0.1);
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 1rem;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.notification-mark-all {
    font-size: 0.75rem;
    color: var(--bs-primary);
    text-decoration: none;
}

.notification-list-dropdown {
    max-height: 300px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    padding: 10px 15px;
    transition: background-color 0.2s;
    border-left: 3px solid transparent;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: rgba(13, 110, 253, 0.05);
    border-left: 3px solid #007bff;
}

.notification-icon-small {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    margin-right: 12px;
    flex-shrink: 0;
    background-color: #e9ecef;
    color: #6c757d;
}

.notification-icon-small.system {
    background-color: rgba(155, 89, 182, 0.15);
    color: #9b59b6;
}

.notification-icon-small.campaign {
    background-color: rgba(243, 156, 18, 0.15);
    color: #f39c12;
}

.notification-icon-small.account {
    background-color: rgba(46, 204, 113, 0.15);
    color: #2ecc71;
}

.notification-content-small {
    flex: 1;
    min-width: 0;
}

.notification-title-small {
    font-weight: 600;
    font-size: 0.825rem;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notification-text-small {
    font-size: 0.75rem;
    color: #6c757d;
    margin-bottom: 2px;
}

.notification-time-small {
    font-size: 0.7rem;
    color: #adb5bd;
}

.notification-empty-small {
    color: #6c757d;
    text-align: center;
    padding: 20px 10px;
    font-size: 0.875rem;
}
</style>