<?php
require_once '../../config/database.php';

// Verificar estrutura da tabela bots
$result = $conn->query("DESCRIBE bots");
echo "<h2>Estrutura da tabela 'bots':</h2>";
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// Verificar estrutura da tabela platforms
$result = $conn->query("DESCRIBE platforms");
echo "<h2>Estrutura da tabela 'platforms':</h2>";
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// Verificar estrutura da tabela games
$result = $conn->query("DESCRIBE games");
echo "<h2>Estrutura da tabela 'games':</h2>";
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

echo "<h2>Nomes das colunas na tabela 'bots':</h2>";
$columns = $conn->query("SHOW COLUMNS FROM bots");
$column_names = [];
while ($row = $columns->fetch_assoc()) {
    $column_names[] = $row['Field'];
}
echo "Colunas: " . implode(", ", $column_names);
?>