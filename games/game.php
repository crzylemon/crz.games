<?php
require_once '../db.php';
require_once '../user/session.php';
require_once 'includes/admin.php';
$user = getCurrentUser();
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: /games/');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT g.*, a.username, a.display_name, ul.id as in_library FROM games g JOIN accounts a ON g.owner_user_id = a.id LEFT JOIN user_library ul ON g.id = ul.game_id AND ul.user_id = ? WHERE g.slug = ?");
    $stmt->execute([$user['id'] ?? 0, $slug]);
    $game = $stmt->fetch();
    
    if (!$game) {
        header('HTTP/1.0 404 Not Found');
        echo "Game not found";
        exit;
    }
    
    $screenshots = json_decode($game['screenshots'] ?? '[]', true);
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
// Check if user can view this game
if (!($game['status'] === 'PLAYABLE' || $game['status'] === 'PUBLIC_UNPLAYABLE' || ($user && (isAdmin($user['id']) || $user['id'] === $game['owner_user_id'])))) {
    header('Location: /games/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($game['title']) ?> - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .library-actions {
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .library-status {
            color: #4caf50;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .add-library-btn {
            background: linear-gradient(135deg, #06bfff 0%, #2d73ff 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .add-library-btn:hover {
            background: linear-gradient(135deg, #0aa3d9 0%, #2558cc 100%);
            transform: translateY(-1px);
        }
        .remove-library-btn {
            background: #3c4043;
            color: #c7d5e0;
            border: 1px solid #5a5a5a;
            padding: 8px 16px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .remove-library-btn:hover {
            background: #d32f2f;
            border-color: #d32f2f;
            color: white;
        }
        .game-content {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .game-media {
            flex: 2;
        }
        .game-info {
            flex: 1;
            background: #1e2329;
            padding: 20px;
            border-radius: 4px;
            height: fit-content;
        }
        .play-button {
            width: 100%;
            background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .play-button:hover:not(:disabled) {
            background: linear-gradient(135deg, #43a047 0%, #5cb85c 100%);
            transform: translateY(-1px);
        }
        .play-button:disabled {
            background: #3c4043;
            color: #8f98a0;
            cursor: not-allowed;
        }
        .info-section {
            margin: 15px 0;
            padding: 10px 0;
            border-bottom: 1px solid #3c4043;
        }
        .info-title {
            color: #8f98a0;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            color: #c7d5e0;
            font-weight: bold;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        .stat-item {
            text-align: center;
            background: #16202d;
            padding: 15px;
            border-radius: 3px;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #66c0f4;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #8f98a0;
            text-transform: uppercase;
        }
        .game-description {
            background: #1e2329;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
        }
        .description-title {
            color: #66c0f4;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        .description-text {
            color: #c7d5e0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    <div class="container" style="margin-top: 80px;">
        <div class="game-header">
            <h1 class="game-title"><?= htmlspecialchars($game['title']) ?></h1>
            <?php if ($game['genre']): ?>
                <div class="game-genre"><?= htmlspecialchars($game['genre']) ?></div>
            <?php endif; ?>
            <?php $statusLabel = $mapStatusToLabel[$game['status']] ?? '[HideMe]'; ?>
            <?php if ($statusLabel !== '[HideMe]'): ?>
            <span class="game-status status-<?= strtolower($game['status']) ?>">
                <?= htmlspecialchars($statusLabel) ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="game-content">
            <div class="game-media">
                <?php if ($game['thumbnail_big']): ?>
                    <img src="<?= htmlspecialchars($game['thumbnail_big']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" class="game-thumbnail">
                <?php endif; ?>
                
                <?php if (!empty($screenshots)): ?>
                    <div class="screenshots">
                        <?php foreach ($screenshots as $screenshot): ?>
                            <img src="<?= htmlspecialchars($screenshot) ?>" alt="Screenshot" class="screenshot">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="game-info">
                <?php if ($user && $user['id'] !== $game['owner_user_id']): ?>
                    <?php if ($game['in_library']): ?>
                        <button class="play-button" onclick="playGame()">Play Now</button>
                        <div class="library-actions">
                            <div class="library-status">✓ In Your Library</div>
                            <form method="POST" action="remove_from_library.php" style="display: inline;">
                                <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                                <button type="submit" class="remove-library-btn">Remove from Library</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="add_to_library.php">
                            <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                            <button type="submit" class="add-library-btn" style="width: 100%; padding: 15px; font-size: 1rem;">Add to Library</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($game['status'] === 'PLAYABLE' || ($user && (isAdmin($user['id']) || $user['id'] === $game['owner_user_id']))): ?>
                        <button class="play-button" onclick="playGame()">Play Now</button>
                    <?php else: ?>
                        <button class="play-button" disabled>Not Available</button>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="info-section">
                    <div class="info-title">Version</div>
                    <div class="info-value"><?= htmlspecialchars($game['current_version']) ?></div>
                </div>

                <div class="info-section">
                    <div class="info-title">Developer</div>
                    <div class="info-value">
                        <a href="profile.php?user=<?= urlencode($game['username']) ?>" style="color: #66c0f4; text-decoration: none;"><?= htmlspecialchars($game['display_name'] ?: $game['username']) ?></a>
                        <?php if ($user && $user['id'] !== $game['owner_user_id']): ?>
                            <a href="compose.php?to=<?= urlencode($game['username']) ?>" style="color: #00d4ff; text-decoration: none; margin-left: 10px; font-size: 0.9rem;">Message</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-section">
                    <div class="info-title">Release Date</div>
                    <div class="info-value"><?= date('M j, Y', strtotime($game['created_at'])) ?></div>
                </div>

                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($game['play_count']) ?></div>
                        <div class="stat-label">Plays</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($game['total_play_time_hours'], 1) ?>h</div>
                        <div class="stat-label">Total Playtime</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($game['description']): ?>
            <div class="game-description">
                <h2 class="description-title">About This Game</h2>
                <div class="description-text"><?= nl2br(htmlspecialchars($game['description'])) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function playGame() {
            <?php if ($game['hosting_type'] === 'URL'): ?>
                window.open('<?= htmlspecialchars($game['game_url']) ?>', '_blank');
            <?php else: ?>
                window.location.href = '/games/play.php?slug=<?= urlencode($game['slug']) ?>';
            <?php endif; ?>
        }
    </script>
</body>
</html>