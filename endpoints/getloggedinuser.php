<?php
require_once '../user/session.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://crz.games');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user = getCurrentUser();
try {
    if ($user) {
        // Remove sensitive data
        unset($user['password_hash']);
        
        echo json_encode([
            'success' => true,
            'username' => $user['username'],
            'id' => $user['id'],
            // if the user allows it ($user['games_read_email']) then send the email, otherwise send null
            'email' => $user['games_read_email'] ? $user['email'] : null,
            'display' => $user['display_name'],
            'created' => $user['created_at'],
            'user' => $user,
            'authenticated' => true
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Not logged in',
            'authenticated' => false
        ]);
    }
}
//exception
catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'authenticated' => false
    ]);
}
?>