<?php
require_once '../db.php';
require_once '../user/session.php';

$user = getCurrentUser();
if (!$user || $user['id'] != 1) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_video') {
        $video_id = $_POST['video_id'] ?? 0;
        $stmt = $pdo_videos->prepare("DELETE FROM videos WHERE id = ?");
        $stmt->execute([$video_id]);
        $success = 'Video deleted successfully';
    }
    
    header('Location: admin.php');
    exit;
}

try {
    // Get all videos
    $stmt = $pdo_videos->prepare("SELECT * FROM videos ORDER BY created_at DESC");
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
    <title>Admin Panel - CRZ.Videos</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-header {
            background: #d32f2f;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .video-list {
            background: #1e2329;
            border-radius: 8px;
            overflow: hidden;
        }
        .video-item {
            padding: 20px;
            border-bottom: 1px solid #3c4043;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .video-item:last-child {
            border-bottom: none;
        }
        .video-details h3 {
            margin: 0 0 5px 0;
            color: #66c0f4;
        }
        .video-meta {
            color: #8f98a0;
            font-size: 0.9rem;
        }
        .admin-actions {
            display: flex;
            gap: 10px;
        }
        .delete-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .view-btn {
            background: #2196f3;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="container" style="margin-top: 80px;">
        <div class="admin-header">
            <h1>CRZ.Videos Admin Panel</h1>
            <p>Manage videos and content moderation</p>
        </div>

        <?php if (isset($success)): ?>
            <div style="background: #4caf50; color: white; padding: 10px; border-radius: 4px; margin-bottom: 20px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <h2>All Videos (<?= count($videos) ?>)</h2>
        
        <?php if (empty($videos)): ?>
            <div class="video-list">
                <div class="video-item">
                    <p>No videos uploaded yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="video-list">
                <?php foreach ($videos as $video): ?>
                    <div class="video-item">
                        <div class="video-details">
                            <h3><?= htmlspecialchars($video['title']) ?></h3>
                            <div class="video-meta">
                                By <?= htmlspecialchars($video['display_name'] ?: $video['username']) ?> • 
                                Status: <?= htmlspecialchars($video['status']) ?> • 
                                <?= number_format($video['views']) ?> views • 
                                Uploaded <?= date('M j, Y', strtotime($video['created_at'])) ?>
                            </div>
                            <?php if ($video['description']): ?>
                                <p style="margin: 10px 0 0 0; color: #c7d5e0;"><?= htmlspecialchars(substr($video['description'], 0, 150)) ?><?= strlen($video['description']) > 150 ? '...' : '' ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="admin-actions">
                            <a href="watch.php?id=<?= $video['id'] ?>" class="view-btn">View</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
                                <button type="submit" name="action" value="delete_video" class="delete-btn" onclick="return confirm('Delete this video?')">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>