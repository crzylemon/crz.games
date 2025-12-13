<?php
require_once '../db.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM games WHERE status IN ('PLAYABLE', 'PUBLIC_UNPLAYABLE') ORDER BY created_at DESC");
    $stmt->execute();
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
                        <?php if ($game['genre']): ?>
                            <div class="game-card-genre"><?= htmlspecialchars($game['genre']) ?></div>
                        <?php endif; ?>
                        <span class="game-status status-<?= strtolower($game['status']) ?>">
                            <?= htmlspecialchars($game['status']) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <button class="upload-button" onclick="location.href='upload.php'">+ Upload Game</button>
</body>
</html>