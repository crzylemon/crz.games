<?php
require_once '../db.php';
require_once '../user/session.php';

function generateVideoId() {
    $chars = '1234567890-_abcdefghjiklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $id = '';
    for ($i = 0; $i < 16; $i++) {
        $id .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $id;
}

$user = getCurrentUser();
if (!$user) {
    header('Location: ../user/login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'PUBLIC';
    
    if (empty($title)) {
        $error = 'Title is required';
    } elseif (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Video file is required';
    } else {
        try {
            // Create upload directories
            $video_dir = "uploads/videos/";
            $thumb_dir = "uploads/thumbnails/";
            if (!is_dir($video_dir)) mkdir($video_dir, 0755, true);
            if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0755, true);
            
            // Generate unique video ID
            do {
                $video_id = generateVideoId();
                $stmt = $pdo_videos->prepare("SELECT id FROM videos WHERE id = ?");
                $stmt->execute([$video_id]);
            } while ($stmt->fetch());
            
            // Insert video record
            $stmt = $pdo_videos->prepare("INSERT INTO videos (id, title, description, video_path, owner_user_id, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$video_id, $title, $description, '', $user['id'], $status]);
            
            // Handle video upload
            $video_ext = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
            $video_filename = $video_id . '_video.' . $video_ext;
            $video_path = $video_dir . $video_filename;
            move_uploaded_file($_FILES['video_file']['tmp_name'], $video_path);
            
            // Handle thumbnail upload
            $thumb_path = '';
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $thumb_ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                $thumb_filename = $video_id . '_thumb.' . $thumb_ext;
                $thumb_path = $thumb_dir . $thumb_filename;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumb_path);
            }
            
            // Update video with file paths
            $stmt = $pdo_videos->prepare("UPDATE videos SET video_path = ?, thumbnail_path = ? WHERE id = ?");
            $stmt->execute([$video_path, $thumb_path, $video_id]);
            
            header('Location: watch.php?id=' . $video_id);
            exit;
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Video - CRZ.Videos</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .upload-form {
            background: #1e2329;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            color: #66c0f4;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px;
            background: #16202d;
            border: 1px solid #3c4043;
            border-radius: 4px;
            color: #c7d5e0;
            font-size: 1rem;
        }
        .form-textarea {
            height: 100px;
            resize: vertical;
        }
        .submit-button {
            background: linear-gradient(90deg, #06bfff 0%, #2d73ff 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }
        .error {
            background: #d32f2f;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="container" style="margin-top: 80px;">
        <div class="game-header">
            <h1 class="game-title">Upload Video</h1>
            <div class="game-genre">Share your content with the community</div>
            <div style="margin-top: 15px;">
                <a href="index.php" style="color: #66c0f4; text-decoration: none;">← Back to Videos</a>
            </div>
        </div>

        <div class="upload-form">
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label" for="title">Title *</label>
                    <input type="text" id="title" name="title" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-textarea"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="video_file">Video File *</label>
                    <input type="file" id="video_file" name="video_file" class="form-input" accept="video/*" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="thumbnail">Thumbnail (optional)</label>
                    <input type="file" id="thumbnail" name="thumbnail" class="form-input" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Visibility</label>
                    <select id="status" name="status" class="form-select">
                        <option value="PUBLIC">Public</option>
                        <option value="UNLISTED">Unlisted</option>
                        <option value="DRAFT">Draft</option>
                    </select>
                </div>

                <button type="submit" class="submit-button">Upload Video</button>
            </form>
        </div>
    </div>
</body>
</html>