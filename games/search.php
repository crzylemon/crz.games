<?php
require_once '../db.php';
require_once '../user/session.php';

$search = $_GET['search'] ?? '';
$engine_filter = $_GET['engine'] ?? '';

// Get blocked engines for current user
$blocked_engines = [];
if ($current_user = getCurrentUser()) {
    try {
        $stmt = $pdo->prepare("SELECT blocked_engines FROM accounts WHERE id = ?");
        $stmt->execute([$current_user['id']]);
        $result = $stmt->fetchColumn();
        if ($result) {
            $blocked_engines = explode(',', $result);
        }
    } catch (PDOException $e) {}
}

try {
    $sql = "SELECT g.*, a.username, a.display_name, ul.id as in_library FROM games g JOIN accounts a ON g.owner_user_id = a.id LEFT JOIN user_library ul ON g.id = ul.game_id AND ul.user_id = ? WHERE g.status IN ('PLAYABLE', 'PUBLIC_UNPLAYABLE')";
    $params = [$current_user['id'] ?? 0];
    
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
    
    $sql .= " ORDER BY g.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $games = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$mapStatusToLabel = [
    'PLAYABLE' => '[HideMe]',
    'PUBLIC_UNPLAYABLE' => 'Non-playable',
    'DRAFT' => 'Draft',
    'WHITELISTED' => 'Whitelisted',
    'PENDING_APPROVAL_P' => 'Pending Approval (Playable)',
    "PENDING_APPROVAL_PU" => 'Pending Approval (Non-playable)',
    'REJECT' => 'Rejected',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .game-card {
            background: linear-gradient(135deg, #1e2329 0%, #252b33 100%);
            border-radius: 4px;
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid #3c4043;
        }
        .game-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            border-color: #66c0f4;
        }
        .game-card-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .game-card-content {
            padding: 15px;
        }
        .game-card-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .game-card-genre {
            color: #66c0f4;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .game-card-author {
            color: #8f98a0;
            font-size: 0.8rem;
            margin-bottom: 8px;
        }
        .game-card-author a {
            color: #66c0f4;
            text-decoration: none;
        }
        .game-card-author a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="container" style="margin-top: 80px;">
        <div class="game-header">
            <h1 class="game-title">Search Games</h1>
            <div class="game-genre">Find your next favorite game</div>
            
            <form method="GET" style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search games..." style="flex: 1; min-width: 200px; padding: 10px; background: #1e2329; border: 1px solid #333; border-radius: 4px; color: white;">
                <select name="engine" style="padding: 10px; background: #1e2329; border: 1px solid #333; border-radius: 4px; color: white;">
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
                <button type="submit" style="padding: 10px 20px; background: #00d4ff; color: white; border: none; border-radius: 4px; cursor: pointer;">Search</button>
                <?php if ($search || $engine_filter): ?>
                    <a href="?" style="padding: 10px 20px; background: #666; color: white; text-decoration: none; border-radius: 4px;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="games-grid">
            <?php foreach ($games as $game): ?>
                <div class="game-card" onclick="location.href='game.php?slug=<?= urlencode($game['slug']) ?>'">
                    <?php if ($game['thumbnail_small']): ?>
                        <img src="<?= htmlspecialchars($game['thumbnail_small']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" class="game-card-image">
                    <?php endif; ?>
                    <div class="game-card-content">
                        <div class="game-card-title"><?= htmlspecialchars($game['title']) ?></div>
                        <div class="game-card-author">by <a href="profile.php?user=<?= urlencode($game['username']) ?>"><?= htmlspecialchars($game['display_name'] ?: $game['username']) ?></a></div>
                        <?php if ($game['genre']): ?>
                            <div class="game-card-genre"><?= htmlspecialchars($game['genre']) ?></div>
                        <?php endif; ?>
                        <?php $statusLabel = $mapStatusToLabel[$game['status']] ?? '[HideMe]'; ?>
                        <?php if ($statusLabel !== '[HideMe]'): ?>
                            <span class="game-status status-<?= strtolower($game['status']) ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>