<?php
// Iniciar sessão
session_start();

// Redirecionar para a página de login
header('Location: login.php');
exit;