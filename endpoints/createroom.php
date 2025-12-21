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

$data = json_decode(file_get_contents('php://input'), true);
$gameId = $data['game_id'] ?? null;
$roomName = $data['room_name'] ?? 'New Room';
$maxPlayers = $data['max_players'] ?? 4;

if (!$gameId) {
    http_response_code(400);
    echo json_encode(['error' => 'game_id required']);
    exit;
}

try {
    $roomId = uniqid('room_', true);
    
    $stmt = $pdo->prepare("
        INSERT INTO game_rooms (room_id, game_id, host_user_id, room_name, max_players, player_count, status, created_at)
        VALUES (?, ?, ?, ?, ?, 0, 'active', NOW())
    ");
    $stmt->execute([$roomId, $gameId, $user['id'], $roomName, $maxPlayers]);

    echo json_encode([
        'success' => true,
        'room_id' => $roomId,
        'websocket_url' => 'ws://localhost:7777'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create room']);
}
?>