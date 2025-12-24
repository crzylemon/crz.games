<?php
require_once '../db.php';
require_once '../user/session.php';

$user = getCurrentUser();
$video_id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo_videos->prepare("SELECT * FROM videos WHERE id = ? AND (status = 'PUBLIC' OR owner_user_id = ?)");
    $stmt->execute([$video_id, $user['id'] ?? 0]);
    $video = $stmt->fetch();
    
    if (!$video) {
        header('HTTP/1.0 404 Not Found');
        echo "Video not found";
        exit;
    }
    
    // Get user info from main database
    $stmt = $pdo->prepare("SELECT username, display_name FROM accounts WHERE id = ?");
    $stmt->execute([$video['owner_user_id']]);
    $user_info = $stmt->fetch();
    $video['username'] = $user_info['username'] ?? 'Unknown';
    $video['display_name'] = $user_info['display_name'] ?? 'Unknown';
    
    // Increment view count
    $stmt = $pdo_videos->prepare("UPDATE videos SET views = views + 1 WHERE id = ?");
    $stmt->execute([$video_id]);
    
    // Handle comment submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && $user) {
        $comment = trim($_POST['comment']);
        if (!empty($comment)) {
            $stmt = $pdo_videos->prepare("INSERT INTO comments (video_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$video_id, $user['id'], $comment]);
        }
    }
    
    // Get comments
    $stmt = $pdo_videos->prepare("SELECT * FROM comments WHERE video_id = ? ORDER BY created_at DESC");
    $stmt->execute([$video_id]);
    $comments = $stmt->fetchAll();
    
    // Get video tags
    $stmt = $pdo_videos->prepare("SELECT t.name FROM tags t JOIN video_tags vt ON t.id = vt.tag_id WHERE vt.video_id = ?");
    $stmt->execute([$video_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get user info for comments
    foreach ($comments as &$comment) {
        $stmt = $pdo->prepare("SELECT username, display_name FROM accounts WHERE id = ?");
        $stmt->execute([$comment['user_id']]);
        $comment_user = $stmt->fetch();
        $comment['username'] = $comment_user['username'] ?? 'Unknown';
        $comment['display_name'] = $comment_user['display_name'] ?? 'Unknown';
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
        .trailer-player {
            position: relative;
            width: 100%;
            height: 100%;
            background: #000;
            border-radius: 4px;
            overflow: hidden;
        }
        .trailer-video {
            width: 100%;
            height: 100%;
        }
        .trailer-controls {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .play-pause-btn, .volume-btn, .fullscreen-btn {
            background: none;
            border: none;
            color: white;
            font-size: 16px;
            cursor: pointer;
            padding: 5px;
        }
        .progress-bar {
            flex: 1;
            height: 4px;
            background: rgba(255,255,255,0.3);
            border-radius: 2px;
            cursor: pointer;
        }
        .progress-fill {
            height: 100%;
            background: #66c0f4;
            border-radius: 2px;
            width: 0%;
            transition: width 0.27s linear; /* make it move smoothly */
        }
        .time-display {
            color: white;
            font-size: 12px;
        }
        .comments-section {
            background: #1e2329;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .comment-form {
            margin-bottom: 20px;
        }
        .comment-input {
            width: 100%;
            padding: 12px;
            background: #16202d;
            border: 1px solid #3c4043;
            border-radius: 4px;
            color: #c7d5e0;
            resize: vertical;
            min-height: 80px;
        }
        .comment-btn {
            background: #66c0f4;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .comment {
            border-bottom: 1px solid #3c4043;
            padding: 15px 0;
        }
        .comment:last-child {
            border-bottom: none;
        }
        .comment-author {
            color: #66c0f4;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .comment-text {
            color: #c7d5e0;
            line-height: 1.5;
        }
        .comment-date {
            color: #8f98a0;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        .tags {
            margin-top: 15px;
        }
        .tag {
            display: inline-block;
            background: #66c0f4;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-right: 8px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="container" style="margin-top: 80px;">
        <div class="video-player-container">
            <div id="video-player" style="width: 100%; height: 500px; background: #000; border-radius: 4px;"></div>
            <script src="js/trailer-player.js"></script>
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
            
            <?php if (!empty($tags)): ?>
                <div class="tags">
                    <?php foreach ($tags as $tag): ?>
                        <span class="tag"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="index.php" style="color: #66c0f4; text-decoration: none;">← Back to Videos</a>
        </div>
        
        <div class="comments-section">
            <h3 style="color: #c7d5e0; margin-bottom: 20px;"><?= count($comments) ?> Comments</h3>
            
            <?php if ($user): ?>
                <form method="POST" class="comment-form">
                    <textarea name="comment" class="comment-input" placeholder="Add a comment..."></textarea>
                    <button type="submit" class="comment-btn">Post Comment</button>
                </form>
            <?php else: ?>
                <p style="color: #8f98a0; margin-bottom: 20px;">
                    <a href="../user/login.php" style="color: #66c0f4;">Sign in</a> to leave a comment
                </p>
            <?php endif; ?>
            
            <?php foreach ($comments as $comment): ?>
                <div class="comment">
                    <div class="comment-author"><?= htmlspecialchars($comment['display_name'] ?: $comment['username']) ?></div>
                    <div class="comment-text"><?= nl2br(htmlspecialchars($comment['comment'])) ?></div>
                    <div class="comment-date"><?= date('M j, Y \a\t g:i A', strtotime($comment['created_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>