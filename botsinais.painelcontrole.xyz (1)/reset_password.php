<?php
// Conectar ao banco de dados
require_once 'config/database.php';

// Nova senha: admin123
$new_password = 'admin123';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Atualizar a senha do admin
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->bind_param("s", $hashed_password);

if ($stmt->execute()) {
    echo "Senha do administrador foi redefinida para: admin123";
} else {
    echo "Erro ao redefinir senha: " . $conn->error;
}

// Fechar conexão
$conn->close();
?>