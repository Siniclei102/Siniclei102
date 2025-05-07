<?php
require_once '../../config/database.php';

// Verificar a estrutura da tabela telegram_bots
echo "<h2>Estrutura da tabela 'telegram_bots':</h2>";
$result = $conn->query("DESCRIBE telegram_bots");

echo "<pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
?>