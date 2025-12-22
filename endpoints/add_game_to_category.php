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
$categoryId = $data['category_id'] ?? null;

if (!$gameId || !$categoryId) {
    http_response_code(400);
    echo json_encode(['error' => 'game_id and category_id required']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO library_game_categories (user_id, game_id, category_id) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $gameId, $categoryId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>