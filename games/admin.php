<?php
require_once '../db.php';
require_once '../user/session.php';

$user = getCurrentUser();
if (!$user || $user['id'] != 1) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = $_POST['game_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE games SET status = 'PLAYABLE' WHERE id = ?");
        $stmt->execute([$game_id]);
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE games SET status = 'DRAFT' WHERE id = ?");
        $stmt->execute([$game_id]);
    } elseif ($action === 'update_banner') {
        $banner_text = $_POST['banner_text'] ?? '';
        $banner_type = $_POST['banner_type'] ?? 'info';
        $banner_enabled = isset($_POST['banner_enabled']) ? 1 : 0;
        
        // Create site_settings table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(255) UNIQUE,
            setting_value TEXT
        )");
        
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['banner_text', $banner_text]);
        $stmt->execute(['banner_type', $banner_type]);
        $stmt->execute(['banner_enabled', $banner_enabled]);
    }
    
    header('Location: admin.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT g.*, a.username, a.display_name FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.status = 'PENDING_APPROVAL' ORDER BY g.created_at ASC");
    $stmt->execute();
    $pending_games = $stmt->fetchAll();
    
    // Get current banner settings
    $banner_text = '';
    $banner_type = 'info';
    $banner_enabled = 0;
    
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('banner_text', 'banner_type', 'banner_enabled')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $banner_text = $settings['banner_text'] ?? '';
    $banner_type = $settings['banner_type'] ?? 'info';
    $banner_enabled = $settings['banner_enabled'] ?? 0;
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-header {
            background: #d32f2f;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .pending-games {
            background: #1e2329;
            border-radius: 8px;
            overflow: hidden;
        }
        .game-item {
            padding: 20px;
            border-bottom: 1px solid #3c4043;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .game-item:last-child {
            border-bottom: none;
        }
        .game-details h3 {
            margin: 0 0 5px 0;
            color: #66c0f4;
        }
        .game-meta {
            color: #8f98a0;
            font-size: 0.9rem;
        }
        .admin-actions {
            display: flex;
            gap: 10px;
        }
        .approve-btn {
            background: #4caf50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .reject-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .view-btn {
            background: #2196f3;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
        }
        .banner-section {
            background: #1e2329;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            display: block;
            color: #66c0f4;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 8px;
            background: #16202d;
            border: 1px solid #3c4043;
            border-radius: 4px;
            color: #c7d5e0;
        }
        .form-textarea {
            height: 80px;
            resize: vertical;
        }
        .update-btn {
            background: #2a5298;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <button onclick="window.location.href='index.php'" style="position: fixed; top: 20px; right: 20px; background: #2a5298; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; z-index: 1000;">← Back to Games</button>
    <div class="container">
        <div class="admin-header">
            <h1>Admin Panel</h1>
            <p>Manage game approvals and site administration</p>
        </div>

        <div class="banner-section">
            <h2>Site Banner</h2>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="banner_enabled" <?= $banner_enabled ? 'checked' : '' ?>>
                        Enable Site Banner
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label" for="banner_text">Banner Text</label>
                    <textarea id="banner_text" name="banner_text" class="form-textarea" placeholder="Enter banner message..."><?= htmlspecialchars($banner_text) ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="banner_type">Banner Type</label>
                    <select id="banner_type" name="banner_type" class="form-select">
                        <option value="info" <?= $banner_type === 'info' ? 'selected' : '' ?>>Info (Blue)</option>
                        <option value="warning" <?= $banner_type === 'warning' ? 'selected' : '' ?>>Warning (Orange)</option>
                        <option value="error" <?= $banner_type === 'error' ? 'selected' : '' ?>>Error (Red)</option>
                        <option value="success" <?= $banner_type === 'success' ? 'selected' : '' ?>>Success (Green)</option>
                    </select>
                </div>
                <button type="submit" name="action" value="update_banner" class="update-btn">Update Banner</button>
            </form>
        </div>

        <h2>Pending Game Approvals (<?= count($pending_games) ?>)</h2>
        
        <?php if (empty($pending_games)): ?>
            <div class="pending-games">
                <div class="game-item">
                    <p>No games pending approval.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="pending-games">
                <?php foreach ($pending_games as $game): ?>
                    <div class="game-item">
                        <div class="game-details">
                            <h3><?= htmlspecialchars($game['title']) ?></h3>
                            <div class="game-meta">
                                By <?= htmlspecialchars($game['display_name'] ?: $game['username']) ?> • 
                                <?= htmlspecialchars($game['genre']) ?> • 
                                Version <?= htmlspecialchars($game['current_version']) ?> • 
                                Submitted <?= date('M j, Y', strtotime($game['created_at'])) ?>
                            </div>
                            <?php if ($game['description']): ?>
                                <p style="margin: 10px 0 0 0; color: #c7d5e0;"><?= htmlspecialchars(substr($game['description'], 0, 150)) ?><?= strlen($game['description']) > 150 ? '...' : '' ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="admin-actions">
                            <a href="game.php?slug=<?= urlencode($game['slug']) ?>" class="view-btn">View</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                                <button type="submit" name="action" value="approve" class="approve-btn">Approve</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                                <button type="submit" name="action" value="reject" class="reject-btn">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>