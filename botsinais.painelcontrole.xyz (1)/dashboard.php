<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Redirecionar com base no tipo de usuário
$role = $_SESSION['role'] ?? 'user';

switch ($role) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'master':
        header('Location: user/master/dashboard.php');
        break;
    case 'user':
    default:
        header('Location: user/dashboard.php');
        break;
}
exit;