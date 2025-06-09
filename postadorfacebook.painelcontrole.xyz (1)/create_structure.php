<?php
// Script para criar estrutura de pastas necessárias

$folders = [
    'assets',
    'assets/css',
    'assets/js',
    'assets/img',
    'includes',
    'pages',
    'api',
    'uploads',
    'uploads/avatars',
    'uploads/posts',
    'uploads/media',
    'logs'
];

echo "<h1>Criando Estrutura de Pastas</h1>";
echo "<hr>";

foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        if (mkdir($folder, 0755, true)) {
            echo "<p>✅ <strong>Pasta criada:</strong> {$folder}</p>";
        } else {
            echo "<p>❌ <strong>Erro ao criar pasta:</strong> {$folder}</p>";
        }
    } else {
        echo "<p>📁 <strong>Pasta já existe:</strong> {$folder}</p>";
    }
}

// Criar arquivos .htaccess para segurança
$htaccess_files = [
    'uploads/.htaccess' => "Options -Indexes\nAddType text/plain .php .php3 .phtml .pht .pl .py .jsp .asp .sh .cgi\nphp_flag engine off",
    'logs/.htaccess' => "Options -Indexes\nDeny from all",
    'includes/.htaccess' => "Options -Indexes\nDeny from all"
];

echo "<h3>Criando Arquivos de Segurança:</h3>";

foreach ($htaccess_files as $file => $content) {
    if (!file_exists($file)) {
        if (file_put_contents($file, $content)) {
            echo "<p>✅ <strong>Arquivo criado:</strong> {$file}</p>";
        } else {
            echo "<p>❌ <strong>Erro ao criar arquivo:</strong> {$file}</p>";
        }
    } else {
        echo "<p>📄 <strong>Arquivo já existe:</strong> {$file}</p>";
    }
}

// Criar arquivo index.php para pasta de uploads
$index_content = "<?php\n// Acesso negado\nheader('HTTP/1.0 403 Forbidden');\nexit('Acesso negado');\n?>";

$index_files = [
    'uploads/index.php',
    'logs/index.php',
    'includes/index.php'
];

foreach ($index_files as $file) {
    if (!file_exists($file)) {
        if (file_put_contents($file, $index_content)) {
            echo "<p>✅ <strong>Arquivo de proteção criado:</strong> {$file}</p>";
        }
    }
}

echo "<hr>";
echo "<p>✅ <strong>Estrutura criada com sucesso!</strong></p>";
echo "<p><a href='dashboard.php'>← Ir para Dashboard</a></p>";
?>