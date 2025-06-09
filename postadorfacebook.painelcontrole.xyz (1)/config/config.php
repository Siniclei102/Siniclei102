<?php
/**
 * Configuração Principal do Sistema
 * Postador Facebook - Painel de Controle
 */

// Configuração do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'sql_postadorface');
define('DB_USER', 'sql_postadorface');
define('DB_PASS', '2577fadde1ae4');
define('DB_CHARSET', 'utf8');  // Mudado de utf8mb3 para utf8


// Configurações do site
define('SITE_NAME', 'PostGrupo Facebook');
define('SITE_URL', 'https://postadorfacebook.painelcontrole.xyz');

// Configurações de segurança
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos em segundos

// Configurações de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Defina como 1 se usar HTTPS
ini_set('session.gc_maxlifetime', 3600); // 1 hora

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Exibição de erros (desabilitar em produção)
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
?>