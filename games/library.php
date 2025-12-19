<?php
require_once '../db.php';
require_once '../user/session.php';

$current_user = getCurrentUser();
if (!$current_user) {
    header('Location: ../user/login.php');
    exit;
}

$search = $_GET['search'] ?? '';
$engine_filter = $_GET['engine'] ?? '';

// Get blocked engines for current user
$blocked_engines = [];
try {
    $stmt = $pdo->prepare("SELECT blocked_engines FROM accounts WHERE id = ?");
    $stmt->execute([$current_user['id']]);
    $result = $stmt->fetchColumn();
    if ($result) {
        $blocked_engines = explode(',', $result);
    }
} catch (PDOException $e) {}

try {
    $sql = "SELECT g.*, a.username, a.display_name FROM games g JOIN accounts a ON g.owner_user_id = a.id JOIN user_library ul ON g.id = ul.game_id WHERE ul.user_id = ?";
    $params = [$current_user['id']];
    
    if ($search) {
        $sql .= " AND (g.title LIKE ? OR g.description LIKE ? OR g.genre LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($engine_filter) {
        $sql .= " AND g.engine = ?";
        $params[] = $engine_filter;
    }
    
    if (!empty($blocked_engines)) {
        $placeholders = str_repeat('?,', count($blocked_engines) - 1) . '?';
        $sql .= " AND g.engine NOT IN ($placeholders)";
        $params = array_merge($params, $blocked_engines);
    }
    
    $sql .= " ORDER BY ul.added_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $games = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .library-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .library-item {
            background: linear-gradient(135deg, #1e2329 0%, #252b33 100%);
            border-radius: 4px;
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid #3c4043;
            position: relative;
        }
        .library-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            border-color: #66c0f4;
        }
        .library-item .remove-overlay {
            position: absolute;
            top: 8px;
            right: 8px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .library-item:hover .remove-overlay {
            opacity: 1;
        }
        .library-remove-btn {
            background: rgba(211, 47, 47, 0.9);
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.7rem;
            cursor: pointer;
            font-weight: bold;
        }
        .library-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .library-content {
            padding: 12px;
        }
        .library-title {
            font-size: 1rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .library-engine {
            color: #888;
            font-size: 0.8rem;
        }
        .filter-section {
            background: #1e2329;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="container">
        <div class="game-header">
            <h1 class="game-title">My Library</h1>
            <div class="game-genre">Your personal game collection</div>
        </div>

        <div class="filter-section">
            <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search library..." style="flex: 1; min-width: 200px; padding: 10px; background: #16202d; border: 1px solid #333; border-radius: 4px; color: white;">
                <select name="engine" style="padding: 10px; background: #16202d; border: 1px solid #333; border-radius: 4px; color: white;">
                    <option value="">All Engines</option>
                    <option value="CRENGINE" <?= $engine_filter === 'CRENGINE' ? 'selected' : '' ?>>CRENGINE</option>
                    <option value="Unity" <?= $engine_filter === 'Unity' ? 'selected' : '' ?>>Unity</option>
                    <option value="Unreal" <?= $engine_filter === 'Unreal' ? 'selected' : '' ?>>Unreal Engine</option>
                    <option value="Godot" <?= $engine_filter === 'Godot' ? 'selected' : '' ?>>Godot</option>
                    <option value="GameMaker20" <?= $engine_filter === 'GameMaker20' ? 'selected' : '' ?>>GameMaker 2.0+</option>
                    <option value="GameMaker" <?= $engine_filter === 'GameMaker' ? 'selected' : '' ?>>GameMaker</option>
                    <option value="Construct" <?= $engine_filter === 'Construct' ? 'selected' : '' ?>>Construct</option>
                    <option value="Phaser" <?= $engine_filter === 'Phaser' ? 'selected' : '' ?>>Phaser</option>
                    <option value="RPG Maker" <?= $engine_filter === 'RPG Maker' ? 'selected' : '' ?>>RPG Maker</option>
                    <option value="Scratch" <?= $engine_filter === 'Scratch' ? 'selected' : '' ?>>Scratch</option>
                    <option value="Scratch Mod" <?= $engine_filter === 'Scratch Mod' ? 'selected' : '' ?>>Scratch Mod</option>
                    <option value="Other" <?= $engine_filter === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
                <button type="submit" style="padding: 10px 20px; background: #00d4ff; color: white; border: none; border-radius: 4px; cursor: pointer;">Filter</button>
                <?php if ($search || $engine_filter): ?>
                    <a href="?" style="padding: 10px 20px; background: #666; color: white; text-decoration: none; border-radius: 4px;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="library-grid">
            <?php foreach ($games as $game): ?>
                <div class="library-item" onclick="location.href='game.php?slug=<?= urlencode($game['slug']) ?>'">
                    <?php if ($game['thumbnail_small']): ?>
                        <img src="<?= htmlspecialchars($game['thumbnail_small']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" class="library-image">
                    <?php endif; ?>
                    <div class="library-content">
                        <div class="library-title"><?= htmlspecialchars($game['title']) ?></div>
                        <div class="library-engine"><?= htmlspecialchars($game['engine'] ?? 'Unknown') ?></div>
                    </div>
                    <div class="remove-overlay">
                        <form method="POST" action="remove_from_library.php" onclick="event.stopPropagation();">
                            <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                            <button type="submit" class="library-remove-btn" onclick="return confirm('Remove from library?')">×</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>