<?php
// Iniciar sessão
session_start();

// Destruir sessão
session_destroy();

// Redirecionar para página de login
header('Location: login.php');
exit;