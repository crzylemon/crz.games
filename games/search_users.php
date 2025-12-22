<?php
require_once '../db.php';
require_once '../user/session.php';
require_once 'includes/admin.php';

if (!isOwner()) {
    http_response_code(403);
    exit;
}

$query = $_GET['q'] ?? '';
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, display_name FROM accounts WHERE username LIKE ? OR display_name LIKE ? ORDER BY username LIMIT 10");
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$searchTerm, $searchTerm]);
    $users = $stmt->fetchAll();
    
    echo json_encode($users);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>