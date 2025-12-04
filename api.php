<?php
require_once 'db.php';

function api($endpoint) {
    global $pdo;
    
    switch($endpoint) {
        case 'stats':
            try {
                $videos = $pdo->query("SELECT COUNT(*) as count FROM videos")->fetch()['count'] ?? 0;
                $users = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'] ?? 0;
                $games = $pdo->query("SELECT COUNT(*) as count FROM games")->fetch()['count'] ?? 0;
                return ['videos' => $videos, 'users' => $users, 'games' => $games];
            } catch(Exception $e) {
                return ['videos' => -1, 'users' => -1, 'games' => -1];
            }
            
        case 'reviews':
            try {
                $stmt = $pdo->query("SELECT name, rating, comment FROM reviews ORDER BY created_at DESC LIMIT 10");
                return $stmt->fetchAll();
            } catch(Exception $e) {
                return [];
            }
            
        default:
            return ['error' => 'Endpoint not found'];
    }
}

if (isset($_GET['endpoint'])) {
    header('Content-Type: application/json');
    echo json_encode(api($_GET['endpoint']));
}
?>