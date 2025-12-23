<?php
require_once '../db.php';
require_once '../user/session.php';

$user = getCurrentUser();
$video_id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT v.*, a.username, a.display_name FROM videos v JOIN accounts a ON v.owner_user_id = a.id WHERE v.id = ? AND (v.status = 'PUBLIC' OR v.owner_user_id = ?)");
    $stmt->execute([$video_id, $user['id'] ?? 0]);
    $video = $stmt->fetch();
    
    if (!$video) {
        header('HTTP/1.0 404 Not Found');
        echo "Video not found";
        exit;
    }
    
    // Increment view count
    $stmt = $pdo->prepare("UPDATE videos SET views = views + 1 WHERE id = ?");
    $stmt->execute([$video_id]);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($video['title']) ?> - CRZ.Videos</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .video-player-container {
            background: #1e2329;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .video-details {
            background: #1e2329;
            border-radius: 8px;
            padding: 20px;
        }
        .video-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #c7d5e0;
        }
        .video-meta {
            color: #8f98a0;
            margin-bottom: 15px;
        }
        .video-author {
            color: #66c0f4;
            font-weight: bold;
        }
        .video-description {
            color: #c7d5e0;
            line-height: 1.6;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <?php include '../games/includes/banner.php'; ?>
    <?php include '../games/includes/account_nav.php'; ?>
    
    <div class="container" style="margin-top: 80px;">
        <div class="video-player-container">
            <div id="video-player" style="width: 100%; height: 500px; background: #000; border-radius: 4px;"></div>
            <script src="../games/js/trailer-player.js"></script>
            <script>
                new TrailerPlayer(document.getElementById('video-player'), '<?= htmlspecialchars($video['video_path']) ?>');
            </script>
        </div>
        
        <div class="video-details">
            <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
            <div class="video-meta">
                <span class="video-author"><?= htmlspecialchars($video['display_name'] ?: $video['username']) ?></span> • 
                <?= number_format($video['views']) ?> views • 
                <?= date('M j, Y', strtotime($video['created_at'])) ?>
            </div>
            
            <?php if ($video['description']): ?>
                <div class="video-description">
                    <?= nl2br(htmlspecialchars($video['description'])) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="index.php" style="color: #66c0f4; text-decoration: none;">← Back to Videos</a>
        </div>
    </div>
</body>
</html>