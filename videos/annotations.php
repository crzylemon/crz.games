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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    header('Location: annotations.php?id=' . $video_id);
    exit;
}

$stmt = $pdo_videos->prepare("SELECT * FROM annotations WHERE video_id = ? ORDER BY start_time");
$stmt->execute([$video_id]);
$annotations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Annotations - <?= htmlspecialchars($video['title']) ?></title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container" style="margin-top: 20px;">
        <h1>Edit Annotations: <?= htmlspecialchars($video['title']) ?></h1>
        
        <div style="display: grid; grid-template-columns: 1fr 300px; gap: 20px;">
            <div>
                <div id="video-player" style="width: 100%; height: 400px; background: #000; position: relative;"></div>
                <div id="annotation-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"></div>
            </div>
            
            <div style="background: #1e2329; padding: 20px; border-radius: 8px;">
                <h3>Add Annotation</h3>
                <form method="POST" id="annotation-form">
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
        </div>
    </div>
    
    <script src="js/trailer-player.js"></script>
    <script>
        const player = new TrailerPlayer(document.getElementById('video-player'), '<?= htmlspecialchars($video['video_path']) ?>');
        
        document.getElementById('video-player').addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;
            const currentTime = player.video.currentTime;
            
            document.getElementById('start_time').value = currentTime;
            document.getElementById('end_time').value = currentTime + 5;
            document.getElementById('x_percent').value = x;
            document.getElementById('y_percent').value = y;
        });
    </script>
</body>
</html>