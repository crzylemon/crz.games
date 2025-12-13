<?php
require_once 'session.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

$user = getCurrentUser();
$response = ['success' => false];

switch ($field) {
    case 'display_name':
        if (strlen($value) > 0 && strlen($value) <= 64) {
            $stmt = $pdo->prepare("UPDATE accounts SET display_name = ? WHERE id = ?");
            if ($stmt->execute([$value, $user['id']])) {
                $response['success'] = true;
            }
        }
        break;
        
    case 'email':
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE email = ? AND id != ?");
            $stmt->execute([$value, $user['id']]);
            
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE accounts SET email = ? WHERE id = ?");
                if ($stmt->execute([$value, $user['id']])) {
                    $response['success'] = true;
                }
            } else {
                $response['error'] = 'Email already in use';
            }
        }
        break;
        
    default:
        $response['error'] = 'Invalid field';
}

echo json_encode($response);
?>