<?php
require_once 'config/config.php';

// Incorporar a classe Database diretamente
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $this->connect();
    }
    
    private function connect() {
        $charsets = ['utf8mb4', 'utf8', 'latin1'];
        $connected = false;
        $lastError = '';
        
        foreach ($charsets as $charset) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . $charset;
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
                ];
                
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                $connected = true;
                break;
                
            } catch (PDOException $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }
        
        if (!$connected) {
            throw new Exception("Erro de conexão com o banco de dados: " . $lastError);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Erro na consulta: " . $e->getMessage());
        }
    }
    
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
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
    
    public function tableExists($tableName) {
        try {
            $result = $this->query("SHOW TABLES LIKE ?", [$tableName]);
            return $result->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Sistema de autenticação simples
session_start();

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Dados do usuário - BUSCAR DO BANCO
$user = [
    'id' => $_SESSION['user_id'] ?? 1,
    'name' => $_SESSION['username'] ?? 'Siniclei102',
    'email' => $_SESSION['email'] ?? 'siniclei102@gmail.com',
    'account_type' => $_SESSION['account_type'] ?? 'admin',
    'expiry_date' => null
];

// Buscar dados reais do usuário no banco
try {
    $db = Database::getInstance();
    if ($db->tableExists('users')) {
        $userData = $db->selectOne("SELECT name, email, account_type, expiry_date FROM users WHERE id = ?", [$user['id']]);
        if ($userData) {
            $user = array_merge($user, $userData);
        }
    }
} catch (Exception $e) {}

$isAdmin = ($user['account_type'] === 'admin');

// Processar dados da extensão
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        
        if (isset($_POST['extension_data'])) {
            // Dados vindos da extensão do Chrome
            $extensionData = json_decode($_POST['extension_data'], true);
            
            if ($extensionData && isset($extensionData['groups'])) {
                foreach ($extensionData['groups'] as $groupData) {
                    // Verificar se grupo já existe
                    $existingGroup = $db->selectOne("SELECT id FROM groups WHERE group_id = ? AND user_id = ?", 
                        [$groupData['group_id'], $user['id']]);
                    
                    if (!$existingGroup) {
                        // Inserir novo grupo
                        $insertData = [
                            'name' => $groupData['name'],
                            'group_id' => $groupData['group_id'],
                            'members_count' => $groupData['members_count'] ?? 0,
                            'status' => 'active',
                            'user_id' => $user['id'],
                            'extension_connected' => 1,
                            'last_sync' => date('Y-m-d H:i:s'),
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        if (isset($groupData['avatar_url'])) {
                            $insertData['avatar_url'] = $groupData['avatar_url'];
                        }
                        
                        $db->insert('groups', $insertData);
                    } else {
                        // Atualizar grupo existente
                        $updateData = [
                            'name' => $groupData['name'],
                            'members_count' => $groupData['members_count'] ?? 0,
                            'extension_connected' => 1,
                            'last_sync' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        if (isset($groupData['avatar_url'])) {
                            $updateData['avatar_url'] = $groupData['avatar_url'];
                        }
                        
                        $db->update('groups', $updateData, 'id = ?', [$existingGroup['id']]);
                    }
                }
                
                $message = count($extensionData['groups']) . ' grupos sincronizados com sucesso!';
                $messageType = 'success';
            }
        }
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'toggle_status':
                    $db->update('groups', 
                        ['status' => $_POST['new_status'], 'updated_at' => date('Y-m-d H:i:s')], 
                        'id = ? AND user_id = ?', 
                        [$_POST['group_id'], $user['id']]
                    );
                    $message = 'Status do grupo atualizado!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_group':
                    $db->delete('groups', 'id = ? AND user_id = ?', [$_POST['group_id'], $user['id']]);
                    $message = 'Grupo removido com sucesso!';
                    $messageType = 'success';
                    break;
                    
                case 'sync_extension':
                    // Marcar para sincronização
                    $message = 'Solicitação de sincronização enviada. Use a extensão para atualizar os grupos.';
                    $messageType = 'info';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'Erro: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Buscar grupos
$groups = [];
$totalGroups = 0;
$activeGroups = 0;
$connectedGroups = 0;

try {
    $db = Database::getInstance();
    
    if ($db->tableExists('groups')) {
        if ($isAdmin) {
            $groups = $db->select("SELECT * FROM groups ORDER BY extension_connected DESC, created_at DESC");
        } else {
            $groups = $db->select("SELECT * FROM groups WHERE user_id = ? ORDER BY extension_connected DESC, created_at DESC", [$user['id']]);
        }
        
        $totalGroups = count($groups);
        $activeGroups = count(array_filter($groups, function($g) { return $g['status'] === 'active'; }));
        $connectedGroups = count(array_filter($groups, function($g) { return $g['extension_connected'] == 1; }));
    }
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// Função para formatar tempo relativo
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'agora mesmo';
    if ($time < 3600) return floor($time/60) . ' min atrás';
    if ($time < 86400) return floor($time/3600) . ' h atrás';
    if ($time < 2592000) return floor($time/86400) . ' dias atrás';
    
    return date('d/m/Y', strtotime($datetime));
}

$pageTitle = "Grupos";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - PostGrupo Facebook</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Cores principais */
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --accent: #f093fb;
            
            /* Cores de status */
            --success: #00ff88;
            --success-light: rgba(0, 255, 136, 0.1);
            --warning: #feca57;
            --warning-light: rgba(254, 202, 87, 0.1);
            --error: #ff6b6b;
            --error-light: rgba(255, 107, 107, 0.1);
            --info: #4facfe;
            --info-light: rgba(79, 172, 254, 0.1);
            
            /* Cores neutras */
            --white: #ffffff;
            --light-gray: #f8fafc;
            --gray: #e2e8f0;
            --dark-gray: #64748b;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #a0aec0;
            
            /* Layout */
            --sidebar-width: 300px;
            --header-height: 80px;
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Gradientes únicos */
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --gradient-5: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --gradient-6: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --gradient-7: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --gradient-8: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);
            --gradient-9: linear-gradient(135deg, #fad0c4 0%, #ffd1ff 100%);
            --gradient-10: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            --gradient-11: linear-gradient(135deg, #a8caba 0%, #5d4e75 100%);
            --gradient-12: linear-gradient(135deg, #ff8a80 0%, #ea4335 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light-gray);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Sidebar com cantos arredondados */
        .sidebar {
            position: fixed;
            left: 15px;
            top: 15px;
            width: calc(var(--sidebar-width) - 15px);
            height: calc(100vh - 30px);
            background: var(--white);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
        }

        .sidebar-header {
            background: var(--gradient-1);
            padding: 25px 20px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .brand-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }

        /* Logo do Facebook centralizada */
        .facebook-logo {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            backdrop-filter: blur(10px);
            margin-bottom: 15px;
        }

        .brand-info {
            text-align: center;
        }

        .brand-info h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .brand-info span {
            font-size: 12px;
            opacity: 0.8;
            font-weight: 500;
        }

        /* Botão menu mobile VERMELHO */
        .mobile-menu-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #e74c3c;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 10px;
            color: white;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .sidebar-content {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .menu-section {
            margin-bottom: 25px;
        }

        .section-title {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 20px 15px 20px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .menu-item {
            margin: 0 15px 8px 15px;
        }

        .menu-item a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .menu-item a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            transition: width 0.3s ease;
            z-index: 0;
        }

        .menu-item:hover a::before,
        .menu-item.active a::before {
            width: 100%;
        }

        .menu-item:hover a,
        .menu-item.active a {
            color: var(--primary);
            transform: translateX(5px);
        }

        .menu-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            position: relative;
            z-index: 1;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .dashboard-icon { background: var(--gradient-1); }
        .posts-icon { background: var(--gradient-2); }
        .groups-icon { background: var(--gradient-3); }
        .schedule-icon { background: var(--gradient-4); }
        .analytics-icon { background: var(--gradient-5); }
        .media-icon { background: var(--gradient-6); }
        .users-icon { background: var(--gradient-7); }
        .settings-icon { background: var(--gradient-8); }
        .help-icon { background: var(--gradient-9); }
        .logout-icon { background: var(--gradient-12); }

        .menu-text {
            flex: 1;
            font-weight: 500;
            font-size: 15px;
            position: relative;
            z-index: 1;
        }

        .menu-badge {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--gray);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--error);
            transition: var(--transition);
            background: rgba(229, 62, 62, 0.05);
        }

        .logout-btn:hover {
            background: rgba(229, 62, 62, 0.1);
            transform: translateX(5px);
        }

        /* Main Content */
        .main-content {
            margin-left: calc(var(--sidebar-width) + 15px);
            min-height: 100vh;
            transition: var(--transition);
            padding-right: 15px;
        }

        /* Page Header APENAS com perfil e validade */
        .page-header {
            background: var(--white);
            margin: 15px 0 25px 0;
            position: relative;
            overflow: hidden;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
        }

        .header-gradient {
            background: var(--gradient-3);
            padding: 30px 40px;
            position: relative;
            overflow: hidden;
            color: white;
        }

        .header-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }

        .header-content {
            position: relative;
            z-index: 2;
            max-width: 1400px;
            margin: 0 auto;
            text-align: center;
        }

        /* Perfil do usuário logado */
        .user-profile-header {
            margin-bottom: 0;
        }

        .user-avatar-large {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255, 255, 255, 0.3);
            margin: 0 auto 20px;
        }

        .user-name {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Data de vencimento */
        .user-expiry {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 25px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            font-size: 16px;
            font-weight: 500;
            margin: 0 auto;
            max-width: 400px;
        }

        .user-expiry.warning {
            background: rgba(254, 202, 87, 0.2);
            border: 1px solid rgba(254, 202, 87, 0.3);
        }

        .user-expiry.danger {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid rgba(255, 107, 107, 0.3);
        }

        /* Container principal */
        .groups-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Extension Integration Section */
        .extension-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: var(--border-radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .extension-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .extension-content {
            position: relative;
            z-index: 2;
        }

        .extension-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
            backdrop-filter: blur(10px);
        }

        .extension-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .extension-description {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 25px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .extension-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .extension-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .stat-content h3 {
            font-size: 40px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
            font-variant-numeric: tabular-nums;
        }

        .stat-content p {
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 12px;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            font-weight: 600;
            color: var(--success);
        }

        /* Cards específicos */
        .stat-card:nth-child(1) .stat-icon { background: var(--gradient-3); }
        .stat-card:nth-child(2) .stat-icon { background: var(--gradient-4); }
        .stat-card:nth-child(3) .stat-icon { background: var(--gradient-1); }

        /* Seção principal */
        .groups-section {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .section-header {
            padding: 25px 30px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .section-header .icon {
            color: var(--primary);
            font-size: 28px;
        }

        /* Botões */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
        }

        .btn-primary {
            background: var(--gradient-3);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--text-primary);
            border: 1px solid var(--gray);
        }

        .btn-secondary:hover {
            background: var(--gray);
        }

        .btn-danger {
            background: var(--gradient-12);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-white {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .btn-white:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }

        /* Grid de grupos */
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            padding: 30px;
        }

        .group-card {
            background: var(--white);
            border: 1px solid var(--gray);
            border-radius: var(--border-radius-lg);
            padding: 25px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .group-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .group-card.extension-connected {
            border-color: var(--success);
            background: linear-gradient(135deg, rgba(0, 255, 136, 0.02), rgba(255, 255, 255, 1));
        }

        .group-card-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
        }

        .group-avatar {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: var(--gradient-3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
            background-image: var(--avatar-url, none);
            background-size: cover;
            background-position: center;
        }

        .group-avatar.has-image {
            font-size: 0;
        }

        .group-info {
            flex: 1;
            min-width: 0;
        }

        .group-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            word-break: break-word;
        }

        .group-id {
            font-size: 12px;
            color: var(--text-muted);
            font-family: monospace;
            background: var(--light-gray);
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        .extension-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--success);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .group-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
        }

        .group-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: var(--success-light);
            color: var(--success);
        }

        .status-inactive {
            background: var(--error-light);
            color: var(--error);
        }

        .status-pending {
            background: var(--warning-light);
            color: var(--warning);
        }

        .group-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 80px;
            margin-bottom: 25px;
            color: var(--text-muted);
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .empty-state p {
            margin-bottom: 30px;
            font-size: 16px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Alertas */
        .alert {
            padding: 16px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: var(--success-light);
            border-color: var(--success);
            color: #0d7346;
        }

        .alert-error {
            background: var(--error-light);
            border-color: var(--error);
            color: #c53030;
        }

        .alert-warning {
            background: var(--warning-light);
            border-color: var(--warning);
            color: #b7791f;
        }

        .alert-info {
            background: var(--info-light);
            border-color: var(--info);
            color: var(--info);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: flex;
            }
            
            .sidebar {
                left: -100%;
                top: 0;
                width: var(--sidebar-width);
                height: 100vh;
                border-radius: 0 var(--border-radius-lg) var(--border-radius-lg) 0;
                margin: 0;
            }
            
            .sidebar.open {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .page-header {
                margin: 0 0 25px 0;
            }
            
            .header-gradient {
                padding: 20px;
            }
            
            .groups-container {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .groups-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }
            
            .section-header {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .group-actions {
                flex-direction: column;
            }
            
            .extension-actions {
                flex-direction: column;
                align-items: center;
            }
        }

        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }

        .group-card {
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: both;
        }

        .extension-section {
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: both;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand-section">
                <!-- Logo do Facebook centralizada ACIMA do nome -->
                <div class="facebook-logo">
                    <i class="fab fa-facebook-f"></i>
                </div>
                <div class="brand-info">
                    <h3>PostGrupo</h3>
                    <span>Facebook Manager</span>
                </div>
                <!-- Botão menu mobile VERMELHO -->
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="sidebar-content">
            <div class="menu-section">
                <span class="section-title">Principal</span>
                <ul class="sidebar-menu">
                    <li class="menu-item">
                        <a href="dashboard.php">
                            <div class="menu-icon dashboard-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <span class="menu-text">Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="posts.php">
                            <div class="menu-icon posts-icon">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <span class="menu-text">Posts</span>
                        </a>
                    </li>
                    
                    <li class="menu-item active">
                        <a href="groups.php">
                            <div class="menu-icon groups-icon">
                                <i class="fab fa-facebook"></i>
                            </div>
                            <span class="menu-text">Grupos</span>
                            <?php if ($totalGroups > 0): ?>
                                <span class="menu-badge"><?php echo $totalGroups; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="schedule.php">
                            <div class="menu-icon schedule-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <span class="menu-text">Agendamentos</span>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="analytics.php">
                            <div class="menu-icon analytics-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <span class="menu-text">Relatórios</span>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="media.php">
                            <div class="menu-icon media-icon">
                                <i class="fas fa-images"></i>
                            </div>
                            <span class="menu-text">Mídia</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <?php if ($isAdmin): ?>
            <div class="menu-section">
                <span class="section-title">Administração</span>
                <ul class="sidebar-menu">
                    <li class="menu-item">
                        <a href="users.php">
                            <div class="menu-icon users-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <span class="menu-text">Usuários</span>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="settings.php">
                            <div class="menu-icon settings-icon">
                                <i class="fas fa-cog"></i>
                            </div>
                            <span class="menu-text">Configurações</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="menu-section">
                <span class="section-title">Suporte</span>
                <ul class="sidebar-menu">
                    <li class="menu-item">
                        <a href="help.php">
                            <div class="menu-icon help-icon">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <span class="menu-text">Ajuda</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <div class="menu-icon logout-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span class="menu-text">Sair</span>
            </a>
        </div>
    </nav>

    <!-- Botão Mobile Menu VERMELHO -->
    <button class="mobile-menu-btn" id="mobileMenuToggle" style="position: fixed; top: 20px; left: 20px; width: 50px; height: 50px; background: #e74c3c; border: none; border-radius: 15px; color: white; cursor: pointer; z-index: 1001; display: none; align-items: center; justify-content: center; box-shadow: var(--shadow);">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header APENAS COM PERFIL E VALIDADE -->
        <div class="page-header">
            <div class="header-gradient">
                <div class="header-content">
                    <!-- APENAS perfil do usuário logado e validade -->
                    <div class="user-profile-header">
                        <div class="user-avatar-large">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                        
                        <!-- APENAS data de vencimento do banco -->
                        <?php if (!empty($user['expiry_date'])): ?>
                            <?php
                            $today = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                            $expiry = new DateTime($user['expiry_date'], new DateTimeZone('America/Sao_Paulo'));
                            $daysLeft = $today->diff($expiry)->days;
                            
                            $expiryClass = '';
                            if ($expiry < $today) {
                                $expiryClass = 'danger';
                                $daysLeft = -1;
                            } elseif ($daysLeft <= 7) {
                                $expiryClass = 'danger';
                            } elseif ($daysLeft <= 30) {
                                $expiryClass = 'warning';
                            }
                            ?>
                            <div class="user-expiry <?php echo $expiryClass; ?>">
                                <?php if ($daysLeft == -1): ?>
                                    ❌ Plano Expirado - Renove para continuar
                                <?php elseif ($daysLeft > 30): ?>
                                    ✅ Plano Ativo até <?php echo $expiry->format('d/m/Y'); ?>
                                <?php elseif ($daysLeft > 7): ?>
                                    ⚠️ Expira em <?php echo $daysLeft; ?> dias (<?php echo $expiry->format('d/m/Y'); ?>)
                                <?php else: ?>
                                    🚨 Expira em <?php echo $daysLeft; ?> dias!
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Container dos Grupos -->
        <div class="groups-container">
            <!-- Mensagens -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Alerta se banco não estiver configurado -->
            <?php if (isset($dbError)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Atenção:</strong> Problema de conexão com banco de dados. 
                        <a href="fix_database.php" style="color: inherit; text-decoration: underline;">Clique aqui para configurar</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Seção da Extensão -->
            <div class="extension-section">
                <div class="extension-content">
                    <div class="extension-icon">
                        <i class="fab fa-chrome"></i>
                    </div>
                    
                    <div class="extension-status" id="extensionStatus">
                        <div class="status-dot"></div>
                        <span id="statusText">Aguardando conexão com a extensão...</span>
                    </div>
                    
                    <h2 class="extension-title">Extensão do Chrome</h2>
                    <p class="extension-description">
                        Conecte seus grupos do Facebook automaticamente usando nossa extensão do Chrome. 
                        Sincronize grupos em tempo real e gerencie postagens de forma simples e segura.
                    </p>
                    
                    <div class="extension-actions">
                        <button class="btn btn-white" id="installExtensionBtn">
                            <i class="fab fa-chrome"></i>
                            Instalar Extensão
                        </button>
                        
                        <button class="btn btn-white" id="syncGroupsBtn">
                            <i class="fas fa-sync"></i>
                            Sincronizar Grupos
                        </button>
                        
                        <button class="btn btn-white" onclick="window.open('help.php#extension', '_blank')">
                            <i class="fas fa-question-circle"></i>
                            Como Usar
                        </button>
                    </div>
                </div>
            </div>
<?php
// Conectar ao banco (ajuste as credenciais)
try {
    $pdo = new PDO("mysql:host=localhost;dbname=SEU_BANCO", "SEU_USUARIO", "SUA_SENHA");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar grupos do usuário atual
    $stmt = $pdo->prepare("SELECT * FROM facebook_groups WHERE user_login = ? ORDER BY synced_at DESC");
    $stmt->execute(['Siniclei102']);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $groups = [];
    $error = "Erro na conexão: " . $e->getMessage();
}
?>

<div class="groups-container">
    <h2>Meus Grupos</h2>
    <button onclick="location.reload()" class="btn-refresh">Atualizar</button>
    
    <?php if (!empty($groups)): ?>
        <div class="groups-list">
            <?php foreach ($groups as $group): ?>
                <div class="group-item">
                    <h3><?= htmlspecialchars($group['group_name']) ?></h3>
                    <p>ID: <?= htmlspecialchars($group['group_id']) ?></p>
                    <p>Sincronizado: <?= $group['synced_at'] ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Nenhum grupo encontrado</p>
        <?php if (isset($error)): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
        <p>Você ainda não possui grupos conectados. Use a extensão do Chrome para sincronizar automaticamente seus grupos do Facebook.</p>
    <?php endif; ?>
</div>
            <!-- Stats dos Grupos -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-content">
                            <h3><?php echo $totalGroups; ?></h3>
                            <p>Total de Grupos</p>
                            <div class="stat-trend">
                                <i class="fas fa-chart-line"></i>
                                <span>Grupos cadastrados</span>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fab fa-facebook"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-content">
                            <h3><?php echo $activeGroups; ?></h3>
                            <p>Grupos Ativos</p>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-up"></i>
                                <span>Prontos para postar</span>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-content">
                            <h3><?php echo $connectedGroups; ?></h3>
                            <p>Conectados via Extensão</p>
                            <div class="stat-trend">
                                <i class="fab fa-chrome"></i>
                                <span>Sincronizados</span>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-link"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Seção Principal dos Grupos -->
            <div class="groups-section">
                <div class="section-header">
                    <h2>
                        <i class="fab fa-facebook icon"></i>
                        Meus Grupos
                    </h2>
                    <button class="btn btn-primary" id="refreshGroupsBtn">
                        <i class="fas fa-sync"></i>
                        Atualizar
                    </button>
                </div>
                
                <?php if (empty($groups)): ?>
                    <div class="empty-state">
                        <i class="fab fa-facebook"></i>
                        <h3>Nenhum grupo encontrado</h3>
                        <p>
                            Você ainda não possui grupos conectados. Use a extensão do Chrome para 
                            sincronizar automaticamente seus grupos do Facebook.
                        </p>
                        <button class="btn btn-primary" id="connectFirstGroupBtn">
                            <i class="fab fa-chrome"></i>
                            Conectar Primeiro Grupo
                        </button>
                    </div>
                <?php else: ?>
                    <div class="groups-grid">
                        <?php foreach ($groups as $index => $group): ?>
                            <div class="group-card <?php echo $group['extension_connected'] ? 'extension-connected' : ''; ?>" style="animation-delay: <?php echo ($index * 0.1); ?>s;">
                                
                                <?php if ($group['extension_connected']): ?>
                                    <div class="extension-badge">
                                        <i class="fab fa-chrome"></i>
                                        Extensão
                                    </div>
                                <?php endif; ?>
                                
                                <div class="group-card-header">
                                    <div class="group-avatar <?php echo !empty($group['avatar_url']) ? 'has-image' : ''; ?>" 
                                         <?php if (!empty($group['avatar_url'])): ?>
                                         style="background-image: url('<?php echo htmlspecialchars($group['avatar_url']); ?>');"
                                         <?php endif; ?>>
                                        <?php if (empty($group['avatar_url'])): ?>
                                            <i class="fab fa-facebook-f"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="group-info">
                                        <h3 class="group-name"><?php echo htmlspecialchars($group['name']); ?></h3>
                                        <div class="group-id" title="Clique para copiar" onclick="copyToClipboard('<?php echo htmlspecialchars($group['group_id']); ?>')">
                                            ID: <?php echo htmlspecialchars($group['group_id']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="group-meta">
                                    <?php if (!empty($group['members_count'])): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-users"></i>
                                            <span><?php echo number_format($group['members_count']); ?> membros</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo timeAgo($group['created_at']); ?></span>
                                    </div>
                                    
                                    <?php if ($group['extension_connected'] && !empty($group['last_sync'])): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-sync"></i>
                                            <span>Sync: <?php echo timeAgo($group['last_sync']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="group-status status-<?php echo $group['status']; ?>">
                                    <i class="fas fa-<?php echo $group['status'] === 'active' ? 'check-circle' : ($group['status'] === 'inactive' ? 'times-circle' : 'clock'); ?>"></i>
                                    <?php echo ucfirst($group['status']); ?>
                                </div>
                                
                                <div class="group-actions">
                                    <?php if ($group['status'] === 'active'): ?>
                                        <button class="btn btn-warning btn-sm" onclick="toggleGroupStatus(<?php echo $group['id']; ?>, 'inactive')">
                                            <i class="fas fa-pause"></i>
                                            Pausar
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-primary btn-sm" onclick="toggleGroupStatus(<?php echo $group['id']; ?>, 'active')">
                                            <i class="fas fa-play"></i>
                                            Ativar
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-danger btn-sm" onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Forms ocultos para ações -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="group_id" id="status_group_id">
        <input type="hidden" name="new_status" id="status_new_status">
    </form>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_group">
        <input type="hidden" name="group_id" id="delete_group_id">
    </form>

    <form id="extensionForm" method="POST" style="display: none;">
        <input type="hidden" name="extension_data" id="extension_data">
    </form>
    
    <script>
        // Mobile menu functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        
        function toggleSidebar() {
            sidebar.classList.toggle('open');
        }
        
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', toggleSidebar);
        }
        
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', toggleSidebar);
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
                if (!sidebar.contains(event.target) && 
                    !mobileMenuToggle.contains(event.target) && 
                    !mobileMenuBtn.contains(event.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
        
                // Show mobile menu button on small screens
        function checkScreenSize() {
            if (window.innerWidth <= 768) {
                mobileMenuToggle.style.display = 'flex';
            } else {
                mobileMenuToggle.style.display = 'none';
                sidebar.classList.remove('open');
            }
        }
        
        window.addEventListener('resize', checkScreenSize);
        checkScreenSize();
        
        // Função para alterar status do grupo
        function toggleGroupStatus(groupId, newStatus) {
            const action = newStatus === 'active' ? 'ativar' : 'pausar';
            const groupCard = document.querySelector(`[onclick*="toggleGroupStatus(${groupId}"]`).closest('.group-card');
            const groupName = groupCard.querySelector('.group-name').textContent;
            
            if (confirm(`Tem certeza que deseja ${action} o grupo "${groupName}"?`)) {
                document.getElementById('status_group_id').value = groupId;
                document.getElementById('status_new_status').value = newStatus;
                document.getElementById('statusForm').submit();
            }
        }
        
        // Função para excluir grupo
        function deleteGroup(groupId, groupName) {
            if (confirm(`Tem certeza que deseja excluir o grupo "${groupName}"?\n\nEsta ação não pode ser desfeita.`)) {
                document.getElementById('delete_group_id').value = groupId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Função para copiar para clipboard
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    showToast('ID copiado para a área de transferência!', 'success', 2000);
                }).catch(function() {
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                fallbackCopyTextToClipboard(text);
            }
        }
        
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.top = '0';
            textArea.style.left = '0';
            textArea.style.position = 'fixed';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showToast('ID copiado!', 'success', 2000);
            } catch (err) {
                showToast('Erro ao copiar ID', 'error', 3000);
            }
            
            document.body.removeChild(textArea);
        }
        
        // Sistema de comunicação com extensão
        class ExtensionConnector {
            constructor() {
                this.isConnected = false;
                this.extensionId = 'postgrupo-facebook-extension'; // ID da extensão
                this.checkConnection();
                this.setupEventListeners();
            }
            
            checkConnection() {
                // Verificar se a extensão está instalada
                if (window.chrome && window.chrome.runtime) {
                    this.pingExtension();
                } else {
                    this.updateStatus('disconnected', 'Extensão não detectada');
                }
            }
            
            pingExtension() {
                // Simular ping para extensão
                const pingInterval = setInterval(() => {
                    // Aqui você faria a comunicação real com a extensão
                    // Por enquanto, vamos simular
                    if (Math.random() > 0.3) { // 70% chance de estar conectada
                        this.updateStatus('connected', 'Extensão conectada');
                        clearInterval(pingInterval);
                    }
                }, 2000);
                
                // Timeout após 10 segundos
                setTimeout(() => {
                    if (!this.isConnected) {
                        this.updateStatus('disconnected', 'Extensão não encontrada');
                        clearInterval(pingInterval);
                    }
                }, 10000);
            }
            
            updateStatus(status, message) {
                const statusElement = document.getElementById('extensionStatus');
                const statusText = document.getElementById('statusText');
                const statusDot = statusElement.querySelector('.status-dot');
                
                if (status === 'connected') {
                    this.isConnected = true;
                    statusDot.style.background = 'var(--success)';
                    statusText.textContent = message;
                    statusElement.style.background = 'rgba(0, 255, 136, 0.2)';
                } else {
                    this.isConnected = false;
                    statusDot.style.background = 'var(--error)';
                    statusText.textContent = message;
                    statusElement.style.background = 'rgba(255, 107, 107, 0.2)';
                }
            }
            
            setupEventListeners() {
                // Listener para mensagens da extensão
                window.addEventListener('message', (event) => {
                    if (event.origin !== window.location.origin) return;
                    
                    if (event.data.type === 'POSTGRUPO_EXTENSION') {
                        this.handleExtensionMessage(event.data);
                    }
                });
            }
            
            handleExtensionMessage(data) {
                switch (data.action) {
                    case 'GROUPS_SYNC':
                        this.syncGroups(data.groups);
                        break;
                    case 'CONNECTION_STATUS':
                        this.updateStatus('connected', 'Extensão ativa');
                        break;
                    case 'ERROR':
                        showToast('Erro na extensão: ' + data.message, 'error');
                        break;
                }
            }
            
            syncGroups(groups) {
                if (!groups || groups.length === 0) {
                    showToast('Nenhum grupo encontrado para sincronizar', 'warning');
                    return;
                }
                
                // Preparar dados para envio
                const extensionData = {
                    groups: groups,
                    timestamp: new Date().toISOString(),
                    user_id: '<?php echo $user['id']; ?>'
                };
                
                // Enviar para o servidor
                document.getElementById('extension_data').value = JSON.stringify(extensionData);
                document.getElementById('extensionForm').submit();
            }
            
            requestSync() {
                if (!this.isConnected) {
                    showToast('Extensão não conectada. Instale a extensão primeiro.', 'warning');
                    return;
                }
                
                // Enviar mensagem para a extensão
                window.postMessage({
                    type: 'POSTGRUPO_REQUEST',
                    action: 'SYNC_GROUPS'
                }, '*');
                
                showToast('Solicitação de sincronização enviada...', 'info', 3000);
            }
            
            openInstallPage() {
                // URL da extensão na Chrome Web Store (substitua pelo URL real)
                const extensionUrl = 'https://chrome.google.com/webstore/detail/postgrupo-facebook/extensionid';
                window.open(extensionUrl, '_blank');
            }
        }
        
        // Inicializar conector da extensão
        const extensionConnector = new ExtensionConnector();
        
        // Event listeners para botões da extensão
        document.getElementById('installExtensionBtn').addEventListener('click', function() {
            extensionConnector.openInstallPage();
        });
        
        document.getElementById('syncGroupsBtn').addEventListener('click', function() {
            extensionConnector.requestSync();
        });
        
        document.getElementById('refreshGroupsBtn').addEventListener('click', function() {
            location.reload();
        });
        
        document.getElementById('connectFirstGroupBtn').addEventListener('click', function() {
            extensionConnector.requestSync();
        });
        
        // Notificações toast
        function showToast(message, type = 'info', duration = 5000) {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; margin-left: auto;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--white);
                color: var(--text-primary);
                padding: 15px 20px;
                border-radius: 12px;
                box-shadow: var(--shadow-lg);
                z-index: 10001;
                border-left: 4px solid var(--${type === 'success' ? 'success' : type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'info'});
                animation: slideInRight 0.3s ease;
                max-width: 400px;
                min-width: 300px;
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.style.animation = 'slideInRight 0.3s ease reverse';
                    setTimeout(() => toast.remove(), 300);
                }
            }, duration);
        }
        
        // Adicionar animação CSS para toast
        const toastStyle = document.createElement('style');
        toastStyle.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(toastStyle);
        
        // Animações de entrada para os cards
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        document.querySelectorAll('.stat-card, .group-card, .extension-section').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
            observer.observe(card);
        });
        
        // Auto-refresh da página a cada 5 minutos para sincronização
        let autoRefreshInterval = setInterval(() => {
            if (extensionConnector.isConnected) {
                console.log('Auto-refresh: Checking for new groups...');
                // Aqui você pode implementar uma verificação AJAX em vez de reload completo
            }
        }, 300000); // 5 minutos
        
        // Verificação de conectividade
        function checkConnectivity() {
            if (!navigator.onLine) {
                showToast('Você está offline. A sincronização com a extensão pode não funcionar.', 'warning');
            }
        }
        
        window.addEventListener('online', function() {
            showToast('Conexão restaurada! Tentando reconectar com a extensão...', 'success', 3000);
            extensionConnector.checkConnection();
        });
        
        window.addEventListener('offline', checkConnectivity);
        
        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + R para refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                location.reload();
            }
            
            // Ctrl + S para sincronizar
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                extensionConnector.requestSync();
            }
            
            // Ctrl + I para instalar extensão
            if (e.ctrlKey && e.key === 'i') {
                e.preventDefault();
                extensionConnector.openInstallPage();
            }
        });
        
        // Função para simular dados da extensão (para testes)
        function simulateExtensionData() {
            const sampleGroups = [
                {
                    name: 'Grupo de Teste 1',
                    group_id: '123456789',
                    members_count: 1500,
                    avatar_url: 'https://via.placeholder.com/60x60/4facfe/white?text=G1'
                },
                {
                    name: 'Grupo de Teste 2',
                    group_id: '987654321',
                    members_count: 2300,
                    avatar_url: 'https://via.placeholder.com/60x60/43e97b/white?text=G2'
                }
            ];
            
            extensionConnector.syncGroups(sampleGroups);
        }
        
        // Modo de desenvolvimento - ativar apenas em ambiente de teste
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            // Adicionar botão de teste
            const testButton = document.createElement('button');
            testButton.textContent = '🧪 Testar Extensão';
            testButton.className = 'btn btn-secondary btn-sm';
            testButton.style.position = 'fixed';
            testButton.style.bottom = '20px';
            testButton.style.right = '20px';
            testButton.style.zIndex = '10000';
            testButton.onclick = simulateExtensionData;
            document.body.appendChild(testButton);
        }
        
        // Atualizar estatísticas em tempo real
        function updateStats() {
            const groupCards = document.querySelectorAll('.group-card');
            const connectedGroups = document.querySelectorAll('.extension-connected').length;
            const activeGroups = document.querySelectorAll('.status-active').length;
            const totalGroups = groupCards.length;
            
            // Atualizar contadores na interface
            const statCards = document.querySelectorAll('.stat-card h3');
            if (statCards[0]) statCards[0].textContent = totalGroups;
            if (statCards[1]) statCards[1].textContent = activeGroups;
            if (statCards[2]) statCards[2].textContent = connectedGroups;
        }
        
        // Busca em tempo real
        function initSearch() {
            if (document.querySelectorAll('.group-card').length === 0) return;
            
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Buscar grupos...';
            searchInput.className = 'form-control';
            searchInput.style.maxWidth = '300px';
            searchInput.style.cssText = `
                width: 300px;
                padding: 10px 15px;
                border: 1px solid var(--gray);
                border-radius: var(--border-radius);
                font-size: 14px;
                background: var(--white);
            `;
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const groupCards = document.querySelectorAll('.group-card');
                
                groupCards.forEach(card => {
                    const groupName = card.querySelector('.group-name').textContent.toLowerCase();
                    const groupId = card.querySelector('.group-id').textContent.toLowerCase();
                    
                    if (groupName.includes(searchTerm) || groupId.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
            
            // Adicionar à header
            const sectionHeader = document.querySelector('.section-header');
            if (sectionHeader) {
                const refreshButton = sectionHeader.querySelector('#refreshGroupsBtn');
                sectionHeader.insertBefore(searchInput, refreshButton);
            }
        }
        
        // Performance monitoring
        function logPerformance() {
            if (window.performance && window.performance.timing) {
                const timing = window.performance.timing;
                const loadTime = timing.loadEventEnd - timing.navigationStart;
                console.log(`Página de grupos carregada em ${loadTime}ms`);
                
                // Log específico para grupos
                const groupCount = document.querySelectorAll('.group-card').length;
                console.log(`${groupCount} grupos renderizados`);
            }
        }
        
        // Inicializar todas as funcionalidades
        document.addEventListener('DOMContentLoaded', function() {
            updateStats();
            initSearch();
            
            // Mostrar dica de boas-vindas se não houver grupos
            if (document.querySelectorAll('.group-card').length === 0 && !localStorage.getItem('groups_extension_welcome_shown')) {
                setTimeout(() => {
                    showToast('💡 Dica: Use a extensão do Chrome para conectar seus grupos automaticamente!', 'info', 8000);
                    localStorage.setItem('groups_extension_welcome_shown', 'true');
                }, 3000);
            }
            
            console.log('Página de grupos com extensão inicializada! 🚀');
        });
        
        // Cleanup ao sair da página
        window.addEventListener('beforeunload', function() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        });
        
        // Performance e logging
        window.addEventListener('load', logPerformance);
    </script>
</body>
</html>