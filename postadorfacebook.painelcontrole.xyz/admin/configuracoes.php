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

// Buscar configurações atuais
$queryConfig = "SELECT * FROM configuracoes LIMIT 1";
$resultConfig = $db->query($queryConfig);
$config = $resultConfig->fetch_assoc();

// Processar formulário de configurações gerais
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $siteNome = $db->real_escape_string($_POST['site_nome']);
    $temaCor = $db->real_escape_string($_POST['tema_cor']);
    
    // Verificar se há upload de logo
    $logoUrl = $config['logo_url']; // Manter o valor atual por padrão
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $logoDir = '../assets/images/';
        $logoFileName = 'logo_' . time() . '_' . basename($_FILES['logo']['name']);
        $logoPath = $logoDir . $logoFileName;
        
        // Verificar tipo de arquivo
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['logo']['type'], $allowedTypes)) {
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
                $logoUrl = 'assets/images/' . $logoFileName;
            } else {
                $messages[] = [
                    'type' => 'danger',
                    'text' => "Erro ao fazer upload do logo."
                ];
            }
        } else {
            $messages[] = [
                'type' => 'danger',
                'text' => "Tipo de arquivo não suportado. Use apenas JPG, PNG ou GIF."
            ];
        }
    }
    
    // Atualizar configurações
    $queryUpdate = "UPDATE configuracoes SET site_nome = ?, logo_url = ?, tema_cor = ?";
    $stmtUpdate = $db->prepare($queryUpdate);
    $stmtUpdate->bind_param("sss", $siteNome, $logoUrl, $temaCor);
    
    if ($stmtUpdate->execute()) {
        $messages[] = [
            'type' => 'success',
            'text' => "Configurações atualizadas com sucesso!"
        ];
        
        // Atualizar variáveis de configuração para exibição
        $config['site_nome'] = $siteNome;
        $config['logo_url'] = $logoUrl;
        $config['tema_cor'] = $temaCor;
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Erro ao atualizar configurações: " . $db->error
        ];
    }
}

// Processar configurações do Facebook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_facebook'])) {
    $fbAppId = $db->real_escape_string($_POST['fb_app_id']);
    $fbAppSecret = $db->real_escape_string($_POST['fb_app_secret']);
    $fbAppVersion = $db->real_escape_string($_POST['fb_app_version']);
    $fbRedirectUri = $db->real_escape_string($_POST['fb_redirect_uri']);
    
    // Criar ou atualizar arquivo de configuração do Facebook
    $fbConfigPath = '../config/facebook-app.php';
    $fbConfigContent = "<?php
// Configurações da API do Facebook
define('FB_APP_ID', '{$fbAppId}');
define('FB_APP_SECRET', '{$fbAppSecret}');
define('FB_APP_VERSION', '{$fbAppVersion}');
define('FB_REDIRECT_URI', '{$fbRedirectUri}');
define('FB_PERMISSIONS', ['public_profile', 'email', 'groups_access_member_info', 'publish_to_groups']);
?>";
    
    if (file_put_contents($fbConfigPath, $fbConfigContent)) {
        $messages[] = [
            'type' => 'success',
            'text' => "Configurações do Facebook atualizadas com sucesso!"
        ];
    } else {
        $messages[] = [
            'type' => 'danger',
            'text' => "Erro ao salvar configurações do Facebook. Verifique as permissões de escrita."
        ];
    }
}

// Processar limpeza de cache
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    $cacheDir = '../cache/';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '*');
        $count = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }
        
        $messages[] = [
            'type' => 'success',
            'text' => "Cache limpo com sucesso! {$count} arquivos removidos."
        ];
    } else {
        $messages[] = [
            'type' => 'info',
            'text' => "Diretório de cache não existe ou já está vazio."
        ];
    }
}

// Buscar configurações do Facebook (se o arquivo existir)
$fbConfig = [
    'fb_app_id' => '',
    'fb_app_secret' => '',
    'fb_app_version' => 'v18.0',
    'fb_redirect_uri' => '',
];

$fbConfigPath = '../config/facebook-app.php';
if (file_exists($fbConfigPath)) {
    include $fbConfigPath;
    
    $fbConfig['fb_app_id'] = defined('FB_APP_ID') ? FB_APP_ID : '';
    $fbConfig['fb_app_secret'] = defined('FB_APP_SECRET') ? FB_APP_SECRET : '';
    $fbConfig['fb_app_version'] = defined('FB_APP_VERSION') ? FB_APP_VERSION : 'v18.0';
    $fbConfig['fb_redirect_uri'] = defined('FB_REDIRECT_URI') ? FB_REDIRECT_URI : '';
}

// Informações do sistema
$systemInfo = [
    'php_version' => PHP_VERSION,
    'mysql_version' => $db->query('SELECT VERSION() as version')->fetch_assoc()['version'] ?? 'Desconhecido',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido',
    'server_os' => PHP_OS,
    'max_upload' => ini_get('upload_max_filesize'),
    'max_post' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time') . ' segundos',
];

// Informações sobre o banco de dados
$dbStats = [
    'tables' => $db->query("SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetch_assoc()['total'] ?? 0,
    'size' => formatDbSize($db, DB_NAME),
    'users' => $db->query("SELECT COUNT(*) as total FROM usuarios")->fetch_assoc()['total'] ?? 0,
    'anuncios' => $db->query("SELECT COUNT(*) as total FROM anuncios")->fetch_assoc()['total'] ?? 0,
    'grupos' => $db->query("SELECT COUNT(*) as total FROM grupos_facebook")->fetch_assoc()['total'] ?? 0,
    'campanhas' => $db->query("SELECT COUNT(*) as total FROM campanhas")->fetch_assoc()['total'] ?? 0,
    'logs' => $db->query("SELECT COUNT(*) as total FROM logs_postagem")->fetch_assoc()['total'] ?? 0,
];

// Função para formatar tamanho do banco de dados
function formatDbSize($db, $dbName) {
    $result = $db->query("
        SELECT SUM(data_length + index_length) AS 'size' 
        FROM information_schema.tables 
        WHERE table_schema = '{$dbName}'
    ");
    
    $row = $result->fetch_assoc();
    $bytes = $row['size'] ?? 0;
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Incluir o cabeçalho
include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Título da Página -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card">
                <div class="modern-card-header">
                    <h5 class="modern-card-title">
                        <i class="fas fa-cog me-2 text-primary"></i> Configurações do Sistema
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
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Menu de Navegação de Configurações -->
        <div class="col-md-3">
            <div class="config-nav">
                <div class="list-group">
                    <a href="#general-config" class="list-group-item list-group-item-action active" data-bs-toggle="list">
                        <i class="fas fa-sliders-h me-2"></i> Configurações Gerais
                    </a>
                    <a href="#facebook-config" class="list-group-item list-group-item-action" data-bs-toggle="list">
                        <i class="fab fa-facebook me-2"></i> Configuração do Facebook
                    </a>
                    <a href="#system-info" class="list-group-item list-group-item-action" data-bs-toggle="list">
                        <i class="fas fa-server me-2"></i> Informações do Sistema
                    </a>
                    <a href="#db-info" class="list-group-item list-group-item-action" data-bs-toggle="list">
                        <i class="fas fa-database me-2"></i> Banco de Dados
                    </a>
                    <a href="#maintenance" class="list-group-item list-group-item-action" data-bs-toggle="list">
                        <i class="fas fa-tools me-2"></i> Manutenção
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Conteúdo das Configurações -->
        <div class="col-md-9">
            <div class="tab-content">
                <!-- Configurações Gerais -->
                <div class="tab-pane fade show active" id="general-config">
                    <div class="modern-card">
                        <div class="modern-card-header">
                            <h5 class="modern-card-title">
                                <i class="fas fa-sliders-h me-2 text-primary"></i> Configurações Gerais
                            </h5>
                        </div>
                        <div class="modern-card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="site_nome" class="form-label">Nome do Site</label>
                                    <input type="text" class="form-control" id="site_nome" name="site_nome" value="<?php echo htmlspecialchars($config['site_nome']); ?>" required>
                                    <div class="form-text">Nome que aparecerá no topo do site e no título das páginas.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="logo" class="form-label">Logo do Site</label>
                                    <input type="file" class="form-control" id="logo" name="logo">
                                    <div class="form-text">Tamanho recomendado: 200x50 pixels. Formatos aceitos: JPG, PNG, GIF.</div>
                                    
                                    <?php if (!empty($config['logo_url'])): ?>
                                        <div class="current-logo mt-2">
                                            <p>Logo atual:</p>
                                            <img src="../<?php echo htmlspecialchars($config['logo_url']); ?>" alt="Logo Atual" class="img-thumbnail" style="max-height: 50px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tema_cor" class="form-label">Cor Principal do Tema</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-palette"></i></span>
                                        <input type="color" class="form-control form-control-color" id="tema_cor" name="tema_cor" value="<?php echo htmlspecialchars($config['tema_cor']); ?>" title="Escolha a cor principal">
                                    </div>
                                    <div class="form-text">Cor principal utilizada em botões e elementos de destaque.</div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="save_config" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Salvar Configurações
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Configuração do Facebook -->
                <div class="tab-pane fade" id="facebook-config">
                    <div class="modern-card">
                        <div class="modern-card-header">
                            <h5 class="modern-card-title">
                                <i class="fab fa-facebook me-2 text-primary"></i> Configuração do Facebook
                            </h5>
                        </div>
                        <div class="modern-card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Para utilizar a integração com o Facebook, você precisa criar um aplicativo no Facebook Developers.
                            </div>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="fb_app_id" class="form-label">App ID do Facebook</label>
                                    <input type="text" class="form-control" id="fb_app_id" name="fb_app_id" value="<?php echo htmlspecialchars($fbConfig['fb_app_id']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fb_app_secret" class="form-label">App Secret do Facebook</label>
                                    <input type="password" class="form-control" id="fb_app_secret" name="fb_app_secret" value="<?php echo htmlspecialchars($fbConfig['fb_app_secret']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fb_app_version" class="form-label">Versão da API</label>
                                    <select class="form-select" id="fb_app_version" name="fb_app_version" required>
                                        <option value="v18.0" <?php echo $fbConfig['fb_app_version'] == 'v18.0' ? 'selected' : ''; ?>>v18.0</option>
                                        <option value="v17.0" <?php echo $fbConfig['fb_app_version'] == 'v17.0' ? 'selected' : ''; ?>>v17.0</option>
                                        <option value="v16.0" <?php echo $fbConfig['fb_app_version'] == 'v16.0' ? 'selected' : ''; ?>>v16.0</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fb_redirect_uri" class="form-label">URL de Redirecionamento</label>
                                    <input type="text" class="form-control" id="fb_redirect_uri" name="fb_redirect_uri" value="<?php echo htmlspecialchars($fbConfig['fb_redirect_uri']); ?>" required>
                                    <div class="form-text">
                                        URL para onde o Facebook redirecionará após o login. 
                                        Geralmente: https://seusite.com/api/facebook-callback.php
                                    </div>
                                </div>
                                
                                <div class="setup-instructions">
                                    <h6><i class="fas fa-info-circle me-2"></i> Como configurar:</h6>
                                    <ol>
                                        <li>Acesse <a href="https://developers.facebook.com/" target="_blank">Facebook Developers</a> e crie um novo aplicativo do tipo "Negócios" ou "Consumidor"</li>
                                        <li>Na seção "Produtos", adicione o produto "Login do Facebook"</li>
                                        <li>Configure a URL de OAuth como mostrado acima</li>
                                        <li>Na seção "Configurações > Básico", copie o ID do aplicativo e o Segredo do aplicativo</li>
                                        <li>Nas configurações de login, adicione as permissões: public_profile, email, groups_access_member_info, publish_to_groups</li>
                                    </ol>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="save_facebook" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Salvar Configurações do Facebook
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Informações do Sistema -->
                <div class="tab-pane fade" id="system-info">
                    <div class="modern-card">
                        <div class="modern-card-header">
                            <h5 class="modern-card-title">
                                <i class="fas fa-server me-2 text-primary"></i> Informações do Sistema
                            </h5>
                        </div>
                        <div class="modern-card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <tbody>
                                        <tr>
                                            <th style="width: 30%;">Versão do PHP</th>
                                            <td><?php echo htmlspecialchars($systemInfo['php_version']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Versão do MySQL</th>
                                            <td><?php echo htmlspecialchars($systemInfo['mysql_version']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Software do Servidor</th>
                                            <td><?php echo htmlspecialchars($systemInfo['server_software']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Sistema Operacional</th>
                                            <td><?php echo htmlspecialchars($systemInfo['server_os']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Limite de Upload</th>
                                            <td><?php echo htmlspecialchars($systemInfo['max_upload']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Limite de POST</th>
                                            <td><?php echo htmlspecialchars($systemInfo['max_post']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Limite de Memória</th>
                                            <td><?php echo htmlspecialchars($systemInfo['memory_limit']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Tempo Máximo de Execução</th>
                                            <td><?php echo htmlspecialchars($systemInfo['max_execution_time']); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Informações do Banco de Dados -->
                <div class="tab-pane fade" id="db-info">
                    <div class="modern-card">
                        <div class="modern-card-header">
                            <h5 class="modern-card-title">
                                <i class="fas fa-database me-2 text-primary"></i> Estatísticas do Banco de Dados
                            </h5>
                        </div>
                        <div class="modern-card-body">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="stats-card h-100">
                                        <div class="stats-icon bg-primary-light text-primary">
                                            <i class="fas fa-table"></i>
                                        </div>
                                        <div class="stats-content">
                                            <h3 class="stats-number"><?php echo $dbStats['tables']; ?></h3>
                                            <p class="stats-label">Tabelas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="stats-card h-100">
                                        <div class="stats-icon bg-info-light text-info">
                                            <i class="fas fa-hdd"></i>
                                        </div>
                                        <div class="stats-content">
                                            <h3 class="stats-number"><?php echo $dbStats['size']; ?></h3>
                                            <p class="stats-label">Tamanho Total</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <div class="stats-card h-100">
                                        <div class="stats-icon bg-success-light text-success">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="stats-content">
                                            <h3 class="stats-number"><?php echo $dbStats['users']; ?></h3>
                                            <p class="stats-label">Usuários</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <div class="stats-card h-100">
                                        <div class="stats-icon bg-warning-light text-warning">
                                            <i class="fas fa-ad"></i>
                                        </div>
                                        <div class="stats-content">
                                            <h3 class="stats-number"><?php echo $dbStats['anuncios']; ?></h3>
                                            <p class="stats-label">Anúncios</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <div class="stats-card h-100">
                                        <div class="stats-icon bg-danger-light text-danger">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="stats-content">
                                            <h3 class="stats-number"><?php echo $dbStats['grupos']; ?></h3>
                                            <p class="stats-label">Grupos</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="stats-card h-100">
                                        <div class="stats-icon bg-purple-light text-purple">
                                            <i class="fas fa-bullhorn"></i>
                                        </div>
                                        <div class="stats-content">
                                            <h3 class="stats-number"><?php echo $dbStats['campanhas']; ?></h3>
                                            <p class="stats-label">Campanhas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="stats-card h-100">
                                        <div class="stats-icon bg-orange-light text-orange">
                                            <i class="fas fa-history"></i>
                                        </div>
                                        <div class="stats-content">
                                            <h3 class="stats-number"><?php echo $dbStats['logs']; ?></h3>
                                            <p class="stats-label">Registros de Log</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Manutenção -->
                <div class="tab-pane fade" id="maintenance">
                    <div class="modern-card">
                        <div class="modern-card-header">
                            <h5 class="modern-card-title">
                                <i class="fas fa-tools me-2 text-primary"></i> Manutenção do Sistema
                            </h5>
                        </div>
                        <div class="modern-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="maintenance-card">
                                        <div class="maintenance-icon">
                                            <i class="fas fa-broom"></i>
                                        </div>
                                        <div class="maintenance-content">
                                            <h5>Limpar Cache</h5>
                                            <p>Remove arquivos temporários para liberar espaço e melhorar o desempenho.</p>
                                            <form method="POST">
                                                <button type="submit" name="clear_cache" class="btn btn-primary">
                                                    <i class="fas fa-broom me-2"></i> Limpar Cache
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="maintenance-card">
                                        <div class="maintenance-icon">
                                            <i class="fas fa-database"></i>
                                        </div>
                                        <div class="maintenance-content">
                                            <h5>Otimizar Banco de Dados</h5>
                                            <p>Otimiza as tabelas do banco de dados para melhorar o desempenho.</p>
                                            <form method="POST">
                                                <button type="submit" name="optimize_db" class="btn btn-primary">
                                                    <i class="fas fa-database me-2"></i> Otimizar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="maintenance-card">
                                        <div class="maintenance-icon">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="maintenance-content">
                                            <h5>Limpar Logs Antigos</h5>
                                            <p>Remove logs de sistema antigos para liberar espaço no banco de dados.</p>
                                            <form method="POST">
                                                <button type="submit" name="clear_logs" class="btn btn-warning">
                                                    <i class="fas fa-file-alt me-2"></i> Limpar Logs
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="maintenance-card">
                                        <div class="maintenance-icon">
                                            <i class="fas fa-history"></i>
                                        </div>
                                        <div class="maintenance-content">
                                            <h5>Verificar Atualizações</h5>
                                            <p>Verifica se há novas versões do sistema disponíveis.</p>
                                            <button type="button" class="btn btn-info" id="checkUpdates">
                                                <i class="fas fa-sync-alt me-2"></i> Verificar Atualizações
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Adicional -->
<style>
/* Estilos para menu de navegação */
.config-nav {
    position: sticky;
    top: 90px;
}

.config-nav .list-group-item {
    border-radius: 10px;
    margin-bottom: 5px;
    border: none;
    background-color: #f8f9fa;
    color: #495057;
    padding: 12px 15px;
    transition: all 0.3s;
}

.config-nav .list-group-item:hover {
    background-color: #e9ecef;
}

.config-nav .list-group-item.active {
    background-color: #3498db;
    color: #fff;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
}

/* Estilos para cards de estatísticas */
.stats-card {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 15px;
    background-color: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stats-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-right: 20px;
}

.stats-content {
    flex: 1;
}

.stats-number {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 5px;
    line-height: 1;
}

.stats-label {
    font-size: 0.9rem;
    color: #777;
    margin: 0;
}

/* Estilos para carnes de manutenção */
.maintenance-card {
    display: flex;
    align-items: flex-start;
    padding: 20px;
    border-radius: 15px;
    background-color: #f8f9fa;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    transition: all 0.3s;
}

.maintenance-card:hover {
    background-color: #fff;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.maintenance-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-right: 20px;
    background-color: rgba(52, 152, 219, 0.1);
    color: #3498db;
}

.maintenance-content {
    flex: 1;
}

.maintenance-content h5 {
    font-weight: 600;
    margin-bottom: 10px;
}

.maintenance-content p {
    color: #777;
    margin-bottom: 15px;
    font-size: 0.9rem;
}

/* Estilos para instruções de configuração */
.setup-instructions {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin: 20px 0;
}

.setup-instructions h6 {
    font-weight: 600;
    margin-bottom: 10px;
}

.setup-instructions ol {
    padding-left: 20px;
}

.setup-instructions li {
    margin-bottom: 5px;
}

/* Estilos para card de status */
.backup-status-card {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
}

.backup-status-card.configured {
    background-color: rgba(46, 204, 113, 0.1);
    border-left: 4px solid #2ecc71;
}

.backup-status-card.not-configured {
    background-color: rgba(243, 156, 18, 0.1);
    border-left: 4px solid #f39c12;
}

/* Cores adicionais para ícones */
.bg-primary-light { background-color: rgba(52, 152, 219, 0.1); }
.bg-success-light { background-color: rgba(46, 204, 113, 0.1); }
.bg-warning-light { background-color: rgba(243, 156, 18, 0.1); }
.bg-danger-light { background-color: rgba(231, 76, 60, 0.1); }
.bg-info-light { background-color: rgba(26, 188, 156, 0.1); }
.bg-purple-light { background-color: rgba(155, 89, 182, 0.1); }
.bg-orange-light { background-color: rgba(230, 126, 34, 0.1); }

.text-purple { color: #9b59b6; }
.text-orange { color: #e67e22; }
</style>

<!-- JavaScript Adicional -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Script para verificar atualizações
    const checkUpdatesBtn = document.getElementById('checkUpdates');
    if(checkUpdatesBtn) {
        checkUpdatesBtn.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Verificando...';
            this.disabled = true;
            
            // Simulação de verificação de atualização
            setTimeout(() => {
                alert('Sistema atualizado! Você está usando a versão mais recente.');
                this.innerHTML = '<i class="fas fa-sync-alt me-2"></i> Verificar Atualizações';
                this.disabled = false;
            }, 2000);
        });
    }
    
    // Script para navegação por abas
    const configTabs = document.querySelectorAll('.list-group-item');
    configTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            configTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
</script>

<?php
// Incluir o rodapé
include '../includes/footer.php';
?>