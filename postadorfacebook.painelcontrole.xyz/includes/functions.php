<?php
/**
 * Funções utilitárias para a aplicação
 */

/**
 * Limpa dados de entrada para prevenir XSS
 */
function limparDado($dado) {
    $dado = trim($dado);
    $dado = stripslashes($dado);
    $dado = htmlspecialchars($dado, ENT_QUOTES, 'UTF-8');
    return $dado;
}

/**
 * Gera uma string aleatória
 */
function gerarStringAleatoria($tamanho = 10) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $stringAleatoria = '';
    for ($i = 0; $i < $tamanho; $i++) {
        $stringAleatoria .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    return $stringAleatoria;
}

/**
 * Formata data no padrão brasileiro
 */
function formatarData($data, $comHora = false) {
    if ($comHora) {
        return date('d/m/Y H:i', strtotime($data));
    }
    return date('d/m/Y', strtotime($data));
}

/**
 * Registra atividade do usuário
 */
function logActivity($usuario_id, $acao, $detalhes = '') {
    $db = Database::getInstance()->getConnection();
    $query = "INSERT INTO logs_atividades (usuario_id, acao, detalhes, ip) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("isss", $usuario_id, $acao, $detalhes, $ip);
    $stmt->execute();
}

/**
 * Verifica se o usuário tem permissão para acessar certa funcionalidade
 */
function temPermissao($permissao) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    if ($_SESSION['is_admin'] == 1) {
        return true;
    }
    
    $db = Database::getInstance()->getConnection();
    $query = "SELECT COUNT(*) as total FROM permissoes WHERE usuario_id = ? AND permissao = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("is", $_SESSION['user_id'], $permissao);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'] > 0;
}

/**
 * Redireciona com mensagem
 */
function redirecionarCom($url, $tipo, $mensagem) {
    $_SESSION['mensagem'] = [
        'tipo' => $tipo,
        'texto' => $mensagem
    ];
    
    header("Location: $url");
    exit;
}