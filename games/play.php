<?php
require_once '../db.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: /games/');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM games WHERE slug = ? AND status = 'PLAYABLE'");
    $stmt->execute([$slug]);
    $game = $stmt->fetch();
    
    if (!$game) {
        header('HTTP/1.0 404 Not Found');
        echo "Game not found or not playable";
        exit;
    }
    
    // Update play count
    $updateStmt = $pdo->prepare("UPDATE games SET play_count = play_count + 1 WHERE id = ?");
    $updateStmt->execute([$game['id']]);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$gameUrl = $game['hosting_type'] === 'URL' ? $game['game_url'] : "/games/uploads/games/{$game['slug']}/{$game['entry_file']}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playing <?= htmlspecialchars($game['title']) ?> - CRZ Games</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #1b2838;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .game-frame {
            width: 100vw;
            height: 100vh;
            border: none;
        }
        .loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <h2>Loading <?= htmlspecialchars($game['title']) ?>...</h2>
    </div>
    <iframe src="<?= htmlspecialchars($gameUrl) ?>" class="game-frame" onload="document.getElementById('loading').style.display='none'"></iframe>
</body>
</html>