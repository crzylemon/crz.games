<?php
require_once 'session.php';
require_once '../db.php';

if (isLoggedIn()) {
    header('Location: /');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            createSession($user['id']);
            header('Location: /');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CRZ.Network</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <div class="game-header">
            <h1 class="game-title">Sign In</h1>
            <p class="game-genre">Welcome back to CRZ.Network</p>
        </div>
        
        <div class="game-content">
            <div class="game-media">
                <?php if ($error): ?>
                    <div style="background: #722f37; color: #ff6b6b; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="info-section">
                        <label class="info-title" for="username">Username or Email</label>
                        <input type="text" id="username" name="username" required 
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <div class="info-section">
                        <label class="info-title" for="password">Password</label>
                        <input type="password" id="password" name="password" required 
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <button type="submit" class="play-button">Sign In</button>
                </form>
                
                <p style="text-align: center; color: #8f98a0;">
                    Don't have an account? <a href="/user/signup.php" style="color: #66c0f4;">Sign up here</a>
                </p>
            </div>
            
            <div class="game-info">
                <div class="info-section">
                    <div class="info-title">Join CRZ.Network</div>
                    <div class="info-value">Access exclusive games, upload videos, and connect with our community.</div>
                </div>
                
                <div class="info-section">
                    <div class="info-title">Features</div>
                    <div class="info-value">
                        • Play games online<br>
                        • Upload and share videos<br>
                        • Connect with friends<br>
                        • Join community discussions
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>