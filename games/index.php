<?php
require_once '../db.php';
require_once '../user/session.php';

$current_user = getCurrentUser();

try {
    // Get admin settings
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('hero_game_id', 'event_title', 'event_description', 'event_image', 'event_enabled')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $hero_game_id = $settings['hero_game_id'] ?? '';
    $event_enabled = $settings['event_enabled'] ?? 0;
    $event_title = $settings['event_title'] ?? '';
    $event_description = $settings['event_description'] ?? '';
    $event_image = $settings['event_image'] ?? '';
    
    // Get hero game (admin selected or most played)
    if ($hero_game_id) {
        $stmt = $pdo->prepare("SELECT g.*, a.username, a.display_name FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.id = ? AND g.status = 'PLAYABLE'");
        $stmt->execute([$hero_game_id]);
        $featured_game = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT g.*, a.username, a.display_name FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.status = 'PLAYABLE' ORDER BY g.play_count DESC LIMIT 1");
        $stmt->execute();
        $featured_game = $stmt->fetch();
    }
    
    // Get featured games for New Releases section
    $stmt = $pdo->prepare("SELECT g.*, a.username, a.display_name FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.status = 'PLAYABLE' AND g.featured = 1 ORDER BY g.created_at DESC LIMIT 6");
    $stmt->execute();
    $featured_releases = $stmt->fetchAll();
    
    // If no featured games, fall back to new releases
    if (empty($featured_releases)) {
        $stmt = $pdo->prepare("SELECT g.*, a.username, a.display_name FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.status = 'PLAYABLE' ORDER BY g.created_at DESC LIMIT 6");
        $stmt->execute();
        $featured_releases = $stmt->fetchAll();
    }
    
    // Get popular games
    $stmt = $pdo->prepare("SELECT g.*, a.username, a.display_name FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.status = 'PLAYABLE' ORDER BY g.play_count DESC LIMIT 8");
    $stmt->execute();
    $popular_games = $stmt->fetchAll();
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
    <title>Games - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .game-card {
            background: #1e2329;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s;
            cursor: pointer;
        }
        .game-card:hover {
            transform: translateY(-5px);
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

        .upload-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #06bfff 0%, #2d73ff 100%);
            color: white;
            border: none;
            padding: 15px 20px;
            border-radius: 3px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            text-transform: uppercase;
            transition: all 0.2s;
        }
        .upload-button:hover {
            background: linear-gradient(135deg, #0aa3d9 0%, #2558cc 100%);
            transform: translateY(-2px);
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
        .hero-section {
            margin-bottom: 30px;
        }
        .featured-game {
            position: relative;
            height: 400px;
            border-radius: 4px;
            overflow: hidden;
            cursor: pointer;
        }
        .hero-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .hero-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.9));
            padding: 40px 30px 30px;
        }
        .hero-title {
            font-size: 2.5rem;
            margin: 0 0 10px 0;
            color: white;
        }
        .hero-description {
            color: #c7d5e0;
            margin: 10px 0;
        }
        .hero-meta {
            color: #8f98a0;
        }
        .section {
            margin: 40px 0;
        }
        .section-title {
            color: #66c0f4;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        .games-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .game-card-small {
            background: linear-gradient(135deg, #1e2329 0%, #252b33 100%);
            border-radius: 4px;
            overflow: hidden;
            cursor: pointer;
            border: 1px solid #3c4043;
            transition: all 0.3s;
        }
        .game-card-small:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            border-color: #66c0f4;
        }
        .card-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        .card-content {
            padding: 10px;
        }
        .card-title {
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .card-author {
            font-size: 0.75rem;
            color: #8f98a0;
        }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="container" style="margin-top: 80px;">
        <?php if ($event_enabled && $event_title): ?>
            <div class="hero-section">
                <div class="featured-game" style="background: linear-gradient(135deg, #1e2329 0%, #252b33 100%); display: flex; align-items: center; justify-content: center; text-align: center;">
                    <?php if ($event_image): ?>
                        <img src="<?= htmlspecialchars($event_image) ?>" alt="<?= htmlspecialchars($event_title) ?>" class="hero-image">
                    <?php endif; ?>
                    <div class="hero-content">
                        <h1 class="hero-title"><?= htmlspecialchars($event_title) ?></h1>
                        <p class="hero-description"><?= htmlspecialchars($event_description) ?></p>
                    </div>
                </div>
            </div>
        <?php elseif ($featured_game): ?>
            <div class="hero-section">
                <div class="featured-game" onclick="location.href='game.php?slug=<?= urlencode($featured_game['slug']) ?>'">
                    <?php if ($featured_game['thumbnail_big']): ?>
                        <img src="<?= htmlspecialchars($featured_game['thumbnail_big']) ?>" alt="<?= htmlspecialchars($featured_game['title']) ?>" class="hero-image">
                    <?php endif; ?>
                    <div class="hero-content">
                        <h1 class="hero-title"><?= htmlspecialchars($featured_game['title']) ?></h1>
                        <p class="hero-description"><?= htmlspecialchars(substr($featured_game['description'] ?? '', 0, 200)) ?>...</p>
                        <div class="hero-meta">by <?= htmlspecialchars($featured_game['display_name'] ?: $featured_game['username']) ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="search-bar">
            <form method="GET" action="search.php" style="display: flex; gap: 10px; max-width: 600px; margin: 20px auto;">
                <input type="text" name="search" placeholder="Search the store..." style="flex: 1; padding: 12px; background: #1e2329; border: 1px solid #333; border-radius: 4px; color: white; font-size: 1rem;">
                <button type="submit" style="padding: 12px 24px; background: linear-gradient(135deg, #06bfff 0%, #2d73ff 100%); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Search</button>
            </form>
        </div>

        <div class="section">
            <h2 class="section-title"><?= !empty($featured_releases) && $stmt->rowCount() > 0 ? 'Featured Games' : 'New Releases' ?></h2>
            <div class="games-row">
                <?php foreach ($featured_releases as $game): ?>
                    <div class="game-card-small" onclick="location.href='game.php?slug=<?= urlencode($game['slug']) ?>'">
                        <?php if ($game['thumbnail_small']): ?>
                            <img src="<?= htmlspecialchars($game['thumbnail_small']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" class="card-image">
                        <?php endif; ?>
                        <div class="card-content">
                            <div class="card-title"><?= htmlspecialchars($game['title']) ?></div>
                            <div class="card-author">by <?= htmlspecialchars($game['display_name'] ?: $game['username']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="section">
            <h2 class="section-title">Popular Games</h2>
            <div class="games-grid">
                <?php foreach ($popular_games as $game): ?>
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
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($current_user): ?>
        <button class="upload-button" onclick="location.href='upload.php'">+ Upload Game</button>
    <?php endif; ?>
</body>
</html>