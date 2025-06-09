<?php
session_start();
require_once 'config/config.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $this->connect();
    }
    
    private function connect() {
        $charsets = ['utf8mb4', 'utf8', 'latin1'];
        $connected = false;
        
        foreach ($charsets as $charset) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . $charset;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                $connected = true;
                break;
            } catch (PDOException $e) {
                continue;
            }
        }
        
        if (!$connected) {
            throw new Exception("Erro de conexão com o banco de dados");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $values = array_values($data);
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $this->query($sql, $values);
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $fields = array_keys($data);
        $setClause = implode(' = ?, ', $fields) . ' = ?';
        $values = array_values($data);
        $values = array_merge($values, $whereParams);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        return $this->query($sql, $values);
    }
    
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $whereParams);
    }
}

$db = Database::getInstance();

// Buscar usuário atual
$currentUser = $db->selectOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

// Verificar se é admin
if ($currentUser['account_type'] !== 'admin') {
    die('Acesso negado. Apenas administradores podem gerenciar usuários.');
}

$message = '';
$messageType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_user':
                    $username = trim($_POST['username']);
                    $email = trim($_POST['email']);
                    $password = trim($_POST['password']);
                    $name = trim($_POST['name']);
                    $account_type = $_POST['account_type'];
                    $subscription_expires_at = $_POST['subscription_expires_at'];
                    
                    if (empty($username) || empty($email) || empty($password) || empty($name)) {
                        throw new Exception('Todos os campos são obrigatórios');
                    }
                    
                    // Verificar se usuário já existe
                    $existing = $db->selectOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
                    if ($existing) {
                        throw new Exception('Usuário ou email já existe');
                    }
                    
                    // Hash da senha
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Inserir usuário
                    $userData = [
                        'username' => $username,
                        'email' => $email,
                        'password' => $hashedPassword,
                        'name' => $name,
                        'account_type' => $account_type,
                        'subscription_expires_at' => $subscription_expires_at ?: null,
                        'account_status' => 'active',
                        'created_by' => $currentUser['id'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $userId = $db->insert('users', $userData);
                    $message = "Usuário '$username' criado com sucesso! ID: $userId";
                    $messageType = 'success';
                    break;
                    
                case 'edit_user':
                    $user_id = intval($_POST['user_id']);
                    $username = trim($_POST['username']);
                    $email = trim($_POST['email']);
                    $name = trim($_POST['name']);
                    $account_type = $_POST['account_type'];
                    $subscription_expires_at = $_POST['subscription_expires_at'];
                    $account_status = $_POST['account_status'];
                    
                    $updateData = [
                        'username' => $username,
                        'email' => $email,
                        'name' => $name,
                        'account_type' => $account_type,
                        'subscription_expires_at' => $subscription_expires_at ?: null,
                        'account_status' => $account_status,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Se senha foi fornecida, atualizar também
                    if (!empty($_POST['password'])) {
                        $updateData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    }
                    
                    $db->update('users', $updateData, 'id = ?', [$user_id]);
                    $message = "Usuário atualizado com sucesso!";
                    $messageType = 'success';
                    break;
                    
                case 'delete_user':
                    $user_id = intval($_POST['user_id']);
                    
                    if ($user_id === $currentUser['id']) {
                        throw new Exception('Você não pode deletar sua própria conta');
                    }
                    
                    $db->delete('users', 'id = ?', [$user_id]);
                    $message = "Usuário deletado com sucesso!";
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Buscar todos os usuários
$users = $db->select("SELECT * FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - PostGrupo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4f6ed 0%, #e6fffa 100%);
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fef7f7 100%);
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e8ecf0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fb;
            font-weight: 600;
            color: #333;
        }
        
        .table tr:hover {
            background: #f8f9fb;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4f6ed;
            color: #047857;
        }
        
        .status-suspended {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .status-pending {
            background: #fffbeb;
            color: #d97706;
        }
        
        .account-type {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .type-admin {
            background: #eff6ff;
            color: #1d4ed8;
        }
        
        .type-user {
            background: #f3f4f6;
            color: #374151;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .nav-links {
            margin-bottom: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-right: 20px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="nav-links">
                <a href="dashboard.php">🏠 Dashboard</a>
                <a href="groups.php">👥 Grupos</a>
                <a href="posts.php">📝 Posts</a>
                <a href="schedule.php">📅 Agenda</a>
                <a href="manage_users.php">👤 Usuários</a>
            </div>
            <h1>👥 Gerenciar Usuários</h1>
            <p>Administração de usuários do sistema PostGrupo</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <span><?php echo $messageType === 'success' ? '✅' : '❌'; ?></span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>➕ Adicionar Novo Usuário</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Nome de Usuário:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Nome Completo:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Senha:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="account_type">Tipo de Conta:</label>
                        <select id="account_type" name="account_type" required>
                            <option value="user">Usuário</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subscription_expires_at">Expira em:</label>
                        <input type="date" id="subscription_expires_at" name="subscription_expires_at" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn">➕ Criar Usuário</button>
            </form>
        </div>
        
        <div class="card">
            <h2>📋 Lista de Usuários</h2>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Expira</th>
                        <th>Último Login</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="account-type type-<?php echo $user['account_type']; ?>">
                                    <?php echo ucfirst($user['account_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $user['account_status']; ?>">
                                    <?php echo ucfirst($user['account_status']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['subscription_expires_at'] ? date('d/m/Y', strtotime($user['subscription_expires_at'])) : 'Nunca'; ?></td>
                            <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                    ✏️ Editar
                                </button>
                                <?php if ($user['id'] !== $currentUser['id']): ?>
                                    <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        🗑️ Deletar
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal para editar usuário -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>✏️ Editar Usuário</h2>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_username">Nome de Usuário:</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_name">Nome Completo:</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email:</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_password">Nova Senha (deixe vazio para manter):</label>
                        <input type="password" id="edit_password" name="password">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_account_type">Tipo de Conta:</label>
                        <select id="edit_account_type" name="account_type" required>
                            <option value="user">Usuário</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_account_status">Status:</label>
                        <select id="edit_account_status" name="account_status" required>
                            <option value="active">Ativo</option>
                            <option value="suspended">Suspenso</option>
                            <option value="pending">Pendente</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_subscription_expires_at">Expira em:</label>
                    <input type="date" id="edit_subscription_expires_at" name="subscription_expires_at">
                </div>
                
                <button type="submit" class="btn">💾 Salvar Alterações</button>
                <button type="button" class="btn btn-danger" onclick="closeModal()">❌ Cancelar</button>
            </form>
        </div>
    </div>
    
    <!-- Modal para deletar usuário -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteModal()">&times;</span>
            <h2>🗑️ Deletar Usuário</h2>
            <p>Tem certeza que deseja deletar o usuário <strong id="delete_username"></strong>?</p>
            <p><strong>⚠️ Esta ação não pode ser desfeita!</strong></p>
            
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id">
                
                <button type="submit" class="btn btn-danger">🗑️ Sim, Deletar</button>
                <button type="button" class="btn" onclick="closeDeleteModal()">❌ Cancelar</button>
            </form>
        </div>
    </div>
    
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_account_type').value = user.account_type;
            document.getElementById('edit_account_status').value = user.account_status;
            document.getElementById('edit_subscription_expires_at').value = user.subscription_expires_at;
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function deleteUser(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Fechar modal clicando fora
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            if (event.target == deleteModal) {
                deleteModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>