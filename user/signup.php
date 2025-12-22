<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once 'session.php';
    require_once '../db.php';
} catch (Exception $e) {
    die('Error loading dependencies: ' . $e->getMessage());
}

try {
    if (isLoggedIn()) {
        header('Location: /');
        exit();
    }
} catch (Exception $e) {
    // Continue with signup if session check fails
    error_log('Session check failed: ' . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($display_name) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            // Check for exact username match
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = 'Username already exists';
            } else {
                // Check for similar usernames without dashes and underscores
                $normalized_username = str_replace(['-', '_'], '', strtolower($username));
                $stmt = $pdo->prepare("SELECT username FROM accounts WHERE REPLACE(REPLACE(LOWER(username), '-', ''), '_', '') = ?");
                $stmt->execute([$normalized_username]);
                
                if ($existing = $stmt->fetch()) {
                    $error = 'Username too similar to existing user: ' . $existing['username'];
                } else {
                    // Check email if provided
                    if (!empty($email)) {
                        $stmt = $pdo->prepare("SELECT id FROM accounts WHERE email = ?");
                        $stmt->execute([$email]);
                        
                        if ($stmt->fetch()) {
                            $error = 'Email already exists';
                        }
                    }
                    
                    if (!$error) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $gjp2_hash = hash('sha1', $password . 'mI29fmAnxgTs');
                        $stmt = $pdo->prepare("INSERT INTO accounts (username, display_name, email, password_hash, gd_password) VALUES (?, ?, ?, ?, ?)");
                        
                        if ($stmt->execute([$username, $display_name, $email ?: '', $password_hash, $gjp2_hash])) {
                            $success = 'Account created successfully! You can now sign in.';
                        } else {
                            $error = 'Failed to create account';
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - CRZ.Network</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <div class="game-header">
            <h1 class="game-title">Create Account</h1>
            <p class="game-genre">Join the CRZ.Network community</p>
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
                        <label class="info-title" for="username">Username</label>
                        <input type="text" id="username" name="username" required maxlength="32"
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <div class="info-section">
                        <label class="info-title" for="display_name">Display Name</label>
                        <input type="text" id="display_name" name="display_name" required maxlength="64"
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <div class="info-section">
                        <label class="info-title" for="email">Email (optional)</label>
                        <input type="email" id="email" name="email"
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <div class="info-section">
                        <label class="info-title" for="password">Password</label>
                        <input type="password" id="password" name="password" required minlength="6"
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <div class="info-section">
                        <label class="info-title" for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               style="width: 100%; padding: 12px; background: #2a2a2a; border: 1px solid #404040; border-radius: 4px; color: #fff; font-size: 16px;">
                    </div>
                    
                    <button type="submit" class="play-button">Create Account</button>
                </form>
                
                <p style="text-align: center; color: #8f98a0;">
                    Already have an account? <a href="/user/login.php" style="color: #66c0f4;">Sign in here</a>
                </p>
            </div>
            
            <div class="game-info">
                <div class="info-section">
                    <div class="info-title">Account Benefits</div>
                    <div class="info-value">
                        • Save your game progress<br>
                        • Upload and manage videos<br>
                        • Connect with friends<br>
                        • Personalized recommendations
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>