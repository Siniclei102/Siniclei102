<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Mensagens de feedback
$messages = [];

// Processar exclusão de usuário
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    
    // Impedir exclusão do próprio usuário
    if ($deleteId === $userId) {
        $messages[] = [
            'type' => 'danger',
            'text' => "Você não pode excluir sua própria conta."
        ];
    } else {
        // Verificar se há campanhas ativas do usuário
        $queryCampanhas = "SELECT COUNT(*) as total FROM campanhas WHERE usuario_id = ? AND ativa = 1";
        $stmtCampanhas = $db->prepare($queryCampanhas);
        $stmtCampanhas->bind_param("i", $deleteId);
        $stmtCampanhas->execute();
        $resultCampanhas = $stmtCampanhas->get_result();
        $campanhasAtivas = $resultCampanhas->fetch_assoc()['total'];
        
        if ($campanhasAtivas > 0) {
            $messages[] = [
                'type' => 'warning',
                'text' => "O usuário possui campanhas ativas. Desative-as antes de excluir a conta."
            ];
        } else {
            // Excluir usuário
            $queryDelete = "DELETE FROM usuarios WHERE id = ?";
            $stmtDelete = $db->prepare($queryDelete);
            $stmtDelete->bind_param("i", $deleteId);
            
            if ($stmtDelete->execute()) {
                $messages[] = [
                    'type' => 'success',
                    'text' => "Usuário excluído com sucesso!"
                ];
            } else {
                $messages[] = [
                    'type' => 'danger',
                    'text' => "Erro ao excluir usuário: " . $db->error
                ];
            }
        }
    }
}

// Processar alteração de status (ativar/desativar)
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $toggleId = intval($_GET['toggle']);
    $status = isset($_GET['status']) ? intval($_GET['status']) : 0;
    
    // Impedir desativar a própria conta
    if ($toggleId === $userId && $status === 0) {
        $messages[] = [
            'type' => 'danger',
            'text' => "Você não pode desativar sua própria conta."
        ];
    } else {
        $queryToggle = "UPDATE usuarios SET is_active = ? WHERE id = ?";
        $stmtToggle = $db->prepare($queryToggle);
        $stmtToggle->bind_param("ii", $status, $toggleId);
        
        if ($stmtToggle->execute()) {
            $messages[] = [
                'type' => 'success',
                'text' => $status ? "Usuário ativado com sucesso!" : "Usuário desativado com sucesso!"
            ];
        } else {
            $messages[] = [
                'type' => 'danger',
                'text' => "Erro ao alterar status do usuário: " . $db->error
            ];
        }
    }
}

// Processar formulário de criação/edição de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioId = isset($_POST['usuario_id']) ? intval($_POST['usuario_id']) : null;
    $nome = $db->real_escape_string($_POST['nome']);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    
    // Validações
    $errors = [];
    
    if (empty($nome)) {
        $errors[] = "O nome é obrigatório.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido.";
    }
    
    // Verificar se email já existe (para novos usuários ou alteração de email)
    $queryCheck = "SELECT id FROM usuarios WHERE email = ? AND id != ? LIMIT 1";
    $stmtCheck = $db->prepare($queryCheck);
    $checkId = $usuarioId ?? 0;
    $stmtCheck->bind_param("si", $email, $checkId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    
    if ($resultCheck->num_rows > 0) {
        $errors[] = "Este email já está em uso.";
    }
    
    // Se for novo usuário, senha é obrigatória
    if (!$usuarioId && empty($password)) {
        $errors[] = "Senha é obrigatória para novos usuários.";
    }
    
    // Se não há erros, prosseguir
    if (empty($errors)) {
        if ($usuarioId) {
            // Edição de usuário existente
            if (!empty($password)) {
                // Atualizar com nova senha
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $query = "UPDATE usuarios SET nome = ?, email = ?, is_admin = ?, is_active = ?, senha = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bind_param("ssiisi", $nome, $email, $isAdmin, $isActive, $passwordHash, $usuarioId);
            } else {
                // Atualizar sem alterar senha
                $query = "UPDATE usuarios SET nome = ?, email = ?, is_admin = ?, is_active = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->bind_param("ssiii", $nome, $email, $isAdmin, $isActive, $usuarioId);
            }
            
            if ($stmt->execute()) {
                $messages[] = [
                    'type' => 'success',
                    'text' => "Usuário atualizado com sucesso!"
                ];
            } else {
                $messages[] = [
                    'type' => 'danger',
                    'text' => "Erro ao atualizar usuário: " . $db->error
                ];
            }
        } else {
            // Criação de novo usuário
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO usuarios (nome, email, senha, is_admin, is_active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bind_param("sssii", $nome, $email, $passwordHash, $isAdmin, $isActive);
            
            if ($stmt->execute()) {
                $messages[] = [
                    'type' => 'success',
                    'text' => "Usuário criado com sucesso!"
                ];
            } else {
                $messages[] = [
                    'type' => 'danger',
                    'text' => "Erro ao criar usuário: " . $db->error
                ];
            }
        }
    } else {
        // Exibir erros de validação
        foreach ($errors as $error) {
            $messages[] = [
                'type' => 'danger',
                'text' => $error
            ];
        }
    }
}

// Verificar se estamos editando um usuário
$usuarioPara = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    
    // Buscar dados do usuário
    $queryEdit = "SELECT * FROM usuarios WHERE id = ?";
    $stmtEdit = $db->prepare($queryEdit);
    $stmtEdit->bind_param("i", $editId);
    $stmtEdit->execute();
    $resultEdit = $stmtEdit->get_result();
    
    if ($resultEdit->num_rows > 0) {
        $usuarioPara = $resultEdit->fetch_assoc();
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Usuário não encontrado."
        ];
    }
}

// Definir filtros e paginação
$busca = isset($_GET['busca']) ? $db->real_escape_string($_GET['busca']) : '';
$status = isset($_GET['status']) ? $db->real_escape_string($_GET['status']) : '';
$role = isset($_GET['role']) ? $db->real_escape_string($_GET['role']) : '';
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta com filtros
$where = [];
$params = [];
$param_types = "";

if (!empty($busca)) {
    $where[] = "(nome LIKE ? OR email LIKE ?)";
    $termoBusca = "%{$busca}%";
    $params[] = $termoBusca;
    $params[] = $termoBusca;
    $param_types .= "ss";
}

if ($status !== '') {
    $where[] = "is_active = ?";
    $params[] = $status;
    $param_types .= "i";
}

if ($role !== '') {
    $where[] = "is_admin = ?";
    $params[] = $role;
    $param_types .= "i";
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Contar total de registros
$queryCount = "SELECT COUNT(*) as total FROM usuarios {$whereClause}";
$stmtCount = $db->prepare($queryCount);

if (!empty($params)) {
    $stmtCount->bind_param($param_types, ...$params);
}

$stmtCount->execute();
$total_registros = $stmtCount->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// Buscar usuários paginados
$queryUsuarios = "
    SELECT u.*,
           (SELECT COUNT(*) FROM campanhas WHERE usuario_id = u.id) as total_campanhas,
           (SELECT COUNT(*) FROM grupos_facebook WHERE usuario_id = u.id) as total_grupos,
           (SELECT COUNT(*) FROM anuncios WHERE usuario_id = u.id) as total_anuncios,
           (SELECT MAX(data_criacao) FROM login_logs WHERE usuario_id = u.id) as ultimo_login
    FROM usuarios u
    {$whereClause}
    ORDER BY u.nome ASC
    LIMIT ? OFFSET ?
";

$paramsFull = $params;
$paramsFull[] = $por_pagina;
$paramsFull[] = $offset;
$param_types_full = $param_types . "ii";

$stmtUsuarios = $db->prepare($queryUsuarios);
$stmtUsuarios->bind_param($param_types_full, ...$paramsFull);
$stmtUsuarios->execute();
$resultUsuarios = $stmtUsuarios->get_result();

// Incluir o cabeçalho
include '../includes/header.php';
?>
<!-- Adicionar no formulário de edição/criação de usuário dentro do arquivo /admin/usuarios.php -->

<div class="row mb-3">
    <div class="col-md-6">
        <label for="validade_ate" class="form-label">Validade da Conta</label>
        <input type="date" class="form-control" id="validade_ate" name="validade_ate" value="<?php echo $usuarioPara && $usuarioPara['validade_ate'] ? date('Y-m-d', strtotime($usuarioPara['validade_ate'])) : ''; ?>">
        <div class="form-text">Deixe em branco para conta sem validade.</div>
    </div>
    
    <div class="col-md-6 mb-3">
        <label class="form-label">Status de Suspensão</label>
        <div class="row mb-2">
            <div class="col-md-6">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="suspenso" name="suspenso" <?php echo ($usuarioPara && $usuarioPara['suspenso']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="suspenso">Conta Suspensa</label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modificar a consulta de edição/criação de usuário para incluir os novos campos -->
<?php
// No bloco de processamento do formulário, adicionar:

$validadeAte = !empty($_POST['validade_ate']) ? $_POST['validade_ate'] : null;
$suspenso = isset($_POST['suspenso']) ? 1 : 0;

// Para edição (atualizar a query existente):
if ($usuarioId) {
    if (!empty($password)) {
        // Atualizar com nova senha
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE usuarios SET nome = ?, email = ?, is_admin = ?, is_active = ?, senha = ?, validade_ate = ?, suspenso = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssiisisi", $nome, $email, $isAdmin, $isActive, $passwordHash, $validadeAte, $suspenso, $usuarioId);
    } else {
        // Atualizar sem alterar senha
        $query = "UPDATE usuarios SET nome = ?, email = ?, is_admin = ?, is_active = ?, validade_ate = ?, suspenso = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssiisii", $nome, $email, $isAdmin, $isActive, $validadeAte, $suspenso, $usuarioId);
    }
} else {
    // Criação de novo usuário (atualizar a query existente):
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $query = "INSERT INTO usuarios (nome, email, senha, is_admin, is_active, validade_ate, suspenso) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("sssiiis", $nome, $email, $passwordHash, $isAdmin, $isActive, $validadeAte, $suspenso);
}
?>

<!-- Adicionar coluna de validade na tabela de listagem de usuários -->
<thead>
    <tr>
        <th>Nome</th>
        <th>Email</th>
        <th>Campanhas</th>
        <th>Grupos</th>
        <th>Anúncios</th>
        <th>Status</th>
        <th>Função</th>
        <th>Validade</th>
        <th>Último Acesso</th>
        <th>Ações</th>
    </tr>
</thead>
<tbody>
    <?php if ($resultUsuarios->num_rows > 0): ?>
        <?php while ($usuario = $resultUsuarios->fetch_assoc()): ?>
            <tr>
                <!-- Outras colunas -->
                <td>
                    <?php if ($usuario['validade_ate']): ?>
                        <?php
                        $validade = new DateTime($usuario['validade_ate']);
                        $hoje = new DateTime();
                        $expirada = $validade < $hoje;
                        ?>
                        <?php if ($expirada): ?>
                            <span class="badge bg-danger">Expirada</span>
                        <?php else: ?>
                            <?php
                            $diasRestantes = $hoje->diff($validade)->days;
                            if ($diasRestantes <= 5) {
                                echo '<span class="badge bg-warning">Vence em ' . $diasRestantes . ' dias</span>';
                            } else {
                                echo date('d/m/Y', strtotime($usuario['validade_ate']));
                            }
                            ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-secondary">Sem validade</span>
                    <?php endif; ?>
                </td>
                <!-- Outras colunas -->
            </tr>
        <?php endwhile; ?>
    <?php endif; ?>
</tbody>
<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-users-cog me-2 text-primary"></i> 
                        <?php echo $usuarioPara ? 'Editar Usuário' : 'Gerenciamento de Usuários'; ?>
                    </h5>
                </div>
                <div class="modern-card-body">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message['text']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Formulário de Edição/Criação -->
                    <?php if ($usuarioPara || isset($_GET['new'])): ?>
                        <div class="user-form-container">
                            <form method="POST" action="usuarios.php">
                                <?php if ($usuarioPara): ?>
                                    <input type="hidden" name="usuario_id" value="<?php echo $usuarioPara['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nome" class="form-label">Nome</label>
                                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $usuarioPara ? htmlspecialchars($usuarioPara['nome']) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $usuarioPara ? htmlspecialchars($usuarioPara['email']) : ''; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">
                                            <?php echo $usuarioPara ? 'Nova Senha (deixe em branco para manter atual)' : 'Senha'; ?>
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" <?php echo !$usuarioPara ? 'required' : ''; ?>>
                                        <?php if (!$usuarioPara): ?>
                                            <div class="form-text">Mínimo de 6 caracteres.</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Configurações</label>
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin" <?php echo ($usuarioPara && $usuarioPara['is_admin']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="is_admin">Administrador</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo ($usuarioPara ? $usuarioPara['is_active'] : true) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="is_active">Ativo</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($usuarioPara): ?>
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <div class="card user-info-card">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle mb-2 text-muted">Informações Adicionais</h6>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <p><strong>Data de Registro:</strong> <?php echo date('d/m/Y H:i', strtotime($usuarioPara['criado_em'])); ?></p>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <p><strong>Facebook:</strong> <?php echo $usuarioPara['facebook_id'] ? 'Conectado' : 'Não conectado'; ?></p>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <p><strong>Último Acesso:</strong> 
                                                                <?php
                                                                $queryLastLogin = "SELECT MAX(data_criacao) as ultimo FROM login_logs WHERE usuario_id = ?";
                                                                $stmtLastLogin = $db->prepare($queryLastLogin);
                                                                $stmtLastLogin->bind_param("i", $usuarioPara['id']);
                                                                $stmtLastLogin->execute();
                                                                $lastLogin = $stmtLastLogin->get_result()->fetch_assoc()['ultimo'] ?? null;
                                                                echo $lastLogin ? date('d/m/Y H:i', strtotime($lastLogin)) : 'Nunca';
                                                                ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-between">
                                            <a href="usuarios.php" class="btn btn-secondary">
                                                <i class="fas fa-arrow-left me-1"></i> Voltar
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i> 
                                                <?php echo $usuarioPara ? 'Atualizar Usuário' : 'Criar Usuário'; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Filtros e Lista de Usuários -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <form method="GET" action="usuarios.php" class="d-flex gap-2">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="busca" placeholder="Buscar por nome ou email" value="<?php echo htmlspecialchars($busca); ?>">
                                        <button class="btn btn-outline-primary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    
                                    <select class="form-select" name="status" onchange="this.form.submit()" style="max-width: 150px;">
                                        <option value="">Status</option>
                                        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Ativos</option>
                                        <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inativos</option>
                                    </select>
                                    
                                    <select class="form-select" name="role" onchange="this.form.submit()" style="max-width: 150px;">
                                        <option value="">Função</option>
                                        <option value="1" <?php echo $role === '1' ? 'selected' : ''; ?>>Administradores</option>
                                        <option value="0" <?php echo $role === '0' ? 'selected' : ''; ?>>Usuários</option>
                                    </select>
                                </form>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="?new=1" class="btn btn-success">
                                    <i class="fas fa-user-plus me-1"></i> Novo Usuário
                                </a>
                            </div>
                        </div>
                        
                        <!-- Tabela de Usuários -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Campanhas</th>
                                        <th>Grupos</th>
                                        <th>Anúncios</th>
                                        <th>Status</th>
                                        <th>Função</th>
                                        <th>Último Acesso</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($resultUsuarios->num_rows > 0): ?>
                                        <?php while ($usuario = $resultUsuarios->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                                <td><?php echo $usuario['total_campanhas']; ?></td>
                                                <td><?php echo $usuario['total_grupos']; ?></td>
                                                <td><?php echo $usuario['total_anuncios']; ?></td>
                                                <td>
                                                    <?php if ($usuario['is_active']): ?>
                                                        <span class="badge bg-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($usuario['is_admin']): ?>
                                                        <span class="badge bg-primary">Admin</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Usuário</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($usuario['ultimo_login']) {
                                                        $now = new DateTime();
                                                        $last = new DateTime($usuario['ultimo_login']);
                                                        $diff = $now->diff($last);
                                                        
                                                        if ($diff->days == 0) {
                                                            if ($diff->h == 0) {
                                                                echo 'Há ' . $diff->i . ' min';
                                                            } else {
                                                                echo 'Há ' . $diff->h . ' hora' . ($diff->h > 1 ? 's' : '');
                                                            }
                                                        } elseif ($diff->days < 30) {
                                                            echo 'Há ' . $diff->days . ' dia' . ($diff->days > 1 ? 's' : '');
                                                        } else {
                                                            echo date('d/m/Y', strtotime($usuario['ultimo_login']));
                                                        }
                                                    } else {
                                                        echo 'Nunca';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?edit=<?php echo $usuario['id']; ?>" class="btn btn-outline-primary" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if ($usuario['id'] != $userId): ?>
                                                            <a href="?toggle=<?php echo $usuario['id']; ?>&status=<?php echo $usuario['is_active'] ? '0' : '1'; ?>" 
                                                               class="btn <?php echo $usuario['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>" 
                                                               title="<?php echo $usuario['is_active'] ? 'Desativar' : 'Ativar'; ?>">
                                                                <i class="fas fa-<?php echo $usuario['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                            </a>
                                                            
                                                            <a href="?delete=<?php echo $usuario['id']; ?>" 
                                                               class="btn btn-outline-danger" 
                                                               onclick="return confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.');"
                                                               title="Excluir">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">Nenhum usuário encontrado.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginação -->
                        <?php if ($total_paginas > 1): ?>
                            <nav aria-label="Navegação de página">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?pagina=1<?php echo !empty($busca) ? '&busca=' . urlencode($busca) : ''; ?><?php echo $status !== '' ? '&status=' . urlencode($status) : ''; ?><?php echo $role !== '' ? '&role=' . urlencode($role) : ''; ?>" aria-label="Primeira">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo !empty($busca) ? '&busca=' . urlencode($busca) : ''; ?><?php echo $status !== '' ? '&status=' . urlencode($status) : ''; ?><?php echo $role !== '' ? '&role=' . urlencode($role) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $total_paginas; ?><?php echo !empty($busca) ? '&busca=' . urlencode($busca) : ''; ?><?php echo $status !== '' ? '&status=' . urlencode($status) : ''; ?><?php echo $role !== '' ? '&role=' . urlencode($role) : ''; ?>" aria-label="Última">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
.user-form-container {
    max-width: 900px;
    margin: 0 auto;
}

.user-info-card {
    background-color: #f8f9fa;
    border: none;
    border-radius: 10px;
    box-shadow: none;
}

.form-check-input {
    width: 2.5em;
    height: 1.25em;
}

table tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.badge {
    font-size: 85%;
    font-weight: 500;
    padding: 0.4em 0.7em;
}
</style>

<?php
// Incluir o rodapé
include '../includes/footer.php';
?>