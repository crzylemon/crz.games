<?php
require_once '../db.php';
require_once '../user/session.php';
require_once 'includes/admin.php';

$user = getCurrentUser();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = $_POST['game_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        // if it's PENDING_APPROVAL_P, set to PLAYABLE, but if _PU, then set to PUBLIC_UNPLAYABLE
        if (strpos($game_id, '_PU') !== false) {
            $stmt = $pdo->prepare("UPDATE games SET status = 'PUBLIC_UNPLAYABLE' WHERE id = ?");
            $stmt->execute([$game_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE games SET status = 'PLAYABLE' WHERE id = ?");
            $stmt->execute([$game_id]);
        }
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
    } elseif ($action === 'add_admin') {
        $username = $_POST['admin_username'] ?? '';
        if ($username) {
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE username = ?");
            $stmt->execute([$username]);
            $userId = $stmt->fetchColumn();
            if ($userId && addAdmin($userId)) {
                $success_message = 'Admin added successfully';
            } else {
                $error_message = 'User not found or already admin';
            }
        }
    } elseif ($action === 'remove_admin') {
        $userId = $_POST['admin_id'] ?? 0;
        if ($userId && removeAdmin($userId)) {
            $success_message = 'Admin removed successfully';
        } else {
            $error_message = 'Cannot remove this admin';
        }
    } elseif ($action === 'set_hero_game') {
        $game_id = $_POST['hero_game_id'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['hero_game_id', $game_id]);
        $success_message = 'Hero game updated';
    } elseif ($action === 'toggle_featured') {
        $game_id = $_POST['game_id'] ?? 0;
        $featured = $_POST['featured'] ?? 0;
        $stmt = $pdo->prepare("UPDATE games SET featured = ? WHERE id = ?");
        $stmt->execute([$featured, $game_id]);
        $success_message = $featured ? 'Game added to featured' : 'Game removed from featured';
    } elseif ($action === 'set_event_banner') {
        $event_title = $_POST['event_title'] ?? '';
        $event_description = $_POST['event_description'] ?? '';
        $event_image = $_POST['event_image'] ?? '';
        $event_enabled = isset($_POST['event_enabled']) ? 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['event_title', $event_title]);
        $stmt->execute(['event_description', $event_description]);
        $stmt->execute(['event_image', $event_image]);
        $stmt->execute(['event_enabled', $event_enabled]);
        $success_message = 'Event banner updated';
    }
    
    header('Location: admin.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT g.*, a.username, a.display_name FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.status = 'PENDING_APPROVAL_P' || g.status = 'PENDING_APPROVAL_PU' ORDER BY g.created_at ASC");
    $stmt->execute();
    $pending_games = $stmt->fetchAll();
    
    // Get current settings
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('banner_text', 'banner_type', 'banner_enabled', 'hero_game_id', 'event_title', 'event_description', 'event_image', 'event_enabled')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $banner_text = $settings['banner_text'] ?? '';
    $banner_type = $settings['banner_type'] ?? 'info';
    $banner_enabled = $settings['banner_enabled'] ?? 0;
    $hero_game_id = $settings['hero_game_id'] ?? '';
    $event_title = $settings['event_title'] ?? '';
    $event_description = $settings['event_description'] ?? '';
    $event_image = $settings['event_image'] ?? '';
    $event_enabled = $settings['event_enabled'] ?? 0;
    
    // Get all games for selection
    $stmt = $pdo->prepare("SELECT g.id, g.title, g.slug, a.username FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.status IN ('PLAYABLE', 'PUBLIC_UNPLAYABLE') ORDER BY g.title");
    $stmt->execute();
    $all_games = $stmt->fetchAll();
    
    // Add featured column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE games ADD COLUMN featured TINYINT(1) DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists
    }
    
    // Get admin list with usernames
    $adminIds = getAdminList();
    $adminUsers = [];
    if (!empty($adminIds)) {
        $placeholders = str_repeat('?,', count($adminIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, username, display_name FROM accounts WHERE id IN ($placeholders)");
        $stmt->execute($adminIds);
        $adminUsers = $stmt->fetchAll();
    }
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
        .search-results {
            max-height: 200px;
            overflow-y: auto;
            background: #16202d;
            border: 1px solid #3c4043;
            border-radius: 4px;
            margin-top: 5px;
            display: none;
        }
        .search-result {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #3c4043;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-result:hover {
            background: #2a2a2a;
        }
        .game-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 3px;
            margin-right: 10px;
        }
        .game-name {
            font-weight: bold;
            color: #c7d5e0;
        }
        .game-author {
            font-size: 0.8rem;
            color: #8f98a0;
        }
        .selected-game {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #16202d;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .remove-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            margin-left: auto;
        }
        .no-selection {
            color: #8f98a0;
            font-style: italic;
            padding: 10px;
        }
        .current-selection {
            margin-top: 10px;
        }
        .featured-list {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    <div class="container" style="margin-top: 80px;">
        <div class="admin-header">
            <h1>Admin Panel</h1>
            <p>Manage game approvals and site administration</p>
            <p>Links: <a href="filemanager.php">File Manager</a><!-- if localhost, show phpmyadmin (/phpmyadmin) --> <?php if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false): ?>, <a href="/phpmyadmin">phpMyAdmin</a><?php endif; ?>
        </div>

        <?php if (isset($success_message)): ?>
            <div style="background: #4caf50; color: white; padding: 10px; border-radius: 4px; margin-bottom: 20px;"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div style="background: #f44336; color: white; padding: 10px; border-radius: 4px; margin-bottom: 20px;"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="banner-section">
            <h2>Admin Management</h2>
            <div style="display: flex; gap: 30px;">
                <div style="flex: 1;">
                    <h3>Current Admins</h3>
                    <?php foreach ($adminUsers as $admin): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #16202d; margin-bottom: 5px; border-radius: 4px;">
                            <span><?= htmlspecialchars($admin['display_name'] ?: $admin['username']) ?> (<?= htmlspecialchars($admin['username']) ?>)</span>
                            <?php if ($admin['id'] != 1): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                                    <button type="submit" name="action" value="remove_admin" style="background: #f44336; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;" onclick="return confirm('Remove admin access?')">Remove</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #888; font-size: 12px;">Owner</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="flex: 1;">
                    <h3>Add Admin</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="admin_username">Username</label>
                            <input type="text" id="admin_username" name="admin_username" class="form-input" placeholder="Enter username" required>
                        </div>
                        <button type="submit" name="action" value="add_admin" class="update-btn">Add Admin</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="banner-section">
            <h2>Featured Content Management</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                <div>
                    <h3>Hero Game Selection</h3>
                    <div class="form-group">
                        <label class="form-label">Search and Select Hero Game</label>
                        <input type="text" id="hero-search" class="form-input" placeholder="Search games..." onkeyup="searchGames('hero')">
                        <div id="hero-results" class="search-results"></div>
                        <div id="current-hero" class="current-selection">
                            <?php if ($hero_game_id): ?>
                                <?php 
                                $stmt = $pdo->prepare("SELECT g.title, g.thumbnail_small, a.username FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.id = ?");
                                $stmt->execute([$hero_game_id]);
                                $hero = $stmt->fetch();
                                if ($hero): ?>
                                    <div class="selected-game">
                                        <img src="<?= htmlspecialchars($hero['thumbnail_small']) ?>" class="game-thumb">
                                        <div>
                                            <div class="game-name"><?= htmlspecialchars($hero['title']) ?></div>
                                            <div class="game-author">by <?= htmlspecialchars($hero['username']) ?></div>
                                        </div>
                                        <button onclick="removeHero()" class="remove-btn">Remove</button>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="no-selection">No hero game selected (using most played)</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div>
                    <h3>Event Banner (Replaces Hero)</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">
                                <input type="checkbox" name="event_enabled" <?= $event_enabled ? 'checked' : '' ?>>
                                Enable Event Banner
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="event_title">Event Title</label>
                            <input type="text" id="event_title" name="event_title" class="form-input" value="<?= htmlspecialchars($event_title) ?>" placeholder="Special Event Title">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="event_description">Event Description</label>
                            <textarea id="event_description" name="event_description" class="form-textarea" placeholder="Event description..."><?= htmlspecialchars($event_description) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="event_image">Event Image URL</label>
                            <input type="url" id="event_image" name="event_image" class="form-input" value="<?= htmlspecialchars($event_image) ?>" placeholder="https://example.com/event.jpg">
                        </div>
                        <button type="submit" name="action" value="set_event_banner" class="update-btn">Update Event</button>
                    </form>
                </div>
            </div>
            
            <h3>Featured Games Management</h3>
            <div class="form-group">
                <label class="form-label">Search and Feature Games</label>
                <input type="text" id="featured-search" class="form-input" placeholder="Search games to feature..." onkeyup="searchGames('featured')">
                <div id="featured-results" class="search-results"></div>
            </div>
            
            <div class="featured-list">
                <h4>Currently Featured Games:</h4>
                <div id="current-featured">
                    <?php 
                    $stmt = $pdo->prepare("SELECT g.id, g.title, g.thumbnail_small, a.username FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.featured = 1 ORDER BY g.title");
                    $stmt->execute();
                    $featured_games = $stmt->fetchAll();
                    foreach ($featured_games as $game): ?>
                        <div class="selected-game" data-game-id="<?= $game['id'] ?>">
                            <img src="<?= htmlspecialchars($game['thumbnail_small']) ?>" class="game-thumb">
                            <div>
                                <div class="game-name"><?= htmlspecialchars($game['title']) ?></div>
                                <div class="game-author">by <?= htmlspecialchars($game['username']) ?></div>
                            </div>
                            <button onclick="removeFeatured(<?= $game['id'] ?>)" class="remove-btn">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
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
    
    <script>
    function searchGames(type) {
        const query = document.getElementById(type + '-search').value;
        const results = document.getElementById(type + '-results');
        
        if (query.length < 2) {
            results.style.display = 'none';
            return;
        }
        
        fetch('search_games.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(games => {
                results.innerHTML = '';
                games.forEach(game => {
                    const div = document.createElement('div');
                    div.className = 'search-result';
                    div.innerHTML = `
                        <img src="${game.thumbnail_small}" class="game-thumb">
                        <div>
                            <div class="game-name">${game.title}</div>
                            <div class="game-author">by ${game.username}</div>
                        </div>
                    `;
                    div.onclick = () => selectGame(game, type);
                    results.appendChild(div);
                });
                results.style.display = games.length ? 'block' : 'none';
            });
    }
    
    function selectGame(game, type) {
        if (type === 'hero') {
            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=set_hero_game&hero_game_id=${game.id}`
            }).then(() => location.reload());
        } else {
            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=toggle_featured&game_id=${game.id}&featured=1`
            }).then(() => location.reload());
        }
    }
    
    function removeHero() {
        fetch('admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=set_hero_game&hero_game_id='
        }).then(() => location.reload());
    }
    
    function removeFeatured(gameId) {
        fetch('admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=toggle_featured&game_id=${gameId}&featured=0`
        }).then(() => location.reload());
    }
    </script>
</body>
</html>