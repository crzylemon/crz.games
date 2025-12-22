<?php
require_once '../db.php';
require_once '../user/session.php';

$current_user = getCurrentUser();
if (!$current_user) {
    header('Location: ../user/login.php');
    exit;
}

$message = '';

if ($_POST) {
    $blocked_engines = $_POST['blocked_engines'] ?? [];
    $blocked_engines_str = implode(',', $blocked_engines);
    $can_games_read_email = isset($_POST['can_games_read_email']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE accounts SET blocked_engines = ?, games_read_email = ? WHERE id = ?");
        $stmt->execute([$blocked_engines_str, $can_games_read_email, $current_user['id']]);
        $message = 'Settings saved successfully';
    } catch (PDOException $e) {
        $message = 'Error saving settings';
    }
}

// Get current settings
$blocked_engines = [];
$games_read_email = false;
try {
    $stmt = $pdo->prepare("SELECT blocked_engines, games_read_email FROM accounts WHERE id = ?");
    $stmt->execute([$current_user['id']]);
    $result = $stmt->fetch();
    if ($result) {
        if ($result['blocked_engines']) {
            $blocked_engines = explode(',', $result['blocked_engines']);
        }
        $games_read_email = (bool)$result['games_read_email'];
    }
} catch (PDOException $e) {}

$engines = ['CRENGINE', 'Unity', 'Unreal', 'Godot', 'GameMaker20', 'GameMaker', 'Construct', 'Phaser', 'RPG Maker', 'Scratch', 'Scratch Mod', 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .settings-form {
            background: #1e2329;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            color: #66c0f4;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .submit-button {
            background: linear-gradient(90deg, #06bfff 0%, #2d73ff 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }
        .message {
            background: #4caf50;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="container">
        <div class="game-header">
            <h1 class="game-title">Game Settings</h1>
            <div class="game-genre">Customize your gaming experience</div>
        </div>

        <div class="settings-form">
            <?php if ($message): ?>
                <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Main Settings</label>
                    <p style="color: #888; font-size: 0.9rem; margin-bottom: 15px;">
                        These settings are for general use.
                    </p>
                    <div class="checkbox-item">
                        <input type="checkbox" name="can_games_read_email" value="1" id="can_games_read_email" <?= $games_read_email ? 'checked' : '' ?>>
                        <label for="can_games_read_email">Allow games to read email?</label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Block Game Engines</label>
                    <p style="color: #888; font-size: 0.9rem; margin-bottom: 15px;">
                        Games made with these engines will be hidden from search, library, and profiles.
                    </p>
                    <div class="checkbox-grid">
                        <?php foreach ($engines as $engine): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="blocked_engines[]" value="<?= htmlspecialchars($engine) ?>" 
                                       <?= in_array($engine, $blocked_engines) ? 'checked' : '' ?> 
                                       id="engine_<?= htmlspecialchars($engine) ?>">
                                <label for="engine_<?= htmlspecialchars($engine) ?>"><?= htmlspecialchars($engine) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="submit-button">Save Settings</button>
            </form>
        </div>
    </div>
</body>
</html>