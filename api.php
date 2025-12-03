<?php
header('Content-Type: application/json');
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

switch($endpoint) {
    case 'stats':
        if($method === 'GET') {
            echo json_encode([
                'videos' => 6,
                'users' => 17,
                'games' => 6
            ]);
        }
        break;
        
    case 'reviews':
        if($method === 'GET') {
            $stmt = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC LIMIT 10");
            echo json_encode($stmt->fetchAll());
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}
?>