<?php
require_once '../db.php';
require_once '../user/session.php';

$current_user = getCurrentUser();
if (!$current_user) {
    header('Location: ../user/login.php');
    exit;
}

if ($_POST && isset($_POST['game_id'])) {
    $game_id = (int)$_POST['game_id'];
    
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_library (user_id, game_id) VALUES (?, ?)");
        $stmt->execute([$current_user['id'], $game_id]);
    } catch (PDOException $e) {
        // Ignore errors (game already in library)
    }
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
?>