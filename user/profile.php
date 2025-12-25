<?php
require_once '../db.php';
require_once '../user/session.php';

$current_user = getCurrentUser();
$username = $_GET['user'] ?? '';

if (empty($username)) {
    header('Location: /');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
    $stmt->execute([$username]);
    $profile_user = $stmt->fetch();
    
    if (!$profile_user) {
        header('HTTP/1.0 404 Not Found');
        echo "User not found";
        exit;
    }
    
    // Get user's videos
    $stmt = $pdo_videos->prepare("SELECT * FROM videos WHERE owner_user_id = ? AND status = 'PUBLIC' ORDER BY created_at DESC LIMIT 6");
    $stmt->execute([$profile_user['id']]);
    $videos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile_user['display_name'] ?: $profile_user['username']) ?> - CRZ.Network</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .profile-header {
            background: #1e2329;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            text-align: center;
        }
        .profile-name {
            font-size: 2rem;
            font-weight: bold;
            color: #c7d5e0;
            margin-bottom: 10px;
        }
        .profile-username {
            color: #66c0f4;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        .profile-bio {
            color: #8f98a0;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto 20px;
        }
        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
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
            color: #8f98a0;
            font-size: 0.9rem;
        }
        .videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
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
            transform: translateY(-3px);
        }
        .video-thumbnail {
            width: 100%;
            height: 140px;
            object-fit: cover;
        }
        .video-info {
            padding: 12px;
        }
        .video-title {
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 5px;
            color: #c7d5e0;
        }
        .video-views {
            color: #8f98a0;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include '../videos/includes/banner.php'; ?>
    <?php include '../videos/includes/account_nav.php'; ?>
    
    <div class="container" style="margin-top: 80px;">
        <div class="profile-header">
            <div class="profile-name"><?= htmlspecialchars($profile_user['display_name'] ?: $profile_user['username']) ?></div>
            <div class="profile-username">@<?= htmlspecialchars($profile_user['username']) ?></div>
            
            <?php if (!empty($profile_user['bio'])): ?>
                <div class="profile-bio"><?= nl2br(htmlspecialchars($profile_user['bio'])) ?></div>
            <?php endif; ?>
            
            <div class="profile-stats">
                <div class="stat">
                    <div class="stat-number"><?= count($videos) ?></div>
                    <div class="stat-label">Videos</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?= date('M Y', strtotime($profile_user['created_at'])) ?></div>
                    <div class="stat-label">Joined</div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($videos)): ?>
            <div style="background: #1e2329; border-radius: 8px; padding: 20px;">
                <h3 style="color: #c7d5e0; margin-bottom: 15px;">Recent Videos</h3>
                <div class="videos-grid">
                    <?php foreach ($videos as $video): ?>
                        <div class="video-card" onclick="location.href='../videos/watch.php?id=<?= $video['id'] ?>'">
                            <?php if ($video['thumbnail_path'] && file_exists('../videos/' . $video['thumbnail_path'])): ?>
                                <img src="../videos/<?= htmlspecialchars($video['thumbnail_path']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" class="video-thumbnail">
                            <?php else: ?>
                                <div class="video-thumbnail" style="background: linear-gradient(135deg, #1e2329 0%, #2a3441 100%); display: flex; align-items: center; justify-content: center; color: #8f98a0; font-size: 2rem;">▶</div>
                            <?php endif; ?>
                            <div class="video-info">
                                <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
                                <div class="video-views"><?= number_format($video['views']) ?> views</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <a href="/" style="color: #66c0f4; text-decoration: none;">← Back to Home</a>
        </div>
    </div>
</body>
</html>