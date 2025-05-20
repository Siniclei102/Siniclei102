<!-- Alerta de validade próxima ao vencimento - Adicionar dentro do header.php após a abertura da tag body -->
<?php if (isset($_SESSION['validity_alert'])): ?>
<div class="validity-alert">
    <div class="container-fluid">
        <div class="alert alert-warning alert-dismissible fade show mb-0" role="alert">
            <div class="d-flex align-items-center">
                <div class="validity-icon me-3">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <strong>Atenção!</strong> Sua assinatura vence em <?php echo $_SESSION['validity_alert']['days']; ?> dias 
                    (<?php echo $_SESSION['validity_alert']['date']; ?>). 
                    <a href="renovar.php" class="alert-link">Renovar agora</a> para evitar a suspensão da sua conta.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>