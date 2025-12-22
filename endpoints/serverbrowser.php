<?php
require_once '../db.php';
require_once '../user/session.php';

header('Content-Type: application/json');

$specific = isset($_GET['game']) ? $_GET['game'] : null;
try {
    // Create game_rooms table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS game_rooms (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_id VARCHAR(255) NOT NULL,
        game_id VARCHAR(255) NOT NULL,
        room_name VARCHAR(255) NOT NULL,
        host_user_id INT,
        player_count INT DEFAULT 0,
        max_players INT DEFAULT 4,
        status ENUM('active', 'inactive', 'full') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_room (room_id)
    )");
    
    // Add multiplayer column to games table if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE games ADD COLUMN multiplayer_enabled TINYINT(1) DEFAULT 0");
    } catch (PDOException $e) {}
    
    if (!$specific) {
        // Get active games that support multiplayer
        $stmt = $pdo->prepare("
            SELECT g.id, g.title, g.slug, g.description,
                COUNT(DISTINCT gr.room_id) as active_rooms,
                SUM(gr.player_count) as total_players
            FROM games g 
            LEFT JOIN game_rooms gr ON g.id = gr.game_id AND gr.status = 'active'
            WHERE g.multiplayer_enabled = 1 AND g.status = 'PLAYABLE'
            GROUP BY g.id
            ORDER BY total_players DESC, g.title ASC
        ");
        $stmt->execute();
        $games = $stmt->fetchAll();
    }
    
    if ($specific) {
        $stmt = $pdo->prepare("
            SELECT r.*, g.title as game_title, g.slug as game_slug
            FROM game_rooms r
            JOIN games g ON r.game_id = g.id
            WHERE r.game_id = ? AND r.status = 'active'
            ORDER BY r.player_count DESC, r.created_at DESC
        ");
        $stmt->execute([$specific]);
        $rooms = $stmt->fetchAll();
        echo json_encode([
            'success' => true,
            'rooms' => $rooms,
            'websocket_url' => 'wss://crz.games:21212/gameserver'
        ]);
        exit;
    }

    $rooms = [];
    if (!empty($games)) {
        $gameIds = array_column($games, 'id');
        $placeholders = str_repeat('?,', count($gameIds) - 1) . '?';
        
        $roomStmt = $pdo->prepare("
            SELECT r.*, g.title as game_title, g.slug as game_slug
            FROM game_rooms r
            JOIN games g ON r.game_id = g.id
            WHERE r.game_id IN ($placeholders) AND r.status = 'active'
            ORDER BY r.player_count DESC, r.created_at DESC
        ");
        $roomStmt->execute($gameIds);
        $rooms = $roomStmt->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'games' => $games,
        'rooms' => $rooms,
        'websocket_url' => 'wss://crz.games:21212/gameserver'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>