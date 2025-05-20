<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance()->getConnection();
    $adminId = $_SESSION['user_id'];
    
    $userId = isset($_POST['usuario_id']) && is_numeric($_POST['usuario_id']) ? intval($_POST['usuario_id']) : 0;
    $diasExtensao = isset($_POST['dias_extensao']) && is_numeric($_POST['dias_extensao']) ? intval($_POST['dias_extensao']) : 30;
    $observacao = isset($_POST['observacao']) ? $db->real_escape_string($_POST['observacao']) : '';
    
    if ($userId > 0) {
        // Obter dados atuais do usuário
        $queryUser = "SELECT nome, email, validade_ate, suspenso FROM usuarios WHERE id = ? LIMIT 1";
        $stmtUser = $db->prepare($queryUser);
        $stmtUser->bind_param("i", $userId);
        $stmtUser->execute();
        $resultUser = $stmtUser->get_result();
        
        if ($resultUser->num_rows > 0) {
            $user = $resultUser->fetch_assoc();
            
            // Calcular nova data de validade
            $hoje = new DateTime();
            $validade = $user['validade_ate'] ? new DateTime($user['validade_ate']) : $hoje;
            
            // Se a validade já expirou, começar a contar a partir de hoje
            if ($validade < $hoje) {                $validade = $hoje;
            }
            
            // Adicionar dias
            $validade->add(new DateInterval("P{$diasExtensao}D"));
            $novaValidadeStr = $validade->format('Y-m-d');
            
            // Atualizar validade e remover suspensão
            $queryUpdate = "UPDATE usuarios SET validade_ate = ?, suspenso = 0 WHERE id = ?";
            $stmtUpdate = $db->prepare($queryUpdate);
            $stmtUpdate->bind_param("si", $novaValidadeStr, $userId);
            
            if ($stmtUpdate->execute()) {
                // Registrar log da operação
                $queryLog = "INSERT INTO admin_logs (admin_id, usuario_id, acao, detalhes) 
                            VALUES (?, ?, 'extensao_validade', ?)";
                $logDetalhes = json_encode([
                    'dias_adicionados' => $diasExtensao,
                    'nova_validade' => $novaValidadeStr,
                    'observacao' => $observacao
                ]);
                $stmtLog = $db->prepare($queryLog);
                $stmtLog->bind_param("iis", $adminId, $userId, $logDetalhes);
                $stmtLog->execute();
                
                // Enviar email de notificação ao usuário
                $to = $user['email'];
                $subject = "Sua conta foi renovada!";
                $message = "
                    <html>
                    <head>
                        <title>Renovação de Conta</title>
                    </head>
                    <body>
                        <p>Olá {$user['nome']},</p>
                        <p>Temos boas notícias! Sua conta no sistema foi renovada com sucesso.</p>
                        <p>Sua assinatura agora é válida até <strong>{$validade->format('d/m/Y')}</strong>.</p>
                        <p>Você pode fazer login normalmente e usar todos os recursos do sistema.</p>
                        <p>Atenciosamente,<br>Equipe de Suporte</p>
                    </body>
                    </html>
                ";
                
                // Headers para envio de email HTML
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Sistema de Postagem Automática <no-reply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                
                // Enviar email (em um sistema real seria importante verificar se o email foi enviado)
                mail($to, $subject, $message, $headers);
                
                // Definir mensagem de sucesso e redirecionar
                $_SESSION['admin_message'] = [
                    'type' => 'success',
                    'text' => "Validade da conta de {$user['nome']} estendida com sucesso até {$validade->format('d/m/Y')}."
                ];
            } else {
                $_SESSION['admin_message'] = [
                    'type' => 'danger',
                    'text' => "Erro ao estender validade: " . $db->error
                ];
            }
        } else {
            $_SESSION['admin_message'] = [
                'type' => 'danger',
                'text' => "Usuário não encontrado."
            ];
        }
    } else {
        $_SESSION['admin_message'] = [
            'type' => 'danger',
            'text' => "ID de usuário inválido."
        ];
    }
    
    // Redirecionar de volta para o relatório de validade
    header('Location: ../relatorio_validade.php');
    exit;
} else {
    // Se não for uma requisição POST, redirecionar para o dashboard
    header('Location: ../dashboard.php');
    exit;
}
?>