<?php
require_once '../db.php';
require_once '../user/session.php';

$current_user = getCurrentUser();
if (!$current_user) {
    header('Location: ../user/login.php');
    exit;
}

// Send message
if ($_POST && isset($_POST['to_user'], $_POST['message'])) {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE username = ?");
    $stmt->execute([$_POST['to_user']]);
    $to_user_id = $stmt->fetchColumn();
    
    if ($to_user_id) {
        $stmt = $pdo->prepare("INSERT INTO messages (from_user_id, to_user_id, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$current_user['id'], $to_user_id, $_POST['message']]);
    }
}

// Get conversations
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        CASE WHEN from_user_id = ? THEN to_user_id ELSE from_user_id END as other_user_id,
        a.username, a.display_name
    FROM messages m
    JOIN accounts a ON a.id = CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END
    WHERE from_user_id = ? OR to_user_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$current_user['id'], $current_user['id'], $current_user['id'], $current_user['id']]);
$conversations = $stmt->fetchAll();

// Get messages with selected user
$selected_user = $_GET['user'] ?? '';
$messages = [];
if ($selected_user) {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE username = ?");
    $stmt->execute([$selected_user]);
    $other_user_id = $stmt->fetchColumn();
    
    if ($other_user_id) {
        $stmt = $pdo->prepare("
            SELECT m.*, a.username, a.display_name 
            FROM messages m 
            JOIN accounts a ON a.id = m.from_user_id
            WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)
            ORDER BY created_at ASC
        ");
        $stmt->execute([$current_user['id'], $other_user_id, $other_user_id, $current_user['id']]);
        $messages = $stmt->fetchAll();
        
        // Mark as read
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE to_user_id = ? AND from_user_id = ?");
        $stmt->execute([$current_user['id'], $other_user_id]);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Messages - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .messages-container { display: flex; height: calc(100vh - 200px); gap: 20px; }
        .conversations { width: 300px; background: #1e2329; border-radius: 8px; overflow-y: auto; }
        .conversation-item { padding: 15px; border-bottom: 1px solid #333; cursor: pointer; }
        .conversation-item:hover { background: #2a3441; }
        .conversation-item.active { background: #2a5298; }
        .messages-panel { flex: 1; background: #1e2329; border-radius: 8px; display: flex; flex-direction: column; }
        .messages-header { padding: 20px; border-bottom: 1px solid #333; font-weight: bold; color: #66c0f4; }
        .messages-list { flex: 1; padding: 20px; overflow-y: auto; }
        .message { margin-bottom: 15px; padding: 10px; border-radius: 8px; max-width: 70%; }
        .message.sent { background: #2a5298; margin-left: auto; }
        .message.received { background: #333; }
        .message-time { font-size: 0.8rem; color: #888; margin-top: 5px; }
        .message-form { padding: 20px; border-top: 1px solid #333; display: flex; gap: 10px; }
        .message-input { flex: 1; padding: 10px; background: #16202d; border: 1px solid #333; border-radius: 4px; color: white; }
        .send-btn { padding: 10px 20px; background: #00d4ff; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="container">
        <h1>Messages</h1>
        
        <div class="messages-container">
            <div class="conversations">
                <?php foreach ($conversations as $conv): ?>
                    <div class="conversation-item <?= $selected_user === $conv['username'] ? 'active' : '' ?>" 
                         onclick="location.href='?user=<?= urlencode($conv['username']) ?>'">
                        <?= htmlspecialchars($conv['display_name'] ?: $conv['username']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="messages-panel">
                <?php if ($selected_user): ?>
                    <div class="messages-header"><?= htmlspecialchars($selected_user) ?></div>
                    <div class="messages-list" id="messagesList">
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?= $msg['from_user_id'] == $current_user['id'] ? 'sent' : 'received' ?>">
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                <div class="message-time"><?= date('M j, g:i A', strtotime($msg['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form class="message-form" method="POST">
                        <input type="hidden" name="to_user" value="<?= htmlspecialchars($selected_user) ?>">
                        <input type="text" name="message" class="message-input" placeholder="Type a message..." required>
                        <button type="submit" class="send-btn">Send</button>
                    </form>
                <?php else: ?>
                    <div class="messages-header">Select a conversation</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        const messagesList = document.getElementById('messagesList');
        if (messagesList) {
            messagesList.scrollTop = messagesList.scrollHeight;
        }
    </script>
</body>
</html>