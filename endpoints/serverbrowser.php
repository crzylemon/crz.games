<?php
require_once '../db.php';
require_once '../user/session.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    // Get active games that support multiplayer
    $stmt = $pdo->prepare("
        SELECT g.id, g.title, g.slug, g.max_players, g.description,
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

    // Get individual rooms for each game
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
        'websocket_url' => 'ws://localhost:7777'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>