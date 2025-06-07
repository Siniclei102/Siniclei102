<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Simular status da extensão
$response = [
    'connected' => false,
    'last_sync' => null,
    'groups_count' => 0,
    'message' => 'Extensão não conectada'
];

echo json_encode($response);
?>