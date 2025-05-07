<?php
// Iniciar sessão
session_start();

// Apagar todas as variáveis da sessão
$_SESSION = array();

// Destruir a sessão
session_destroy();

// Redirecionar para a página de login
header("Location: index.php");
exit;
?>