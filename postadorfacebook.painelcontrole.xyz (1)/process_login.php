<?php
// Iniciar sessão
session_start();

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Credencial temporária para teste
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['login_error'] = 'Credenciais inválidas';
        header('Location: login.php');
        exit;
    }
} else {
    // Redirecionamento se não for POST
    header('Location: login.php');
    exit;
}