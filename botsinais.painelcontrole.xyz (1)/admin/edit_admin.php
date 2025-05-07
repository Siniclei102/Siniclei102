<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/check_expiry.php'; // Incluir o novo arquivo

// Verificar permissão de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Verificar se é um super administrador (pode editar outros admins)
$isSuperAdmin = checkPermission($conn, $_SESSION['user_id'], 'edit_admin');

$userId = $_SESSION['user_id']; // Por padrão, edita o próprio perfil
$alert = "";
$alert_type = "";

// Se estiver editando outro usuário
if (isset($_GET['id']) && $isSuperAdmin) {
    $userId = (int)$_GET['id'];
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    // Validações básicas
    if (empty($username) || empty($email)) {
        $alert = "Nome de usuário e email são obrigatórios";
        $alert_type = "danger";
    } else {
        // Verificar se o usuário atual tem permissão para esta ação
        if ($userId != $_SESSION['user_id'] && !$isSuperAdmin) {
            $alert = "Você não tem permissão para editar outros administradores";
            $alert_type = "danger";
        } else {
            // Se estiver mudando a senha
            if (!empty($newPassword)) {
                // Verificar se senha atual está correta
                if ($userId === $_SESSION['user_id']) {
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    
                    if (!password_verify($currentPassword, $user['password'])) {
                        $alert = "Senha atual incorreta";
                        $alert_type = "danger";
                    } elseif ($newPassword !== $confirmPassword) {
                        $alert = "As senhas não coincidem";
                        $alert_type = "danger";
                    } else {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, expiry_date = ? WHERE id = ?");
                        $stmt->bind_param("ssssi", $username, $email, $hashedPassword, $expiryDate, $userId);
                    }
                } else {
                    // Super admin alterando senha de outro admin
                    if ($newPassword !== $confirmPassword) {
                        $alert = "As senhas não coincidem";
                        $alert_type = "danger";
                    } else {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, expiry_date = ? WHERE id = ?");
                        $stmt->bind_param("ssssi", $username, $email, $hashedPassword, $expiryDate, $userId);
                    }
                }
            } else {
                // Atualizar sem mudar a senha
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, expiry_date = ? WHERE id = ?");
                $stmt->bind_param("sssi", $username, $email, $expiryDate, $userId);
            }
            
            // Se não houve erro até aqui, atualiza
            if (empty($alert)) {
                if ($stmt->execute()) {
                    // Se o próprio usuário atualizou seu nome, atualizar sessão
                    if ($userId === $_SESSION['user_id']) {
                        $_SESSION['username'] = $username;
                    }
                    $alert = "Informações atualizadas com sucesso!";
                    $alert_type = "success";
                } else {
                    $alert = "Erro ao atualizar: " . $conn->error;
                    $alert_type = "danger";
                }
            }
        }
    }
}

// Obter dados do usuário
$stmt = $conn->prepare("SELECT id, username, email, role, status, expiry_date FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}
$userData = $result->fetch_assoc();

$pageTitle = "Editar Perfil de Administrador";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <!-- Head, metas, CSS, etc. (semelhante aos seus outros arquivos) -->
    <title><?php echo $pageTitle; ?></title>
    <style>
        /* Seus estilos CSS aqui */
    </style>
</head>
<body>
    <!-- Sidebar e cabeçalho (semelhante aos seus outros arquivos) -->
    
    <div class="content">
        <!-- Alertas -->
        <?php if (!empty($alert)): ?>
            <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $alert; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>
        
        <!-- Formulário de edição -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><?php echo $pageTitle; ?></h6>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($userId != $_SESSION['user_id'] ? '?id=' . $userId : '')); ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nome de Usuário</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="expiry_date" class="form-label">Data de Validade</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                               value="<?php echo !empty($userData['expiry_date']) ? $userData['expiry_date'] : ''; ?>">
                        <div class="form-text">Data em que a conta expirará. Deixe em branco para não definir validade.</div>
                    </div>
                    
                    <h5 class="mt-4">Alterar Senha</h5>
                    
                    <?php if ($userId === $_SESSION['user_id']): ?>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Senha Atual</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                    
                    <button type="submit" name="update_admin" class="btn btn-primary">Salvar Alterações</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts JS, etc. (semelhante aos seus outros arquivos) -->
</body>
</html>