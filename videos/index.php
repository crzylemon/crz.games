<?php
require_once '../db.php';
require_once '../user/session.php';

$user = getCurrentUser();

try {
    // Create videos table if it doesn't exist
    $pdo_videos->exec("CREATE TABLE IF NOT EXISTS videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        video_path VARCHAR(500) NOT NULL,
        thumbnail_path VARCHAR(500),
        owner_user_id INT NOT NULL,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('DRAFT', 'PUBLIC', 'UNLISTED') DEFAULT 'PUBLIC'
    )");
    
    // Get recent videos
    $stmt = $pdo_videos->prepare("SELECT * FROM videos WHERE status = 'PUBLIC' ORDER BY created_at DESC LIMIT 12");
    $stmt->execute();
    $videos = $stmt->fetchAll();
    
    // Get user info for each video
    foreach ($videos as &$video) {
        $stmt = $pdo->prepare("SELECT username, display_name FROM accounts WHERE id = ?");
        $stmt->execute([$video['owner_user_id']]);
        $user_info = $stmt->fetch();
        $video['username'] = $user_info['username'] ?? 'Unknown';
        $video['display_name'] = $user_info['display_name'] ?? 'Unknown';
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRZ.Videos</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .video-card {
            background: #1e2329;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .video-card:hover {
            transform: translateY(-5px);
        }
        .video-thumbnail {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .video-info {
            padding: 15px;
        }
        .video-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 8px;
            color: #c7d5e0;
        }
        .video-author {
            color: #66c0f4;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .video-views {
            color: #8f98a0;
            font-size: 0.8rem;
        }
        .upload-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #06bfff 0%, #2d73ff 100%);
            color: white;
            border: none;
            padding: 15px 20px;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    <?php include '../games/includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="container" style="margin-top: 80px;">
        <div class="game-header">
            <h1 class="game-title">CRZ.Videos</h1>
            <div class="game-genre">Share and discover amazing videos</div>
        </div>

        <div class="videos-grid">
            <?php foreach ($videos as $video): ?>
                <div class="video-card" onclick="location.href='watch.php?id=<?= $video['id'] ?>'">
                    <?php if ($video['thumbnail_path']): ?>
                        <img src="<?= htmlspecialchars($video['thumbnail_path']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" class="video-thumbnail">
                    <?php endif; ?>
                    <div class="video-info">
                        <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
                        <div class="video-author">by <?= htmlspecialchars($video['display_name'] ?: $video['username']) ?></div>
                        <div class="video-views"><?= number_format($video['views']) ?> views</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($user): ?>
        <button class="upload-btn" onclick="location.href='upload.php'">+ Upload Video</button>
    <?php endif; ?>
</body>
</html>