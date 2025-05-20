<?php
// Arquivo de autenticação e proteção de rotas

// Iniciar sessão se ainda não foi iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o usuário está logado
 * Se não estiver, redireciona para a página de login
 */
function verificarLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . getBaseUrl() . 'index.php');
        exit;
    }
}

/**
 * Verifica se o usuário tem permissão de administrador
 * Se não tiver, redireciona para o dashboard
 */
function verificarAdmin() {
    verificarLogin();
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        header('Location: ' . getBaseUrl() . 'dashboard.php');
        exit;
    }
}

/**
 * Retorna a URL base dependendo se estamos em um arquivo admin ou não
 */
function getBaseUrl() {
    $isAdmin = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
    return $isAdmin ? '../' : '';
}

// Executa a verificação de login por padrão em páginas protegidas
// Esta linha só deve executar verificarLogin() se não estivermos em index.php ou registro.php
$current_page = basename($_SERVER['SCRIPT_NAME']);
if (!in_array($current_page, ['index.php', 'registro.php', 'recuperar-senha.php'])) {
    verificarLogin();
}