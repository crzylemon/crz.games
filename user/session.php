<?php
try {
    // Check if db.php is already loaded or load it with correct path
    if (!isset($pdo)) {
        $db_path = dirname(__DIR__) . '/db.php';
        if (file_exists($db_path)) {
            require_once $db_path;
        } else {
            require_once '../db.php';
        }
    }
} catch (Exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    return false;
}

function isLoggedIn() {
    if (isset($_COOKIE['session_token'])) {
        return validateSessionToken($_COOKIE['session_token']);
    }
    return false;
}

function validateSessionToken($token) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT user_id FROM user_sessions WHERE session_token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    return $stmt->fetchColumn() !== false;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /user/login.php');
        exit();
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT a.* FROM accounts a JOIN user_sessions s ON a.id = s.user_id WHERE s.session_token = ? AND s.expires_at > NOW()");
    $stmt->execute([$_COOKIE['session_token']]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update lse every time user is active (page load)
        $stmt = $pdo->prepare("UPDATE accounts SET lse = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
    }
    
    return $user;
}

function createSession($userId) {
    global $pdo;
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
    
    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expires]);
    
    setcookie('session_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    
    $stmt = $pdo->prepare("UPDATE accounts SET last_login = NOW(), lss = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
}

function destroySession() {
    if (isset($_COOKIE['session_token'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT user_id FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$_COOKIE['session_token']]);
        $userId = $stmt->fetchColumn();
        
        if ($userId) {
            $stmt = $pdo->prepare("UPDATE accounts SET lse = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        }
        
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$_COOKIE['session_token']]);
    }
    setcookie('session_token', '', time() - 3600, '/');
}
?>