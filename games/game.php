<?php
require_once '../db.php';
require_once '../user/session.php';
$user = getCurrentUser();
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: /games/');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM games WHERE slug = ?");
    $stmt->execute([$slug]);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($game['title']) ?> - CRZ Games</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <div class="game-header">
            <h1 class="game-title"><?= htmlspecialchars($game['title']) ?></h1>
            <?php if ($game['genre']): ?>
                <div class="game-genre"><?= htmlspecialchars($game['genre']) ?></div>
            <?php endif; ?>
            <span class="game-status status-<?= strtolower($game['status']) ?>">
                <?= htmlspecialchars($game['status']) ?>
            </span>
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
                <?php if ($game['status'] === 'PLAYABLE' || $user['id'] === 1 || $user['id'] === $game['owner_user_id']): ?>
                    <button class="play-button" onclick="playGame()">Play Now</button>
                <?php else: ?>
                    <button class="play-button" disabled>Not Available</button>
                <?php endif; ?>

                <div class="info-section">
                    <div class="info-title">Version</div>
                    <div class="info-value"><?= htmlspecialchars($game['current_version']) ?></div>
                </div>

                <div class="info-section">
                    <div class="info-title">Developer</div>
                    <div class="info-value">User #<?= htmlspecialchars($game['owner_user_id']) ?></div>
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