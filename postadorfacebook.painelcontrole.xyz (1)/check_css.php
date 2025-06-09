<?php
echo "<h1>Verificação dos Arquivos CSS</h1>";
echo "<hr>";

$css_files = [
    'assets/css/dashboard.css',
    'assets/css/pages.css'
];

foreach ($css_files as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "<p>✅ <strong>Arquivo existe:</strong> {$file} ({$size} bytes)</p>";
    } else {
        echo "<p>❌ <strong>Arquivo não encontrado:</strong> {$file}</p>";
    }
}

// Verificar se as pastas existem
$folders = ['assets', 'assets/css', 'assets/js', 'includes'];
foreach ($folders as $folder) {
    if (is_dir($folder)) {
        echo "<p>✅ <strong>Pasta existe:</strong> {$folder}</p>";
    } else {
        echo "<p>❌ <strong>Pasta não encontrada:</strong> {$folder}</p>";
    }
}

echo "<hr>";
echo "<p><a href='create_structure.php'>Criar estrutura de pastas</a></p>";
echo "<p><a href='dashboard.php'>Voltar ao Dashboard</a></p>";
?>