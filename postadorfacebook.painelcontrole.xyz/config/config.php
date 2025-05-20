<?php
/**
 * Arquivo de configurações gerais do sistema
 * 
 * Este arquivo contém as configurações principais do sistema,
 * como constantes, configurações de ambiente, entre outros.
 * 
 * @version 1.0
 */

// Definir o modo de debug (true em desenvolvimento, false em produção)
define('DEBUG_MODE', true);

// URL base do sistema
$base_url = 'https://postadorfacebook.painelcontrole.xyz';
define('BASE_URL', $base_url);

// Diretórios do sistema
define('ROOT_DIR', dirname(dirname(__FILE__)));
define('INCLUDES_DIR', ROOT_DIR . '/includes');
define('CLASSES_DIR', ROOT_DIR . '/classes');
define('UPLOADS_DIR', ROOT_DIR . '/uploads');
define('LOGS_DIR', ROOT_DIR . '/logs');

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_cache_limiter('private_no_expire');

// Configurações de segurança
define('HASH_COST', 10); // Custo do hash para senhas bcrypt

// Limites e configurações do sistema
define('MAX_GROUPS_FREE', 5);  // Máximo de grupos para usuários gratuitos
define('MAX_GROUPS_PRO', 50);  // Máximo de grupos para usuários pro
define('MAX_GROUPS_ENTERPRISE', 200);  // Máximo de grupos para usuários enterprise

// Configurações para schedule de posts
define('MIN_INTERVAL_BETWEEN_POSTS', 15); // Tempo mínimo entre posts em minutos

// Configurações de paginação
define('ITEMS_PER_PAGE', 15);

// Caminhos para a API do Facebook
define('FB_API_VERSION', 'v16.0');
define('FB_LOGIN_URL', 'https://www.facebook.com/' . FB_API_VERSION . '/dialog/oauth');
define('FB_GRAPH_URL', 'https://graph.facebook.com/' . FB_API_VERSION);

// Configurações para emails
define('EMAIL_FROM', 'naoresponder@postadorfacebook.painelcontrole.xyz');
define('EMAIL_NAME', 'Postador Facebook');

// Versão do sistema
define('SYSTEM_VERSION', '1.0.0');

// Função de autoload de classes
spl_autoload_register(function($class_name) {
    $class_file = CLASSES_DIR . '/' . $class_name . '.php';
    if (file_exists($class_file)) {
        require_once $class_file;
    }
});

// Incluir funções úteis
require_once INCLUDES_DIR . '/functions.php';

// Configurações de exibição de erros
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Função para registrar erros em log
function logSystemError($message, $file = null, $line = null) {
    $date = date('Y-m-d H:i:s');
    $log_file = LOGS_DIR . '/system_errors.log';
    
    $log_message = "[{$date}] {$message}";
    if ($file) {
        $log_message .= " in {$file}";
        if ($line) {
            $log_message .= " on line {$line}";
        }
    }
    
    $log_message .= PHP_EOL;
    
    // Garantir que o diretório de logs existe
    if (!is_dir(LOGS_DIR)) {
        mkdir(LOGS_DIR, 0755, true);
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Handler para erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        // Este código de erro não está incluído na configuração error_reporting
        return;
    }
    
    logSystemError($errstr, $errfile, $errline);
    
    if (DEBUG_MODE) {
        // Em modo debug, deixa o PHP exibir o erro normalmente
        return false;
    } else {
        // Em modo produção, trata o erro silenciosamente
        return true;
    }
});

// Handler para exceções não capturadas
set_exception_handler(function($exception) {
    logSystemError($exception->getMessage(), $exception->getFile(), $exception->getLine());
    
    if (DEBUG_MODE) {
        // Em modo debug, exibe detalhes da exceção
        echo "<h2>Erro no sistema</h2>";
        echo "<p><strong>Mensagem:</strong> " . $exception->getMessage() . "</p>";
        echo "<p><strong>Arquivo:</strong> " . $exception->getFile() . "</p>";
        echo "<p><strong>Linha:</strong> " . $exception->getLine() . "</p>";
    } else {
        // Em modo produção, exibe mensagem genérica
        header("HTTP/1.1 500 Internal Server Error");
        include ROOT_DIR . '/templates/500.php';
    }
    
    exit(1);
});
?>