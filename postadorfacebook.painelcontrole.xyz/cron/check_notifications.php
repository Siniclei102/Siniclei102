<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Notification.php';

// Obter conexão com o banco de dados
$db = Database::getInstance()->getConnection();

// Instanciar classe de notificação
$notification = new Notification($db);

// Verificar contas próximas do vencimento (7 dias)
$queryVencimento = "
    SELECT id, nome, email, validade_ate 
    FROM usuarios 
    WHERE validade_ate IS NOT NULL
    AND validade_ate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND validade_ate > CURDATE()
";

$resultVencimento = $db->query($queryVencimento);

while ($usuario = $resultVencimento->fetch_assoc()) {
    // Calcular dias restantes
    $hoje = new DateTime();
    $validade = new DateTime($usuario['validade_ate']);
    $diasRestantes = $hoje->diff($validade)->days;
    
    // Criar notificação
    $notification->criar(
        $usuario['id'],
        'conta',
        'Sua conta está prestes a expirar',
        "Sua assinatura vence em {$diasRestantes} dias. Para evitar a interrupção dos serviços, renove sua conta agora.",
        'renovar.php'
    );
    
    // Enviar email de alerta
    $to = $usuario['email'];
    $subject = "Sua conta está prestes a expirar";
    $message = "
        <html>
        <head>
            <title>Alerta de Expiração de Conta</title>
        </head>
        <body>
            <p>Olá {$usuario['nome']},</p>
            <p>Estamos entrando em contato para informar que sua conta no sistema de postagem automática expira em {$diasRestantes} dias.</p>
            <p>Para evitar a interrupção dos serviços, renove sua assinatura o mais breve possível.</p>
            <p><a href='https://{$_SERVER['HTTP_HOST']}/renovar.php'>Clique aqui para renovar sua conta</a></p>
            <p>Atenciosamente,<br>Equipe de Suporte</p>
        </body>
        </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Sistema de Postagem Automática <no-reply@{$_SERVER['HTTP_HOST']}>\r\n";
    
    mail($to, $subject, $message, $headers);
}

// Verificar campanhas com poucos anúncios (menos de 3)
$queryCampanhas = "
    SELECT c.id, c.nome, c.usuario_id, COUNT(a.id) as total_anuncios
    FROM campanhas c
    LEFT JOIN anuncios a ON c.id = a.campanha_id
    WHERE c.ativa = 1
    GROUP BY c.id
    HAVING total_anuncios < 3
";

$resultCampanhas = $db->query($queryCampanhas);

while ($campanha = $resultCampanhas->fetch_assoc()) {
    // Criar notificação
    $notification->criar(
        $campanha['usuario_id'],
        'campanha',
        'Campanha com poucos anúncios',
        "Sua campanha \"{$campanha['nome']}\" possui apenas {$campanha['total_anuncios']} anúncios. Para melhores resultados, adicione mais anúncios.",
        'anuncios.php?campanha_id=' . $campanha['id']
    );
}

// Verificar grupos inativos (sem postagens nos últimos 30 dias)
$queryGrupos = "
    SELECT g.id, g.nome, g.usuario_id
    FROM grupos_facebook g
    LEFT JOIN logs_postagem l ON g.id = l.grupo_id AND l.postado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE g.ativo = 1
    GROUP BY g.id
    HAVING COUNT(l.id) = 0
";

$resultGrupos = $db->query($queryGrupos);

while ($grupo = $resultGrupos->fetch_assoc()) {
    // Criar notificação
    $notification->criar(
        $grupo['usuario_id'],
        'campanha',
        'Grupo sem atividade recente',
        "O grupo \"{$grupo['nome']}\" não recebeu postagens nos últimos 30 dias. Verifique se ele ainda está ativo e disponível.",
        'grupos.php?editar=' . $grupo['id']
    );
}

echo "Verificação de notificações concluída.\n";
?>