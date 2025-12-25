<?php
require_once 'session.php';
requireLogin();

$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = trim($_POST['display_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($display_name)) {
        $error = 'Display name is required';
    } else {
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            
            if ($stmt->fetch()) {
                $error = 'Email already in use by another account';
            }
        }
        
        if (!$error) {
            if (!empty($new_password)) {
                if (empty($current_password) || !password_verify($current_password, $user['password_hash'])) {
                    $error = 'Current password is incorrect';
                } elseif (strlen($new_password) < 6) {
                    $error = 'New password must be at least 6 characters';
                } else {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $gjp2_hash = hash('sha1', $new_password . 'mI29fmAnxgTs');
                    $gjp2_hash = password_hash($gjp2_hash, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE accounts SET display_name = ?, bio = ?, email = ?, password_hash = ?, gd_password = ? WHERE id = ?");
                    $stmt->execute([$display_name, $bio, $email ?: '', $password_hash, $gjp2_hash, $user['id']]);
                    $success = 'Settings updated successfully';
                }
            } else {
                $stmt = $pdo->prepare("UPDATE accounts SET display_name = ?, bio = ?, email = ? WHERE id = ?");
                $stmt->execute([$display_name, $bio, $email ?: '', $user['id']]);
                $success = 'Settings updated successfully';
            }
            
            if ($success) {
                $user = getCurrentUser();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - CRZ.Network</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <div class="game-header">
            <h1 class="game-title">Account Settings</h1>
            <p class="game-genre">Manage your CRZ.Network account</p>
        </div>
        
        <div class="game-content">
            <div class="game-media">
                <?php if ($error): ?>
                    <div style="background: #722f37; color: #ff6b6b; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div style="background: #2d5a2d; color: #90ee90; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="info-section">
                        <label class="info-title" for="username">Username (cannot be changed)</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled
                               style="width: 100%; padding: 12px; background: #1a1a1a; border: 1px solid #404040; border-radius: 4px; color: #888; font-size: 16px;">
                    </div>
                    
                    <div class="info-section">
                        <label class="info-title" for="display_name">Display Name</label>
                        <input type="text" id="display_name" name="display_name" required maxlength="64" 
                               value="<?php echo htmlspecialchars($user['display_name']); ?>"
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <div class="info-section">
                        <label class="info-title" for="bio">Bio</label>
                        <textarea id="bio" name="bio" maxlength="500" rows="4"
                                  style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px; resize: vertical;"
                                  placeholder="Tell others about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="info-section">
                        <label class="info-title" for="email">Email (optional)</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <div class="info-section">
                        <label class="info-title" for="current_password">Current Password (required to change password)</label>
                        <input type="password" id="current_password" name="current_password"
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <div class="info-section">
                        <label class="info-title" for="new_password">New Password (leave blank to keep current)</label>
                        <input type="password" id="new_password" name="new_password" minlength="6"
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <button type="submit" class="play-button">Update Settings</button>
                </form>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #404040;">
                    <a href="/" style="color: #66c0f4; text-decoration: none;">← Back to Home</a>
                    <span style="float: right;">
                        <a href="/user/logout.php" style="color: #ff6b6b; text-decoration: none;">Sign Out</a>
                    </span>
                </div>
            </div>
            
            <div class="game-info">
                <div class="info-section">
                    <div class="info-title">Account Info</div>
                    <div class="info-value">
                        Member since: <?php echo date('M j, Y', strtotime($user['created_at'])); ?><br>
                        Last login: <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>