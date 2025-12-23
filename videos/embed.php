<?php
require_once '../db.php';

$video_id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo_videos->prepare("SELECT * FROM videos WHERE id = ? AND status = 'PUBLIC'");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();
    
    if (!$video) {
        header('HTTP/1.0 404 Not Found');
        echo "Video not found";
        exit;
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
    <style>
        body { margin: 0; padding: 0; background: #000; font-family: Arial, sans-serif; }
        .embed-container { position: relative; width: 100%; height: 100vh; }
        .trailer-player {
            position: relative;
            width: 100%;
            height: 100%;
            background: #000;
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
            transition: width 0.27s linear;
        }
        .time-display {
            color: white;
            font-size: 12px;
        }
        .embed-overlay { position: absolute; top: 10px; right: 10px; }
        .watch-link { 
            background: rgba(0,0,0,0.8); 
            color: #66c0f4; 
            padding: 8px 12px; 
            text-decoration: none; 
            border-radius: 4px; 
            font-size: 14px;
        }
        .watch-link:hover { background: rgba(0,0,0,0.9); }
    </style>
</head>
<body>
    <div class="embed-container">
        <div id="video-player" style="width: 100%; height: 100%;"></div>
        <div class="embed-overlay">
            <a href="/videos/watch.php?id=<?= $video_id ?>" target="_blank" class="watch-link">Watch on CRZ.Videos</a>
        </div>
    </div>
    
    <script src="js/trailer-player.js"></script>
    <script>
        new TrailerPlayer(document.getElementById('video-player'), '<?= htmlspecialchars($video['video_path']) ?>');
    </script>
</body>
</html>