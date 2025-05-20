<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste de Diagnóstico da Página de Anúncios</h1>";

// Verifica se o arquivo anuncios.php existe
if (file_exists('anuncios.php')) {
    echo "<p style='color:green'>✓ O arquivo anuncios.php existe.</p>";
    echo "<p>Tamanho do arquivo: " . filesize('anuncios.php') . " bytes</p>";
    echo "<p>Permissões: " . substr(sprintf('%o', fileperms('anuncios.php')), -4) . "</p>";
} else {
    echo "<p style='color:red'>✗ O arquivo anuncios.php NÃO existe no diretório raiz!</p>";
}

// Verifica se há .htaccess que possa estar causando redirecionamento
if (file_exists('.htaccess')) {
    echo "<p style='color:orange'>! Arquivo .htaccess encontrado - pode estar causando redirecionamentos:</p>";
    echo "<pre>" . htmlspecialchars(file_get_contents('.htaccess')) . "</pre>";
} else {
    echo "<p>Não há arquivo .htaccess que possa estar causando redirecionamentos.</p>";
}

// Lista todos os arquivos PHP na raiz
echo "<h2>Arquivos PHP na raiz:</h2>";
$files = glob("*.php");
echo "<ul>";
foreach ($files as $file) {
    echo "<li>$file - " . filesize($file) . " bytes</li>";
}
echo "</ul>";

// Testa inclusão do arquivo anuncios.php
echo "<h2>Tentativa de incluir anuncios.php:</h2>";
echo "<div style='background-color:#f5f5f5; padding:10px; border:1px solid #ddd'>";
try {
    include_once 'anuncios.php';
    echo "<p style='color:green'>✓ Arquivo incluído com sucesso (continua após este ponto).</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>✗ Erro ao incluir o arquivo anuncios.php:</p>";
    echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
echo "</div>";

// Verifica sessão
echo "<h2>Informações de Sessão:</h2>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Botão para tentar acessar anuncios.php diretamente
echo "<h2>Links de Teste:</h2>";
echo "<p><a href='anuncios.php' style='padding:10px; background-color:#4CAF50; color:white; text-decoration:none; border-radius:4px;'>Acessar anuncios.php Diretamente</a></p>";
echo "<p><a href='dashboard.php' style='padding:10px; background-color:#2196F3; color:white; text-decoration:none; border-radius:4px;'>Voltar para Dashboard</a></p>";