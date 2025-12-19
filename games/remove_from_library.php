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
        $stmt = $pdo->prepare("DELETE FROM user_library WHERE user_id = ? AND game_id = ?");
        $stmt->execute([$current_user['id'], $game_id]);
    } catch (PDOException $e) {
        // Ignore errors
    }
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
?>