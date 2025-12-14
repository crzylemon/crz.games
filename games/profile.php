<?php
require_once '../db.php';
require_once '../user/session.php';

$username = $_GET['user'] ?? '';
if (!$username) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
    $stmt->execute([$username]);
    $profile_user = $stmt->fetch();
    
    if (!$profile_user) {
        header('Location: index.php');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM games WHERE owner_user_id = ? AND status IN ('PLAYABLE', 'PUBLIC_UNPLAYABLE') ORDER BY created_at DESC");
    $stmt->execute([$profile_user['id']]);
    $user_games = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$current_user = getCurrentUser();

$mapStatusToLabel = [
    'PLAYABLE' => '[HideMe]',
    'PUBLIC_UNPLAYABLE' => 'Non-playable',
    'DRAFT' => 'Draft',
    'WHITELISTED' => 'Whitelisted',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile_user['username']) ?> - CRZ Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .profile-header {
            background: #1e2329;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(45deg, #06bfff, #2d73ff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
        }
        .profile-info h1 {
            margin: 0 0 10px 0;
            color: #66c0f4;
        }
        .profile-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        .stat {
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #66c0f4;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #8f98a0;
        }
        .games-section h2 {
            margin-bottom: 20px;
            color: #66c0f4;
        }
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
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
        .no-games {
            text-align: center;
            color: #8f98a0;
            padding: 40px;
            background: #1e2329;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-header">
            <div class="profile-avatar">
                <?= strtoupper(substr($profile_user['username'], 0, 1)) ?>
            </div>
            <div class="profile-info">
                <h1><?= htmlspecialchars($profile_user['display_name'] ?: $profile_user['username']) ?></h1>
                <p style="color: #8f98a0; margin: 5px 0;">@<?= htmlspecialchars($profile_user['username']) ?></p>
                <p>Member since <?= date('F Y', strtotime($profile_user['created_at'])) ?></p>
                <div class="profile-stats">
                    <div class="stat">
                        <div class="stat-number"><?= count($user_games) ?></div>
                        <div class="stat-label">Games</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?= date('M j', strtotime($profile_user['last_login'] ?? $profile_user['created_at'])) ?></div>
                        <div class="stat-label">Last Active</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="games-section">
            <h2><?= htmlspecialchars($profile_user['display_name'] ?: $profile_user['username']) ?>'s Games</h2>
            
            <?php if (empty($user_games)): ?>
                <div class="no-games">
                    <h3>No games yet</h3>
                    <p><?= htmlspecialchars($profile_user['display_name'] ?: $profile_user['username']) ?> hasn't published any games yet.</p>
                </div>
            <?php else: ?>
                <div class="games-grid">
                    <?php foreach ($user_games as $game): ?>
                        <div class="game-card" onclick="location.href='game.php?slug=<?= urlencode($game['slug']) ?>'">
                            <?php if ($game['thumbnail_small']): ?>
                                <img src="<?= htmlspecialchars($game['thumbnail_small']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" class="game-card-image">
                            <?php endif; ?>
                            <div class="game-card-content">
                                <div class="game-card-title"><?= htmlspecialchars($game['title']) ?></div>
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
            <?php endif; ?>
        </div>
    </div>
</body>
</html>