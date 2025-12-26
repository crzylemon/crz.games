<?php
require_once '../db.php';
require_once '../user/session.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../user/login.php');
    exit;
}

$video_id = $_GET['id'] ?? '';
if (empty($video_id)) {
    header('Location: index.php');
    exit;
}

// Verify user owns the video
$stmt = $pdo_videos->prepare("SELECT * FROM videos WHERE id = ? AND owner_user_id = ?");
$stmt->execute([$video_id, $user['id']]);
$video = $stmt->fetch();

if (!$video) {
    header('Location: index.php');
    exit;
}

// Handle annotation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annotation'])) {
    $stmt = $pdo_videos->prepare("INSERT INTO annotations (video_id, start_time, end_time, x_percent, y_percent, width_percent, height_percent, text, link_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $video_id,
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['x_percent'],
        $_POST['y_percent'],
        $_POST['width_percent'],
        $_POST['height_percent'],
        $_POST['text'],
        $_POST['link_url']
    ]);
    header('Location: editor.php?id=' . $video_id);
    exit;
}

// Handle caption submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['caption'])) {
    $stmt = $pdo_videos->prepare("INSERT INTO captions (video_id, language, label, start_time, end_time, text) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $video_id,
        $_POST['language'],
        $_POST['label'],
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['caption_text']
    ]);
    header('Location: editor.php?id=' . $video_id);
    exit;
}

$stmt = $pdo_videos->prepare("SELECT * FROM annotations WHERE video_id = ? ORDER BY start_time");
$stmt->execute([$video_id]);
$annotations = $stmt->fetchAll();

$stmt = $pdo_videos->prepare("SELECT * FROM captions WHERE video_id = ? ORDER BY start_time");
$stmt->execute([$video_id]);
$captions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Editor - <?= htmlspecialchars($video['title']) ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .tab-btn {
            background: #3c4043;
            color: #c7d5e0;
            border: none;
            padding: 10px 20px;
            margin-right: 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .tab-btn.active {
            background: #66c0f4;
        }
        .video-player-container {
            background: #1e2329;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
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
            transition: width 0.27s linear;
        }
        .time-display {
            color: white;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container" style="margin-top: 20px;">
        <h1>Video Editor: <?= htmlspecialchars($video['title']) ?></h1>
        
        <div style="margin-bottom: 20px;">
            <button onclick="showTab('annotations')" id="annotations-tab" class="tab-btn active">Annotations</button>
            <button onclick="showTab('captions')" id="captions-tab" class="tab-btn">Captions</button>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 300px; gap: 20px;">
            <div class="video-player-container">
                <div id="video-player" style="width: 100%; height: 400px; background: #000; position: relative;"></div>
                <div id="annotation-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"></div>
            </div>
            
            <div style="background: #1e2329; padding: 20px; border-radius: 8px;">
                <div id="annotations-panel">
                    <h3>Add Annotation</h3>
                    <form method="POST" id="annotation-form">
                        <input type="hidden" name="annotation" value="1">
                        <input type="hidden" name="start_time" id="start_time">
                        <input type="hidden" name="end_time" id="end_time">
                        <input type="hidden" name="x_percent" id="x_percent">
                        <input type="hidden" name="y_percent" id="y_percent">
                        <input type="hidden" name="width_percent" id="width_percent" value="20">
                        <input type="hidden" name="height_percent" id="height_percent" value="10">
                        
                        <div style="margin-bottom: 10px;">
                            <label>Text:</label>
                            <textarea name="text" required style="width: 100%; padding: 8px;"></textarea>
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label>Link URL (optional):</label>
                            <input type="url" name="link_url" style="width: 100%; padding: 8px;">
                        </div>
                        
                        <button type="submit">Add Annotation</button>
                    </form>
                    
                    <h3>Existing Annotations</h3>
                    <?php foreach ($annotations as $annotation): ?>
                        <div style="border: 1px solid #3c4043; padding: 10px; margin-bottom: 10px;">
                            <strong><?= htmlspecialchars($annotation['text']) ?></strong><br>
                            <?= $annotation['start_time'] ?>s - <?= $annotation['end_time'] ?>s
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div id="captions-panel" style="display: none;">
                    <h3>Add Caption</h3>
                    <form method="POST" id="caption-form">
                        <input type="hidden" name="caption" value="1">
                        <input type="hidden" name="start_time" id="caption_start_time">
                        <input type="hidden" name="end_time" id="caption_end_time">
                        
                        <div style="margin-bottom: 10px;">
                            <label>Language:</label>
                            <input type="text" name="language" value="en" style="width: 100%; padding: 8px;">
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label>Label:</label>
                            <input type="text" name="label" value="English" style="width: 100%; padding: 8px;">
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label>Caption Text:</label>
                            <textarea name="caption_text" required style="width: 100%; padding: 8px;"></textarea>
                        </div>
                        
                        <button type="submit">Add Caption</button>
                    </form>
                    
                    <h3>Existing Captions</h3>
                    <?php foreach ($captions as $caption): ?>
                        <div style="border: 1px solid #3c4043; padding: 10px; margin-bottom: 10px;">
                            <strong><?= htmlspecialchars($caption['text'] ?? 'File: ' . basename($caption['file_path'] ?? '')) ?></strong><br>
                            <?= $caption['start_time'] ? $caption['start_time'] . 's - ' . $caption['end_time'] . 's' : 'File-based' ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/trailer-player.js"></script>
    <script>
        const player = new TrailerPlayer(document.getElementById('video-player'), '<?= htmlspecialchars($video['video_path']) ?>');
        
        function showTab(tab) {
            document.getElementById('annotations-panel').style.display = tab === 'annotations' ? 'block' : 'none';
            document.getElementById('captions-panel').style.display = tab === 'captions' ? 'block' : 'none';
            document.getElementById('annotations-tab').className = tab === 'annotations' ? 'tab-btn active' : 'tab-btn';
            document.getElementById('captions-tab').className = tab === 'captions' ? 'tab-btn active' : 'tab-btn';
        }
        
        document.getElementById('video-player').addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;
            const currentTime = player.video.currentTime;
            
            // For annotations
            document.getElementById('start_time').value = currentTime;
            document.getElementById('end_time').value = currentTime + 5;
            document.getElementById('x_percent').value = x;
            document.getElementById('y_percent').value = y;
            
            // For captions
            document.getElementById('caption_start_time').value = currentTime;
            document.getElementById('caption_end_time').value = currentTime + 3;
        });
    </script>
</body>
</html>