<?php
require_once '../db.php';
require_once 'includes/admin.php';

requireAdmin();

$query = $_GET['q'] ?? '';
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT g.id, g.title, g.thumbnail_small, a.username FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.status IN ('PLAYABLE', 'PUBLIC_UNPLAYABLE') AND (g.title LIKE ? OR a.username LIKE ?) ORDER BY g.title LIMIT 10");
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$searchTerm, $searchTerm]);
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($games);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>