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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return user categories
    try {
        $stmt = $pdo->prepare("SELECT * FROM library_categories WHERE user_id = ? ORDER BY name");
        $stmt->execute([$user['id']]);
        $categories = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'categories' => $categories]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$categoryName = $data['name'] ?? null;

if (!$categoryName) {
    http_response_code(400);
    echo json_encode(['error' => 'name required']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO library_categories (user_id, name) VALUES (?, ?)");
    $stmt->execute([$user['id'], $categoryName]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['error' => 'Category already exists']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}
?>