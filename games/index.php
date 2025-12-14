<?php
require_once '../db.php';
require_once '../user/session.php';

try {
    $stmt = $pdo->prepare("SELECT g.*, a.username FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.status IN ('PLAYABLE', 'PUBLIC_UNPLAYABLE') ORDER BY g.created_at DESC");
    $stmt->execute();
    $games = $stmt->fetchAll();
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
    <title>Games - CRZ Games</title>
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
        .nav-links {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        .nav-link {
            background: #1e2329;
            color: #66c0f4;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .upload-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(90deg, #06bfff 0%, #2d73ff 100%);
            color: white;
            border: none;
            padding: 15px 20px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    <div class="nav-links">
        <?php if ($current_user): ?>
            <a href="dashboard.php" class="nav-link">My Games</a>
            <a href="../user/logout.php" class="nav-link">Logout</a>
        <?php else: ?>
            <a href="../user/login.php" class="nav-link">Login</a>
            <a href="../user/signup.php" class="nav-link">Sign Up</a>
        <?php endif; ?>
    </div>
    
    <div class="container">
        <div class="game-header">
            <h1 class="game-title">Games Library</h1>
            <div class="game-genre">Discover and play amazing games</div>
        </div>

        <div class="games-grid">
            <?php foreach ($games as $game): ?>
                <div class="game-card" onclick="location.href='game.php?slug=<?= urlencode($game['slug']) ?>'">
                    <?php if ($game['thumbnail_small']): ?>
                        <img src="<?= htmlspecialchars($game['thumbnail_small']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" class="game-card-image">
                    <?php endif; ?>
                    <div class="game-card-content">
                        <div class="game-card-title"><?= htmlspecialchars($game['title']) ?></div>
                        <div class="game-card-author">by <a href="profile.php?user=<?= urlencode($game['username']) ?>"><?= htmlspecialchars($game['username']) ?></a></div>
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

    <?php if ($current_user): ?>
        <button class="upload-button" onclick="location.href='upload.php'">+ Upload Game</button>
    <?php endif; ?>
</body>
</html>