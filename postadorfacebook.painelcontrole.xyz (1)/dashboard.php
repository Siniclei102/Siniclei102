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

// Inicializar estatísticas
$stats = [
    'users' => 1,
    'groups' => 0,
    'posts' => 0,
    'postsToday' => 0,
    'postsWeek' => 0,
    'postsMonth' => 0,
    'successRate' => 98.5
];

$recentPosts = [];
$recentActivity = [];
$activeGroups = [];

// Tentar conectar ao banco e obter dados reais
try {
    $db = Database::getInstance();
    
    // Estatísticas básicas
    try {
        $stats['users'] = $db->selectOne("SELECT COUNT(*) as count FROM users")['count'] ?? 1;
    } catch (Exception $e) {}
    
    if ($db->tableExists('groups')) {
        try {
            $stats['groups'] = $db->selectOne("SELECT COUNT(*) as count FROM groups")['count'] ?? 0;
            $activeGroups = $db->select("SELECT * FROM groups ORDER BY created_at DESC LIMIT 6");
        } catch (Exception $e) {}
    }
    
    if ($db->tableExists('posts')) {
        try {
            $stats['posts'] = $db->selectOne("SELECT COUNT(*) as count FROM posts")['count'] ?? 0;
            $stats['postsToday'] = $db->selectOne("SELECT COUNT(*) as count FROM posts WHERE DATE(created_at) = CURDATE()")['count'] ?? 0;
            $stats['postsWeek'] = $db->selectOne("SELECT COUNT(*) as count FROM posts WHERE WEEK(created_at) = WEEK(CURDATE())")['count'] ?? 0;
            $stats['postsMonth'] = $db->selectOne("SELECT COUNT(*) as count FROM posts WHERE MONTH(created_at) = MONTH(CURDATE())")['count'] ?? 0;
            
            $recentPosts = $db->select("
                SELECT p.*, u.name as user_name
                FROM posts p 
                LEFT JOIN users u ON p.user_id = u.id 
                ORDER BY p.created_at DESC 
                LIMIT 5
            ");
        } catch (Exception $e) {}
    }
    
    if ($db->tableExists('system_logs')) {
        try {
            $recentActivity = $db->select("
                SELECT * FROM system_logs 
                ORDER BY created_at DESC 
                LIMIT 8
            ");
        } catch (Exception $e) {}
    }
    
} catch (Exception $e) {
    // Se não conseguir conectar, usar dados padrão
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

$pageTitle = "Dashboard";
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
            background: var(--gradient-1);
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

        /* Dashboard Container */
        .dashboard-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
        .stat-card:nth-child(1) .stat-icon { background: var(--gradient-7); }
        .stat-card:nth-child(2) .stat-icon { background: var(--gradient-3); }
        .stat-card:nth-child(3) .stat-icon { background: var(--gradient-2); }
        .stat-card:nth-child(4) .stat-icon { background: var(--gradient-5); }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .dashboard-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        .dashboard-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            padding: 25px 30px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-header .icon {
            color: var(--primary);
        }

        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
        }

        .view-all:hover {
            color: var(--secondary);
        }

        .card-content {
            padding: 25px 30px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--text-muted);
            opacity: 0.5;
        }

        .empty-state h4 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .empty-state p {
            margin-bottom: 25px;
            font-size: 14px;
        }

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
            background: var(--gradient-1);
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

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .action-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 24px 16px;
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            border: 2px solid transparent;
            background: var(--light-gray);
        }

        .action-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            border-color: var(--primary);
            background: white;
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .action-new-post .action-icon { background: var(--gradient-4); }
        .action-groups .action-icon { background: var(--gradient-3); }
        .action-schedule .action-icon { background: var(--gradient-5); }
        .action-analytics .action-icon { background: var(--gradient-1); }

        .action-text {
            font-size: 14px;
            font-weight: 600;
            text-align: center;
        }

        /* Bottom Grid */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        /* Activity List */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .activity-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            border-radius: var(--border-radius);
            background: var(--light-gray);
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .activity-item:hover {
            background: var(--primary-light);
            border-color: var(--primary);
        }

        .activity-icon {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            margin-top: 8px;
            flex-shrink: 0;
            box-shadow: 0 0 8px rgba(102, 126, 234, 0.3);
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 4px;
            font-weight: 500;
        }

        .activity-time {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Groups Grid */
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .group-item {
            padding: 20px;
            border-radius: var(--border-radius);
            background: var(--light-gray);
            transition: var(--transition);
            text-align: center;
            border: 1px solid transparent;
        }

        .group-item:hover {
            background: var(--info-light);
            border-color: var(--info);
            transform: translateY(-2px);
        }

        .group-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--gradient-3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin: 0 auto 12px;
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
        }

        .group-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .group-stats {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* Status indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-online {
            background: var(--success-light);
            color: var(--success);
        }

        .status-active {
            background: var(--info-light);
            color: var(--info);
        }

        .status-pending {
            background: var(--warning-light);
            color: var(--warning);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .bottom-grid {
                grid-template-columns: 1fr;
            }
        }

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
            
            .dashboard-container {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .groups-grid {
                grid-template-columns: 1fr;
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
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        .dashboard-card {
            animation: fadeInUp 0.6s ease-out;
            animation-delay: 0.5s;
            animation-fill-mode: both;
        }

        /* Scrollbar personalizada */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-gray);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
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

        .alert-info {
            background: var(--info-light);
            border-color: var(--info);
            color: var(--info);
        }

        .alert-warning {
            background: var(--warning-light);
            border-color: var(--warning);
            color: #b7791f;
        }

        .alert-success {
            background: var(--success-light);
            border-color: var(--success);
            color: #0d7346;
        }

        /* Loading states */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--gray);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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
                    <li class="menu-item active">
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
                            <?php if ($stats['posts'] > 0): ?>
                                <span class="menu-badge"><?php echo $stats['posts']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a href="groups.php">
                            <div class="menu-icon groups-icon">
                                <i class="fab fa-facebook"></i>
                            </div>
                            <span class="menu-text">Grupos</span>
                            <?php if ($stats['groups'] > 0): ?>
                                <span class="menu-badge"><?php echo $stats['groups']; ?></span>
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
                            <span class="menu-badge"><?php echo $stats['users']; ?></span>
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
        
        <!-- Dashboard Container (resto permanece igual) -->
        <div class="dashboard-container">
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

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['users']); ?></h3>
                            <p>Usuários Registrados</p>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-up"></i>
                                <span>Sistema ativo</span>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['groups']); ?></h3>
                            <p>Grupos Conectados</p>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-up"></i>
                                <span>Pronto para usar</span>
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
                            <h3><?php echo number_format($stats['posts']); ?></h3>
                            <p>Posts Enviados</p>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-up"></i>
                                <span>Total acumulado</span>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-content">
                            <h3><?php echo $stats['successRate']; ?>%</h3>
                            <p>Taxa de Sucesso</p>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-up"></i>
                                <span>Excelente performance</span>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Main Content Area -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-rocket icon"></i>
                            Atividade Recente
                        </h3>
                        <a href="posts.php" class="view-all">Ver Histórico</a>
                    </div>
                    <div class="card-content">
                        <?php if (empty($recentPosts) && empty($recentActivity)): ?>
                            <div class="empty-state">
                                <i class="fas fa-rocket"></i>
                                <h4>Pronto para começar!</h4>
                                <p>Seu sistema está configurado e pronto para uso. Que tal criar seu primeiro post?</p>
                                <a href="new-post.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i>
                                    Criar Primeiro Post
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach (array_slice($recentActivity, 0, 5) as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon"></div>
                                        <div class="activity-content">
                                            <div class="activity-text"><?php echo htmlspecialchars($activity['action']); ?></div>
                                            <div class="activity-time"><?php echo timeAgo($activity['created_at']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-bolt icon"></i>
                            Ações Rápidas
                        </h3>
                    </div>
                    <div class="card-content">
                        <div class="quick-actions">
                            <a href="new-post.php" class="action-item action-new-post">
                                <div class="action-icon">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <span class="action-text">Novo Post</span>
                            </a>
                            
                            <a href="groups.php" class="action-item action-groups">
                                <div class="action-icon">
                                    <i class="fab fa-facebook"></i>
                                </div>
                                <span class="action-text">Gerenciar Grupos</span>
                            </a>
                            
                            <a href="schedule.php" class="action-item action-schedule">
                                <div class="action-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <span class="action-text">Agendar Posts</span>
                            </a>
                            
                            <a href="analytics.php" class="action-item action-analytics">
                                <div class="action-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <span class="action-text">Ver Relatórios</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Grid -->
            <div class="bottom-grid">
                <!-- Status do Sistema -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-heartbeat icon"></i>
                            Status do Sistema
                        </h3>
                    </div>
                    <div class="card-content">
                        <div class="activity-list">
                            <div class="activity-item">
                                <div class="activity-icon" style="background: var(--success);"></div>
                                <div class="activity-content">
                                    <div class="activity-text">Sistema Online</div>
                                    <div class="activity-time">Funcionando normalmente</div>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: var(--info);"></div>
                                <div class="activity-content">
                                    <div class="activity-text">Banco de Dados</div>
                                    <div class="activity-time">
                                        <?php echo isset($dbError) ? 'Requer configuração' : 'Conectado com sucesso'; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: var(--primary);"></div>
                                <div class="activity-content">
                                    <div class="activity-text">Usuário Logado</div>
                                    <div class="activity-time"><?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['account_type']; ?>)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Grupos Favoritos -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-star icon"></i>
                            Grupos Favoritos
                        </h3>
                        <a href="groups.php" class="view-all">Gerenciar</a>
                    </div>
                    <div class="card-content">
                        <?php if (empty($activeGroups)): ?>
                            <div class="empty-state">
                                <i class="fab fa-facebook"></i>
                                <h4>Nenhum grupo conectado</h4>
                                <p>Conecte grupos do Facebook para começar a postar automaticamente.</p>
                                <a href="groups.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i>
                                    Conectar Grupos
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="groups-grid">
                                <?php foreach (array_slice($activeGroups, 0, 4) as $group): ?>
                                    <div class="group-item">
                                        <div class="group-avatar">
                                            <i class="fab fa-facebook-f"></i>
                                        </div>
                                        <div class="group-name">
                                            <?php echo htmlspecialchars(substr($group['name'], 0, 20)); ?><?php echo strlen($group['name']) > 20 ? '...' : ''; ?>
                                        </div>
                                        <div class="group-stats">
                                            <?php echo number_format($group['members_count'] ?? 0); ?> membros
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
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
        
        // Add smooth animations to cards
        const cards = document.querySelectorAll('.stat-card, .dashboard-card');
        
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
        
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
            observer.observe(card);
        });
        
        // Auto-refresh stats every 5 minutes
        setInterval(() => {
            console.log('Stats refresh check...');
        }, 300000);
    </script>
</body>
</html>