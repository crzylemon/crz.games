<?php
require_once '../db.php';
require_once '../user/session.php';

$current_user = getCurrentUser();
if (!$current_user) {
    header('Location: ../user/login.php');
    exit;
}

$message = '';
$to_user = $_GET['to'] ?? '';

if ($_POST) {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE username = ?");
    $stmt->execute([$_POST['to_user']]);
    $to_user_id = $stmt->fetchColumn();
    
    if ($to_user_id) {
        $stmt = $pdo->prepare("INSERT INTO messages (from_user_id, to_user_id, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$current_user['id'], $to_user_id, $_POST['message']]);
        header('Location: messages.php?user=' . urlencode($_POST['to_user']));
        exit;
    } else {
        $message = 'User not found';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Compose Message - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .compose-form { background: #1e2329; border-radius: 8px; padding: 30px; max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; color: #66c0f4; font-weight: bold; margin-bottom: 8px; }
        .form-input, .form-textarea { width: 100%; padding: 12px; background: #16202d; border: 1px solid #3c4043; border-radius: 4px; color: #c7d5e0; }
        .form-textarea { height: 200px; resize: vertical; }
        .submit-button { background: #00d4ff; color: white; border: none; padding: 15px 30px; border-radius: 6px; cursor: pointer; width: 100%; }
        .error { background: #d32f2f; color: white; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="container">
        <h1>Compose Message</h1>
        
        <div class="compose-form">
            <?php if ($message): ?>
                <div class="error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">To</label>
                    <input type="text" name="to_user" class="form-input" value="<?= htmlspecialchars($to_user) ?>" placeholder="Username" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea name="message" class="form-textarea" placeholder="Type your message..." required></textarea>
                </div>
                
                <button type="submit" class="submit-button">Send Message</button>
            </form>
        </div>
    </div>
</body>
</html>